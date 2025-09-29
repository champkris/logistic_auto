# Production Node.js Configuration Guide

## Problem
The browser automation fails on production with the error:
```
SyntaxError: Unexpected token '??='
```

This occurs when the PHP process uses a different/older Node.js version than what's installed on your system.

## Solution

### Step 1: Diagnose Node.js on Production

SSH into your production server and run:

```bash
# Check system Node.js version
node --version

# Find Node.js installation path
which node

# Check if PHP sees the same Node.js
php -r "echo shell_exec('node --version');"
php -r "echo shell_exec('which node');"
```

### Step 2: Configure Node.js Path

1. Copy the production environment example:
```bash
cp .env.production.example .env
```

2. Edit `.env` and set the Node.js path:
```env
# Set this to the path from "which node" command
NODE_BINARY_PATH=/usr/local/bin/node
```

Common paths on different systems:
- Ubuntu/Debian: `/usr/bin/node`
- CentOS/RHEL: `/usr/local/bin/node`
- Node Version Manager (nvm): `/home/user/.nvm/versions/node/v18.x.x/bin/node`
- Homebrew (macOS): `/opt/homebrew/bin/node`

### Step 3: Verify Node.js Version

The application requires Node.js 15+ for modern JavaScript features. If your production server has an older version:

#### Option A: Update Node.js (Recommended)

For Ubuntu/Debian:
```bash
# Add NodeSource repository for Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
```

For CentOS/RHEL:
```bash
# Add NodeSource repository for Node.js 18
curl -fsSL https://rpm.nodesource.com/setup_18.x | sudo bash -
sudo yum install nodejs
```

#### Option B: Install Node.js with nvm (User-specific)

```bash
# Install nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash

# Load nvm
source ~/.bashrc

# Install Node.js 18
nvm install 18
nvm use 18
nvm alias default 18

# Get the path to use in .env
which node  # This will show the nvm Node.js path
```

### Step 4: Test Browser Automation

Run the diagnostic script:

```bash
php test-node-version.php
```

This should show:
1. Node.js version 15+ or higher
2. Successful nullish coalescing operator test

### Step 5: Clear Laravel Cache

After configuring:

```bash
php artisan config:clear
php artisan cache:clear
```

### Step 6: Test Vessel Tracking

```bash
# Test the vessel tracking service
php artisan tinker
>>> $service = new \App\Services\VesselTrackingService();
>>> $service->checkVesselETA('C1C2', ['vessel_name' => 'WAN HAI 517', 'voyage_code' => 'S093']);
```

## Alternative: Puppeteer Downgrade

If you cannot update Node.js on production, downgrade Puppeteer in the browser-automation directory:

```bash
cd browser-automation
npm uninstall puppeteer
npm install puppeteer@13.7.0
```

However, this is not recommended as newer Puppeteer versions have better compatibility with modern websites.

## Troubleshooting

1. **Permission Issues**: Ensure the web server user can execute Node.js:
   ```bash
   sudo -u www-data node --version
   ```

2. **SELinux/AppArmor**: May block Node.js execution. Check logs:
   ```bash
   # For SELinux
   sudo ausearch -m avc -ts recent

   # For AppArmor
   sudo aa-status
   ```

3. **Firewall**: Browser automation needs outbound HTTPS (port 443) access to terminal websites.

4. **Memory**: Puppeteer requires at least 512MB of available RAM. Check with:
   ```bash
   free -m
   ```

## Contact

If issues persist, check the Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

The BrowserAutomationService will log which Node.js binary it's using and any errors encountered.