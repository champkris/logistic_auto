const puppeteer = require('puppeteer');

async function focusedVesselTest() {
  console.log('🎯 Focused Vessel Test - MARSA PRIDE data extraction');
  
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
    
    console.log('📄 Page loaded');
    
    // Select MARSA PRIDE
    await page.select('select', 'MARSA PRIDE');
    console.log('✅ Selected MARSA PRIDE');
    
    // Use the same search method as the working scraper
    const searchClicked = await page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search') || 
            text.toLowerCase().includes('ค้นหา') ||
            text.toLowerCase().includes('submit')) {
          button.click();
          console.log('Found and clicked search button:', text);
          return true;
        }
      }
      return false;
    });
    
    if (searchClicked) {
      console.log('🔍 Search button clicked successfully');
    } else {
      console.log('⚠️ No search button found, trying Enter key');
      await page.focus('select');
      await page.keyboard.press('Enter');
    }
    
    // Wait for AJAX response with better detection
    console.log('⏳ Waiting for schedule data...');
    
    // Wait for the content to contain actual schedule data
    await page.waitForFunction(() => {
      const text = document.body.textContent;
      return text.includes('528S') || 
             text.includes('Berthing Time') || 
             (text.includes('MARSA PRIDE') && text.includes('A0'));
    }, { timeout: 20000 });
    
    console.log('📊 Schedule data loaded!');
    
    // Extract the data using multiple strategies
    const scheduleData = await page.evaluate(() => {
      console.log('🔍 Starting data extraction...');
      
      const results = {
        strategy_used: '',
        vessel_data: null,
        raw_content: '',
        all_text_containing_marsa: [],
        table_analysis: []
      };
      
      // Strategy 1: Look for tables again (maybe they loaded now)
      const tables = document.querySelectorAll('table');
      console.log(`Found ${tables.length} tables`);
      
      for (let i = 0; i < tables.length; i++) {
        const table = tables[i];
        const rows = table.querySelectorAll('tr');
        const tableData = [];
        
        for (let j = 0; j < rows.length; j++) {
          const cells = rows[j].querySelectorAll('td, th');
          const rowData = Array.from(cells).map(cell => cell.textContent.trim());
          if (rowData.some(cell => cell.length > 0)) {
            tableData.push(rowData);
          }
        }
        
        results.table_analysis.push({
          tableIndex: i,
          rows: tableData,
          containsMarsa: tableData.some(row => row.some(cell => cell.includes('MARSA PRIDE')))
        });
        
        // Check if this table contains MARSA PRIDE
        if (tableData.some(row => row.some(cell => cell.includes('MARSA PRIDE')))) {
          console.log('Found MARSA PRIDE in table', i);
          
          // Find the row with MARSA PRIDE
          const marsaRow = tableData.find(row => row.some(cell => cell.includes('MARSA PRIDE')));
          if (marsaRow && marsaRow.length >= 6) {
            results.strategy_used = 'table_extraction';
            results.vessel_data = {
              vessel_name: marsaRow.find(cell => cell.includes('MARSA PRIDE')),
              voyage_in: marsaRow.find(cell => cell.match(/\d{3}S/)),
              voyage_out: marsaRow.find(cell => cell.match(/\d{3}N/)),
              berthing_time: marsaRow.find(cell => cell.includes('2025') && cell.includes(':')),
              departure_time: marsaRow.filter(cell => cell.includes('2025') && cell.includes(':')).slice(-1)[0],
              terminal: marsaRow.find(cell => cell.match(/^[A-Z]\d+$/)),
              full_row: marsaRow
            };
            console.log('Extracted vessel data:', results.vessel_data);
            return results;
          }
        }
      }
      
      // Strategy 2: Text-based extraction with regex patterns
      const pageText = document.body.textContent;
      results.raw_content = pageText.substring(0, 2000); // First 2000 chars
      
      // Look for all lines containing MARSA PRIDE
      const lines = pageText.split('\n').map(line => line.trim()).filter(line => line.length > 0);
      results.all_text_containing_marsa = lines.filter(line => line.includes('MARSA PRIDE'));
      
      // Try to extract structured data from text
      const marsaLines = results.all_text_containing_marsa;
      for (const line of marsaLines) {
        // Look for patterns like: MARSA PRIDE 528S 528N 22/07/2025 - 04:00 23/07/2025 - 11:00 A0
        const voyageMatch = line.match(/(\d{3}[SN])/g);
        const dateMatch = line.match(/(\d{1,2}\/\d{1,2}\/\d{4})/g);
        const timeMatch = line.match(/(\d{1,2}:\d{2})/g);
        const terminalMatch = line.match(/([A-Z]\d+)/);
        
        if (voyageMatch || dateMatch || terminalMatch) {
          results.strategy_used = 'text_pattern_extraction';
          results.vessel_data = {
            vessel_name: 'MARSA PRIDE',
            voyage_in: voyageMatch ? voyageMatch[0] : null,
            voyage_out: voyageMatch ? voyageMatch[1] : null,
            dates_found: dateMatch,
            times_found: timeMatch,
            terminal: terminalMatch ? terminalMatch[0] : null,
            source_line: line
          };
          console.log('Text extraction result:', results.vessel_data);
          break;
        }
      }
      
      return results;
    });
    
    console.log('\n🎯 Extraction Results:');
    console.log('Strategy used:', scheduleData.strategy_used);
    console.log('Vessel data:', scheduleData.vessel_data);
    console.log('\n📋 Table Analysis:');
    scheduleData.table_analysis.forEach((table, i) => {
      console.log(`Table ${i} (contains MARSA: ${table.containsMarsa}):`);
      table.rows.forEach((row, j) => {
        if (row.some(cell => cell.length > 0)) {
          console.log(`  Row ${j}:`, row);
        }
      });
    });
    
    console.log('\n📝 MARSA PRIDE text matches:');
    scheduleData.all_text_containing_marsa.forEach((line, i) => {
      console.log(`${i + 1}. ${line}`);
    });
    
    // Take final screenshot
    await page.screenshot({ 
      path: '/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/debug-final-result.png',
      fullPage: true 
    });
    console.log('📸 Final screenshot saved');
    
  } catch (error) {
    console.error('❌ Test error:', error.message);
    
    // Take error screenshot
    await page.screenshot({ 
      path: '/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/debug-error.png',
      fullPage: true 
    });
  } finally {
    await browser.close();
  }
}

focusedVesselTest().catch(console.error);