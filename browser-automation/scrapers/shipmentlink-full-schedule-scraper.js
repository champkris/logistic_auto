const puppeteer = require('puppeteer');

/**
 * Shipmentlink Full Schedule Scraper
 * Scrapes all vessel schedules from Shipmentlink terminals (SIAM, KERRY)
 */
class ShipmentlinkFullScheduleScraper {
  constructor() {
    this.browser = null;
    this.page = null;
    // ShipmentLink uses a single URL for all schedules, filtered by dropdown
    this.baseUrl = 'https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp';
  }

  async initialize() {
    console.error('🚀 Initializing Shipmentlink Full Schedule Scraper...');

    this.browser = await puppeteer.launch({
      headless: true,
      defaultViewport: { width: 1920, height: 1080 },
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage'
      ]
    });

    this.page = await this.browser.newPage();
    await this.page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    console.error('✅ Browser initialized');
  }

  async scrapeFullSchedule(terminal) {
    try {
      console.error(`⚠️  Shipmentlink does not support full schedule scraping`);
      console.error(`   This terminal requires vessel code for individual lookups`);
      console.error(`   Cannot scrape all vessels for terminal: ${terminal}`);

      // Shipmentlink website (https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp)
      // requires a vessel code parameter (?vslCode=XXX) to show schedules
      // It does NOT display a full terminal schedule like Hutchison or TIPS
      // Therefore, this scraper cannot provide bulk vessel data

      return {
        success: true,
        terminal: terminal,
        vessels: [], // Always empty - Shipmentlink doesn't support full schedule
        scraped_at: new Date().toISOString(),
        message: 'Shipmentlink requires vessel code - full schedule not available'
      };

    } catch (error) {
      console.error(`❌ Error: ${error.message}`);
      return {
        success: false,
        terminal: terminal,
        error: error.message,
        vessels: []
      };
    }
  }

  async close() {
    if (this.browser) {
      await this.browser.close();
      console.error('🧹 Browser closed');
    }
  }
}

async function main() {
  const terminal = process.argv[2] || 'SIAM';

  const scraper = new ShipmentlinkFullScheduleScraper();

  try {
    await scraper.initialize();
    const result = await scraper.scrapeFullSchedule(terminal);
    console.log(JSON.stringify(result));
  } catch (error) {
    console.error('Fatal error:', error);
    console.log(JSON.stringify({
      success: false,
      error: error.message,
      vessels: []
    }));
  } finally {
    await scraper.close();
  }
}

if (require.main === module) {
  main();
}

module.exports = ShipmentlinkFullScheduleScraper;
