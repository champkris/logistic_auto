# Browser Automation Strategy for CS Shipping LCB Vessel Tracking

## 🎯 Problem Analysis

**JavaScript-Dependent Terminals Identified:**
- **LCB1**: Requires vessel selection from dropdown + search button
- **Potentially LCIT & ECTT**: May require similar interactions

**Requirements:**
- Automate vessel selection and data retrieval
- Integration with existing Laravel system  
- Scheduled execution (daily updates)
- Reliable error handling
- Scalable to multiple terminals

---

## 🔧 **Approach Options**

### **Option 1: n8n Workflow Automation** ⭐ **RECOMMENDED**

**✅ Advantages:**
- **Visual Workflow Builder** - Easy to create and modify automation
- **Built-in Browser Automation** - Puppeteer integration included
- **Scheduling** - Built-in cron-like scheduling
- **Laravel Integration** - Can call your Laravel APIs via webhooks
- **Error Handling** - Built-in retry logic and error notifications
- **Monitoring** - Visual execution logs and debugging
- **No Code Changes** - Works alongside your existing Laravel system

**🔧 Implementation Plan:**
```
┌─────────────────┐    ┌──────────────┐    ┌─────────────────┐
│   n8n Workflow │───▶│   Browser    │───▶│   Laravel API   │
│   (Scheduled)   │    │   Automation │    │   (Update DB)   │
└─────────────────┘    └──────────────┘    └─────────────────┘
```

**📋 Workflow Steps:**
1. **Schedule Trigger** - Run every 6 hours
2. **Browser Node** - Open LCB1 website
3. **Select Vessel** - Choose MARSA PRIDE from dropdown
4. **Click Search** - Trigger data load
5. **Extract Data** - Get ETA from loaded table
6. **HTTP Request** - POST data to Laravel `/api/vessel-update`
7. **Error Handler** - Notify if failed

---

### **Option 2: Laravel + Puppeteer (PHP)**

**✅ Advantages:**
- **Native Integration** - Direct PHP implementation
- **Full Control** - Custom error handling and logging
- **Single Codebase** - Everything in Laravel

**❌ Disadvantages:**
- **More Development** - Need to build browser automation from scratch
- **Server Requirements** - Chrome/Chromium on server
- **Complexity** - Handle browser lifecycle, memory management

---

### **Option 3: Laravel Dusk Extension**

**✅ Advantages:**
- **Laravel Native** - Built-in Laravel testing tool
- **Easy Syntax** - Familiar Laravel/PHP patterns

**❌ Disadvantages:**
- **Testing Focus** - Designed for testing, not production automation
- **Limited Scheduling** - Would need Laravel scheduler
- **Overhead** - Full browser for simple data extraction

---

## 🚀 **n8n Implementation Strategy**

### **Phase 1: Setup & Basic Automation**

#### **1.1 n8n Installation**
```bash
# Option A: Docker (Recommended)
docker run -it --rm --name n8n -p 5678:5678 -v ~/.n8n:/home/node/.n8n n8nio/n8n

# Option B: npm
npm install n8n -g
n8n start
```

#### **1.2 LCB1 Browser Automation Workflow**
```json
{
  "nodes": [
    {
      "name": "Schedule Trigger",
      "type": "n8n-nodes-base.cron",
      "parameters": {
        "rule": {
          "hour": "*/6"
        }
      }
    },
    {
      "name": "Open LCB1",
      "type": "n8n-nodes-base.puppeteer", 
      "parameters": {
        "url": "https://www.lcb1.com/BerthSchedule",
        "waitForSelector": "select[name='vessel']"
      }
    },
    {
      "name": "Select Vessel",
      "type": "n8n-nodes-base.puppeteer",
      "parameters": {
        "action": "select",
        "selector": "select[name='vessel']",
        "value": "MARSA PRIDE"
      }
    },
    {
      "name": "Click Search",
      "type": "n8n-nodes-base.puppeteer",
      "parameters": {
        "action": "click",
        "selector": "button[type='submit']",
        "waitFor": "table"
      }
    },
    {
      "name": "Extract Data",
      "type": "n8n-nodes-base.puppeteer",
      "parameters": {
        "action": "evaluate",
        "script": "() => { /* Extract vessel data logic */ }"
      }
    },
    {
      "name": "Update Laravel",
      "type": "n8n-nodes-base.httpRequest",
      "parameters": {
        "url": "http://localhost:8000/api/vessel-update",
        "method": "POST",
        "body": "json"
      }
    }
  ]
}
```

### **Phase 2: Laravel API Integration**

#### **2.1 Create Laravel API Endpoint**
```php
// routes/api.php
Route::post('/vessel-update', [VesselTrackingController::class, 'updateFromBrowser']);

// app/Http/Controllers/VesselTrackingController.php  
public function updateFromBrowser(Request $request)
{
    $validated = $request->validate([
        'terminal' => 'required|string',
        'vessel_name' => 'required|string', 
        'voyage_code' => 'string|nullable',
        'eta' => 'date|nullable',
        'source' => 'required|string'
    ]);
    
    // Update vessel in database
    $vessel = Vessel::updateOrCreate(
        ['vessel_name' => $validated['vessel_name']],
        $validated
    );
    
    return response()->json(['success' => true, 'vessel' => $vessel]);
}
```

#### **2.2 Enhanced VesselTrackingService**
```php
// app/Services/VesselTrackingService.php
public function getBrowserRequiredTerminals()
{
    return collect($this->terminals)
        ->where('status', 'requires_js')
        ->keys();
}

public function handleBrowserUpdate($terminalCode, $data)
{
    // Process browser-extracted data
    // Update database
    // Trigger notifications if needed
}
```

### **Phase 3: Advanced Features**

#### **3.1 Multi-Terminal Support**
```json
{
  "workflows": {
    "lcb1_automation": { /* LCB1 specific */ },
    "lcit_automation": { /* LCIT specific */ },
    "ectt_automation": { /* ECTT specific */ }
  }
}
```

#### **3.2 Error Handling & Notifications**
- **Slack/Email alerts** when automation fails
- **Retry logic** with exponential backoff
- **Screenshot capture** on errors for debugging
- **Fallback to manual notification** if automation fails

#### **3.3 Monitoring Dashboard**
```php
// app/Http/Controllers/MonitoringController.php
public function automationStatus()
{
    return [
        'direct_terminals' => $this->getDirectTerminalStatus(),
        'browser_terminals' => $this->getBrowserTerminalStatus(), 
        'last_updates' => $this->getLastUpdateTimes(),
        'error_rates' => $this->getErrorRates()
    ];
}
```

---

## 📊 **Architecture Overview**

### **Hybrid Approach: Direct + Browser Automation**

```
┌─────────────────────────────────────────────────────────────┐
│                    CS Shipping LCB System                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────┐         ┌─────────────────────────────┐ │
│  │   Laravel App   │◄────────┤      VesselTracking         │ │
│  │                 │         │        Service              │ │
│  │  ┌──────────────┐│         │                             │ │
│  │  │   Direct     ││         │  ┌─────────┐ ┌─────────────┐│ │
│  │  │  Terminal    ││         │  │  TIPS   │ │ Hutchison   ││ │
│  │  │  Integration ││         │  │  (HTTP) │ │   (HTTP)    ││ │  
│  │  └──────────────┘│         │  └─────────┘ └─────────────┘│ │
│  │                 │         │  ┌─────────┐                 │ │
│  │  ┌──────────────┐│         │  │  ESCO   │                 │ │
│  │  │  Dashboard & ││         │  │ (HTTP)  │                 │ │
│  │  │    API       ││         │  └─────────┘                 │ │
│  │  └──────────────┘│         └─────────────────────────────┘ │
│  └─────────────────┘                                         │
│           ▲                                                  │
│           │ API Calls                                        │
│           ▼                                                  │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │                    n8n Workflows                        │ │
│  │                                                         │ │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐  │ │
│  │  │    LCB1     │  │    LCIT     │  │      ECTT       │  │ │
│  │  │ Browser Bot │  │ Browser Bot │  │   Browser Bot   │  │ │
│  │  │             │  │             │  │                 │  │ │
│  │  │ ┌─────────┐ │  │ ┌─────────┐ │  │ ┌─────────────┐ │  │ │
│  │  │ │Puppeteer│ │  │ │Puppeteer│ │  │ │  Puppeteer  │ │  │ │
│  │  │ └─────────┘ │  │ └─────────┘ │  │ └─────────────┘ │  │ │
│  │  └─────────────┘  └─────────────┘  └─────────────────┘  │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🚀 **Implementation Timeline**

### **Week 1: n8n Setup**
- [ ] Install n8n (Docker recommended)
- [ ] Create basic LCB1 workflow
- [ ] Test browser automation manually
- [ ] Create Laravel API endpoint

### **Week 2: Integration**  
- [ ] Connect n8n to Laravel API
- [ ] Implement error handling
- [ ] Add scheduling (every 6 hours)
- [ ] Test end-to-end flow

### **Week 3: Additional Terminals**
- [ ] Analyze LCIT website requirements
- [ ] Create LCIT automation workflow  
- [ ] Analyze ECTT website requirements
- [ ] Create ECTT automation workflow

### **Week 4: Production & Monitoring**
- [ ] Deploy to production server
- [ ] Set up monitoring dashboard
- [ ] Configure alerts (Slack/Email)
- [ ] Documentation & handover

---

## 💡 **Why n8n is Perfect for This Use Case**

### **✅ Advantages for CS Shipping LCB:**
1. **Visual Workflows** - Your team can easily see and modify automation
2. **No Developer Lock-in** - Non-technical staff can maintain workflows
3. **Built-in Scheduling** - Perfect for daily vessel updates
4. **Error Handling** - Automatic retries and notifications
5. **Laravel Integration** - Seamless API calls to your system
6. **Browser Automation** - Puppeteer included out of the box
7. **Scalable** - Easy to add more terminals
8. **Community** - Large community and extensive documentation

### **📊 Expected Results:**
- **100% Terminal Coverage** - All 6 terminals automated
- **Reliable Data** - Consistent ETA updates every 6 hours
- **Error Resilience** - Automatic retries and fallbacks  
- **Easy Maintenance** - Visual workflow editing
- **Monitoring** - Clear logs of all automation runs

---

## 🎯 **Next Steps**

1. **Install n8n** on your development machine
2. **Create basic LCB1 workflow** as proof of concept
3. **Test integration** with your Laravel API
4. **Scale to other terminals** once proven

Would you like me to help you set up the initial n8n workflow for LCB1?
