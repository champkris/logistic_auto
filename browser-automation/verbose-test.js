const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

async function verboseScraperTest() {
  const scraper = new LCB1VesselScraper();
  
  console.log('🚢 Running verbose LCB1 scraper test for MARSA PRIDE...');
  
  try {
    await scraper.initialize();
    
    // Navigate and do the search
    await scraper.page.goto('https://www.lcb1.com/BerthSchedule', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    console.log('📄 Page loaded, selecting vessel...');
    
    await scraper.page.select('select', 'MARSA PRIDE');
    console.log('✅ Selected MARSA PRIDE');
    
    // Click search
    const searchButton = await scraper.page.evaluateHandle(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search') || 
            text.toLowerCase().includes('ค้นหา') ||
            text.toLowerCase().includes('submit')) {
          return button;
        }
      }
      return null;
    });
    
    if (searchButton) {
      await searchButton.click();
      console.log('🔍 Search clicked');
    }
    
    // Wait with multiple checks
    console.log('⏳ Waiting for results with verbose monitoring...');
    
    let attempts = 0;
    const maxAttempts = 15;
    
    while (attempts < maxAttempts) {
      await new Promise(resolve => setTimeout(resolve, 1000));
      attempts++;
      
      const pageAnalysis = await scraper.page.evaluate(() => {
        const analysis = {
          bodyText: document.body.textContent.substring(0, 500),
          hasMarsa: document.body.textContent.includes('MARSA PRIDE'),
          hasVoyageData: document.body.textContent.includes('528'),
          hasTerminal: document.body.textContent.includes('A0'),
          hasBerthing: document.body.textContent.includes('Berthing'),
          tableCount: document.querySelectorAll('table').length,
          tableWithData: false
        };
        
        // Check tables for actual data
        const tables = document.querySelectorAll('table');
        for (const table of tables) {
          const text = table.textContent;
          if (text.includes('MARSA PRIDE') && (text.includes('528') || text.includes('A0'))) {
            analysis.tableWithData = true;
            analysis.tableContent = text.substring(0, 300);
            break;
          }
        }
        
        // Look for the actual schedule data in any format
        const allText = document.body.textContent;
        const lines = allText.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        const marsaLines = lines.filter(l => l.includes('MARSA PRIDE'));
        
        analysis.marsaLines = marsaLines;
        analysis.marsaLinesCount = marsaLines.length;
        
        // Check for specific data patterns
        analysis.patterns = {
          voyage528S: allText.includes('528S'),
          voyage528N: allText.includes('528N'),
          date22_07: allText.includes('22/07/2025'),
          date23_07: allText.includes('23/07/2025'),
          time04_00: allText.includes('04:00'),
          time11_00: allText.includes('11:00'),
          terminalA0: allText.includes('A0')
        };
        
        return analysis;
      });
      
      console.log(`📊 Attempt ${attempts}:`);
      console.log(`  - Tables: ${pageAnalysis.tableCount}, With Data: ${pageAnalysis.tableWithData}`);
      console.log(`  - Has MARSA: ${pageAnalysis.hasMarsa}, Has 528: ${pageAnalysis.hasVoyageData}`);
      console.log(`  - Has Terminal A0: ${pageAnalysis.hasTerminal}, Has Berthing: ${pageAnalysis.hasBerthing}`);
      console.log(`  - MARSA lines found: ${pageAnalysis.marsaLinesCount}`);
      console.log(`  - Patterns: 528S=${pageAnalysis.patterns.voyage528S}, A0=${pageAnalysis.patterns.terminalA0}`);
      
      if (pageAnalysis.marsaLinesCount > 0) {
        console.log(`\n🚢 MARSA PRIDE lines found:`);
        pageAnalysis.marsaLines.forEach((line, i) => {
          console.log(`  ${i + 1}. ${line}`);
        });
      }
      
      // If we found the data we expect, break
      if (pageAnalysis.patterns.voyage528S && pageAnalysis.patterns.terminalA0) {
        console.log('🎯 Found complete schedule data!');
        
        // Take screenshot at this point
        await scraper.page.screenshot({ 
          path: '/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/verbose-success.png',
          fullPage: true 
        });
        
        // Now extract with the original method
        const extractedData = await scraper.page.evaluate((targetVessel) => {
          // This is the exact extraction logic from the original scraper
          const results = [];
          
          // Strategy 1: Look for tables with proper structure
          const tables = document.querySelectorAll('table');
          
          for (const table of tables) {
            const rows = table.querySelectorAll('tr');
            
            // Skip tables with too few rows
            if (rows.length < 2) continue;
            
            // Extract data rows
            for (let i = 0; i < rows.length; i++) {
              const cells = rows[i].querySelectorAll('td, th');
              
              if (cells.length >= 4) {
                const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                
                // Find vessel name column
                let vesselColumn = -1;
                for (let j = 0; j < Math.min(3, rowData.length); j++) {
                  if (rowData[j].toUpperCase().includes(targetVessel.toUpperCase())) {
                    vesselColumn = j;
                    break;
                  }
                }
                
                if (vesselColumn >= 0) {
                  console.log('Found MARSA PRIDE in table row:', rowData);
                  
                  // Determine column mapping
                  let mapping = {};
                  
                  if (cells.length >= 6) {
                    mapping = {
                      vessel_name: rowData[1] || rowData[vesselColumn],
                      voyage_in: rowData[2],
                      voyage_out: rowData[3], 
                      berthing_time: rowData[4],
                      departure_time: rowData[5],
                      terminal: rowData[6]
                    };
                  }
                  
                  results.push({
                    ...mapping,
                    raw_data: rowData,
                    table_columns: cells.length
                  });
                }
              }
            }
          }
          
          // Strategy 2: Fallback text search
          if (results.length === 0) {
            console.log('No table data found, using text search');
            const pageText = document.body.textContent;
            const lines = pageText.split('\n').map(line => line.trim()).filter(line => line.length > 0);
            
            for (const line of lines) {
              if (line.toUpperCase().includes(targetVessel.toUpperCase())) {
                results.push({
                  vessel_name: targetVessel,
                  raw_text: line,
                  note: 'Extracted from page text, manual parsing needed'
                });
              }
            }
          }
          
          return results;
        }, 'MARSA PRIDE');
        
        console.log(`\n📋 Final extraction results (${extractedData.length} entries):`);
        extractedData.forEach((data, i) => {
          console.log(`\nResult ${i + 1}:`);
          console.log(JSON.stringify(data, null, 2));
        });
        
        break;
      }
    }
    
  } catch (error) {
    console.error('❌ Verbose test error:', error.message);
  } finally {
    await scraper.cleanup();
  }
}

verboseScraperTest().catch(console.error);