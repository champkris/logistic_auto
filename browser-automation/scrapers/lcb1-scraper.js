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
    // Console logs go to stderr to avoid contaminating JSON output
    new winston.transports.Console({
      stderrLevels: ['error', 'warn', 'info', 'debug']
    }),
    new winston.transports.File({ filename: 'vessel-scraping.log' })
  ]
});

class LCB1VesselScraper {
  constructor(laravelApiUrl = 'http://localhost:8003') {
    this.apiUrl = laravelApiUrl;
    this.browser = null;
    this.page = null;
  }

  async initialize() {
    logger.info('🚀 Initializing LCB1 Browser Scraper...');
    
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
        '--disable-gpu',
        '--disable-blink-features=AutomationControlled',
        '--disable-features=VizDisplayCompositor'
      ]
    });
    
    this.page = await this.browser.newPage();
    
    // Enhanced anti-detection measures
    await this.page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    await this.page.setViewport({ width: 1920, height: 1080 });
    
    // Remove webdriver traces
    await this.page.evaluateOnNewDocument(() => {
      Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
    });
    
    // Add human-like mouse movements
    await this.page.evaluateOnNewDocument(() => {
      window.humanClick = async (element) => {
        const box = element.getBoundingClientRect();
        const x = box.left + box.width * (0.3 + Math.random() * 0.4);
        const y = box.top + box.height * (0.3 + Math.random() * 0.4);
        
        // Simulate hover first
        element.dispatchEvent(new MouseEvent('mouseover', { bubbles: true, clientX: x, clientY: y }));
        await new Promise(resolve => setTimeout(resolve, Math.random() * 200 + 100));
        
        // Then click
        element.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: x, clientY: y }));
      };
    });
    
    logger.info('✅ Browser initialized successfully with enhanced interaction capabilities');
  }

  async humanLikeMouseInteraction(selector, interactionType = 'click') {
    try {
      const element = await this.page.$(selector);
      if (!element) return false;
      
      // Get element position
      const boundingBox = await element.boundingBox();
      if (!boundingBox) return false;
      
      // Calculate random position within element
      const x = boundingBox.x + boundingBox.width * (0.3 + Math.random() * 0.4);
      const y = boundingBox.y + boundingBox.height * (0.3 + Math.random() * 0.4);
      
      // Move mouse to element with human-like speed
      await this.page.mouse.move(x, y, { steps: 10 });
      await this.delay(100 + Math.random() * 200);
      
      switch (interactionType) {
        case 'hover':
          // Just hover without clicking
          logger.info(`🖱️ Hovering over element: ${selector}`);
          break;
          
        case 'click':
          await this.page.mouse.click(x, y);
          logger.info(`🖱️ Clicked element: ${selector}`);
          break;
          
        case 'doubleClick':
          await this.page.mouse.click(x, y, { clickCount: 2 });
          logger.info(`🖱️ Double-clicked element: ${selector}`);
          break;
          
        case 'rightClick':
          await this.page.mouse.click(x, y, { button: 'right' });
          logger.info(`🖱️ Right-clicked element: ${selector}`);
          break;
      }
      
      await this.delay(100 + Math.random() * 200);
      return true;
      
    } catch (error) {
      logger.warn(`Failed ${interactionType} interaction with ${selector}: ${error.message}`);
      return false;
    }
  }
  
  // Human-like delays
  async delay(ms) {
    const variation = ms * 0.3; // Add 30% variation
    const actualDelay = ms + (Math.random() * variation - variation / 2);
    await new Promise(resolve => setTimeout(resolve, Math.max(50, actualDelay)));
  }
  
  // Advanced dropdown interaction for custom components
  async interactWithCustomDropdown(dropdownSelector, optionText, requiresHover = false) {
    try {
      logger.info(`🔽 Interacting with custom dropdown: ${dropdownSelector}`);
      
      // First, hover over dropdown if required (common for custom components)
      if (requiresHover) {
        await this.humanLikeMouseInteraction(dropdownSelector, 'hover');
        await this.delay(300);
      }
      
      // Click to open dropdown
      await this.humanLikeMouseInteraction(dropdownSelector, 'click');
      await this.delay(500);
      
      // Wait for dropdown options to appear
      await this.page.waitForFunction(() => {
        // Look for common dropdown option patterns
        const selectors = ['li', '.option', '.dropdown-item', 'div[role="option"]'];
        return selectors.some(sel => document.querySelectorAll(sel).length > 0);
      }, { timeout: 5000 });
      
      // Find and click the desired option
      const optionClicked = await this.page.evaluate((targetText) => {
        const selectors = ['li', '.option', '.dropdown-item', 'div[role="option"]', 'a'];
        
        for (const selector of selectors) {
          const options = document.querySelectorAll(selector);
          for (const option of options) {
            if (option.textContent.trim().includes(targetText)) {
              option.click();
              return true;
            }
          }
        }
        return false;
      }, optionText);
      
      if (optionClicked) {
        logger.info(`✅ Selected option: ${optionText}`);
        return true;
      } else {
        logger.warn(`⚠️ Could not find option: ${optionText}`);
        return false;
      }
      
    } catch (error) {
      logger.error(`❌ Custom dropdown interaction failed: ${error.message}`);
      return false;
    }
  }
  
  async scrapeVesselSchedule(vesselName = 'MARSA PRIDE') {
    try {
      logger.info(`🔍 Scraping LCB1 schedule for vessel: ${vesselName}`);
      
      // Navigate to LCB1 berth schedule
      await this.page.goto('https://www.lcb1.com/BerthSchedule', {
        waitUntil: 'networkidle2',
        timeout: 30000
      });
      
      logger.info('📄 Page loaded, looking for vessel dropdown...');
      
      // Wait for the vessel dropdown to appear
      await this.page.waitForSelector('select', { timeout: 30000 });
      
      // Find all select elements and identify the vessel dropdown
      const dropdowns = await this.page.$$('select');
      logger.info(`📋 Found ${dropdowns.length} dropdown(s) on page`);
      
      let vesselDropdown = null;
      for (let i = 0; i < dropdowns.length; i++) {
        const dropdown = dropdowns[i];
        
        // Check if this dropdown contains vessel names
        const options = await dropdown.$$eval('option', options => 
          options.map(option => ({
            value: option.value,
            text: option.textContent.trim()
          }))
        );
        
        // Look for dropdown that contains MARSA PRIDE or similar vessel names
        const hasVessels = options.some(option => 
          option.text.includes('MARSA') || 
          option.text.includes('PRIDE') ||
          option.text.includes('MAERSK') ||
          option.text.length > 5 // Vessel names are typically longer
        );
        
        if (hasVessels) {
          vesselDropdown = dropdown;
          logger.info(`✅ Found vessel dropdown with ${options.length} options`);
          
          // Log available vessels for debugging
          const vesselOptions = options.filter(opt => opt.text.length > 3);
          logger.info(`🚢 Available vessels: ${vesselOptions.map(v => v.text).join(', ')}`);
          break;
        }
      }
      
      if (!vesselDropdown) {
        throw new Error('Could not find vessel dropdown on page');
      }
      
      // Select the target vessel with enhanced selection
      await this.page.evaluate((vesselName) => {
        const select = document.querySelector('select');
        const options = Array.from(select.options);
        const targetOption = options.find(opt => opt.text.includes(vesselName));
        
        if (targetOption) {
          select.value = targetOption.value;
          // Trigger change event to ensure proper selection
          const changeEvent = new Event('change', { bubbles: true });
          select.dispatchEvent(changeEvent);
          console.log(`Selected vessel: ${vesselName} with value: ${targetOption.value}`);
          return true;
        }
        
        console.log(`Vessel ${vesselName} not found in options`);
        return false;
      }, vesselName);
      
      logger.info(`✅ Selected vessel: ${vesselName}`);
      
      // Look for and click the search button with multiple strategies
      let searchClicked = false;
      
      // Strategy 1: Try specific button types
      const buttonSelectors = [
        'button[type="submit"]',
        'input[type="submit"]',
        'button[name*="search"]',
        'button[id*="search"]',
        'input[value*="Search"]',
        'input[value*="search"]'
      ];
      
      for (const selector of buttonSelectors) {
        try {
          const element = await this.page.$(selector);
          if (element) {
            await element.click();
            logger.info(`🔍 Clicked search button using selector: ${selector}`);
            searchClicked = true;
            break;
          }
        } catch (error) {
          continue;
        }
      }
      
      // Strategy 2: Find buttons by text content
      if (!searchClicked) {
        try {
          const searchButton = await this.page.evaluateHandle(() => {
            const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
            for (const button of buttons) {
              const text = button.textContent || button.value || '';
              if (text.toLowerCase().includes('search') || 
                  text.toLowerCase().includes('ค้นหา') ||
                  text.toLowerCase().includes('submit')) {
                return button;
              }
            }
            return null;
          });
          
          if (searchButton) {
            await searchButton.click();
            logger.info('🔍 Clicked search button by text content');
            searchClicked = true;
          }
        } catch (error) {
          logger.warn('Could not find search button by text content');
        }
      }
      
      // Strategy 3: Try pressing Enter on the dropdown (common pattern)
      if (!searchClicked) {
        try {
          await this.page.focus('select');
          await this.page.keyboard.press('Enter');
          logger.info('🔍 Pressed Enter on dropdown to trigger search');
          searchClicked = true;
        } catch (error) {
          logger.warn('Could not trigger search with Enter key');
        }
      }
      
      // Strategy 4: Click any button as fallback
      if (!searchClicked) {
        try {
          await this.page.click('button, input[type="button"]');
          logger.info('🔍 Clicked fallback button');
          searchClicked = true;
        } catch (error) {
          logger.error('No clickable buttons found');
        }
      }
      
      // Wait specifically for the schedule table to load
      logger.info('⏳ Waiting for schedule table...');
      
      try {
        // Wait for the grid table with data to appear
        await this.page.waitForSelector('#grid tbody tr', { timeout: 20000 });
        logger.info('📊 Schedule table loaded successfully!');
      } catch (waitError) {
        logger.warn('⚠️ Grid table not found, trying fallback detection...');
        
        // Fallback: wait for any content that suggests schedule data loaded
        try {
          await this.page.waitForFunction(() => {
            const body = document.body.textContent;
            return (body.includes('MARSA PRIDE') && body.includes('528')) ||
                   body.includes('Voyage In') ||
                   body.includes('Berthing Time');
          }, { timeout: 15000 });
          logger.info('📊 Schedule data detected via fallback method');
        } catch (fallbackError) {
          logger.warn('⚠️ No schedule data detected after 35s total');
        }
      }
      
      // Extract schedule data with FIXED table parsing logic
      const scheduleData = await this.page.evaluate((targetVessel) => {
        console.log('🔍 Starting FIXED data extraction...');
        
        const results = [];
        
        // Strategy 1: Look specifically for the #grid table (primary method)
        const gridTable = document.querySelector('#grid');
        if (gridTable) {
          console.log('✅ Found #grid table');
          
          const rows = gridTable.querySelectorAll('tbody tr');
          console.log(`Found ${rows.length} data rows in grid table`);
          
          for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.querySelectorAll('td');
            
            if (cells.length >= 7) {
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              console.log(`Row ${i} data:`, rowData);
              
              // Check if this row contains our target vessel
              if (rowData[1] && rowData[1].toUpperCase().includes(targetVessel.toUpperCase())) {
                console.log('Found target vessel in grid table');
                
                // Map columns according to known structure: No, Vessel, Voyage In, Voyage Out, Berthing, Departure, Terminal
                const mapping = {
                  vessel_name: rowData[1],
                  voyage_in: rowData[2] ? rowData[2].trim() : null,
                  voyage_out: rowData[3] ? rowData[3].trim() : null,
                  berthing_time: rowData[4] ? rowData[4].trim() : null,
                  departure_time: rowData[5] ? rowData[5].trim() : null,
                  terminal: rowData[6] ? rowData[6].trim() : null,
                  raw_data: rowData,
                  table_columns: cells.length,
                  extraction_method: 'grid_table'
                };
                
                results.push(mapping);
                console.log('Grid table extraction successful:', mapping);
              }
            }
          }
          
          if (results.length > 0) {
            return results;
          }
        }
        
        // Strategy 2: Enhanced table search for any table containing the vessel
        console.log('⚠️ Grid table not found or no data, trying table search...');
        const tables = document.querySelectorAll('table');
        console.log(`Searching ${tables.length} tables for vessel data`);
        
        for (const table of tables) {
          const rows = table.querySelectorAll('tr');
          
          // Skip tables with too few rows
          if (rows.length < 2) continue;
          
          // Extract data rows
          for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].querySelectorAll('td, th');
            
            if (cells.length >= 4) {
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              
              // Find vessel name in any column
              const vesselColumnIndex = rowData.findIndex(cell => 
                cell.toUpperCase().includes(targetVessel.toUpperCase())
              );
              
              if (vesselColumnIndex >= 0) {
                console.log('Found vessel in table search:', rowData);
                
                // Try to intelligently map columns
                let mapping = {
                  vessel_name: rowData[vesselColumnIndex],
                  extraction_method: 'table_search',
                  raw_data: rowData,
                  table_columns: cells.length
                };
                
                // Look for voyage patterns (XXXs/XXXN)
                const voyageIn = rowData.find(cell => cell.match(/\d{3}S/));
                const voyageOut = rowData.find(cell => cell.match(/\d{3}N/));
                const terminal = rowData.find(cell => cell.match(/^[A-Z]\d+$/));
                const dates = rowData.filter(cell => cell.includes('/2025'));
                
                if (voyageIn) mapping.voyage_in = voyageIn.trim();
                if (voyageOut) mapping.voyage_out = voyageOut.trim(); 
                if (terminal) mapping.terminal = terminal.trim();
                if (dates.length >= 2) {
                  mapping.berthing_time = dates[0].trim();
                  mapping.departure_time = dates[1].trim();
                } else if (dates.length === 1) {
                  mapping.berthing_time = dates[0].trim();
                }
                
                results.push(mapping);
                console.log('Table search extraction successful:', mapping);
                break;
              }
            }
          }
          
          if (results.length > 0) break;
        }
        
        // Strategy 3: Fallback text search (last resort)
        if (results.length === 0) {
          console.log('No table data found, using text search fallback');
          const pageText = document.body.textContent;
          const lines = pageText.split('\n').map(line => line.trim()).filter(line => line.length > 0);
          
          for (const line of lines) {
            if (line.toUpperCase().includes(targetVessel.toUpperCase())) {
              results.push({
                vessel_name: targetVessel,
                raw_text: line,
                extraction_method: 'text_search_fallback',
                note: 'Extracted from page text, manual parsing needed'
              });
            }
          }
        }
        
        return results;
      }, vesselName);
      
      logger.info(`📊 Extracted ${scheduleData.length} schedule entries`);
      
      if (scheduleData.length > 0) {
        // Process and format the first matching result
        const schedule = scheduleData[0];
        
        const result = {
          success: true,
          terminal: 'LCB1',
          vessel_name: vesselName,
          voyage_code: schedule.voyage_in || null,
          voyage_out: schedule.voyage_out || null,
          eta: this.parseDateTime(schedule.berthing_time),
          etd: this.parseDateTime(schedule.departure_time),
          raw_data: schedule,
          scraped_at: new Date().toISOString(),
          source: 'lcb1_browser_automation'
        };
        
        logger.info(`✅ Successfully scraped ${vesselName}: ETA ${result.eta || 'N/A'}`);
        return result;
        
      } else {
        logger.warn(`⚠️ No schedule data found for ${vesselName}`);
        return {
          success: false,
          message: `Vessel ${vesselName} not found in current schedule`,
          terminal: 'LCB1',
          scraped_at: new Date().toISOString()
        };
      }
      
    } catch (error) {
      logger.error(`❌ Error scraping LCB1: ${error.message}`);
      
      // Take screenshot for debugging
      try {
        await this.page.screenshot({ path: `lcb1-error-${Date.now()}.png`, fullPage: true });
        logger.info('📸 Error screenshot saved');
      } catch (screenshotError) {
        logger.error('Failed to take error screenshot');
      }
      
      return {
        success: false,
        error: error.message,
        terminal: 'LCB1',
        scraped_at: new Date().toISOString()
      };
    }
  }
  
  parseDateTime(dateTimeString) {
    if (!dateTimeString) return null;
    
    // Handle various date formats found on LCB1
    // Examples: "22/07/2025 - 04:00", "22/07/2025", "04:00"
    
    const datePatterns = [
      // DD/MM/YYYY HH:MM or DD/MM/YYYY - HH:MM
      /(\d{1,2})\/(\d{1,2})\/(\d{4})\s*[-–]\s*(\d{1,2}):(\d{2})/,
      // DD/MM/YYYY HH:MM
      /(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})/,
      // DD/MM/YYYY only  
      /(\d{1,2})\/(\d{1,2})\/(\d{4})/
    ];
    
    for (const pattern of datePatterns) {
      const match = dateTimeString.match(pattern);
      if (match) {
        try {
          const day = parseInt(match[1]);
          const month = parseInt(match[2]) - 1; // JavaScript months are 0-indexed
          const year = parseInt(match[3]);
          const hour = match[4] ? parseInt(match[4]) : 0;
          const minute = match[5] ? parseInt(match[5]) : 0;
          
          const date = new Date(year, month, day, hour, minute);
          return date.toISOString().slice(0, 19).replace('T', ' '); // YYYY-MM-DD HH:MM:SS format
        } catch (parseError) {
          continue;
        }
      }
    }
    
    return null;
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

// Main function for direct execution - outputs clean JSON to stdout
async function main() {
  const scraper = new LCB1VesselScraper();
  
  try {
    await scraper.initialize();
    const result = await scraper.scrapeVesselSchedule('MARSA PRIDE');
    
    // ONLY clean JSON to stdout - logs go to stderr
    console.log(JSON.stringify(result, null, 2));
    
    await scraper.cleanup();
    process.exit(result.success ? 0 : 1);
    
  } catch (error) {
    const errorResult = {
      success: false,
      error: error.message,
      terminal: 'LCB1',
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
      terminal: 'LCB1',
      scraped_at: new Date().toISOString()
    }, null, 2));
    process.exit(1);
  });
}

module.exports = { LCB1VesselScraper };
