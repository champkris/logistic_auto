#!/usr/bin/env node

const TipsVesselScraper = require('./scrapers/tips-scraper.js');

// Get command line arguments
const args = process.argv.slice(2);

if (args.length === 0) {
  process.stderr.write('Usage: node tips-wrapper.js "VESSEL NAME" [VOYAGE_CODE]\n');
  process.exit(1);
}

const vesselName = args[0];
const voyageCode = args[1] || '';

(async () => {
  const scraper = new TipsVesselScraper();

  try {
    // Initialize browser
    await scraper.initialize();

    // Search for vessel
    const result = await scraper.searchVessel(vesselName, voyageCode);

    // Output clean JSON to stdout for Laravel
    process.stdout.write(JSON.stringify(result, null, 2));

  } catch (error) {
    // Log error to stderr
    process.stderr.write(`TIPS Scraper Error: ${error.message}\n`);

    // Output error result to stdout
    process.stdout.write(JSON.stringify({
      success: false,
      terminal: 'TIPS',
      vessel_name: vesselName,
      voyage_code: voyageCode,
      vessel_found: false,
      voyage_found: false,
      eta: null,
      error: error.message,
      search_method: 'wrapper_error'
    }, null, 2));

  } finally {
    // Always cleanup
    await scraper.cleanup();
  }
})().catch((error) => {
  process.stderr.write(`Fatal TIPS wrapper error: ${error.message}\n`);
  process.exit(1);
});