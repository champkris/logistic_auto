const puppeteer = require('puppeteer');

async function debugPageContent() {
  const browser = await puppeteer.launch({
    headless: true,
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  try {
    await page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    // Handle cookies
    await new Promise(resolve => setTimeout(resolve, 2000));
    try {
      await page.click('button[id*="accept"]');
    } catch (error) {
      // Continue
    }
    
    // Select EVER BUILD and search
    await page.waitForSelector('select', { timeout: 10000 });
    await page.select('select', 'BULD');
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    await page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search') || text.toLowerCase().includes('submit')) {
          button.click();
          return true;
        }
      }
    });
    
    await new Promise(resolve => setTimeout(resolve, 4000));
    
    // Debug page content
    const debugInfo = await page.evaluate(() => {
      const pageText = document.body.textContent.toUpperCase();
      
      return {
        hasSelectVesselText: pageText.includes('PLEASE SELECT THE VESSEL NAME'),
        hasVesselDropdown: !!document.querySelector('select'),
        hasEverBuild: pageText.includes('EVER BUILD'),
        hasArrText: pageText.includes('ARR'),
        hasDepText: pageText.includes('DEP'),
        hasTargetVoyage: pageText.includes('0815-079S'),
        pageTextPreview: document.body.textContent.substring(0, 1000),
        titleText: document.title,
        tableCount: document.querySelectorAll('table').length
      };
    });
    
    console.log('🔍 Page Debug Info:');
    console.log(JSON.stringify(debugInfo, null, 2));
    
  } catch (error) {
    console.error('❌ Error:', error.message);
  }
  
  await browser.close();
}

debugPageContent().catch(console.error);