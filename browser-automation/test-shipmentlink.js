const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function testShipmentLinkScraper() {
  console.log('🧪 Testing ShipmentLink Vessel Scraper...');
  
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    console.log('🚀 Initializing browser...');
    await scraper.initialize();
    
    console.log('🔍 Scraping vessel schedule for EVER BUILD...');
    const result = await scraper.scrapeVesselSchedule('EVER BUILD');
    
    console.log('📊 Results:');
    console.log(JSON.stringify(result, null, 2));
    
    console.log('🧹 Cleaning up...');
    await scraper.cleanup();
    
    if (result.success) {
      console.log('✅ Test completed successfully!');
      process.exit(0);
    } else {
      console.log('❌ Test failed - no data found');
      process.exit(1);
    }
    
  } catch (error) {
    console.error('💥 Test failed with error:', error.message);
    
    try {
      await scraper.cleanup();
    } catch (cleanupError) {
      console.error('Failed to cleanup:', cleanupError.message);
    }
    
    process.exit(1);
  }
}

// Run test
if (require.main === module) {
  testShipmentLinkScraper();
}

module.exports = { testShipmentLinkScraper };