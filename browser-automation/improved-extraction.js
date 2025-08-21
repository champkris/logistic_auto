const puppeteer = require('puppeteer');

async function improvedShipmentLinkExtraction() {
  console.log('🚀 Improved ShipmentLink extraction for EVER BUILD 0815-079S...');
  
  const browser = await puppeteer.launch({
    headless: false, // Show browser
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  try {
    await page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    // Handle cookies
    await new Promise(resolve => setTimeout(resolve, 2000));
    try {
      await page.click('button[id*="accept"]');
      console.log('✅ Accepted cookies');
    } catch (error) {
      console.log('ℹ️ No cookie popup');
    }
    
    // Select EVER BUILD
    await page.waitForSelector('select', { timeout: 10000 });
    await page.select('select', 'BULD'); // Based on URL pattern in screenshot
    console.log('✅ Selected EVER BUILD');
    
    // Click search
    await new Promise(resolve => setTimeout(resolve, 1000));
    await page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search') || text.toLowerCase().includes('submit')) {
          button.click();
          return true;
        }
      }
      return false;
    });
    
    console.log('✅ Clicked search, waiting for results...');
    await new Promise(resolve => setTimeout(resolve, 4000));
    
    // Enhanced extraction based on screenshot structure
    const scheduleData = await page.evaluate(() => {
      console.log('🔍 Enhanced ShipmentLink extraction...');
      
      const targetVoyage = '0815-079S';
      const results = [];
      
      // Strategy 1: Look for voyage headings in the page
      const pageHTML = document.body.innerHTML;
      const voyagePattern = new RegExp(`EVER BUILD ${targetVoyage}`, 'gi');
      const hasTargetVoyage = voyagePattern.test(pageHTML);
      
      console.log(`Target voyage ${targetVoyage} found in HTML:`, hasTargetVoyage);
      
      if (hasTargetVoyage) {
        // Strategy 2: Find all table-like structures
        const allTables = document.querySelectorAll('table');
        console.log(`Found ${allTables.length} tables`);
        
        // Strategy 3: Look for text elements containing the voyage code
        const allElements = document.querySelectorAll('*');
        let voyageContext = null;
        
        for (const element of allElements) {
          const text = element.textContent;
          if (text.includes('EVER BUILD') && text.includes(targetVoyage)) {
            console.log('Found element with target voyage:', text.trim());
            voyageContext = element;
            
            // Get the parent container that likely contains the schedule
            let container = element.parentElement;
            let attempts = 0;
            
            while (container && attempts < 5) {
              const tables = container.querySelectorAll('table');
              if (tables.length > 0) {
                console.log(`Found ${tables.length} table(s) in container`);
                
                // Extract data from the first meaningful table
                const table = tables[0];
                const rows = table.querySelectorAll('tr');
                
                if (rows.length >= 2) {
                  const scheduleInfo = {
                    voyage: targetVoyage,
                    vessel: 'EVER BUILD',
                    table_rows: rows.length,
                    ports: [],
                    schedule: {}
                  };
                  
                  // Extract port names (usually in first row or header)
                  const headerRow = rows[0];
                  const headerCells = headerRow.querySelectorAll('td, th');
                  const ports = Array.from(headerCells).map(cell => {
                    const text = cell.textContent.trim();
                    // Clean up port names (remove extra spaces/formatting)
                    return text.replace(/\s+/g, ' ').trim();
                  }).filter(port => port.length > 0);
                  
                  scheduleInfo.ports = ports;
                  console.log('Extracted ports:', ports);
                  
                  // Extract ARR/DEP data from subsequent rows
                  for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    const cells = row.querySelectorAll('td, th');
                    const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                    
                    if (rowData.length > 0 && rowData[0]) {
                      const scheduleType = rowData[0]; // ARR or DEP
                      const dates = rowData.slice(1);
                      
                      if (scheduleType.includes('ARR') || scheduleType.includes('DEP')) {
                        scheduleInfo.schedule[scheduleType] = dates;
                        console.log(`${scheduleType}:`, dates);
                      }
                    }
                  }
                  
                  // Extract ETA for specific ports (Laem Chabang is likely index 1 based on screenshot)
                  if (scheduleInfo.schedule.ARR && scheduleInfo.ports.length > 1) {
                    const laemChabangIndex = scheduleInfo.ports.findIndex(port => 
                      port.toUpperCase().includes('LAEM') || port.toUpperCase().includes('CHABANG')
                    );
                    
                    if (laemChabangIndex >= 0 && scheduleInfo.schedule.ARR[laemChabangIndex]) {
                      const etaDate = scheduleInfo.schedule.ARR[laemChabangIndex];
                      console.log(`Found Laem Chabang ETA: ${etaDate}`);
                      scheduleInfo.laem_chabang_eta = etaDate;
                    }
                  }
                  
                  results.push(scheduleInfo);
                  break;
                }
              }
              container = container.parentElement;
              attempts++;
            }
            break;
          }
        }
      }
      
      // Strategy 4: Fallback - look for any tables with date patterns
      if (results.length === 0) {
        console.log('Fallback: searching all tables for date patterns...');
        
        const tables = document.querySelectorAll('table');
        for (let i = 0; i < tables.length; i++) {
          const table = tables[i];
          const tableText = table.textContent;
          
          // Look for tables that contain date patterns and ARR/DEP
          if (tableText.includes('ARR') && /\d{2}\/\d{2}/.test(tableText)) {
            console.log(`Table ${i + 1} contains schedule data`);
            
            const rows = table.querySelectorAll('tr');
            if (rows.length >= 2) {
              const fallbackInfo = {
                voyage: 'Unknown (fallback search)',
                vessel: 'EVER BUILD',
                table_index: i + 1,
                raw_content: tableText.substring(0, 300)
              };
              
              results.push(fallbackInfo);
            }
          }
        }
      }
      
      return results;
    });
    
    console.log('\n🔍 Enhanced Extraction Results:');
    console.log(JSON.stringify(scheduleData, null, 2));
    
    // Keep browser open for inspection
    console.log('\nBrowser staying open for 15 seconds...');
    await new Promise(resolve => setTimeout(resolve, 15000));
    
  } catch (error) {
    console.error('❌ Error:', error.message);
  }
  
  await browser.close();
}

improvedShipmentLinkExtraction().catch(console.error);