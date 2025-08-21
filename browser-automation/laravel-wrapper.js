const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

/**
 * Laravel wrapper for LCB1 Vessel Scraper
 * Usage: node laravel-wrapper.js 'VESSEL_NAME'
 */

async function main() {
  const args = process.argv.slice(2);
  const vesselName = args[0] || 'MARSA PRIDE';
  
  const scraper = new LCB1VesselScraper();
  
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
      terminal: 'LCB1',
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
      terminal: 'LCB1',
      scraped_at: new Date().toISOString()
    }, null, 2));
    process.exit(1);
  });
}

module.exports = { main };