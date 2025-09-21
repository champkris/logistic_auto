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
    new winston.transports.File({ filename: 'hutchison-scraping.log' })
  ]
});

class HutchisonVesselScraper {
  constructor(laravelApiUrl = 'http://localhost:8003') {
    this.apiUrl = laravelApiUrl;
    this.browser = null;
    this.page = null;
    this.baseUrl = 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17:6927160550678:::::';
  }

  async initialize() {
    logger.info('🚀 Initializing Hutchison Ports Browser Scraper...');

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

  async scrapeVesselSchedule(vesselName = 'WAN HAI 517') {
    try {
      logger.info(`🔍 Scraping Hutchison Ports schedule for vessel: ${vesselName}`);

      // Navigate to Hutchison Ports vessel schedule
      await this.page.goto(this.baseUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });

      logger.info('📄 Page loaded, analyzing Oracle APEX structure...');

      // Wait for page content to load
      await this.page.waitForSelector('body', { timeout: 10000 });

      // Check if this is a login page or requires authentication
      const pageTitle = await this.page.title();
      logger.info(`📋 Page title: ${pageTitle}`);

      // Wait for the Oracle APEX content to fully load
      await this.delay(3000);

      // Look for vessel data in the page - Oracle APEX applications often use specific patterns
      logger.info('🔍 Looking for vessel schedule data...');

      // Try to find vessel dropdown or search functionality
      await this.findAndInteractWithVesselSearch(vesselName);

      // Extract vessel data from the page
      const vesselData = await this.extractVesselDataFromPage(vesselName);

      if (vesselData.length > 0) {
        const result = this.formatResult(vesselData[0], vesselName);
        logger.info(`✅ Successfully scraped ${vesselName}: ETA ${result.eta || 'N/A'}`);
        return result;
      } else {
        logger.warn(`⚠️ No schedule data found for ${vesselName}`);

        // Take screenshot for debugging
        try {
          await this.page.screenshot({ path: `hutchison-no-data-${Date.now()}.png`, fullPage: true });
          logger.info('📸 No-data screenshot saved for debugging');
        } catch (screenshotError) {
          // Ignore screenshot errors
        }

        return {
          success: false,
          message: `No schedule data found for ${vesselName}`,
          terminal: 'Hutchison Ports',
          scraped_at: new Date().toISOString()
        };
      }

    } catch (error) {
      logger.error(`❌ Error scraping Hutchison Ports: ${error.message}`);

      // Take screenshot for debugging
      try {
        await this.page.screenshot({ path: `hutchison-error-${Date.now()}.png`, fullPage: true });
        logger.info('📸 Error screenshot saved');
      } catch (screenshotError) {
        logger.error('Failed to take error screenshot');
      }

      return {
        success: false,
        error: error.message,
        terminal: 'Hutchison Ports',
        scraped_at: new Date().toISOString()
      };
    }
  }

  async findAndInteractWithVesselSearch(vesselName) {
    logger.info('🔍 Looking for vessel search functionality...');

    // Strategy 1: Look for pagination dropdown (found in logs: "row(s) 1 - 15 of 54")
    const dropdownSelectors = [
      'select[name*="vessel"]',
      'select[id*="vessel"]',
      'select[name*="ship"]',
      'select[id*="ship"]',
      'select[class*="vessel"]',
      'select[class*="ship"]',
      'select' // fallback to any select
    ];

    let paginationDropdown = null;
    for (const selector of dropdownSelectors) {
      try {
        const element = await this.page.$(selector);
        if (element) {
          // Check if this dropdown has pagination options
          const options = await element.$$eval('option', options =>
            options.map(opt => opt.textContent.trim())
          );

          logger.info(`📋 Found dropdown with selector ${selector}, ${options.length} options`);

          // Look for pagination patterns like "row(s) 1 - 15 of 54"
          const hasPagination = options.some(opt =>
            opt.includes('row(s)') ||
            opt.includes('of') ||
            opt.match(/\d+\s*-\s*\d+/)
          );

          if (hasPagination) {
            paginationDropdown = element;
            logger.info(`✅ Found pagination dropdown: ${selector}`);
            logger.info(`📄 Pagination options: ${options.join(', ')}`);
            break;
          }
        }
      } catch (error) {
        continue;
      }
    }

    if (paginationDropdown) {
      // Search through all pages
      await this.searchThroughAllPages(paginationDropdown, vesselName);
    } else {
      // Fallback: Try text input search
      await this.tryTextInputSearch(vesselName);
    }
  }

  async searchThroughAllPages(paginationDropdown, vesselName) {
    logger.info('🔍 Searching through all pages for vessel...');

    // Get all pagination options
    const paginationOptions = await paginationDropdown.$$eval('option', options =>
      options.map(opt => ({
        value: opt.value,
        text: opt.textContent.trim()
      }))
    );

    logger.info(`📄 Found ${paginationOptions.length} pages to search`);

    // Search through each page
    for (let i = 0; i < paginationOptions.length; i++) {
      const option = paginationOptions[i];
      logger.info(`🔍 Searching page ${i + 1}: ${option.text}`);

      // Select this page
      await this.page.select('select', option.value);
      await this.delay(2000); // Wait for page to load

      // Check if vessel is on this page
      const vesselFound = await this.page.evaluate((targetVessel) => {
        const pageText = document.body.textContent.toUpperCase();
        return pageText.includes(targetVessel.toUpperCase());
      }, vesselName);

      if (vesselFound) {
        logger.info(`✅ Found vessel ${vesselName} on page ${i + 1}`);
        return true;
      }
    }

    logger.info(`⚠️ Vessel ${vesselName} not found on any page`);
    return false;
  }

  async tryTextInputSearch(vesselName) {
    logger.info('🔍 Trying text input search...');

    // Strategy 2: Look for text input fields
    const inputSelectors = [
      'input[name*="vessel"]',
      'input[id*="vessel"]',
      'input[name*="ship"]',
      'input[id*="ship"]',
      'input[type="text"]'
    ];

    for (const selector of inputSelectors) {
      try {
        const element = await this.page.$(selector);
        if (element) {
          logger.info(`📝 Found text input: ${selector}`);
          await element.clear();
          await element.type(vesselName, { delay: 100 });

          // Try to submit
          await this.page.keyboard.press('Enter');
          await this.delay(3000);
          break;
        }
      } catch (error) {
        continue;
      }
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
      'input[onclick*="submit"]',
      // Oracle APEX specific selectors
      'button[id*="B"]', // Oracle APEX buttons often have IDs like "B123456"
      'input[id*="B"]'
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
              text.toLowerCase().includes('go') ||
              text.toLowerCase().includes('query')) {
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
      console.log('🔍 Starting Hutchison Ports data extraction...');

      const results = [];

      // Strategy 1: Look for Oracle APEX report regions or tables
      const tables = document.querySelectorAll('table');
      console.log(`Searching ${tables.length} tables for vessel data`);

      for (const table of tables) {
        // Look for schedule tables specifically
        const hasScheduleHeaders = Array.from(table.querySelectorAll('th, td')).some(cell => {
          const text = cell.textContent.toLowerCase();
          return text.includes('vessel') || text.includes('voyage') ||
                 text.includes('eta') || text.includes('etd') ||
                 text.includes('departure') || text.includes('arrival') ||
                 text.includes('schedule') || text.includes('berth') ||
                 text.includes('terminal') || text.includes('ship');
        });

        if (hasScheduleHeaders) {
          console.log('Found potential schedule table');

          const rows = table.querySelectorAll('tr');

          for (let i = 0; i < rows.length; i++) {
            const cells = rows[i].querySelectorAll('td, th');

            if (cells.length >= 4) {
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());

              // Find vessel name in any column
              const vesselColumnIndex = rowData.findIndex(cell => {
                const cellUpper = cell.toUpperCase();
                const vesselUpper = vesselName.toUpperCase();

                // Try exact match first
                if (cellUpper === vesselUpper) return true;

                // Try partial match
                if (cellUpper.includes(vesselUpper) && vesselUpper.length > 3) {
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
                console.log('Found vessel in Hutchison table:', rowData);

                // Enhanced column mapping for Hutchison format
                let mapping = {
                  vessel_name: rowData[vesselColumnIndex],
                  extraction_method: 'hutchison_table',
                  raw_data: rowData,
                  table_columns: cells.length,
                  row_index: i
                };

                // Try to map common Hutchison columns
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

                  // Look for berth/terminal information
                  if (cellLower.includes('berth') || cellLower.includes('terminal') ||
                      cellValue.match(/^[A-Z]\d+$/) || cellValue.match(/^C[12]$/)) {
                    mapping.terminal = cellValue.trim();
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
                console.log('Hutchison extraction successful:', mapping);
                break;
              }
            }
          }

          if (results.length > 0) break;
        }
      }

      // Strategy 2: Look for Oracle APEX report regions
      if (results.length === 0) {
        console.log('No table found, looking for APEX report regions...');

        const reportRegions = document.querySelectorAll('[id*="report"], [class*="report"], .t-Report');
        for (const region of reportRegions) {
          const text = region.textContent;
          if (text.toUpperCase().includes(vesselName.toUpperCase())) {
            console.log('Found vessel in APEX report region');

            results.push({
              vessel_name: vesselName,
              raw_text: text,
              extraction_method: 'apex_report_region',
              note: 'Found in APEX report region, manual parsing needed'
            });
            break;
          }
        }
      }

      // Strategy 3: General text search in divs and other elements
      if (results.length === 0) {
        console.log('No structured data found, searching page elements...');

        const allElements = document.querySelectorAll('div, span, p, td');
        for (const element of allElements) {
          const text = element.textContent.trim();
          if (text.length > 10 && text.length < 200) {
            if (text.toUpperCase().includes(vesselName.toUpperCase())) {

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
      terminal: 'Hutchison Ports',
      vessel_name: vesselName,
      voyage_code: scheduleData.voyage_code || null,
      voyage_out: scheduleData.voyage_out || null,
      eta: this.parseDateTime(scheduleData.eta),
      etd: this.parseDateTime(scheduleData.etd),
      terminal_berth: scheduleData.terminal || null,
      raw_data: scheduleData,
      scraped_at: new Date().toISOString(),
      source: 'hutchison_browser_automation'
    };
  }

  parseDateTime(dateTimeString) {
    if (!dateTimeString) return null;

    // Handle various date formats found on Hutchison Ports
    const datePatterns = [
      // DD/MM/YYYY HH:MM or DD/MM/YYYY - HH:MM
      /(\d{1,2})\/(\d{1,2})\/(\d{4})\s*[-–]\s*(\d{1,2}):(\d{2})/,
      // DD/MM/YYYY HH:MM
      /(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})/,
      // DD/MM/YYYY only
      /(\d{1,2})\/(\d{1,2})\/(\d{4})/,
      // YYYY-MM-DD format
      /(\d{4})-(\d{1,2})-(\d{1,2})/,
      // DD-MM-YYYY format
      /(\d{1,2})-(\d{1,2})-(\d{4})/
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
            // DD/MM/YYYY or DD-MM-YYYY format
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
  const vesselName = process.argv[2] || 'WAN HAI 517';
  const scraper = new HutchisonVesselScraper();

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
      terminal: 'Hutchison Ports',
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
      terminal: 'Hutchison Ports',
      scraped_at: new Date().toISOString()
    }, null, 2));
    process.exit(1);
  });
}

module.exports = { HutchisonVesselScraper };