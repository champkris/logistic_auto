const puppeteer = require('puppeteer');

/**
 * Hutchison Full Schedule Scraper
 * Scrapes all vessel schedules from Hutchison terminals (C1, C2, C3, D1)
 * WITHOUT requiring vessel name input
 */
class HutchisonFullScheduleScraper {
  constructor() {
    this.browser = null;
    this.page = null;
    // This URL should show all schedules - we need to find the correct one
    this.baseUrl = 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17';
  }

  async initialize() {
    console.error('🚀 Initializing Hutchison Full Schedule Scraper...');

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
      console.error(`🔍 Scraping full schedule for terminal: ${terminal}`);

      await this.page.goto(this.baseUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });

      console.error('📄 Page loaded, waiting for table...');
      await new Promise(resolve => setTimeout(resolve, 2000));

      // Look for the schedule table
      const vessels = await this.page.evaluate(() => {
        const results = [];

        // Find all table rows - adjust selector based on actual page structure
        const rows = document.querySelectorAll('table tr, .schedule-row, .vessel-row');

        rows.forEach(row => {
          try {
            // Extract vessel data from row
            // This is a template - adjust based on actual HTML structure
            const cells = row.querySelectorAll('td');

            if (cells.length >= 4) {
              const vesselName = cells[0]?.innerText?.trim();
              const voyage = cells[1]?.innerText?.trim();
              const eta = cells[2]?.innerText?.trim();
              const etd = cells[3]?.innerText?.trim();

              if (vesselName && vesselName !== 'Vessel Name') {
                results.push({
                  vessel_name: vesselName,
                  voyage: voyage,
                  eta: eta,
                  etd: etd,
                  berth: cells[4]?.innerText?.trim() || null
                });
              }
            }
          } catch (e) {
            // Skip invalid rows
          }
        });

        return results;
      });

      console.error(`✅ Found ${vessels.length} vessels`);

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

// CLI usage
async function main() {
  const terminal = process.argv[2] || 'C1';

  const scraper = new HutchisonFullScheduleScraper();

  try {
    await scraper.initialize();
    const result = await scraper.scrapeFullSchedule(terminal);

    // Output JSON to stdout for Laravel to parse
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

// Run if called directly
if (require.main === module) {
  main();
}

module.exports = HutchisonFullScheduleScraper;
