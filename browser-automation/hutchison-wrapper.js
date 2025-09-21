#!/usr/bin/env node

/**
 * Hutchison Ports (C1C2) Vessel Tracking Wrapper
 *
 * This wrapper provides a consistent interface for the Laravel VesselTrackingService
 * to interact with the Hutchison Ports browser automation scraper.
 */

const { HutchisonVesselScraper } = require('./scrapers/hutchison-scraper');

async function main() {
  // Get vessel name from command line arguments
  const vesselName = process.argv[2];

  if (!vesselName) {
    console.error('Usage: node hutchison-wrapper.js "VESSEL NAME"');
    process.exit(1);
  }

  const scraper = new HutchisonVesselScraper();

  try {
    await scraper.initialize();
    const result = await scraper.scrapeVesselSchedule(vesselName);

    // Output clean JSON result for Laravel to parse
    console.log(JSON.stringify(result, null, 2));

    await scraper.cleanup();
    process.exit(result.success ? 0 : 1);

  } catch (error) {
    const errorResult = {
      success: false,
      error: error.message,
      terminal: 'Hutchison Ports',
      vessel_name: vesselName,
      scraped_at: new Date().toISOString()
    };

    console.log(JSON.stringify(errorResult, null, 2));
    await scraper.cleanup();
    process.exit(1);
  }
}

// Run the main function
main().catch(error => {
  console.log(JSON.stringify({
    success: false,
    error: error.message,
    terminal: 'Hutchison Ports',
    scraped_at: new Date().toISOString()
  }, null, 2));
  process.exit(1);
});