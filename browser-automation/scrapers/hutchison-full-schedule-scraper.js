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

      let allVessels = [];
      let currentPage = 1;
      let hasMorePages = true;

      // Loop through all pages
      while (hasMorePages) {
        console.error(`📄 Scraping page ${currentPage}...`);

        // Extract vessels from current page
        const vessels = await this.page.evaluate(() => {
          const results = [];

          // Find the main schedule table (Table 1 from debug output)
          const tables = document.querySelectorAll('table');
          const scheduleTable = tables[1]; // The second table is the vessel schedule

          if (scheduleTable) {
            const rows = scheduleTable.querySelectorAll('tbody tr');

            rows.forEach(row => {
              try {
                const cells = row.querySelectorAll('td');

                // Hutchison table structure (from debug output):
                // [0] Vessel Name
                // [1] Vessel ID
                // [2] In Voy (Inbound Voyage)
                // [3] Out Voy (Outbound Voyage)
                // [4] Arrival (ETA)
                // [5] Departure (ETD)
                // [6] Berth Terminal
                // [7] Release port
                // [8] Open Gate
                // [9] Gate Closing Time

                if (cells.length >= 10) {
                  const vesselName = cells[0]?.innerText?.trim();
                  const voyageIn = cells[2]?.innerText?.trim();
                  const voyageOut = cells[3]?.innerText?.trim();
                  const eta = cells[4]?.innerText?.trim(); // Arrival column
                  const etd = cells[5]?.innerText?.trim(); // Departure column
                  const berth = cells[6]?.innerText?.trim();
                  const openGate = cells[8]?.innerText?.trim();
                  const cutoff = cells[9]?.innerText?.trim();

                  // Skip header rows
                  if (vesselName &&
                      vesselName !== 'Vessel Name' &&
                      vesselName.length > 2) {

                    results.push({
                      vessel_name: vesselName,
                      voyage: voyageIn, // Use inbound voyage as primary
                      eta: eta,
                      etd: etd,
                      berth: berth,
                      opengate: openGate,
                      cutoff: cutoff
                    });
                  }
                }
              } catch (e) {
                // Skip invalid rows
              }
            });
          }

          return results;
        });

        allVessels = allVessels.concat(vessels);
        console.error(`   Found ${vessels.length} vessels on page ${currentPage} (total: ${allVessels.length})`);

        // Check if there's a "Next" button and click it
        hasMorePages = await this.page.evaluate(() => {
          const nextButton = Array.from(document.querySelectorAll('a, button'))
            .find(el => el.textContent.includes('Next'));

          if (nextButton && !nextButton.classList.contains('disabled')) {
            nextButton.click();
            return true;
          }
          return false;
        });

        if (hasMorePages) {
          // Wait for page to load
          await new Promise(resolve => setTimeout(resolve, 2000));
          currentPage++;
        }
      }

      console.error(`✅ Found ${allVessels.length} total vessels across ${currentPage} pages`);

      return {
        success: true,
        terminal: terminal,
        vessels: allVessels,
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
