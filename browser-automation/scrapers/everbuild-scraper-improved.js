const puppeteer = require('puppeteer');
const axios = require('axios');
const winston = require('winston');

// Configure logging - FIXED: logs go to stderr, not stdout
const logger = winston.createLogger({
  level: 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.printf(({ timestamp, level, message }) => {
      return `${timestamp} [${level.toUpperCase()}]: ${message}`;
    })
  ),
  transports: [
    new winston.transports.Console({
      stderrLevels: ['error', 'warn', 'info', 'debug']
    }),
    new winston.transports.File({ filename: 'everbuild-scraping.log' })
  ]
});

class ImprovedEverbuildVesselScraper {
  constructor(laravelApiUrl = 'http://localhost:8003') {
    this.apiUrl = laravelApiUrl;
    this.browser = null;
    this.page = null;
    this.baseUrl = 'https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp';
  }

  async initialize() {
    logger.info('🚀 Initializing Improved Everbuild Browser Scraper...');
    
    this.browser = await puppeteer.launch({
      headless: true, // Set to false for debugging
      defaultViewport: { width: 1920, height: 1080 },
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu'
      ]
    });
    
    this.page = await this.browser.newPage();
    await this.page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    await this.page.setViewport({ width: 1920, height: 1080 });
    
    logger.info('✅ Browser initialized successfully');
  }

  async delay(ms) {
    const variation = ms * 0.3;
    const actualDelay = ms + (Math.random() * variation - variation / 2);
    await new Promise(resolve => setTimeout(resolve, Math.max(50, actualDelay)));
  }
  
  async scrapeVesselSchedule(vesselName = 'EVER BUILD') {
    try {
      logger.info(`🔍 Scraping Everbuild schedule for vessel: ${vesselName}`);
      
      // Navigate to Shipment Link vessel schedule
      await this.page.goto(this.baseUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });
      
      logger.info('📄 Page loaded successfully');
      
      // Wait for dropdown to be available
      await this.page.waitForSelector('select', { timeout: 10000 });
      await this.delay(2000);
      
      // Find and select vessel from dropdown
      const vesselSelected = await this.page.evaluate((targetVessel) => {
        const dropdown = document.querySelector('select');
        if (!dropdown) return { success: false, error: 'No dropdown found' };
        
        const options = Array.from(dropdown.options);
        
        // Find EVER BUILD option (without voyage code)
        const targetOption = options.find(opt => 
          opt.text.trim().toUpperCase().includes('EVER BUILD')
        );
        
        if (targetOption) {
          dropdown.value = targetOption.value;
          
          // Trigger change events
          dropdown.dispatchEvent(new Event('change', { bubbles: true }));
          dropdown.dispatchEvent(new Event('input', { bubbles: true }));
          
          return {
            success: true,
            selectedText: targetOption.text.trim(),
            selectedValue: targetOption.value
          };
        }
        
        return { 
          success: false, 
          error: 'EVER BUILD not found',
          availableOptions: options.slice(0, 10).map(opt => opt.text.trim())
        };
        
      }, vesselName);
      
      if (!vesselSelected.success) {
        logger.warn(`⚠️ Could not select vessel: ${vesselSelected.error}`);
        return {
          success: false,
          message: vesselSelected.error,
          available_vessels: vesselSelected.availableOptions,
          terminal: 'Everbuild',
          scraped_at: new Date().toISOString()
        };
      }
      
      logger.info(`✅ Selected vessel: ${vesselSelected.selectedText}`);
      await this.delay(1000);
      
      // Click search/submit button
      const searchClicked = await this.clickSearchButton();
      if (!searchClicked) {
        logger.warn('⚠️ Could not find search button, trying Enter key...');
        await this.page.keyboard.press('Enter');
      }
      
      // Wait for results to load - look for content changes
      logger.info('⏳ Waiting for search results...');
      await this.delay(3000);
      
      // Try to wait for schedule table or results to appear
      try {
        await this.page.waitForFunction(() => {
          // Look for tables that might contain schedule data
          const tables = document.querySelectorAll('table');
          const hasDataTables = Array.from(tables).some(table => {
            const rows = table.querySelectorAll('tr');
            return rows.length > 3; // More than just headers
          });
          
          // Also look for any content that suggests results loaded
          const bodyText = document.body.textContent || '';
          const hasScheduleContent = bodyText.includes('ETA') || 
                                   bodyText.includes('ETD') || 
                                   bodyText.includes('Arrival') ||
                                   bodyText.includes('Departure') ||
                                   bodyText.includes('Port') ||
                                   bodyText.includes('Terminal');
          
          return hasDataTables || hasScheduleContent;
        }, { timeout: 8000 });
        
        logger.info('📊 Search results detected, extracting data...');
      } catch (waitError) {
        logger.warn('⚠️ Timeout waiting for results, proceeding with extraction...');
      }
      
      // Extract vessel schedule data
      const scheduleData = await this.extractScheduleData(vesselSelected.selectedText);
      
      if (scheduleData.success) {
        logger.info(`✅ Successfully extracted schedule for ${vesselSelected.selectedText}`);
        return scheduleData;
      } else {
        logger.warn(`⚠️ No schedule data found for ${vesselSelected.selectedText}`);
        
        // Take screenshot for debugging
        try {
          await this.page.screenshot({ path: `everbuild-no-schedule-${Date.now()}.png` });
          logger.info('📸 Debug screenshot saved');
        } catch (screenshotError) {
          // Ignore screenshot errors
        }
        
        return {
          success: false,
          message: 'No schedule data found after search',
          vessel_selected: vesselSelected.selectedText,
          terminal: 'Everbuild',
          scraped_at: new Date().toISOString()
        };
      }
      
    } catch (error) {
      logger.error(`❌ Error scraping Everbuild: ${error.message}`);
      
      // Take error screenshot
      try {
        await this.page.screenshot({ path: `everbuild-error-${Date.now()}.png` });
        logger.info('📸 Error screenshot saved');
      } catch (screenshotError) {
        // Ignore screenshot errors
      }
      
      return {
        success: false,
        error: error.message,
        terminal: 'Everbuild',
        scraped_at: new Date().toISOString()
      };
    }
  }
  
  async clickSearchButton() {
    const buttonSelectors = [
      'input[type="submit"]',
      'button[type="submit"]', 
      'input[value*="Search"]',
      'input[value*="Submit"]',
      'input[value*="GO"]',
      'button[onclick*="submit"]',
      'input[onclick*="submit"]'
    ];
    
    for (const selector of buttonSelectors) {
      try {
        const button = await this.page.$(selector);
        if (button) {
          const isVisible = await button.boundingBox();
          if (isVisible) {
            await button.click();
            logger.info(`🔍 Clicked search button: ${selector}`);
            return true;
          }
        }
      } catch (error) {
        continue;
      }
    }
    
    // Try to find buttons by text content
    try {
      const buttonFound = await this.page.evaluate(() => {
        const allButtons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
        for (const btn of allButtons) {
          const text = btn.textContent || btn.value || '';
          if (text.toLowerCase().includes('search') || 
              text.toLowerCase().includes('submit') ||
              text.toLowerCase().includes('go')) {
            btn.click();
            return true;
          }
        }
        return false;
      });
      
      if (buttonFound) {
        logger.info('🔍 Clicked search button by text content');
        return true;
      }
    } catch (error) {
      logger.warn('Could not find search button by text');
    }
    
    return false;
  }
  
  async extractScheduleData(vesselName) {
    return await this.page.evaluate((vessel) => {
      console.log(`🔍 Extracting schedule data for: ${vessel}`);
      
      // Strategy 1: Look for schedule tables
      const tables = document.querySelectorAll('table');
      console.log(`Found ${tables.length} tables on page`);
      
      for (let tableIndex = 0; tableIndex < tables.length; tableIndex++) {
        const table = tables[tableIndex];
        const rows = table.querySelectorAll('tr');
        
        if (rows.length < 2) continue; // Skip empty tables
        
        console.log(`Checking table ${tableIndex + 1} with ${rows.length} rows`);
        
        // Check if this table contains schedule-related headers
        const headerRow = rows[0];
        const headerCells = headerRow.querySelectorAll('th, td');
        const headers = Array.from(headerCells).map(cell => cell.textContent.trim().toLowerCase());
        
        const hasScheduleHeaders = headers.some(header => 
          header.includes('eta') || header.includes('etd') || 
          header.includes('arrival') || header.includes('departure') ||
          header.includes('port') || header.includes('terminal') ||
          header.includes('vessel') || header.includes('voyage')
        );
        
        if (hasScheduleHeaders || rows.length > 5) {
          console.log(`Table ${tableIndex + 1} looks like schedule table`);
          console.log('Headers:', headers);
          
          // Search for vessel data in this table
          for (let rowIndex = 1; rowIndex < rows.length; rowIndex++) {
            const row = rows[rowIndex];
            const cells = row.querySelectorAll('td, th');
            
            if (cells.length < 2) continue;
            
            const rowData = Array.from(cells).map(cell => cell.textContent.trim());
            console.log(`Row ${rowIndex}:`, rowData);
            
            // Check if this row contains our vessel
            const hasVessel = rowData.some(cell => {
              const cellUpper = cell.toUpperCase();
              return cellUpper.includes('EVER BUILD') || 
                     cellUpper.includes(vessel.toUpperCase());
            });
            
            if (hasVessel) {
              console.log('✅ Found vessel in schedule table!');
              
              // Extract schedule information
              const scheduleInfo = {
                vessel_name: vessel,
                table_index: tableIndex,
                row_index: rowIndex,
                headers: headers,
                raw_data: rowData,
                extraction_method: 'schedule_table'
              };
              
              // Try to map data to schedule fields
              for (let i = 0; i < rowData.length; i++) {
                const cellValue = rowData[i].trim();
                const header = headers[i] || `column_${i}`;
                
                // Look for dates (various formats)
                if (cellValue.match(/\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}/) ||
                    cellValue.match(/\d{2,4}-\d{1,2}-\d{1,2}/) ||
                    cellValue.match(/\d{1,2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i)) {
                  
                  if (header.includes('eta') || header.includes('arrival') || (!scheduleInfo.eta && i > 1)) {
                    scheduleInfo.eta = cellValue;
                  } else if (header.includes('etd') || header.includes('departure')) {
                    scheduleInfo.etd = cellValue;
                  }
                }
                
                // Look for voyage codes
                if (cellValue.match(/\d{3,4}[SN]?/) && !cellValue.includes('/') && i !== 0) {
                  if (!scheduleInfo.voyage_code) {
                    scheduleInfo.voyage_code = cellValue;
                  }
                }
                
                // Look for ports/terminals
                if (header.includes('port') || header.includes('terminal') || 
                    (cellValue.length < 20 && cellValue.match(/^[A-Z\s]+$/))) {
                  if (!scheduleInfo.port) {
                    scheduleInfo.port = cellValue;
                  }
                }
              }
              
              return {
                success: true,
                terminal: 'Everbuild',
                vessel_name: vessel,
                eta: scheduleInfo.eta || null,
                etd: scheduleInfo.etd || null,
                voyage_code: scheduleInfo.voyage_code || null,
                port: scheduleInfo.port || null,
                raw_schedule_data: scheduleInfo,
                scraped_at: new Date().toISOString(),
                source: 'shipmentlink_improved_extraction'
              };
            }
          }
        }
      }
      
      // Strategy 2: Look for schedule information in page text
      console.log('No table data found, searching page content...');
      
      const pageText = document.body.textContent || '';
      const lines = pageText.split('\n').map(line => line.trim()).filter(line => line.length > 0);
      
      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        if (line.toUpperCase().includes('EVER BUILD') || line.toUpperCase().includes(vessel.toUpperCase())) {
          console.log(`Found vessel reference in line: ${line}`);
          
          // Look for dates in surrounding lines
          const contextLines = lines.slice(Math.max(0, i - 3), i + 4);
          const datePattern = /\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}|\d{2,4}-\d{1,2}-\d{1,2}/;
          
          for (const contextLine of contextLines) {
            const dateMatch = contextLine.match(datePattern);
            if (dateMatch) {
              return {
                success: true,
                terminal: 'Everbuild',
                vessel_name: vessel,
                eta: dateMatch[0],
                extraction_method: 'page_text_search',
                context_lines: contextLines,
                scraped_at: new Date().toISOString(),
                source: 'shipmentlink_text_extraction'
              };
            }
          }
          
          // Return partial success even without dates
          return {
            success: true,
            terminal: 'Everbuild',
            vessel_name: vessel,
            eta: null,
            extraction_method: 'vessel_found_no_schedule',
            found_line: line,
            scraped_at: new Date().toISOString(),
            source: 'shipmentlink_vessel_confirmation'
          };
        }
      }
      
      // No vessel data found
      console.log('❌ No vessel schedule data found on page');
      return {
        success: false,
        message: 'No schedule data found for vessel',
        page_tables: tables.length,
        page_content_length: pageText.length,
        searched_vessel: vessel
      };
      
    }, vesselName);
  }
  
  async sendToLaravel(data) {
    try {
      logger.info('📤 Sending data to Laravel API...');
      
      const response = await axios.post(`${this.apiUrl}/api/vessel-update`, data, {
        headers: {
          'Content-Type': 'application/json',
          'User-Agent': 'CS-Shipping-Browser-Bot/1.0'
        },
        timeout: 30000
      });
      
      logger.info(`✅ Laravel API response: ${response.status} ${response.statusText}`);
      return response.data;
      
    } catch (error) {
      logger.error(`❌ Failed to send data to Laravel: ${error.message}`);
      throw error;
    }
  }
  
  async cleanup() {
    if (this.browser) {
      await this.browser.close();
      logger.info('🧹 Browser closed');
    }
  }
}

// Main function for direct execution
async function main() {
  const vesselName = process.argv[2] || 'EVER BUILD';
  const scraper = new ImprovedEverbuildVesselScraper();
  
  try {
    await scraper.initialize();
    const result = await scraper.scrapeVesselSchedule(vesselName);
    
    // Output clean JSON to stdout
    console.log(JSON.stringify(result, null, 2));
    
    await scraper.cleanup();
    process.exit(result.success ? 0 : 1);
    
  } catch (error) {
    const errorResult = {
      success: false,
      error: error.message,
      terminal: 'Everbuild',
      scraped_at: new Date().toISOString()
    };
    
    console.log(JSON.stringify(errorResult, null, 2));
    await scraper.cleanup();
    process.exit(1);
  }
}

// Run if called directly
if (require.main === module) {
  main().catch(error => {
    console.log(JSON.stringify({
      success: false,
      error: error.message,
      terminal: 'Everbuild',
      scraped_at: new Date().toISOString()
    }, null, 2));
    process.exit(1);
  });
}

module.exports = { ImprovedEverbuildVesselScraper };
