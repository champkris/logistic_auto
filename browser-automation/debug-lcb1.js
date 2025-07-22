const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

(async () => {
  const scraper = new LCB1VesselScraper();
  await scraper.initialize();
  console.log('🚢 Debug mode: Analyzing page structure...');
  
  try {
    // Navigate and perform the selection
    await scraper.page.goto('https://www.lcb1.com/BerthSchedule', { waitUntil: 'networkidle2' });
    await scraper.page.waitForSelector('select');
    const dropdowns = await scraper.page.$$('select');
    await dropdowns[0].select('MARSA PRIDE');
    
    // Find and click search button
    const searchClicked = await scraper.page.evaluate(() => {
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
    
    console.log('Search button clicked:', searchClicked);
    
    // Wait for results with multiple strategies
    await new Promise(resolve => setTimeout(resolve, 8000));
    
    // Debug: Analyze the page structure
    const debugInfo = await scraper.page.evaluate(() => {
      const info = {
        totalTables: document.querySelectorAll('table').length,
        tables: [],
        bodyLength: document.body.textContent.length,
        containsMarsa: document.body.textContent.includes('MARSA PRIDE'),
        containsBerthing: document.body.textContent.includes('Berthing Time')
      };
      
      document.querySelectorAll('table').forEach((table, index) => {
        const rows = table.querySelectorAll('tr');
        const tableInfo = {
          index: index,
          rows: rows.length,
          columns: rows[0] ? rows[0].querySelectorAll('td, th').length : 0,
          firstRowData: [],
          secondRowData: [],
          allRowsData: [],
          containsMarsa: table.textContent.includes('MARSA PRIDE')
        };
        
        if (rows.length > 0) {
          const firstRow = rows[0].querySelectorAll('td, th');
          tableInfo.firstRowData = Array.from(firstRow).map(cell => cell.textContent.trim());
        }
        
        if (rows.length > 1) {
          const secondRow = rows[1].querySelectorAll('td, th');
          tableInfo.secondRowData = Array.from(secondRow).map(cell => cell.textContent.trim());
        }
        
        // Get all rows data for complete analysis
        for (let i = 0; i < Math.min(5, rows.length); i++) {
          const cells = rows[i].querySelectorAll('td, th');
          const rowData = Array.from(cells).map(cell => cell.textContent.trim());
          tableInfo.allRowsData.push(rowData);
        }
        
        info.tables.push(tableInfo);
      });
      
      return info;
    });
    
    console.log('\n🔍 PAGE STRUCTURE DEBUG:');
    console.log('Total tables found:', debugInfo.totalTables);
    console.log('Body text length:', debugInfo.bodyLength);
    console.log('Contains MARSA PRIDE:', debugInfo.containsMarsa);
    console.log('Contains Berthing Time:', debugInfo.containsBerthing);
    
    debugInfo.tables.forEach(table => {
      console.log('\n📋 Table', table.index + 1, ':');
      console.log('  Rows:', table.rows);
      console.log('  Columns:', table.columns);
      console.log('  Contains MARSA:', table.containsMarsa);
      console.log('  First row:', table.firstRowData);
      console.log('  Second row:', table.secondRowData);
      
      if (table.containsMarsa) {
        console.log('  🎯 ALL ROWS DATA:');
        table.allRowsData.forEach((row, index) => {
          console.log(`    Row ${index}:`, row);
        });
      }
    });
    
    // Take a screenshot for visual debugging
    await scraper.page.screenshot({ path: 'lcb1-debug-screenshot.png', fullPage: true });
    console.log('\n📸 Screenshot saved as lcb1-debug-screenshot.png');
    
  } catch (error) {
    console.error('Debug error:', error.message);
  } finally {
    await scraper.cleanup();
  }
})();
