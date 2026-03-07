# Vessel Tracking - Production Bugs

## Bug 1: Single-vessel scrapers fail - "node not found" (exit code 127) / "Browser automation process failed"

### Symptoms
- **C1C2 (Hutchison):** `exit code: 127 'node': no such file or directory`
- **B4 (TIPS):** `ETA check failed: Browser automation process failed`
- All other single-vessel scrapers (LCIT, ESCO, LCB1, ShipmentLink) likely affected too
- Daily cron scrapers (full schedule) work fine
- Local PC works fine

### Root Cause

**Node.js is installed via nvm, not system-wide.**

On the production server, Node.js is only available at:
```
/home/vessel-ssh/.nvm/versions/node/v20.19.6/bin/node
```
There is no `/usr/local/bin/node` or `/usr/bin/node`.

**Two issues combined:**

1. **`.env` has wrong path:** `NODE_BINARY_PATH=/usr/local/bin/node` — this path doesn't exist on the server (it works on local PC because local PC has node installed system-wide).

2. **`VesselTrackingService.php` doesn't use `getNodePath()` at all.** The 6 single-vessel scraper methods hardcode bare `node` in the command string:
   ```php
   'cd %s && timeout 60s node scrapers/hutchison-full-schedule-scraper.js --vessel %s --voyage %s'
   ```
   Affected lines: 344, 475, 605, 789, 912, 1033.

**Why daily cron works but single-vessel doesn't:**
- Daily cron runs as `vessel-ssh` user via shell — nvm is loaded in `.bashrc`, so bare `node` resolves via PATH.
- Single-vessel scrapers run via PHP (web request / queue worker) — PHP doesn't load `.bashrc`, so `node` is not found.

**Why local PC works:**
- Local PC has Node.js installed system-wide at `/usr/local/bin/node`, so both bare `node` and the `.env` path work.

### Temporary Fix (applied on server 2026-03-07)

Created a symlink so `node` is available system-wide:
```bash
sudo ln -s /home/vessel-ssh/.nvm/versions/node/v20.19.6/bin/node /usr/local/bin/node
```
This fixes both bugs immediately without code changes. However, if nvm version changes, the symlink will break.

### Recommended Code Fix

#### Part A: Make `getNodePath()` dynamically find nvm node

In `BrowserAutomationService.php`, update `getNodePath()` to scan nvm directories dynamically instead of hardcoding paths:

```php
$possiblePaths = [
    'node',                        // System-wide (works on local PC + servers with global install)
    '/usr/local/bin/node',
    '/usr/bin/node',
];

// Dynamically scan nvm directories (handles any node version)
$homeDirs = [getenv('HOME'), '/home/vessel-ssh', '/home/deploy'];
foreach ($homeDirs as $home) {
    if ($home) {
        $nvmDir = $home . '/.nvm/versions/node';
        if (is_dir($nvmDir)) {
            $versions = glob($nvmDir . '/v*/bin/node');
            if (!empty($versions)) {
                // Use the latest version found
                $possiblePaths[] = end($versions);
            }
        }
    }
}
```

This approach:
- Tries system-wide `node` first (covers local PC + any server with global install)
- If not found, scans common nvm locations dynamically (no hardcoded version)
- Works on both local PC and production server without changing `.env`
- Survives nvm version upgrades automatically

#### Part B: Make single-vessel scrapers use `getNodePath()`

In `VesselTrackingService.php`, replace bare `node` with the resolved path in all 6 methods:

```php
$nodePath = BrowserAutomationService::getNodePath();
$command = sprintf(
    'cd %s && timeout 60s %s scrapers/hutchison-full-schedule-scraper.js --vessel %s --voyage %s',
    escapeshellarg(base_path('browser-automation')),
    escapeshellarg($nodePath),
    escapeshellarg($vesselName),
    escapeshellarg($voyageCode ?: '')
);
```

Apply this pattern to all 6 single-vessel scraper methods:
- `hutchison_browser()` — line 344
- `tips_browser()` — line 475
- `lcit_browser()` — line 605
- `esco_browser()` — line 789
- `lcb1_browser()` — line 912
- `shipmentlink_browser()` — line 1033

### Files to Modify
- `app/Services/BrowserAutomationService.php` — update `getNodePath()` with nvm scanning
- `app/Services/VesselTrackingService.php` — 6 methods, replace bare `node` with `getNodePath()`
- `.env` — optionally fix `NODE_BINARY_PATH` (not strictly needed if code fix is applied)
