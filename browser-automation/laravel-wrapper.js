const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

// Simple command-line wrapper for Laravel integration
(async () => {
    const vesselName = process.argv[2] || 'MARSA PRIDE';
    
    const scraper = new LCB1VesselScraper();
    
    try {
        await scraper.initialize();
        const result = await scraper.scrapeVesselSchedule(vesselName);
        
        // Output clean JSON for Laravel to parse
        console.log(JSON.stringify(result, null, 0));
        
    } catch (error) {
        // Output error in same format
        const errorResult = {
            success: false,
            error: error.message,
            terminal: 'LCB1',
            vessel_name: vesselName,
            scraped_at: new Date().toISOString()
        };
        
        console.log(JSON.stringify(errorResult, null, 0));
        
    } finally {
        await scraper.cleanup();
        process.exit(0);
    }
})();
