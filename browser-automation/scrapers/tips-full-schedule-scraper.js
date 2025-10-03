const puppeteer = require('puppeteer');

/**
 * TIPS Full Schedule Scraper
 * Scrapes all vessel schedules from TIPS terminal
 */
class TipsFullScheduleScraper {
  constructor() {
    this.browser = null;
    this.page = null;
    this.baseUrl = 'https://www.tips.co.th/container/shipSched/List';
  }

  async initialize() {
    console.error('🚀 Initializing TIPS Full Schedule Scraper...');

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

  async scrapeFullSchedule() {
    try {
      console.error(`🔍 Scraping full schedule from TIPS`);

      await this.page.goto(this.baseUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });

      console.error('📄 Page loaded, extracting schedule...');
      await new Promise(resolve => setTimeout(resolve, 2000));

      const vessels = await this.page.evaluate(() => {
        const results = [];
        const rows = document.querySelectorAll('table tbody tr, .data-table tr');

        rows.forEach(row => {
          try {
            const cells = row.querySelectorAll('td');

            if (cells.length >= 4) {
              const vesselName = cells[0]?.innerText?.trim();
              const voyage = cells[1]?.innerText?.trim();
              const eta = cells[2]?.innerText?.trim();
              const etd = cells[3]?.innerText?.trim();
              const berth = cells[4]?.innerText?.trim();

              if (vesselName && vesselName.length > 2 && !vesselName.toLowerCase().includes('vessel')) {
                results.push({
                  vessel_name: vesselName,
                  voyage: voyage,
                  eta: eta,
                  etd: etd,
                  berth: berth || null
                });
              }
            }
          } catch (e) {
            // Skip invalid rows
          }
        });

        return results;
      });

      console.error(`✅ Found ${vessels.length} vessels from TIPS`);

      return {
        success: true,
        terminal: 'TIPS',
        vessels: vessels,
        scraped_at: new Date().toISOString()
      };

    } catch (error) {
      console.error(`❌ Error scraping TIPS:`, error.message);
      return {
        success: false,
        terminal: 'TIPS',
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
  const scraper = new TipsFullScheduleScraper();

  try {
    await scraper.initialize();
    const result = await scraper.scrapeFullSchedule();
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

module.exports = TipsFullScheduleScraper;
