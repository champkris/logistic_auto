const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function debugShipmentLinkExtraction() {
  console.log('🔍 Debugging ShipmentLink extraction for EVER BUILD 0815-079S...\n');
  
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    
    // Test with the exact vessel name from the issue
    const vesselName = 'EVER BUILD 0815-079S';
    
    console.log(`🚢 Testing vessel: ${vesselName}`);
    
    // Navigate to the page
    await scraper.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
      waitUntil: 'networkidle0',
      timeout: 30000
    });
    
    // Handle cookies
    try {
      await scraper.page.waitForSelector('button[id*="accept"]', { timeout: 5000 });
      await scraper.page.click('button[id*="accept"]');
      console.log('✅ Accepted cookies');
      await new Promise(resolve => setTimeout(resolve, 2000));
    } catch (e) {
      console.log('ℹ️ No cookie popup found');
    }
    
    // Wait for dropdown
    await scraper.page.waitForSelector('select', { timeout: 10000 });
    
    // Select the vessel
    await scraper.page.select('select', 'EVER BUILD');
    console.log('✅ Selected EVER BUILD from dropdown');
    
    // Click search using the same method as the working scraper
    const searchClicked = await scraper.page.evaluate(() => {
      // Look for search button by text content
      const buttons = Array.from(document.querySelectorAll('input[type="button"], input[type="submit"], button'));
      for (const button of buttons) {
        const text = button.value || button.textContent || '';
        if (text.toLowerCase().includes('search') || text.toLowerCase().includes('查詢')) {
          button.click();
          return true;
        }
      }
      return false;
    });
    
    if (searchClicked) {
      console.log('✅ Clicked search button');
    } else {
      console.log('⚠️ Could not find search button, trying Enter key');
      await scraper.page.keyboard.press('Enter');
    }
    
    // Wait for results
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Take a screenshot for debugging
    await scraper.page.screenshot({ path: 'debug-shipmentlink-results.png', fullPage: true });
    console.log('📸 Screenshot saved as debug-shipmentlink-results.png');
    
    // Debug: Extract all text content
    const pageContent = await scraper.page.evaluate(() => {
      return {
        bodyText: document.body.textContent,
        hasEverBuild: document.body.textContent.includes('EVER BUILD'),
        hasEverBuild0815: document.body.textContent.includes('0815-079S'),
        hasLaemChabang: document.body.textContent.includes('LAEM CHABANG'),
        hasArriveDepartText: document.body.textContent.includes('ARR') || document.body.textContent.includes('DEP'),
        tableCount: document.querySelectorAll('table').length
      };
    });
    
    console.log('\n📊 Page Content Analysis:');
    console.log('- Has "EVER BUILD":', pageContent.hasEverBuild);
    console.log('- Has "0815-079S":', pageContent.hasEverBuild0815);
    console.log('- Has "LAEM CHABANG":', pageContent.hasLaemChabang);
    console.log('- Has ARR/DEP:', pageContent.hasArriveDepartText);
    console.log('- Table count:', pageContent.tableCount);
    
    // Extract all EVER BUILD voyage data
    const voyageData = await scraper.page.evaluate(() => {
      const results = [];
      const text = document.body.innerHTML;
      
      // Look for all EVER BUILD voyage patterns
      const voyageRegex = /EVER BUILD (\d{4}-\d{3}[SN])/g;
      let match;
      const voyages = [];
      
      while ((match = voyageRegex.exec(text)) !== null) {
        if (!voyages.includes(match[1])) {
          voyages.push(match[1]);
        }
      }
      
      return {
        availableVoyages: voyages,
        hasTargetVoyage: voyages.includes('0815-079S')
      };
    });
    
    console.log('\n🚢 Voyage Analysis:');
    console.log('- Available EVER BUILD voyages:', voyageData.availableVoyages);
    console.log('- Has target 0815-079S:', voyageData.hasTargetVoyage);
    
    // If we have the target voyage, try to extract its schedule
    if (voyageData.hasTargetVoyage) {
      console.log('\n🎯 Extracting schedule for 0815-079S...');
      
      const scheduleData = await scraper.page.evaluate(() => {
        // Find the specific voyage section
        const html = document.body.innerHTML;
        const voyageIndex = html.indexOf('0815-079S');
        
        if (voyageIndex === -1) return { error: 'Voyage not found in HTML' };
        
        // Extract a section around the voyage
        const start = Math.max(0, voyageIndex - 2000);
        const end = Math.min(html.length, voyageIndex + 2000);
        const section = html.substring(start, end);
        
        // Create temp div to parse as DOM
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = section;
        
        // Look for tables in this section
        const tables = tempDiv.querySelectorAll('table');
        console.log('Tables in voyage section:', tables.length);
        
        for (const table of tables) {
          const rows = table.querySelectorAll('tr');
          if (rows.length >= 2) {
            const data = {
              tableRowCount: rows.length,
              headers: [],
              arrivalRow: [],
              departureRow: []
            };
            
            // Get headers (port names)
            const headerCells = rows[0].querySelectorAll('td, th');
            data.headers = Array.from(headerCells).map(cell => cell.textContent.trim());
            
            // Get data rows
            for (let i = 1; i < rows.length; i++) {
              const row = rows[i];
              const cells = row.querySelectorAll('td, th');
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              
              if (rowData[0] && rowData[0].toUpperCase().includes('ARR')) {
                data.arrivalRow = rowData;
              } else if (rowData[0] && rowData[0].toUpperCase().includes('DEP')) {
                data.departureRow = rowData;
              }
            }
            
            return data;
          }
        }
        
        return { error: 'No valid tables found in voyage section' };
      });
      
      console.log('\n📊 Schedule extraction result:');
      console.log(JSON.stringify(scheduleData, null, 2));
    }
    
    await scraper.cleanup();
    
  } catch (error) {
    console.error('❌ Debug failed:', error);
    await scraper.cleanup();
  }
}

debugShipmentLinkExtraction();
