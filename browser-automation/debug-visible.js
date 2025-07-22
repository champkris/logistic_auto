const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

(async () => {
  const scraper = new LCB1VesselScraper();
  await scraper.initialize();
  
  // Make browser visible for debugging
  await scraper.browser.close();
  scraper.browser = await require('puppeteer').launch({
    headless: false, // VISIBLE browser for debugging
    defaultViewport: { width: 1920, height: 1080 },
    slowMo: 1000, // Slow down actions for visibility
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  scraper.page = await scraper.browser.newPage();
  
  console.log('🚢 Testing with VISIBLE browser - watch the automation!');
  
  try {
    // Navigate to LCB1
    await scraper.page.goto('https://www.lcb1.com/BerthSchedule', { waitUntil: 'networkidle2' });
    console.log('📄 Page loaded');
    
    // Wait for dropdown and select vessel
    await scraper.page.waitForSelector('select');
    console.log('🔽 Dropdown found');
    
    const dropdowns = await scraper.page.$$('select');
    await dropdowns[0].select('MARSA PRIDE');
    console.log('✅ MARSA PRIDE selected');
    
    // Wait a moment after selection
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Try multiple search strategies
    console.log('🔍 Attempting search strategies...');
    
    // Strategy 1: Look for submit/search buttons
    const searchResult1 = await scraper.page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type=\"submit\"], input[type=\"button\"]');
      for (const button of buttons) {
        const text = (button.textContent || button.value || '').toLowerCase();
        console.log('Found button:', text);
        if (text.includes('search') || text.includes('submit') || text.includes('find')) {
          button.click();
          return 'clicked: ' + text;
        }
      }
      return 'no search button found';
    });
    console.log('Strategy 1 result:', searchResult1);
    
    // Wait for response
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    // Strategy 2: Try form submission
    const searchResult2 = await scraper.page.evaluate(() => {
      const forms = document.querySelectorAll('form');
      if (forms.length > 0) {
        forms[0].submit();
        return 'form submitted';
      }
      return 'no forms found';
    });
    console.log('Strategy 2 result:', searchResult2);
    
    // Wait for response  
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    // Strategy 3: Enter key on dropdown
    await scraper.page.focus('select');
    await scraper.page.keyboard.press('Enter');
    console.log('Strategy 3: Enter key pressed');
    
    // Wait for response
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    // Strategy 4: Check for onclick handlers
    const onclickResult = await scraper.page.evaluate(() => {
      const elements = document.querySelectorAll('*');
      const clickableElements = [];
      
      for (const el of elements) {
        if (el.onclick || el.getAttribute('onclick')) {
          clickableElements.push({
            tag: el.tagName,
            text: el.textContent?.trim().substring(0, 50),
            onclick: el.getAttribute('onclick')
          });
        }
      }
      
      return clickableElements;
    });
    
    console.log('\n🎯 Found clickable elements:');
    onclickResult.forEach((el, i) => {
      console.log(`${i + 1}. ${el.tag}: ${el.text} -> ${el.onclick}`);
    });
    
    // Now check what we have on the page
    const finalAnalysis = await scraper.page.evaluate(() => {
      return {
        bodyText: document.body.textContent.length,
        hasMarsa: document.body.textContent.includes('MARSA PRIDE'),
        hasBerthing: document.body.textContent.includes('Berthing Time'),
        has528: document.body.textContent.includes('528'),
        tablesCount: document.querySelectorAll('table').length,
        textAroundMarsa: (() => {
          const text = document.body.textContent;
          const index = text.indexOf('MARSA PRIDE');
          if (index !== -1) {
            return text.substring(index - 200, index + 200);
          }
          return 'not found';
        })()
      };
    });
    
    console.log('\\n📊 Final Analysis:');
    console.log('Body text length:', finalAnalysis.bodyText);
    console.log('Has MARSA PRIDE:', finalAnalysis.hasMarsa);
    console.log('Has Berthing Time:', finalAnalysis.hasBerthing);
    console.log('Has 528:', finalAnalysis.has528);
    console.log('Tables count:', finalAnalysis.tablesCount);
    console.log('\\nText around MARSA PRIDE:');
    console.log(finalAnalysis.textAroundMarsa);
    
    console.log('\\n👀 Browser will stay open for 30 seconds for manual inspection...');
    await new Promise(resolve => setTimeout(resolve, 30000));
    
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await scraper.cleanup();
  }
})();
