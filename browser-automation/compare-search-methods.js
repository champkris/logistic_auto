const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function compareSearchMethods() {
  console.log('🔍 Comparing different search methods...\n');
  
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    
    // Method 1: Our current approach
    console.log('🔍 Method 1: Current automation approach');
    await scraper.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp');
    
    try {
      await scraper.page.waitForSelector('button[id*="accept"]', { timeout: 5000 });
      await scraper.page.click('button[id*="accept"]');
      await new Promise(resolve => setTimeout(resolve, 2000));
    } catch (e) {}
    
    await scraper.page.waitForSelector('select');
    await scraper.page.select('select', 'BULD');
    
    // Check URL before submit
    const urlBefore = await scraper.page.url();
    console.log('URL before submit:', urlBefore);
    
    await scraper.page.evaluate(() => {
      const submitBtn = document.querySelector('input[value="Submit"]');
      if (submitBtn) submitBtn.click();
    });
    
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    const method1Results = await scraper.page.evaluate(() => {
      return {
        url: window.location.href,
        hasParams: window.location.href.includes('?'),
        params: window.location.search,
        tablesCount: document.querySelectorAll('table').length,
        hasEverBuild: document.body.textContent.includes('EVER BUILD'),
        has0815: document.body.textContent.includes('0815-079S'),
        pageSize: document.body.textContent.length
      };
    });
    
    console.log('Method 1 Results:', method1Results);
    
    // Method 2: Try direct URL with parameters (like we saw in working version)
    console.log('\n🔍 Method 2: Direct URL with vessel parameter');
    await scraper.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp?vslCode=BULD');
    
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    const method2Results = await scraper.page.evaluate(() => {
      return {
        url: window.location.href,
        tablesCount: document.querySelectorAll('table').length,
        hasEverBuild: document.body.textContent.includes('EVER BUILD'),
        has0815: document.body.textContent.includes('0815-079S'),
        pageSize: document.body.textContent.length,
        hasMultipleVoyages: (document.body.textContent.match(/EVER BUILD \d{4}-\d{3}[SN]/g) || []).length
      };
    });
    
    console.log('Method 2 Results:', method2Results);
    
    // Method 3: Try alternative parameter format
    console.log('\n🔍 Method 3: Alternative parameter format');
    await scraper.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp?vslCode=BULD&vslName=EVER+BUILD');
    
    await new Promise(resolve => setTimeout(resolve, 5000));
    
    const method3Results = await scraper.page.evaluate(() => {
      return {
        url: window.location.href,
        tablesCount: document.querySelectorAll('table').length,
        hasEverBuild: document.body.textContent.includes('EVER BUILD'),
        has0815: document.body.textContent.includes('0815-079S'),
        pageSize: document.body.textContent.length,
        multipleVoyages: (document.body.textContent.match(/EVER BUILD \d{4}-\d{3}[SN]/g) || [])
      };
    });
    
    console.log('Method 3 Results:', method3Results);
    
    // Try to extract any voyage codes we can find
    const availableVoyages = await scraper.page.evaluate(() => {
      const text = document.body.textContent;
      const voyageMatches = text.match(/\d{4}-\d{3}[SN]/g) || [];
      const uniqueVoyages = [...new Set(voyageMatches)];
      return uniqueVoyages;
    });
    
    console.log('\n🚢 Available voyages found:', availableVoyages);
    
    await scraper.cleanup();
    
  } catch (error) {
    console.error('❌ Comparison failed:', error);
    await scraper.cleanup();
  }
}

compareSearchMethods();
