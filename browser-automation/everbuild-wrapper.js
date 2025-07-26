const { EverbuildVesselScraper } = require('./scrapers/everbuild-scraper');

// FIXED Everbuild Laravel wrapper - logs to stderr, clean JSON to stdout
(async () => {
    const vesselName = process.argv[2] || 'EVER BUILD 0815-079S';
    
    const scraper = new EverbuildVesselScraper();
    
    try {
        await scraper.initialize();
        const result = await scraper.scrapeVesselSchedule(vesselName);
        
        // ONLY clean JSON to stdout - logs go to stderr via winston config
        console.log(JSON.stringify(result));
        
        process.exit(result.success ? 0 : 1);
        
    } catch (error) {
        // Send error details to stderr, clean error JSON to stdout
        console.error(`Everbuild Laravel Wrapper Error: ${error.message}`);
        
        const errorResult = {
            success: false,
            error: error.message,
            terminal: 'Everbuild',
            vessel_name: vesselName,
            scraped_at: new Date().toISOString()
        };
        
        console.log(JSON.stringify(errorResult));
        process.exit(1);
        
    } finally {
        await scraper.cleanup();
    }
})();
