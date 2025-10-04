const puppeteer = require('puppeteer');

/**
 * LCB1 Full Schedule Scraper
 * Scrapes the berth schedule table directly from LCB1 website
 * Covers terminals: A0, B1, A3, D1
 */
class LCB1FullScheduleScraper {
  constructor() {
    this.browser = null;
    this.page = null;
    this.baseUrl = 'https://www.lcb1.com/BerthSchedule';
  }

  async initialize() {
    console.error('🚀 Initializing LCB1 Full Schedule Scraper...');

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
      console.error(`🔍 Scraping LCB1 berth schedule...`);

      await this.page.goto(this.baseUrl, {
        waitUntil: 'networkidle2',
        timeout: 30000
      });

      console.error('📄 Page loaded, extracting schedule...');
      await new Promise(resolve => setTimeout(resolve, 2000));

      // Extract vessels from the berth schedule table
      const vessels = await this.page.evaluate(() => {
        const results = [];
        const rows = document.querySelectorAll('table tbody tr');

        rows.forEach(row => {
          try {
            const cells = row.querySelectorAll('td');

            // LCB1 table structure (similar to ESCO):
            // Typical berth schedule has columns like:
            // Vessel Name, VOY IN, VOY OUT, STATUS, ETB (ETA), ETD, GATE OPEN, GATE CLOSE, BERTH/TERMINAL

            if (cells.length >= 6) {
              const vesselName = cells[0]?.innerText?.trim().replace(/^M\.V\.\s*/i, ''); // Remove "M.V." prefix if exists
              const voyageIn = cells[1]?.innerText?.trim();
              const voyageOut = cells[2]?.innerText?.trim();
              const status = cells[3]?.innerText?.trim();
              const etb = cells[4]?.innerText?.trim(); // ETA
              const etd = cells[5]?.innerText?.trim();

              // Look for terminal/berth in remaining cells
              let terminal = null;
              let berth = null;

              for (let i = 6; i < cells.length; i++) {
                const cellText = cells[i]?.innerText?.trim();
                // Look for terminal codes (A0, B1, A3, D1)
                const terminalMatch = cellText?.match(/(A0|B1|A3|D1)/i);
                if (terminalMatch) {
                  terminal = terminalMatch[0].toUpperCase();
                  berth = cellText;
                  break;
                }
              }

              // Skip header rows and empty rows
              if (vesselName &&
                  vesselName.length > 2 &&
                  !vesselName.toLowerCase().includes('vessel') &&
                  vesselName !== 'VESSEL' &&
                  vesselName !== 'Vessel Name') {

                results.push({
                  vessel_name: vesselName,
                  voyage: voyageIn || voyageOut, // Use inbound voyage, fallback to outbound
                  eta: etb,
                  etd: etd,
                  status: status,
                  port_terminal: terminal || 'LCB1', // Default to LCB1 if can't detect specific terminal
                  berth: berth
                });
              }
            }
          } catch (e) {
            // Skip invalid rows
          }
        });

        return results;
      });

      console.error(`✅ Found ${vessels.length} vessels from LCB1`);

      return {
        success: true,
        terminal: 'LCB1',
        vessels: vessels,
        scraped_at: new Date().toISOString()
      };

    } catch (error) {
      console.error(`❌ Error scraping LCB1:`, error.message);
      return {
        success: false,
        terminal: 'LCB1',
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
  const scraper = new LCB1FullScheduleScraper();

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

module.exports = LCB1FullScheduleScraper;
