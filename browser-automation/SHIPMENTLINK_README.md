# ShipmentLink Vessel Scraper

## Overview
The ShipmentLink Vessel Scraper is designed to automate vessel schedule extraction from the ShipmentLink vessel tracking website at `https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp`.

## Key Features

### 🍪 Cookie Consent Handling
- Automatically detects and accepts cookie consent popups
- Supports multiple languages (English, Thai)
- Multiple detection strategies for various popup designs

### 🔽 Smart Dropdown Selection
- Intelligently identifies vessel name dropdowns
- Supports exact and partial vessel name matching
- Handles both standard HTML selects and custom dropdown components

### 🔍 Advanced Data Extraction
- Multi-strategy table parsing
- Intelligent column mapping based on headers
- Fallback text search if table parsing fails

### 🖱️ Human-like Interactions
- Randomized mouse movements and click positions
- Natural delays between actions
- Anti-detection measures

## Usage

### Direct Execution
```bash
# Run ShipmentLink scraper directly
node scrapers/shipmentlink-scraper.js

# Or via npm script
npm run shipmentlink
```

### Via Main Orchestrator
```bash
# Run only ShipmentLink scraper
node vessel-scraper.js shipmentlink

# Run all scrapers including ShipmentLink
node vessel-scraper.js test
```

### Test Script
```bash
# Run dedicated test
node test-shipmentlink.js
```

## Configuration

### Default Vessel Name
The scraper defaults to searching for "EVER BUILD" but can be configured:

```javascript
const scraper = new ShipmentLinkVesselScraper();
await scraper.initialize();
const result = await scraper.scrapeVesselSchedule('YOUR_VESSEL_NAME');
```

### Browser Settings
- **Headless Mode**: `true` (set to `false` for debugging)
- **Viewport**: 1920x1080
- **Anti-detection**: Enabled with WebDriver property removal

## Data Output Format

### Successful Response
```json
{
  "success": true,
  "terminal": "ShipmentLink",
  "vessel_name": "EVER BUILD",
  "voyage_code": "079S",
  "eta": "2025-07-28 14:00:00",
  "etd": "2025-07-30 18:00:00",
  "port": "Bangkok",
  "service": "AAE1",
  "raw_data": {
    "vessel_name": "EVER BUILD",
    "raw_data": ["EVER BUILD", "079S", "28/07/2025 14:00", "30/07/2025 18:00"],
    "table_headers": ["vessel", "voyage", "eta", "etd"],
    "extraction_method": "shipmentlink_table_search"
  },
  "scraped_at": "2025-07-26T10:30:45.123Z",
  "source": "shipmentlink_browser_automation"
}
```

### Error Response
```json
{
  "success": false,
  "error": "Vessel EVER BUILD not found in dropdown options",
  "terminal": "ShipmentLink",
  "scraped_at": "2025-07-26T10:30:45.123Z"
}
```

## Troubleshooting

### Common Issues

1. **Vessel Not Found in Dropdown**
   - Check vessel name spelling and format
   - The scraper supports partial matching
   - Verify vessel is actually scheduled on the website

2. **Cookie Popup Not Handled**
   - Screenshot saved automatically on errors
   - Check `shipmentlink-error-[timestamp].png` for visual debugging
   - May need to update cookie selectors

3. **Timeout Issues**
   - Default timeout is 30 seconds for page load
   - 20 seconds for results to appear
   - Increase timeouts if network is slow

### Debugging Mode
Set `headless: false` in the scraper initialization to see browser actions:

```javascript
this.browser = await puppeteer.launch({
  headless: false, // Enable for visual debugging
  // ... other options
});
```

## Website-Specific Implementation

### URL
`https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp`

### Workflow
1. Navigate to ShipmentLink vessel schedule page
2. Handle cookie consent popup if present
3. Wait for vessel dropdown to load
4. Select target vessel from dropdown
5. Click search/submit button
6. Wait for results table to load
7. Extract vessel schedule data
8. Parse and format results

### Data Mapping
The scraper intelligently maps table columns based on:
- **Header Text**: vessel, eta, etd, voyage, port, service
- **Content Patterns**: Date formats, voyage codes, vessel names
- **Position Logic**: Common shipping industry table layouts

## Integration with Laravel

### API Endpoint
Data is automatically sent to: `POST /api/vessel-update`

### Request Format
```json
{
  "success": true,
  "terminal": "ShipmentLink",
  "vessel_name": "EVER BUILD",
  "voyage_code": "079S",
  "eta": "2025-07-28 14:00:00",
  "etd": "2025-07-30 18:00:00",
  "scraped_at": "2025-07-26T10:30:45.123Z"
}
```

## Performance Notes

- **Average execution time**: 15-30 seconds
- **Memory usage**: ~200MB (Chromium browser)
- **Network dependency**: High (requires stable internet)
- **Error recovery**: Automatic screenshot capture on failure

## Future Enhancements

1. **Multi-vessel support**: Batch processing of multiple vessels
2. **Enhanced parsing**: Better handling of complex table layouts
3. **Retry logic**: Automatic retry on temporary failures
4. **Caching**: Store dropdown options to reduce page loads
5. **Proxy support**: Route through proxy servers if needed

## Maintenance

### Dependencies
- **puppeteer**: ^22.10.0 (for browser automation)
- **axios**: ^1.6.0 (for Laravel API calls)
- **winston**: ^3.11.0 (for logging)

### Update Schedule
- Test scraper monthly to ensure website compatibility
- Monitor for changes in dropdown structure or cookie popups
- Update selectors as needed when website changes

---

**Created**: July 2025  
**Last Updated**: July 2025  
**Version**: 1.0.0  
**Maintainer**: CS Shipping LCB Development Team