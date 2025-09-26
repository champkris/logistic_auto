#!/usr/bin/env node

const LCITScraper = require('./scrapers/lcit-scraper.js');

async function main() {
    const vesselName = process.argv[2] || 'SKY SUNSHINE';
    const voyageCode = process.argv[3] && process.argv[3] !== '' ? process.argv[3] : null;

    const scraper = new LCITScraper();

    try {
        const result = await scraper.scrapeVesselSchedule(vesselName, voyageCode);
        // Only output JSON to stdout
        console.log(JSON.stringify(result));
    } catch (error) {
        // On error, still output valid JSON
        const errorResult = {
            success: false,
            error: error.message,
            vessel_name: vesselName,
            voyage_code: voyageCode,
            details: 'Scraper encountered an error'
        };
        console.log(JSON.stringify(errorResult));
    }
}

main().catch(err => {
    // Fallback error handling
    const fallbackResult = {
        success: false,
        error: err.message || 'Unknown error',
        vessel_name: process.argv[2] || 'Unknown',
        voyage_code: process.argv[3] || null,
        details: 'Fatal error in scraper wrapper'
    };
    console.log(JSON.stringify(fallbackResult));
    process.exit(1);
});