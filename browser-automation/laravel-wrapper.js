const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

// FIXED Laravel wrapper - logs to stderr, clean JSON to stdout
(async () => {
    const vesselName = process.argv[2] || 'MARSA PRIDE';
    
    const scraper = new LCB1VesselScraper();
    
    try {
        await scraper.initialize();
        const result = await scraper.scrapeVesselSchedule(vesselName);
        
        // ONLY clean JSON to stdout - logs go to stderr via winston config
        console.log(JSON.stringify(result));
        
        process.exit(result.success ? 0 : 1);
        
    } catch (error) {
        // Send error details to stderr, clean error JSON to stdout
        console.error(`Laravel Wrapper Error: ${error.message}`);
        
        const errorResult = {
            success: false,
            error: error.message,
            terminal: 'LCB1',
            vessel_name: vesselName,
            scraped_at: new Date().toISOString()
        };
        
        console.log(JSON.stringify(errorResult));
        process.exit(1);
        
    } finally {
        await scraper.cleanup();
    }
})();
