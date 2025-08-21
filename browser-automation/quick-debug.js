const puppeteer = require('puppeteer');

async function quickDebug() {
  console.log('🚀 Quick ShipmentLink Debug...');
  
  const browser = await puppeteer.launch({
    headless: true, // Keep headless for now
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
    
    // Select vessel
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
      const searchClicked = await page.evaluate(() => {
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
      
      if (searchClicked) {
        console.log('✅ Clicked search');
      } else {
        await page.keyboard.press('Enter');
        console.log('✅ Pressed Enter');
      }
      
      // Wait and check results
      await new Promise(resolve => setTimeout(resolve, 3000));
      
      const analysis = await page.evaluate(() => {
        const tables = document.querySelectorAll('table');
        const results = {
          tableCount: tables.length,
          hasEverBuild: document.body.textContent.toUpperCase().includes('EVER BUILD'),
          sampleTableData: []
        };
        
        // Check first table with data
        for (let i = 0; i < tables.length; i++) {
          const table = tables[i];
          const rows = table.querySelectorAll('tr');
          
          if (rows.length >= 2) {
            const tableInfo = {
              index: i,
              rowCount: rows.length,
              headers: [],
              firstDataRow: []
            };
            
            // Get headers
            const headerCells = rows[0].querySelectorAll('th, td');
            tableInfo.headers = Array.from(headerCells).map(cell => cell.textContent.trim());
            
            // Get first data row
            if (rows.length > 1) {
              const dataCells = rows[1].querySelectorAll('td, th');
              tableInfo.firstDataRow = Array.from(dataCells).map(cell => cell.textContent.trim());
            }
            
            results.sampleTableData.push(tableInfo);
            
            // Only analyze first few tables
            if (results.sampleTableData.length >= 3) break;
          }
        }
        
        return results;
      });
      
      console.log('\n🔍 Results Analysis:');
      console.log(`Tables found: ${analysis.tableCount}`);
      console.log(`EVER BUILD mentioned on page: ${analysis.hasEverBuild}`);
      
      analysis.sampleTableData.forEach(table => {
        console.log(`\nTable ${table.index + 1} (${table.rowCount} rows):`);
        console.log(`  Headers: ${table.headers.join(' | ')}`);
        console.log(`  First row: ${table.firstDataRow.join(' | ')}`);
      });
      
    } else {
      console.log('❌ EVER BUILD not found in dropdown');
    }
    
  } catch (error) {
    console.error('❌ Error:', error.message);
  }
  
  await browser.close();
}

quickDebug().catch(console.error);