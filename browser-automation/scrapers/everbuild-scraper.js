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
    new winston.transports.File({ filename: 'everbuild-scraping.log' })
  ]
});

class EverbuildVesselScraper {
  constructor(laravelApiUrl = 'http://localhost:8003') {
    this.apiUrl = laravelApiUrl;
    this.browser = null;
    this.page = null;
    this.baseUrl = 'https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp';
  }

  async initialize() {
    logger.info('🚀 Initializing Everbuild Browser Scraper...');
    
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
          logger.info(`🖱️ Hovering over element: ${selector}`);
          break;
          
        case 'click':
          await this.page.mouse.click(x, y);
          logger.info(`🖱️ Clicked element: ${selector}`);
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
  
  async scrapeVesselSchedule(vesselName = 'EVER BUILD 0815-079S') {
    try {
      logger.info(`🔍 Scraping Everbuild schedule for vessel: ${vesselName}`);
      
      // Navigate to Everbuild vessel schedule
      await this.page.goto(this.baseUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });
      
      logger.info('📄 Page loaded, analyzing page structure...');
      
      // Wait for page content to load
      await this.page.waitForSelector('body', { timeout: 10000 });
      
      // Check if this is a login page or requires authentication
      const pageTitle = await this.page.title();
      logger.info(`📋 Page title: ${pageTitle}`);
      
      // Look for vessel dropdown - enhanced detection
      await this.delay(3000); // Give page time to fully load
      
      logger.info('🔍 Looking for vessel dropdown...');
      
      // Try multiple dropdown selectors
      const dropdownSelectors = [
        'select[name="vesselName"]',
        'select[name="vessel"]', 
        'select[id="vesselName"]',
        'select[id="vessel"]',
        'select[name*="vessel"]',
        'select[id*="vessel"]',
        'select' // fallback to any select
      ];
      
      let vesselDropdown = null;
      let dropdownSelector = null;
      
      for (const selector of dropdownSelectors) {
        try {
          const element = await this.page.$(selector);
          if (element) {
            // Check if this dropdown has vessel options
            const options = await element.$$eval('option', options => 
              options.map(opt => opt.textContent.trim())
            );
            
            logger.info(`📋 Found dropdown with selector ${selector}, ${options.length} options`);
            
            // Look for EVER BUILD or similar vessel names in options
            const hasVessels = options.some(opt => 
              opt.includes('EVER BUILD') || 
              opt.includes('EVER') || 
              opt.length > 10 // Vessel names are typically longer
            );
            
            if (hasVessels) {
              vesselDropdown = element;
              dropdownSelector = selector;
              logger.info(`✅ Found vessel dropdown: ${selector}`);
              logger.info(`🚢 Sample vessels: ${options.slice(0, 10).join(', ')}...`);
              break;
            }
          }
        } catch (error) {
          continue;
        }
      }
      
      if (!vesselDropdown) {
        throw new Error('Could not find vessel dropdown on page');
      }
      
      // Select the target vessel with enhanced selection
      logger.info(`🔍 Searching for vessel: ${vesselName}`);
      
      const selectionSuccess = await this.page.evaluate((selector, targetVessel) => {
        const dropdown = document.querySelector(selector);
        if (!dropdown) return false;
        
        const options = Array.from(dropdown.options);
        console.log(`Looking for "${targetVessel}" in ${options.length} options`);
        
        // Try exact match first
        let targetOption = options.find(opt => 
          opt.text.trim().toUpperCase() === targetVessel.toUpperCase()
        );
        
        // If no exact match, try partial match for "EVER BUILD"
        if (!targetOption) {
          targetOption = options.find(opt => 
            opt.text.toUpperCase().includes('EVER BUILD')
          );
        }
        
        if (targetOption) {
          dropdown.value = targetOption.value;
          
          // Trigger change events
          const changeEvent = new Event('change', { bubbles: true });
          dropdown.dispatchEvent(changeEvent);
          
          const inputEvent = new Event('input', { bubbles: true });
          dropdown.dispatchEvent(inputEvent);
          
          console.log(`✅ Selected vessel: "${targetOption.text}" with value: "${targetOption.value}"`);
          return {
            success: true,
            selectedText: targetOption.text,
            selectedValue: targetOption.value
          };
        }
        
        console.log(`❌ Vessel "${targetVessel}" not found in dropdown options`);
        console.log('Available options:', options.map(opt => opt.text.trim()).join(', '));
        return { success: false, availableOptions: options.map(opt => opt.text.trim()) };
        
      }, dropdownSelector, vesselName);
      
      if (!selectionSuccess.success) {
        logger.warn(`⚠️ Vessel ${vesselName} not found. Available vessels: ${selectionSuccess.availableOptions?.slice(0, 10).join(', ')}...`);
        
        // Return partial success with available vessels for debugging
        return {
          success: false,
          message: `Vessel ${vesselName} not found in dropdown`,
          terminal: 'Everbuild',
          available_vessels: selectionSuccess.availableOptions?.slice(0, 20),
          scraped_at: new Date().toISOString()
        };
      }
      
      logger.info(`✅ Selected vessel: ${selectionSuccess.selectedText}`);
      
      // Look for and click search/submit button
      await this.delay(1000);
      
      const searchClicked = await this.clickSearchButton();
      
      if (!searchClicked) {
        logger.warn('⚠️ Could not find search button, trying form submission...');
        // Try pressing Enter as fallback
        await this.page.keyboard.press('Enter');
      }
      
      // Wait for results to load
      logger.info('⏳ Waiting for search results...');
      await this.delay(5000);
      
      // Try to wait for results table or content to appear
      try {
        await this.page.waitForFunction(() => {
          // Look for table rows, schedule data, or loading completion
          const tables = document.querySelectorAll('table');
          const rows = document.querySelectorAll('tr');
          return tables.length > 1 || rows.length > 5;
        }, { timeout: 10000 });
        
        logger.info('📊 Results loaded, extracting vessel data...');
      } catch (waitError) {
        logger.warn('⚠️ Timeout waiting for results, proceeding with current page content...');
      }
      
      // Extract vessel data from results
      const vesselData = await this.extractVesselDataFromPage(vesselName);
      
      if (vesselData.length > 0) {
        const result = this.formatResult(vesselData[0], vesselName);
        logger.info(`✅ Successfully scraped ${vesselName}: ETA ${result.eta || 'N/A'}`);
        return result;
      } else {
        logger.warn(`⚠️ No schedule data found for ${vesselName} after search`);
        
        // Take screenshot for debugging
        try {
          await this.page.screenshot({ path: `everbuild-no-data-${Date.now()}.png`, fullPage: true });
          logger.info('📸 No-data screenshot saved for debugging');
        } catch (screenshotError) {
          // Ignore screenshot errors
        }
        
        return {
          success: false,
          message: `No schedule data found for ${vesselName} after search`,
          terminal: 'Everbuild',
          search_performed: true,
          vessel_selected: selectionSuccess.selectedText,
          scraped_at: new Date().toISOString()
        };
      }
      
    } catch (error) {
      logger.error(`❌ Error scraping Everbuild: ${error.message}`);
      
      // Take screenshot for debugging
      try {
        await this.page.screenshot({ path: `everbuild-error-${Date.now()}.png`, fullPage: true });
        logger.info('📸 Error screenshot saved');
      } catch (screenshotError) {
        logger.error('Failed to take error screenshot');
      }
      
      return {
        success: false,
        error: error.message,
        terminal: 'Everbuild',
        scraped_at: new Date().toISOString()
      };
    }
  }
  
  async selectVesselFromDropdown(dropdown, vesselName) {
    try {
      // Get all options from dropdown
      const options = await dropdown.$$eval('option', options => 
        options.map(option => ({
          value: option.value,
          text: option.textContent.trim()
        }))
      );
      
      logger.info(`📋 Found ${options.length} options in dropdown`);
      
      // Find vessel option
      const targetOption = options.find(opt => 
        opt.text.toUpperCase().includes(vesselName.toUpperCase())
      );
      
      if (targetOption) {
        await this.page.select(dropdown, targetOption.value);
        logger.info(`✅ Selected vessel: ${targetOption.text}`);
        return true;
      } else {
        logger.warn(`⚠️ Vessel ${vesselName} not found in dropdown options`);
        return false;
      }
      
    } catch (error) {
      logger.error(`❌ Error selecting from dropdown: ${error.message}`);
      return false;
    }
  }
  
  async clickSearchButton() {
    const buttonSelectors = [
      'button[type="submit"]',
      'input[type="submit"]',
      'button[name*="search"]',
      'button[id*="search"]',
      'input[value*="Search"]',
      'input[value*="Submit"]',
      'input[value*="GO"]',
      'input[value*="Find"]',
      'button[value*="Search"]',
      'button[value*="Submit"]',
      'button[onclick*="submit"]',
      'input[onclick*="submit"]'
    ];
    
    // Try specific button selectors first
    for (const selector of buttonSelectors) {
      try {
        const button = await this.page.$(selector);
        if (button) {
          // Check if button is visible and enabled
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
      const searchButton = await this.page.evaluateHandle(() => {
        const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
        for (const button of buttons) {
          const text = button.textContent || button.value || '';
          if (text.toLowerCase().includes('search') || 
              text.toLowerCase().includes('submit') ||
              text.toLowerCase().includes('find') ||
              text.toLowerCase().includes('go')) {
            return button;
          }
        }
        return null;
      });
      
      if (searchButton) {
        await searchButton.click();
        logger.info('🔍 Clicked search button by text content');
        return true;
      }
    } catch (error) {
      logger.warn('Could not find search button by text content');
    }
    
    // Try to submit any form on the page
    try {
      const formSubmitted = await this.page.evaluate(() => {
        const forms = document.querySelectorAll('form');
        for (const form of forms) {
          try {
            form.submit();
            return true;
          } catch (e) {
            continue;
          }
        }
        return false;
      });
      
      if (formSubmitted) {
        logger.info('🔍 Submitted form directly');
        return true;
      }
    } catch (error) {
      logger.warn('Could not submit form directly');
    }
    
    // Last resort: try pressing Enter
    try {
      await this.page.keyboard.press('Enter');
      logger.info('🔍 Pressed Enter to trigger search');
      return true;
    } catch (error) {
      logger.warn('Could not press Enter');
      return false;
    }
  }
  
  async extractVesselDataFromPage(targetVessel) {
    return await this.page.evaluate((vesselName) => {
      console.log('🔍 Starting enhanced Everbuild data extraction...');
      
      const results = [];
      
      // Strategy 1: Look for shipmentlink-specific table structures
      const tables = document.querySelectorAll('table');
      console.log(`Searching ${tables.length} tables for vessel data`);
      
      for (const table of tables) {
        // Look for schedule tables specifically
        const hasScheduleHeaders = Array.from(table.querySelectorAll('th, td')).some(cell => {
          const text = cell.textContent.toLowerCase();
          return text.includes('vessel') || text.includes('voyage') || 
                 text.includes('eta') || text.includes('etd') || 
                 text.includes('departure') || text.includes('arrival') ||
                 text.includes('schedule') || text.includes('port');
        });
        
        if (hasScheduleHeaders) {
          console.log('Found potential schedule table');
          
          const rows = table.querySelectorAll('tr');
          
          for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].querySelectorAll('td, th');
            
            if (cells.length >= 4) {
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              
              // Find vessel name in any column (more flexible matching)
              const vesselColumnIndex = rowData.findIndex(cell => {
                const cellUpper = cell.toUpperCase();
                const vesselUpper = vesselName.toUpperCase();
                
                // Try exact match first
                if (cellUpper === vesselUpper) return true;
                
                // Try partial match for EVER BUILD
                if (cellUpper.includes('EVER BUILD') && vesselUpper.includes('EVER BUILD')) {
                  return true;
                }
                
                // Try to match vessel name without voyage code
                const vesselBase = vesselUpper.replace(/\s+\d+[SN]?$/g, ''); // Remove voyage codes
                if (cellUpper.includes(vesselBase) && vesselBase.length > 5) {
                  return true;
                }
                
                return false;
              });
              
              if (vesselColumnIndex >= 0) {
                console.log('Found vessel in shipmentlink table:', rowData);
                
                // Enhanced column mapping for shipmentlink format
                let mapping = {
                  vessel_name: rowData[vesselColumnIndex],
                  extraction_method: 'shipmentlink_table',
                  raw_data: rowData,
                  table_columns: cells.length,
                  row_index: i
                };
                
                // Try to map common shipmentlink columns
                for (let j = 0; j < rowData.length; j++) {
                  const cellValue = rowData[j];
                  const cellLower = cellValue.toLowerCase();
                  
                  // Look for voyage codes
                  if (cellValue.match(/\d{3,4}[SN]?/) && j !== vesselColumnIndex) {
                    if (!mapping.voyage_code) {
                      mapping.voyage_code = cellValue.trim();
                    } else if (!mapping.voyage_out) {
                      mapping.voyage_out = cellValue.trim();
                    }
                  }
                  
                  // Look for ports/terminals
                  if (cellLower.includes('port') || cellLower.includes('terminal') || 
                      cellValue.match(/^[A-Z]{2,4}$/)) {
                    mapping.port = cellValue.trim();
                  }
                  
                  // Look for dates (multiple formats)
                  if (cellValue.match(/\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}/) || 
                      cellValue.match(/\d{2,4}-\d{1,2}-\d{1,2}/) ||
                      cellValue.match(/\d{1,2}\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/i)) {
                    
                    if (!mapping.eta) {
                      mapping.eta = cellValue.trim();
                    } else if (!mapping.etd) {
                      mapping.etd = cellValue.trim();
                    }
                  }
                  
                  // Look for time patterns
                  if (cellValue.match(/\d{1,2}:\d{2}/) && j !== vesselColumnIndex) {
                    if (mapping.eta && !mapping.eta.includes(':')) {
                      mapping.eta += ' ' + cellValue.trim();
                    } else if (mapping.etd && !mapping.etd.includes(':')) {
                      mapping.etd += ' ' + cellValue.trim();
                    }
                  }
                }
                
                results.push(mapping);
                console.log('ShipmentLink extraction successful:', mapping);
                break;
              }
            }
          }
          
          if (results.length > 0) break;
        }
      }
      
      // Strategy 2: Look for any table with vessel data (fallback)
      if (results.length === 0) {
        console.log('No specialized table found, trying general table search...');
        
        for (const table of tables) {
          const rows = table.querySelectorAll('tr');
          
          for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].querySelectorAll('td, th');
            
            if (cells.length >= 3) {
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              
              // General vessel search
              const vesselFound = rowData.some(cell => 
                cell.toUpperCase().includes(vesselName.toUpperCase()) ||
                (cell.toUpperCase().includes('EVER BUILD') && vesselName.toUpperCase().includes('EVER BUILD'))
              );
              
              if (vesselFound) {
                console.log('Found vessel in general table search:', rowData);
                
                results.push({
                  vessel_name: vesselName,
                  extraction_method: 'general_table_search',
                  raw_data: rowData,
                  table_columns: cells.length,
                  note: 'Found in general table, may need manual parsing'
                });
                break;
              }
            }
          }
          
          if (results.length > 0) break;
        }
      }
      
      // Strategy 3: Search in divs and other elements
      if (results.length === 0) {
        console.log('No table data found, searching page elements...');
        
        const allElements = document.querySelectorAll('div, span, p, td');
        for (const element of allElements) {
          const text = element.textContent.trim();
          if (text.length > 10 && text.length < 200) {
            if (text.toUpperCase().includes(vesselName.toUpperCase()) ||
                (text.toUpperCase().includes('EVER BUILD') && vesselName.toUpperCase().includes('EVER BUILD'))) {
              
              results.push({
                vessel_name: vesselName,
                raw_text: text,
                extraction_method: 'element_search',
                note: 'Found in page element, manual parsing needed'
              });
              break;
            }
          }
        }
      }
      
      return results;
    }, targetVessel);
  }
  
  formatResult(scheduleData, vesselName) {
    return {
      success: true,
      terminal: 'Everbuild',
      vessel_name: vesselName,
      voyage_code: scheduleData.voyage_code || null,
      voyage_out: scheduleData.voyage_out || null,
      eta: this.parseDateTime(scheduleData.eta),
      etd: this.parseDateTime(scheduleData.etd),
      raw_data: scheduleData,
      scraped_at: new Date().toISOString(),
      source: 'everbuild_browser_automation'
    };
  }
  
  parseDateTime(dateTimeString) {
    if (!dateTimeString) return null;
    
    // Handle various date formats found on shipmentlink
    const datePatterns = [
      // DD/MM/YYYY HH:MM or DD/MM/YYYY - HH:MM
      /(\d{1,2})\/(\d{1,2})\/(\d{4})\s*[-–]\s*(\d{1,2}):(\d{2})/,
      // DD/MM/YYYY HH:MM
      /(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})/,
      // DD/MM/YYYY only  
      /(\d{1,2})\/(\d{1,2})\/(\d{4})/,
      // YYYY-MM-DD format
      /(\d{4})-(\d{1,2})-(\d{1,2})/
    ];
    
    for (const pattern of datePatterns) {
      const match = dateTimeString.match(pattern);
      if (match) {
        try {
          let day, month, year, hour = 0, minute = 0;
          
          if (pattern.source.includes('YYYY-')) {
            // YYYY-MM-DD format
            year = parseInt(match[1]);
            month = parseInt(match[2]) - 1;
            day = parseInt(match[3]);
          } else {
            // DD/MM/YYYY format
            day = parseInt(match[1]);
            month = parseInt(match[2]) - 1;
            year = parseInt(match[3]);
            hour = match[4] ? parseInt(match[4]) : 0;
            minute = match[5] ? parseInt(match[5]) : 0;
          }
          
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
  const vesselName = process.argv[2] || 'EVER BUILD 0815-079S';
  const scraper = new EverbuildVesselScraper();
  
  try {
    await scraper.initialize();
    const result = await scraper.scrapeVesselSchedule(vesselName);
    
    // ONLY clean JSON to stdout - logs go to stderr
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

module.exports = { EverbuildVesselScraper };
