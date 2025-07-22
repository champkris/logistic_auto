const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

(async () => {
  console.log('🎯 ROBUST TABLE EXTRACTION - Debugging mode');
  
  const scraper = new LCB1VesselScraper();
  
  // Initialize first, then replace with visible browser
  await scraper.initialize();
  await scraper.browser.close();
  
  scraper.browser = await require('puppeteer').launch({
    headless: false, // VISIBLE browser
    defaultViewport: { width: 1920, height: 1080 },
    slowMo: 500, // Slow down for visibility
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  scraper.page = await scraper.browser.newPage();
  
  try {
    // Navigate and search
    console.log('📄 Navigating to LCB1...');
    await scraper.page.goto('https://www.lcb1.com/BerthSchedule', { waitUntil: 'networkidle2' });
    
    console.log('🔽 Selecting MARSA PRIDE...');
    await scraper.page.waitForSelector('select');
    const dropdowns = await scraper.page.$$('select');
    await dropdowns[0].select('MARSA PRIDE');
    
    console.log('🔍 Clicking Search button...');
    const searchClicked = await scraper.page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="submit"], input[type="button"]');
      let clicked = false;
      
      for (const btn of buttons) {
        const text = (btn.textContent || btn.value || '').toLowerCase().trim();
        console.log('Found button:', text, 'className:', btn.className);
        
        if (text.includes('search') || btn.className.includes('btn')) {
          btn.click();
          console.log('Clicked button:', text);
          clicked = true;
          break;
        }
      }
      
      return clicked;
    });
    
    console.log('Search clicked:', searchClicked);
    
    if (searchClicked) {
      console.log('⏳ Waiting for results... (up to 60 seconds)');
      
      // Progressive waiting with status updates
      let found = false;
      for (let i = 0; i < 20; i++) { // 20 attempts, 3 seconds each = 60 seconds max
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        const status = await scraper.page.evaluate(() => {
          const body = document.body.textContent;
          return {
            bodyLength: body.length,
            hasOverview: body.includes('Overview'),
            hasOverviewCalls: body.includes('Overview Calls'),
            hasVoyageIn: body.includes('Voyage In'),
            hasBerthing: body.includes('Berthing'),
            has528S: body.includes('528S'),
            hasMarsa: body.includes('MARSA PRIDE'),
            tablesCount: document.querySelectorAll('table').length
          };
        });
        
        console.log(`Status check ${i + 1}:`, status);
        
        // Check if we have the data we expect
        if (status.hasOverviewCalls && status.hasVoyageIn && status.has528S) {
          console.log('🎉 Data detected! Proceeding with extraction...');
          found = true;
          break;
        }
        
        // Even if we don't have exact matches, proceed after reasonable time
        if (i >= 5 && status.bodyLength > 15000) {
          console.log('⚠️ Proceeding with extraction based on page size...');
          found = true;
          break;
        }
      }
      
      if (!found) {
        console.log('⚠️ Timeout waiting for results, but proceeding anyway...');
      }
      
      // Extract data regardless of wait result
      console.log('📊 Starting data extraction...');
      
      const extractionResult = await scraper.page.evaluate(() => {
        const results = {
          tablesFound: 0,
          vesselData: null,
          debugInfo: []
        };
        
        // Check all tables on the page
        const tables = document.querySelectorAll('table');
        results.tablesFound = tables.length;
        
        console.log('Found tables:', tables.length);
        
        for (let tableIndex = 0; tableIndex < tables.length; tableIndex++) {
          const table = tables[tableIndex];
          const tableText = table.textContent;
          
          console.log(`Table ${tableIndex + 1} text length:`, tableText.length);
          console.log(`Table ${tableIndex + 1} contains MARSA:`, tableText.includes('MARSA'));
          
          results.debugInfo.push({
            tableIndex: tableIndex + 1,
            textLength: tableText.length,
            containsMarsa: tableText.includes('MARSA'),
            containsVoyage: tableText.includes('Voyage'),
            textSample: tableText.substring(0, 200)
          });
          
          // If this table contains our vessel
          if (tableText.includes('MARSA PRIDE')) {
            console.log(`Processing table ${tableIndex + 1} with MARSA PRIDE`);
            
            const rows = table.querySelectorAll('tr');
            console.log('Rows in this table:', rows.length);
            
            // Process all rows to find our data
            for (let rowIndex = 0; rowIndex < rows.length; rowIndex++) {
              const row = rows[rowIndex];
              const cells = row.querySelectorAll('td, th');
              
              if (cells.length > 0) {
                const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                console.log(`Row ${rowIndex}:`, rowData);
                
                // Check if this row contains MARSA PRIDE data
                if (rowData.some(cell => cell.includes('MARSA PRIDE'))) {
                  console.log('Found MARSA PRIDE data row:', rowData);
                  
                  // Extract the data based on position
                  results.vesselData = {
                    raw_row: rowData,
                    vessel_name: rowData.find(cell => cell.includes('MARSA PRIDE')) || 'MARSA PRIDE',
                    voyage_in: rowData.find(cell => /^\d+[A-Z]$/.test(cell)) || null,
                    // Look for patterns in the row data
                    all_cells: rowData,
                    row_length: rowData.length
                  };
                  
                  // Try to identify specific data by patterns
                  rowData.forEach((cell, cellIndex) => {
                    if (/^\d{2}\/\d{2}\/\d{4}\s*-\s*\d{2}:\d{2}$/.test(cell)) {
                      if (!results.vesselData.berthing_time) {
                        results.vesselData.berthing_time = cell;
                      } else if (!results.vesselData.departure_time) {
                        results.vesselData.departure_time = cell;
                      }
                    }
                    if (/^[A-Z]\d+$/.test(cell)) {
                      results.vesselData.terminal = cell;
                    }
                    if (/^\d{3}[A-Z]$/.test(cell)) {
                      if (cell.endsWith('S')) {
                        results.vesselData.voyage_in = cell;
                      } else if (cell.endsWith('N')) {
                        results.vesselData.voyage_out = cell;
                      }
                    }
                  });
                  
                  break;
                }
              }
            }
          }
        }
        
        return results;
      });
      
      console.log('\\n🔍 EXTRACTION RESULTS:');
      console.log('Tables found:', extractionResult.tablesFound);
      
      console.log('\\n📋 DEBUG INFO:');
      extractionResult.debugInfo.forEach(info => {
        console.log(`Table ${info.tableIndex}:`, {
          textLength: info.textLength,
          containsMarsa: info.containsMarsa,
          containsVoyage: info.containsVoyage,
          sample: info.textSample
        });
      });
      
      if (extractionResult.vesselData) {
        console.log('\\n🎉 VESSEL DATA FOUND:');
        console.log(JSON.stringify(extractionResult.vesselData, null, 2));
      } else {
        console.log('\\n❌ No vessel data found');
      }
      
      // Keep browser open for manual inspection
      console.log('\\n👀 Browser staying open for 15 seconds for manual inspection...');
      await new Promise(resolve => setTimeout(resolve, 15000));
    }
    
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await scraper.cleanup();
  }
})();
