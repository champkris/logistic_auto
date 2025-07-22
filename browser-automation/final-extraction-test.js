const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

(async () => {
  console.log('🎯 TARGETED DATA EXTRACTION TEST');
  
  const scraper = new LCB1VesselScraper();
  await scraper.initialize();
  
  try {
    // Perform the search as usual
    await scraper.page.goto('https://www.lcb1.com/BerthSchedule', { waitUntil: 'networkidle2' });
    await scraper.page.waitForSelector('select');
    const dropdowns = await scraper.page.$$('select');
    await dropdowns[0].select('MARSA PRIDE');
    
    // Click search 
    await scraper.page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = (button.textContent || button.value || '').toLowerCase();
        if (text.includes('search') || text.includes('submit')) {
          button.click();
          return true;
        }
      }
    });
    
    // Wait for results with our enhanced timing
    await new Promise(resolve => setTimeout(resolve, 8000));
    
    // COMPREHENSIVE DATA EXTRACTION
    const completeExtraction = await scraper.page.evaluate(() => {
      const results = {
        method1_tables: [],
        method2_divs: [],
        method3_text_analysis: null,
        method4_all_elements: []
      };
      
      // Method 1: Enhanced table extraction
      const tables = document.querySelectorAll('table, div[role="table"], .table');
      tables.forEach((table, index) => {
        if (table.textContent.includes('MARSA') || 
            table.textContent.includes('Berthing') ||
            table.textContent.includes('Voyage')) {
          
          const tableData = {
            index,
            tagName: table.tagName,
            className: table.className,
            id: table.id,
            textContent: table.textContent.trim(),
            innerHTML: table.innerHTML
          };
          
          results.method1_tables.push(tableData);
        }
      });
      
      // Method 2: Look for div-based tables or grids
      const divs = document.querySelectorAll('div');
      divs.forEach((div, index) => {
        const text = div.textContent;
        if (text.includes('MARSA PRIDE') && 
            (text.includes('528') || text.includes('A0') || text.includes('Berthing'))) {
          
          results.method2_divs.push({
            index,
            className: div.className,
            id: div.id,
            textContent: text.trim().substring(0, 500)
          });
        }
      });
      
      // Method 3: Text pattern analysis around MARSA PRIDE
      const fullText = document.body.textContent;
      const marsaIndex = fullText.indexOf('MARSA PRIDE');
      if (marsaIndex !== -1) {
        const surrounding = fullText.substring(marsaIndex - 500, marsaIndex + 500);
        
        // Look for patterns like dates, times, codes
        const patterns = {
          dates: surrounding.match(/\d{2}\/\d{2}\/\d{4}/g) || [],
          times: surrounding.match(/\d{2}:\d{2}/g) || [],
          voyageCodes: surrounding.match(/\d{3}[A-Z]/g) || [],
          terminals: surrounding.match(/[A-Z]\d+/g) || [],
          fullContext: surrounding
        };
        
        results.method3_text_analysis = patterns;
      }
      
      // Method 4: Look for any element containing structured data
      const allElements = document.querySelectorAll('*');
      allElements.forEach(el => {
        if (el.textContent.includes('MARSA PRIDE') && 
            el.children.length === 0 && // Leaf element
            el.textContent.length > 20 && el.textContent.length < 200) {
          
          results.method4_all_elements.push({
            tagName: el.tagName,
            className: el.className,
            id: el.id,
            textContent: el.textContent.trim()
          });
        }
      });
      
      return results;
    });
    
    console.log('\\n🔍 COMPREHENSIVE EXTRACTION RESULTS:');
    console.log('=====================================');
    
    console.log('\n📋 Method 1 - Table Extraction:');
    completeExtraction.method1_tables.forEach((table, i) => {
      console.log(`Table ${i + 1}: ${table.tagName} (${table.className})`);
      console.log('Content:', table.textContent.substring(0, 200) + '...');
    });
    
    console.log('\n📦 Method 2 - Div Extraction:');
    completeExtraction.method2_divs.forEach((div, i) => {
      console.log(`Div ${i + 1}: ${div.className}`);
      console.log('Content:', div.textContent);
    });
    
    console.log('\n🔤 Method 3 - Text Pattern Analysis:');
    if (completeExtraction.method3_text_analysis) {
      const patterns = completeExtraction.method3_text_analysis;
      console.log('Dates found:', patterns.dates);
      console.log('Times found:', patterns.times);
      console.log('Voyage codes found:', patterns.voyageCodes);
      console.log('Terminals found:', patterns.terminals);
      console.log('\nFull context around MARSA PRIDE:');
      console.log(patterns.fullContext);
    }
    
    console.log('\n📄 Method 4 - All Elements:');
    completeExtraction.method4_all_elements.forEach((el, i) => {
      console.log(`Element ${i + 1}: ${el.tagName} (${el.className})`);
      console.log('Content:', el.textContent);
    });
    
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await scraper.cleanup();
  }
})();
