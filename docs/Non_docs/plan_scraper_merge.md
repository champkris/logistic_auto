# Plan: Merge Single & Cron Scrapers into One Codebase

Date: 2026-03-07 (updated from 2026-03-06)
Session: 7 (last updated: session 14)

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
| **LCB1** | _(queue-based: `ScrapeLCB1Vessel.php` → JS scraper)_ | ~~`lcb1-wrapper.js` + `lcb1-scraper.js`~~ → `lcb1-full-schedule-scraper.js --vessel` | `ScrapeLCB1Vessels.php` | `VTS::lcb1()` | HTTPS + queue |
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

### Files that REMAIN (6 JS scrapers + 2 data files)

| File | Role |
|------|------|
| `browser-automation/scrapers/hutchison-full-schedule-scraper.js` | Unified Hutchison scraper (cron + single) |
| `browser-automation/scrapers/tips-full-schedule-scraper.js` | Unified TIPS scraper (cron + single) |
| `browser-automation/scrapers/esco-full-schedule-scraper.js` | Unified ESCO scraper (cron + single) |
| `browser-automation/scrapers/lcit-full-schedule-scraper.js` | Unified LCIT scraper (cron + single) |
| `browser-automation/scrapers/lcb1-full-schedule-scraper.js` | Unified LCB1 scraper (single only — cron uses queue jobs) |
| `browser-automation/scrapers/shipmentlink-full-schedule-scraper.js` | Unified ShipmentLink scraper (cron + single) |
| `browser-automation/scrapers/lcb1-vessel-codes.json` | Cached LCB1 vessel name → code mapping (371 entries) |
| `browser-automation/scrapers/shipmentlink-vessel-codes.json` | Data file for ShipmentLink vessel codes |

### New files to CREATE

| File | Role |
|------|------|
| `app/Console/Commands/ScrapeJwdSchedule.php` | JWD cron command — HTTP GET, parse all vessels, store to DB |
| `app/Jobs/ScrapeLCB1Vessel.php` | LCB1 queue job — calls JS scraper per vessel, stores to DB |
| `app/Console/Commands/ScrapeLCB1Vessels.php` | LCB1 cron dispatcher — queries shipments, dispatches queue jobs |
| `browser-automation/scrapers/lcb1-vessel-codes.json` | Cached vessel name → code mapping (auto-refreshed on miss) |

### Files that REMAIN UNCHANGED (not merged)

| File | Reason |
|------|--------|
| `app/Jobs/ScrapeKerryVessel.php` | Kerry uses PHP HTTP + queue — not a JS scraper |
| `app/Console/Commands/ScrapeKerryVessels.php` | Kerry queue dispatcher — separate system |
| `app/Jobs/ScrapeLCB1Vessel.php` | LCB1 queue job — NEW in Phase 5 |
| `app/Console/Commands/ScrapeLCB1Vessels.php` | LCB1 queue dispatcher — NEW in Phase 5 |

### PHP methods to CONSOLIDATE

**`VesselTrackingService.php`** — Each terminal's live method (`hutchison_browser`, `tips_browser`, etc.) gets simplified to call the full-schedule scraper with `--vessel`/`--voyage` args instead of the wrapper scripts.

**`BrowserAutomationService.php`** — Cron methods (`scrapeHutchisonFullSchedule`, etc.) stay mostly unchanged but may be simplified since the JS scripts handle both modes now.

### Dead PHP code to REMOVE

| Method | File | Reason |
|--------|------|--------|
| `everbuild_browser()` | `VesselTrackingService.php` | No port mapping routes to it — dead code |
| `jwd_browser()` | `VesselTrackingService.php` | Replaced by `jwd_http_request()` (no Puppeteer needed) |
| ~~`tips()`~~ | ~~`VesselTrackingService.php`~~ | ~~DONE (session 12) -- deleted~~ |
| ~~`esco()` (HTTP version)~~ | ~~`VesselTrackingService.php`~~ | ~~DONE (session 11) -- rewritten to call unified JS scraper~~ |
| ~~`parseVesselData()`~~ | ~~`VesselTrackingService.php`~~ | ~~DONE (session 12) -- deleted (along with `ectt()`, `getSearchMethod()`, `extractVesselSection()`, `findScheduleLinks()`)~~ |
| ~~`extractETAFromHTML()`~~ | ~~`VesselTrackingService.php`~~ | ~~DONE (session 12) -- deleted~~ |
| ~~`extractETAFromTable()`~~ | ~~`VesselTrackingService.php`~~ | ~~DONE (session 12) -- deleted~~ |

---

## Implementation Phases

### Phase 1: LCIT (easiest — same HTTPS API, different params) — DONE (session 10)

**Why easiest:** Both scrapers already call the same LCIT XML API. Single scraper passes `?vessel=SAMAL&voy=2606S`, cron passes `?vessel=%&voy=` (wildcard). No Puppeteer.

**Actual code changes made (session 10):**

1. **MODIFIED: `browser-automation/scrapers/lcit-full-schedule-scraper.js`**
   - Added `parseArgs()` function — parses `--vessel` and `--voyage` CLI args
   - Added `scrapeSingleVessel(vesselName, voyageCode)` method — passes real vessel/voyage to API (not wildcard `%`), finds best voyage match (exact > includes > first), returns flat object with `vessel_found` boolean
   - Added `formatDate(dateStr)` method — converts LCIT date format `"DD MMM YY/HH:MM"` → ISO `"YYYY-MM-DDThh:mm:00"` (ported from old `lcit-scraper.js`)
   - Updated `main()` — routes to `scrapeSingleVessel()` when `--vessel` arg present, otherwise `scrapeFullSchedule()` (unchanged)
   - Cron mode output unchanged: `{ success, terminal, vessels: [...] }`
   - Single mode output: `{ success, vessel_found, vessel_name, voyage_code, berth, eta, etd, cutoff, opengate, raw_data }`

2. **MODIFIED: `app/Services/VesselTrackingService.php`** (`lcit()` method ~line 589)
   - Changed command from `node lcit-wrapper.js {vessel} {voyage}` to `node scrapers/lcit-full-schedule-scraper.js --vessel {vessel} --voyage {voyage}`
   - Added handling for `vessel_found: false` response (new scraper returns `success: true, vessel_found: false` instead of old `success: false` for not-found)

3. **DELETED:** `browser-automation/lcit-wrapper.js`, `browser-automation/scrapers/lcit-scraper.js`, `browser-automation/scrapers/lcit-scraper-old.js`

**Why the API approach is efficient:** LCIT API natively supports `?vessel=X&voy=Y` filtering. In single mode we pass real params instead of wildcard `%`, so the API returns only matching results (typically 1-2 rows) instead of 700+ vessels. Much faster than scrape-all-then-filter.

**Note on multi-berth:** LCIT has berths B5 and C3. Both map to terminal `lcit`. The API returns vessels from all berths regardless of which port the user selected. The `berth` field in the response reflects the actual berth assignment. This is pre-existing behavior — see P4.2 in `finding_and_problem_ETA_2.md` for planned berth-change UI indicator.

**Test results (2026-03-06):**

| Test | Result |
|------|--------|
| Cron mode (no args) | Works — returns all vessels, output unchanged |
| Single mode (`--vessel "POS HOCHIMINH" --voyage "1061S"`) | Works — `vessel_found: true`, ETA=`2026-03-18T00:40:00`, berth=B5 |
| Not-found (`--vessel "NONEXISTENT" --voyage "999X"`) | Works — `vessel_found: false` |
| PHP live (artisan tinker, cache cleared) | Works — `vessel_found: true`, ETA correct |
| PHP not-found (artisan tinker) | Works — graceful `vessel_found: false`, `search_method: lcit_not_found` |

---

### Phase 2: ESCO (Puppeteer → HTTP + add single mode) — DONE (session 11)

**Discovery:** ESCO serves static HTML — no JavaScript rendering needed. Neither old approach used direct HTTP properly:

| Path | Old behavior | Problem |
|------|-------------|---------|
| **Daily cron** | `node esco-full-schedule-scraper.js` → Puppeteer (launches Chromium) | Overkill — page is static HTML, no JS rendering needed. Wastes ~150MB RAM + 5-8 seconds per scrape |
| **Manual single scraper** | PHP `esco()` → `Http::get()` → `parseVesselData()` | Right transport (HTTP) but wrong parsing — `str_contains()` string matching on raw HTML, fragile regex ETA extraction |

**Fix:** Rewrote JS scraper to use `axios` + `node-html-parser` (both already in package.json). Now both cron and single mode use correct transport (HTTP) AND correct parsing (structured table columns).

**Actual code changes made (session 11):**

1. **REWRITTEN: `browser-automation/scrapers/esco-full-schedule-scraper.js`**
   - Replaced Puppeteer with `axios` + `node-html-parser` — no browser launch needed
   - Added `parseArgs()` function, `scrapeSingleVessel()` method, `formatDate()` method
   - ESCO date format: `"DD/MM/YYYY HH:MM"` → ISO `"YYYY-MM-DDThh:mm:00"`
   - Cron mode output unchanged: `{ success, terminal, vessels: [...] }`
   - Single mode output: `{ success, vessel_found, vessel_name, voyage_code, berth, eta, etd, cutoff, opengate, raw_data }`

2. **REWRITTEN: `app/Services/VesselTrackingService.php`** (`esco()` method ~line 764)
   - Was: `Http::get()` → `parseVesselData()` (fragile string-search on raw HTML)
   - Now: calls `node scrapers/esco-full-schedule-scraper.js --vessel X --voyage Y` via `proc_open`
   - Handles `vessel_found: false`, timeout, and error responses (same pattern as `lcit()`)

**Performance improvement:** ~5-8 seconds (Puppeteer) → ~1-2 seconds (HTTP GET). No Chromium process needed.

**`parseVesselData()` status:** Still used by `tips()` and `generic_scrape()` — will be cleaned up in Phase 3 (TIPS).

**Test results (2026-03-06):**

| Test | Result |
|------|--------|
| Cron mode (no args) | Works — 28 vessels, output format unchanged |
| Single mode (`--vessel "WAN HAI 173"`) | Works — `vessel_found: true`, ETA=`2026-03-07T05:00:00`, berth=B3 |
| Not-found (`--vessel "NONEXISTENT"`) | Works — `vessel_found: false` |
| PHP live (artisan tinker, cache cleared) | Works — `vessel_found: true`, `search_method: esco_scraper` |
| PHP not-found (artisan tinker) | Works — graceful `vessel_found: false`, `search_method: esco_not_found` |

**Files retired:** None (no separate single scraper existed)

---

### Phase 3: TIPS (Puppeteer -> HTTP + add single mode) -- DONE (session 12)

**Discovery:** TIPS serves static HTML -- no JavaScript rendering needed. Neither old approach used direct HTTP properly:

| Path | Old behavior | Problem |
|------|-------------|---------|
| **Daily cron** | `node tips-full-schedule-scraper.js` -> Puppeteer (launches Chromium, changes DataTables page size) | Overkill -- page is static HTML. ~150MB RAM, 5-8 sec. Only extracted 4 fields (no ETD, no closing time) |
| **Manual single scraper** | `node tips-wrapper.js` -> `tips-scraper.js` -> Puppeteer (775 lines! human-like mouse simulation, screenshot debugging) | Massive overengineering for static HTML. Fragile regex ETA extraction instead of structured column parsing |

**Fix:** Rewrote JS scraper to use `axios` + `node-html-parser` (same as ESCO). Now both cron and single mode use correct transport (HTTP) AND correct parsing (structured table columns).

**TIPS table structure (11 columns per data row):**
- [0] Vessel Name, [1] Id, [2] Radio Call Sign, [3] I/B Voyage, [4] O/B Voyage
- [5] ETA (estimate), [6] ETD (estimate), [7] ATA (actual), [8] ATD (actual)
- [9] Closing Time, [10] Service Code
- Date format: `DD/MM/YYYY HH:MM` (same as ESCO)

**Actual code changes made (session 12):**

1. **REWRITTEN: `browser-automation/scrapers/tips-full-schedule-scraper.js`**
   - Replaced Puppeteer with `axios` + `node-html-parser` -- no browser launch needed
   - Added `parseArgs()`, `scrapeSingleVessel()`, `formatDate()`, `generateVoyageVariations()`
   - Uses actual dates (ATA/ATD) if available, otherwise estimates (ETA/ETD)
   - Cron mode output unchanged: `{ success, terminal, vessels: [...] }`
   - Single mode output: `{ success, vessel_found, vessel_name, voyage_code, berth, eta, etd, cutoff, raw_data }`

2. **REWRITTEN: `app/Services/VesselTrackingService.php`** (`tips_browser()` method)
   - Was: calls `tips-wrapper.js` via proc_open (120s timeout, Puppeteer)
   - Now: calls `node scrapers/tips-full-schedule-scraper.js --vessel X --voyage Y` (30s timeout, HTTP)
   - Handles `vessel_found: false`, timeout, and error responses (same pattern as `esco()` and `lcit()`)

3. **DELETED JS files:** `browser-automation/tips-wrapper.js`, `browser-automation/scrapers/tips-scraper.js`

4. **DELETED PHP methods:** `tips()`, `ectt()`, `parseVesselData()`, `getSearchMethod()`, `extractETAFromHTML()`, `extractETAFromTable()`, `extractVesselSection()`, `findScheduleLinks()` -- all dead code, only called by each other or by dead methods

**Performance improvement:** ~5-8 seconds (Puppeteer) -> ~1-2 seconds (HTTP GET). No Chromium process needed.

**Test results (2026-03-06):**

| Test | Result |
|------|--------|
| Cron mode (no args) | Works -- 47 vessels, all dates ISO formatted |
| Single mode (`--vessel "LADY OF LUCK" --voyage "284N"`) | Works -- `vessel_found: true`, ETA=`2026-03-17T23:00:00`, berth=B4 |
| Not-found (`--vessel "NONEXISTENT"`) | Works -- `vessel_found: false` |
| PHP live (artisan tinker, cache cleared) | Works -- `vessel_found: true`, `search_method: tips_scraper` |
| PHP not-found (artisan tinker) | Works -- graceful `vessel_found: false`, `search_method: tips_not_found` |

---

### Phase 4: Hutchison (Puppeteer -> HTTP + add single mode) -- DONE (session 13)

**Discovery:** Hutchison's website (`online.hutchisonports.co.th`) is built on **Oracle APEX** (Application Express) -- Oracle's low-code web framework for building database-driven apps. Think of it as Oracle's equivalent of Laravel/Django admin panels. Key characteristics:

- **Server-side rendered HTML** -- APEX generates static HTML tables from database queries. No client-side JS framework (no React/Vue). The vessel data is already in the HTML on page load.
- **Session management** -- Each page load creates a new `p_instance` (session ID) stored in a hidden `<input>` field. Cookies (`HPTDP_COOKIE`, `ORA_WWV_RAC_INSTANCE`) must accompany all requests. Unlike Kerry (which accepts any fake cookie value), APEX validates the real session -- fake sessions return `"Your session has ended."`.
- **Pagination via AJAX widget** -- APEX renders 15 rows per page. The pagination `<select>` dropdown calls `apex.widget.report.paginate()` in JavaScript, which internally calls `apex.server.plugin()` -- this makes a POST to `wwv_flow.show` on the server. The server returns an HTML fragment (just the table rows), not a full page. This is the key insight that allows HTTP-only pagination.
- **CSRF-like token** -- Each pagination call requires a `p_request=PLUGIN=<token>` parameter. The token is embedded in the pagination `<select>`'s `onchange` handler and is tied to the session. It changes every page load.

**HTTP pagination flow (replaces Puppeteer):**
1. GET page -> static HTML with first 15 vessels + APEX session ID + token + cookies
2. POST `wwv_flow.show` with `p_request=PLUGIN=<token>` + cookies -> returns next 15 as HTML fragment
3. Repeat for each page (page count discovered dynamically from `<select>` options)

No login required. Session is created on GET, used for pagination POSTs, then discarded. The entire scrape takes ~900ms (4 HTTP requests for ~60 vessels).

| Path | Old behavior | Problem |
|------|-------------|---------|
| **Daily cron** | `node hutchison-full-schedule-scraper.js C1` -> Puppeteer (launches Chromium, clicks Next button, 2s wait per page) | Overkill -- page data is static HTML with AJAX pagination. ~150MB RAM, 10-15 sec. No date formatting (raw `DD/MM/YYYY HH:MM` strings). Called 4 times for C1/C2/C3/D1 but returns ALL berths each time |
| **Manual single scraper** | `node hutchison-wrapper.js` -> `hutchison-scraper.js` -> Puppeteer (693 lines! human-like mouse simulation, 3 extraction strategies, screenshot debugging) | Massive overengineering. Screenshot debris (8 PNG files). Failed silently most of the time (0 vessels found in DailyScrapeLog for weeks) |

**Table structure (10 columns, always consistent):**
- [0] Vessel Name, [1] Vessel ID, [2] In Voy, [3] Out Voy
- [4] Arrival (ETA) `DD/MM/YYYY HH:MM`, [5] Departure (ETD) `DD/MM/YYYY HH:MM`
- [6] Berth Terminal (C1C2, D1, D2, A2, A3), [7] Release port (number)
- [8] Status (Berthed/Vessel Departed/Gate-Opened/Gate-Closed)
- [9] Gate Closing Time `DD-MMM-YYYY HH:MM` (different format!) or status text or empty

**Actual code changes made (session 13):**

1. **REWRITTEN: `browser-automation/scrapers/hutchison-full-schedule-scraper.js`**
   - Replaced Puppeteer with `axios` + `node-html-parser` -- no browser launch needed
   - HTTP pagination: GET page 1 for session/cookies, POST `wwv_flow.show` for pages 2+
   - Dynamic pagination: parses `<select>` options, handles any number of pages
   - Added `parseArgs()`, `scrapeSingleVessel()` (early-exit when vessel found)
   - Added `formatDate()` (ETA/ETD: `DD/MM/YYYY HH:MM` -> ISO)
   - Added `formatGateClosingDate()` (Gate Closing: `DD-MMM-YYYY HH:MM` -> ISO, returns null for non-date values like "Gate-Closed")
   - Cron mode output unchanged: `{ success, terminal, vessels: [...] }`
   - Single mode output: `{ success, vessel_found, vessel_name, voyage_code, berth, eta, etd, cutoff, raw_data }`

2. **REWRITTEN: `app/Services/VesselTrackingService.php`** (`hutchison_browser()` method)
   - Was: calls `hutchison-wrapper.js` via `BrowserAutomationService::runNodeScript` (90s timeout, Puppeteer)
   - Now: calls `node scrapers/hutchison-full-schedule-scraper.js --vessel X --voyage Y` via `proc_open` (60s timeout, HTTP)
   - Handles `vessel_found: false`, timeout, and error responses (same pattern as `tips_browser()`, `esco()`, `lcit()`)

3. **DELETED JS files:** `browser-automation/hutchison-wrapper.js`, `browser-automation/scrapers/hutchison-scraper.js`
4. **DELETED debug files:** 8 `hutchison-no-data-*.png` screenshots, `hutchison-scraping.log`

**Performance improvement:** ~10-15 seconds (Puppeteer) -> ~900ms (4 HTTP requests). No Chromium process needed.

**Test results (2026-03-06):**

| Test | Result |
|------|--------|
| Cron mode (`C1` arg) | Works -- 59 vessels, all dates ISO formatted, ~900ms |
| Single mode (`--vessel "WAN HAI 509" --voyage "S160"`) | Works -- `vessel_found: true`, ETA=`2026-03-06T06:00:00`, berth=C1C2, found on page 2 (early exit) |
| Single mode with cutoff (`--vessel "ONE MILLAU"`) | Works -- cutoff=`2026-03-06T20:00:00` (parsed from `DD-MMM-YYYY HH:MM` format) |
| Not-found (`--vessel "NONEXISTENT"`) | Works -- `vessel_found: false` |
| PHP live (artisan tinker, cache cleared) | Works -- `vessel_found: true`, `search_method: hutchison_scraper` |
| PHP not-found (artisan tinker) | Works -- graceful `vessel_found: false`, `search_method: hutchison_not_found` |

---

### Phase 5: LCB1 (Puppeteer → HTTPS + queue-based cron + vessel code cache) — DONE (session 14)

**Key discovery: LCB1 API uses vessel CODES, not names.** The `<select>` dropdown on `/BerthSchedule` has `<option value="KXM">KMTC XIAMEN</option>`. The POST to `/BerthSchedule/Detail` requires `vesselName=KXM` (the code), not `vesselName=KMTC+XIAMEN` (the display name). Sending the full name always returns "No data found". This was the critical bug in all previous LCB1 scraper attempts.

**LCB1 API structure:**
```
GET  https://www.lcb1.com/BerthSchedule
  Returns: HTML page with <select id="txtVesselName"> dropdown (~396 options)
  No auth, no cookies, no session

POST https://www.lcb1.com/BerthSchedule/Detail
  Content-Type: application/x-www-form-urlencoded
  Body: vesselName=KXM&voyageIn=&voyageOut=&pageSize=100&page=1
  Returns: HTML table fragment

  Table columns (7):
    [0] No.  [1] Vessel Name  [2] Voyage In  [3] Voyage Out
    [4] Berthing Time (DD/MM/YYYY - HH:MM)  [5] Departure Time  [6] Terminal (A0, B1)
```

No rate limiting detected (tested 50 rapid requests). Response time ~750ms sequential, ~2.8s parallel (10 concurrent). Behind Imperva Incapsula CDN (`visid_incap_`, `incap_ses_` cookies) but WAF doesn't block scraping.

**Why queue-based cron (like Kerry), not batch scrape:** LCB1's API only supports querying one vessel at a time (POST with `vesselName=CODE`). Unlike Hutchison/TIPS/ESCO which return all vessels in one page, LCB1 would require ~400 sequential POSTs for a full schedule. A queue-based approach dispatches one job per active shipment's vessel, rate-limited to 40/min. Queue concurrency: 1 worker (parallel requests slow down 3-4x). Expected cycle: ~15 vessels x ~2s = ~30 seconds.

**Vessel code mapping — why 371 not 395:**
The LCB1 dropdown has **396 `<option>` tags**:
- 1 is the empty placeholder: `<option value="">Select</option>` → filtered out (empty code)
- 24 are **duplicate vessel names** with different codes (same name listed 2-3 times, e.g. `MAERSK NARVIK` has codes `ER0` and `SE1`). When stored as `{ "NAME": "CODE" }` JSON, the last code wins
- Result: **395 valid entries - 24 dupes = 371 unique vessel name → code mappings**

Example duplicates from LCB1:
| Vessel Name | Code 1 (old) | Code 2 (new, stored) |
|-------------|-------------|---------------------|
| MAERSK NARVIK | ER0 | SE1 |
| PANCON CHAMPION | GI6 → KPCP | PCHM |
| ALS VENUS | AE6 | H4B |
| SEOUL GLOW | 4CO | EP9 |

The last code is stored in cache because the dropdown lists them in order and the `mapping[name] = code` assignment overwrites previous entries. This matches what the dropdown shows as the active option.

**Vessel code cache file: `browser-automation/scrapers/lcb1-vessel-codes.json`**
- Same format as `shipmentlink-vessel-codes.json`: `{ "VESSEL NAME": "CODE", ... }`
- 371 entries, ~12KB
- Three-tier lookup strategy:
  1. **Cache hit** → use cached code, no HTTP request
  2. **Cache miss** → live GET to `/BerthSchedule`, parse dropdown, refresh entire cache file, retry lookup
  3. **Stale code detection** → if POST with cached code returns no data, force-refresh cache and retry with new code. Handles the edge case where a vessel name stays the same but its code changes on LCB1's side

| Old behavior | New behavior |
|-------------|-------------|
| `lcb1-scraper.js`: 684-line Puppeteer, Select2 widget interaction, screenshot debugging | `lcb1-full-schedule-scraper.js`: ~340 lines, pure HTTPS (`node:https` + `node-html-parser`), no Puppeteer |
| `lcb1-wrapper.js` → `laravel-wrapper.js` → Puppeteer → 60s timeout | Direct `node scrapers/lcb1-full-schedule-scraper.js --vessel X --voyage Y` → 30s timeout |
| Every call fetched vessel list from LCB1 website | JSON cache file, live refresh only on miss or stale code |
| No cron scraper (commented out — too slow with Puppeteer, 392 sequential lookups) | Queue-based cron: `vessel:scrape-lcb1` → dispatches `ScrapeLCB1Vessel` jobs, rate-limited 40/min |
| `BrowserAutomationService::runNodeScript()` | `proc_open` in both `VesselTrackingService::lcb1()` and `ScrapeLCB1Vessel::callScraper()` |

**Actual code changes made (session 14):**

1. **REWRITTEN: `browser-automation/scrapers/lcb1-full-schedule-scraper.js`**
   - Was: 187-line cron-only scraper looping through all ~400 vessels
   - Now: Single-vessel scraper with `--vessel`/`--voyage` CLI args (used by both live ETA checks and queue jobs)
   - Key methods:
     - `loadCache()` / `saveCache()` — read/write `lcb1-vessel-codes.json`
     - `fetchLiveVesselMapping()` — GET `/BerthSchedule`, parse `<select id="txtVesselName">` dropdown into `{ NAME: CODE }` mapping
     - `lookupInCache(vesselName)` — exact match first, then partial (contains) match
     - `findVesselCode(vesselName, forceRefresh)` — cache-first lookup with live fallback
     - `scrapeSingleVessel(vesselName, voyageCode)` — POST with vessel CODE, parse HTML table, stale-code retry
     - `findVesselMatch(schedules, voyageCode)` — exact → partial → fallback to first (P4.5 deferred)
     - `parseScheduleHTML(html, vesselName)` — 7-column table: [#, Vessel, Voyage In, Voyage Out, Berthing Time, Departure Time, Terminal]
     - `formatDate(dateStr)` — `"DD/MM/YYYY - HH:MM"` → `"YYYY-MM-DDThh:mm:00"`
     - `makeRequest(path, method, postData, retries)` — HTTPS with 30s timeout, auto-retry up to 2 times on network failures
   - Technology: `node:https` + `node:fs` + `node-html-parser` (no Puppeteer, no axios)

2. **NEW: `browser-automation/scrapers/lcb1-vessel-codes.json`**
   - 371 vessel name → code mappings cached from LCB1 dropdown
   - Auto-refreshed when vessel not found in cache or when cached code returns no data

3. **REWRITTEN: `app/Services/VesselTrackingService.php`** (`lcb1()` method ~line 903)
   - Was: Called `laravel-wrapper.js` via `BrowserAutomationService::runNodeScript` (60s timeout, Puppeteer)
   - Now: Calls `node scrapers/lcb1-full-schedule-scraper.js --vessel X --voyage Y` via `proc_open` (30s timeout, HTTP)
   - Response handling: `vessel_found: false` → `lcb1_not_found`, timeout → `lcb1_timeout_fallback`, success → `lcb1_scraper`, error → `lcb1_error`

4. **NEW: `app/Jobs/ScrapeLCB1Vessel.php`** (queue job)
   - `implements ShouldQueue`, `$tries = 2`, `$backoff = 30`
   - `middleware()`: `RateLimited('lcb1-api')`
   - `handle()`: calls JS scraper via `proc_open`, parses JSON, `VesselSchedule::updateOrCreate()` with match keys `vessel_name + port_terminal + voyage_code`
   - `logScrape()`: writes to `DailyScrapeLog` (terminal: 'lcb1')
   - Skips ETAs more than 1 month old, sets 48h expiry

5. **NEW: `app/Console/Commands/ScrapeLCB1Vessels.php`** (cron dispatcher)
   - Signature: `vessel:scrape-lcb1 {--dry-run}`
   - Queries: `Shipment::where('status', 'in-progress')->whereIn('port_terminal', ['A0', 'B1'])->whereNotNull('vessel_id')->whereBetween('client_requested_delivery_date', [now()->subMonth(), now()->addMonth()])`
   - Deduplicates by `vessel_name + voyage` (avoids redundant API calls for multiple containers on same ship)
   - Dispatches `ScrapeLCB1Vessel` jobs to `lcb1-scraper` queue

6. **MODIFIED: `app/Providers/AppServiceProvider.php`**
   - Added: `RateLimiter::for('lcb1-api', fn($job) => Limit::perMinute((int) env('LCB1_RATE_LIMIT', 40)));`

7. **MODIFIED: `bootstrap/app.php`** (line 36-37)
   - Added after JWD: `\Illuminate\Support\Facades\Artisan::call('vessel:scrape-lcb1');`

8. **MODIFIED: `app/Livewire/ScheduleManager.php`** (line 143-144)
   - Added after JWD in `runNow()`: `\Illuminate\Support\Facades\Artisan::call('vessel:scrape-lcb1');`

9. **DELETED 12 files:**

   | File | Reason |
   |------|--------|
   | `browser-automation/lcb1-wrapper.js` | Replaced by `--vessel` mode |
   | `browser-automation/enhanced-lcb1-debug.js` | Debug script |
   | `browser-automation/laravel-wrapper.js` | Generic wrapper, replaced by direct calls |
   | `browser-automation/scrapers/lcb1-scraper.js` | 684-line Puppeteer scraper replaced by ~340-line HTTPS |
   | `browser-automation/scrapers/lcb1-full-schedule-scraper-puppeteer.js` | Old Puppeteer cron version |
   | `browser-automation/scrapers/debug-lcb1-structure.js` | One-time debug script |
   | `browser-automation/lcb1-debug-screenshot.png` | Debug screenshot |
   | `browser-automation/lcb1-enhanced-debug.png` | Debug screenshot |
   | `browser-automation/lcb1-enhanced-error.png` | Debug screenshot |
   | `browser-automation/lcb1-ajax-debug.png` | Debug screenshot |
   | `browser-automation/lcb1-error-1753203340851.png` | Debug screenshot |
   | `browser-automation/lcb1-error-1753205964695.png` | Debug screenshot |

**Data flow after LCB1 merge:**
```
Cron fires vessel_scrape schedule
  → vessel:scrape-schedules (Hutchison, TIPS, ESCO, LCIT — batch scrapers, unchanged)
  → vessel:scrape-kerry (queue-based per-vessel, PHP HTTP)
  → vessel:scrape-jwd (single HTTP GET, all vessels at once)
  → vessel:scrape-lcb1 (dispatches queue jobs, returns instantly)
      → Queue worker processes ScrapeLCB1Vessel jobs (rate limited 40/min)
      → Each job: JS scraper → cache lookup → POST to LCB1 → parse → vessel_schedules DB

Live ETA check for A0/B1 shipment:
  → checkVesselETAWithParsedName() resolves A0 → 'lcb1'
  → Checks vessel_schedules DB first (instant if cron pre-cached)
  → Falls back to live JS scraper if not cached
```

**Production setup:**
- Deploy code + `php artisan migrate` (ensure jobs/failed_jobs tables exist)
- Start queue worker: `php artisan queue:work --queue=lcb1-scraper --tries=2 --backoff=30 --sleep=3 --timeout=60`
- Optional env: `LCB1_RATE_LIMIT=40` (default)
- No new crontab entry — existing `schedule:run` triggers `vessel:scrape-lcb1` via `bootstrap/app.php`
- Only 1 worker needed — with ~15 unique vessels deduped from ~26 shipments, all jobs complete in under 30 seconds

**Test results (2026-03-06):**

| Test | Result |
|------|--------|
| JS scraper cache hit (`--vessel "KMTC XIAMEN" --voyage "2602S"`) | Works — `Cache hit: KMTC XIAMEN → KXM`, ETA=`2026-02-28T07:00:00` |
| JS scraper cache miss (removed entry, re-ran) | Works — `Cache miss`, fetched live, saved 371 vessels, found code |
| JS scraper second vessel (`--vessel "SAWASDEE DENEB" --voyage "2602S"`) | Works — ETA=`2026-03-01T04:00:00` |
| Cron dry-run (`vessel:scrape-lcb1 --dry-run`) | Works — 26 shipments, 15 unique vessels |
| PHP live ETA (artisan tinker, `checkVesselETAByName`) | Works — `source: lcb1_db_cached`, ETA correct from DB |
| Vessel code cache file | 371 entries in `lcb1-vessel-codes.json` |

**Test vessels (confirmed working 2026-03-06):**

| Vessel | Voyage | ETA | Port | Notes |
|--------|--------|-----|------|-------|
| KMTC XIAMEN | 2602S | 28/02 07:00 | A0 | Standard test case |
| SAWASDEE DENEB | 2602S | 01/03 04:00 | A0 | Second vessel test |
| SM JAKARTA | 2602W | — | A0 | "Not Found" — vessel departed |
| SAWASDEE SUNRISE | 2602S | 17/03 16:00 | A0 | **P4.5 bug** — returns 2603S ETA for 2602S request (voyage fallback) |

**Testing/verification commands:**
```bash
# JS scraper tests
cd /home/dragonnon2/projects/logistic_auto/browser-automation
node scrapers/lcb1-full-schedule-scraper.js --vessel "KMTC XIAMEN" --voyage "2602S"
node scrapers/lcb1-full-schedule-scraper.js --vessel "NONEXISTENT" --voyage "999X"

# PHP cron
php artisan vessel:scrape-lcb1 --dry-run
php artisan vessel:scrape-lcb1
php artisan queue:work --queue=lcb1-scraper --once

# PHP live ETA
php artisan tinker
# (new App\Services\VesselTrackingService)->checkVesselETAByName('KMTC XIAMEN 2602S', 'A0')

# Verify DB
# SELECT * FROM vessel_schedules WHERE source = 'lcb1' ORDER BY updated_at DESC LIMIT 10;
# SELECT * FROM daily_scrape_logs WHERE terminal = 'lcb1' ORDER BY id DESC LIMIT 5;
```

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

### Phase 8: Everbuild + Siam (cleanup only) — DONE (session 9)

**Everbuild:** Removed all Everbuild code. It was a Puppeteer-based scraper targeting `ss.shipmentlink.com` (same site as ShipmentLink B2) but was never wired into port routing — zero DB records, no port mapping. A worse duplicate of the ShipmentLink HTTPS scraper.

**Removed files (28 total):**
- `everbuild-wrapper.js`, `scrapers/everbuild-scraper.js`, `scrapers/everbuild-scraper-improved.js` (core)
- `everbuild-*.png` (4 debug screenshots)
- 21 debug/fix scripts in `browser-automation/` (`debug-*.js`, `compare-*.js`, `fix-*.js`, `quick-debug.js`, `vessel-scraper.js`) — all one-off throwaway scripts from development
- `everbuild_browser()` method (118 lines) from `VesselTrackingService.php`
- Stale `package.json` entries (`"main"`, `"start"`, `"test"`, `"everbuild"` script)

**Verified:** ShipmentLink (B2) scraper tested and works correctly after removal — completely independent code paths.

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
    → Terminals: Hutchison, TIPS, ESCO, LCIT (Node.js batch scrapers)
  → vessel:scrape-kerry (queue-based, per-vessel PHP HTTP — Kerry API requires vessel param)
  → vessel:scrape-jwd (single HTTP GET → parse all ~75 rows → store to DB)
  → vessel:scrape-lcb1 (queue-based, per-vessel JS scraper — LCB1 API requires vessel code)
    → Dispatches ScrapeLCB1Vessel jobs to lcb1-scraper queue
    → Queue worker: JS scraper → cache lookup → POST → parse → vessel_schedules DB

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
    ├── lcb1-full-schedule-scraper.js         (single mode only — cron uses queue)
    ├── lcb1-vessel-codes.json               (vessel name → code cache, 371 entries)
    ├── shipmentlink-full-schedule-scraper.js (cron + single mode)
    ├── shipmentlink-vessel-codes.json
    └── UPDATE-VESSEL-CODES.md

app/Console/Commands/
    ├── ScrapeVesselSchedules.php             (existing — Hutchison, TIPS, ESCO, LCIT cron)
    ├── ScrapeKerryVessels.php                (existing — Kerry queue cron)
    ├── ScrapeJwdSchedule.php                 (NEW — JWD PHP HTTP cron)
    └── ScrapeLCB1Vessels.php                 (NEW — LCB1 queue dispatcher cron)

app/Jobs/
    ├── ScrapeKerryVessel.php                (existing — Kerry queue job)
    └── ScrapeLCB1Vessel.php                 (NEW — LCB1 queue job)
```

**Result:** ~24 JS files → 9 files (6 scrapers + 2 data files + 1 doc) + 2 new PHP commands + 1 new PHP job
**JWD moved from JS to PHP** — no more Node.js dependency for JWD
**LCB1 moved from Puppeteer to HTTPS** — queue-based cron like Kerry

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
6. **Four cron patterns after merge:**
   - **Node.js batch scrapers** (Hutchison, TIPS, ESCO, LCIT) — called via `vessel:scrape-schedules`, scrape full schedule in one go
   - **Node.js queue** (LCB1) — called via `vessel:scrape-lcb1`, dispatches per-vessel queue jobs (API only supports one vessel per POST)
   - **PHP queue** (Kerry) — called via `vessel:scrape-kerry`, dispatches per-vessel jobs
   - **PHP direct HTTP** (JWD) — called via `vessel:scrape-jwd`, single GET + parse + store
7. **Output format contract (JS scrapers):**
   - Cron mode: `{ success: bool, terminal: string, vessels: Array<{vessel_name, voyage, eta, etd, ...}> }`
   - Single mode: `{ success: bool, vessel_found: bool, vessel_name: string, voyage_code: string, eta: string, ... }`
