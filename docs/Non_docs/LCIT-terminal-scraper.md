# LCIT Terminal Scraper - Investigation Summary

Date: 2026-02-27

## Overview

LCIT terminal (ports B5, C3) has **two separate scrapers** and **two code paths** for ETA checking.

---

## 1. Daily Full Schedule Scraper (HTTP API)

**File:** `browser-automation/scrapers/lcit-full-schedule-scraper.js`
**Called by:** `php artisan vessel:scrape-schedules` (cron) -> `BrowserAutomationService::scrapeLcitFullSchedule()`
**Technology:** Plain `https` (Node.js built-in) + `xmldom` for XML parsing

### How it works
- Calls LCIT's ASMX web service: `https://www.lcit.com/Lcit.asmx/GetFullVessel?vessel=%&voy=`
- The `%` wildcard returns ALL vessels (~754 vessels as of 2026-02-27)
- Response is XML in Microsoft .NET diffgram format
- Parsed with `xmldom` DOMParser, extracting `<AllVesselSchedule>` elements
- Results stored in `vessel_schedules` DB table with 48-hour expiry

### XML Fields
| Field | Meaning |
|---|---|
| `VSC_BERTH` | Berth code (e.g., `C3E` -> `C3`) |
| `VISIT_VSL_NAME_AN` | Vessel name |
| `EXT_IN_VOY_C` | Inbound voyage code |
| `EXT_VOY_C` | Outbound voyage code |
| `INIT_ETB_TM` | Estimated berthing time (ETA) |
| `INIT_ETD_TM` | Estimated departure time (ETD) |
| `CUTOFF_TM` | Cargo cutoff time |
| `RECEIVE_TM` | Open gate / receiving time |
| `VISIT_STATE_CODE` | Status: `PA` (Pre-Arrival), `OD` (On Dock), `DP` (Departed) |
| `VSL_BERTH_D` | Actual berthing date (used when status is OD/DP) |
| `EST_DPTR_D` | Actual departure date (used when status is DP) |

### Date format
`DD MMM YY/HH:MM` (e.g., `28 FEB 26/12:00`)

### Dependencies
- `https` (Node.js built-in)
- `xmldom` (in package.json)

---

## 2. Single Vessel Scraper (HTTP API - Rewritten 2026-02-27)

**File:** `browser-automation/scrapers/lcit-scraper.js`
**Wrapper:** `browser-automation/lcit-wrapper.js`
**Called by:** `VesselTrackingService::lcit()` via `proc_open('node lcit-wrapper.js {vessel} {voyage}')`
**Technology:** Same HTTP API as the daily scraper (rewritten from playwright)

### How it works
- Called when user clicks "Check ETA" and vessel is NOT in DB cache
- Calls the same LCIT ASMX API with specific vessel name: `?vessel=CNC%20JAGUAR&voy=N806S`
- Returns single match as JSON to stdout (PHP reads via proc_open)
- Logs go to stderr (separated from JSON output)

### Output format (JSON to stdout)
```json
{
  "success": true,
  "vessel_name": "CNC JAGUAR",
  "voyage_code": "0N806S1NC",
  "berth": "C3",
  "eta": "2026-02-28T12:00:00",
  "etd": "2026-03-02T12:00:00",
  "cutoff": "2026-02-27T12:00:00",
  "opengate": "2026-02-23T12:00:00",
  "raw_data": { "table_row": [...], "status": "PA" }
}
```

### Dependencies
- `https` (Node.js built-in)
- `xmldom` (in package.json)

### Previous version (before rewrite)
- Used `playwright` + headless Chromium to load `https://www.lcit.com/vessel?vsl=...&voy=...`
- Playwright was NEVER added to package.json, so it never actually worked on production
- The old file is kept as `browser-automation/scrapers/lcit-scraper-old.js`

---

## ETA Check Flow (how both scrapers connect)

```
User clicks "Check ETA" for LCIT vessel
    |
    v
VesselTrackingService::checkVesselETAWithParsedName()
    |
    +--> 1. Check DB cache (vessel_schedules table)
    |        |
    |        +--> Found? Return cached result (instant)
    |        |    (populated by daily cron scraper)
    |        |
    |        +--> Not found? Fall through to live scraping
    |
    +--> 2. Live scrape: VesselTrackingService::lcit()
             |
             +--> proc_open('node lcit-wrapper.js {vessel} {voyage}')
                  |
                  +--> lcit-scraper.js (HTTP API call)
                       |
                       +--> Returns JSON result
```

---

## Root Cause: Why LCIT ETA stopped working after Jan 8, 2026

### Finding
- The daily cron job (`php artisan schedule:run` -> `vessel:scrape-schedules`) stopped running on production around Jan 8, 2026
- DB cache entries expire after 48 hours, so all LCIT data in `vessel_schedules` expired
- Without DB cache, every ETA check fell through to the live scraper
- The live scraper (old version) used `playwright` which was never installed -> always failed
- Result: "Vessel not found" for all LCIT vessels

### Evidence
- No code changes between Oct 20, 2025 and Feb 21, 2026
- The LCIT API endpoint (`Lcit.asmx/GetFullVessel`) is still working (tested 2026-02-27, returns 754 vessels)
- The full schedule scraper (`lcit-full-schedule-scraper.js`) works correctly when run manually

### Fix applied (2026-02-27)
- Rewrote `lcit-scraper.js` to use HTTP API instead of playwright
- Now even without the daily cron, individual ETA checks will work

### Still needs investigation on production
- Why did `php artisan schedule:run` stop executing?
- Check server crontab: `crontab -l` for the Laravel scheduler entry
- Check `eta_check_schedules` table: are `vessel_scrape` type records `is_active = true`?
- Check `daily_scrape_logs` table: when was the last successful LCIT scrape?

---

## Files Reference

| File | Purpose |
|---|---|
| `browser-automation/scrapers/lcit-full-schedule-scraper.js` | Daily cron: scrapes ALL vessels via API |
| `browser-automation/scrapers/lcit-scraper.js` | Single vessel: scrapes ONE vessel via API (rewritten) |
| `browser-automation/scrapers/lcit-scraper-old.js` | Old single vessel scraper (playwright, broken) |
| `browser-automation/lcit-wrapper.js` | CLI wrapper for lcit-scraper.js |
| `app/Services/VesselTrackingService.php` (lcit method ~L575) | PHP: calls lcit-wrapper.js via proc_open |
| `app/Services/BrowserAutomationService.php` (scrapeLcitFullSchedule ~L581) | PHP: calls lcit-full-schedule-scraper.js |
| `app/Console/Commands/ScrapeVesselSchedules.php` (scrapeLcit ~L343) | Artisan command for daily cron |
| `bootstrap/app.php` (withSchedule ~L19) | Laravel scheduler config |

---

## .gitignore Fix (also applied 2026-02-27)

Changed `/node_modules` to `node_modules` (removed leading `/`) so that `browser-automation/node_modules/` is also ignored by git. Previously only root `node_modules/` was ignored, causing ~3997 files to be tracked in git.
