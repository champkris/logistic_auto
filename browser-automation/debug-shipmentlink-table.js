const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function debugShipmentLinkExtraction() {
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    
    // Get the browser page reference
    const page = scraper.page;
    
    // Navigate and search for EVER BUILD 0815-079S
    await page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', { 
      waitUntil: 'networkidle0', 
      timeout: 30000 
    });
    
    // Handle cookies
    try {
      await page.waitForSelector('button[id*="accept"], button[id*="Accept"], .cookie button', { timeout: 3000 });
      await page.click('button[id*="accept"]');
      await new Promise(resolve => setTimeout(resolve, 1000));
    } catch (e) {
      console.log('No cookie popup found');
    }
    
    // Wait for the page to load completely
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Check if we're looking at the right vessel data
    const htmlContent = await page.content();
    
    // Debug: Search for EVER BUILD 0815-079S specifically
    const voyageExists = htmlContent.includes('EVER BUILD 0815-079S');
    console.log(`🔍 EVER BUILD 0815-079S found in page: ${voyageExists}`);
    
    if (voyageExists) {
      // Extract the section around this voyage
      const voyageIndex = htmlContent.indexOf('EVER BUILD 0815-079S');
      const sectionStart = Math.max(0, voyageIndex - 200);
      const sectionEnd = Math.min(htmlContent.length, voyageIndex + 2000);
      const voyageSection = htmlContent.substring(sectionStart, sectionEnd);
      
      console.log('\n🔍 HTML SECTION AROUND EVER BUILD 0815-079S:');
      console.log('='.repeat(80));
      console.log(voyageSection);
      console.log('='.repeat(80));
      
      // Try to parse this section
      const result = await page.evaluate((section) => {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = section;
        
        const tables = tempDiv.querySelectorAll('table');
        console.log(`Found ${tables.length} tables in section`);
        
        for (const table of tables) {
          const rows = table.querySelectorAll('tr');
          console.log(`Table has ${rows.length} rows`);
          
          if (rows.length >= 2) {
            // Get header row (ports)
            const headerCells = rows[0].querySelectorAll('td, th');
            const ports = Array.from(headerCells).map(cell => 
              cell.textContent.trim().replace(/\\s+/g, ' ')
            ).filter(text => text && text.length > 1);
            
            console.log('Ports extracted:', ports);
            
            // Get data rows
            const rowsData = [];
            for (let i = 1; i < rows.length; i++) {
              const cells = rows[i].querySelectorAll('td, th');
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              rowsData.push(rowData);
            }
            
            return {
              ports: ports,
              rows: rowsData,
              tableHtml: table.outerHTML.substring(0, 500) + '...'
            };
          }
        }
        
        return { error: 'No valid tables found' };
      }, voyageSection);
      
      console.log('\n📊 PARSED TABLE DATA:');
      console.log(JSON.stringify(result, null, 2));
    }
    
    await scraper.cleanup();
    
  } catch (error) {
    console.error('Debug error:', error);
    await scraper.cleanup();
  }
}

debugShipmentLinkExtraction();
