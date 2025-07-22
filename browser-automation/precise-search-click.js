const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

(async () => {
  console.log('🎯 PRECISE SEARCH BUTTON TARGETING');
  
  const scraper = new LCB1VesselScraper();
  await scraper.initialize();
  await scraper.browser.close();
  
  scraper.browser = await require('puppeteer').launch({
    headless: false,
    defaultViewport: { width: 1920, height: 1080 },
    slowMo: 1000,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  scraper.page = await scraper.browser.newPage();
  
  try {
    await scraper.page.goto('https://www.lcb1.com/BerthSchedule', { waitUntil: 'networkidle2' });
    await scraper.page.waitForSelector('select');
    
    const dropdowns = await scraper.page.$$('select');
    await dropdowns[0].select('MARSA PRIDE');
    console.log('✅ MARSA PRIDE selected');
    
    // Wait a moment for any JavaScript to process the selection
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Comprehensive button analysis
    const buttonAnalysis = await scraper.page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="submit"], input[type="button"], a');
      const buttonInfo = [];
      
      buttons.forEach((btn, index) => {
        const rect = btn.getBoundingClientRect();
        const style = window.getComputedStyle(btn);
        
        buttonInfo.push({
          index,
          tagName: btn.tagName,
          type: btn.type,
          className: btn.className,
          id: btn.id,
          textContent: btn.textContent?.trim(),
          value: btn.value,
          visible: rect.width > 0 && rect.height > 0,
          backgroundColor: style.backgroundColor,
          color: style.color,
          onclick: btn.onclick?.toString(),
          onclickAttr: btn.getAttribute('onclick')
        });
      });
      
      return buttonInfo;
    });
    
    console.log('\\n🔍 BUTTON ANALYSIS:');
    buttonAnalysis.forEach(btn => {
      if (btn.visible && (btn.textContent?.toLowerCase().includes('search') || 
          btn.value?.toLowerCase().includes('search') ||
          btn.className.includes('btn') ||
          btn.backgroundColor.includes('blue'))) {
        console.log(`\\n🎯 Candidate ${btn.index}:`);
        console.log(`  Tag: ${btn.tagName}`);
        console.log(`  Class: ${btn.className}`);
        console.log(`  Text: ${btn.textContent}`);
        console.log(`  Background: ${btn.backgroundColor}`);
        console.log(`  OnClick: ${btn.onclickAttr || btn.onclick}`);
      }
    });
    
    // Try multiple clicking strategies in sequence
    console.log('\\n🔄 TRYING MULTIPLE CLICK STRATEGIES...');
    
    // Strategy 1: Direct button click with text "Search"
    const strategy1 = await scraper.page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="submit"], input[type="button"]');
      for (const btn of buttons) {
        if (btn.textContent?.trim().toLowerCase() === 'search' || 
            btn.value?.toLowerCase() === 'search') {
          btn.click();
          return `Clicked: ${btn.textContent || btn.value} (${btn.className})`;
        }
      }
      return 'No exact search button found';
    });
    console.log('Strategy 1:', strategy1);
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Check if data loaded
    let dataLoaded = await scraper.page.evaluate(() => document.body.textContent.includes('Overview Calls'));
    if (dataLoaded) {
      console.log('🎉 Strategy 1 worked!');
    } else {
      console.log('Strategy 1 failed, trying Strategy 2...');
      
      // Strategy 2: Form submission
      const strategy2 = await scraper.page.evaluate(() => {
        const forms = document.querySelectorAll('form');
        if (forms.length > 0) {
          const form = forms[0];
          // Check if form has the vessel dropdown
          if (form.querySelector('select')) {
            const event = new Event('submit', { bubbles: true, cancelable: true });
            form.dispatchEvent(event);
            return `Submitted form with ${form.elements.length} elements`;
          }
        }
        return 'No suitable form found';
      });
      console.log('Strategy 2:', strategy2);
      await new Promise(resolve => setTimeout(resolve, 3000));
      
      dataLoaded = await scraper.page.evaluate(() => document.body.textContent.includes('Overview Calls'));
      if (dataLoaded) {
        console.log('🎉 Strategy 2 worked!');
      } else {
        console.log('Strategy 2 failed, trying Strategy 3...');
        
        // Strategy 3: Trigger change event + enter key
        await scraper.page.focus('select');
        await scraper.page.keyboard.press('Tab'); // Move to next element (likely the search button)
        await scraper.page.keyboard.press('Enter');
        console.log('Strategy 3: Tab + Enter');
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        dataLoaded = await scraper.page.evaluate(() => document.body.textContent.includes('Overview Calls'));
        if (dataLoaded) {
          console.log('🎉 Strategy 3 worked!');
        } else {
          console.log('Strategy 3 failed, trying Strategy 4...');
          
          // Strategy 4: Click blue button by style
          const strategy4 = await scraper.page.evaluate(() => {
            const buttons = document.querySelectorAll('button, input[type="submit"], input[type="button"]');
            for (const btn of buttons) {
              const style = window.getComputedStyle(btn);
              if (style.backgroundColor.includes('blue') || 
                  style.backgroundColor.includes('rgb(') ||
                  btn.className.includes('btn-primary') ||
                  btn.className.includes('btn-info')) {
                btn.click();
                return `Clicked blue button: ${btn.textContent} (${btn.className})`;
              }
            }
            return 'No blue button found';
          });
          console.log('Strategy 4:', strategy4);
          await new Promise(resolve => setTimeout(resolve, 3000));
          
          dataLoaded = await scraper.page.evaluate(() => document.body.textContent.includes('Overview Calls'));
        }
      }
    }
    
    // Final check
    const finalStatus = await scraper.page.evaluate(() => {
      const body = document.body.textContent;
      return {
        hasOverviewCalls: body.includes('Overview Calls'),
        has528S: body.includes('528S'),
        hasVoyageIn: body.includes('Voyage In'),
        bodyLength: body.length,
        tablesCount: document.querySelectorAll('table').length
      };
    });
    
    console.log('\\n📊 FINAL STATUS:');
    console.log(finalStatus);
    
    if (finalStatus.hasOverviewCalls && finalStatus.has528S) {
      console.log('\\n🎉 SUCCESS! Data is now loaded. Extracting...');
      
      // Extract the data
      const vesselData = await scraper.page.evaluate(() => {
        const tables = document.querySelectorAll('table');
        for (const table of tables) {
          if (table.textContent.includes('MARSA PRIDE') && table.textContent.includes('528')) {
            const rows = table.querySelectorAll('tr');
            for (const row of rows) {
              const cells = Array.from(row.querySelectorAll('td, th')).map(cell => cell.textContent.trim());
              if (cells.some(cell => cell.includes('MARSA PRIDE'))) {
                return {
                  success: true,
                  rawData: cells,
                  vessel_name: cells.find(cell => cell.includes('MARSA PRIDE')),
                  voyage_in: cells.find(cell => /^\\d+S$/.test(cell)),
                  voyage_out: cells.find(cell => /^\\d+N$/.test(cell)),
                  berthing_time: cells.find(cell => /^\\d{2}\\/\\d{2}\\/\\d{4}\\s*-\\s*\\d{2}:\\d{2}$/.test(cell)),
                  terminal: cells.find(cell => /^[A-Z]\\d+$/.test(cell))
                };
              }
            }
          }
        }
        return { success: false, error: 'Table not found' };
      });
      
      console.log('\\n🎯 EXTRACTED DATA:');
      console.log(JSON.stringify(vesselData, null, 2));
    } else {
      console.log('\\n❌ All strategies failed - manual inspection needed');
    }
    
    console.log('\\n👀 Browser staying open for 20 seconds for manual inspection...');
    await new Promise(resolve => setTimeout(resolve, 20000));
    
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await scraper.cleanup();
  }
})();
