# Plan: Merge Single & Cron Scrapers into One Codebase

Date: 2026-03-03
Context: Currently each terminal has separate code for single/manual lookup and daily cron. We want one codebase per terminal.

---

## Strategy: Option B — Add vessel filter to full-schedule scrapers

Each full-schedule scraper gets optional `--vessel` and `--voyage` CLI args:
- **No args** → full schedule mode (cron) — scrape everything
- **With args** → single vessel mode — scrape and filter, early-exit when found

```bash
# Cron mode (no filter):
node scrapers/tips-full-schedule-scraper.js

# Single vessel mode (with filter):
node scrapers/tips-full-schedule-scraper.js --vessel "NATTHA BHUM" --voyage "050N"
```

In single mode, the scraper returns a **flat object** (same format the PHP code expects from single scrapers), not an array.

---

## Implementation Order

### Phase 1: LCIT (easiest — same API, just different params)

**Current state:**
- Single: `lcit-scraper.js` calls API with `?vessel=SAMAL&voy=2606S`
- Cron: `lcit-full-schedule-scraper.js` calls API with `?vessel=%&voy=` (wildcard)
- Both use HTTPS (no Puppeteer)

**Steps:**
1. Add `--vessel`/`--voyage` args to `lcit-full-schedule-scraper.js`
2. In filter mode: pass actual vessel/voyage to API (not wildcard) — avoids fetching 700+ results
3. Return flat object format in filter mode
4. Update `VesselTrackingService.php::lcit_api()` to call `lcit-full-schedule-scraper.js --vessel X --voyage Y` instead of `lcit-wrapper.js`
5. Test: `node scrapers/lcit-full-schedule-scraper.js --vessel "SAMAL" --voyage "2606S"`
6. Test: full schedule mode still works (no args)

**Files affected:**
- `browser-automation/scrapers/lcit-full-schedule-scraper.js` — add filter mode
- `app/Services/VesselTrackingService.php` — update `lcit_api()` method
- `browser-automation/lcit-wrapper.js` — becomes unused (keep for now, remove later)
- `browser-automation/scrapers/lcit-scraper.js` — becomes unused

---

### Phase 2: ESCO (already has only full-schedule, just needs single wrapper)

**Current state:**
- Single: No dedicated scraper — PHP calls full-schedule and searches result
- Cron: `esco-full-schedule-scraper.js` (Puppeteer)

**Steps:**
1. Add `--vessel`/`--voyage` args to `esco-full-schedule-scraper.js`
2. In filter mode: scrape full schedule (small table, ~32 vessels) then filter and return flat object
3. Update PHP to call with filter args
4. Test with latest B3 vessel from production DB

**Files affected:**
- `browser-automation/scrapers/esco-full-schedule-scraper.js` — add filter mode
- `app/Services/VesselTrackingService.php` — update ESCO method (if separate single method exists)

---

### Phase 3: TIPS (Puppeteer, good merge candidate)

**Current state:**
- Single: `tips-scraper.js` — Puppeteer form interaction, heuristic date extraction
- Cron: `tips-full-schedule-scraper.js` — Puppeteer DataTables page size change, table extraction (now with fixed column mapping)

**Steps:**
1. Add `--vessel`/`--voyage` args to `tips-full-schedule-scraper.js`
2. In filter mode: still scrape full table (uses DataTables page size=100), then filter by vessel name + voyage
3. Add fuzzy voyage matching (the single scraper has `generateVoyageVariations()` — port this logic)
4. Return flat object format in filter mode
5. Update `VesselTrackingService.php::tips_browser()` to call full-schedule scraper
6. Test: `node scrapers/tips-full-schedule-scraper.js --vessel "NATTHA BHUM" --voyage "050N"`

**Files affected:**
- `browser-automation/scrapers/tips-full-schedule-scraper.js` — add filter mode + voyage matching
- `app/Services/VesselTrackingService.php` — update `tips_browser()` method
- `browser-automation/tips-wrapper.js` — becomes unused
- `browser-automation/scrapers/tips-scraper.js` — becomes unused

---

### Phase 4: Hutchison (Puppeteer, pagination)

**Current state:**
- Single: `hutchison-scraper.js` — Puppeteer with anti-detection, searches by vessel name
- Cron: `hutchison-full-schedule-scraper.js` — Puppeteer with pagination, scrapes all vessels

**Steps:**
1. Add `--vessel`/`--voyage` args to `hutchison-full-schedule-scraper.js`
2. In filter mode: scrape pages and check each one, early-exit when vessel found
3. Return flat object format in filter mode
4. Update `VesselTrackingService.php::hutchison_browser()` to call full-schedule scraper
5. Test with latest C1C2 vessel

**Files affected:**
- `browser-automation/scrapers/hutchison-full-schedule-scraper.js` — add filter mode
- `app/Services/VesselTrackingService.php` — update `hutchison_browser()` method
- `browser-automation/hutchison-wrapper.js` — becomes unused
- `browser-automation/scrapers/hutchison-scraper.js` — becomes unused

---

### Phase 5: LCB1 (technology choice needed)

**Current state:**
- Single: `lcb1-scraper.js` — Puppeteer with form interaction
- Cron: `lcb1-full-schedule-scraper.js` — HTTPS + HTML parsing (lightweight, but slow for full schedule: 392 vessels one-by-one)

**Decision needed:** Use the HTTPS approach (cron scraper) for single lookups too?
- Pro: No Puppeteer needed (lighter, faster startup)
- Con: The HTTPS approach POSTs per vessel — for single lookup, just POST once for the target vessel (fast)

**Steps:**
1. Add `--vessel`/`--voyage` args to `lcb1-full-schedule-scraper.js`
2. In filter mode: skip vessel list fetch, directly POST for the target vessel
3. Return flat object format
4. Update PHP to call full-schedule scraper with filter
5. Test with latest A0/B1 vessel

**Files affected:**
- `browser-automation/scrapers/lcb1-full-schedule-scraper.js` — add filter mode
- `app/Services/VesselTrackingService.php` — update `lcb1_browser()` method
- `browser-automation/lcb1-wrapper.js` — becomes unused
- `browser-automation/scrapers/lcb1-scraper.js` — becomes unused

---

### Phase 6: ShipmentLink (HTTPS approach)

**Current state:**
- Single: `shipmentlink-wrapper.js` — Puppeteer, complex vessel code lookup
- Cron: `shipmentlink-full-schedule-scraper.js` — HTTPS, enumerates all vessel codes

**Steps:**
1. Add `--vessel`/`--voyage` args to `shipmentlink-full-schedule-scraper.js`
2. In filter mode: search vessel code by name first, then query only that vessel's schedule
3. Return flat object format
4. Update PHP
5. Test with latest B2 vessel

**Files affected:**
- `browser-automation/scrapers/shipmentlink-full-schedule-scraper.js` — add filter mode
- `app/Services/VesselTrackingService.php` — update `shipmentlink_browser()` method
- `browser-automation/shipmentlink-wrapper.js` — becomes unused (450 lines)

---

### Phase 7: JWD (needs full-schedule scraper or stays as exception)

**Current state:**
- Single: `jwd-scraper.js` — Puppeteer
- Cron: None

**Decision needed:** Create a full-schedule scraper for JWD, or keep single-only?
- JWD website (`dg-net.org/th/service-shipping`) has a simple table — can scrape all
- If few JWD shipments, may not be worth the effort

**Steps (if creating full-schedule):**
1. Create `jwd-full-schedule-scraper.js` based on existing `jwd-scraper.js` table parsing
2. Add `--vessel`/`--voyage` filter support
3. Update PHP
4. Add JWD to `ScrapeVesselSchedules.php` `$scrapableTerminals`

---

## After All Phases

### Cleanup
- Remove unused single scraper files (after verifying everything works)
- Remove unused wrapper files
- Update `finding_and_problem_ETA_2.md` with final status

### PHP Consolidation (optional future work)
- Consolidate `VesselTrackingService` terminal methods into one generic method
- Consolidate `BrowserAutomationService` scrape methods
- Single calling pattern: `runScraper($terminal, $vessel, $voyage)`

---

## Testing Strategy

For each phase:
1. Run scraper in **cron mode** (no args) — verify full schedule output unchanged
2. Run scraper in **single mode** (`--vessel X --voyage Y`) — verify correct vessel returned
3. Test with **dirty voyage** (e.g., `V.1060S`) — normalization happens in PHP, scraper receives clean value
4. Test through **PHP artisan tinker** — call the VesselTrackingService method directly
5. Test through **UI** — trigger ETA check from transport screen

Use latest vessel per terminal from production DB:
```sql
SELECT s.port_terminal, v.name, s.voyage, s.id
FROM shipments s LEFT JOIN vessels v ON s.vessel_id = v.id
INNER JOIN (SELECT port_terminal, MAX(id) max_id FROM shipments WHERE port_terminal != '' GROUP BY port_terminal) s2 ON s.id = s2.max_id
ORDER BY s.port_terminal;
```

---

## Key Considerations

1. **Fix bugs before merge** — P0/P1 bugs are now fixed (session 3). Merge can proceed.
2. **LCIT filter mode should use API params, not scrape-all-then-filter** — LCIT API supports native filtering, much faster than fetching 700+ vessels.
3. **Fuzzy voyage matching** — Port `generateVoyageVariations()` from `tips-scraper.js` if needed in Node.js filter logic. But since PHP now normalizes voyage centrally, this may not be needed.
4. **Output format** — In filter mode, return flat `{ success, vessel_name, voyage_code, eta, ... }`. In cron mode, return `{ success, vessels: [...] }`. PHP code already expects these formats.
5. **Two copies of code** — The git repo is at the Windows path. The WSL clone at `/home/dragonnon2/projects/logistic_auto/` is used for running scrapers. After editing the git repo, sync changes to WSL for testing (or `git pull` in WSL clone).
