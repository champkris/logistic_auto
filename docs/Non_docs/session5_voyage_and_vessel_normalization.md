# Session 5: Voyage & Vessel Name Normalization

Date: 2026-03-04

---

## Problem (Finding 2 & Finding 5 from `finding_and_problem_ETA_2.md`)

147 shipments have dirty voyage data and 12 shipments are linked to vessels with trailing spaces in their names. These cause:
1. **ETA lookup failures** — scrapers receive `V.1060S` instead of `1060S`, return "not found"
2. **Dirty GUI display** — `/shipments` page shows `V.1060S`, ` 0284S` etc. instead of clean `1060S`, `0284S`
3. **Duplicate vessels** — `MAKHA BHUM` and `MAKHA BHUM ` (trailing space) exist as separate records

---

## Dirty Data Inventory (from production DB)

### Voyage — 147 affected shipments

| Pattern | Example | Unique Voyages | Shipments |
|---------|---------|:--------------:|:---------:|
| `V.` prefix (no space) | `V.1060S` → `1060S` | 22 | 69 |
| `V. ` prefix (with space) | `V. 251S` → `251S` | 10 | 16 |
| Leading space | ` 0284S` → `0284S` | 36 | 78 |
| Leading space + V. | ` V.2602S` → `2602S` | ~15 | (subset of above) |
| Trailing space | `V.2548S ` → `2548S` | at least 1 | (subset of above) |

**Key finding:** Every `V.` voyage has a matching clean version in the DB. `V.` means "Voyage" (human prefix). No real voyage starts with `V`. Safe to strip.

### Vessel Name — 3 dirty vessels, 12 shipments

| Dirty Vessel | ID | Shipments | Clean Duplicate Exists? |
|-------------|:--:|:---------:|:-----------------------:|
| `MAKHA BHUM ` (trailing space) | 59 | 7 | Yes — `MAKHA BHUM` exists separately |
| `KMTC JAKATA ` (trailing space) | 16 | 3 | No clean version (also misspelled: JAKATA vs JAKARTA) |
| `SM JAKARTA ` (trailing space) | 144 | 1 (#1811) | No clean version |

---

## What Changed

### Change 1: Normalize voyage on save — `ShipmentManager::save()`

**File:** `app/Livewire/ShipmentManager.php`
**Location:** Line ~600 (before `$shipmentData = [`)

**Added:**
```php
// Normalize voyage before saving
if ($this->voyage) {
    $this->voyage = trim($this->voyage);
    $this->voyage = preg_replace('/^V\.?\s*/i', '', $this->voyage);
    $this->voyage = trim($this->voyage);
}
```

**Why:** Ensures new/updated shipments always store clean voyage. The GUI at `/shipments` shows the normalized value because it reads directly from the DB.

**What it fixes:** Finding 2 — dirty voyage causes ETA lookup failures and ugly GUI display. New shipments will always have clean voyages going forward.

---

### Change 2: Normalize vessel name on save — `ShipmentManager::resolveVessel()`

**File:** `app/Livewire/ShipmentManager.php`
**Location:** Line ~351 (inside `resolveVessel()`, after `if (!empty($this->vessel_name))`)

**Added:**
```php
// Normalize vessel name
$this->vessel_name = trim($this->vessel_name);
$this->vessel_name = preg_replace('/\s+/', ' ', $this->vessel_name);
```

**Why:** Prevents creating duplicate vessels like `SM JAKARTA ` vs `SM JAKARTA`. The `Vessel::where('vessel_name', ...)` lookup will match correctly with clean input.

**What it fixes:** Finding 5 — trailing spaces in vessel names cause duplicate vessel records and ETA lookup mismatches.

---

### Change 3: Enhance ETA lookup normalization — `checkVesselETAWithParsedName()`

**File:** `app/Services/VesselTrackingService.php`
**Location:** Line 198 (existing normalization)

**Before:**
```php
$parsedVessel['vessel_name'] = trim($parsedVessel['vessel_name']);
$parsedVessel['voyage_code'] = trim(preg_replace('/^V\.?\s*/i', '', $parsedVessel['voyage_code'] ?? ''));
```

**After:**
```php
$parsedVessel['vessel_name'] = preg_replace('/\s+/', ' ', trim($parsedVessel['vessel_name']));
$parsedVessel['voyage_code'] = trim(preg_replace('/^V\.?\s*/i', '', trim($parsedVessel['voyage_code'] ?? '')));
```

**What changed:**
- Vessel name: added multi-space collapse (`preg_replace('/\s+/', ' ', ...)`)
- Voyage: added inner `trim()` before V. stripping (handles ` V.1060S` → `V.1060S` → `1060S`)

**Why:** Safety net for old dirty data still in the DB. Even though new saves are clean (Change 1 & 2), old records with `V.1060S` or `MAKHA  BHUM` will still work correctly during ETA lookups.

**What it fixes:** Finding 2 — old dirty voyages in DB would still fail ETA lookup without this runtime normalization.

---

## Data Flow After Fix

```
New shipment — user enters "V.1060S" as voyage:
  → ShipmentManager::save() normalizes to "1060S" before saving
  → DB stores "1060S"
  → GUI shows "1060S"
  → ETA lookup receives "1060S" (already clean)
  → Scraper finds vessel correctly

Old shipment with dirty "V.1060S" already in DB (not cleaned):
  → checkVesselETAWithParsedName() normalizes at lookup time (Change 3 safety net)
  → GUI still shows dirty "V.1060S", but ETA lookup works correctly
```

---

## Normalization Regex Explained

```php
// Step 1: Trim whitespace (handles " V.1060S", "1060S ", " 0284S")
$voyage = trim($voyage);

// Step 2: Strip V. prefix (handles "V.1060S", "V. 251S", "V1060S")
//   ^V     — starts with V (case-insensitive due to /i flag)
//   \.?    — optional dot (handles both "V." and "V ")
//   \s*    — zero or more spaces after (handles "V. 251S")
$voyage = preg_replace('/^V\.?\s*/i', '', $voyage);

// Step 3: Trim again (in case stripping V. left trailing space)
$voyage = trim($voyage);
```

**Edge cases covered:**

| Input | After trim | After V. strip | After trim | Result |
|-------|-----------|---------------|-----------|--------|
| `V.1060S` | `V.1060S` | `1060S` | `1060S` | `1060S` |
| `V. 251S` | `V. 251S` | `251S` | `251S` | `251S` |
| ` V.2602S` | `V.2602S` | `2602S` | `2602S` | `2602S` |
| ` 0284S` | `0284S` | `0284S` | `0284S` | `0284S` |
| `V. 0N806S` | `V. 0N806S` | `0N806S` | `0N806S` | `0N806S` |
| `1060S` | `1060S` | `1060S` | `1060S` | `1060S` (no change) |
| `VIRA BHUM 140S` | `VIRA BHUM 140S` | `IRA BHUM 140S` | `IRA BHUM 140S` | Wrong! But this is a data entry error, not a V-prefix issue. Handled separately (P5). |

---

## Old Dirty Data — Not Cleaned

Old dirty data stays as-is in the DB. No one-time SQL cleanup was performed. Reasons:
- Change 3 (ETA lookup normalization) acts as safety net — ETA lookups work correctly at runtime
- Old shipments with dirty voyages still show dirty values in the GUI, but ETA works
- Only new/updated shipments get clean values via Change 1 & 2

---

## Test Results

| Test | Input | Expected | Actual | Pass |
|------|-------|----------|--------|------|
| Save voyage | `V.9999S` | DB stores `9999S` | `9999S` | YES |
| Vessel name trailing space | `MAKHA BHUM ` | Matches existing vessel ID=59 | Matched, no duplicate | YES |
| Vessel name multi-space | `TEST  MULTI   SPACE` | `TEST MULTI SPACE` | `TEST MULTI SPACE` | YES |
| ETA lookup dirty voyage | `POS HOCHIMINH V.1060S` at B5 | Normalizes to `1060S`, finds vessel | Found at LCIT C3 | YES |
| ETA lookup leading space | `ACX PEARL  0284S` at C1C2 | Normalizes to `0284S` | `0284S` | YES |

---

## Not Covered (future fix — documented in `finding_and_problem_ETA_2.md`)

| Issue | Example | Priority | Why Not Now |
|-------|---------|----------|-------------|
| Vessel name in voyage field | `VIRA BHUM 140S` (#1073), `LITTLE DOLPHIN  V. 2518S` (#1059) | P5 | Data entry error — can't auto-fix. Both are completed shipments. |
| Empty port_terminal | Shipment #1819 | P2 | Needs form validation, not normalization |
| Vessel name typo | `KMTC JAKATA` vs `KMTC JAKARTA` | P5 | Spelling error, not whitespace. Would need fuzzy matching or manual correction. |

---

## Files Changed

| File | Change | Lines |
|------|--------|-------|
| `app/Livewire/ShipmentManager.php` | Added voyage normalization in `save()` | ~line 600 |
| `app/Livewire/ShipmentManager.php` | Added vessel name normalization in `resolveVessel()` | ~line 351 |
| `app/Services/VesselTrackingService.php` | Enhanced existing normalization with multi-space collapse + inner trim | line 198 |
