const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function debugTableParsing() {
  console.log('🔍 Debugging table parsing for EVER BUILD 0815-079S...\n');
  
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    
    // Navigate and search (using our known working approach)
    await scraper.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp');
    
    // Handle cookies
    try {
      await scraper.page.waitForSelector('button[id*="accept"]', { timeout: 5000 });
      await scraper.page.click('button[id*="accept"]');
      await new Promise(resolve => setTimeout(resolve, 2000));
    } catch (e) {}
    
    // Select and search
    await scraper.page.waitForSelector('select');
    await scraper.page.select('select', 'BULD');
    await scraper.page.evaluate(() => {
      const submitBtn = document.querySelector('input[value="Submit"]');
      if (submitBtn) submitBtn.click();
    });
    
    // Wait for results
    await new Promise(resolve => setTimeout(resolve, 8000));
    
    // Debug specific table parsing for 0815-079S
    const tableAnalysis = await scraper.page.evaluate(() => {
      const results = {
        totalTables: document.querySelectorAll('table').length,
        foundVoyage: false,
        tableDetails: []
      };
      
      // Look for the 0815-079S voyage specifically
      const htmlContent = document.documentElement.outerHTML;
      const voyageIndex = htmlContent.indexOf('0815-079S');
      
      if (voyageIndex !== -1) {
        results.foundVoyage = true;
        
        // Extract section around the voyage
        const sectionStart = Math.max(0, voyageIndex - 1000);
        const sectionEnd = Math.min(htmlContent.length, voyageIndex + 2000);
        const voyageSection = htmlContent.substring(sectionStart, sectionEnd);
        
        // Parse as DOM
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = voyageSection;
        
        // Analyze tables in this section
        const tables = tempDiv.querySelectorAll('table');
        results.sectionTableCount = tables.length;
        
        for (let t = 0; t < tables.length; t++) {
          const table = tables[t];
          const rows = table.querySelectorAll('tr');
          
          if (rows.length >= 2) {
            const tableInfo = {
              tableIndex: t,
              totalRows: rows.length,
              headers: [],
              dataRows: []
            };
            
            // Get header row
            const headerCells = rows[0].querySelectorAll('td, th');
            tableInfo.headers = Array.from(headerCells).map(cell => cell.textContent.trim());
            
            // Get data rows
            for (let r = 1; r < Math.min(rows.length, 4); r++) { // Just first few rows
              const cells = rows[r].querySelectorAll('td, th');
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              tableInfo.dataRows.push(rowData);
            }
            
            results.tableDetails.push(tableInfo);
          }
        }
      }
      
      return results;
    });
    
    console.log('📊 Table Analysis Results:');
    console.log('- Total tables on page:', tableAnalysis.totalTables);
    console.log('- Found 0815-079S voyage:', tableAnalysis.foundVoyage);
    
    if (tableAnalysis.foundVoyage) {
      console.log('- Tables in voyage section:', tableAnalysis.sectionTableCount);
      
      tableAnalysis.tableDetails.forEach((table, index) => {
        console.log(`\n📋 Table ${index + 1}:`);
        console.log('  Headers:', table.headers);
        console.log('  Sample rows:');
        table.dataRows.forEach((row, rowIndex) => {
          console.log(`    Row ${rowIndex + 1}:`, row);
        });
      });
    } else {
      console.log('❌ Could not find 0815-079S voyage in HTML');
    }
    
    await scraper.cleanup();
    
  } catch (error) {
    console.error('❌ Debug failed:', error);
    await scraper.cleanup();
  }
}

debugTableParsing();
