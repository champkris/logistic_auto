const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function testLaemChabangExtraction() {
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
    
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Specifically look for EVER BUILD 0815-079S and extract its table
    const result = await page.evaluate(() => {
      const targetVoyage = '0815-079S';
      const htmlContent = document.documentElement.outerHTML;
      const voyageIndex = htmlContent.indexOf('EVER BUILD ' + targetVoyage);
      
      if (voyageIndex !== -1) {
        console.log('Found EVER BUILD 0815-079S at index:', voyageIndex);
        
        // Extract larger section to ensure we get the complete table
        const sectionStart = Math.max(0, voyageIndex - 3000);
        const sectionEnd = Math.min(htmlContent.length, voyageIndex + 12000);
        const voyageSection = htmlContent.substring(sectionStart, sectionEnd);
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = voyageSection;
        
        const tables = tempDiv.querySelectorAll('table');
        console.log(`Found ${tables.length} tables near voyage`);
        
        for (let t = 0; t < tables.length; t++) {
          const table = tables[t];
          const rows = table.querySelectorAll('tr');
          
          console.log(`\\n=== TABLE ${t + 1} ===`);
          console.log(`Rows: ${rows.length}`);
          
          // Look for the table that contains our voyage
          let hasOurVoyage = false;
          for (const row of rows) {
            if (row.textContent.includes(targetVoyage)) {
              hasOurVoyage = true;
              break;
            }
          }
          
          if (hasOurVoyage) {
            console.log('✅ This table contains our voyage!');
            
            // Extract all data from this table
            const tableData = [];
            for (let r = 0; r < rows.length; r++) {
              const cells = rows[r].querySelectorAll('td, th');
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              tableData.push(rowData);
              console.log(`Row ${r}:`, rowData);
            }
            
            // Find the port headers (look for row with LAEM CHABANG)
            let headerRow = -1;
            let arrRow = -1;
            let depRow = -1;
            
            for (let r = 0; r < tableData.length; r++) {
              const rowText = tableData[r].join(' ').toUpperCase();
              if (rowText.includes('LAEM') && rowText.includes('CHABANG')) {
                headerRow = r;
                console.log('Found header row with LAEM CHABANG at row:', r);
              }
              if (rowText.includes('ARR') && !rowText.includes('DEP')) {
                arrRow = r;
                console.log('Found ARR row at:', r);
              }
              if (rowText.includes('DEP') && !rowText.includes('ARR')) {
                depRow = r;
                console.log('Found DEP row at:', r);
              }
            }
            
            if (headerRow >= 0 && arrRow >= 0) {
              console.log('\\n🎯 FOUND COMPLETE TABLE STRUCTURE:');
              console.log('Header row:', tableData[headerRow]);
              console.log('ARR row:', tableData[arrRow]);
              
              // Find LAEM CHABANG column index
              const headerCells = tableData[headerRow];
              let laemChabangIndex = -1;
              
              for (let i = 0; i < headerCells.length; i++) {
                if (headerCells[i].toUpperCase().includes('LAEM') || 
                    headerCells[i].toUpperCase().includes('CHABANG')) {
                  laemChabangIndex = i;
                  console.log(`Found LAEM CHABANG at column ${i}: "${headerCells[i]}"`);
                  break;
                }
              }
              
              if (laemChabangIndex >= 0 && tableData[arrRow][laemChabangIndex]) {
                const laemChabangETA = tableData[arrRow][laemChabangIndex];
                console.log(`\\n🎯 LAEM CHABANG ETA: ${laemChabangETA}`);
                
                return {
                  success: true,
                  laemChabangETA: laemChabangETA,
                  headerRow: tableData[headerRow],
                  arrRow: tableData[arrRow],
                  laemChabangIndex: laemChabangIndex
                };
              }
            }
          }
        }
      }
      
      return { success: false, error: 'Could not find table structure' };
    });
    
    console.log('\\n📊 FINAL RESULT:');
    console.log(JSON.stringify(result, null, 2));
    
    await scraper.cleanup();
    
  } catch (error) {
    console.error('Error:', error);
    await scraper.cleanup();
  }
}

testLaemChabangExtraction();
