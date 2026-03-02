# ETA Vessel Tracking — Findings & Problems (Session 2)

Date: 2026-03-02
Continuation of: `finding_and_problem_ETA.md` (Session 1)

---

## What Was Done This Session

1. Downloaded production database dump to WSL local dev (`vessel` DB, 1786 shipments)
2. Tested all 9 terminal scrapers (both single/live and daily cron) on WSL
3. Identified 6 new bugs/issues with evidence from live testing and production data

---

## Test Environment

- **WSL local dev** at `/home/dragonnon2/projects/logistic_auto/`
- **Database:** production dump imported as `vessel` DB (original `logistic_auto` dev DB still intact)
- **Chromium deps:** installed `libnss3` + related libs via apt-get (was missing, caused all Puppeteer scrapers to fail)

---

## Scraper Test Results

### Single Scrapers (live, per-vessel lookup)

| Terminal | Port | Test Vessel / Voyage | Result | Notes |
|----------|------|----------------------|--------|-------|
| **LCIT** | B5/C3 | SAMAL / 2606S | **WORKS** | HTTP API, no Puppeteer needed |
| **Hutchison** | C1/C2 | XIN QING DAO / 251S | **WORKS** (vessel not in schedule) | Puppeteer works. Vessel genuinely not listed. |
| **TIPS** | B4 | NATTHA BHUM / 050N | **WORKS** | vessel_found=true, ETA=07/03/2026 |
| **ESCO** | B3 | (full schedule) | **WORKS** | Returns 32 vessels including STARSHIP AQUILA |
| **LCB1** | A0/B1 | KOTA LAYANG / 609W | **WORKS** | Found at B1, ETA=2026-03-03 20:00 |
| **ShipmentLink** | B2 | (tested) | **WORKS** | Scraper runs correctly |
| **JWD** | JWD | (tested) | **WORKS** | Scraper runs correctly |
| **Kerry** | KLN | (skipped) | **SKIPPED** | API now requires password; will test later |
| **Siam** | SIAM | (skipped) | **SKIPPED** | n8n webhook placeholder |

### Daily Cron Scrapers (full schedule)

| Terminal | Result | Vessel Count | Notes |
|----------|--------|:------------:|-------|
| **Hutchison C1** | **WORKS** | 67 | Puppeteer needed |
| **TIPS** | **WORKS** (but data is wrong) | 44 | ETA field contains service codes, not dates! (see Finding 1) |
| **ESCO** | **WORKS** | 32 | Puppeteer needed |
| **LCIT** | **WORKS** | many (large output) | HTTP API, no Puppeteer |
| **LCB1** | **TIMEOUT** (>60s) | 392 to scrape | Puppeteer, scraping one-by-one, too slow |
| **ShipmentLink** | **TIMEOUT** (>60s) | 813 to scrape | Puppeteer, scraping one-by-one, too slow |

**Note:** LCB1 and ShipmentLink are already commented out of `$scrapableTerminals` in `ScrapeVesselSchedules.php` (line 37-38) with notes "keep as live-scrape only".

---

## Finding 1: TIPS Full Schedule Scraper — Column Mapping is WRONG

### How I Found This

Ran the TIPS cron scraper locally and noticed the returned data had service codes in the ETA field:

```bash
cd /home/dragonnon2/projects/logistic_auto/browser-automation
node scrapers/tips-full-schedule-scraper.js
```

Sample output:
```json
{"vessel_name":"NATTHA BHUM","voyage":"050N","eta":"TID2","etd":"23/02/2026 08:54","berth":null}
{"vessel_name":"A GORYU","voyage":"2605S","eta":"CVT1","etd":"","berth":null}
```

`"TID2"` and `"CVT1"` are service route codes, not ETA dates.

### Verification — Live Website Column Headers

Scraped the actual TIPS website (`https://www.tips.co.th/container/shipSched/List`) with Puppeteer to extract headers and sample data:

**Headers (from `<thead>`):** 13 `<th>` elements
```
0: Vessel Name | 1: Id | 2: Radio Call Sign | 3: I/B Vyg | 4: O/B Vyg
5: Esitmate | 6: Actual | 7: Closing Time | 8: Service
9: ETA | 10: ETD | 11: ATA | 12: ATD
```

**Data rows (from `<tbody>`):** only 11 `<td>` elements per row (header has grouped columns with colspan)

**A GORYU sample data:**
```
[0] "A GORYU"           → vessel name
[1] "AGY"               → id
[2] "3E2296"            → radio call sign
[3] "2605S"             → I/B voyage
[4] "2605N"             → O/B voyage
[5] "03/03/2026 17:00"  → gate open (estimate)
[6] "04/03/2026 02:00"  → gate open (actual)
[7] ""                  → closing time (estimate)
[8] ""                  → closing time (actual)
[9] "02/03/2026 17:00"  → ETA ← THIS IS THE REAL ETA
[10] "CVT1"             → Service code ← SCRAPER THINKS THIS IS ETA
```

### The Bug

**File:** `browser-automation/scrapers/tips-full-schedule-scraper.js` lines 104-106

```js
// Current code (WRONG):
const eta = cells[10]?.innerText?.trim();   // ← Gets SERVICE CODE (CVT1, TID2, RBC)
const etd = cells[7]?.innerText?.trim();    // ← Gets CLOSING TIME estimate, not ETD
const berth = cells[11]?.innerText?.trim(); // ← OUT OF BOUNDS (only 11 cells, max index 10)
```

**Should be:**
```js
// Correct mapping:
const eta = cells[9]?.innerText?.trim();     // ← Real ETA date
const service = cells[10]?.innerText?.trim(); // ← Service code (CVT1, TID2, etc.)
// No ETD column in data rows
// No berth column in data rows
```

### Impact

If the TIPS cron scraper ever successfully stores data to `vessel_schedules`, all TIPS entries would have garbage ETA values (service codes like "CVT1" instead of dates). Date parsing would fail or produce wrong results.

Currently this bug has no production impact because the cron scraper fails silently anyway (Finding 3), but it must be fixed before the cron can work correctly.

---

## Finding 2: Voyage Not Normalized Before Calling Node.js Scrapers

### How I Found This

Queried the production database for shipments with `tracking_status = 'not_found'` and shipments with voyage starting with "V." or leading spaces:

```php
// Shipments with not_found status
Shipment::where('tracking_status','not_found')->orderBy('id','desc')->take(10)->get();

// Shipments with "V." prefix
Shipment::where('voyage','like','V.%')->orWhere('voyage','like','V %')->get();

// Shipments with leading spaces
Shipment::where('voyage','like',' %')->get();
```

### Evidence from Production Data

**3 types of dirty voyage input causing `not_found`:**

| Type | Examples | Affected Shipment IDs |
|------|---------|----------------------|
| **"V." prefix** | `V.1060S`, `V.2602S`, `V. 251S`, `V. 0N806S` | #1808, #1807, #1806, #1810 |
| **Leading spaces** | ` 0284S`, ` 251S`, ` V.2602S` | #1819, #1818, #1817, #1799, #1761 |
| **Trailing space in vessel name** | `SM JAKARTA ` (with trailing space) | #1811 |

### Where Normalization Exists vs Doesn't

**PHP side (`VesselTrackingService.php` lines 1175-1200):**
- Has `voyageSearchVariations` logic that strips "V." prefix and handles spaces
- BUT this is **only used when parsing HTML** (Kerry/Siam terminals and DB cache search)
- It is **NOT applied before calling Node.js scrapers** — raw dirty voyage passes through to `escapeshellarg($voyageCode)`

**Node.js scraper side:**

| Terminal | Voyage Normalization | Detail |
|----------|---------------------|--------|
| **TIPS** | Has it | `generateVoyageVariations()` — strips V., creates 4-5 variations |
| **ESCO** | Partial | Strips `M.V.` prefix from vessel name only (not voyage) |
| **Hutchison** | None | Case-insensitive only; no voyage param (searches by vessel name only) |
| **LCIT** | None | `.toUpperCase()` + `includes()` only |
| **LCB1** | None | Pattern match `\d{3}S/N` |
| **ShipmentLink** | None | Expects clean format like `0815-079S` |
| **JWD** | None | Exact string match, case-sensitive |

### The Flow That Fails

```
User enters "V.1060S" as voyage
    → VesselNameParser: stores as "V.1060S" (no normalization)
    → VesselTrackingService::lcit(): sends "V.1060S" via escapeshellarg()
    → lcit-wrapper.js: receives "V.1060S"
    → lcit-scraper.js: calls API with ?voy=V.1060S → NOT FOUND
    (API expects "1060S")
```

Tested this directly against LCIT API:
- `?voy=V.1060S` → **Not Found**
- `?voy=1060S` → **Found** (POS HOCHIMINH at C3)

### Fix Approach

Normalize voyage at a central point in PHP **before** passing to any scraper:
```php
// Strip "V." / "V. " / "V " prefix
$voyageCode = preg_replace('/^V\.?\s*/i', '', $voyageCode);
// Trim leading/trailing spaces
$voyageCode = trim($voyageCode);
```

This should be applied in `checkVesselETAWithParsedName()` before calling terminal-specific methods.

---

## Finding 3: `2>/dev/null` Silences ALL Scraper Errors

### How I Found This

Investigated why production `daily_scrape_logs` showed `status='success'` with `vessels_found=0` and `duration_seconds=0` for every terminal. Searched for `2>/dev/null` in the PHP code:

```
grep -n '2>/dev/null' app/Services/BrowserAutomationService.php
```

### Evidence

**7 locations in `BrowserAutomationService.php`** (all cron scraper methods):
- Line 413 (Hutchison), 461 (ShipmentLink), 508 (TIPS), 550 (ESCO), 592 (LCIT), 634 (ShipmentLink B2), 663 (LCB1)

**2 locations in `VesselTrackingService.php`** (single/live scraper methods):
- Line 481 (TIPS), 887 (ShipmentLink)

### The Paradox

The code sets up `proc_open` with a stderr pipe to capture error output:
```php
$descriptors = [
    0 => ['pipe', 'r'],  // stdin
    1 => ['pipe', 'w'],  // stdout (JSON)
    2 => ['pipe', 'w']   // stderr (logs) ← SET UP TO CAPTURE
];
```

But the command itself redirects stderr to /dev/null:
```php
$command = "cd %s && timeout 120 node tips-wrapper.js %s %s 2>/dev/null";
//                                                        ^^^^^^^^^^
//                                                        DISCARDS ALL ERRORS
```

The `2>/dev/null` in the shell command runs **before** proc_open's pipe can capture it. So `$logOutput = stream_get_contents($pipes[2])` always gets empty string. The error logging code never receives any data.

### How This Causes "Success with 0 Vessels" on Production

```
1. Chromium missing on production
2. Node.js scraper tries to launch Puppeteer → fails → prints error to stderr
3. stderr is discarded by 2>/dev/null
4. shell_exec() returns null (no stdout output)
5. PHP method returns null
6. Cron code: if (!$result) → "No data returned" → continues with count=0
7. Log entry: status='success', vessels_found=0, duration_seconds=0
```

### Production Log Evidence

Last 20 `daily_scrape_logs` entries from production DB:

```
ID   | terminal   | status  | found | created | updated | dur | date
1012 | lcit       | success | 0     | 0       | 0       | 0s  | 2026-03-02 07:00
1011 | esco       | success | 0     | 0       | 0       | 0s  | 2026-03-02 07:00
1010 | tips       | success | 0     | 0       | 0       | 0s  | 2026-03-02 07:00
1009 | hutchison  | success | 0     | 0       | 0       | 0s  | 2026-03-02 07:00
...  (same pattern repeating for weeks)
1000 | lcit       | success | 757   | 309     | 448     | -14s| 2026-02-27 17:53  ← ONLY successful run (manual test)
```

Only log #1000 (LCIT on Feb 27, when we manually tested the LCIT fix) ever found vessels.

### Fix

Remove `2>/dev/null` from all commands. The proc_open pipe (`$pipes[2]`) will then correctly capture stderr for logging.

**In `BrowserAutomationService.php`:** Change all 7 occurrences:
```php
// Before:
'%s %s %s 2>/dev/null'
// After:
'%s %s %s'
```

**In `VesselTrackingService.php`:** Change 2 occurrences (lines 481, 887).

Note: LCIT scraper at line 585 does NOT have `2>/dev/null` — this is correct and is why it worked when tested.

---

## Finding 4: Hutchison — Vessel Name Search Only, No Voyage

### How I Found This

Tested `node hutchison-wrapper.js "XIN QING DAO" "251S"` — vessel not found. Then read the Hutchison wrapper code.

### Detail

`hutchison-wrapper.js` only accepts vessel name as argument:
```js
// Line 14:
const vesselName = process.argv[2];
// No voyage code argument
```

The scraper then does a text search across all paginated pages on Hutchison's Oracle APEX website. If the vessel isn't currently listed (departed or too far in future), it won't be found.

The cron scraper returned 67 vessels for C1 — XIN QING DAO was not among them. This is a **data timing issue**, not a code bug.

However, the Hutchison single scraper could be improved by also accepting voyage code for more precise matching.

---

## Finding 5: ACX PEARL — Empty port_terminal + Leading Space in Voyage

### How I Found This

Found in the production database query for `not_found` shipments:

```
#1819 | ACX PEARL | voy:[ 0284S] | port:     | last_check:2026-03-02 08:18:34
#1818 | ACX PEARL | voy:[ 0284S] | port:C1C2 | last_check:2026-03-02 08:18:02
```

### Double Problem

1. **Shipment #1819 has empty `port_terminal`** — system can't determine which terminal scraper to call → fails immediately
2. **Leading space in voyage** `" 0284S"` — even if the correct scraper is called, the space causes mismatch

This suggests the shipment creation form doesn't validate:
- Required port_terminal field
- Whitespace in voyage field

---

## Finding 6: KLN Port Not Mapped to Any Terminal

### How I Found This

Checked the `$portToTerminal` mapping in `VesselTrackingService.php` (lines 18-55). Port code `KLN` is not in the list.

```php
protected $portToTerminal = [
    'C1' => 'hutchison',
    'C2' => 'hutchison',
    'B4' => 'tips',
    'B5' => 'lcit',
    // ... etc
    // KLN is NOT listed
];
```

### Production Impact

```
#1824 | GSL AFRICA | voy:980N | port:KLN  | tracking_status:(empty)
#1823 | GSL AFRICA | voy:980N | port:KLN  | tracking_status:(empty)
#1735 | HMM MIRACLE | voy: 0015S | port:KLN
#1703 | KMTC TOKYO | voy: 2601S | port:KLN
```

Multiple shipments use KLN. ETA check will always fail because the port can't be routed to any scraper.

**Note:** KLN likely refers to Kerry Logistics terminal. The Kerry API now requires a password. Will investigate in a future session once credentials are available.

---

## Production Database Analysis — Not_Found Patterns

All shipments with `tracking_status = 'not_found'` from production:

| ID | Vessel | Voyage | Port | Likely Cause |
|----|--------|--------|------|-------------|
| 1821 | XIN QING DAO | 251S | C1C2 | Vessel not in current Hutchison schedule |
| 1820 | XIN QING DAO | 251S | C1C2 | Same (duplicate shipment) |
| 1819 | ACX PEARL | ` 0284S` | (empty) | Empty port + leading space in voyage |
| 1818 | ACX PEARL | ` 0284S` | C1C2 | Leading space in voyage |
| 1817 | ACX PEARL | ` 0284S` | C1C2 | Leading space in voyage (duplicate) |
| 1811 | SM JAKARTA  | 2602W | A0 | Trailing space in vessel name |
| 1808 | POS HOCHIMINH | V.1060S | B5 | "V." prefix not stripped |
| 1807 | KMTC XIAMEN | V.2602S | A0 | "V." prefix not stripped |
| 1806 | XIN QING DAO | V. 251S | C1C2 | "V. " prefix + vessel not in schedule |
| 1805 | KMTC XIAMEN | 2602S | A0 | Clean voyage — LCB1 scraper might need testing |

---

## Fix Priority List (for next session)

| Priority | Issue | Files to Change | Effort |
|----------|-------|----------------|--------|
| **P0** | Remove `2>/dev/null` from all scraper commands | `BrowserAutomationService.php` (7 places), `VesselTrackingService.php` (2 places) | 10 min |
| **P1** | Fix TIPS column mapping (cells[10] → cells[9]) | `tips-full-schedule-scraper.js` line 104-106 | 5 min |
| **P1** | Add central voyage normalization (strip "V.", trim spaces) | `VesselTrackingService.php` in `checkVesselETAWithParsedName()` | 15 min |
| **P1** | Add vessel name trimming | Same location | 5 min |
| **P2** | Add KLN to port mapping | `VesselTrackingService.php` line 18-55 | 5 min (needs Kerry password) |
| **P2** | Add port_terminal validation in shipment form | Blade/controller files | 15 min |
| **P3** | Production: install Chromium system libs | Server admin task | Needs sudo |
| **P3** | Production: deploy latest code | `git pull` on server | Needs access |

---

## Files Reference (additions to Session 1)

| File | Purpose |
|------|---------|
| `browser-automation/scrapers/tips-full-schedule-scraper.js` | TIPS cron scraper — has column mapping bug |
| `browser-automation/scrapers/tips-scraper.js` | TIPS single scraper — uses heuristic date extraction |
| `browser-automation/tips-wrapper.js` | TIPS wrapper for PHP → Node.js bridge |
| `browser-automation/hutchison-wrapper.js` | Hutchison wrapper — only takes vessel name (no voyage) |
| `app/Services/BrowserAutomationService.php` | PHP service calling Node.js scrapers — has `2>/dev/null` everywhere |

---

## How to Reproduce Tests

```bash
# Set clean PATH (avoid Windows npm leaking into WSL)
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

# Ensure MySQL is running
sudo service mysql start

# Test individual scrapers from browser-automation/
cd /home/dragonnon2/projects/logistic_auto/browser-automation

# LCIT (HTTP API — always works)
node lcit-wrapper.js "SAMAL" "2606S"

# Hutchison (Puppeteer — needs libnss3)
node hutchison-wrapper.js "XIN QING DAO"

# TIPS (Puppeteer)
node tips-wrapper.js "NATTHA BHUM" "050N"

# ESCO (Puppeteer — uses cron scraper for single lookup too)
node scrapers/esco-full-schedule-scraper.js

# LCB1 (Puppeteer)
node lcb1-wrapper.js "KOTA LAYANG" "609W"

# Test cron scrapers
node scrapers/hutchison-full-schedule-scraper.js C1
node scrapers/tips-full-schedule-scraper.js
node scrapers/lcit-full-schedule-scraper.js
node scrapers/esco-full-schedule-scraper.js

# Get TIPS actual column headers (verification script)
node -e "
const puppeteer = require('puppeteer');
(async () => {
  const browser = await puppeteer.launch({headless:true, args:['--no-sandbox']});
  const page = await browser.newPage();
  await page.goto('https://www.tips.co.th/container/shipSched/List', {waitUntil:'networkidle2'});
  await new Promise(r=>setTimeout(r,2000));
  const headers = await page.evaluate(() => {
    const ths = document.querySelectorAll('table thead tr th');
    return Array.from(ths).map((th,i) => i + ': ' + th.innerText.trim());
  });
  const rows = await page.evaluate(() => {
    const trs = document.querySelectorAll('table tbody tr');
    return Array.from(trs).slice(0,2).map(tr => {
      const cells = tr.querySelectorAll('td');
      return Array.from(cells).map((td,i) => i + ': ' + td.innerText.trim());
    });
  });
  console.log(JSON.stringify({headers, sample_rows: rows}, null, 2));
  await browser.close();
})();
"
```

---

## Database Info (local WSL)

- **Production dump:** imported as `vessel` database
- **Dev seed data:** still in `logistic_auto` database
- **Switch between them:** change `DB_DATABASE` in `/home/dragonnon2/projects/logistic_auto/.env`
- **Current setting:** `DB_DATABASE=vessel` (production data)
- **SCP from production:** `sshpass -p '2F8UHt6FWBxNb0NuY3oM' scp -P 4889 vessel-ssh@103.125.93.219:~/vessel_backup.sql /home/dragonnon2/vessel_backup.sql`
