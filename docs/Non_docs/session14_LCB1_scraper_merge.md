# Phase 5: LCB1 Scraper Merge — Implementation Plan

Date: 2026-03-06 (session 14)

---

## Context

LCB1 terminal (`www.lcb1.com`) has ports A0 and B1. Currently has 3 separate scraper files:
- `lcb1-full-schedule-scraper.js` — cron mode, HTTPS, loops 392 vessels (~120s)
- `lcb1-scraper.js` — single mode, Puppeteer (684 lines, human-like mouse simulation)
- `lcb1-wrapper.js` — wrapper for PHP to call lcb1-scraper.js

Goal: Replace all 3 with one unified scraper + queue-based cron (like Kerry).

---

## LCB1 API Structure

```
GET  https://www.lcb1.com/BerthSchedule
  Returns: HTML page with <select id="txtVesselName"> dropdown (~392 vessel names)
  No auth, no cookies, no session

POST https://www.lcb1.com/BerthSchedule/Detail
  Content-Type: application/x-www-form-urlencoded
  Body: vesselName=SAWASDEE SUNRISE&voyageIn=&voyageOut=&pageSize=100&page=1
  Returns: HTML table fragment

  Table columns (7):
    [0] No.  [1] Vessel Name  [2] Voyage In  [3] Voyage Out
    [4] Berthing Time (DD/MM/YYYY - HH:MM)  [5] Departure Time  [6] Terminal (A0, B1)
```

No rate limiting detected (tested 50 rapid requests on separate PC). Response time ~750ms sequential, ~2.8s parallel (10 concurrent). Server handles concurrent requests but slows down.

---

## Test Vessels (confirmed working 2026-03-06)

From UI screenshot — these 3 vessels have current data on LCB1 website:

| Vessel | Voyage | ETA (from UI) | Port | Status |
|--------|--------|---------------|------|--------|
| **KMTC XIAMEN** | **2602S** | 28-Feb 02:02 | A0 | In progress — Updated ETA 28/02 07:00, On Track |
| **SAWASDEE DENEB** | **2602S** | 01-Mar 19:59 | A0 | Completed — Updated ETA 01/03 04:00, Early |
| **KMTC XIAMEN** | **2602S** | 28-Feb 15:31 | A0 | In progress — Updated ETA 28/02 07:00, Early |

Vessel with NO current data (departed):
- SM JAKARTA / 2602W — "Not Found" in UI

Vessel with WRONG voyage match (bug — see P4.5):
- SAWASDEE SUNRISE / 2602S — UI shows "On Track" with ETA 17/03 16:00, but that's from voyage **2603S** (the only current voyage). The old 2602S voyage is gone from LCB1.

---

## Architecture Decision: Queue-Based Cron (like Kerry)

**Why queue, not full-scrape:**
- Full scrape = 392 POST requests = ~120 seconds (already disabled in cron)
- Only ~10 unique active vessels need scraping
- Future scale: 100+ vessels/day → queue handles growth with rate limiting
- Same pattern as Kerry = consistent codebase

**Rate limit config:**
- 40 requests/minute (env: `LCB1_RATE_LIMIT`)
- Queue concurrency: 1 worker (parallel requests slow down 3-4x)
- Tries: 2, Backoff: 30 seconds
- Expected cycle: ~10 vessels x ~2s = ~20 seconds

---

## Implementation Plan

### 1. Rewrite `lcb1-full-schedule-scraper.js` (single-vessel only)

**File:** `browser-automation/scrapers/lcb1-full-schedule-scraper.js`
**Was:** 187 lines, cron-only (getAllVessels loop + parseScheduleHTML)
**Now:** ~190 lines, single-vessel only (--vessel/--voyage args)

Key changes:
- Remove `scrapeFullSchedule()` and `getAllVessels()` (the 392-vessel loop)
- Remove `delay()` method
- Add `scrapeSingleVessel(vesselName, voyageCode)` — one POST to `/BerthSchedule/Detail`
- Add `findVesselMatch(vessels, vesselName, voyageCode)` — match vessel name + voyage
- Add `parseArgs()` — parse `--vessel`/`--voyage` CLI args
- Keep `parseScheduleHTML()`, `parseDate()`, `makeRequest()` (same logic, minor cleanup)
- Change `parseDate()` output from `YYYY-MM-DD HH:MM:00` to `YYYY-MM-DDThh:mm:00` (ISO format, consistent with other scrapers)
- Add request timeout (15s) to `makeRequest()`

Usage:
```bash
node scrapers/lcb1-full-schedule-scraper.js --vessel "KMTC XIAMEN" --voyage "2602S"
```

Output (single mode):
```json
{
  "success": true,
  "vessel_found": true,
  "vessel_name": "KMTC XIAMEN",
  "voyage_code": "2602S",
  "berth": "A0",
  "eta": "2026-02-28T07:00:00",
  "etd": "2026-03-01T12:00:00",
  "raw_data": { "table_row": [...], "voyage_in": "2602S", "voyage_out": "2602S" }
}
```

### 2. Rewrite `VesselTrackingService::lcb1()` (PHP live scraper)

**File:** `app/Services/VesselTrackingService.php` line 903
**Was:** Calls `laravel-wrapper.js` via `BrowserAutomationService::runNodeScript` (Puppeteer, 60s)
**Now:** Calls `node scrapers/lcb1-full-schedule-scraper.js --vessel X --voyage Y` via `proc_open` (30s)

Same pattern as `hutchison_browser()`, `tips_browser()`, `esco()`, `lcit()`:
- Build command with `escapeshellarg()`
- `proc_open` with stdout/stderr pipes
- Parse JSON response
- Handle `vessel_found: false` → `search_method: lcb1_not_found`
- Handle timeout → `search_method: lcb1_timeout_fallback`
- Handle `!success` → throw exception
- Success → `search_method: lcb1_scraper`

### 3. Create `ScrapeLC1Vessel.php` (queue job)

**File:** `app/Jobs/ScrapeLC1Vessel.php` (NEW)
**Pattern:** Same as `ScrapeKerryVessel.php`

- `implements ShouldQueue`
- Constructor: `(string $vesselName, string $voyageCode, string $portTerminal = 'A0')`
- `$tries = 2`, `$backoff = 30`
- Middleware: `RateLimited('lcb1-api')`
- `handle()`:
  - Call `node scrapers/lcb1-full-schedule-scraper.js --vessel X --voyage Y` via `proc_open`
  - Parse JSON result
  - If `vessel_found`: `VesselSchedule::updateOrCreate()` with match keys `vessel_name + port_terminal + voyage_code`
  - Log to `DailyScrapeLog` (terminal: 'lcb1')

### 4. Create `ScrapeLC1Vessels.php` (artisan command)

**File:** `app/Console/Commands/ScrapeLC1Vessels.php` (NEW)
**Pattern:** Same as `ScrapeKerryVessels.php`

- Signature: `vessel:scrape-lcb1 {--dry-run}`
- Query: `Shipment::where('status', 'in-progress')->whereIn('port_terminal', ['A0', 'B1'])->whereNotNull('vessel_id')->whereBetween('client_requested_delivery_date', [now()->subMonth(), now()->addMonth()])`
- Deduplicate by `vessel_name + voyage`
- Dispatch `ScrapeLC1Vessel` jobs to `lcb1-scraper` queue

### 5. Add rate limiter to `AppServiceProvider.php`

**File:** `app/Providers/AppServiceProvider.php`

Add in `boot()` after Kerry rate limiter:
```php
RateLimiter::for('lcb1-api', function (object $job) {
    return Limit::perMinute((int) env('LCB1_RATE_LIMIT', 40));
});
```

### 6. Add to schedule and "Run Now"

**File:** `bootstrap/app.php` line 34 (after JWD)
```php
\Illuminate\Support\Facades\Artisan::call('vessel:scrape-lcb1');
```

**File:** `app/Livewire/ScheduleManager.php` line 142 (after JWD)
```php
\Illuminate\Support\Facades\Artisan::call('vessel:scrape-lcb1');
```

### 7. Delete old files

| File | Reason |
|------|--------|
| `browser-automation/lcb1-wrapper.js` | Replaced by --vessel mode |
| `browser-automation/scrapers/lcb1-scraper.js` | 684-line Puppeteer scraper replaced |
| `browser-automation/scrapers/lcb1-full-schedule-scraper-puppeteer.js` | Old Puppeteer cron version |
| `browser-automation/scrapers/debug-lcb1-structure.js` | Debug script |
| `browser-automation/enhanced-lcb1-debug.js` | Debug script |
| `browser-automation/lcb1-debug-screenshot.png` | Debug screenshot |
| `browser-automation/lcb1-enhanced-debug.png` | Debug screenshot |
| `browser-automation/lcb1-enhanced-error.png` | Debug screenshot |
| `browser-automation/lcb1-ajax-debug.png` | Debug screenshot |
| `browser-automation/lcb1-error-1753203340851.png` | Debug screenshot |
| `browser-automation/lcb1-error-1753205964695.png` | Debug screenshot |

---

## Known Bug: Voyage Fallback Mismatch (P4.5)

**Found during this session.** When no exact voyage match exists, all scrapers (LCB1, Hutchison, TIPS, ESCO, LCIT) fall back to the first available voyage's data. This causes wrong ETAs:

Example: SAWASDEE SUNRISE / 2602S
- LCB1 only has voyages 2603S and 2603N (2602S already departed)
- Scraper returns 2603S ETA (17/03/2026 16:00) for voyage 2602S request
- UI shows green "On Track" with wrong date

**Deferred to P4.5** — fix will return fallback data with actual voyage code, UI will show orange uncertainty indicator: `17/03 16:00 (2603S)`.

For Phase 5 implementation: keep the current fallback behavior (same as all other scrapers). P4.5 will fix all scrapers together.

---

## Testing Plan

### JS scraper tests
```bash
cd /home/dragonnon2/projects/logistic_auto/browser-automation

# Test with current vessel (should return data)
node scrapers/lcb1-full-schedule-scraper.js --vessel "KMTC XIAMEN" --voyage "2602S"

# Test not-found vessel
node scrapers/lcb1-full-schedule-scraper.js --vessel "NONEXISTENT" --voyage "999X"

# Test vessel with no current schedule
node scrapers/lcb1-full-schedule-scraper.js --vessel "SM JAKARTA" --voyage "2602W"

# Test SAWASDEE SUNRISE (voyage fallback — 2603S returned for 2602S request)
node scrapers/lcb1-full-schedule-scraper.js --vessel "SAWASDEE SUNRISE" --voyage "2602S"
```

### PHP tests
```bash
# Dry run cron command
php artisan vessel:scrape-lcb1 --dry-run

# Real cron (dispatches jobs)
php artisan vessel:scrape-lcb1

# Process one job
php artisan queue:work --queue=lcb1-scraper --once

# Live ETA check
php artisan tinker
# (new VesselTrackingService)->checkVesselETAByName('KMTC XIAMEN', '2602S', 'A0')
```

### Verify DB
```sql
SELECT * FROM vessel_schedules WHERE source = 'lcb1' ORDER BY updated_at DESC LIMIT 10;
SELECT * FROM daily_scrape_logs WHERE terminal = 'lcb1' ORDER BY id DESC LIMIT 5;
```

---

## Production Setup

Same as Kerry:
1. Deploy code, no new migrations needed (jobs table already exists)
2. Optional env: `LCB1_RATE_LIMIT=40`
3. Add supervisor config for `lcb1-scraper` queue worker (or add `lcb1-scraper` to existing worker's `--queue` list)
4. No new crontab — existing `schedule:run` triggers `vessel:scrape-lcb1` via `bootstrap/app.php`

---

## Data Flow After Implementation

```
Cron fires vessel_scrape schedule
  -> vessel:scrape-schedules (Hutchison, TIPS, ESCO, LCIT -- Node.js scrapers)
  -> vessel:scrape-kerry (queue-based PHP HTTP)
  -> vessel:scrape-jwd (single PHP HTTP GET)
  -> vessel:scrape-lcb1 (NEW -- dispatches queue jobs, returns immediately)
      -> Queue worker processes jobs (rate limited 40/min)
      -> Each job: node lcb1-full-schedule-scraper.js --vessel X --voyage Y
      -> Parse result -> vessel_schedules table

Live ETA check for A0/B1 shipment:
  -> VesselTrackingService::lcb1()
  -> Check vessel_schedules DB first (instant if cron pre-cached)
  -> Fallback: node lcb1-full-schedule-scraper.js --vessel X --voyage Y (one POST, ~750ms)
```
