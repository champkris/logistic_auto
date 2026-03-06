# Plan: Merge Single & Cron Scrapers into One Codebase

Date: 2026-03-06 (updated from 2026-03-03)
Session: 7

---

## Problem Statement

Each terminal currently has **two separate scraper codepaths**:

1. **Daily Cron Scraper** — scrapes ALL vessels for a terminal, stores in `vessel_schedules` DB
   - Entry: `ScrapeVesselSchedules.php` → `BrowserAutomationService.php` → `*-full-schedule-scraper.js`
2. **Live/Single Scraper** — scrapes ONE vessel on-demand when user checks ETA
   - Entry: `VesselTrackingService.php` → `*-wrapper.js` → `*-scraper.js`

This means **duplicate JS scripts**, **duplicate PHP methods**, and **different parsing logic** for the same terminal. Bugs fixed in one don't get fixed in the other.

---

## Current File Inventory

### Per-Terminal Breakdown

| Terminal | Cron JS (full-schedule) | Live JS (wrapper + scraper) | Cron PHP | Live PHP | Technology |
|----------|------------------------|----------------------------|----------|----------|------------|
| **Hutchison** | `hutchison-full-schedule-scraper.js` | `hutchison-wrapper.js` + `hutchison-scraper.js` | `BAS::scrapeHutchisonFullSchedule()` | `VTS::hutchison_browser()` | Puppeteer |
| **TIPS** | `tips-full-schedule-scraper.js` | `tips-wrapper.js` + `tips-scraper.js` | `BAS::scrapeTipsFullSchedule()` | `VTS::tips_browser()` | Puppeteer |
| **ESCO** | `esco-full-schedule-scraper.js` | _(none — PHP calls full-schedule + filters)_ | `BAS::scrapeEscoFullSchedule()` | `VTS::esco()` | Puppeteer |
| **LCIT** | `lcit-full-schedule-scraper.js` | `lcit-wrapper.js` + `lcit-scraper.js` | `BAS::scrapeLcitFullSchedule()` | `VTS::lcit()` | HTTPS/XML |
| **LCB1** | `lcb1-full-schedule-scraper.js` | `lcb1-wrapper.js` + `lcb1-scraper.js` | `BAS::scrapeLcb1FullSchedule()` | `VTS::lcb1()` | HTTPS |
| **ShipmentLink** | `shipmentlink-full-schedule-scraper.js` | `shipmentlink-wrapper.js` + `shipmentlink-https-scraper.js` | `BAS::scrapeShipmentlinkFullSchedule()` | `VTS::shipmentlink_browser()` | HTTPS |
| **JWD** | _(none — to be created as PHP command)_ | `jwd-scraper.js` | _(none)_ | `VTS::jwd_browser()` + `VTS::jwd_http_request()` | HTTP GET (returns all vessels in one request) |
| **Kerry** | _(queue-based: `ScrapeKerryVessel.php`)_ | _(PHP HTTP: `VTS::kerry_http_request()`)_ | `ScrapeKerryVessels.php` | `VTS::kerry_http_request()` | PHP HTTP |
| **Everbuild** | _(none)_ | `everbuild-wrapper.js` + `everbuild-scraper.js` | _(none)_ | `VTS::everbuild_browser()` | Puppeteer |
| **Siam** | _(none)_ | _(n8n placeholder)_ | _(none)_ | `VTS::siam_n8n_line()` | n8n/LINE |

**BAS** = `BrowserAutomationService.php`, **VTS** = `VesselTrackingService.php`

---

## Strategy: Add `--vessel`/`--voyage` args to full-schedule scrapers

Each full-schedule scraper gets optional CLI args:
- **No args** → cron mode — scrape everything, return `{ success, vessels: [...] }`
- **With args** → single mode — scrape/filter for one vessel, return `{ success, vessel_found, vessel_name, voyage_code, eta, ... }`

```bash
# Cron mode (unchanged):
node scrapers/tips-full-schedule-scraper.js

# Single vessel mode (replaces wrapper + single scraper):
node scrapers/tips-full-schedule-scraper.js --vessel "NATTHA BHUM" --voyage "050N"
```

### Why this approach?
- Full-schedule scrapers are **already tested and working** (bugs fixed in sessions 3-5)
- Single scrapers have **different parsing logic** that sometimes doesn't match
- One script = one place to fix bugs, one place to update when website changes

---

## What Gets REMOVED vs REMAINS

### Files to REMOVE after merge (17 files)

| File | Reason |
|------|--------|
| `browser-automation/hutchison-wrapper.js` | Replaced by `--vessel` mode in full-schedule scraper |
| `browser-automation/tips-wrapper.js` | Same |
| `browser-automation/lcit-wrapper.js` | Same |
| `browser-automation/lcb1-wrapper.js` | Same |
| `browser-automation/shipmentlink-wrapper.js` | Same (450 lines!) |
| `browser-automation/everbuild-wrapper.js` | Dead code — no port mapping routes here |
| `browser-automation/laravel-wrapper.js` | Generic wrapper, replaced by direct calls |
| `browser-automation/scrapers/hutchison-scraper.js` | Replaced by full-schedule scraper's single mode |
| `browser-automation/scrapers/tips-scraper.js` | Same |
| `browser-automation/scrapers/lcit-scraper.js` | Same |
| `browser-automation/scrapers/lcit-scraper-old.js` | Old version, already unused |
| `browser-automation/scrapers/lcb1-scraper.js` | Same |
| `browser-automation/scrapers/shipmentlink-scraper.js` | Same |
| `browser-automation/scrapers/shipmentlink-https-scraper.js` | Same |
| `browser-automation/scrapers/everbuild-scraper.js` | Dead code |
| `browser-automation/scrapers/everbuild-scraper-improved.js` | Dead code |
| `browser-automation/scrapers/jwd-scraper.js` | Replaced by PHP-based `ScrapeJwdSchedule` command |
| `browser-automation/scrapers/lcb1-full-schedule-scraper-puppeteer.js` | Old Puppeteer version, replaced by HTTPS version |

### Debug files to REMOVE (4 files)

| File | Reason |
|------|--------|
| `browser-automation/scrapers/debug-lcb1-structure.js` | One-time debug script |
| `browser-automation/scrapers/debug-hutchison-structure.js` | Same |
| `browser-automation/scrapers/debug-tips-structure.js` | Same |
| `browser-automation/scrapers/debug-shipmentlink-structure.js` | Same |

### Files that REMAIN (6 JS scrapers + 1 data file)

| File | Role |
|------|------|
| `browser-automation/scrapers/hutchison-full-schedule-scraper.js` | Unified Hutchison scraper (cron + single) |
| `browser-automation/scrapers/tips-full-schedule-scraper.js` | Unified TIPS scraper (cron + single) |
| `browser-automation/scrapers/esco-full-schedule-scraper.js` | Unified ESCO scraper (cron + single) |
| `browser-automation/scrapers/lcit-full-schedule-scraper.js` | Unified LCIT scraper (cron + single) |
| `browser-automation/scrapers/lcb1-full-schedule-scraper.js` | Unified LCB1 scraper (cron + single) |
| `browser-automation/scrapers/shipmentlink-full-schedule-scraper.js` | Unified ShipmentLink scraper (cron + single) |
| `browser-automation/scrapers/shipmentlink-vessel-codes.json` | Data file for ShipmentLink vessel codes |

### New files to CREATE

| File | Role |
|------|------|
| `app/Console/Commands/ScrapeJwdSchedule.php` | JWD cron command — HTTP GET, parse all vessels, store to DB |

### Files that REMAIN UNCHANGED (not merged)

| File | Reason |
|------|--------|
| `app/Jobs/ScrapeKerryVessel.php` | Kerry uses PHP HTTP + queue — not a JS scraper |
| `app/Console/Commands/ScrapeKerryVessels.php` | Kerry queue dispatcher — separate system |

### PHP methods to CONSOLIDATE

**`VesselTrackingService.php`** — Each terminal's live method (`hutchison_browser`, `tips_browser`, etc.) gets simplified to call the full-schedule scraper with `--vessel`/`--voyage` args instead of the wrapper scripts.

**`BrowserAutomationService.php`** — Cron methods (`scrapeHutchisonFullSchedule`, etc.) stay mostly unchanged but may be simplified since the JS scripts handle both modes now.

### Dead PHP code to REMOVE

| Method | File | Reason |
|--------|------|--------|
| `everbuild_browser()` | `VesselTrackingService.php` | No port mapping routes to it — dead code |
| `jwd_browser()` | `VesselTrackingService.php` | Replaced by `jwd_http_request()` (no Puppeteer needed) |
| `tips()` | `VesselTrackingService.php` | Old HTTP-based TIPS method, replaced by `tips_browser()` |
| `esco()` (HTTP version) | `VesselTrackingService.php` | Will be replaced by unified call |
| `parseVesselData()` | `VesselTrackingService.php` | Generic HTML parser only used by dead `tips()`/`esco()` methods |
| `extractETAFromHTML()` | `VesselTrackingService.php` | Only used by `parseVesselData()` |
| `extractETAFromTable()` | `VesselTrackingService.php` | Only used by `parseVesselData()` |

---

## Implementation Phases

### Phase 1: LCIT (easiest — same HTTPS API, different params)

**Why easiest:** Both scrapers already call the same LCIT XML API. Single scraper passes `?vessel=SAMAL&voy=2606S`, cron passes `?vessel=%&voy=` (wildcard). No Puppeteer.

**JS changes to `lcit-full-schedule-scraper.js`:**
1. Parse `--vessel` and `--voyage` CLI args
2. If args provided: use them as API params (not wildcard `%`)
3. If args provided: filter result to matching vessel, return flat object:
   ```json
   { "success": true, "vessel_found": true, "vessel_name": "SAMAL", "voyage_code": "2606S", "eta": "2026-03-07 17:00", "etd": "...", "berth": "B5" }
   ```
4. If no args: behave exactly as before (return `{ success, vessels: [...] }`)

**PHP changes to `VesselTrackingService.php`:**
1. Update `lcit()` method to call `lcit-full-schedule-scraper.js --vessel X --voyage Y` via `proc_open()`
2. Remove dependency on `lcit-wrapper.js` and `lcit-scraper.js`

**Test:**
```bash
# Cron mode (unchanged):
node scrapers/lcit-full-schedule-scraper.js
# Single mode:
node scrapers/lcit-full-schedule-scraper.js --vessel "SAMAL" --voyage "2606S"
```

**Files retired:** `lcit-wrapper.js`, `lcit-scraper.js`, `lcit-scraper-old.js`

---

### Phase 2: ESCO (already semi-merged — just add filter args)

**Why easy:** ESCO only has a full-schedule scraper. The PHP `esco()` method already calls full-schedule and filters result. We just move the filtering to JS for consistency.

**JS changes to `esco-full-schedule-scraper.js`:**
1. Parse `--vessel`/`--voyage` args
2. If args provided: scrape full table (~32 vessels), filter, return flat object
3. If no args: behave as before

**PHP changes:** Update ESCO method to pass args and handle flat object response.

**Files retired:** None (no separate single scraper exists)

---

### Phase 3: TIPS (Puppeteer, good merge candidate)

**Why now:** Column mapping bug (Finding 1) is fixed in cron scraper. Cron scraper's DataTables approach is more reliable than single scraper's heuristic date extraction.

**JS changes to `tips-full-schedule-scraper.js`:**
1. Parse `--vessel`/`--voyage` args
2. If args provided: scrape full table (DataTables page size=100), filter by vessel+voyage
3. Port `generateVoyageVariations()` from `tips-scraper.js` for fuzzy voyage matching
4. Return flat object in filter mode

**PHP changes:** Update `tips_browser()` to call full-schedule scraper with args.

**Files retired:** `tips-wrapper.js`, `tips-scraper.js`

---

### Phase 4: Hutchison (Puppeteer, pagination with early exit)

**JS changes to `hutchison-full-schedule-scraper.js`:**
1. Parse `--vessel`/`--voyage` args
2. If args provided: scrape pages but **early-exit** when vessel found (don't scrape all pages)
3. Return flat object in filter mode

**PHP changes:** Update `hutchison_browser()` to call full-schedule scraper with args.

**Files retired:** `hutchison-wrapper.js`, `hutchison-scraper.js`

---

### Phase 5: LCB1 (HTTPS, skip full vessel list in single mode)

**Decision:** Use HTTPS approach for both modes. In single mode, skip fetching all 392 vessel names — directly POST for the target vessel.

**JS changes to `lcb1-full-schedule-scraper.js`:**
1. Parse `--vessel`/`--voyage` args
2. If args provided: skip vessel list fetch, directly POST to `/BerthSchedule/Detail` for target vessel
3. Return flat object in filter mode

**PHP changes:** Update `lcb1()` method.

**Files retired:** `lcb1-wrapper.js`, `lcb1-scraper.js`, `lcb1-full-schedule-scraper-puppeteer.js`

---

### Phase 6: ShipmentLink (HTTPS, smart vessel code lookup)

**JS changes to `shipmentlink-full-schedule-scraper.js`:**
1. Parse `--vessel`/`--voyage` args
2. If args provided: search vessel code by name from `shipmentlink-vessel-codes.json`, then query only that vessel's schedule
3. Return flat object in filter mode

**PHP changes:** Update `shipmentlink_browser()` method.

**Files retired:** `shipmentlink-wrapper.js`, `shipmentlink-scraper.js`, `shipmentlink-https-scraper.js`

---

### Phase 7: JWD cron scraper (PHP HTTP, no queue needed) — DONE (session 7)

**Why no queue:** Unlike Kerry (which requires a separate API call per vessel+voyage), JWD's API returns **ALL vessels in one GET request** (~75 rows). One HTTP call = full schedule. No need for queue jobs.

**Pattern:** Similar to how `ScrapeVesselSchedules.php` works for other terminals, but as a standalone command because JWD uses pure PHP HTTP (no Node.js/Puppeteer).

**API Details:**
- **URL:** `https://www.dg-net.org/th/service-api/shipping-schedule`
- **Method:** GET (no auth, no params)
- **Response:** HTML page with `<table>` containing all vessel rows
- **Row structure:**
  ```html
  <tr>
    <td class="no">1</td>
    <td>INCHEON VOYAGER</td>              <!-- vessel name -->
    <td class="in">2602S</td>             <!-- voyage IN (arrival) -->
    <td class="out"></td>                  <!-- voyage OUT (departure) -->
    <td class="arrival">06 Mar 2026 05:00:00</td>   <!-- ETA -->
    <td class="departure"></td>            <!-- ETD -->
    <td>A0-LCMT</td>                      <!-- berth-terminal -->
  </tr>
  ```
- **Date format:** `"06 Mar 2026 05:00:00"` (full date with year — easier than Kerry's DD/MM HH:MM)
- **Special:** Same vessel appears in TWO rows — one for arrival (voyage IN + arrival date), one for departure (voyage OUT + departure date). Must merge into single `vessel_schedules` record.
- **Berth column includes terminal:** `A0-LCMT`, `C1C2-HUTCHISON`, `B4-TIPS`, `B3-ESCO`, etc. This is a cross-terminal schedule. We only store the berth part, not the terminal suffix.

**New file: `app/Console/Commands/ScrapeJwdSchedule.php`**

```php
// Signature: vessel:scrape-jwd {--dry-run}
//
// Flow:
// 1. HTTP GET to JWD API (reuse logic from VTS::jwd_http_request)
// 2. Parse HTML with DOMDocument + XPath (reuse logic from VTS::parseJWDScheduleHTML)
//    - But parse ALL rows, not filter for one vessel
// 3. Group rows by vessel_name + voyage to merge arrival/departure into one record
// 4. For each vessel: VesselSchedule::updateOrCreate()
//    - Match keys: vessel_name (uppercase), port_terminal ('JWD'), voyage_code
//    - Store: eta (from arrival row), etd (from departure row), berth, source='jwd'
// 5. Log to DailyScrapeLog
```

**Key implementation details:**
- Reuses existing `formatJWDDateTime()` logic from `VesselTrackingService.php`
- Groups arrival + departure rows by vessel name to get both ETA and ETD
- Port terminal stored as `'JWD'` (matches existing `$portToTerminal` mapping)
- Berth: extract just the berth code from `"A0-LCMT"` → `"A0"` (split on `-`)
- No rate limiting needed — single HTTP request
- No queue needed — returns all data in one response (~75 rows)

**Row merging example:**
```
Row 1: INCHEON VOYAGER | in=2602S | arrival=06 Mar 2026 05:00:00  (arrival row)
Row 2: INCHEON VOYAGER | out=2602S | departure=07 Mar 2026 00:01:59 (departure row)
                                    ↓ merge ↓
vessel_schedules: vessel=INCHEON VOYAGER, voyage=2602S, eta=2026-03-06 05:00, etd=2026-03-07 00:01, berth=A0
```

**PHP changes to `VesselTrackingService.php`:**
- Keep `jwd_http_request()` as the live/single scraper method (it works fine)
- Remove `jwd_browser()` — dead code, `$terminals['jwd']['method']` already points to `jwd_http_request`
- Remove `jwd-scraper.js` — no longer needed since live check uses PHP HTTP

**Schedule integration:**
- Add `Artisan::call('vessel:scrape-jwd')` to `bootstrap/app.php` (after existing scrape commands)
- Add to `ScheduleManager.php` "Run Now" button (after existing scrape calls)
- Same pattern as Kerry was added in session 4

**Files retired:** `jwd-scraper.js`

**Actual code changes made (session 7):**

1. **NEW: `app/Console/Commands/ScrapeJwdSchedule.php`** — Full implementation:
   - `handle()` — makes HTTP GET, calls `parseAllRows()`, `mergeArrivalDeparture()`, stores via `storeVesselSchedule()`
   - `parseAllRows($html)` — DOMDocument + XPath to extract all `<tr>` rows (skips headers, requires 7+ `<td>` cells)
   - `mergeArrivalDeparture($rows)` — groups by `vessel_name|voyage` key, merges arrival row (ETA) + departure row (ETD) into one record, extracts berth code from `"A0-LCMT"` → `"A0"`
   - `formatJwdDate($dateStr)` — parses `"06 Mar 2026 05:00:00"` → `"2026-03-06 05:00:00"` (same logic as `VesselTrackingService::formatJWDDateTime()`)
   - `storeVesselSchedule($data)` — `VesselSchedule::updateOrCreate()` with match keys `vessel_name + port_terminal('JWD') + voyage_code`, skips records with no ETA or ETA > 1 month old, sets 48h expiry
   - `logScrape()` — writes to `DailyScrapeLog` (same pattern as `ScrapeKerryVessel.php`)

2. **MODIFIED: `bootstrap/app.php`** (line 33-34) — Added after Kerry:
   ```php
   // Scrape JWD schedule (single HTTP GET, returns immediately)
   \Illuminate\Support\Facades\Artisan::call('vessel:scrape-jwd');
   ```
   This runs inside the `vessel_scrape` schedule type block, so JWD cron fires alongside Hutchison/TIPS/ESCO/LCIT/Kerry.

3. **MODIFIED: `app/Livewire/ScheduleManager.php`** (line 142) — Added after Kerry in `runNow()`:
   ```php
   // Scrape JWD schedule (single HTTP GET)
   \Illuminate\Support\Facades\Artisan::call('vessel:scrape-jwd');
   ```
   This lets the admin trigger JWD scrape from the UI "Run Now" button.

**Why these changes:**
- JWD previously had NO cron scraper — live ETA checks always called the external API (slow, 2-5s)
- Now the cron pre-caches JWD schedules in `vessel_schedules` table
- When user clicks "Check ETA" for a JWD shipment, `checkVesselETAWithParsedName()` finds the cached data instantly from DB — no external API call needed
- The external API (`jwd_http_request()`) is still kept as fallback for when data isn't cached yet

**Test results (2026-03-06):**

| Test | Result |
|------|--------|
| `--dry-run` | 74 rows fetched, merged into 70 vessels (4 arrival+departure pairs merged) |
| Real scrape (1st run) | 34 new, 0 updated in 1.4s — only 34/70 stored because 36 had no ETA (departure-only rows) |
| Real scrape (2nd run) | 0 new, 34 updated in 1.4s — proves idempotency, no duplicate rows |
| DB verification | `INCHEON VOYAGER / 2602S | berth=A0 | ETA=2026-03-06 05:00:00 | ETD=2026-03-07 00:01:59` — correct merge of arrival+departure |
| DailyScrapeLog | `status=success, vessels_found=70, created=34, duration=1s` |

---

### Phase 8: Everbuild + Siam (cleanup only)

**Everbuild:** Remove `everbuild_browser()` from PHP and `everbuild-wrapper.js`/`everbuild-scraper.js` from JS. Dead code — no port mapping routes to it.

**Siam:** Keep `siam_n8n_line()` placeholder as-is. Different integration pattern (n8n/LINE).

---

## PHP Consolidation (optional, after all phases)

After all JS scrapers are unified, the PHP side can be simplified too:

**Current:** Each terminal has a unique PHP method with unique calling logic:
```
hutchison_browser() → shell_exec + json_decode
tips_browser()      → proc_open + stream parsing
lcit()              → proc_open + stream parsing
lcb1()              → BrowserAutomationService::runNodeScript()
shipmentlink_browser() → proc_open + stream parsing
```

**After consolidation:** One generic method:
```php
protected function runUnifiedScraper(string $terminal, string $vessel, string $voyage): array
{
    $script = "scrapers/{$terminal}-full-schedule-scraper.js";
    $command = sprintf('cd %s && timeout 120 node %s --vessel %s --voyage %s',
        base_path('browser-automation'),
        $script,
        escapeshellarg($vessel),
        escapeshellarg($voyage)
    );
    // proc_open, capture stdout/stderr, json_decode, return
}
```

This would replace 6 separate PHP methods with 1.

---

## Data Flow After Merge

```
=== CRON (Daily Batch) ===
schedule:run
  → vessel:scrape-schedules
    → BrowserAutomationService → node scrapers/X-full-schedule-scraper.js (NO args)
    → Returns { vessels: [...] }
    → storeVesselSchedule() → vessel_schedules DB
    → Terminals: Hutchison, TIPS, ESCO, LCIT (Node.js scrapers)
  → vessel:scrape-kerry (queue-based, per-vessel HTTP — Kerry API requires vessel param)
  → vessel:scrape-jwd (single HTTP GET → parse all ~75 rows → store to DB)

=== LIVE (Single Vessel Check) — simplified flow ===
User clicks "Check ETA"
  → VesselTrackingService::checkVesselETAWithParsedName()
    → Check vessel_schedules DB first (instant if cron cached it)
    → If not cached:
      → JS terminals: node scrapers/X-full-schedule-scraper.js --vessel "X" --voyage "Y"
      → Kerry: kerry_http_request() (PHP HTTP)
      → JWD: jwd_http_request() (PHP HTTP)
    → Returns { vessel_found, eta, ... } (flat object)
    → Display to user
```

---

## Summary: Before vs After

### Before (current)
```
browser-automation/
├── hutchison-wrapper.js          ← REMOVE
├── tips-wrapper.js               ← REMOVE
├── lcit-wrapper.js               ← REMOVE
├── lcb1-wrapper.js               ← REMOVE
├── shipmentlink-wrapper.js       ← REMOVE
├── everbuild-wrapper.js          ← REMOVE
├── laravel-wrapper.js            ← REMOVE
└── scrapers/
    ├── hutchison-scraper.js      ← REMOVE (merged into full-schedule)
    ├── hutchison-full-schedule-scraper.js  ← KEEP + enhance
    ├── tips-scraper.js           ← REMOVE
    ├── tips-full-schedule-scraper.js       ← KEEP + enhance
    ├── esco-full-schedule-scraper.js       ← KEEP + enhance
    ├── lcit-scraper.js           ← REMOVE
    ├── lcit-scraper-old.js       ← REMOVE
    ├── lcit-full-schedule-scraper.js       ← KEEP + enhance
    ├── lcb1-scraper.js           ← REMOVE
    ├── lcb1-full-schedule-scraper.js       ← KEEP + enhance
    ├── lcb1-full-schedule-scraper-puppeteer.js  ← REMOVE
    ├── shipmentlink-scraper.js   ← REMOVE
    ├── shipmentlink-https-scraper.js       ← REMOVE
    ├── shipmentlink-full-schedule-scraper.js  ← KEEP + enhance
    ├── everbuild-scraper.js      ← REMOVE
    ├── everbuild-scraper-improved.js       ← REMOVE
    ├── jwd-scraper.js            ← REMOVE (replaced by PHP HTTP command)
    ├── debug-*.js (4 files)      ← REMOVE
    ├── shipmentlink-vessel-codes.json      ← KEEP
    └── UPDATE-VESSEL-CODES.md    ← KEEP
```

### After (merged)
```
browser-automation/
└── scrapers/
    ├── hutchison-full-schedule-scraper.js   (cron + single mode)
    ├── tips-full-schedule-scraper.js         (cron + single mode)
    ├── esco-full-schedule-scraper.js         (cron + single mode)
    ├── lcit-full-schedule-scraper.js         (cron + single mode)
    ├── lcb1-full-schedule-scraper.js         (cron + single mode)
    ├── shipmentlink-full-schedule-scraper.js (cron + single mode)
    ├── shipmentlink-vessel-codes.json
    └── UPDATE-VESSEL-CODES.md

app/Console/Commands/
    ├── ScrapeVesselSchedules.php             (existing — Hutchison, TIPS, ESCO, LCIT cron)
    ├── ScrapeKerryVessels.php                (existing — Kerry queue cron)
    └── ScrapeJwdSchedule.php                 (NEW — JWD PHP HTTP cron)
```

**Result:** ~24 JS files → 8 files (6 scrapers + 1 data file + 1 doc) + 1 new PHP command
**JWD moved from JS to PHP** — no more Node.js dependency for JWD

---

## Testing Strategy (per phase)

1. **Cron mode** (no args) — verify full schedule output unchanged
2. **Single mode** (`--vessel X --voyage Y`) — verify correct vessel returned with flat object
3. **Vessel not found** — verify graceful response `{ success: true, vessel_found: false }`
4. **PHP integration** — `php artisan tinker` → call VesselTrackingService method
5. **UI test** — trigger ETA check from transport screen

Test vessels per terminal (from production DB):
```sql
SELECT s.port_terminal, v.name, s.voyage, s.id
FROM shipments s LEFT JOIN vessels v ON s.vessel_id = v.id
INNER JOIN (SELECT port_terminal, MAX(id) max_id FROM shipments WHERE port_terminal != '' GROUP BY port_terminal) s2 ON s.id = s2.max_id
ORDER BY s.port_terminal;
```

---

## Key Considerations

1. **All prerequisite bugs are fixed** — P0 (`2>/dev/null`), P1 (TIPS columns, voyage normalization) done in sessions 3-5
2. **LCIT filter should use API params** — don't fetch 700+ vessels then filter client-side; pass vessel/voyage to API directly
3. **Voyage normalization happens in PHP** — scrapers receive clean input (no "V." prefix, no leading spaces)
4. **Kerry is excluded from JS merge** — uses PHP HTTP + Laravel queue, already has its own cron system
5. **JWD uses PHP HTTP (no queue)** — one GET returns all ~75 vessels, so no need for per-vessel queue jobs like Kerry
6. **Three cron patterns after merge:**
   - **Node.js scrapers** (Hutchison, TIPS, ESCO, LCIT, LCB1, ShipmentLink) — called via `vessel:scrape-schedules`
   - **PHP queue** (Kerry) — called via `vessel:scrape-kerry`, dispatches per-vessel jobs
   - **PHP direct HTTP** (JWD) — called via `vessel:scrape-jwd`, single GET + parse + store
7. **Output format contract (JS scrapers):**
   - Cron mode: `{ success: bool, terminal: string, vessels: Array<{vessel_name, voyage, eta, etd, ...}> }`
   - Single mode: `{ success: bool, vessel_found: bool, vessel_name: string, voyage_code: string, eta: string, ... }`
