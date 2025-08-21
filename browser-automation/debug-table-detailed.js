const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function debugTableExtraction() {
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    
    // Navigate to EVER BUILD direct URL
    const page = scraper.page;
    await page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp?vslCode=BULD', {
      waitUntil: 'networkidle0',
      timeout: 30000
    });
    
    // Handle cookies
    try {
      await page.waitForSelector('button[id*="accept"]', { timeout: 3000 });
      await page.click('button[id*="accept"]');
      await new Promise(resolve => setTimeout(resolve, 1000));
    } catch (e) {
      console.log('No cookie popup');
    }
    
    // Wait for results
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Debug the extraction process
    const result = await page.evaluate(() => {
      const targetVoyage = '0815-079S';
      const htmlContent = document.documentElement.outerHTML;
      const voyageIndex = htmlContent.indexOf(targetVoyage);
      
      console.log(`Voyage ${targetVoyage} found at index: ${voyageIndex}`);
      
      if (voyageIndex !== -1) {
        // Try a larger section
        const sectionStart = Math.max(0, voyageIndex - 1000);
        const sectionEnd = Math.min(htmlContent.length, voyageIndex + 3000);
        const voyageSection = htmlContent.substring(sectionStart, sectionEnd);
        
        console.log('=== VOYAGE SECTION (first 1000 chars) ===');
        console.log(voyageSection.substring(0, 1000));
        console.log('=== END SECTION ===');
        
        // Parse tables in this section
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = voyageSection;
        
        const tables = tempDiv.querySelectorAll('table');
        console.log(`Found ${tables.length} tables in section`);
        
        let tableData = [];
        
        for (let t = 0; t < tables.length; t++) {
          const table = tables[t];
          const rows = table.querySelectorAll('tr');
          
          console.log(`\\n--- TABLE ${t + 1} (${rows.length} rows) ---`);
          
          for (let r = 0; r < Math.min(rows.length, 10); r++) {  // Show first 10 rows
            const cells = rows[r].querySelectorAll('td, th');
            const rowData = Array.from(cells).map(cell => cell.textContent.trim());
            console.log(`Row ${r} (${cells.length} cells):`, rowData);
          }
          
          if (rows.length >= 2) {
            // Get first row as headers
            const headerCells = rows[0].querySelectorAll('td, th');
            const ports = Array.from(headerCells).map(cell => 
              cell.textContent.trim().replace(/\\s+/g, ' ')
            ).filter(text => text && text.length > 1);
            
            tableData.push({
              tableIndex: t,
              ports: ports,
              rowCount: rows.length
            });
          }
        }
        
        return {
          voyageFound: true,
          voyageIndex: voyageIndex,
          sectionLength: voyageSection.length,
          tablesFound: tables.length,
          tableData: tableData
        };
      }
      
      return { voyageFound: false };
    });
    
    console.log('\\n🔍 EXTRACTION RESULTS:');
    console.log(JSON.stringify(result, null, 2));
    
    await scraper.cleanup();
    
  } catch (error) {
    console.error('Debug error:', error);
    await scraper.cleanup();
  }
}

debugTableExtraction();
