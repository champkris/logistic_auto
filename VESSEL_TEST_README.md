# 🚢 Vessel Tracking Test Setup
**CS Shipping LCB - Real Terminal ETA Testing**

---

## 🎯 **What This Test Does**

This test suite checks **6 real terminal websites** to see if we can:
1. **Access the vessel schedule pages**
2. **Find specific vessel names in the HTML**
3. **Extract ETA (Estimated Time of Arrival) data**
4. **Evaluate automation feasibility**

### **Test Vessels & Terminals:**
```
┌─────────┬─────────────────┬──────────────────────┬────────────────────────┐
│Terminal │ Operator        │ Vessel Name          │ Status                 │
├─────────┼─────────────────┼──────────────────────┼────────────────────────┤
│ C1C2    │ Hutchison Ports │ WAN HAI 517 S093     │ Oracle APEX App        │
│ B4      │ TIPS            │ SRI SUREE V.25080S   │ Container Schedule     │
│ B5/C3   │ LCIT            │ ASL QINGDAO V.2508S  │ Home Page Navigation   │
│ B3      │ ESCO            │ CUL NANSHA V. 2528S  │ Berth Schedule         │
│ A0/B1   │ LCB1            │ MARSA PRIDE V.528S   │ Berth Schedule         │
│ B2      │ ECTT            │ EVER BUILD V.0794-074S│ Cookie Policy Page     │
└─────────┴─────────────────┴──────────────────────┴────────────────────────┘
```

---

## 🚀 **How to Run the Tests**

### **Method 1: Command Line (Standalone)**
```bash
# Navigate to your project directory
cd /Users/apichakriskalambasuta/Sites/localhost/logistic_auto

# Run the standalone test
php vessel_test.php
```

**This will:**
- Test all 6 terminals sequentially
- Show real-time progress
- Display detailed results with HTML analysis
- Provide success rate statistics
- Give automation recommendations

### **Method 2: Web Browser (Interactive)**
```bash
# Make sure your Laravel server is running
php artisan serve

# Open in browser
http://localhost:8000/vessel-test

# Click "Run Test" button
```

**Features:**
- Beautiful web interface
- Real-time progress updates
- Interactive results display
- Raw data previews
- Expandable sections for details

### **Method 3: Laravel Service (Integrated)**
```bash
# If you have the Laravel command set up
php artisan vessel:test

# Test specific terminal
php artisan vessel:test C1C2
```

---

## 📊 **Expected Test Results**

### **Success Scenarios:**
```
✅ VESSEL FOUND!
🕒 ETA: 2025-07-25 14:30:00
📄 Raw Data Preview: "...WAN HAI 517 S093...ETA 25/07/2025 14:30..."
🕐 Checked at: 2025-07-22 15:45:23
```

### **Partial Success:**
```
✅ VESSEL FOUND!
⚠️  ETA: Not found or could not parse
📄 Raw Data Preview: "...vessel schedule showing WAN HAI 517..."
```

### **Not Found:**
```
❌ Vessel not found in schedule
💬 Message: Vessel not found in HTML content
📄 HTML Size: 45,231 bytes
```

### **Access Issues:**
```
❌ ERROR: Failed to fetch URL - may be blocked or down
```

---

## 🔍 **What the Test Analyzes**

### **1. Website Accessibility**
- Can we access the terminal website?
- Are we being blocked by security measures?
- What's the response time and HTML size?

### **2. Vessel Detection**
- Is the vessel name present in the HTML?
- Where in the page structure is it located?
- What's the surrounding context?

### **3. ETA Extraction**
- Can we find date/time patterns near the vessel?
- What date formats are used?
- Is the ETA clearly associated with the vessel?

### **4. Automation Viability**
- How reliable is the data extraction?
- What's the success rate across terminals?
- Which terminals are best for automation?

---

## 🛠️ **Technical Details**

### **Web Scraping Approach:**
```php
// HTTP Request with browser-like headers
$headers = [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) WebKit/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language' => 'en-US,en;q=0.5',
    'Connection' => 'keep-alive'
];

// Respectful rate limiting
sleep(2); // 2 seconds between requests
```

### **ETA Pattern Matching:**
```php
$datePatterns = [
    '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/',     // DD/MM/YYYY HH:MM
    '/(\d{4}-\d{2}-\d{2})\s*(\d{2}:\d{2})/',              // YYYY-MM-DD HH:MM
    '/ETA[:\s]*(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/i',
    '/Estimated[:\s]*(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/i',
];
```

### **Data Extraction:**
```php
// Find vessel in HTML
$vesselFound = stripos($html, $vesselName) !== false;

// Extract surrounding context (1000 chars)
$pos = stripos($html, $vesselName);
$start = max(0, $pos - 500);
$vesselSection = substr($html, $start, 1000);
```

---

## 📈 **Interpreting Results**

### **Success Rate Benchmarks:**
- **90-100%**: Excellent - Ready for full automation
- **70-89%**: Good - Automation viable with error handling
- **50-69%**: Fair - May need backup methods
- **<50%**: Poor - Consider alternative approaches

### **ETA Extraction Quality:**
- **High**: Specific date/time format found
- **Medium**: Date found but no time
- **Low**: Vessel found but no date/time patterns
- **None**: Vessel not found or page inaccessible

---

## 🔧 **Troubleshooting**

### **Common Issues:**

#### **"Failed to fetch URL"**
- Website may be down or blocking requests
- Try accessing manually in browser
- Check if VPN/proxy is needed

#### **"Vessel not found"**
- Vessel may not be in current schedule
- Name format might be different on website
- Page structure may have changed

#### **"Could not parse ETA"**
- Date format not matching our patterns
- ETA displayed as image or in JavaScript
- Need custom parser for this terminal

#### **SSL/HTTPS Issues**
```bash
# If you get SSL certificate errors
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
```

---

## 🎯 **Next Steps Based on Results**

### **If Results are Good (70%+ success):**
1. **Integrate into Laravel application**
2. **Set up scheduled jobs** (CRON)
3. **Add error handling and retries**
4. **Create customer notification system**

### **If Results are Mixed (30-70% success):**
1. **Focus on high-success terminals first**
2. **Develop terminal-specific parsers**
3. **Add fallback notification methods**
4. **Consider API alternatives**

### **If Results are Poor (<30% success):**
1. **Investigate API opportunities**
2. **Consider manual checking workflows**
3. **Implement hybrid automation**
4. **Partner with terminal operators**

---

## 📝 **Sample Test Output**

```
🚢 CS Shipping LCB - Vessel Tracking Test
═══════════════════════════════════════════

🚢 Testing Terminal C1C2 (Hutchison Ports)
📍 Vessel: WAN HAI 517 S093
🌐 URL: https://online.hutchisonports.co.th/hptpcs/...

📄 HTML Size: 89,234 bytes
✅ VESSEL FOUND!
🕒 ETA: 2025-07-25 14:30:00
📄 Raw Data Preview: ...vessel berth eta schedule...
🕐 Checked at: 2025-07-22 15:45:23
─────────────────────────────────────────────────────────

📊 VESSEL TRACKING TEST SUMMARY
═══════════════════════════════════════════

C1C2   Hutchison Ports ✅  ✅  2025-07-25 14:30:00
B4     TIPS            ✅  ❌  Not found
B5C3   LCIT            ❌  ❌  Not found
B3     ESCO            ✅  ✅  2025-07-26 08:00:00
A0B1   LCB1            ✅  ❌  Not found
B2     ECTT            ❌  ❌  Not found

📈 Statistics:
  • Total terminals tested: 6
  • Successful requests: 4/6
  • Vessels found: 2/6
  • ETAs extracted: 2/6

📊 Success Rates:
  • Request success: 66.7%
  • Vessel detection: 33.3%
  • ETA extraction: 33.3%

⚠️  Vessel detection works, but ETA parsing needs improvement.

💡 Next Steps:
  1. Focus on terminals with high success rates
  2. Develop specific parsers for each terminal
  3. Consider API integrations where available
  4. Add this to your Laravel application
```

---

## 🎉 **Integration into Phase 2**

Once you have good test results, this becomes the foundation for:
- **Automated daily vessel checking**
- **Customer email notifications**
- **Real-time dashboard updates**
- **Alert system for delays**

**Files Ready for Integration:**
- `app/Services/VesselTrackingService.php` - Main service
- `app/Console/Commands/TestVesselTracking.php` - Artisan command
- `routes/web.php` - Web interface
- `resources/views/vessel-test.blade.php` - Test UI

**Happy testing! 🚀**