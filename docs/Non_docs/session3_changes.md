# Session 3 Changes — P0/P1 Bug Fixes

Date: 2026-03-03
Branch: (current working branch)
Previous context: `finding_and_problem_ETA_2.md`

---

## P0: Removed `2>/dev/null` from all scraper commands

**Problem:** All 9 scraper shell commands had `2>/dev/null` appended, silencing stderr. This meant:
- Chromium crashes, missing libs, Node.js errors were all invisible
- `proc_open` pipe for stderr always received empty string
- Production cron logged `status=success, vessels_found=0` for weeks with no error info

**Files changed:**

### `app/Services/BrowserAutomationService.php` (7 places)
Removed ` 2>/dev/null` from `sprintf` format strings in:
- `scrapeHutchisonFullSchedule()` — was `'%s %s %s 2>/dev/null'`
- `scrapeShipmentlinkFullSchedule()` (line ~461)
- `scrapeTipsFullSchedule()` — was `'%s %s 2>/dev/null'`
- `scrapeEscoFullSchedule()`
- `scrapeLcitFullSchedule()`
- `scrapeShipmentlinkB2FullSchedule()`
- `scrapeLcb1FullSchedule()`

All changed to same format without `2>/dev/null`. The `shell_exec()` will now capture stderr alongside stdout.

### `app/Services/VesselTrackingService.php` (2 places)
- `tips_browser()` (line ~481): `"cd %s && timeout 120 node tips-wrapper.js %s %s 2>/dev/null"` → removed suffix
- `shipmentlink_browser()` (line ~887): `"cd %s && timeout 30 node scrapers/shipmentlink-https-scraper.js %s 2>/dev/null"` → removed suffix

These use `proc_open` with stderr pipe — now `$pipes[2]` will actually capture error output.

---

## P1a: Fixed TIPS full-schedule column mapping

**Problem:** The TIPS website `<thead>` has 13 `<th>` elements but `<tbody>` rows have only 11 `<td>` cells (due to colspan grouping). The scraper used header-based indices which were wrong for data rows.

**What was wrong:**
```js
// OLD (wrong):
const eta = cells[10]  // Got SERVICE CODE (CVT1, TID2)
const etd = cells[7]   // Got CLOSING TIME
const berth = cells[11] // OUT OF BOUNDS (undefined)
```

**What it is now:**
```js
// NEW (correct):
const eta = cells[9]     // Real ETA date (e.g., "02/03/2026 17:00")
const service = cells[10] // Service code (CVT1, TID2, etc.)
```

**File changed:** `browser-automation/scrapers/tips-full-schedule-scraper.js`
- Updated column comments (lines 85-91) to show actual 11-cell layout
- Changed `cells[10]` to `cells[9]` for ETA
- Replaced `etd`/`berth` with `service` field
- Output object now has `{ vessel_name, voyage, eta, service }` instead of `{ vessel_name, voyage, eta, etd, berth }`

**Note:** The PHP cron code in `ScrapeVesselSchedules.php::scrapeTips()` references `$vessel['etd']` and `$vessel['berth']` — these now resolve to `null`, which is correct since TIPS data rows don't have those columns. Berth defaults to `'B4'`.

**Note:** This file was also edited in the WSL clone at `/home/dragonnon2/projects/logistic_auto/` for testing.

---

## P1b: Central voyage normalization

**Problem:** Dirty voyage values from user input passed raw to scrapers:
- `V.1060S` → LCIT API returned "not found" (expects `1060S`)
- ` 0284S` → leading space caused mismatch
- `V. 251S` → combined prefix + space

**Fix location:** `app/Services/VesselTrackingService.php` — top of `checkVesselETAWithParsedName()` method (line ~198)

```php
$parsedVessel['voyage_code'] = trim(preg_replace('/^V\.?\s*/i', '', $parsedVessel['voyage_code'] ?? ''));
```

This runs **before** both the DB cache lookup and the live scraper call, so all downstream code receives clean voyage.

**Normalization results verified:**
| Input | Output |
|-------|--------|
| `V.1060S` | `1060S` |
| `V.2602S` | `2602S` |
| `V. 251S` | `251S` |
| `V. 0N806S` | `0N806S` |
| ` 0284S` | `0284S` |
| ` 251S` | `251S` |
| `2606S` | `2606S` (unchanged) |

---

## P1c: Vessel name trimming

**Problem:** Some vessel names had trailing spaces (e.g., `SM JAKARTA `) causing scraper mismatch.

**Fix location:** Same method, same location as P1b (line ~197):

```php
$parsedVessel['vessel_name'] = trim($parsedVessel['vessel_name']);
```

---

## Test Results

| Test | Input | Result |
|------|-------|--------|
| TIPS column fix | Full schedule scrape | ETAs show dates (`02/03/2026 17:00`) not service codes |
| TIPS NATTHA BHUM | Specific vessel check | ETA `06/03/2026 16:00`, service `TID2` |
| LCIT dirty voyage | `V.1060S` | Regex strips to `1060S` — vessel found |
| LCIT clean voyage | `1060S` | Vessel found (unchanged) |
| LCIT latest vessel | SAMAL / 2606S | success, ETA 2026-03-02, berth B5 |
| Regex edge cases | All 8 dirty patterns | All normalized correctly |

---

## Files Modified (summary)

| File | Changes |
|------|---------|
| `app/Services/BrowserAutomationService.php` | Removed `2>/dev/null` from 7 shell commands |
| `app/Services/VesselTrackingService.php` | Removed `2>/dev/null` from 2 commands + added voyage normalization + vessel trim |
| `browser-automation/scrapers/tips-full-schedule-scraper.js` | Fixed column mapping (cells[10]→cells[9]), updated comments, replaced etd/berth with service |
