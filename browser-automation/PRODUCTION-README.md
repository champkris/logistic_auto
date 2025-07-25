# Fixed LCB1 Browser Automation

## Problem Fixed
✅ **JSON Contamination Issue Resolved**
- Logs now go to `stderr` (not mixed with JSON)
- Clean JSON output goes to `stdout` only
- No more "Invalid JSON from browser automation" errors

## Usage

### 1. Direct LCB1 Scraper (Recommended for Laravel)
```bash
# Returns clean JSON only
node lcb1-wrapper.js "MARSA PRIDE"

# Or use the scraper directly 
node scrapers/lcb1-scraper.js
```

### 2. Full Automation Suite
```bash
# Test all scrapers
node vessel-scraper.js test

# Run LCB1 only  
node vessel-scraper.js lcb1

# Start scheduled automation
node vessel-scraper.js schedule
```

## Laravel Integration

### PHP Example
```php
$command = 'cd ' . base_path('browser-automation') . ' && node lcb1-wrapper.js "MARSA PRIDE"';
$output = shell_exec($command . ' 2>/dev/null'); // Suppress logs, get JSON only

$result = json_decode($output, true);

if ($result && $result['success']) {
    // Process successful result
    $eta = $result['eta'];
    $vesselName = $result['vessel_name'];
} else {
    // Handle error
    $error = $result['error'] ?? 'Unknown error';
}
```

### Or capture both output and logs separately:
```php
$command = 'cd ' . base_path('browser-automation') . ' && node lcb1-wrapper.js "MARSA PRIDE"';
$descriptors = [
    0 => ['pipe', 'r'],  // stdin
    1 => ['pipe', 'w'],  // stdout (JSON)
    2 => ['pipe', 'w']   // stderr (logs)
];

$process = proc_open($command, $descriptors, $pipes);
$jsonOutput = stream_get_contents($pipes[1]);
$logOutput = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);

$result = json_decode($jsonOutput, true);
```

## Digital Ocean Production Setup

### Install Dependencies
```bash
# Install Node.js and dependencies
sudo apt update
sudo apt install -y nodejs npm chromium-browser

# Install project dependencies
cd /path/to/your/project/browser-automation
npm install
```

### Environment Setup
```bash
# Set production environment
export NODE_ENV=production

# Ensure logs directory exists
mkdir -p logs

# Make scripts executable
chmod +x lcb1-wrapper.js
```

### Systemd Service (Optional)
```ini
# /etc/systemd/system/vessel-automation.service
[Unit]
Description=Vessel Automation Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/your/project/browser-automation
ExecStart=/usr/bin/node vessel-scraper.js schedule
Restart=always
RestartSec=10
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

## Output Format

### Success Response
```json
{
  "success": true,
  "terminal": "LCB1",
  "vessel_name": "MARSA PRIDE",
  "voyage_code": "528S",
  "voyage_out": "529N",
  "eta": "2025-07-25 04:00:00",
  "etd": "2025-07-26 18:00:00",
  "scraped_at": "2025-07-25T16:05:00.000Z",
  "source": "lcb1_browser_automation"
}
```

### Error Response
```json
{
  "success": false,
  "error": "Vessel MARSA PRIDE not found in current schedule",
  "terminal": "LCB1",
  "scraped_at": "2025-07-25T16:05:00.000Z"
}
```

## Logs
- **Application logs**: `vessel-automation.log`, `vessel-scraping.log`
- **Console logs**: Sent to `stderr` (won't contaminate JSON)
- **JSON output**: Sent to `stdout` (clean for parsing)

## Testing
```bash
# Test the fix
node lcb1-wrapper.js "MARSA PRIDE" > result.json 2> logs.txt

# Check clean JSON output
cat result.json

# Check logs separately  
cat logs.txt
```

## Key Changes Made
1. ✅ Winston logger now uses `stderrLevels` to send logs to stderr
2. ✅ Added clean JSON output functions to both scrapers
3. ✅ Created production-ready wrapper script
4. ✅ Separated concerns: logs vs data output
5. ✅ Added proper error handling with JSON responses
