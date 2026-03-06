# ETA Vessel Tracking - Findings & Problems Summary

Date: 2026-03-02
Investigation period: 2026-02-27 ~ 2026-03-02

---

## System Architecture Overview

```
User clicks "Check ETA"
    |
    v
VesselTrackingService::checkVesselETAWithParsedName()
    |
    +--> Layer 1: DB Cache (vessel_schedules table, 48h expiry)
    |       populated by daily cron: php artisan vessel:scrape-schedules
    |       |
    |       +--> HIT  → return cached result (instant, no scraping needed)
    |       +--> MISS → fall through to Layer 2
    |
    +--> Layer 2: Live Scraper
            PHP calls Node.js via proc_open()
            stdout = JSON result, stderr = logs
            |
            +--> node {terminal}-wrapper.js {vessel} {voyage}
                    |
                    +--> {terminal}-scraper.js (HTTP API or Puppeteer)
```

---

## 9 Terminal Scrapers - Technology Matrix

| Terminal | Ports | Single Scraper | Daily Cron Scraper | Technology |
|----------|-------|---------------|-------------------|------------|
| **LCIT** | B5, C3 | `lcit-scraper.js` | `lcit-full-schedule-scraper.js` | **HTTP API** (`https` + `xmldom`) |
| **Hutchison** | C1, C2 | `hutchison-scraper.js` | `hutchison-full-schedule-scraper.js` | **Puppeteer** + `axios` |
| **TIPS** | B4 | `tips-scraper.js` | `tips-full-schedule-scraper.js` | **Puppeteer** + `axios` |
| **ESCO** | B3 | (uses cron scraper) | `esco-full-schedule-scraper.js` | **Puppeteer** |
| **LCB1** | A0, B1, A3, D1 | `lcb1-scraper.js` | `lcb1-full-schedule-scraper.js` | **Puppeteer** + `axios` / `https` (cron) |
| **ShipmentLink** | B2 | `shipmentlink-scraper.js` | `shipmentlink-full-schedule-scraper.js` | **Puppeteer** + `axios` / `https` (cron) |
| **Kerry** | KERRY | (PHP HTTP direct) | - | **PHP HTTP** (no Node.js) |
| **Siam** | SIAM | (n8n integration) | - | **n8n webhook** (placeholder) |
| **JWD** | JWD | `jwd-scraper.js` | - | **Puppeteer** |

### Dependencies in package.json

```json
{
  "puppeteer": "13.7.0",    // <-- ต้องใช้ Chromium (6 terminals ต้องการ)
  "axios": "^1.6.0",
  "xmldom": "^0.6.0",       // <-- LCIT only
  "node-html-parser": "^7.0.1",
  "winston": "^3.11.0",
  "cron": "^3.1.0"
}
```

**Note:** `playwright` ไม่มีใน package.json แต่ `lcit-scraper-old.js` ใช้ playwright → ไม่เคยทำงานได้จริง

---

## Problems Found

---

### Problem 1: Voyage Format ไม่ถูก normalize

**อาการ:** User กรอก voyage เป็น `V.1060S` แต่ terminal API เก็บเป็น `1060S` → หาไม่เจอ

**ทดสอบจริงกับ LCIT API:**
- `?voy=V.1060S` → **Not Found** (API ทำ exact match)
- `?voy=1060S` → **Found** (POS HOCHIMINH at C3)

**สาเหตุ:** ไม่มีจุดไหนใน code flow ที่ strip prefix "V.", "V. ", "V" ก่อนส่งไป scraper

```
User enters "V.1060S"
    → VesselNameParser: เก็บเป็น "V.1060S" (ไม่ strip)
    → VesselTrackingService::lcit(): ส่ง "V.1060S" ตรงๆ
    → lcit-wrapper.js: ส่งต่อ "V.1060S"
    → lcit-scraper.js: API ?voy=V.1060S → NOT FOUND
```

**Normalization status ของแต่ละ scraper:**

| Terminal | Voyage Normalization | รายละเอียด |
|----------|---------------------|-----------|
| **TIPS** | มี (ครบ) | `generateVoyageVariations()` — strip V., สร้าง variations 4-5 แบบ |
| **ESCO** | มีบางส่วน | strip `M.V.` prefix จากชื่อเรือ (ไม่ใช่ voyage) |
| **Hutchison** | ไม่มี | case-insensitive เท่านั้น |
| **LCIT** | ไม่มี | `.toUpperCase()` + `includes()` เท่านั้น |
| **LCB1** | ไม่มี | pattern match `\d{3}S/N` |
| **ShipmentLink** | ไม่มี | expects clean format `0815-079S` |
| **JWD** | ไม่มี | **exact string match, case-sensitive!** |

**PHP side:** `VesselTrackingService` มี voyage variation logic (strip "V." prefix) แต่ใช้แค่ตอน search **DB cache** — ไม่ได้ใช้ก่อนเรียก live scraper

**แนวทางแก้:** Normalize voyage ที่จุดกลาง (PHP) ก่อนส่งให้ทุก scraper หรือเพิ่ม normalization ในแต่ละ scraper

---

### Problem 2: User กรอกท่าเรือผิด / Berth เปลี่ยน

**อาการ:** User กรอก port = `B5` (ซึ่งเป็น LCIT) แต่เรือจอดจริงที่ berth `C3` (ก็เป็น LCIT เหมือนกัน)

**ตัวอย่าง:**
```
User กรอก:   POS HOCHIMINH / 1060S / ท่าเรือ B5
ความจริง:    POS HOCHIMINH / 1060S / berth C3
```

ทั้ง B5 และ C3 เป็น port ของ LCIT — ระบบจะ route ไป LCIT scraper เหมือนกัน ดังนั้น **กรณีนี้ยังหาเจอ** เพราะ LCIT API คืนผลทั้ง B5 และ C3

**แต่ปัญหาจะเกิดถ้า:**
- User กรอก port ผิด terminal เลย (เช่น กรอก B4 ซึ่งเป็น TIPS แต่เรือจอดที่ B5 ซึ่งเป็น LCIT)
- ระบบจะไป search ผิด terminal → ไม่เจอ

**Port → Terminal mapping:**
```
B5, C3       → LCIT
C1, C2       → Hutchison
B4           → TIPS
B3           → ESCO
A0, B1, A3   → LCB1
B2           → ShipmentLink
D1           → LCB1 (shared)
KERRY        → Kerry
SIAM         → Siam
JWD          → JWD
```

**แนวทางแก้:** ถ้าหาไม่เจอใน terminal ที่ระบุ อาจลอง search terminal อื่นที่ใกล้เคียง หรือ search ทุก terminal (แต่จะช้า)

---

### Problem 3: Puppeteer/Chromium ติดตั้งไม่ได้บน Production

**อาการ:** `npm install` ใน production ต้องการ `sudo` สำหรับ download Chromium binary ที่ puppeteer ต้องใช้

**ผลกระทบ:** 6 จาก 9 terminals ใช้ Puppeteer → ถ้า Chromium ไม่ได้ติดตั้ง scraper เหล่านี้ทำงานไม่ได้:
- Hutchison (C1/C2)
- TIPS (B4)
- ESCO (B3)
- LCB1 (A0/B1)
- ShipmentLink (B2)
- JWD (D1)

**Terminals ที่ไม่ต้องการ Puppeteer:** (ทำงานได้แม้ไม่มี Chromium)
- LCIT → HTTP API (rewritten แล้ว)
- Kerry → PHP HTTP direct (ไม่ใช้ Node.js)
- Siam → n8n webhook

**แนวทางแก้:**
1. ขอ sudo จากคุณเรย์เพื่อ `npm install`
2. หรือ rewrite scraper จาก Puppeteer เป็น HTTP API (แบบที่ทำกับ LCIT) สำหรับ terminal ที่มี API
3. หรือ set `PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true` แล้วใช้ system Chromium

---

### Problem 4: node_modules ถูกลบโดยไม่มีสาเหตุ

**อาการ:** folder `browser-automation/node_modules/` หายไปจาก production ทำให้ scraper ทุกตัวที่ต้องการ dependency (puppeteer, xmldom, axios) ทำงานไม่ได้

**แก้ไข:** `cd browser-automation && npm install` (แก้แล้ว)

**สาเหตุที่เป็นไปได้:**
- deploy script ทำ clean install แล้วลบ folder
- git operation (reset/clean) ลบ untracked files
- server maintenance script

**ป้องกัน:** ตรวจสอบ deploy script ว่ามี `npm install` ใน browser-automation/ หรือไม่

---

### Problem 5: บาง Scraper ทำงานได้โดยไม่มี dependency (ยังหาสาเหตุไม่เจอ)

**อาการ:** แม้ `node_modules` จะถูกลบ บาง scraper ดูเหมือนจะยังทำงานได้

**ข้อสังเกตที่สำคัญ:**
- **LCIT "ทำงานได้" จนถึง 8 ม.ค. 2026** — แต่จริงๆ แล้ว lcit-scraper.js (playwright) ไม่เคยทำงานได้เลย สิ่งที่ทำงานคือ **DB cache** จาก daily cron ไม่ใช่ live scraper
- **Daily cron scraper** (`lcit-full-schedule-scraper.js`) ใช้แค่ `https` (built-in) + `xmldom` → ต้องการ node_modules สำหรับ xmldom
- ถ้า node_modules ถูกลบจริง daily cron ก็ไม่ควรทำงานได้ (xmldom จะ fail)

**ข้อสันนิษฐาน:**
1. node_modules อาจถูกลบหลังจากที่ cron หยุดทำงานแล้ว (ไม่ใช่สาเหตุที่ทำให้ cron หยุด)
2. หรือ cron หยุดด้วยสาเหตุอื่น (PHP scheduler issue) ไม่เกี่ยวกับ node_modules
3. ต้องตรวจสอบ `daily_scrape_logs` table บน production เพื่อดู error message ของ cron ก่อนหยุด

---

### Problem 6: Single Scraper กับ Daily Cron ใช้คนละ Code และ Library

**อาการ:** terminal เดียวกันมี scraper 2 ตัวที่ใช้ technology ต่างกัน

| Terminal | Single Scraper (live) | Daily Cron Scraper | ต่างกันอย่างไร |
|----------|----------------------|-------------------|----------------|
| **LCIT** | `https` + `xmldom` (fixed) | `https` + `xmldom` | **เหมือนกันแล้ว** (หลัง rewrite) |
| **LCB1** | `puppeteer` + `axios` | `https` (HTTP only) | **ต่าง** — single ใช้ browser, cron ใช้ HTTP |
| **ShipmentLink** | `puppeteer` + `axios` | `https` (HTTP only) | **ต่าง** — single ใช้ browser, cron ใช้ HTTP |
| **Hutchison** | `puppeteer` + `axios` | `puppeteer` | เหมือนกัน (ทั้งคู่ใช้ browser) |
| **TIPS** | `puppeteer` + `axios` | `puppeteer` | เหมือนกัน (ทั้งคู่ใช้ browser) |
| **ESCO** | (ใช้ cron scraper) | `puppeteer` | มีแค่ตัวเดียว |

**ปัญหา:**
- maintain ยาก — แก้ bug ที่เดียว อีกตัวอาจยังมี bug
- ถ้า terminal เปลี่ยน API/HTML structure ต้องแก้ 2 ที่
- behavior ไม่ consistent ระหว่าง cached result กับ live result

**ข้อสังเกตสำคัญ:** LCB1 และ ShipmentLink มี **cron scraper ที่ใช้ HTTP** (ไม่ต้องการ Puppeteer) แต่ **single scraper ยังใช้ Puppeteer** → เป็นไปได้ที่จะ rewrite single scraper เป็น HTTP เหมือน LCIT

---

## LCIT Investigation Summary (completed)

### Root Cause: ทำไม LCIT ETA หยุดทำงานหลัง 8 ม.ค. 2026

```
ก่อน 8 ม.ค. 2026:
  Daily cron ทำงาน → เติม DB cache ทุกวัน
  User กด Check ETA → hit DB cache → เจอ (instant)
  lcit-scraper.js (playwright) ไม่เคยถูกเรียกใช้

หลัง 8 ม.ค. 2026:
  Daily cron หยุดทำงาน → DB cache หมดอายุ (48h)
  User กด Check ETA → DB cache MISS → เรียก lcit-scraper.js
  lcit-scraper.js ใช้ playwright ซึ่งไม่เคย install → FAIL ทุกครั้ง
```

### Fix Applied (2026-02-27, merged as PR #1, commit 1302a88)
- Rewrite `lcit-scraper.js` จาก Playwright → HTTP API (เดียวกับ cron scraper)
- ตอนนี้ทั้ง single scraper และ cron scraper ใช้ LCIT ASMX API เหมือนกัน
- แม้ daily cron ยังไม่ทำงาน live scraper ก็ทำงานได้แล้ว

---

## Production Investigation Checklist (ยังไม่ได้ทำ)

- [ ] ทำไม `php artisan schedule:run` หยุดทำงาน?
  - ตรวจ `crontab -l` บน server
  - ตรวจ `eta_check_schedules` table — `is_active`, `last_run_at`
  - ตรวจ `daily_scrape_logs` table — last successful scrape date + error messages
- [ ] `npm install` ใน `browser-automation/` บน production — ต้องการ sudo?
- [ ] Pull latest code (`git pull`) บน production — LCIT fix ยังไม่ได้ deploy
- [ ] Test LCIT scraper บน production หลัง deploy

---

## WSL Local Dev Environment (setup completed 2026-03-02)

### Location & Stack
- **WSL Path:** `/home/dragonnon2/projects/logistic_auto/`
- **Cloned from:** `https://github.com/champkris/logistic_auto`
- **OS:** Ubuntu 24.04 (WSL2)
- **Stack:** PHP 8.3, Composer 2.8, Node 18, npm 9.2, MySQL 8.0
- **DB:** `logistic_auto` (MySQL, user=root, no password, seeded with `migrate:fresh --seed`)
- **Puppeteer + Chromium:** installed and working in `browser-automation/node_modules/`

### Login Credentials (seeded)
| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@easternair.com` | `admin123456` |
| Manager | `manager@easternair.com` | `manager123456` |
| User | `user@easternair.com` | `user123456` |

### WSL sudo password
`040940non`

### Known WSL Issues & Fixes

**1. Windows npm leaks into WSL PATH**
Windows `C:/Program Files/nodejs/npm` overrides WSL npm. Always prefix commands:
```bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
```

**2. Vite CSS ไม่แสดงผลบน Windows browser**
- **ปัญหา:** Vite ใน WSL ใช้ `0.0.0.0:5173` เป็น origin → browser บน Windows เข้า `0.0.0.0` ไม่ได้ → CSS/JS ไม่โหลด
- **แก้:** เพิ่ม `server.hmr.host` ใน `vite.config.js` (เฉพาะ WSL copy — ไม่ต้อง commit):
```js
// vite.config.js — add server block
export default defineConfig({
    plugins: [ laravel({ input: [...], refresh: true }) ],
    server: {
        host: '0.0.0.0',
        hmr: { host: 'localhost' }
    }
});
```

**3. MySQL ต้อง start ทุกครั้งที่เปิด WSL**
MySQL ไม่ auto-start ใน WSL. ต้องรัน `sudo service mysql start` ก่อน.

### วิธี Start Dev Server (ทุกครั้งที่เปิด WSL ใหม่)
```bash
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
sudo service mysql start
cd /home/dragonnon2/projects/logistic_auto
php artisan serve --host=0.0.0.0 --port=8000 &
npx vite &
```
เปิด browser: **http://localhost:8000**

### เปิด VS Code ใน WSL
```
Ctrl+Shift+P → "WSL: Connect to WSL" → Ctrl+O → /home/dragonnon2/projects/logistic_auto/
```
(ต้องมี VS Code extension "WSL" ติดตั้ง)

---

## Next Steps

1. ~~**Setup WSL** for local testing~~ — DONE (2026-03-02)
2. **Fix voyage normalization** — strip "V." prefix centrally or per-scraper
3. **Test remaining 8 terminal scrapers on WSL** — Hutchison, TIPS, ESCO, LCB1, ShipmentLink, Kerry, Siam, JWD
4. **Consider rewriting Puppeteer scrapers to HTTP API** where possible (LCB1, ShipmentLink already have HTTP cron scrapers)
5. **Deploy LCIT fix to production** — pull commit `1302a88` to server
6. **Investigate production cron failure** — check server crontab and logs

---

## Files Reference

| File | Purpose |
|------|---------|
| `app/Services/VesselTrackingService.php` | Main PHP service — routes to correct terminal scraper |
| `app/Services/VesselNameParser.php` | Parses "VESSEL NAME VOYAGE" → separate fields |
| `app/Services/BrowserAutomationService.php` | PHP wrapper for calling Node.js scrapers |
| `app/Console/Commands/ScrapeVesselSchedules.php` | Daily cron command |
| `app/Models/EtaCheckSchedule.php` | Scheduler model |
| `bootstrap/app.php` | Laravel scheduler config |
| `browser-automation/package.json` | Node.js dependencies |
| `browser-automation/scrapers/` | All Node.js scraper scripts |
| `docs/Non_docs/LCIT-terminal-scraper.md` | Detailed LCIT investigation |
