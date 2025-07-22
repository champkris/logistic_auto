const puppeteer = require('puppeteer');

async function interceptPostRequest() {
  console.log('🕵️ Intercepting POST request to understand the API call...');
  
  const browser = await puppeteer.launch({
    headless: false,
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  // Intercept all requests
  await page.setRequestInterception(true);
  
  const interceptedRequests = [];
  
  page.on('request', request => {
    // Log all requests, especially POST requests
    if (request.method() === 'POST' || request.url().includes('BerthSchedule')) {
      const requestInfo = {
        url: request.url(),
        method: request.method(),
        headers: request.headers(),
        postData: request.postData(),
        timestamp: new Date().toISOString()
      };
      
      interceptedRequests.push(requestInfo);
      console.log(`📡 ${request.method()} ${request.url()}`);
      
      if (request.postData()) {
        console.log(`   POST Data: ${request.postData()}`);
      }
    }
    
    // Continue with the request
    request.continue();
  });
  
  page.on('response', async response => {
    if (response.url().includes('BerthSchedule/Detail')) {
      console.log(`📥 Response: ${response.status()} ${response.url()}`);
      try {
        const responseText = await response.text();
        console.log(`   Response length: ${responseText.length} characters`);
        console.log(`   Response preview: ${responseText.substring(0, 200)}...`);
        
        // Save full response for analysis
        const fs = require('fs');
        fs.writeFileSync('/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/api-response.html', responseText);
        console.log('   💾 Full response saved to api-response.html');
      } catch (e) {
        console.log('   ❌ Could not read response text');
      }
    }
  });
  
  try {
    await page.goto('https://www.lcb1.com/BerthSchedule', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    console.log('📄 Page loaded');
    
    // First, let's see what vessel options are available and their values
    const vesselOptions = await page.evaluate(() => {
      const select = document.querySelector('select');
      const options = Array.from(select.options).map(option => ({
        value: option.value,
        text: option.text
      }));
      
      return options.slice(0, 10); // First 10 for analysis
    });
    
    console.log('\n🚢 Sample vessel options:');
    vesselOptions.forEach((opt, i) => {
      console.log(`  ${i}: value="${opt.value}" text="${opt.text}"`);
    });
    
    // Now select MARSA PRIDE and see what its actual value is
    const marsaValue = await page.evaluate(() => {
      const select = document.querySelector('select');
      const options = Array.from(select.options);
      const marsaOption = options.find(opt => opt.text.includes('MARSA PRIDE'));
      
      if (marsaOption) {
        select.value = marsaOption.value;
        // Trigger change event
        const changeEvent = new Event('change', { bubbles: true });
        select.dispatchEvent(changeEvent);
        return {
          found: true,
          value: marsaOption.value,
          text: marsaOption.text,
          selectedValue: select.value
        };
      }
      
      return { found: false };
    });
    
    console.log('\n🎯 MARSA PRIDE selection result:', marsaValue);
    
    // Wait a moment for any immediate changes
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Now trigger the search
    console.log('\n🔍 Triggering search...');
    await page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search')) {
          console.log('Clicking search button');
          button.click();
          break;
        }
      }
    });
    
    // Wait for the API call and response
    console.log('⏳ Waiting for API calls...');
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    // Check what happened after the API call
    const postSearchAnalysis = await page.evaluate(() => {
      return {
        selectedValue: document.querySelector('select').value,
        bodyLength: document.body.textContent.length,
        hasScheduleData: document.body.textContent.includes('528S'),
        hasMarsaInContent: document.body.textContent.includes('MARSA PRIDE'),
        tableCount: document.querySelectorAll('table').length,
        // Look for any new content that might have been added
        containsVoyage: document.body.textContent.includes('voyage'),
        containsBerthing: document.body.textContent.includes('berthing'),
        containsTerminal: document.body.textContent.includes('terminal')
      };
    });
    
    console.log('\n📊 Post-search analysis:', postSearchAnalysis);
    
    // Summary of intercepted requests
    console.log('\n📋 Intercepted requests summary:');
    interceptedRequests.forEach((req, i) => {
      console.log(`\n${i + 1}. ${req.method} ${req.url}`);
      if (req.postData) {
        console.log(`   Data: ${req.postData}`);
      }
    });
    
    // Take a screenshot
    await page.screenshot({ 
      path: '/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/post-intercept-screenshot.png',
      fullPage: true 
    });
    console.log('\n📸 Screenshot saved: post-intercept-screenshot.png');
    
  } catch (error) {
    console.error('❌ Intercept test error:', error.message);
  } finally {
    console.log('\n⏸️ Keeping browser open for manual inspection...');
    setTimeout(async () => {
      await browser.close();
    }, 30000);
  }
}

interceptPostRequest().catch(console.error);