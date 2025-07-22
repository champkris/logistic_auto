const puppeteer = require('puppeteer');

async function debugTableExtraction() {
  console.log('🔍 Debug: Starting table extraction analysis...');
  
  const browser = await puppeteer.launch({
    headless: false, // Show browser for debugging
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  try {
    // Navigate to LCB1
    await page.goto('https://www.lcb1.com/BerthSchedule', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    console.log('📄 Page loaded, selecting MARSA PRIDE...');
    
    // Select MARSA PRIDE
    await page.select('select', 'MARSA PRIDE');
    console.log('✅ Selected MARSA PRIDE');
    
    // Click search button  
    await page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search') || text.toLowerCase().includes('submit')) {
          button.click();
          return;
        }
      }
    });
    
    console.log('🔍 Search clicked, waiting for results...');
    
    // Wait for results
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    // Debug: Get page HTML structure
    const tableDebug = await page.evaluate(() => {
      const debug = {
        totalTables: document.querySelectorAll('table').length,
        tableDetails: [],
        pageText: document.body.textContent.includes('MARSA PRIDE'),
        hasScheduleKeywords: false
      };
      
      // Check for schedule keywords
      const text = document.body.textContent.toLowerCase();
      debug.hasScheduleKeywords = text.includes('berthing') || text.includes('departure') || text.includes('terminal');
      
      // Analyze each table
      document.querySelectorAll('table').forEach((table, index) => {
        const rows = table.querySelectorAll('tr');
        const tableInfo = {
          tableIndex: index,
          totalRows: rows.length,
          tableHTML: table.outerHTML.substring(0, 500) + '...', // First 500 chars
          rows: []
        };
        
        // Get first few rows of data
        for (let i = 0; i < Math.min(5, rows.length); i++) {
          const cells = rows[i].querySelectorAll('td, th');
          const rowData = Array.from(cells).map(cell => cell.textContent.trim());
          tableInfo.rows.push({
            rowIndex: i,
            cellCount: cells.length,
            data: rowData
          });
        }
        
        debug.tableDetails.push(tableInfo);
      });
      
      return debug;
    });
    
    console.log('📊 Table Analysis Results:');
    console.log(`- Total tables found: ${tableDebug.totalTables}`);
    console.log(`- Page contains MARSA PRIDE: ${tableDebug.pageText}`);
    console.log(`- Has schedule keywords: ${tableDebug.hasScheduleKeywords}`);
    
    tableDebug.tableDetails.forEach(table => {
      console.log(`\n📋 Table ${table.tableIndex}:`);
      console.log(`  - Rows: ${table.totalRows}`);
      table.rows.forEach(row => {
        console.log(`  Row ${row.rowIndex} (${row.cellCount} cells):`, row.data);
      });
    });
    
    // Try to extract MARSA PRIDE data specifically
    const marsaData = await page.evaluate(() => {
      const results = [];
      const tables = document.querySelectorAll('table');
      
      for (const table of tables) {
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
          const cells = rows[i].querySelectorAll('td, th');
          const rowData = Array.from(cells).map(cell => cell.textContent.trim());
          
          // Look for MARSA PRIDE in any cell
          if (rowData.some(cell => cell.includes('MARSA PRIDE'))) {
            results.push({
              rowIndex: i,
              totalCells: cells.length,
              allData: rowData,
              potentialMapping: {
                vessel: rowData.find(cell => cell.includes('MARSA PRIDE')),
                possibleVoyage: rowData.filter(cell => cell.match(/\d{3}[SN]/)),
                possibleDates: rowData.filter(cell => cell.includes('/2025')),
                possibleTerminal: rowData.filter(cell => cell.match(/^[A-Z]\d+$/))
              }
            });
          }
        }
      }
      
      return results;
    });
    
    console.log('\n🚢 MARSA PRIDE Specific Data:');
    marsaData.forEach((data, index) => {
      console.log(`\nMatch ${index + 1}:`);
      console.log(`Row ${data.rowIndex} with ${data.totalCells} cells:`);
      console.log('All data:', data.allData);
      console.log('Potential mapping:', data.potentialMapping);
    });
    
    // Take screenshot for visual debugging
    await page.screenshot({ 
      path: '/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/debug-table-screenshot.png', 
      fullPage: true 
    });
    console.log('📸 Screenshot saved: debug-table-screenshot.png');
    
  } catch (error) {
    console.error('❌ Debug error:', error.message);
  } finally {
    await browser.close();
  }
}

// Run the debug
debugTableExtraction().catch(console.error);