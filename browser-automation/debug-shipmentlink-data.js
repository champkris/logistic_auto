const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function debugShipmentLinkData() {
  console.log('🔍 Debug: Checking ShipmentLink data extraction...');
  
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    
    // Navigate to the page
    await scraper.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    console.log('📄 Page loaded, handling cookies...');
    await scraper.handleCookieConsent();
    
    // Wait for dropdowns to load
    await scraper.page.waitForSelector('select', { timeout: 10000 });
    
    // Get all available vessel options for debugging
    const vessels = await scraper.page.evaluate(() => {
      const selects = document.querySelectorAll('select');
      const results = [];
      
      selects.forEach((select, index) => {
        const options = Array.from(select.options).map(option => ({
          value: option.value,
          text: option.textContent.trim()
        }));
        
        // Only include selects with substantial options (likely vessel dropdowns)
        if (options.length > 10) {
          results.push({
            dropdownIndex: index,
            totalOptions: options.length,
            sampleOptions: options.slice(0, 20).filter(opt => opt.text.length > 3)
          });
        }
      });
      
      return results;
    });
    
    console.log('🚢 Available vessels in dropdowns:');
    vessels.forEach((dropdown, index) => {
      console.log(`\nDropdown ${dropdown.dropdownIndex + 1} (${dropdown.totalOptions} total options):`);
      console.log('Sample vessels:', dropdown.sampleOptions.map(v => v.text).join(', '));
    });
    
    // Try searching for any vessel that contains "EVER" to see what happens
    const everVessels = await scraper.page.evaluate(() => {
      const selects = document.querySelectorAll('select');
      const everVessels = [];
      
      selects.forEach(select => {
        const options = Array.from(select.options);
        options.forEach(option => {
          if (option.textContent.toUpperCase().includes('EVER')) {
            everVessels.push({
              value: option.value,
              text: option.textContent.trim()
            });
          }
        });
      });
      
      return everVessels;
    });
    
    console.log('\n🔍 Vessels containing "EVER":');
    everVessels.forEach(vessel => {
      console.log(`  - ${vessel.text} (value: ${vessel.value})`);
    });
    
    if (everVessels.length > 0) {
      // Try selecting the first EVER vessel found
      const targetVessel = everVessels[0];
      console.log(`\n🎯 Testing with vessel: ${targetVessel.text}`);
      
      await scraper.page.select('select', targetVessel.value);
      await scraper.page.evaluate((value) => {
        const select = document.querySelector('select');
        if (select) {
          select.value = value;
          const changeEvent = new Event('change', { bubbles: true });
          select.dispatchEvent(changeEvent);
        }
      }, targetVessel.value);
      
      // Click search
      await scraper.page.evaluate(() => {
        const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
        for (const button of buttons) {
          const text = button.textContent || button.value || '';
          if (text.toLowerCase().includes('search') || 
              text.toLowerCase().includes('submit') ||
              text.toLowerCase().includes('go')) {
            button.click();
            return true;
          }
        }
        return false;
      });
      
      // Wait for results
      await new Promise(resolve => setTimeout(resolve, 3000));
      
      // Take a screenshot for debugging
      await scraper.page.screenshot({ path: 'debug-after-search.png', fullPage: true });
      
      // Check what data is now available
      const pageData = await scraper.page.evaluate(() => {
        // Get all tables
        const tables = document.querySelectorAll('table');
        const tableData = [];
        
        tables.forEach((table, index) => {
          const rows = table.querySelectorAll('tr');
          if (rows.length > 1) {
            const tableInfo = {
              tableIndex: index,
              rowCount: rows.length,
              sampleData: []
            };
            
            // Get first few rows of data
            for (let i = 0; i < Math.min(3, rows.length); i++) {
              const cells = rows[i].querySelectorAll('td, th');
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              if (rowData.some(cell => cell.length > 0)) {
                tableInfo.sampleData.push(rowData);
              }
            }
            
            tableData.push(tableInfo);
          }
        });
        
        return {
          tables: tableData,
          pageText: document.body.textContent.substring(0, 1000)
        };
      });
      
      console.log('\n📊 Data found after search:');
      console.log('Tables:', JSON.stringify(pageData.tables, null, 2));
      console.log('\nPage content preview:', pageData.pageText.substring(0, 500));
    }
    
    await scraper.cleanup();
    
  } catch (error) {
    console.error('Debug failed:', error.message);
    await scraper.cleanup();
  }
}

// Run debug
debugShipmentLinkData();