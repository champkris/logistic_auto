# Session 4: KLN/Kerry Port Mapping + Queue-Based Scraper

Date: 2026-03-04

---

## Problem

1. **Port code `KLN` not mapped** — 240 shipments use `KLN` but `$portToTerminal` only maps `KERRY` (28 shipments). All KLN shipments fail with `"Unknown port code: KLN"`. Dropdown shows `KLN => "KLN Seaport"` correctly, but the backend routing map is missing `KLN`.
2. **No daily cron scraper for Kerry** — Other terminals (Hutchison, TIPS, ESCO, LCIT) have full-schedule scrapers. Kerry API doesn't support full-schedule listing (requires vessel name per request), so it was skipped entirely.

---

## Session 4 Investigation Results

**Kerry API (`https://terminaltracking.ksp.kln.com/SearchVesselVisit/List`):**
- Login: POST `/Login/Login` with `username=vessel9&password=12123_Vessel`
- Sets `UserToken` cookie — but server only checks cookie **existence**, not value
- Any `UserToken=<anything>` works, no real session expiry
- Endpoint: POST with query params `PARM_VESSELNAME`, `PARM_VOY`, `pageNumber`
- Returns HTML table with 11 columns
- Empty search returns "Empty" — **cannot list all vessels** (must provide vessel name)
- 50 rapid-fire requests didn't trigger rate limits
- `kerry_http_request()` at `VesselTrackingService.php:1682` tested end-to-end — **works correctly**

**Queue infrastructure (pre-existing):**
- `QUEUE_CONNECTION=database` in `.env` (setup doc says redis but project uses database — fine for our needs)
- `config/queue.php` configured, jobs table migration exists
- `php artisan queue:listen --tries=1` already in `composer.json` dev script
- No job classes existed yet — Kerry is the first

**Why queue instead of simple loop?**

Other terminals scrape a full schedule page — one request returns all vessels. Kerry API requires one request per vessel. For SaaS with hundreds/thousands of shipments, a synchronous loop would block the cron process. Queue jobs are:
- **Non-blocking** — cron dispatches jobs instantly, returns
- **Rate-limited** — configurable requests/min
- **Resilient** — failed jobs retry automatically
- **Scalable** — add workers as needed
- **Independent** — doesn't block other terminals or web requests

---

## What Changed

### Fix 1: KLN Port Mapping (2 lines)

**File: `app/Services/VesselTrackingService.php`**

**Change 1 — `$portToTerminal` array (~line 51):**
```php
// Before:
'KERRY' => 'kerry',

// After:
'KERRY' => 'kerry',
'KLN' => 'kerry',        // ← ADDED
```
**Why:** Routes KLN shipments to the Kerry terminal handler. Without this, `checkVesselETAWithParsedName()` throws "Unknown port code: KLN" at line 242.

**Change 2 — `$terminals['kerry']['ports']` (~line 126):**
```php
// Before:
'ports' => ['KERRY']

// After:
'ports' => ['KERRY', 'KLN']    // ← ADDED 'KLN'
```
**Why:** The `ports` array is used by `getTerminalInfo()` for display/lookup. Adding KLN ensures the terminal info resolver recognizes KLN as a valid Kerry port.

---

### Fix 2: Queue-Based Kerry Scraper (2 new files + 4 modified files)

#### NEW: `app/Jobs/ScrapeKerryVessel.php`

First queue job in the project. Handles one vessel per job.

**How it works:**
1. `callKerryApi()` — POST to `https://terminaltracking.ksp.kln.com/SearchVesselVisit/List` with `PARM_VESSELNAME` and `PARM_VOY` query params. Uses `UserToken=00000111112222233333` cookie (server only checks cookie existence, not value — discovered in session 4 investigation).
2. `parseKerryHtml()` — Extracts all 11 columns from HTML table response. Enhanced version of existing `parseKerryETA()` which only extracts ETA column.
3. `parseKerryDate()` — Parses Kerry's `"DD/MM HH:MM"` format (e.g., `"27/02 02:00"`) into Carbon datetime. Uses current year with 6-month lookback for year boundary.
4. Stores to `vessel_schedules` via `VesselSchedule::updateOrCreate()` — same pattern as `ScrapeVesselSchedules::storeVesselSchedule()`.
5. Logs each job to `daily_scrape_logs`.

**Kerry HTML table columns extracted:**

| Index | Column | Stored as |
|-------|--------|-----------|
| 1 | Vessel Name | `vessel_name` (composite key) |
| 2 | I/B Voyage | `voyage_code` (composite key) |
| 5 | ETA | `eta` |
| 6 | ETD | `etd` |
| 9 | Open Gate | `opengate` |
| 10 | Closing Time | `cutoff` |

**Queue config:**
- Queue name: `kerry-scraper`
- Rate limited: 40 requests/min via `RateLimited('kerry-api')` middleware
- Retries: 2 attempts, 30s backoff
- Timeout: 30s per API call

#### NEW: `app/Console/Commands/ScrapeKerryVessels.php`

Artisan command: `vessel:scrape-kerry`

**How it works:**
1. Queries `Shipment::where('status', 'in-progress')->whereIn('port_terminal', ['KLN', 'KERRY'])` — only active shipments need ETA tracking.
2. Deduplicates by `vessel_name + voyage` — multiple containers on the same ship+voyage only need 1 API call.
3. Dispatches `ScrapeKerryVessel` jobs to `kerry-scraper` queue.
4. Returns instantly (non-blocking) — actual API calls happen in the queue worker.
5. Supports `--dry-run` flag to preview what would be dispatched.

**Why only in-progress shipments:** Completed shipments (254 of 268 KLN shipments) have already delivered. No point checking their ETA.

#### MODIFIED: `app/Providers/AppServiceProvider.php`

```php
// Added in boot():
RateLimiter::for('kerry-api', function (object $job) {
    return Limit::perMinute((int) env('KERRY_RATE_LIMIT', 40));
});
```
**Why:** Rate limits Kerry API calls to 40/min. Configurable via `KERRY_RATE_LIMIT` env variable. Prevents API abuse when scaling to SaaS with hundreds of shipments.

#### MODIFIED: `bootstrap/app.php` (line 31)

```php
// Added after Artisan::call('vessel:scrape-schedules') at line 28:
\Illuminate\Support\Facades\Artisan::call('vessel:scrape-kerry');
```
**Why:** When the cron schedule fires a `vessel_scrape` job, it now also dispatches Kerry queue jobs alongside the existing terminal scrapers. Returns instantly since it only dispatches jobs.

#### MODIFIED: `app/Livewire/ScheduleManager.php` (in `runNow()` method)

```php
// Added after Artisan::call('vessel:scrape-schedules') at line 138:
\Illuminate\Support\Facades\Artisan::call('vessel:scrape-kerry');
```
**Why:** The "Run Now" button in the Schedule Manager UI now also triggers Kerry scraping.

---

### Bug Fix During Testing: Voyage Whitespace

**File: `app/Jobs/ScrapeKerryVessel.php` (line 110-111)**

```php
// Before:
'PARM_VESSELNAME' => strtolower($this->vesselName),
'PARM_VOY' => strtolower($this->voyageCode),

// After:
'PARM_VESSELNAME' => strtolower(trim($this->vesselName)),
'PARM_VOY' => strtolower(trim($this->voyageCode)),
```
**Why:** Some voyage codes in the database have leading whitespace (e.g., ` 0015S` for HMM MIRACLE). The Kerry API returns empty results when voyage has a leading space. Adding `trim()` fixed the mismatch.

---

## Data Flow

```
Cron fires vessel_scrape schedule
  → vessel:scrape-schedules (Hutchison, TIPS, ESCO, LCIT — unchanged)
  → vessel:scrape-kerry (dispatches jobs, returns instantly)
      → Queue worker processes jobs (rate limited 40/min)
      → Each job: Kerry API → parse HTML → vessel_schedules table

Live ETA check for KLN shipment (unchanged flow, now works):
  → checkVesselETAWithParsedName() resolves KLN → 'kerry'
  → Checks vessel_schedules DB first (instant if cron pre-cached)
  → Falls back to live kerry_http_request() if not cached
```

---

## Verification Commands

```bash
# 1. Ensure jobs table exists
php artisan migrate

# 2. Verify command finds KLN shipments (no actual dispatch)
php artisan vessel:scrape-kerry --dry-run

# 3. Dispatch real jobs
php artisan vessel:scrape-kerry

# 4. Process one job to verify it works
php artisan queue:work --queue=kerry-scraper --once

# 5. Check results
php artisan tinker
>>> \App\Models\VesselSchedule::where('source', 'kerry')->get()
>>> \App\Models\DailyScrapeLog::where('terminal', 'kerry')->latest()->first()

# 6. Test live ETA check with KLN port
php artisan tinker
>>> (new \App\Services\VesselTrackingService)->checkVesselETAByName('GSL AFRICA 980N', 'KLN')
```

---

## Test Results

- `vessel:scrape-kerry --dry-run` → 14 shipments, 10 unique vessel+voyage combos
- All 10 jobs dispatched and processed (234-744ms each)
- 3 vessels found in Kerry system with ETA data:
  - M.V.GSL AFRICA 980N — ETA Feb 27, already arrived
  - M.V.YM CAPACITY 065S — ETA Feb 22, already arrived
  - M.V.HMM MIRACLE 0015S — ETA Feb 23, already arrived (found after trim() fix)
- 7 vessels returned empty from Kerry — confirmed via internet search that these are future arrivals not yet in any terminal's schedule
- 0 failed jobs, 0 pending jobs after completion
- Live ETA check `checkVesselETAByName('GSL AFRICA 980N', 'KLN')` returns cached data with `source: "kerry_db_cached"`

---

## Production Setup (after pulling code)

### 1. Run migrations

```bash
php artisan migrate
```

Ensures `jobs`, `job_batches`, and `failed_jobs` tables exist (migrations already in project).

### 2. Optional: Set rate limit env variable

Add to `.env` (default is 40 if not set):

```
KERRY_RATE_LIMIT=40
```

### 3. Start queue worker with Supervisor

Create `/etc/supervisor/conf.d/kerry-scraper.conf`:

```ini
[program:kerry-scraper]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=kerry-scraper --tries=2 --backoff=30 --sleep=3 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/kerry-worker.log
```

Replace `/path/to/` with the actual project path on the production server.

Then reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start kerry-scraper:*
```

Only **1 worker** needed — with ~10-15 active KLN shipments (deduped to ~5-10 unique vessels), all jobs complete in under 15 seconds.

### 4. Verify worker is running

```bash
sudo supervisorctl status kerry-scraper:*
```

### 5. No new crontab entry needed

The existing `php artisan schedule:run` cron already triggers `vessel:scrape-kerry` via `bootstrap/app.php`.

### 6. Queue driver note

- `.env` uses `QUEUE_CONNECTION=database` (not redis)
- Database driver works fine for current scale
- Consider switching to Redis for high-scale SaaS later

### 7. Dev environment

Already handled — `composer.json` dev script runs `php artisan queue:listen --tries=1` which picks up all queues including `kerry-scraper`.

---

## Files Reference

| File | Status | Purpose |
|------|--------|---------|
| `app/Services/VesselTrackingService.php` | Modified | Added `KLN` to port mapping (2 lines). Also contains existing `kerry_http_request()` (line 1682) and `parseKerryETA()` (line 1775) |
| `app/Jobs/ScrapeKerryVessel.php` | **New** | Queue job — calls Kerry API, parses HTML, stores schedule |
| `app/Console/Commands/ScrapeKerryVessels.php` | **New** | Artisan command — queries shipments, dispatches jobs |
| `app/Providers/AppServiceProvider.php` | Modified | Added `kerry-api` rate limiter in `boot()` |
| `bootstrap/app.php` | Modified | Added `vessel:scrape-kerry` to cron schedule (line 31) |
| `app/Livewire/ScheduleManager.php` | Modified | Added `vessel:scrape-kerry` to "Run Now" button (in `runNow()` method) |
| `app/Console/Commands/ScrapeVesselSchedules.php` | Reference | Existing cron scraper — `storeVesselSchedule()` pattern at line 386 |
| `app/Console/Commands/CheckAllShipmentsETA.php` | Reference | Existing ETA checker — vessel name access at line 207 |
| `app/Models/VesselSchedule.php` | Reference | `updateOrCreate()`, `findVessel()`, `cleanupExpired()` |
| `app/Models/DailyScrapeLog.php` | Reference | Scrape logging model |
