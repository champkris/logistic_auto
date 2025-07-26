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
  
  async scrapeVesselSchedule(vesselName = 'EVER BUILD') {
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
      
      // Look for vessel search functionality
      await this.delay(2000);
      
      // Try to find vessel search input or dropdown
      const searchInputSelectors = [
        'input[name*="vessel"]',
        'input[placeholder*="vessel"]',
        'input[id*="vessel"]',
        'input[type="text"]',
        'select[name*="vessel"]',
        'select[id*="vessel"]'
      ];
      
      let searchElement = null;
      let searchType = null;
      
      for (const selector of searchInputSelectors) {
        try {
          const element = await this.page.$(selector);
          if (element) {
            searchElement = element;
            searchType = selector.includes('select') ? 'select' : 'input';
            logger.info(`✅ Found search element: ${selector} (type: ${searchType})`);
            break;
          }
        } catch (error) {
          continue;
        }
      }
      
      if (!searchElement) {
        // Try to find any form elements or tables that might contain vessel data
        logger.info('🔍 No search element found, looking for existing vessel data...');
        
        const vesselData = await this.extractVesselDataFromPage(vesselName);
        if (vesselData.length > 0) {
          return this.formatResult(vesselData[0], vesselName);
        }
        
        throw new Error('No vessel search functionality or data found on page');
      }
      
      // Interact with search element based on type
      if (searchType === 'select') {
        // Handle dropdown selection
        await this.selectVesselFromDropdown(searchElement, vesselName);
      } else {
        // Handle text input
        await searchElement.click();
        await searchElement.clear();
        await searchElement.type(vesselName, { delay: 100 });
        logger.info(`✅ Entered vessel name: ${vesselName}`);
      }
      
      // Look for and click search button
      await this.clickSearchButton();
      
      // Wait for results
      logger.info('⏳ Waiting for search results...');
      await this.delay(3000);
      
      // Extract vessel data from results
      const vesselData = await this.extractVesselDataFromPage(vesselName);
      
      if (vesselData.length > 0) {
        const result = this.formatResult(vesselData[0], vesselName);
        logger.info(`✅ Successfully scraped ${vesselName}: ETA ${result.eta || 'N/A'}`);
        return result;
      } else {
        logger.warn(`⚠️ No schedule data found for ${vesselName}`);
        return {
          success: false,
          message: `Vessel ${vesselName} not found in current schedule`,
          terminal: 'Everbuild',
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
      'button:contains("Search")',
      'button:contains("Submit")'
    ];
    
    for (const selector of buttonSelectors) {
      try {
        const button = await this.page.$(selector);
        if (button) {
          await button.click();
          logger.info(`🔍 Clicked search button: ${selector}`);
          return true;
        }
      } catch (error) {
        continue;
      }
    }
    
    // Try pressing Enter as fallback
    try {
      await this.page.keyboard.press('Enter');
      logger.info('🔍 Pressed Enter to trigger search');
      return true;
    } catch (error) {
      logger.warn('Could not find search button or trigger search');
      return false;
    }
  }
  
  async extractVesselDataFromPage(targetVessel) {
    return await this.page.evaluate((vesselName) => {
      console.log('🔍 Starting Everbuild data extraction...');
      
      const results = [];
      
      // Strategy 1: Look for tables containing vessel data
      const tables = document.querySelectorAll('table');
      console.log(`Searching ${tables.length} tables for vessel data`);
      
      for (const table of tables) {
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
          const cells = rows[i].querySelectorAll('td, th');
          
          if (cells.length >= 4) {
            const rowData = Array.from(cells).map(cell => cell.textContent.trim());
            
            // Find vessel name in any column
            const vesselColumnIndex = rowData.findIndex(cell => 
              cell.toUpperCase().includes(vesselName.toUpperCase())
            );
            
            if (vesselColumnIndex >= 0) {
              console.log('Found vessel in table:', rowData);
              
              // Try to intelligently map columns
              let mapping = {
                vessel_name: rowData[vesselColumnIndex],
                extraction_method: 'table_search',
                raw_data: rowData,
                table_columns: cells.length
              };
              
              // Look for common patterns
              const voyagePattern = rowData.find(cell => cell.match(/\d{3}[SN]/));
              const terminal = rowData.find(cell => cell.match(/^[A-Z]\d+$|EVERBUILD|ECTT/i));
              const dates = rowData.filter(cell => 
                cell.includes('/202') || 
                cell.match(/\d{1,2}\/\d{1,2}\/\d{4}/) ||
                cell.match(/\d{4}-\d{2}-\d{2}/)
              );
              
              if (voyagePattern) mapping.voyage_code = voyagePattern.trim();
              if (terminal) mapping.terminal = terminal.trim();
              if (dates.length >= 2) {
                mapping.eta = dates[0].trim();
                mapping.etd = dates[1].trim();
              } else if (dates.length === 1) {
                mapping.eta = dates[0].trim();
              }
              
              results.push(mapping);
              console.log('Everbuild extraction successful:', mapping);
              break;
            }
          }
        }
        
        if (results.length > 0) break;
      }
      
      // Strategy 2: Look for specific shipmentlink elements
      if (results.length === 0) {
        console.log('⚠️ No table data found, trying shipmentlink-specific elements...');
        
        // Look for vessel schedule specific elements
        const vesselElements = document.querySelectorAll('[id*="vessel"], [class*="vessel"], [name*="vessel"]');
        
        for (const element of vesselElements) {
          const text = element.textContent || element.value || '';
          if (text.toUpperCase().includes(vesselName.toUpperCase())) {
            results.push({
              vessel_name: vesselName,
              raw_text: text,
              extraction_method: 'element_search',
              note: 'Found vessel reference, manual parsing needed'
            });
            break;
          }
        }
      }
      
      // Strategy 3: Fallback text search
      if (results.length === 0) {
        console.log('No structured data found, using text search fallback');
        const pageText = document.body.textContent;
        const lines = pageText.split('\n').map(line => line.trim()).filter(line => line.length > 0);
        
        for (const line of lines) {
          if (line.toUpperCase().includes(vesselName.toUpperCase())) {
            results.push({
              vessel_name: vesselName,
              raw_text: line,
              extraction_method: 'text_search_fallback',
              note: 'Extracted from page text, manual parsing needed'
            });
            break;
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
  const vesselName = process.argv[2] || 'EVER BUILD';
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
