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

      // Change DataTables page size to show all entries
      try {
        const changed = await this.page.evaluate(() => {
          // Try different selectors for the page length dropdown
          const selectors = [
            'select[name="DataTables_Table_0_length"]',
            'select[name*="length"]',
            '.dataTables_length select',
            'select'
          ];

          for (const selector of selectors) {
            const select = document.querySelector(selector);
            if (select && select.name && select.name.includes('length')) {
              select.value = '100';
              select.dispatchEvent(new Event('change', { bubbles: true }));
              return true;
            }
          }
          return false;
        });

        if (changed) {
          console.error('✅ Changed page size to 100');
          await new Promise(resolve => setTimeout(resolve, 2000)); // Wait for table to reload
        } else {
          console.error('⚠️  Could not find page size selector');
        }
      } catch (e) {
        console.error('⚠️  Could not change page size:', e.message);
      }

      // Extract vessels from the page
      const vessels = await this.page.evaluate(() => {
        const results = [];
        const rows = document.querySelectorAll('table tbody tr');

        rows.forEach(row => {
          try {
            const cells = row.querySelectorAll('td');

            // TIPS table structure:
            // [0] Vessel Name
            // [1] Id
            // [2] Radio Call Sign
            // [3] I/B Voyage (Inbound)
            // [4] O/B Voyage (Outbound)
            // [5] Gate open
            // [6] Estimate
            // [7] Actual
            // [8] Closing Time
            // [9] Service
            // [10] ETA
            // [11] ETD
            // [12] ATA
            // [13] ATD

            if (cells.length >= 11) {
              const vesselName = cells[0]?.innerText?.trim();
              const voyageIn = cells[3]?.innerText?.trim();
              const eta = cells[10]?.innerText?.trim();  // ETA column [10]
              const etd = cells[7]?.innerText?.trim();   // Actual column [7] (departure time)
              const berth = cells[11]?.innerText?.trim(); // Service/Berth column [11]

              // Skip header rows and empty rows
              if (vesselName &&
                  vesselName.length > 2 &&
                  !vesselName.toLowerCase().includes('vessel') &&
                  vesselName !== 'Vessel Name') {

                results.push({
                  vessel_name: vesselName,
                  voyage: voyageIn,
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

      // TODO: Add pagination support if TIPS website has pagination
      // For now, single page scraping is sufficient

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
