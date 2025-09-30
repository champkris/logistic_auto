# 🚢 SIAM COMMERCIAL Terminal - Complete Implementation Guide

## 📋 Table of Contents
1. [Overview](#overview)
2. [What Was Done](#what-was-done)
3. [What You Need To Do](#what-you-need-to-do)
4. [Implementation Steps](#implementation-steps)
5. [Architecture](#architecture)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)
8. [API Reference](#api-reference)

---

## 📌 Overview

This guide documents the complete implementation of SIAM COMMERCIAL terminal integration with n8n chatbot for vessel tracking.

### Key Features
- ✅ **Real-time chatbot integration** via LINE messaging
- ✅ **Automatic polling** for vessel ETA updates
- ✅ **Smart caching** (3-hour rate limiting)
- ✅ **Multi-attempt handling** (up to 5 attempts)
- ✅ **Clean MVC architecture** with dedicated controller

### Technology Stack
- **Backend**: Laravel 12.x + SiamTerminalController
- **Frontend**: JavaScript polling + Livewire
- **Integration**: n8n workflow + LINE Bot
- **Database**: SQLite (siam_com_chatbot_eta_requests table)

---

## ✅ What Was Done

### 1. Backend Implementation ✅ COMPLETE

#### Created `SiamTerminalController.php`
**Location**: `app/Http/Controllers/SiamTerminalController.php`

**Methods**:
- `startChatbotRequest()` - Triggers n8n workflow
- `pollChatbotStatus()` - Polls database for results
- `getConfig()` - Returns terminal configuration

#### Updated Routes
**Location**: `routes/web.php`

```php
use App\Http\Controllers\SiamTerminalController;

Route::post('/vessel-test-public/siam/start', [SiamTerminalController::class, 'startChatbotRequest'])->name('vessel-test.siam.start');
Route::get('/vessel-test-public/siam/poll', [SiamTerminalController::class, 'pollChatbotStatus'])->name('vessel-test.siam.poll');
Route::get('/vessel-test-public/siam/config', [SiamTerminalController::class, 'getConfig'])->name('vessel-test.siam.config');
```

#### Added Validation
Updated validation rules to include 'SIAM' terminal in dropdown validation.

### 2. Frontend Visual Updates ✅ COMPLETE

#### Updated `vessel-test.blade.php`

**Changes**:
- Added "SIAM - Siam Commercial" to terminal dropdown
- Added SIAM terminal information card
- Updated text from "6 terminals" to "8 terminals"

**Terminal Card**:
```html
<div class="border rounded-lg p-4 hover:bg-blue-50 transition">
    <h3 class="font-semibold text-blue-600">Terminal SIAM</h3>
    <p class="text-sm text-gray-600">Siam Commercial</p>
    <p class="text-xs text-gray-500 mt-1">🚢 Default: MAKHA BHUM</p>
    <p class="text-xs text-gray-400">🧭 Voyage: 119S</p>
</div>
```

### 3. Database Integration ✅ READY

Uses existing `SiamComChatbotEtaRequest` model:
- Table: `siam_com_chatbot_eta_requests`
- Fields: vessel_name, voyage_code, status, last_known_eta, attempts
- Rate limiting: 3 hours cache duration

### 4. n8n Integration ✅ READY

Connects to your existing n8n workflow:
- Webhook: `N8N_SIAM_WEBHOOK_URL`
- LINE Group: `SIAM_COM_LINE_GROUP_ID`
- API endpoints already exist in `routes/api.php`

---

## ⏳ What You Need To Do

### Step 1: Environment Variables (30 seconds)

Open `.env` and add:

```env
# n8n SIAM Commercial Webhook URL
N8N_SIAM_WEBHOOK_URL=http://localhost:5678/webhook/siam-com-eta

# SIAM Commercial LINE Group ID
SIAM_COM_LINE_GROUP_ID=siam_com_line_group_C123456789
```

**Replace `siam_com_line_group_C123456789` with your actual LINE group ID!**

### Step 2: Update JavaScript (5 minutes)

Open `resources/views/vessel-test.blade.php`

#### Part A: Add SIAM Detection (line ~220)

**Find this code**:
```javascript
const vesselName = document.getElementById('vesselName').value.trim();
const voyageCode = document.getElementById('voyageCode').value.trim();
const terminal = document.getElementById('terminal').value;

if (!vesselName || !terminal) {
    alert('Please fill in vessel name and select a terminal');
    return;
}

// Show loading state
button.innerHTML = '<span class="loading"></span> Testing...';
```

**Add AFTER the validation, BEFORE "Show loading state"**:
```javascript
// Check if SIAM terminal - handle with chatbot
if (terminal === 'SIAM') {
    await handleSiamTerminalTest(vesselName, voyageCode, button, results, resultContainer);
    return;
}
```

#### Part B: Add Helper Functions (before `</script>`)

**Find the LAST `</script>` tag** in the file (around line 510+)

**Paste THIS before `</script>`**:

```javascript
// ========================================
// SIAM Terminal Helper Functions
// ========================================

async function handleSiamTerminalTest(vesselName, voyageCode, button, results, resultContainer) {
    if (!voyageCode) {
        alert('Voyage code is required for SIAM terminal');
        return;
    }

    button.innerHTML = '<span class="loading"></span> Starting Chatbot...';
    button.disabled = true;
    results.classList.remove('hidden');
    
    resultContainer.innerHTML = `
        <div class="border rounded-lg p-4 border-blue-200 bg-blue-50">
            <div class="flex items-center mb-3">
                <span class="loading mr-3"></span>
                <h3 class="font-semibold text-blue-800 text-lg">🤖 Chatbot Initialization</h3>
            </div>
            <p class="text-blue-700">Triggering n8n workflow for SIAM Commercial...</p>
        </div>
    `;

    try {
        const startResponse = await fetch('/vessel-test-public/siam/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ vessel_name: vesselName, voyage_code: voyageCode })
        });

        const startData = await startResponse.json();
        if (!startData.success) throw new Error(startData.error || 'Failed to start chatbot');

        if (startData.status === 'cached') {
            resultContainer.innerHTML = `
                <div class="border rounded-lg p-4 border-green-200 bg-green-50">
                    <h3 class="font-semibold text-green-800 text-lg mb-3">✅ SIAM Commercial (Cached)</h3>
                    <div class="bg-white p-4 rounded border-l-4 border-green-500">
                        <p class="font-medium text-green-800">📦 Using Cached Data (${startData.data.hours_ago}h ago)</p>
                        <p class="text-green-700 font-medium text-lg mt-2">🕒 ETA: ${startData.data.eta || 'N/A'}</p>
                    </div>
                </div>
            `;
            button.innerHTML = '<span>🔍</span><span>Test This Vessel</span>';
            button.disabled = false;
            return;
        }

        resultContainer.innerHTML = `
            <div class="border rounded-lg p-4 border-blue-200 bg-blue-50">
                <div class="flex items-center mb-3">
                    <span class="loading mr-3"></span>
                    <h3 class="font-semibold text-blue-800 text-lg">💬 Chatbot Active</h3>
                </div>
                <p class="text-blue-700 mb-2" id="chatbot-status">Contacting Siam Commercial admin...</p>
                <div class="mt-3 bg-white p-3 rounded">
                    <p class="text-sm">🚢 ${vesselName} | 🧭 ${voyageCode}</p>
                    <p class="text-sm text-gray-500 mt-2">⏱️ Wait time: 1-5 minutes</p>
                    <p class="text-sm text-blue-600 mt-1" id="elapsed-time">Elapsed: 0s</p>
                </div>
            </div>
        `;

        pollSiamChatbotStatus(vesselName, voyageCode, resultContainer, button);

    } catch (error) {
        resultContainer.innerHTML = `
            <div class="bg-red-50 p-4 rounded-lg border-l-4 border-red-500">
                <p class="text-red-800 font-medium">❌ Error: ${error.message}</p>
            </div>
        `;
        button.innerHTML = '<span>🔍</span><span>Test This Vessel</span>';
        button.disabled = false;
    }
}

let pollInterval = null;
let elapsedSeconds = 0;

function pollSiamChatbotStatus(vesselName, voyageCode, resultContainer, button) {
    if (pollInterval) clearInterval(pollInterval);
    
    elapsedSeconds = 0;
    const elapsedTimer = setInterval(() => {
        elapsedSeconds++;
        const el = document.getElementById('elapsed-time');
        if (el) el.textContent = `Elapsed: ${Math.floor(elapsedSeconds/60)}m ${elapsedSeconds%60}s`;
    }, 1000);

    pollInterval = setInterval(async () => {
        try {
            const res = await fetch(`/vessel-test-public/siam/poll?vessel_name=${encodeURIComponent(vesselName)}&voyage_code=${encodeURIComponent(voyageCode)}`);
            const data = await res.json();

            const statusEl = document.getElementById('chatbot-status');
            if (statusEl && data.message) statusEl.textContent = data.message;

            if (data.status === 'complete') {
                clearInterval(pollInterval);
                clearInterval(elapsedTimer);
                resultContainer.innerHTML = `
                    <div class="border rounded-lg p-4 border-green-200 bg-green-50">
                        <h3 class="font-semibold text-green-800 text-lg mb-3">✅ SIAM Commercial</h3>
                        <div class="bg-white p-4 rounded border-l-4 border-green-500">
                            <p class="font-medium text-green-800 mb-2">🎉 ETA Received!</p>
                            <p class="text-green-700 font-medium text-lg">🕒 ETA: ${data.data.eta || 'N/A'}</p>
                            <p class="text-xs text-gray-500 mt-2">✅ Vessel Found | ✅ Voyage Matched</p>
                        </div>
                    </div>
                `;
                button.innerHTML = '<span>🔍</span><span>Test This Vessel</span>';
                button.disabled = false;
            } else if (data.status === 'failed') {
                clearInterval(pollInterval);
                clearInterval(elapsedTimer);
                resultContainer.innerHTML = `
                    <div class="border rounded-lg p-4 border-yellow-200 bg-yellow-50">
                        <h3 class="font-semibold text-yellow-800 text-lg mb-3">⚠️ SIAM Commercial</h3>
                        <div class="bg-white p-4 rounded border-l-4 border-yellow-500">
                            <p class="font-medium text-yellow-800">❌ No Response from Admin</p>
                            <p class="text-sm text-yellow-700 mt-2">Admin did not respond after multiple attempts.</p>
                        </div>
                    </div>
                `;
                button.innerHTML = '<span>🔍</span><span>Test This Vessel</span>';
                button.disabled = false;
            }

            if (elapsedSeconds > 300) {
                clearInterval(pollInterval);
                clearInterval(elapsedTimer);
                resultContainer.innerHTML = `<div class="border rounded-lg p-4 border-gray-200"><p class="text-gray-800">⏱️ Request timeout after 5 minutes</p></div>`;
                button.innerHTML = '<span>🔍</span><span>Test This Vessel</span>';
                button.disabled = false;
            }
        } catch (error) {
            console.error('Poll error:', error);
        }
    }, 5000);
}
```

### Step 3: Test It! (2 minutes)

```bash
php artisan serve
```

Visit: http://localhost:8000/vessel-test-public

1. Select: **SIAM - Siam Commercial**
2. Vessel: **MAKHA BHUM**
3. Voyage: **119S**
4. Click: **Test This Vessel**
5. Watch the real-time polling! 🎉

---

## 🏗️ Architecture

### System Flow

```
┌─────────────────────────────────────────┐
│         USER BROWSER                    │
│  [vessel-test.blade.php]               │
│         │                               │
│         │ 1. User selects SIAM         │
│         ↓                               │
│  handleSiamTerminalTest()              │
└─────────┼───────────────────────────────┘
          │
          │ 2. POST /vessel-test-public/siam/start
          ↓
┌─────────────────────────────────────────┐
│      LARAVEL BACKEND                    │
│  [SiamTerminalController]              │
│         │                               │
│         ├─→ Check: Cached?             │
│         │   YES → Return immediately    │
│         │   NO  → Continue...          │
│         │                               │
│         └─→ Trigger n8n webhook        │
└─────────┼───────────────────────────────┘
          │
          │ 3. HTTP POST
          ↓
┌─────────────────────────────────────────┐
│       N8N WORKFLOW                      │
│         │                               │
│         ├─→ Send LINE message          │
│         ├─→ Wait for admin response    │
│         └─→ Update database             │
└─────────┼───────────────────────────────┘
          │
          │ 4. Writes to DB
          ↓
┌─────────────────────────────────────────┐
│       DATABASE                          │
│  [siam_com_chatbot_eta_requests]       │
│    status: PENDING → COMPLETE          │
└─────────┬───────────────────────────────┘
          │
          │ 5. Polls every 5s
          ↓
┌─────────────────────────────────────────┐
│         USER BROWSER                    │
│  pollSiamChatbotStatus()               │
│         │                               │
│         ├─ PENDING  → Keep polling     │
│         ├─ COMPLETE → Show ETA         │
│         └─ FAILED   → Show error       │
└─────────────────────────────────────────┘
```

### Database Schema

**Table**: `siam_com_chatbot_eta_requests`

```sql
- id (primary key)
- group_id (LINE group ID)
- vessel_name (varchar)
- voyage_code (varchar)
- last_known_eta (datetime)
- status (READY/PENDING/COMPLETE/FAILED)
- last_asked_at (datetime)
- attempts (integer)
- conversation_history (json)
- created_at (timestamp)
- updated_at (timestamp)
```

### Rate Limiting Logic

```php
// Check if should ask new (3-hour cache)
public function shouldAskNew($hours = 3)
{
    // Never asked before
    if (!$this->last_asked_at) return true;
    
    // No ETA data available
    if (is_null($this->last_known_eta)) return true;
    
    // Time expired (3+ hours passed)
    return $this->last_asked_at->diffInHours(now()) >= $hours;
}
```

---

## 🧪 Testing

### Manual Testing Checklist

#### Test 1: First Request
```
Input: MAKHA BHUM / 119S
Expected: 
  ✅ Shows "Chatbot Initialization"
  ✅ Changes to "Chatbot Active"
  ✅ Timer counts up
  ✅ After admin responds: Shows ETA
```

#### Test 2: Cached Data
```
Input: Same vessel (within 3 hours)
Expected:
  ✅ Shows "Using Cached Data"
  ✅ Displays ETA immediately
  ✅ No polling occurs
```

#### Test 3: Invalid Input
```
Input: Vessel only (no voyage code)
Expected:
  ✅ Alert: "Voyage code is required"
```

#### Test 4: Network Error
```
Condition: n8n not running
Expected:
  ✅ Shows error message
  ✅ Button re-enables
```

### Testing Commands

```bash
# Test routes exist
php artisan route:list | grep siam

# Test controller methods
php artisan tinker
>>> $controller = new App\Http\Controllers\SiamTerminalController();
>>> get_class_methods($controller);

# Test database connection
php artisan tinker
>>> App\Models\SiamComChatbotEtaRequest::count();

# Test n8n webhook (manual curl)
curl -X POST http://localhost:5678/webhook/siam-com-eta \
  -H "Content-Type: application/json" \
  -d '{"vessel_name":"MAKHA BHUM","voyage_code":"119S"}'
```

---

## 🔧 Troubleshooting

### Issue: "Failed to trigger n8n workflow"

**Symptoms**: Error immediately when clicking Test

**Causes**:
- n8n is not running
- Wrong webhook URL in `.env`
- Network connectivity issue

**Solutions**:
1. Check n8n is running: Visit http://localhost:5678
2. Verify `.env`: `N8N_SIAM_WEBHOOK_URL` is correct
3. Test webhook manually with curl
4. Check Laravel logs: `tail -f storage/logs/laravel.log`

### Issue: Polling shows "asking" forever

**Symptoms**: Status never changes from "asking"

**Causes**:
- n8n workflow not updating database
- Wrong LINE group ID
- Admin not responding in LINE

**Solutions**:
1. Check n8n execution logs
2. Verify `SIAM_COM_LINE_GROUP_ID` in `.env`
3. Check database:
   ```sql
   SELECT * FROM siam_com_chatbot_eta_requests 
   ORDER BY updated_at DESC LIMIT 1;
   ```
4. Ensure n8n calls: `PUT /api/siam-com/chatbot/eta/update`

### Issue: JavaScript error "handleSiamTerminalTest is not defined"

**Symptoms**: Console error when clicking Test

**Causes**:
- Helper functions not added to JavaScript
- Typo in function name

**Solutions**:
1. Check all helper functions are added before `</script>`
2. Clear browser cache (Ctrl+Shift+R)
3. Inspect browser console for exact error

### Issue: "Vessel not found" even with correct data

**Symptoms**: Always returns failed status

**Causes**:
- Vessel name or voyage code mismatch
- Database not updated by n8n
- Wrong group ID

**Solutions**:
1. Check exact spelling of vessel/voyage
2. Verify n8n logs show successful execution
3. Check database for matching records
4. Ensure case-sensitive matching

---

## 📚 API Reference

### 1. Start Chatbot Request

**Endpoint**: `POST /vessel-test-public/siam/start`

**Request**:
```json
{
  "vessel_name": "MAKHA BHUM",
  "voyage_code": "119S"
}
```

**Response (Cached)**:
```json
{
  "success": true,
  "status": "cached",
  "message": "Using cached ETA data",
  "data": {
    "vessel_name": "MAKHA BHUM",
    "voyage_code": "119S",
    "eta": "2025-09-19 07:30:00",
    "hours_ago": 2,
    "terminal": "Siam Commercial",
    "vessel_found": true,
    "voyage_found": true
  }
}
```

**Response (Asking)**:
```json
{
  "success": true,
  "status": "asking",
  "message": "Chatbot is contacting Siam Commercial admin via LINE...",
  "data": {
    "vessel_name": "MAKHA BHUM",
    "voyage_code": "119S",
    "terminal": "Siam Commercial",
    "estimated_wait_time": "1-5 minutes"
  }
}
```

**Error Response**:
```json
{
  "success": false,
  "error": "Failed to trigger n8n workflow",
  "status": "error"
}
```

### 2. Poll Chatbot Status

**Endpoint**: `GET /vessel-test-public/siam/poll`

**Query Parameters**:
- `vessel_name` (required): "MAKHA BHUM"
- `voyage_code` (required): "119S"

**Response (Complete)**:
```json
{
  "success": true,
  "status": "complete",
  "message": "ETA received from Siam Commercial admin",
  "data": {
    "vessel_name": "MAKHA BHUM",
    "voyage_code": "119S",
    "eta": "2025-09-19 07:30:00",
    "terminal": "Siam Commercial",
    "vessel_found": true,
    "voyage_found": true,
    "search_method": "n8n_chatbot",
    "checked_at": "2025-09-30 11:30:15"
  }
}
```

**Response (Pending)**:
```json
{
  "success": true,
  "status": "asking",
  "message": "Chatbot is asking Siam Commercial admin... (attempt 2/5)",
  "attempts": 2,
  "elapsed_time": 35
}
```

**Response (Failed)**:
```json
{
  "success": true,
  "status": "failed",
  "message": "Failed to get ETA from Siam Commercial admin",
  "data": {
    "vessel_name": "MAKHA BHUM",
    "voyage_code": "119S",
    "terminal": "Siam Commercial",
    "vessel_found": false,
    "error": "No response from admin after multiple attempts"
  }
}
```

### 3. Get Terminal Config

**Endpoint**: `GET /vessel-test-public/siam/config`

**Response**:
```json
{
  "terminal": "SIAM",
  "name": "Siam Commercial",
  "method": "n8n_chatbot",
  "default_vessel": "MAKHA BHUM",
  "default_voyage": "119S",
  "polling_interval": 5000,
  "max_wait_time": 300,
  "cache_duration": 3,
  "requires_voyage_code": true
}
```

---

## 📊 Files Modified/Created

### Created:
- ✅ `app/Http/Controllers/SiamTerminalController.php` - Main controller
- ✅ `SIAM_COMPLETE_GUIDE.md` - This documentation (consolidated)

### Modified:
- ✅ `routes/web.php` - Added SIAM routes (3 lines)
- ✅ `resources/views/vessel-test.blade.php` - Added dropdown option + card
- ⏳ `resources/views/vessel-test.blade.php` - Need to add JavaScript (TODO)
- ⏳ `.env` - Need to add environment variables (TODO)

### Unchanged (Already Exists):
- ✅ `app/Models/SiamComChatbotEtaRequest.php`
- ✅ `app/Http/Controllers/Api/SiamComChatbotEtaRequestController.php`
- ✅ `routes/api.php` (n8n integration routes)
- ✅ Database table: `siam_com_chatbot_eta_requests`

---

## ✅ Quick Checklist

Before deploying:
- [ ] Added `N8N_SIAM_WEBHOOK_URL` to `.env`
- [ ] Added `SIAM_COM_LINE_GROUP_ID` to `.env`
- [ ] Added SIAM detection in JavaScript (line ~220)
- [ ] Added all helper functions before `</script>`
- [ ] Tested with MAKHA BHUM / 119S
- [ ] Verified n8n workflow is active
- [ ] Checked database connection
- [ ] Tested polling mechanism
- [ ] Verified cached data returns correctly

---

## 🎉 Success Metrics

Your implementation is successful when:

✅ SIAM appears in terminal dropdown  
✅ Selecting SIAM shows initialization message  
✅ Polling starts automatically with timer  
✅ Status updates every 5 seconds  
✅ Success message shows when admin responds  
✅ Cached data returns immediately on second request  
✅ No JavaScript errors in console  
✅ No errors in Laravel logs  

---

**Total Implementation Time**: 10-15 minutes  
**Difficulty**: Easy (mostly copy-paste)  
**Status**: Backend Complete ✅ | Frontend JavaScript Pending ⏳

---

*Last Updated: September 30, 2025*  
*Version: 1.0 - Consolidated Documentation*
