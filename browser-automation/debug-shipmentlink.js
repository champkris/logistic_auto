const puppeteer = require('puppeteer');

class ShipmentLinkDebugger {
  constructor() {
    this.browser = null;
    this.page = null;
    this.baseUrl = 'https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp';
  }

  async initialize() {
    console.log('🚀 Initializing debug browser...');
    
    this.browser = await puppeteer.launch({
      headless: false, // Run in visible mode for debugging
      defaultViewport: { width: 1920, height: 1080 },
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    this.page = await this.browser.newPage();
    await this.page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    await this.page.setViewport({ width: 1920, height: 1080 });
    
    console.log('✅ Browser initialized');
  }

  async delay(ms) {
    await new Promise(resolve => setTimeout(resolve, ms));
  }
  
  async debugShipmentLink() {
    try {
      console.log('🔍 Loading Shipment Link page...');
      
      // Navigate to the page
      await this.page.goto(this.baseUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });
      
      console.log('📄 Page loaded, taking initial screenshot...');
      await this.page.screenshot({ path: 'debug-01-initial-page.png', fullPage: true });
      
      // Wait for dropdown
      await this.page.waitForSelector('select', { timeout: 10000 });
      await this.delay(2000);
      
      console.log('🔍 Analyzing page structure...');
      
      // Check page title and URL
      const pageTitle = await this.page.title();
      const currentUrl = await this.page.url();
      console.log(`Page title: ${pageTitle}`);
      console.log(`Current URL: ${currentUrl}`);
      
      // Count dropdowns and tables
      const pageInfo = await this.page.evaluate(() => {
        return {
          dropdowns: document.querySelectorAll('select').length,
          tables: document.querySelectorAll('table').length,
          forms: document.querySelectorAll('form').length,
          buttons: document.querySelectorAll('button, input[type="submit"], input[type="button"]').length
        };
      });
      
      console.log('📊 Page elements:', pageInfo);
      
      // Find and analyze dropdown
      const dropdownInfo = await this.page.evaluate(() => {
        const dropdown = document.querySelector('select');
        if (!dropdown) return { found: false };
        
        const options = Array.from(dropdown.options);
        return {
          found: true,
          totalOptions: options.length,
          sampleOptions: options.slice(0, 5).map(opt => opt.text.trim()),
          hasEverBuild: options.some(opt => opt.text.toUpperCase().includes('EVER BUILD'))
        };
      });
      
      console.log('📋 Dropdown analysis:', dropdownInfo);
      
      if (dropdownInfo.hasEverBuild) {
        console.log('✅ EVER BUILD found in dropdown');
        
        // Select EVER BUILD
        const selectionResult = await this.page.evaluate(() => {
          const dropdown = document.querySelector('select');
          const options = Array.from(dropdown.options);
          
          const everBuildOption = options.find(opt => 
            opt.text.toUpperCase().includes('EVER BUILD')
          );
          
          if (everBuildOption) {
            dropdown.value = everBuildOption.value;
            dropdown.dispatchEvent(new Event('change', { bubbles: true }));
            
            return {
              success: true,
              selectedText: everBuildOption.text.trim(),
              selectedValue: everBuildOption.value
            };
          }
          
          return { success: false };
        });
        
        console.log('🎯 Selection result:', selectionResult);
        
        await this.delay(1000);
        await this.page.screenshot({ path: 'debug-02-vessel-selected.png', fullPage: true });
        
        // Look for and click search button
        console.log('🔍 Looking for search button...');
        
        const searchButtonInfo = await this.page.evaluate(() => {
          const buttons = document.querySelectorAll('button, input[type="submit"], input[type="button"]');
          const buttonInfo = Array.from(buttons).map(btn => ({
            tagName: btn.tagName,
            type: btn.type,
            value: btn.value,
            textContent: btn.textContent,
            id: btn.id,
            name: btn.name,
            visible: btn.offsetParent !== null
          }));
          
          return {
            totalButtons: buttons.length,
            buttons: buttonInfo
          };
        });
        
        console.log('🔘 Search button analysis:', searchButtonInfo);
        
        // Try to click search button
        const searchClicked = await this.page.evaluate(() => {
          const buttons = document.querySelectorAll('button, input[type="submit"], input[type="button"]');
          
          for (const btn of buttons) {
            const text = btn.textContent || btn.value || '';
            if (text.toLowerCase().includes('search') || 
                text.toLowerCase().includes('submit') ||
                btn.type === 'submit') {
              
              console.log('Clicking button:', text);
              btn.click();
              return { clicked: true, buttonText: text };
            }
          }
          
          return { clicked: false };
        });
        
        console.log('🎯 Search click result:', searchClicked);
        
        if (searchClicked.clicked) {
          console.log('⏳ Waiting for results to load...');
          await this.delay(5000);
          
          await this.page.screenshot({ path: 'debug-03-after-search.png', fullPage: true });
          
          // Analyze page content after search
          const afterSearchInfo = await this.page.evaluate(() => {
            const tables = document.querySelectorAll('table');
            const tableInfo = Array.from(tables).map((table, index) => {
              const rows = table.querySelectorAll('tr');
              const headers = Array.from(table.querySelectorAll('th')).map(th => th.textContent.trim());
              const firstRowCells = rows.length > 1 ? 
                Array.from(rows[1].querySelectorAll('td, th')).map(cell => cell.textContent.trim()) : [];
              
              return {
                index,
                rows: rows.length,
                headers: headers.length > 0 ? headers : null,
                firstRowSample: firstRowCells.length > 0 ? firstRowCells.slice(0, 3) : null,
                hasVesselData: Array.from(rows).some(row => 
                  row.textContent.toUpperCase().includes('EVER BUILD')
                )
              };
            });
            
            return {
              tablesCount: tables.length,
              tables: tableInfo,
              pageTextLength: document.body.textContent.length,
              hasEverBuildInText: document.body.textContent.toUpperCase().includes('EVER BUILD')
            };
          });
          
          console.log('📊 After search analysis:', JSON.stringify(afterSearchInfo, null, 2));
          
          // Check if page content has changed
          const pageChanged = await this.page.evaluate(() => {
            // Look for any indication that results loaded
            const indicators = [
              'No schedule found',
              'No data available', 
              'Schedule',
              'ETA',
              'ETD',
              'Departure',
              'Arrival'
            ];
            
            const bodyText = document.body.textContent.toLowerCase();
            const foundIndicators = indicators.filter(indicator => 
              bodyText.includes(indicator.toLowerCase())
            );
            
            return {
              pageLength: bodyText.length,
              foundIndicators,
              possibleResultsLoaded: foundIndicators.length > 0
            };
          });
          
          console.log('🔄 Page change analysis:', pageChanged);
          
        } else {
          console.log('❌ Could not find or click search button');
        }
        
      } else {
        console.log('❌ EVER BUILD not found in dropdown');
      }
      
      console.log('🎯 Debug completed. Check the screenshots for visual analysis.');
      console.log('Screenshots saved:');
      console.log('  - debug-01-initial-page.png');
      console.log('  - debug-02-vessel-selected.png');
      console.log('  - debug-03-after-search.png');
      
    } catch (error) {
      console.error('❌ Debug error:', error.message);
      await this.page.screenshot({ path: 'debug-error.png', fullPage: true });
    }
  }
  
  async cleanup() {
    if (this.browser) {
      // Keep browser open for manual inspection
      console.log('🔍 Browser left open for manual inspection...');
      // Uncomment the line below to auto-close:
      // await this.browser.close();
    }
  }
}

// Run debug
async function main() {
  const debug = new ShipmentLinkDebugger();
  
  try {
    await debug.initialize();
    await debug.debugShipmentLink();
    
    console.log('\n🔍 DEBUG COMPLETE');
    console.log('The browser window is left open for manual inspection.');
    console.log('Press Ctrl+C to close when done.');
    
    // Keep process alive for manual inspection
    process.stdin.resume();
    
  } catch (error) {
    console.error('💥 Debug failed:', error.message);
    await debug.cleanup();
    process.exit(1);
  }
}

main().catch(console.error);
