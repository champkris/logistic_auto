const puppeteer = require('puppeteer');
const axios = require('axios');
const winston = require('winston');

// Configure logging - logs go to stderr, not stdout
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
    new winston.transports.File({ filename: 'tips-scraping.log' })
  ]
});

class TipsVesselScraper {
  constructor() {
    this.browser = null;
    this.page = null;
    this.baseUrl = 'https://www.tips.co.th/container/shipSched/List';
  }

  async initialize() {
    logger.info('🚀 Initializing TIPS Browser Scraper...');

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

    // Set headers to appear more legitimate
    await this.page.setExtraHTTPHeaders({
      'Accept-Language': 'en-US,en;q=0.9,th;q=0.8',
      'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
      'DNT': '1',
      'Connection': 'keep-alive',
      'Upgrade-Insecure-Requests': '1',
    });

    logger.info('✅ Browser initialized successfully with enhanced interaction capabilities');
  }

  async delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
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
          logger.info(`🖱️ Clicking element: ${selector}`);
          await this.page.mouse.click(x, y);
          break;
      }

      await this.delay(300 + Math.random() * 700);
      return true;
    } catch (error) {
      logger.error(`❌ Mouse interaction failed for ${selector}: ${error.message}`);
      return false;
    }
  }

  async searchVessel(vesselName, voyageCode = '') {
    try {
      logger.info(`🔍 Starting vessel search for: ${vesselName} ${voyageCode}`);

      // Navigate to TIPS schedule page
      logger.info(`📍 Navigating to: ${this.baseUrl}`);
      await this.page.goto(this.baseUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });

      await this.delay(2000);

      // Check if search functionality exists on the page
      const hasSearchForm = await this.page.$('input[type="text"], input[name*="search"], input[id*="search"]');

      if (hasSearchForm) {
        logger.info('🔍 Search form detected, attempting to use search functionality');
        const searchResult = await this.useSearchForm(vesselName, voyageCode);
        if (searchResult.vessel_found) {
          return searchResult;
        }
      }

      // If no search form or search failed, scan all pages
      logger.info('📄 No search form found or search failed, scanning all pages...');
      return await this.scanAllPages(vesselName, voyageCode);

    } catch (error) {
      logger.error(`❌ Error during vessel search: ${error.message}`);
      await this.takeDebugScreenshot('tips-search-error');
      return {
        success: false,
        terminal: 'TIPS',
        vessel_name: vesselName,
        voyage_code: voyageCode,
        vessel_found: false,
        voyage_found: false,
        eta: null,
        error: error.message,
        search_method: 'browser_automation_error'
      };
    }
  }

  async useSearchForm(vesselName, voyageCode) {
    try {
      logger.info('🔍 Attempting to use search form...');

      // Common search input selectors
      const searchSelectors = [
        'input[type="text"]',
        'input[name*="search"]',
        'input[id*="search"]',
        'input[placeholder*="search"]',
        'input[placeholder*="vessel"]',
        'input[name*="vessel"]'
      ];

      let searchInput = null;
      for (const selector of searchSelectors) {
        searchInput = await this.page.$(selector);
        if (searchInput) {
          logger.info(`🎯 Found search input: ${selector}`);
          break;
        }
      }

      if (!searchInput) {
        logger.info('❌ No search input found');
        return { vessel_found: false };
      }

      // Clear and enter search term
      await searchInput.click();
      await this.page.keyboard.down('Control');
      await this.page.keyboard.press('KeyA');
      await this.page.keyboard.up('Control');
      await searchInput.type(vesselName, { delay: 100 });

      await this.delay(500);

      // Look for search button
      const searchButtonSelectors = [
        'button[type="submit"]',
        'input[type="submit"]',
        'button:contains("Search")',
        'button:contains("Find")',
        '.search-btn',
        '#search-btn'
      ];

      let searchClicked = false;
      for (const selector of searchButtonSelectors) {
        const button = await this.page.$(selector);
        if (button) {
          logger.info(`🔍 Clicking search button: ${selector}`);
          await button.click();
          searchClicked = true;
          break;
        }
      }

      if (!searchClicked) {
        // Try pressing Enter
        logger.info('⌨️ No search button found, pressing Enter');
        await this.page.keyboard.press('Enter');
      }

      await this.delay(3000); // Wait for search results

      // Check if results loaded
      return await this.extractVesselData(vesselName, voyageCode, 'search_form');

    } catch (error) {
      logger.error(`❌ Search form error: ${error.message}`);
      return { vessel_found: false };
    }
  }

  async scanAllPages(vesselName, voyageCode) {
    try {
      let pageNumber = 1;
      const maxPages = 20; // Safety limit

      logger.info(`📄 Starting page scan for vessel: ${vesselName}`);

      while (pageNumber <= maxPages) {
        logger.info(`📑 Scanning page ${pageNumber}...`);

        // Extract vessel data from current page
        const result = await this.extractVesselData(vesselName, voyageCode, `page_${pageNumber}`);

        if (result.vessel_found) {
          logger.info(`🎯 Vessel found on page ${pageNumber}!`);
          return result;
        }

        // Look for next page button/link
        const nextPageFound = await this.goToNextPage();
        if (!nextPageFound) {
          logger.info(`📄 No more pages found after page ${pageNumber}`);
          break;
        }

        pageNumber++;
        await this.delay(2000); // Wait between page loads
      }

      logger.info(`❌ Vessel not found after scanning ${pageNumber - 1} pages`);

      return {
        success: true,
        terminal: 'TIPS',
        vessel_name: vesselName,
        voyage_code: voyageCode,
        vessel_found: false,
        voyage_found: false,
        eta: null,
        message: `Vessel not found after scanning ${pageNumber - 1} pages`,
        search_method: 'multi_page_scan',
        pages_scanned: pageNumber - 1
      };

    } catch (error) {
      logger.error(`❌ Page scanning error: ${error.message}`);
      return {
        success: false,
        terminal: 'TIPS',
        vessel_name: vesselName,
        voyage_code: voyageCode,
        vessel_found: false,
        voyage_found: false,
        eta: null,
        error: error.message,
        search_method: 'multi_page_scan_error'
      };
    }
  }

  async goToNextPage() {
    try {
      logger.info(`🔍 Looking for next page navigation...`);

      // First, let's see what pagination elements exist on the page
      const paginationInfo = await this.page.evaluate(() => {
        const allLinks = Array.from(document.querySelectorAll('a'));
        const allButtons = Array.from(document.querySelectorAll('button'));

        const nextLikeElements = [...allLinks, ...allButtons].filter(el => {
          const text = el.textContent?.toLowerCase() || '';
          const classes = el.className || '';
          return text.includes('next') || text.includes('>') || text.includes('→') ||
                 classes.includes('next') || classes.includes('page-next');
        });

        return {
          totalLinks: allLinks.length,
          totalButtons: allButtons.length,
          nextLikeElements: nextLikeElements.map(el => ({
            tagName: el.tagName,
            text: el.textContent?.trim() || '',
            className: el.className || '',
            disabled: el.disabled || el.classList.contains('disabled'),
            href: el.href || null
          }))
        };
      });

      logger.info(`📄 Pagination info: ${JSON.stringify(paginationInfo, null, 2)}`);

      // Enhanced pagination selectors - more comprehensive
      const nextPageSelectors = [
        '.paginate_button.next',  // Specific for TIPS site
        '.next',
        '.page-next',
        'a[rel="next"]',
        'button[rel="next"]',
        '.pagination .next',
        '.pagination a:last-child',
        '.page-numbers .next',
        'a[title*="next" i]',
        'button[title*="next" i]'
      ];

      let nextPageClicked = false;

      for (const selector of nextPageSelectors) {
        try {
          // Simple selector handling
          const element = await this.page.$(selector);
          const elements = element ? [element] : [];

          for (const nextLink of elements) {
            if (!nextLink) continue;

            // Check if it's disabled or inactive
            const linkInfo = await this.page.evaluate((el) => {
              return {
                disabled: el.disabled ||
                         el.classList.contains('disabled') ||
                         el.classList.contains('inactive') ||
                         el.hasAttribute('disabled') ||
                         el.getAttribute('aria-disabled') === 'true',
                text: el.textContent?.trim() || '',
                className: el.className || '',
                href: el.href || null
              };
            }, nextLink);

            logger.info(`🔗 Found potential next link: ${JSON.stringify(linkInfo)}`);

            if (!linkInfo.disabled) {
              logger.info(`📄 Clicking next page button with selector: ${selector}`);

              try {
                await nextLink.click();
                await this.delay(3000); // Wait for page load

                // Verify that page content changed
                const newContent = await this.page.content();
                if (newContent.length > 1000) { // Basic check that page loaded
                  logger.info(`✅ Successfully navigated to next page`);
                  return true;
                }
              } catch (clickError) {
                logger.warn(`⚠️ Click failed for ${selector}: ${clickError.message}`);
                continue;
              }
            } else {
              logger.info(`❌ Next button is disabled: ${selector}`);
            }
          }
        } catch (e) {
          logger.warn(`⚠️ Error with selector ${selector}: ${e.message}`);
          continue;
        }
      }

      // Try looking for numbered pagination as fallback
      logger.info(`🔢 Trying numbered pagination...`);
      const pageNumbers = await this.page.$$('.pagination a, .page-numbers a, .pager a');

      if (pageNumbers.length > 0) {
        logger.info(`📄 Found ${pageNumbers.length} numbered page links`);

        for (let i = 0; i < pageNumbers.length; i++) {
          const pageInfo = await this.page.evaluate((el, index) => {
            return {
              text: el.textContent?.trim() || '',
              className: el.className || '',
              isActive: el.classList.contains('active') || el.classList.contains('current') || el.classList.contains('selected'),
              href: el.href || null,
              index: index
            };
          }, pageNumbers[i], i);

          logger.info(`📄 Page ${i}: ${JSON.stringify(pageInfo)}`);

          // If we find an active page, try to click the next one
          if (pageInfo.isActive && i + 1 < pageNumbers.length) {
            logger.info(`📄 Current active page found at index ${i}, clicking next page`);
            try {
              await pageNumbers[i + 1].click();
              await this.delay(3000);
              logger.info(`✅ Successfully clicked numbered pagination`);
              return true;
            } catch (clickError) {
              logger.warn(`⚠️ Failed to click numbered pagination: ${clickError.message}`);
            }
          }
        }
      }

      logger.info(`❌ No working next page button found`);
      return false;

    } catch (error) {
      logger.error(`❌ Error navigating to next page: ${error.message}`);
      return false;
    }
  }

  async extractVesselData(vesselName, voyageCode, searchMethod) {
    try {
      logger.info(`🔍 Extracting vessel data for: ${vesselName} (method: ${searchMethod})`);

      // Try to extract data from table structure first
      const tableResult = await this.extractFromTable(vesselName, voyageCode);
      if (tableResult.vessel_found) {
        logger.info(`🎯 Vessel found in table structure!`);
        return {
          success: true,
          terminal: 'TIPS',
          vessel_name: vesselName,
          voyage_code: voyageCode,
          vessel_found: tableResult.vessel_found,
          voyage_found: tableResult.voyage_found,
          eta: tableResult.eta,
          search_method: searchMethod + '_table',
          checked_at: new Date().toISOString()
        };
      }

      // Fallback to content-based search
      logger.info(`🔍 Table extraction failed, trying content-based search...`);

      // Get page content
      const content = await this.page.content();
      logger.info(`📄 Page content length: ${content.length} characters`);

      // Vessel name matching (case insensitive)
      const vesselNamePattern = new RegExp(vesselName.replace(/\s+/g, '\\s+'), 'gi');
      const vesselFound = vesselNamePattern.test(content);

      // Voyage code matching with variations
      let voyageFound = false;
      const voyageVariations = this.generateVoyageVariations(voyageCode);

      for (const variation of voyageVariations) {
        if (variation && content.toUpperCase().includes(variation.toUpperCase())) {
          voyageFound = true;
          logger.info(`🎯 Found voyage variation: ${variation}`);
          break;
        }
      }

      logger.info(`🔍 Vessel "${vesselName}" found: ${vesselFound}`);
      logger.info(`🔍 Voyage "${voyageCode}" found: ${voyageFound}`);

      let eta = null;
      if (vesselFound) {
        eta = await this.extractETA(content, vesselName);
      }

      return {
        success: true,
        terminal: 'TIPS',
        vessel_name: vesselName,
        voyage_code: voyageCode,
        vessel_found: vesselFound,
        voyage_found: voyageFound,
        eta: eta,
        search_method: searchMethod,
        checked_at: new Date().toISOString()
      };

    } catch (error) {
      logger.error(`❌ Error extracting vessel data: ${error.message}`);
      return {
        success: false,
        terminal: 'TIPS',
        vessel_name: vesselName,
        voyage_code: voyageCode,
        vessel_found: false,
        voyage_found: false,
        eta: null,
        error: error.message,
        search_method: searchMethod
      };
    }
  }

  async extractFromTable(vesselName, voyageCode) {
    try {
      logger.info(`🗂️ Extracting data from table structure...`);

      // Wait for table to be present
      await this.page.waitForSelector('table, .table, [role="table"]', { timeout: 5000 });

      // Extract debugging information about tables and content
      const pageInfo = await this.page.evaluate((searchVessel, searchVoyage) => {
        const tables = document.querySelectorAll('table, .table, [role="table"]');
        const bodyText = document.body.textContent || '';

        // Check if vessel name appears anywhere in the page
        const vesselInPage = bodyText.toUpperCase().includes(searchVessel.toUpperCase());
        const voyageInPage = searchVoyage ? bodyText.toUpperCase().includes(searchVoyage.toUpperCase()) : false;

        let tableInfo = [];
        let allRowsText = [];

        for (let i = 0; i < tables.length; i++) {
          const table = tables[i];
          const rows = table.querySelectorAll('tr');

          tableInfo.push({
            tableIndex: i,
            rowCount: rows.length,
            hasData: rows.length > 1
          });

          // Extract all row text for debugging
          for (const row of rows) {
            const rowText = row.textContent?.trim();
            if (rowText && rowText.length > 5) { // Filter out empty rows
              allRowsText.push(rowText);
            }
          }
        }

        return {
          tableCount: tables.length,
          tableInfo: tableInfo,
          vesselInPage: vesselInPage,
          voyageInPage: voyageInPage,
          totalRowsFound: allRowsText.length,
          sampleRows: allRowsText.slice(0, 5), // First 5 rows for debugging
          bodyTextLength: bodyText.length
        };
      }, vesselName, voyageCode);

      logger.info(`📊 Page analysis: ${JSON.stringify(pageInfo, null, 2)}`);

      // Extract table data using page.evaluate
      const tableData = await this.page.evaluate((searchVessel, searchVoyage) => {
        const tables = document.querySelectorAll('table, .table, [role="table"]');
        let results = [];

        for (const table of tables) {
          const rows = table.querySelectorAll('tr');

          for (const row of rows) {
            const cells = row.querySelectorAll('td, th');
            if (cells.length === 0) continue;

            const rowText = row.textContent || '';

            // More flexible vessel matching - check each cell and the full row
            let vesselFound = false;
            const vesselCell = Array.from(cells).find(cell => {
              const cellText = cell.textContent || '';
              return cellText.toUpperCase().includes(searchVessel.toUpperCase());
            });

            // Also check if vessel name appears anywhere in the row
            if (vesselCell || rowText.toUpperCase().includes(searchVessel.toUpperCase())) {
              vesselFound = true;
            }

            if (vesselFound) {
              const rowData = {
                vesselName: '',
                voyage: '',
                eta: '',
                fullRowText: rowText.trim(),
                cellCount: cells.length
              };

              // Extract vessel name
              const vesselMatch = rowText.match(new RegExp(searchVessel.replace(/\s+/g, '\\s+'), 'gi'));
              if (vesselMatch) {
                rowData.vesselName = vesselMatch[0];
              }

              // Look for voyage codes in the row
              const voyagePatterns = [
                /\b\d{4,5}[A-Z]\b/g,  // 25106S pattern
                /\b[A-Z]\.?\s*\d{4,5}[A-Z]?\b/g,  // V.25106S pattern
                /\b\d{2,4}[A-Z]{1,2}\b/g  // 069N pattern
              ];

              for (const pattern of voyagePatterns) {
                const voyageMatches = rowText.match(pattern);
                if (voyageMatches) {
                  rowData.voyage = voyageMatches.join(', ');
                  break;
                }
              }

              // Look for dates (potential ETA)
              const datePatterns = [
                /\b\d{2}\/\d{2}\/\d{4}\b/g,
                /\b\d{4}-\d{2}-\d{2}\b/g,
                /\b\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}\b/g
              ];

              for (const pattern of datePatterns) {
                const dateMatches = rowText.match(pattern);
                if (dateMatches && dateMatches.length > 0) {
                  rowData.eta = dateMatches[0];
                  break;
                }
              }

              results.push(rowData);
            }
          }
        }

        return results;
      }, vesselName, voyageCode);

      logger.info(`🗂️ Found ${tableData.length} potential matches in table`);

      if (tableData.length > 0) {
        for (const data of tableData) {
          logger.info(`📋 Table row: Vessel="${data.vesselName}", Voyage="${data.voyage}", ETA="${data.eta}"`);

          // Check if this row matches our criteria
          const vesselMatch = data.vesselName.toUpperCase().includes(vesselName.toUpperCase());
          let voyageMatch = false;

          if (voyageCode) {
            const voyageVariations = this.generateVoyageVariations(voyageCode);
            voyageMatch = voyageVariations.some(variation =>
              data.voyage.toUpperCase().includes(variation.toUpperCase())
            );
          } else {
            voyageMatch = true; // If no voyage specified, consider it a match
          }

          if (vesselMatch) {
            return {
              vessel_found: true,
              voyage_found: voyageMatch,
              eta: data.eta || null,
              table_data: data
            };
          }
        }
      }

      return { vessel_found: false, voyage_found: false, eta: null };

    } catch (error) {
      logger.error(`❌ Table extraction error: ${error.message}`);
      return { vessel_found: false, voyage_found: false, eta: null };
    }
  }

  generateVoyageVariations(voyageCode) {
    if (!voyageCode) return [];

    const variations = [voyageCode];

    // Remove common prefixes
    if (voyageCode.match(/^V\.?\s*/i)) {
      variations.push(voyageCode.replace(/^V\.?\s*/i, ''));
    }

    // Add/remove spaces and dots
    variations.push(voyageCode.replace(/[\s\.]/g, ''));
    variations.push(voyageCode.replace(/\./g, ' '));

    // Add common prefixes if not present
    if (!voyageCode.match(/^V/i)) {
      variations.push(`V.${voyageCode}`);
      variations.push(`V. ${voyageCode}`);
      variations.push(`V${voyageCode}`);
    }

    return [...new Set(variations)]; // Remove duplicates
  }

  async extractETA(content, vesselName) {
    try {
      // Common ETA patterns in Thai port websites
      const etaPatterns = [
        // ISO format dates
        /(\d{4}-\d{2}-\d{2})/g,
        // Thai format dates
        /(\d{1,2}\/\d{1,2}\/\d{4})/g,
        // Date with time
        /(\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2})/g,
        // Date ranges
        /(\d{1,2}-\d{1,2}-\d{4})/g
      ];

      // Look for dates near vessel name
      const vesselPattern = new RegExp(vesselName.replace(/\s+/g, '\\s+'), 'gi');
      const matches = [...content.matchAll(vesselPattern)];

      for (const match of matches) {
        const startIndex = Math.max(0, match.index - 500);
        const endIndex = Math.min(content.length, match.index + match[0].length + 500);
        const contextText = content.substring(startIndex, endIndex);

        for (const pattern of etaPatterns) {
          const dateMatches = [...contextText.matchAll(pattern)];
          if (dateMatches.length > 0) {
            const potentialETA = dateMatches[0][1];
            logger.info(`🗓️ Found potential ETA: ${potentialETA}`);
            return potentialETA;
          }
        }
      }

      logger.info('📅 No ETA found near vessel name');
      return null;

    } catch (error) {
      logger.error(`❌ Error extracting ETA: ${error.message}`);
      return null;
    }
  }

  async takeDebugScreenshot(filename) {
    try {
      const timestamp = Date.now();
      const screenshotPath = `tips-${filename}-${timestamp}.png`;
      await this.page.screenshot({
        path: screenshotPath,
        fullPage: true
      });
      logger.info(`📸 Debug screenshot saved: ${screenshotPath}`);
    } catch (error) {
      logger.error(`❌ Failed to take screenshot: ${error.message}`);
    }
  }

  async cleanup() {
    try {
      if (this.page) {
        await this.page.close();
      }
      if (this.browser) {
        await this.browser.close();
      }
      logger.info('🧹 Browser cleanup completed');
    } catch (error) {
      logger.error(`❌ Cleanup error: ${error.message}`);
    }
  }
}

// Export for use as module
module.exports = TipsVesselScraper;

// CLI usage
if (require.main === module) {
  const args = process.argv.slice(2);
  if (args.length === 0) {
    console.error('Usage: node tips-scraper.js "VESSEL NAME" [VOYAGE_CODE]');
    process.exit(1);
  }

  const vesselName = args[0];
  const voyageCode = args[1] || '';

  (async () => {
    const scraper = new TipsVesselScraper();

    try {
      await scraper.initialize();
      const result = await scraper.searchVessel(vesselName, voyageCode);

      // Output JSON result to stdout (Laravel expects this)
      console.log(JSON.stringify(result, null, 2));

    } catch (error) {
      logger.error(`❌ Fatal error: ${error.message}`);
      console.log(JSON.stringify({
        success: false,
        terminal: 'TIPS',
        vessel_name: vesselName,
        voyage_code: voyageCode,
        vessel_found: false,
        voyage_found: false,
        eta: null,
        error: error.message,
        search_method: 'fatal_error'
      }, null, 2));
    } finally {
      await scraper.cleanup();
    }
  })();
}