const puppeteer = require('puppeteer');

async function debugShipmentLink() {
  console.log('🚀 Starting ShipmentLink Debug Session...');
  
  const browser = await puppeteer.launch({
    headless: false, // Show browser for debugging
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  try {
    console.log('📄 Loading ShipmentLink page...');
    await page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    await page.screenshot({ path: 'debug-01-initial-page.png', fullPage: true });
    console.log('📸 Screenshot saved: debug-01-initial-page.png');
    
    // Handle cookies
    console.log('🍪 Handling cookie consent...');
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    try {
      await page.click('button[id*="accept"]');
      console.log('✅ Accepted cookies');
      await new Promise(resolve => setTimeout(resolve, 1000));
    } catch (error) {
      console.log('ℹ️ No cookie popup found');
    }
    
    // Find and select vessel
    console.log('🔽 Looking for vessel dropdown...');
    await page.waitForSelector('select', { timeout: 10000 });
    
    const dropdowns = await page.$$('select');
    console.log(`📋 Found ${dropdowns.length} dropdown(s)`);
    
    if (dropdowns.length > 0) {
      // Get dropdown options
      const options = await dropdowns[0].$$eval('option', options => 
        options.map(option => ({
          value: option.value,
          text: option.textContent.trim()
        }))
      );
      
      console.log(`Dropdown has ${options.length} options`);
      
      // Find EVER BUILD
      const targetOption = options.find(option => 
        option.text.toUpperCase().includes('EVER BUILD')
      );
      
      if (targetOption) {
        console.log(`✅ Found vessel: ${targetOption.text}`);
        await page.select('select', targetOption.value);
        console.log('✅ Selected vessel');
        
        await page.screenshot({ path: 'debug-02-vessel-selected.png', fullPage: true });
        console.log('📸 Screenshot saved: debug-02-vessel-selected.png');
        
        // Click search button
        console.log('🔍 Looking for search button...');
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Try to find and click search button
        const searchClicked = await page.evaluate(() => {
          const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
          for (const button of buttons) {
            const text = button.textContent || button.value || '';
            if (text.toLowerCase().includes('search') || 
                text.toLowerCase().includes('submit') ||
                text.toLowerCase().includes('go')) {
              console.log('Found search button:', text);
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
          await page.keyboard.press('Enter');
        }
        
        // Wait for results
        console.log('⏳ Waiting for results...');
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        await page.screenshot({ path: 'debug-03-after-search.png', fullPage: true });
        console.log('📸 Screenshot saved: debug-03-after-search.png');
        
        // Debug: Check what's on the page now
        const pageContent = await page.evaluate(() => {
          console.log('🔍 Starting page analysis...');
          
          // Get all tables
          const tables = document.querySelectorAll('table');
          console.log(`Found ${tables.length} tables`);
          
          const results = {
            tables: [],
            pageText: document.body.textContent.substring(0, 1000),
            vesselMentions: []
          };
          
          // Analyze each table
          for (let i = 0; i < tables.length; i++) {
            const table = tables[i];
            const rows = table.querySelectorAll('tr');
            
            if (rows.length >= 2) {
              const tableData = {
                index: i,
                rowCount: rows.length,
                headers: [],
                sampleRows: []
              };
              
              // Get headers
              const headerRow = rows[0];
              const headerCells = headerRow.querySelectorAll('th, td');
              for (const cell of headerCells) {
                tableData.headers.push(cell.textContent.trim());
              }
              
              // Get first few data rows
              for (let r = 1; r < Math.min(rows.length, 4); r++) {
                const row = rows[r];
                const cells = row.querySelectorAll('td, th');
                const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                tableData.sampleRows.push(rowData);
              }
              
              results.tables.push(tableData);
            }
          }
          
          // Look for vessel mentions
          const pageText = document.body.textContent.toUpperCase();
          if (pageText.includes('EVER BUILD')) {
            results.vesselMentions.push('EVER BUILD found in page text');
          }
          if (pageText.includes('EVER')) {
            results.vesselMentions.push('EVER found in page text');
          }
          if (pageText.includes('BUILD')) {
            results.vesselMentions.push('BUILD found in page text');
          }
          
          return results;
        });
        
        console.log('\n🔍 Page Analysis Results:');
        console.log(`Tables found: ${pageContent.tables.length}`);
        
        pageContent.tables.forEach((table, index) => {
          console.log(`\nTable ${index + 1}:`);
          console.log(`  Rows: ${table.rowCount}`);
          console.log(`  Headers: [${table.headers.join(', ')}]`);
          console.log(`  Sample data:`);
          table.sampleRows.forEach((row, rowIndex) => {
            console.log(`    Row ${rowIndex + 1}: [${row.join(' | ')}]`);
          });
        });
        
        console.log(`\nVessel mentions: ${pageContent.vesselMentions.join(', ')}`);
        console.log(`\nPage text preview: ${pageContent.pageText.substring(0, 500)}...`);
        
      } else {
        console.log('❌ EVER BUILD not found in dropdown options');
        console.log('Available options:', options.slice(0, 10).map(o => o.text));
      }
    }
    
    console.log('\n🎯 Debug session complete. Check the screenshots and analysis above.');
    console.log('Press Enter to close browser...');
    
    // Keep browser open for manual inspection
    await new Promise(resolve => setTimeout(resolve, 5000));
    
  } catch (error) {
    console.error('❌ Error during debug:', error.message);
    await page.screenshot({ path: 'debug-error.png', fullPage: true });
  }
  
  await browser.close();
}

debugShipmentLink().catch(console.error);