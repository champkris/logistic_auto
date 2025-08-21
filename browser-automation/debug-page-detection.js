const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function debugPageDetection() {
  console.log('🔍 Debugging page detection after search...\n');
  
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    
    // Navigate to the page
    await scraper.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
      waitUntil: 'networkidle0',
      timeout: 30000
    });
    
    // Handle cookies
    try {
      await scraper.page.waitForSelector('button[id*="accept"]', { timeout: 5000 });
      await scraper.page.click('button[id*="accept"]');
      await new Promise(resolve => setTimeout(resolve, 2000));
    } catch (e) {
      console.log('No cookie popup');
    }
    
    // Select EVER BUILD
    await scraper.page.waitForSelector('select', { timeout: 10000 });
    await scraper.page.select('select', 'BULD');
    console.log('✅ Selected EVER BUILD');
    
    // Submit
    await scraper.page.evaluate(() => {
      const submitBtn = document.querySelector('input[value="Submit"]');
      if (submitBtn) submitBtn.click();
    });
    console.log('✅ Clicked submit');
    
    // Wait for results
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    // Debug the page detection logic
    const detectionResults = await scraper.page.evaluate((targetVessel) => {
      const pageText = document.body.textContent.toUpperCase();
      const voyageMatch = targetVessel.match(/(\d{4}-\d{3}[SN])/);
      const targetVoyage = voyageMatch ? voyageMatch[1] : null;
      
      // All the detection checks
      const checks = {
        pageText_length: pageText.length,
        hasSelectText: pageText.includes('PLEASE SELECT THE VESSEL NAME'),
        hasVesselDropdown: !!document.querySelector('select'),
        hasEverBuild: pageText.includes('EVER BUILD'),
        hasARR: pageText.includes('ARR'),
        hasDEP: pageText.includes('DEP'),
        hasVoyageResults: pageText.includes('EVER BUILD') && (pageText.includes('ARR') || pageText.includes('DEP')),
        tablesCount: document.querySelectorAll('table').length,
        hasScheduleTables: document.querySelectorAll('table').length > 5,
        hasVoyageCodes: pageText.includes('0807-') || pageText.includes('0811-') || 
                       pageText.includes('0815-') || pageText.includes('0819-') || 
                       pageText.includes('0823-'),
        targetVoyage: targetVoyage,
        hasTargetVoyage: targetVoyage ? pageText.includes(targetVoyage) : false,
        url: window.location.href
      };
      
      // Final detection logic
      const isStillOnSelectionPage = checks.hasSelectText && 
                                    !checks.hasVoyageResults && 
                                    !checks.hasScheduleTables && 
                                    !checks.hasVoyageCodes;
      
      checks.finalDetection_stillOnSelectionPage = isStillOnSelectionPage;
      
      return checks;
    }, 'EVER BUILD 0815-079S');
    
    console.log('📊 Page Detection Results:');
    Object.entries(detectionResults).forEach(([key, value]) => {
      console.log(`  ${key}: ${value}`);
    });
    
    // Extract a sample of the page text
    const sampleText = await scraper.page.evaluate(() => {
      const text = document.body.textContent;
      return {
        first200chars: text.substring(0, 200),
        searchForEverBuild: text.substring(text.indexOf('EVER BUILD'), text.indexOf('EVER BUILD') + 100),
        searchForARR: text.includes('ARR') ? text.substring(text.indexOf('ARR'), text.indexOf('ARR') + 50) : 'NOT FOUND'
      };
    });
    
    console.log('\n📄 Sample Page Content:');
    console.log('First 200 characters:', sampleText.first200chars);
    console.log('Around EVER BUILD:', sampleText.searchForEverBuild);
    console.log('Around ARR:', sampleText.searchForARR);
    
    await scraper.cleanup();
    
  } catch (error) {
    console.error('❌ Debug failed:', error);
    await scraper.cleanup();
  }
}

debugPageDetection();
