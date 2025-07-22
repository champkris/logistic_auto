const puppeteer = require('puppeteer');

async function fixedVesselExtraction() {
  console.log('🛠️ Testing FIXED vessel extraction logic...');
  
  const browser = await puppeteer.launch({
    headless: false,
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  try {
    // Navigate and search (we know this works now)
    await page.goto('https://www.lcb1.com/BerthSchedule', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    console.log('📄 Page loaded');
    
    // Select MARSA PRIDE using the correct value
    await page.evaluate(() => {
      const select = document.querySelector('select');
      const options = Array.from(select.options);
      const marsaOption = options.find(opt => opt.text.includes('MARSA PRIDE'));
      if (marsaOption) {
        select.value = marsaOption.value;
        const changeEvent = new Event('change', { bubbles: true });
        select.dispatchEvent(changeEvent);
      }
    });
    console.log('✅ Selected MARSA PRIDE');
    
    // Click search
    await page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search')) {
          button.click();
          break;
        }
      }
    });
    console.log('🔍 Search clicked');
    
    // Wait for the table with id="grid" to appear
    await page.waitForSelector('#grid tbody tr', { timeout: 15000 });
    console.log('📊 Schedule table loaded!');
    
    // Extract data using the CORRECT table structure
    const extractedData = await page.evaluate(() => {
      console.log('🔍 Starting FIXED extraction...');
      
      // Strategy 1: Look specifically for the grid table
      const gridTable = document.querySelector('#grid');
      if (gridTable) {
        console.log('✅ Found #grid table');
        
        const results = [];
        const rows = gridTable.querySelectorAll('tbody tr');
        console.log(`Found ${rows.length} data rows`);
        
        rows.forEach((row, index) => {
          const cells = row.querySelectorAll('td');
          console.log(`Row ${index}: ${cells.length} cells`);
          
          if (cells.length >= 7) {
            const rowData = Array.from(cells).map(cell => cell.textContent.trim());
            console.log(`Row data:`, rowData);
            
            // Map columns according to the known structure
            const vesselData = {
              row_number: rowData[0],
              vessel_name: rowData[1], 
              voyage_in: rowData[2],
              voyage_out: rowData[3],
              berthing_time: rowData[4],
              departure_time: rowData[5],
              terminal: rowData[6],
              extraction_method: 'grid_table_fixed',
              raw_row_data: rowData
            };
            
            results.push(vesselData);
          }
        });
        
        return results;
      }
      
      // Strategy 2: Look for any table containing MARSA PRIDE
      console.log('⚠️ #grid table not found, trying fallback...');
      const allTables = document.querySelectorAll('table');
      console.log(`Found ${allTables.length} total tables`);
      
      for (let i = 0; i < allTables.length; i++) {
        const table = allTables[i];
        const tableText = table.textContent;
        
        if (tableText.includes('MARSA PRIDE')) {
          console.log(`✅ Found MARSA PRIDE in table ${i}`);
          
          const rows = table.querySelectorAll('tr');
          const results = [];
          
          rows.forEach((row, rowIndex) => {
            const cells = row.querySelectorAll('td, th');
            const rowData = Array.from(cells).map(cell => cell.textContent.trim());
            
            if (rowData.some(cell => cell.includes('MARSA PRIDE'))) {
              console.log(`MARSA PRIDE row data:`, rowData);
              
              // Try to intelligently map the columns
              let vesselData = {
                vessel_name: 'MARSA PRIDE',
                extraction_method: 'fallback_table_search',
                table_index: i,
                row_index: rowIndex,
                raw_row_data: rowData
              };
              
              // Look for voyage patterns (XXXs/XXXN)
              const voyageIn = rowData.find(cell => cell.match(/\d{3}S/));
              const voyageOut = rowData.find(cell => cell.match(/\d{3}N/));
              const terminal = rowData.find(cell => cell.match(/^[A-Z]\d+$/));
              const dates = rowData.filter(cell => cell.includes('/2025'));
              
              if (voyageIn) vesselData.voyage_in = voyageIn.trim();
              if (voyageOut) vesselData.voyage_out = voyageOut.trim();
              if (terminal) vesselData.terminal = terminal.trim();
              if (dates.length >= 2) {
                vesselData.berthing_time = dates[0].trim();
                vesselData.departure_time = dates[1].trim();
              } else if (dates.length === 1) {
                vesselData.berthing_time = dates[0].trim();
              }
              
              results.push(vesselData);
            }
          });
          
          if (results.length > 0) {
            return results;
          }
        }
      }
      
      console.log('❌ No vessel data found');
      return [];
    });
    
    console.log(`\n🎯 Extraction Results (${extractedData.length} entries):`);
    extractedData.forEach((data, i) => {
      console.log(`\n--- Result ${i + 1} ---`);
      console.log(`Method: ${data.extraction_method}`);
      console.log(`Vessel: ${data.vessel_name}`);
      console.log(`Voyage In: ${data.voyage_in || 'N/A'}`);
      console.log(`Voyage Out: ${data.voyage_out || 'N/A'}`);
      console.log(`Berthing: ${data.berthing_time || 'N/A'}`);
      console.log(`Departure: ${data.departure_time || 'N/A'}`);
      console.log(`Terminal: ${data.terminal || 'N/A'}`);
      console.log(`Raw Data: [${data.raw_row_data.join(', ')}]`);
    });
    
    // Format the result like the original scraper expects
    if (extractedData.length > 0) {
      const firstResult = extractedData[0];
      
      const formattedResult = {
        success: true,
        terminal: 'LCB1',
        vessel_name: firstResult.vessel_name,
        voyage_code: firstResult.voyage_in,
        voyage_out: firstResult.voyage_out,
        eta: parseDateTime(firstResult.berthing_time),
        etd: parseDateTime(firstResult.departure_time),
        terminal_berth: firstResult.terminal,
        raw_data: firstResult,
        scraped_at: new Date().toISOString(),
        source: 'lcb1_fixed_extraction'
      };
      
      console.log('\n🎉 FINAL FORMATTED RESULT:');
      console.log(JSON.stringify(formattedResult, null, 2));
      
    } else {
      console.log('\n❌ No data extracted');
    }
    
    // Take screenshot
    await page.screenshot({ 
      path: '/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/fixed-extraction-success.png',
      fullPage: true 
    });
    console.log('\n📸 Screenshot saved: fixed-extraction-success.png');
    
  } catch (error) {
    console.error('❌ Test error:', error.message);
  } finally {
    await browser.close();
  }
}

function parseDateTime(dateTimeString) {
  if (!dateTimeString) return null;
  
  // Handle format like "22/07/2025 - 04:00"
  const match = dateTimeString.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*[-–]\s*(\d{1,2}):(\d{2})/);
  if (match) {
    try {
      const day = parseInt(match[1]);
      const month = parseInt(match[2]) - 1; // JavaScript months are 0-indexed
      const year = parseInt(match[3]);
      const hour = parseInt(match[4]);
      const minute = parseInt(match[5]);
      
      const date = new Date(year, month, day, hour, minute);
      return date.toISOString().slice(0, 19).replace('T', ' '); // YYYY-MM-DD HH:MM:SS format
    } catch (parseError) {
      return null;
    }
  }
  
  return null;
}

fixedVesselExtraction().catch(console.error);