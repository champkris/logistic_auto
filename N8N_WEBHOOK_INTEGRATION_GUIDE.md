# 🔗 n8n to Laravel Integration Guide - SIAM Terminal

## 📋 Overview

Your Laravel API endpoints are **already set up** and ready to receive webhooks from n8n! This guide shows you how to configure n8n to communicate with your Laravel application.

---

## 🎯 Available Laravel API Endpoints

Your Laravel app has these endpoints ready for n8n (located in `routes/api.php`):

### 1. Start ETA Request
```
POST http://your-domain.com/api/siam-com/chatbot/eta/start
```

### 2. Get Pending Request
```
GET http://your-domain.com/api/siam-com/chatbot/eta/pending
```

### 3. Update ETA Status
```
PUT http://your-domain.com/api/siam-com/chatbot/eta/update
```

### 4. Get Attempts Count
```
GET http://your-domain.com/api/siam-com/chatbot/eta/get-attempts?group_id={group_id}
```

### 5. Increment Attempts
```
POST http://your-domain.com/api/siam-com/chatbot/eta/increment-attempts
```

### 6. Get All Requests (Testing)
```
GET http://your-domain.com/api/siam-com/chatbot/eta/all
```

---

## 🔧 n8n Workflow Setup

### Step 1: Add Webhook Trigger (Receives from Laravel)

**Node Type**: `Webhook`

**Settings**:
- **Path**: `siam-com-eta`
- **HTTP Method**: `POST`
- **Response Mode**: `Using 'Respond to Webhook' Node`

**Full URL** (when n8n is running):
```
http://localhost:5678/webhook/siam-com-eta
```

This is the URL you put in Laravel's `.env`:
```env
N8N_SIAM_WEBHOOK_URL=http://localhost:5678/webhook/siam-com-eta
```

**What it receives** (from Laravel):
```json
{
  "vessel_name": "MAKHA BHUM",
  "voyage_code": "119S"
}
```

---

### Step 2: Check Rate Limiting (HTTP Request to Laravel)

**Node Type**: `HTTP Request`

**Settings**:
- **Method**: `POST`
- **URL**: `http://localhost:8000/api/siam-com/chatbot/eta/start`
- **Authentication**: `None`
- **Send Body**: `Yes`
- **Body Content Type**: `JSON`
- **Body (JSON)**:
```json
{
  "vessel_name": "{{ $json.vessel_name }}",
  "voyage_code": "{{ $json.voyage_code }}"
}
```

**What it returns**:
```json
{
  "success": true,
  "action": "ask_new" or "return_cached",
  "data": {
    "vessel_name": "MAKHA BHUM",
    "voyage_code": "119S",
    "should_ask_line": true or false,
    "eta": "..." (if cached)
  }
}
```

**What to do next**:
- If `action === "return_cached"` → Return immediately (don't ask LINE)
- If `action === "ask_new"` → Continue to LINE message

---

### Step 3: IF Node - Check Should Ask LINE

**Node Type**: `IF`

**Condition**:
```
{{ $json.action }} === "ask_new"
```

**TRUE Branch**: Continue to send LINE message  
**FALSE Branch**: Go to "Respond to Webhook" (return cached data)

---

### Step 4: Send LINE Message

**Node Type**: `LINE` (or HTTP Request to LINE API)

**Message**:
```
มีใครรู้ ETA ของเรือ {{ $json.vessel_name }} voyage {{ $json.voyage_code }} ไหมครับ? (สำหรับ Siam Com)
```

**Group ID**: Get from your SIAM_COM_LINE_GROUP_ID

---

### Step 5: Wait for LINE Response

**Node Type**: `Wait` or polling mechanism

You need to set up another workflow that:
1. Listens to LINE webhook for messages
2. Parses admin responses
3. Extracts ETA information
4. Updates Laravel database

---

### Step 6: Update Laravel Database (HTTP Request)

**Node Type**: `HTTP Request`

**Settings**:
- **Method**: `PUT`
- **URL**: `http://localhost:8000/api/siam-com/chatbot/eta/update`
- **Body (JSON)**:
```json
{
  "group_id": "{{ $json.group_id }}",
  "status": "COMPLETE",
  "eta": "2025-09-19 07:30:00",
  "conversation_message": "Admin responded with ETA"
}
```

**Status values**:
- `PENDING` - Still asking
- `COMPLETE` - Got ETA successfully
- `FAILED` - No response after multiple attempts

---

### Step 7: Respond to Webhook

**Node Type**: `Respond to Webhook`

**Response**:
```json
{
  "success": true,
  "action": "{{ $json.action }}",
  "message": "Request processed",
  "data": "{{ $json }}"
}
```

---

## 📊 Complete n8n Workflow Diagram

```
┌─────────────────────────────────────────────────────────┐
│ 1. Webhook Trigger                                      │
│    Path: /webhook/siam-com-eta                         │
│    Receives: { vessel_name, voyage_code }              │
└─────────────┬───────────────────────────────────────────┘
              │
              ↓
┌─────────────────────────────────────────────────────────┐
│ 2. HTTP Request: Check Rate Limit                      │
│    POST /api/siam-com/chatbot/eta/start                │
│    Returns: { action: "ask_new" or "return_cached" }   │
└─────────────┬───────────────────────────────────────────┘
              │
              ↓
┌─────────────────────────────────────────────────────────┐
│ 3. IF: Should Ask LINE?                                 │
│    Condition: action === "ask_new"                      │
└─────┬───────────────────────────────────────────┬───────┘
      │ TRUE                                      │ FALSE
      ↓                                           ↓
┌─────────────────────────┐     ┌──────────────────────────┐
│ 4. Send LINE Message    │     │ 7. Respond to Webhook    │
│    "มีใครรู้ ETA..."      │     │    Return cached data    │
└─────────┬───────────────┘     └──────────────────────────┘
          │
          ↓
┌─────────────────────────────────────────────────────────┐
│ 5. Wait for Response / Polling                          │
│    (Separate workflow listens to LINE webhook)         │
└─────────────┬───────────────────────────────────────────┘
              │
              ↓
┌─────────────────────────────────────────────────────────┐
│ 6. HTTP Request: Update Database                        │
│    PUT /api/siam-com/chatbot/eta/update                │
│    Body: { status: "COMPLETE", eta: "..." }            │
└─────────────┬───────────────────────────────────────────┘
              │
              ↓
┌─────────────────────────────────────────────────────────┐
│ 7. Respond to Webhook                                   │
│    Return success response to Laravel                   │
└─────────────────────────────────────────────────────────┘
```

---

## 🔑 Configuration Details

### Laravel Side (.env)

```env
# This is the n8n webhook URL that Laravel calls
N8N_SIAM_WEBHOOK_URL=http://localhost:5678/webhook/siam-com-eta

# Your LINE group ID
SIAM_COM_LINE_GROUP_ID=C1234567890abcdef1234567890abcdef
```

### n8n Side

**Webhook Settings**:
- Production URL: `https://your-n8n-domain.com/webhook/siam-com-eta`
- Test URL: `http://localhost:5678/webhook/siam-com-eta`

**Important**: If n8n is on a different server than Laravel:
- Update Laravel's `.env` with n8n's public URL
- Ensure n8n can reach Laravel's API (may need ngrok or public domain)

---

## 🧪 Testing the Integration

### Test 1: Manual Webhook Test

From terminal, trigger n8n webhook:

```bash
curl -X POST http://localhost:5678/webhook/siam-com-eta \
  -H "Content-Type: application/json" \
  -d '{
    "vessel_name": "MAKHA BHUM",
    "voyage_code": "119S"
  }'
```

**Expected**: n8n workflow executes

### Test 2: Test Laravel API Endpoints

```bash
# Test rate limit check
curl -X POST http://localhost:8000/api/siam-com/chatbot/eta/start \
  -H "Content-Type: application/json" \
  -d '{
    "vessel_name": "MAKHA BHUM",
    "voyage_code": "119S"
  }'

# Test get pending
curl http://localhost:8000/api/siam-com/chatbot/eta/pending

# Test update status
curl -X PUT http://localhost:8000/api/siam-com/chatbot/eta/update \
  -H "Content-Type: application/json" \
  -d '{
    "group_id": "your_group_id",
    "status": "COMPLETE",
    "eta": "2025-09-19 07:30:00"
  }'
```

### Test 3: End-to-End Test

1. Go to: `http://localhost:8000/vessel-test-public`
2. Select: SIAM - Siam Commercial
3. Enter: MAKHA BHUM / 119S
4. Click: Test This Vessel
5. Monitor:
   - Laravel logs: `tail -f storage/logs/laravel.log`
   - n8n execution logs
   - Database: `SELECT * FROM siam_com_chatbot_eta_requests;`

---

## 🔍 Debugging

### Check if n8n Receives Webhook

In n8n workflow:
1. Click on Webhook node
2. Click "Listen for Test Event"
3. Trigger from Laravel
4. You should see the payload

### Check if Laravel Receives n8n Response

```bash
# Check Laravel logs
tail -f storage/logs/laravel.log | grep SIAM
```

### Check Database Updates

```sql
-- Check latest request
SELECT * FROM siam_com_chatbot_eta_requests 
ORDER BY updated_at DESC LIMIT 1;

-- Check status changes
SELECT vessel_name, voyage_code, status, last_known_eta, updated_at 
FROM siam_com_chatbot_eta_requests 
ORDER BY updated_at DESC;
```

---

## 📝 n8n Node Configuration Examples

### Node 1: Webhook Trigger

```json
{
  "nodes": [
    {
      "parameters": {
        "path": "siam-com-eta",
        "responseMode": "responseNode",
        "options": {}
      },
      "name": "Webhook",
      "type": "n8n-nodes-base.webhook",
      "position": [250, 300]
    }
  ]
}
```

### Node 2: HTTP Request to Laravel

```json
{
  "parameters": {
    "method": "POST",
    "url": "http://localhost:8000/api/siam-com/chatbot/eta/start",
    "options": {
      "timeout": 10000
    },
    "bodyParametersJson": "={{ JSON.stringify({\n  \"vessel_name\": $json.vessel_name,\n  \"voyage_code\": $json.voyage_code\n}) }}"
  },
  "name": "Check Rate Limit",
  "type": "n8n-nodes-base.httpRequest"
}
```

### Node 3: IF Condition

```json
{
  "parameters": {
    "conditions": {
      "string": [
        {
          "value1": "={{ $json.action }}",
          "value2": "ask_new"
        }
      ]
    }
  },
  "name": "Should Ask LINE?",
  "type": "n8n-nodes-base.if"
}
```

### Node 4: Update Laravel

```json
{
  "parameters": {
    "method": "PUT",
    "url": "http://localhost:8000/api/siam-com/chatbot/eta/update",
    "bodyParametersJson": "={{ JSON.stringify({\n  \"group_id\": \"{{ $env.SIAM_COM_LINE_GROUP_ID }}\",\n  \"status\": \"COMPLETE\",\n  \"eta\": $json.eta,\n  \"conversation_message\": \"Admin responded\"\n}) }}"
  },
  "name": "Update Database",
  "type": "n8n-nodes-base.httpRequest"
}
```

---

## 🎯 Quick Setup Checklist

- [ ] n8n is running on port 5678
- [ ] Webhook node created with path `siam-com-eta`
- [ ] HTTP Request node configured to call Laravel API
- [ ] LINE bot connected and group ID obtained
- [ ] Laravel `.env` has `N8N_SIAM_WEBHOOK_URL`
- [ ] Laravel `.env` has `SIAM_COM_LINE_GROUP_ID`
- [ ] Test webhook from curl ✓
- [ ] Test Laravel API endpoints ✓
- [ ] Test end-to-end from browser ✓

---

## 🔒 Security Notes

### For Production:

1. **Use HTTPS** for both Laravel and n8n
2. **Add webhook authentication**:
   ```php
   // In routes/api.php
   Route::middleware('verify.webhook.token')->group(function() {
       // SIAM routes here
   });
   ```

3. **Rate limiting**:
   ```php
   Route::middleware('throttle:60,1')->group(function() {
       // API routes
   });
   ```

4. **Validate webhook source**:
   - Check IP whitelist
   - Verify webhook signature
   - Use API tokens

---

## 📚 Related Files

- `routes/api.php` - All API endpoints (already configured)
- `app/Http/Controllers/Api/SiamComChatbotEtaRequestController.php` - Handles n8n requests
- `app/Models/SiamComChatbotEtaRequest.php` - Database model
- `.env` - Configuration (need to add webhook URL)

---

**Status**: API endpoints ready ✅ | n8n configuration needed ⏳

**Next**: Configure n8n webhook nodes using this guide!
