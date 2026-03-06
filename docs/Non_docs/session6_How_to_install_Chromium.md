# How to Install Chromium Dependencies on Production Server

## Why This Is Needed

The vessel tracking system uses **Puppeteer** (a Node.js library) to scrape terminal websites. Puppeteer downloads its own Chromium binary via `npm install`, but that binary depends on **system-level shared libraries** (.so files) to actually launch and run.

Without these libraries, Puppeteer fails with errors like:
```
error while loading shared libraries: libnss3.so: cannot open shared object file
```

This caused all scrapers on production to silently fail (reporting `vessels_found=0`).

---

## Prerequisites

- SSH access to production server
- `sudo` privileges
- Ubuntu/Debian-based OS

---

## Step-by-Step Installation

### Step 1: SSH into production server

```bash
ssh -p 4889 vessel-ssh@103.125.93.219
```

### Step 2: Install system libraries

Run this single command to install all required Chromium dependencies:

```bash
sudo apt-get update && sudo apt-get install -y \
  libnss3 \
  libatk1.0-0 \
  libatk-bridge2.0-0 \
  libcups2 \
  libdrm2 \
  libxkbcommon0 \
  libxcomposite1 \
  libxdamage1 \
  libxrandr2 \
  libgbm1 \
  libpango-1.0-0 \
  libcairo2 \
  libasound2
```

### Step 3: Verify Puppeteer can launch Chromium

```bash
cd /path/to/logistic_auto/browser-automation
node -e "const puppeteer = require('puppeteer'); puppeteer.launch({headless: true}).then(b => { console.log('Chromium launched successfully'); b.close(); }).catch(e => console.error('Failed:', e.message));"
```

If it prints `Chromium launched successfully`, you're done. If it fails, the error message will tell you which library is still missing.

---

## What Each Package Does

| Package | Purpose |
|---------|---------|
| `libnss3` | Network Security Services — SSL/TLS certificate handling |
| `libatk1.0-0` | Accessibility Toolkit — UI accessibility support |
| `libatk-bridge2.0-0` | AT-SPI bridge for accessibility |
| `libcups2` | Printing system support (required by Chromium even if not printing) |
| `libdrm2` | Direct Rendering Manager — GPU/display rendering |
| `libxkbcommon0` | Keyboard input handling |
| `libxcomposite1` | X11 compositing extension — window layering |
| `libxdamage1` | X11 damage extension — screen update tracking |
| `libxrandr2` | X11 display resize/rotation |
| `libgbm1` | Generic Buffer Management — GPU memory allocation |
| `libpango-1.0-0` | Text rendering and layout engine |
| `libcairo2` | 2D graphics library — drawing/rendering |
| `libasound2` | ALSA sound library (required by Chromium even in headless mode) |

---

## Relationship Between npm install and apt-get install

```
npm install (in browser-automation/)
  └── Downloads Puppeteer package
       └── Downloads Chromium binary (~170MB)
            └── Needs system libs (libnss3, libcairo2, etc.) to run
                 └── Installed via apt-get install
```

- `npm install` = gives you the Chromium **binary**
- `apt-get install` = gives that binary the **OS libraries** it needs to launch

Both are required. One without the other won't work.

---

## Troubleshooting

### Find which libraries are missing

If Puppeteer still fails after installation, check which shared libraries the Chromium binary can't find:

```bash
# Find the Chromium binary path
CHROME_PATH=$(node -e "console.log(require('puppeteer').executablePath())" 2>/dev/null || find node_modules -name chrome -type f | head -1)

# Check for missing libraries
ldd "$CHROME_PATH" | grep "not found"
```

Install any missing libraries shown in the output.

### Common error messages

| Error | Missing Package |
|-------|----------------|
| `libnss3.so: cannot open` | `libnss3` |
| `libatk-1.0.so.0: cannot open` | `libatk1.0-0` |
| `libgbm.so.1: cannot open` | `libgbm1` |
| `libXcomposite.so.1: cannot open` | `libxcomposite1` |
