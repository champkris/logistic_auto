const puppeteer = require('puppeteer');

async function testShipmentLinkVoyageExtraction() {
  console.log('🚀 Testing ShipmentLink voyage-specific extraction...');
  
  const browser = await puppeteer.launch({
    headless: false, // Show browser to see what's happening
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
    const options = await page.$$eval('select option', options => 
      options.map(option => ({
        value: option.value,
        text: option.textContent.trim()
      }))
    );
    
    const targetOption = options.find(option => 
      option.text.toUpperCase().includes('EVER BUILD')
    );
    
    if (targetOption) {
      console.log(`✅ Found vessel: ${targetOption.text}`);
      await page.select('select', targetOption.value);
      
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
      
      // Wait for results
      await new Promise(resolve => setTimeout(resolve, 4000));
      
      // Extract voyage-specific data
      const voyageData = await page.evaluate(() => {
        console.log('🔍 Starting voyage-specific extraction...');
        
        const targetVoyage = '0815-079S';
        const targetVessel = 'EVER BUILD';
        const results = [];
        
        // Look for voyage-specific headings
        const pageText = document.body.innerHTML;
        
        // Find all text that contains both vessel name and voyage code
        const voyagePattern = new RegExp(`${targetVessel}\\s+${targetVoyage.replace('-', '[-]?')}`, 'gi');
        const voyageMatches = pageText.match(voyagePattern);
        
        console.log('Voyage pattern matches:', voyageMatches);
        
        // More specific: look for the exact voyage heading
        const allElements = document.querySelectorAll('*');
        let voyageElement = null;
        
        for (const element of allElements) {
          const text = element.textContent.trim();
          if (text.includes(targetVessel) && text.includes(targetVoyage)) {
            console.log('Found voyage element:', text);
            voyageElement = element;
            break;
          }
        }
        
        if (voyageElement) {
          console.log('✅ Found voyage element, looking for associated table...');
          
          // Find the table that comes after this voyage heading
          let currentElement = voyageElement;
          let scheduleTable = null;
          
          // Look for the next table element (within reasonable distance)
          for (let i = 0; i < 20; i++) {
            currentElement = currentElement.nextElementSibling;
            if (!currentElement) break;
            
            if (currentElement.tagName === 'TABLE') {
              scheduleTable = currentElement;
              break;
            }
            
            // Also check if there are tables inside this element
            const nestedTable = currentElement.querySelector('table');
            if (nestedTable) {
              scheduleTable = nestedTable;
              break;
            }
          }
          
          if (scheduleTable) {
            console.log('✅ Found schedule table for voyage');
            
            // Extract table data
            const rows = scheduleTable.querySelectorAll('tr');
            console.log(`Table has ${rows.length} rows`);
            
            const tableData = {
              voyage: targetVoyage,
              vessel: targetVessel,
              headers: [],
              schedule: [],
              raw_table: scheduleTable.outerHTML.substring(0, 500) + '...'
            };
            
            // Get headers (usually port names)
            if (rows.length > 0) {
              const headerCells = rows[0].querySelectorAll('td, th');
              tableData.headers = Array.from(headerCells).map(cell => cell.textContent.trim());
            }
            
            // Get schedule rows (ARR/DEP)
            for (let i = 1; i < rows.length; i++) {
              const row = rows[i];
              const cells = row.querySelectorAll('td, th');
              const rowData = Array.from(cells).map(cell => cell.textContent.trim());
              
              if (rowData.length > 0) {
                tableData.schedule.push({
                  type: rowData[0], // ARR or DEP
                  dates: rowData.slice(1)
                });
              }
            }
            
            results.push(tableData);
          } else {
            console.log('⚠️ Could not find schedule table after voyage heading');
          }
        } else {
          console.log('❌ Could not find voyage-specific heading');
          
          // Fallback: look for any mention of the voyage code
          const allText = document.body.textContent;
          if (allText.includes(targetVoyage)) {
            console.log('✅ Voyage code found in page text');
            results.push({
              voyage: targetVoyage,
              vessel: targetVessel,
              found_in_text: true,
              note: 'Voyage code found but could not locate specific table structure'
            });
          }
        }
        
        return results;
      });
      
      console.log('\n🔍 Voyage Extraction Results:');
      console.log(JSON.stringify(voyageData, null, 2));
      
      // Keep browser open for manual inspection
      console.log('\nBrowser will stay open for 10 seconds for manual inspection...');
      await new Promise(resolve => setTimeout(resolve, 10000));
      
    } else {
      console.log('❌ EVER BUILD not found in dropdown');
    }
    
  } catch (error) {
    console.error('❌ Error:', error.message);
  }
  
  await browser.close();
}

testShipmentLinkVoyageExtraction().catch(console.error);