# Fix ETA Branch v2 — Bug Fixes

## Bug 1: Node.js not found + Chrome/Puppeteer not installed on production

**Date fixed:** 2026-03-08

### Affected shipments (5)

| # | Terminal | Port | Vessel | Voyage | Error |
|---|----------|------|--------|--------|-------|
| 5 | LCB1 | A0 | INCHEON VOYAGER | 2602S | Bot can't find vessel (no specific error) |
| 6 | TIPS | B4 | MAKHA BHUM | 130N | "Browser automation process failed" |
| 10 | LCB1 | A0 | POS LAEMCHABANG | 1034W | "Could not find Chrome (ver. 127.0.6533.88)" |
| 11 | LCB1 | A0 | DONGJIN CONFIDENT | 0143S | Same Chrome error as #10 |
| 12 | LCB1 | A0 | HEUNG-A HOCHIMINH | 2603S | Same Chrome error as #10 |

Note: Some A0 vessels (e.g. PANCON BRIDGE 2603S) worked because they were pre-cached in `vessel_schedules` from a previous successful cron scrape — the ETA check hit the DB cache and never triggered the live Puppeteer scraper.

### Root cause

Two separate issues on the production server:

**Problem 1: Wrong deployment directory**

PR #2 (Fix ETA + merge scrapers) was deployed to `/home/easternair-vessel/htdocs/vessel.easternair.co.th/` but the live site serves from `/home/easternair-vessel/htdocs/logistic_auto/`. The old code (with bare `node` calls instead of `BrowserAutomationService::getNodePath()`) was still running.

**Problem 2: PHP-FPM can't find `node`**

Even after fixing the deployment directory, the scrapers ran `timeout 30s node scrapers/...` with bare `node`. PHP-FPM runs as `easternair-vessel` user with a minimal PATH (`/usr/bin:/bin`) that doesn't include `/usr/local/bin`. So even the first symlink at `/usr/local/bin/node` didn't work for PHP-FPM processes.

The Chrome/Puppeteer error occurred because without Node.js being found, `npm install` in `browser-automation/` was never run properly, and Puppeteer's Chrome binary was missing from `/var/www/.cache/puppeteer`.

### Fix applied

**Code fix (PR #2, already merged):**
- `app/Services/BrowserAutomationService.php` — Updated `getNodePath()` to dynamically scan nvm directories instead of hardcoding paths
- `app/Services/VesselTrackingService.php` — All 6 single-vessel scraper methods (`hutchison_browser`, `tips_browser`, `lcit`, `esco`, `lcb1`, `shipmentlink_browser`) now use `BrowserAutomationService::getNodePath()` instead of bare `node`

**Production server fix:**
1. Git pulled PR #2 into the correct `/home/easternair-vessel/htdocs/logistic_auto/` directory
2. Ran `npm install` in `browser-automation/` (installs Puppeteer + Chrome)
3. Cleared Laravel caches
4. Created symlinks so both CLI and PHP-FPM can find Node.js:

```
/usr/local/bin/node → /home/vessel-ssh/.nvm/versions/node/v20.19.6/bin/node  (works for CLI, cron, artisan)
/usr/bin/node       → /home/vessel-ssh/.nvm/versions/node/v20.19.6/bin/node  (works for PHP-FPM)
```

5. Restarted PHP-FPM

---

## Bug 2: LCIT voyage mismatch — partial voyage codes don't match

**Date fixed:** 2026-03-08

### Affected shipment

| # | Terminal | Port | Vessel | Voyage (user) | Voyage (LCIT) | Error |
|---|----------|------|--------|---------------|---------------|-------|
| 3 | LCIT | B5 | CNC JAGUAR | N806S | 0N806S1NC | "Not Found" — ETA lookup fails silently |

### Root cause

User entered voyage `N806S` but LCIT stores the full voyage as `0N806S1NC`. The lookup failed at **two layers**:

**Layer 1: DB cache lookup (`VesselSchedule::findVessel()`)**

The `vessel_schedules` table (populated by daily cron) stores `voyage_code = '0N806S1NC'`. The query used exact match:

```php
// app/Models/VesselSchedule.php line 94-96 (BEFORE fix)
if ($voyageCode) {
    $query->where('voyage_code', $voyageCode);
}
```

`WHERE voyage_code = 'N806S'` → no match against `0N806S1NC`.

**Layer 2: Live LCIT scraper fallback**

When DB cache misses, the code falls back to the live LCIT API. The scraper passed the user's voyage directly to the API:

```js
// browser-automation/scrapers/lcit-full-schedule-scraper.js line 49 (BEFORE fix)
const url = `${this.apiUrl}?vessel=${encodeURIComponent(vesselName)}&voy=${encodeURIComponent(voyageCode || '')}`;
```

LCIT's server does exact matching on the `voy` parameter → `?voy=N806S` returns 0 results.

The scraper already had partial matching logic at lines 70-72:

```js
return voy === voyageUpper || voy.includes(voyageUpper) || voyageUpper.includes(voy);
```

But this code never ran because the API returned 0 vessels to match against.

### Fix applied (3 changes)

**Change 1: `app/Models/VesselSchedule.php` line 94-100 — Bidirectional contains matching**

```php
// AFTER fix
if ($voyageCode) {
    $query->where(function ($q) use ($voyageCode) {
        $q->where('voyage_code', $voyageCode)                              // exact match
          ->orWhere('voyage_code', 'LIKE', '%' . $voyageCode . '%')        // DB contains user input
          ->orWhereRaw('? LIKE CONCAT(\'%\', voyage_code, \'%\')', [$voyageCode]); // user input contains DB value
    });
}
```

- `voyage_code LIKE '%N806S%'` → matches `0N806S1NC` (DB value contains user input)
- `'N806S' LIKE CONCAT('%', voyage_code, '%')` → handles reverse case (user input contains DB value)
- Both directions needed because we don't know which side has the longer code

**Change 2: `app/Services/VesselTrackingService.php` line 214-217 — Consistent voyage_found flag**

```php
// BEFORE: exact match
$voyageMatches = !$parsedVessel['voyage_code'] ||
                 strcasecmp($dbSchedule->voyage_code, $parsedVessel['voyage_code']) === 0;

// AFTER: bidirectional contains
$voyageMatches = !$parsedVessel['voyage_code'] ||
                 stripos($dbSchedule->voyage_code, $parsedVessel['voyage_code']) !== false ||
                 stripos($parsedVessel['voyage_code'], $dbSchedule->voyage_code) !== false;
```

This ensures the `voyage_found` response flag reflects the same fuzzy logic used by the DB query.

**Change 3: `browser-automation/scrapers/lcit-full-schedule-scraper.js` line 51-59 — Retry without voyage**

```js
// AFTER fix — if API returns 0 results with voyage filter, retry without it
if (vessels.length === 0 && voyageCode) {
    console.error(`⚠️ No results with voyage filter "${voyageCode}", retrying without voyage...`);
    const retryUrl = `${this.apiUrl}?vessel=${encodeURIComponent(vesselName)}&voy=`;
    xmlData = await this.makeRequest(retryUrl);
    vessels = this.parseXML(xmlData);
}
```

First request: `?vessel=CNC+JAGUAR&voy=N806S` → 0 results (LCIT exact match fails).
Retry: `?vessel=CNC+JAGUAR&voy=` → returns ALL voyages for that vessel.
Then the existing JS partial matching at lines 70-72 finds `0N806S1NC` contains `N806S` → match.

---

<!-- Append Bug 3, 4, etc. below this line -->
