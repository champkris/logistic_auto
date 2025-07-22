const puppeteer = require('puppeteer');

async function testSearchTriggerMethods() {
  console.log('🔄 Testing different search trigger methods...');
  
  const browser = await puppeteer.launch({
    headless: false,
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  // Monitor network requests
  const networkRequests = [];
  page.on('request', request => {
    networkRequests.push({
      url: request.url(),
      method: request.method(),
      timestamp: new Date().toISOString()
    });
  });
  
  page.on('response', response => {
    if (response.url().includes('BerthSchedule') && response.url() !== 'https://www.lcb1.com/BerthSchedule') {
      console.log(`📡 AJAX Response: ${response.status()} ${response.url()}`);
    }
  });
  
  try {
    await page.goto('https://www.lcb1.com/BerthSchedule', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    console.log('📄 Page loaded');
    
    // Select MARSA PRIDE
    await page.select('select', 'MARSA PRIDE');
    console.log('✅ Selected MARSA PRIDE');
    
    console.log('\n🧪 Method 1: Standard button click');
    const method1Success = await page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search')) {
          console.log('Clicking button:', text);
          button.click();
          return true;
        }
      }
      return false;
    });
    console.log('Method 1 result:', method1Success);
    
    // Wait and check
    await new Promise(resolve => setTimeout(resolve, 3000));
    let hasData = await page.evaluate(() => document.body.textContent.includes('528S'));
    console.log('Has schedule data after method 1:', hasData);
    
    if (!hasData) {
      console.log('\n🧪 Method 2: Form submission');
      await page.evaluate(() => {
        const forms = document.querySelectorAll('form');
        console.log(`Found ${forms.length} forms`);
        if (forms.length > 0) {
          forms[0].submit();
          return true;
        }
        return false;
      });
      
      await new Promise(resolve => setTimeout(resolve, 3000));
      hasData = await page.evaluate(() => document.body.textContent.includes('528S'));
      console.log('Has schedule data after method 2:', hasData);
    }
    
    if (!hasData) {
      console.log('\n🧪 Method 3: Keyboard Enter on select');
      await page.focus('select');
      await page.keyboard.press('Enter');
      
      await new Promise(resolve => setTimeout(resolve, 3000));
      hasData = await page.evaluate(() => document.body.textContent.includes('528S'));
      console.log('Has schedule data after method 3:', hasData);
    }
    
    if (!hasData) {
      console.log('\n🧪 Method 4: JavaScript event dispatch');
      await page.evaluate(() => {
        const select = document.querySelector('select');
        const changeEvent = new Event('change', { bubbles: true });
        select.dispatchEvent(changeEvent);
        
        // Also try triggering a submit event
        const submitEvent = new Event('submit', { bubbles: true });
        document.dispatchEvent(submitEvent);
      });
      
      await new Promise(resolve => setTimeout(resolve, 3000));
      hasData = await page.evaluate(() => document.body.textContent.includes('528S'));
      console.log('Has schedule data after method 4:', hasData);
    }
    
    if (!hasData) {
      console.log('\n🧪 Method 5: Click all buttons');
      const buttonClicks = await page.evaluate(() => {
        const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"], input[value*="search"], input[value*="Search"]');
        console.log(`Found ${buttons.length} buttons to try`);
        
        const results = [];
        buttons.forEach((button, i) => {
          const text = button.textContent || button.value || button.outerHTML;
          console.log(`Button ${i}: ${text.substring(0, 100)}`);
          try {
            button.click();
            results.push(`Clicked button ${i}: ${text.substring(0, 50)}`);
          } catch (e) {
            results.push(`Failed to click button ${i}: ${e.message}`);
          }
        });
        
        return results;
      });
      
      buttonClicks.forEach(result => console.log('  ', result));
      
      await new Promise(resolve => setTimeout(resolve, 5000));
      hasData = await page.evaluate(() => document.body.textContent.includes('528S'));
      console.log('Has schedule data after method 5:', hasData);
    }
    
    // Final check and page analysis
    console.log('\n📊 Final Analysis:');
    const finalAnalysis = await page.evaluate(() => {
      return {
        pageUrl: window.location.href,
        hasMarsa: document.body.textContent.includes('MARSA PRIDE'),
        hasScheduleData: document.body.textContent.includes('528S'),
        bodyLength: document.body.textContent.length,
        tableCount: document.querySelectorAll('table').length,
        formCount: document.querySelectorAll('form').length,
        buttonCount: document.querySelectorAll('button, input[type="button"], input[type="submit"]').length,
        selectedValue: document.querySelector('select').value
      };
    });
    
    console.log('Final analysis:', finalAnalysis);
    
    console.log('\n📡 Network Requests Summary:');
    const relevantRequests = networkRequests.filter(req => 
      req.url.includes('BerthSchedule') || 
      req.url.includes('ajax') || 
      req.url.includes('api')
    );
    relevantRequests.forEach(req => console.log(`  ${req.method} ${req.url}`));
    
    // Take final screenshot
    await page.screenshot({ 
      path: '/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/search-methods-test.png',
      fullPage: true 
    });
    console.log('📸 Screenshot saved: search-methods-test.png');
    
  } catch (error) {
    console.error('❌ Test error:', error.message);
  } finally {
    console.log('\n⏸️ Keeping browser open for 30 seconds for manual inspection...');
    setTimeout(async () => {
      await browser.close();
      console.log('🔚 Browser closed');
    }, 30000);
  }
}

testSearchTriggerMethods().catch(console.error);