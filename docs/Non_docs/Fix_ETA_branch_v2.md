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

<!-- Append Bug 2, 3, etc. below this line -->
