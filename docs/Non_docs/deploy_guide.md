# Production Deployment Guide

Date: 2026-03-07
Branch: `master` (merged from PR #2 `Fix_ETA_vessel_not_work`)
Commit: `8956945`

---

## What This Deploy Includes

18 commits merged via PR #2. Key changes:

1. **Fix scraper stderr capture** — removed `2>/dev/null` from all scraper commands so errors are logged
2. **Fix TIPS column mapping** — ETA was reading service code instead of date
3. **Voyage & vessel name normalization** — strips `V.` prefix, trims whitespace on save and ETA lookup
4. **KLN port mapping + Kerry queue scraper** — new queue-based cron for Kerry terminal
5. **JWD cron scraper** — PHP HTTP scraper, no queue needed
6. **Kerry date filter** — only scrapes shipments within +/- 1 month of delivery date
7. **P4 scraper merge (8 phases)** — merged single/cron scrapers into one unified scraper per terminal:
   - LCIT, ESCO, TIPS, Hutchison — replaced Puppeteer with HTTP
   - LCB1 — HTTP + queue-based cron
   - ShipmentLink — HTTP + queue-based cron (legacy 813-vessel cron mode removed)
   - JWD — PHP HTTP cron
   - Everbuild — dead code removed (uses same endpoint as ShipmentLink)
8. **Cleanup** — deleted ~20 unused test/debug/wrapper scripts

---

## Step-by-Step Deployment

### Step 1: Pull the latest code

```bash
cd /path/to/logistic_auto
git checkout master
git pull origin master
```

Verify you see commit `8956945` (Merge pull request #2):
```bash
git log --oneline -1
```

### Step 2: Run migrations

The `jobs`, `job_batches`, and `failed_jobs` tables are required for the queue-based scrapers (Kerry, LCB1, ShipmentLink). The migration file already exists — just run it if the tables don't exist yet:

```bash
php artisan migrate
```

If the tables already exist, this will safely skip them.

### Step 3: Clear Laravel caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Step 4: Start queue worker

The 3 queue-based scrapers need a running queue worker:
- `kerry-scraper` queue (Kerry/KLN terminal)
- `lcb1-scraper` queue (LCB1/A0/B1 terminal)
- `shipmentlink-scraper` queue (ShipmentLink/B2 terminal)

**Check if a queue worker is already running:**
```bash
ps aux | grep "queue:work"
```

**If no worker is running, start one:**

Option A — Simple (need to restart manually if server reboots):
```bash
nohup php artisan queue:work --queue=kerry-scraper,lcb1-scraper,shipmentlink-scraper --tries=2 --backoff=30 --sleep=3 --timeout=60 > storage/logs/queue-worker.log 2>&1 &
```

Option B — Supervisor (auto-restarts on crash/reboot, recommended):
```bash
sudo nano /etc/supervisor/conf.d/vessel-queue.conf
```

Paste this (replace `/path/to/logistic_auto` with actual project path):
```ini
[program:vessel-queue]
process_name=%(program_name)s
command=php /path/to/logistic_auto/artisan queue:work --queue=kerry-scraper,lcb1-scraper,shipmentlink-scraper --tries=2 --backoff=30 --sleep=3 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/logistic_auto/storage/logs/queue-worker.log
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start vessel-queue
```

### Step 5: Verify deployment

**Test cron scraper commands (dry run first):**
```bash
php artisan vessel:scrape-kerry --dry-run
php artisan vessel:scrape-lcb1 --dry-run
php artisan vessel:scrape-shipmentlink --dry-run
```

**Run actual scrape cycle:**
```bash
php artisan vessel:scrape-schedules
php artisan vessel:scrape-kerry
php artisan vessel:scrape-jwd
php artisan vessel:scrape-lcb1
php artisan vessel:scrape-shipmentlink
```

**Check queue worker is processing jobs:**
```bash
tail -f storage/logs/queue-worker.log
```

**Check pending/failed jobs:**
```bash
php artisan tinker --execute="echo 'Pending: ' . DB::table('jobs')->count() . ', Failed: ' . DB::table('failed_jobs')->count();"
```

**Check vessel_schedules has data:**
```bash
php artisan tinker --execute="echo DB::table('vessel_schedules')->count() . ' records, latest: ' . DB::table('vessel_schedules')->max('updated_at');"
```

**Check daily_scrape_logs for successful runs:**
```bash
php artisan tinker --execute="DB::table('daily_scrape_logs')->orderByDesc('id')->take(10)->get()->each(function(\$r){ echo \"\$r->terminal | \$r->status | found:\$r->vessels_found | \$r->created_at\n\"; });"
```

---

## No New Libraries Needed

- **No new composer packages**
- **No new npm packages** — `axios`, `cheerio`, `puppeteer` already in `browser-automation/package.json`
- **No new system packages** — Chromium libs already installed (see `session6_How_to_install_Chromium.md`)
- **No new crontab entries** — the existing `php artisan schedule:run` cron triggers all scrapers automatically

---

## Cron Schedule (no changes needed)

The existing `php artisan schedule:run` cron triggers all scrapers:

1. `vessel:scrape-schedules` — Hutchison, TIPS, ESCO, LCIT (direct HTTP, stores immediately)
2. `vessel:scrape-kerry` — dispatches Kerry jobs to queue
3. `vessel:scrape-jwd` — JWD HTTP scraper (stores immediately, no queue)
4. `vessel:scrape-lcb1` — dispatches LCB1 jobs to queue
5. `vessel:scrape-shipmentlink` — dispatches ShipmentLink jobs to queue

---

## Rollback

If something goes wrong:
```bash
git checkout 1302a88
php artisan config:clear
php artisan cache:clear
```

`1302a88` is the previous master commit (Merge pull request #1).

---

## Important Warnings

- **DO NOT run ShipmentLink scraper for all 813 vessels** — this will get the server IP permanently blocked by ShipmentLink. The legacy cron mode has been removed from code. Rate limit of 40 req/min is safe.
- **Queue worker must be running** — without it, Kerry/LCB1/ShipmentLink cron jobs will pile up in the `jobs` table and never execute.
- **Only 1 queue worker needed** — with ~20 active shipments across the 3 queue terminals, all jobs complete in under 30 seconds per cron cycle.
