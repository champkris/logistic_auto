#!/usr/bin/env node
/**
 * LCB1 Wrapper Script for Production
 * This script provides clean JSON output for Laravel integration
 * Logs go to stderr, JSON results go to stdout
 */

const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

async function main() {
  const vesselName = process.argv[2] || 'MARSA PRIDE';
  const scraper = new LCB1VesselScraper();
  
  try {
    await scraper.initialize();
    const result = await scraper.scrapeVesselSchedule(vesselName);
    
    // Output ONLY clean JSON to stdout
    console.log(JSON.stringify(result));
    
    await scraper.cleanup();
    process.exit(result.success ? 0 : 1);
    
  } catch (error) {
    // Error JSON to stdout, error details to stderr
    console.error(`LCB1 Scraper Error: ${error.message}`);
    console.log(JSON.stringify({
      success: false,
      error: error.message,
      terminal: 'LCB1',
      scraped_at: new Date().toISOString()
    }));
    
    await scraper.cleanup();
    process.exit(1);
  }
}

main().catch(error => {
  console.error(`Fatal Error: ${error.message}`);
  console.log(JSON.stringify({
    success: false,
    error: error.message,
    terminal: 'LCB1',
    scraped_at: new Date().toISOString()
  }));
  process.exit(1);
});
