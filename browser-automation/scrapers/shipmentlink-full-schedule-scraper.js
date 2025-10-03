const puppeteer = require('puppeteer');

/**
 * Shipmentlink Full Schedule Scraper
 * Scrapes all vessel schedules from Shipmentlink terminals (SIAM, KERRY)
 */
class ShipmentlinkFullScheduleScraper {
  constructor() {
    this.browser = null;
    this.page = null;
    this.baseUrls = {
      'SIAM': 'https://www.shipmentlink.com/servlet/TDB1_VesselScheduleByTerminal.do?terminalId=SIAM',
      'KERRY': 'https://www.shipmentlink.com/servlet/TDB1_VesselScheduleByTerminal.do?terminalId=KERRY'
    };
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
      const url = this.baseUrls[terminal];
      if (!url) {
        throw new Error(`Unknown terminal: ${terminal}`);
      }

      console.error(`🔍 Scraping full schedule for ${terminal} from ${url}`);

      await this.page.goto(url, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });

      console.error('📄 Page loaded, extracting schedule...');
      await new Promise(resolve => setTimeout(resolve, 2000));

      const vessels = await this.page.evaluate(() => {
        const results = [];
        const rows = document.querySelectorAll('table tr, .schedule-table tr');

        rows.forEach(row => {
          try {
            const cells = row.querySelectorAll('td');

            if (cells.length >= 3) {
              const vesselName = cells[0]?.innerText?.trim();
              const voyage = cells[1]?.innerText?.trim();
              const eta = cells[2]?.innerText?.trim();
              const etd = cells[3]?.innerText?.trim();

              if (vesselName && vesselName.length > 2 && !vesselName.includes('Vessel')) {
                results.push({
                  vessel_name: vesselName,
                  voyage: voyage,
                  eta: eta,
                  etd: etd,
                  berth: null
                });
              }
            }
          } catch (e) {
            // Skip invalid rows
          }
        });

        return results;
      });

      console.error(`✅ Found ${vessels.length} vessels for ${terminal}`);

      return {
        success: true,
        terminal: terminal,
        vessels: vessels,
        scraped_at: new Date().toISOString()
      };

    } catch (error) {
      console.error(`❌ Error scraping ${terminal}:`, error.message);
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
