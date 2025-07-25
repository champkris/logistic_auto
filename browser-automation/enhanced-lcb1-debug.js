const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

(async () => {
  const scraper = new LCB1VesselScraper();
  await scraper.initialize();
  console.log('🔬 Enhanced LCB1 Debug - Investigating Ajax/Schedule Loading...');
  
  try {
    // Navigate to the page
    await scraper.page.goto('https://www.lcb1.com/BerthSchedule', { waitUntil: 'networkidle2' });
    console.log('📄 Page loaded');
    
    // Set up network monitoring to catch AJAX requests
    const responses = [];
    scraper.page.on('response', response => {
      responses.push({
        url: response.url(),
        status: response.status(),
        headers: response.headers()
      });
    });
    
    // Wait for and interact with the dropdown
    await scraper.page.waitForSelector('select');
    const dropdowns = await scraper.page.$$('select');
    
    console.log('🔍 Selecting MARSA PRIDE from dropdown...');
    
    // Check what options are available first
    const options = await scraper.page.evaluate(() => {
      const select = document.querySelector('select');
      return Array.from(select.options).map(opt => ({
        value: opt.value,
        text: opt.textContent.trim()
      }));
    });
    
    console.log('📋 Available dropdown options:');
    options.forEach((opt, index) => {
      console.log(`  ${index}: "${opt.text}" (value: "${opt.value}")`);
    });
    
    // Find MARSA PRIDE option
    const marsaPrideOption = options.find(opt => opt.text.includes('MARSA PRIDE'));
    if (!marsaPrideOption) {
      console.log('❌ MARSA PRIDE not found in dropdown options');
      return;
    }
    
    console.log(`✅ Found MARSA PRIDE: "${marsaPrideOption.text}" (value: "${marsaPrideOption.value}")`);
    
    // Select MARSA PRIDE
    await scraper.page.select('select', marsaPrideOption.value);
    console.log('✅ MARSA PRIDE selected');
    
    // Wait a bit for any immediate changes
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Check if anything changed after selection
    const afterSelectionContent = await scraper.page.evaluate(() => {
      return {
        bodyLength: document.body.textContent.length,
        tablesCount: document.querySelectorAll('table').length,
        hasScheduleGrid: !!document.querySelector('#grid'),
        hasDataRows: document.querySelectorAll('tbody tr').length
      };
    });
    
    console.log('📊 After selection:', afterSelectionContent);
    
    // Try different search button strategies
    console.log('🔍 Looking for search button...');
    
    const searchStrategies = [
      // Strategy 1: Button with specific attributes
      { selector: 'button[type="submit"]', name: 'Submit button' },
      { selector: 'input[type="submit"]', name: 'Submit input' },
      { selector: 'button[onclick*="search"]', name: 'Button with search onclick' },
      { selector: 'input[onclick*="search"]', name: 'Input with search onclick' },
      
      // Strategy 2: Button by text content
      { 
        selector: null, 
        name: 'Button by text content',
        action: async () => {
          return await scraper.page.evaluate(() => {
            const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
            for (const button of buttons) {
              const text = (button.textContent || button.value || '').toLowerCase();
              if (text.includes('search') || text.includes('submit') || text.includes('ค้นหา')) {
                button.click();
                return `Clicked button with text: "${button.textContent || button.value}"`;
              }
            }
            return 'No search button found by text';
          });
        }
      },
      
      // Strategy 3: Form submission
      { 
        selector: 'form', 
        name: 'Form submission',
        action: async () => {
          const forms = await scraper.page.$$('form');
          if (forms.length > 0) {
            await forms[0].evaluate(form => form.submit());
            return 'Form submitted';
          }
          return 'No forms found';
        }
      }
    ];
    
    let searchExecuted = false;
    
    for (const strategy of searchStrategies) {
      try {
        if (strategy.selector) {
          const element = await scraper.page.$(strategy.selector);
          if (element) {
            await element.click();
            console.log(`✅ ${strategy.name}: Clicked`);
            searchExecuted = true;
            break;
          } else {
            console.log(`❌ ${strategy.name}: Not found`);
          }
        } else if (strategy.action) {
          const result = await strategy.action();
          console.log(`🔄 ${strategy.name}: ${result}`);
          if (result.includes('Clicked')) {
            searchExecuted = true;
            break;
          }
        }
      } catch (error) {
        console.log(`❌ ${strategy.name}: Error - ${error.message}`);
      }
    }
    
    if (!searchExecuted) {
      console.log('⚠️ No search method worked, trying Enter key...');
      await scraper.page.focus('select');
      await scraper.page.keyboard.press('Enter');
      searchExecuted = true;
    }
    
    // Monitor network requests after search
    console.log('⏳ Waiting for response after search...');
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    // Log all network requests that happened
    console.log('\n📡 Network requests captured:');
    responses.forEach((resp, index) => {
      if (!resp.url.includes('favicon') && !resp.url.includes('.css') && !resp.url.includes('.js')) {
        console.log(`  ${index}: ${resp.status} ${resp.url}`);
      }
    });
    
    // Extended wait with periodic checks
    console.log('⏳ Extended wait with periodic content checks...');
    
    for (let i = 0; i < 10; i++) {
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      const contentCheck = await scraper.page.evaluate((iterationNum) => {
        const grid = document.querySelector('#grid');
        const dataRows = document.querySelectorAll('tbody tr');
        const bodyText = document.body.textContent;
        
        return {
          iteration: iterationNum + 1,
          hasGrid: !!grid,
          dataRowsCount: dataRows.length,
          containsVoyage528: bodyText.includes('528'),
          containsBerthing: bodyText.includes('Berthing'),
          bodyLength: bodyText.length,
          recentText: bodyText.slice(-200) // Last 200 chars to see what's new
        };
      }, i);
      
      console.log(`📊 Check ${contentCheck.iteration}:`, contentCheck);
      
      // If we find meaningful data, break early
      if (contentCheck.hasGrid || contentCheck.dataRowsCount > 0 || contentCheck.containsVoyage528) {
        console.log('✅ Meaningful data detected, proceeding with extraction...');
        break;
      }
    }
    
    // Final data extraction attempt
    console.log('\n🎯 Final data extraction attempt...');
    
    const finalData = await scraper.page.evaluate(() => {
      const results = {
        tables: [],
        gridTable: null,
        textContent: document.body.textContent,
        foundVoyage528: false,
        foundBerthing: false
      };
      
      // Check all tables
      document.querySelectorAll('table').forEach((table, index) => {
        const rows = table.querySelectorAll('tr');
        const tableData = {
          index: index,
          rows: rows.length,
          id: table.id || 'no-id',
          classes: table.className || 'no-class',
          data: []
        };
        
        rows.forEach(row => {
          const cells = row.querySelectorAll('td, th');
          const rowData = Array.from(cells).map(cell => cell.textContent.trim());
          if (rowData.some(cell => cell.length > 0)) {
            tableData.data.push(rowData);
          }
        });
        
        results.tables.push(tableData);
        
        if (table.id === 'grid') {
          results.gridTable = tableData;
        }
      });
      
      // Text search for voyage and berthing info
      results.foundVoyage528 = results.textContent.toLowerCase().includes('528');
      results.foundBerthing = results.textContent.toLowerCase().includes('berthing');
      
      // Look for MARSA PRIDE specific content
      if (results.textContent.toUpperCase().includes('MARSA PRIDE')) {
        const lines = results.textContent.split('\n');
        results.marsaPrideLines = lines.filter(line => 
          line.toUpperCase().includes('MARSA PRIDE') || 
          line.includes('528')
        ).map(line => line.trim()).filter(line => line.length > 0);
      }
      
      return results;
    });
    
    console.log('\n📋 FINAL EXTRACTION RESULTS:');
    console.log(`Tables found: ${finalData.tables.length}`);
    console.log(`Grid table: ${finalData.gridTable ? 'Found' : 'Not found'}`);
    console.log(`Contains voyage 528: ${finalData.foundVoyage528}`);
    console.log(`Contains berthing: ${finalData.foundBerthing}`);
    
    if (finalData.marsaPrideLines) {
      console.log('\n🚢 MARSA PRIDE related content:');
      finalData.marsaPrideLines.forEach(line => console.log(`  "${line}"`));
    }
    
    if (finalData.gridTable) {
      console.log('\n📊 Grid table data:');
      finalData.gridTable.data.forEach((row, index) => {
        console.log(`  Row ${index}:`, row);
      });
    }
    
    // Take final screenshot
    await scraper.page.screenshot({ path: 'lcb1-enhanced-debug.png', fullPage: true });
    console.log('\n📸 Enhanced debug screenshot saved as lcb1-enhanced-debug.png');
    
  } catch (error) {
    console.error('❌ Enhanced debug error:', error.message);
    await scraper.page.screenshot({ path: 'lcb1-enhanced-error.png', fullPage: true });
  } finally {
    await scraper.cleanup();
  }
})();