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

class ShipmentLinkVesselScraper {
  constructor(laravelApiUrl = 'http://localhost:8003') {
    this.apiUrl = laravelApiUrl;
    this.browser = null;
    this.page = null;
  }

  async initialize() {
    logger.info('🚀 Initializing ShipmentLink Browser Scraper...');
    
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
  
  // Handle cookie consent popup
  async handleCookieConsent() {
    try {
      logger.info('🍪 Checking for cookie consent popup...');
      
      // Common cookie consent selectors
      const cookieSelectors = [
        'button[id*="accept"]',
        'button[class*="accept"]',
        'button[id*="cookie"]',
        'button[class*="cookie"]',
        '.cookie-accept',
        '.accept-cookies',
        '#accept-cookies',
        '#cookie-accept',
        'button:contains("Accept")',
        'button:contains("OK")',
        'button:contains("Agree")',
        'button:contains("ยอมรับ")',
        'button:contains("ตกลง")'
      ];
      
      // Wait a bit for popup to appear
      await this.delay(2000);
      
      for (const selector of cookieSelectors) {
        try {
          const element = await this.page.$(selector);
          if (element) {
            const isVisible = await element.isIntersectingViewport();
            if (isVisible) {
              await this.humanLikeMouseInteraction(selector, 'click');
              logger.info(`✅ Accepted cookies using selector: ${selector}`);
              await this.delay(1000);
              return true;
            }
          }
        } catch (error) {
          continue;
        }
      }
      
      // Try to find cookie buttons by text content
      const cookieAccepted = await this.page.evaluate(() => {
        const buttons = document.querySelectorAll('button, a, div[onclick], span[onclick]');
        for (const button of buttons) {
          const text = button.textContent.toLowerCase();
          if (text.includes('accept') || text.includes('ok') || 
              text.includes('agree') || text.includes('ยอมรับ') || 
              text.includes('ตกลง') || text.includes('cookie')) {
            // Check if element is visible
            const rect = button.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
              button.click();
              return true;
            }
          }
        }
        return false;
      });
      
      if (cookieAccepted) {
        logger.info('✅ Accepted cookies by text content');
        await this.delay(1000);
        return true;
      }
      
      logger.info('ℹ️ No cookie consent popup found');
      return true;
      
    } catch (error) {
      logger.warn(`⚠️ Cookie consent handling failed: ${error.message}`);
      return false;
    }
  }
  
  // Enhanced dropdown interaction for vessel selection
  async selectVesselFromDropdown(vesselName) {
    try {
      logger.info(`🔽 Looking for vessel dropdown to select: ${vesselName}`);
      
      // Wait for dropdowns to load
      await this.page.waitForSelector('select', { timeout: 10000 });
      
      // Find all select elements
      const dropdowns = await this.page.$$('select');
      logger.info(`📋 Found ${dropdowns.length} dropdown(s) on page`);
      
      let vesselSelected = false;
      
      for (let i = 0; i < dropdowns.length; i++) {
        const dropdown = dropdowns[i];
        
        // Get dropdown options
        const options = await dropdown.$$eval('option', options => 
          options.map(option => ({
            value: option.value,
            text: option.textContent.trim()
          }))
        );
        
        logger.info(`Dropdown ${i + 1} has ${options.length} options`);
        
        // Check if this dropdown contains vessel names
        // Special handling for EVER BUILD vessels
        let targetOption = null;
        
        if (vesselName.toUpperCase().includes('EVER BUILD')) {
          // For EVER BUILD vessels, look for exact "EVER BUILD" option
          targetOption = options.find(option => 
            option.text.trim().toUpperCase() === 'EVER BUILD'
          );
          
          if (targetOption) {
            logger.info(`✅ Found EVER BUILD option with value: ${targetOption.value}`);
          }
        }
        
        // If not found or not EVER BUILD, try general matching
        if (!targetOption) {
          // Extract base vessel name (remove voyage codes like "0815-079S")
          const baseVesselName = vesselName.replace(/\s+\d{4}-\d{3}[SN]/i, '').trim();
          
          targetOption = options.find(option => 
            option.text.toUpperCase().includes(baseVesselName.toUpperCase()) ||
            baseVesselName.toUpperCase().includes(option.text.toUpperCase())
          );
        }
        
        if (targetOption) {
          logger.info(`✅ Found target vessel "${targetOption.text}" in dropdown ${i + 1}`);
          
          // Select the vessel
          await this.page.select('select', targetOption.value);
          
          // Trigger change event
          await this.page.evaluate((value) => {
            const select = document.querySelector('select');
            if (select) {
              select.value = value;
              const changeEvent = new Event('change', { bubbles: true });
              select.dispatchEvent(changeEvent);
            }
          }, targetOption.value);
          
          logger.info(`✅ Selected vessel: ${targetOption.text}`);
          vesselSelected = true;
          break;
        } else {
          // Log available options for debugging
          const vesselLikeOptions = options.filter(opt => 
            opt.text.length > 5 && 
            !opt.text.includes('Select') && 
            !opt.text.includes('Choose')
          );
          
          if (vesselLikeOptions.length > 0) {
            logger.info(`🚢 Available options in dropdown ${i + 1}: ${vesselLikeOptions.map(v => v.text).slice(0, 5).join(', ')}...`);
          }
        }
      }
      
      if (!vesselSelected) {
        // Try partial matching for vessel names
        for (let i = 0; i < dropdowns.length; i++) {
          const dropdown = dropdowns[i];
          
          const options = await dropdown.$$eval('option', options => 
            options.map(option => ({
              value: option.value,
              text: option.textContent.trim()
            }))
          );
          
          // Look for partial matches
          const partialMatch = options.find(option => {
            const optionWords = option.text.toUpperCase().split(/\s+/);
            const vesselWords = vesselName.toUpperCase().split(/\s+/);
            
            // Check if any word from vessel name matches any word in option
            return vesselWords.some(vWord => 
              optionWords.some(oWord => 
                vWord.includes(oWord) || oWord.includes(vWord)
              )
            );
          });
          
          if (partialMatch) {
            logger.info(`✅ Found partial match "${partialMatch.text}" for vessel ${vesselName}`);
            
            await this.page.select('select', partialMatch.value);
            await this.page.evaluate((value) => {
              const select = document.querySelector('select');
              if (select) {
                select.value = value;
                const changeEvent = new Event('change', { bubbles: true });
                select.dispatchEvent(changeEvent);
              }
            }, partialMatch.value);
            
            vesselSelected = true;
            break;
          }
        }
      }
      
      return vesselSelected;
      
    } catch (error) {
      logger.error(`❌ Vessel dropdown selection failed: ${error.message}`);
      return false;
    }
  }
  
  async scrapeVesselSchedule(vesselName = 'EVER BUILD') {
    try {
      logger.info(`🔍 Scraping ShipmentLink schedule for vessel: ${vesselName}`);
      
      // Special handling for EVER BUILD vessels - use direct URL approach
      if (vesselName.toUpperCase().includes('EVER BUILD')) {
        logger.info('🚢 Using direct URL approach for EVER BUILD vessel');
        
        // Navigate directly to results page with vessel code
        await this.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp?vslCode=BULD', {
          waitUntil: 'networkidle0',
          timeout: 30000
        });
        
        logger.info('📄 Direct URL loaded successfully');
        
        // Handle cookie consent if present
        await this.handleCookieConsent();
        
      } else {
        // Use form-based approach for other vessels
        logger.info('📄 Loading ShipmentLink vessel schedule page...');
        await this.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
          waitUntil: 'networkidle2',
          timeout: 30000
        });
        
        logger.info('📄 Page loaded successfully');
        
        // Handle cookie consent first
        await this.handleCookieConsent();
        
        // Wait for page to stabilize
        await this.delay(2000);
        
        // Select vessel from dropdown
        const vesselSelected = await this.selectVesselFromDropdown(vesselName);
        
        if (!vesselSelected) {
          logger.warn(`⚠️ Could not select vessel: ${vesselName}`);
          return {
            success: false,
            error: `Vessel ${vesselName} not found in dropdown options`,
            terminal: 'ShipmentLink',
            scraped_at: new Date().toISOString()
          };
        }
        
        // Click search/submit button
        await this.clickSearchButton();
      }
      
      // Continue with data extraction for both approaches
      
      // Wait for results to load with better detection
      logger.info('⏳ Waiting for schedule results...');
      
      try {
        // Wait for the specific results to appear - look for voyage codes in tables
        await this.page.waitForFunction(() => {
          // Look for tables with voyage data
          const tables = document.querySelectorAll('table');
          let hasVoyageData = false;
          
          for (const table of tables) {
            const tableText = table.textContent;
            if (tableText.includes('EVER BUILD') && 
                (tableText.includes('0815-') || tableText.includes('0811-') || 
                 tableText.includes('0819-') || tableText.includes('0823-'))) {
              hasVoyageData = true;
              break;
            }
          }
          
          // Also check for multiple schedule tables (results page has many)
          const hasMultipleTables = tables.length > 10;
          
          return hasVoyageData || hasMultipleTables;
        }, { timeout: 30000 });
        
        logger.info('📊 Results loaded successfully');
        
        // Wait a bit more for dynamic content to fully load
        await new Promise(resolve => setTimeout(resolve, 3000));
        
      } catch (waitError) {
        logger.warn('⚠️ Timeout waiting for full results, proceeding with data extraction...');
        
        // Wait a bit more anyway
        await new Promise(resolve => setTimeout(resolve, 2000));
      }
      
      // Enhanced extraction for voyage-specific schedule data
      const scheduleData = await this.page.evaluate((targetVessel) => {
        console.log('🔍 Starting enhanced ShipmentLink data extraction...');
        
        const results = [];
        const pageText = document.body.textContent.toUpperCase();
        
        // Extract voyage code from vessel name (e.g., "EVER BUILD 0815-079S" -> "0815-079S")
        const voyageMatch = targetVessel.match(/(\d{4}-\d{3}[SN])/);
        const targetVoyage = voyageMatch ? voyageMatch[1] : null;
        
        console.log('Target vessel:', targetVessel);
        console.log('Target voyage:', targetVoyage);
        
        // Check if we have results or are still on selection page
        const isStillOnSelectionPage = pageText.includes('PLEASE SELECT THE VESSEL NAME');
        const hasVesselDropdown = document.querySelector('select');
        
        // Check if we actually have results by looking for voyage-specific content
        const hasVoyageResults = pageText.includes('EVER BUILD') && 
                                (pageText.includes('ARR') || pageText.includes('DEP'));
        
        // Check for results tables - results page has many tables with schedule data
        const tablesCount = document.querySelectorAll('table').length;
        const hasScheduleTables = tablesCount > 5; // Results page has many tables
        
        // Check for voyage-specific patterns in the HTML
        const hasVoyageCodes = pageText.includes('0807-') || pageText.includes('0811-') || 
                              pageText.includes('0815-') || pageText.includes('0819-') || 
                              pageText.includes('0823-');
        
        console.log('🔍 Page Detection Debug:');
        console.log('- isStillOnSelectionPage:', isStillOnSelectionPage);
        console.log('- hasVesselDropdown:', !!hasVesselDropdown);
        console.log('- hasVoyageResults:', hasVoyageResults);
        console.log('- hasScheduleTables:', hasScheduleTables, `(${tablesCount} tables)`);
        console.log('- hasVoyageCodes:', hasVoyageCodes);
        console.log('- pageText includes EVER BUILD:', pageText.includes('EVER BUILD'));
        console.log('- pageText includes ARR:', pageText.includes('ARR'));
        console.log('- pageText includes DEP:', pageText.includes('DEP'));
        
        // We're still on selection page ONLY if we have selection text AND no actual results
        if (isStillOnSelectionPage && !hasVoyageResults && !hasScheduleTables && !hasVoyageCodes) {
          console.log('⚠️ Still on vessel selection page - no schedule data available');
          return {
            noScheduleData: true,
            message: `No current schedule data available for ${targetVessel}`,
            stillOnSelectionPage: true
          };
        }
        
        // Look for the specific voyage if we have one
        if (targetVoyage) {
          console.log(`🎯 Looking for specific voyage: ${targetVoyage}`);
          
          // Get all HTML content
          const htmlContent = document.documentElement.outerHTML;
          const voyageIndex = htmlContent.indexOf(targetVoyage);
          
          if (voyageIndex !== -1) {
            console.log(`✅ Found voyage ${targetVoyage} in HTML`);
            
            // Extract section around the voyage - increased size to capture full table width
            const sectionStart = Math.max(0, voyageIndex - 2000);
            const sectionEnd = Math.min(htmlContent.length, voyageIndex + 8000);
            const voyageSection = htmlContent.substring(sectionStart, sectionEnd);
            
            // Create a temporary div to parse this section as DOM
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = voyageSection;
            
            // Look for table structures in this section
            const tables = tempDiv.querySelectorAll('table');
            console.log(`Found ${tables.length} tables in voyage section`);
            
            for (const table of tables) {
              const rows = table.querySelectorAll('tr');
              if (rows.length >= 2) {
                console.log(`Processing table with ${rows.length} rows`);
                
                const scheduleInfo = {
                  voyage: targetVoyage,
                  vessel_name: targetVessel.replace(` ${targetVoyage}`, '').trim(),
                  extraction_method: 'voyage_specific_table'
                };
                
                // Extract port headers with better handling of complex table structures
                const headerCells = rows[0].querySelectorAll('td, th');
                const ports = [];
                
                headerCells.forEach(cell => {
                  const cellText = cell.textContent.trim().replace(/\s+/g, ' ');
                  
                  // Split by common separators and filter valid port names
                  const portNames = cellText.split(/\n|\t|  /).map(p => p.trim()).filter(p => {
                    return p && p.length > 2 && 
                           !p.match(/^\d+$/) && // Not just numbers
                           !p.match(/^(ARR|DEP)$/i) && // Not ARR/DEP
                           p.match(/[A-Z]/); // Contains letters
                  });
                  
                  ports.push(...portNames);
                });
                
                scheduleInfo.ports = ports;
                console.log('Ports found:', ports);
                
                // Extract ARR/DEP data with improved parsing
                const arrivalDates = [];
                const departureDates = [];
                
                for (let i = 1; i < rows.length; i++) {
                  const row = rows[i];
                  const cells = row.querySelectorAll('td, th');
                  const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                  
                  if (rowData.length > 0) {
                    const scheduleType = rowData[0].toUpperCase();
                    
                    // Extract individual dates from cells (skip first cell which is ARR/DEP label)
                    const dates = rowData.slice(1).map(cellText => {
                      // Extract dates from complex cell content
                      const dateMatches = cellText.match(/\d{2}\/\d{2}/g);
                      return dateMatches ? dateMatches : [cellText.trim()];
                    }).flat().filter(date => date.match(/^\d{2}\/\d{2}$/));
                    
                    if (scheduleType.includes('ARR')) {
                      arrivalDates.push(...dates);
                      console.log('ARR dates extracted:', dates);
                    } else if (scheduleType.includes('DEP')) {
                      departureDates.push(...dates);
                      console.log('DEP dates extracted:', dates);
                    }
                  }
                }
                
                scheduleInfo.arrival_dates = arrivalDates;
                scheduleInfo.departure_dates = departureDates;
                
                // Find Laem Chabang ETA specifically with improved matching
                if (scheduleInfo.ports && scheduleInfo.arrival_dates) {
                  // Clean up ports array to remove duplicates and invalid entries
                  const cleanPorts = scheduleInfo.ports.filter((port, index, arr) => {
                    return port && 
                           port.length > 2 && 
                           !port.match(/^(ARR|DEP)$/i) && 
                           !port.match(/^\d+$/) &&
                           arr.indexOf(port) === index; // Remove duplicates
                  });
                  
                  console.log('Clean ports for matching:', cleanPorts);
                  console.log('Available arrival dates:', scheduleInfo.arrival_dates);
                  
                  const laemChabangIndex = cleanPorts.findIndex(port => 
                    port.toUpperCase().includes('LAEM') || port.toUpperCase().includes('CHABANG')
                  );
                  
                  if (laemChabangIndex >= 0 && scheduleInfo.arrival_dates[laemChabangIndex]) {
                    const etaDate = scheduleInfo.arrival_dates[laemChabangIndex];
                    console.log(`Found LAEM CHABANG at index ${laemChabangIndex}, ETA: ${etaDate}`);
                    
                    // Convert MM/DD to YYYY-MM-DD format (assuming 2025)
                    if (etaDate.match(/^\d{2}\/\d{2}$/)) {
                      const [month, day] = etaDate.split('/');
                      scheduleInfo.eta = `2025-${month.padStart(2, '0')}-${day.padStart(2, '0')} 00:00:00`;
                      console.log(`🎯 Laem Chabang ETA: ${scheduleInfo.eta}`);
                    }
                  } else {
                    console.log(`LAEM CHABANG index: ${laemChabangIndex}, Available dates: ${scheduleInfo.arrival_dates.length}`);
                  }
                }
                
                results.push(scheduleInfo);
                break; // Take the first valid table
              }
            }
          } else {
            console.log(`❌ Voyage ${targetVoyage} not found in HTML`);
            
            // Check what voyages are available
            const voyagePattern = /EVER BUILD (\d{4}-\d{3}[SN])/g;
            const availableVoyages = [];
            let match;
            
            while ((match = voyagePattern.exec(htmlContent)) !== null) {
              if (!availableVoyages.includes(match[1])) {
                availableVoyages.push(match[1]);
              }
            }
            
            console.log('Available EVER BUILD voyages:', availableVoyages);
            
            return {
              noScheduleData: true,
              message: availableVoyages.length > 0 ? 
                `Voyage ${targetVoyage} not available. Current voyages: ${availableVoyages.join(', ')}` :
                `No EVER BUILD voyages currently available`,
              availableVoyages: availableVoyages,
              targetVoyage: targetVoyage
            };
          }
        } else {
          console.log('⚠️ No voyage code found in vessel name, using general search');
          
          // Fallback to general EVER BUILD search
          const tables = document.querySelectorAll('table');
          for (const table of tables) {
            const tableText = table.textContent.toUpperCase();
            if (tableText.includes('EVER BUILD') && tableText.includes('ARR')) {
              console.log('Found table with EVER BUILD schedule data');
              
              const rows = table.querySelectorAll('tr');
              if (rows.length >= 2) {
                const scheduleInfo = {
                  vessel_name: 'EVER BUILD',
                  extraction_method: 'general_table_search',
                  raw_data: tableText.substring(0, 300)
                };
                
                results.push(scheduleInfo);
                break;
              }
            }
          }
        }
        
        return results.length > 0 ? results : {
          noScheduleData: true,
          message: `No schedule data found for ${targetVessel}`,
          hasData: pageText.includes('ARR') && pageText.includes('DEP')
        };
        
      }, vesselName);
      
      logger.info(`📊 Extracted ${Array.isArray(scheduleData) ? scheduleData.length : 0} schedule entries`);
      
      // Handle case where no schedule data is available
      if (scheduleData.noScheduleData) {
        logger.warn(`⚠️ ${scheduleData.message}`);
        return {
          success: false,
          message: scheduleData.message,
          details: scheduleData.stillOnSelectionPage ? 
            'Search completed but page remained on vessel selection form - likely no schedule data available' :
            'Page loaded but no recognizable schedule data found',
          terminal: 'ShipmentLink',
          vessel_name: vesselName,
          scraped_at: new Date().toISOString(),
          debug_info: scheduleData
        };
      }
      
      if (Array.isArray(scheduleData) && scheduleData.length > 0) {
        const schedule = scheduleData[0];
        
        const result = {
          success: true,
          terminal: 'ShipmentLink',
          vessel_name: vesselName,
          voyage_code: schedule.voyage_code || null,
          eta: this.parseDateTime(schedule.eta),
          etd: this.parseDateTime(schedule.etd),
          port: schedule.port || null,
          service: schedule.service || null,
          raw_data: schedule,
          scraped_at: new Date().toISOString(),
          source: 'shipmentlink_browser_automation'
        };
        
        logger.info(`✅ Successfully scraped ${vesselName}: ETA ${result.eta || 'N/A'}`);
        return result;
        
      } else {
        logger.warn(`⚠️ No schedule data found for ${vesselName}`);
        return {
          success: false,
          message: `Vessel ${vesselName} not found in current schedule`,
          details: 'Search completed successfully but no matching vessel data found in results',
          terminal: 'ShipmentLink',
          vessel_name: vesselName,
          scraped_at: new Date().toISOString()
        };
      }
      
    } catch (error) {
      logger.error(`❌ Error scraping ShipmentLink: ${error.message}`);
      
      // Take screenshot for debugging
      try {
        await this.page.screenshot({ 
          path: `shipmentlink-error-${Date.now()}.png`, 
          fullPage: true 
        });
        logger.info('📸 Error screenshot saved');
      } catch (screenshotError) {
        logger.error('Failed to take error screenshot');
      }
      
      return {
        success: false,
        error: error.message,
        terminal: 'ShipmentLink',
        scraped_at: new Date().toISOString()
      };
    }
  }
  
  parseDateTime(dateTimeString) {
    if (!dateTimeString) return null;
    
    // Handle various date formats that might be found on ShipmentLink
    const datePatterns = [
      // DD/MM/YYYY HH:MM
      /(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})/,
      // DD-MM-YYYY HH:MM
      /(\d{1,2})-(\d{1,2})-(\d{4})\s+(\d{1,2}):(\d{2})/,
      // YYYY-MM-DD HH:MM
      /(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d{1,2}):(\d{2})/,
      // DD/MM/YYYY
      /(\d{1,2})\/(\d{1,2})\/(\d{4})/,
      // DD-MM-YYYY
      /(\d{1,2})-(\d{1,2})-(\d{4})/,
      // YYYY-MM-DD
      /(\d{4})-(\d{1,2})-(\d{1,2})/
    ];
    
    for (const pattern of datePatterns) {
      const match = dateTimeString.match(pattern);
      if (match) {
        try {
          let day, month, year, hour = 0, minute = 0;
          
          // Determine date format and extract values
          if (pattern.source.startsWith('(\\d{4})')) {
            // YYYY-MM-DD format
            year = parseInt(match[1]);
            month = parseInt(match[2]) - 1;
            day = parseInt(match[3]);
            if (match[4]) hour = parseInt(match[4]);
            if (match[5]) minute = parseInt(match[5]);
          } else {
            // DD/MM/YYYY or DD-MM-YYYY format
            day = parseInt(match[1]);
            month = parseInt(match[2]) - 1;
            year = parseInt(match[3]);
            if (match[4]) hour = parseInt(match[4]);
            if (match[5]) minute = parseInt(match[5]);
          }
          
          const date = new Date(year, month, day, hour, minute);
          return date.toISOString().slice(0, 19).replace('T', ' ');
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
  
  async delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
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
      'button:contains("Submit")',
      '.search-btn',
      '#search-btn',
      '.btn-search'
    ];

    for (const selector of buttonSelectors) {
      try {
        const button = await this.page.$(selector);
        if (button) {
          logger.info(`🔍 Found search button with selector: ${selector}`);
          await button.click();
          await this.delay(2000);
          return true;
        }
      } catch (error) {
        logger.debug(`Could not click search button with selector ${selector}: ${error.message}`);
      }
    }

    logger.warn('⚠️ No search button found with standard selectors');
    return false;
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
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    const result = await scraper.scrapeVesselSchedule('EVER BUILD');
    
    // Clean JSON output to stdout
    console.log(JSON.stringify(result, null, 2));
    
    await scraper.cleanup();
    process.exit(result.success ? 0 : 1);
    
  } catch (error) {
    const errorResult = {
      success: false,
      error: error.message,
      terminal: 'ShipmentLink',
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
      terminal: 'ShipmentLink',
      scraped_at: new Date().toISOString()
    }, null, 2));
    process.exit(1);
  });
}

module.exports = { ShipmentLinkVesselScraper };