const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

/**
 * Laravel wrapper for ShipmentLink Vessel Scraper
 * Usage: node shipmentlink-wrapper.js 'VESSEL_NAME'
 */

async function main() {
  const args = process.argv.slice(2);
  const vesselName = args[0] || 'EVER BUILD';
  
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    const result = await scraper.scrapeVesselSchedule(vesselName);
    
    // Output clean JSON to stdout for Laravel to parse
    console.log(JSON.stringify(result, null, 2));
    
    await scraper.cleanup();
    process.exit(result.success ? 0 : 1);
    
  } catch (error) {
    await scraper.cleanup();
    
    const errorResult = {
      success: false,
      error: error.message,
      terminal: 'ShipmentLink',
      vessel_name: vesselName,
      scraped_at: new Date().toISOString()
    };
    
    console.log(JSON.stringify(errorResult, null, 2));
    process.exit(1);
  }
}

// Run if called directly
if (require.main === module) {
  main().catch(error => {
    console.log(JSON.stringify({
      success: false,
      error: error.message,
      terminal: 'ShipmentLink',
      scraped_at: new Date().toISOString()
    }, null, 2));
    process.exit(1);
  });
}

module.exports = { main };