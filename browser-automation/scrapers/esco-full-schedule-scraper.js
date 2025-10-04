const puppeteer = require('puppeteer');

/**
 * ESCO (B3) Full Schedule Scraper
 * Scrapes all vessel schedules from ESCO terminal
 */
class EscoFullScheduleScraper {
  constructor() {
    this.browser = null;
    this.page = null;
    this.baseUrl = 'https://service.esco.co.th/BerthSchedule';
  }

  async initialize() {
    console.error('🚀 Initializing ESCO Full Schedule Scraper...');

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
      console.error(`🔍 Scraping full schedule from ESCO (B3)`);

      await this.page.goto(this.baseUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });

      console.error('📄 Page loaded, extracting schedule...');
      await new Promise(resolve => setTimeout(resolve, 2000));

      // Extract vessels from the page
      const vessels = await this.page.evaluate(() => {
        const results = [];
        const rows = document.querySelectorAll('table tbody tr');

        rows.forEach(row => {
          try {
            const cells = row.querySelectorAll('td');

            // ESCO table structure:
            // [0] Vessel Name (e.g., "M.V. XIN MING ZHOU 108")
            // [1] VOY IN (Inbound voyage)
            // [2] VOY OUT (Outbound voyage)
            // [3] STATUS (e.g., "DEPARTED", "BERTHED")
            // [4] ETB (Estimated Time of Berthing / ETA)
            // [5] ETD (Estimated Time of Departure)
            // [6] GATE OPEN
            // [7] GATE CLOSE

            if (cells.length >= 6) {
              const vesselName = cells[0]?.innerText?.trim().replace(/^M\.V\.\s*/i, ''); // Remove "M.V." prefix
              const voyageIn = cells[1]?.innerText?.trim();
              const voyageOut = cells[2]?.innerText?.trim();
              const status = cells[3]?.innerText?.trim();
              const etb = cells[4]?.innerText?.trim(); // ETA
              const etd = cells[5]?.innerText?.trim();
              const gateOpen = cells[6]?.innerText?.trim();
              const gateClose = cells[7]?.innerText?.trim();

              // Skip header rows and empty rows
              if (vesselName &&
                  vesselName.length > 2 &&
                  !vesselName.toLowerCase().includes('vessel') &&
                  vesselName !== 'VESSEL') {

                results.push({
                  vessel_name: vesselName,
                  voyage: voyageIn || voyageOut, // Use inbound voyage, fallback to outbound
                  eta: etb,
                  etd: etd,
                  status: status,
                  opengate: gateOpen,
                  cutoff: gateClose,
                  berth: 'B3' // ESCO is terminal B3
                });
              }
            }
          } catch (e) {
            // Skip invalid rows
          }
        });

        return results;
      });

      console.error(`✅ Found ${vessels.length} vessels from ESCO`);

      return {
        success: true,
        terminal: 'B3',
        vessels: vessels,
        scraped_at: new Date().toISOString()
      };

    } catch (error) {
      console.error(`❌ Error scraping ESCO:`, error.message);
      return {
        success: false,
        terminal: 'B3',
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
  const scraper = new EscoFullScheduleScraper();

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

module.exports = EscoFullScheduleScraper;
