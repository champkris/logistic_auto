const puppeteer = require('puppeteer');

async function finalDebugTest() {
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
    
    // Handle cookies and search
    await new Promise(resolve => setTimeout(resolve, 2000));
    try { await page.click('button[id*="accept"]'); } catch {}
    await page.waitForSelector('select', { timeout: 10000 });
    await page.select('select', 'BULD');
    await new Promise(resolve => setTimeout(resolve, 1000));
    await page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search')) {
          button.click();
          return true;
        }
      }
    });
    await new Promise(resolve => setTimeout(resolve, 4000));
    
    // Debug the page state
    const debugData = await page.evaluate(() => {
      const pageText = document.body.textContent.toUpperCase();
      const targetVoyage = '0815-079S';
      
      const debugInfo = {
        pageText_includes_PLEASE_SELECT: pageText.includes('PLEASE SELECT THE VESSEL NAME'),
        pageText_includes_EVER_BUILD: pageText.includes('EVER BUILD'),
        pageText_includes_ARR: pageText.includes('ARR'),
        pageText_includes_DEP: pageText.includes('DEP'),
        pageText_includes_target_voyage: pageText.includes(targetVoyage),
        hasVesselDropdown: !!document.querySelector('select'),
        tableCount: document.querySelectorAll('table').length,
        
        // Calculate hasVoyageResults
        hasVoyageResults: pageText.includes('EVER BUILD') && (pageText.includes('ARR') || pageText.includes('DEP')),
        
        // The actual condition being tested
        wouldBeConsideredSelectionPage: null
      };
      
      // Calculate the actual condition
      const isStillOnSelectionPage = debugInfo.pageText_includes_PLEASE_SELECT;
      const hasVesselDropdown = debugInfo.hasVesselDropdown;
      const hasVoyageResults = debugInfo.hasVoyageResults;
      
      debugInfo.wouldBeConsideredSelectionPage = isStillOnSelectionPage && hasVesselDropdown && !hasVoyageResults;
      
      // Try to find the voyage in HTML
      const htmlContent = document.documentElement.outerHTML;
      const voyageIndex = htmlContent.indexOf(targetVoyage);
      debugInfo.voyageFoundInHTML = voyageIndex !== -1;
      debugInfo.voyagePosition = voyageIndex;
      
      if (voyageIndex !== -1) {
        // Extract section around voyage
        const sectionStart = Math.max(0, voyageIndex - 200);
        const sectionEnd = Math.min(htmlContent.length, voyageIndex + 200);
        debugInfo.voyageSection = htmlContent.substring(sectionStart, sectionEnd);
      }
      
      return debugInfo;
    });
    
    console.log('🔍 Final Debug Results:');
    console.log(JSON.stringify(debugData, null, 2));
    
  } catch (error) {
    console.error('❌ Error:', error.message);
  }
  
  await browser.close();
}

finalDebugTest().catch(console.error);