const puppeteer = require('puppeteer');

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
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    this.page = await this.browser.newPage();
    console.error('✅ Browser initialized');
  }

  async scrapeFullSchedule() {
    console.error('🔍 Loading LCB1 berth schedule page...');
    await this.page.goto(this.baseUrl, { waitUntil: 'networkidle0', timeout: 30000 });

    // Get all vessel names from dropdown
    const vesselNames = await this.page.evaluate(() => {
      const select = document.querySelector('select');
      if (!select) return [];

      const options = Array.from(select.options);
      return options
        .map(opt => opt.text.trim())
        .filter(name => name && name !== 'Select' && name.length > 2);
    });

    console.error(`📦 Found ${vesselNames.length} vessels`);

    const allSchedules = [];
    let processedCount = 0;

    for (const vesselName of vesselNames) {
      try {
        const schedules = await this.getVesselSchedule(vesselName);
        allSchedules.push(...schedules);
        processedCount++;

        if (processedCount % 50 === 0) {
          console.error(`   Processed ${processedCount}/${vesselNames.length} vessels...`);
        }
      } catch (error) {
        console.error(`   ⚠️  Error with ${vesselName}: ${error.message}`);
      }

      // Small delay to avoid overwhelming the server
      await this.delay(100);
    }

    console.error(`✅ Scraped ${allSchedules.length} schedules from ${vesselNames.length} vessels`);

    return {
      success: true,
      terminals: ['A0', 'B1', 'A3'],
      vessels: allSchedules,
      scraped_at: new Date().toISOString()
    };
  }

  async getVesselSchedule(vesselName) {
    try {
      // Select vessel from dropdown
      await this.page.select('select', vesselName);

      // Click search button
      await this.page.click('#btnSearch');

      // Wait for table to load
      await this.page.waitForSelector('table', { timeout: 5000 });

      // Extract schedule data
      const schedules = await this.page.evaluate((vessel) => {
        const results = [];
        const tables = document.querySelectorAll('table');

        for (const table of tables) {
          const rows = table.querySelectorAll('tr');

          for (const row of rows) {
            const cells = Array.from(row.querySelectorAll('td')).map(td => td.textContent.trim());

            // LCB1 table format: [#, Vessel, Voyage In, Voyage Out, Berthing Time, Departure Time, Terminal]
            if (cells.length >= 7) {
              const rowVessel = cells[1];
              const voyageIn = cells[2];
              const voyageOut = cells[3];
              const berthingTime = cells[4];
              const departureTime = cells[5];
              const terminal = cells[6];

              // Check if this row matches our vessel
              if (rowVessel && rowVessel.toUpperCase().includes(vessel.toUpperCase())) {
                results.push({
                  vessel_name: vessel,
                  voyage: voyageIn || voyageOut || null,
                  port_terminal: terminal,
                  berth: terminal,
                  berthing_time: berthingTime,
                  departure_time: departureTime,
                  raw_data: {
                    vessel: rowVessel,
                    voyage_in: voyageIn,
                    voyage_out: voyageOut,
                    berthing_time: berthingTime,
                    departure_time: departureTime,
                    terminal: terminal
                  }
                });
              }
            }
          }
        }

        return results;
      }, vesselName);

      // Parse dates
      return schedules.map(s => ({
        ...s,
        eta: this.parseDate(s.berthing_time),
        etd: this.parseDate(s.departure_time),
        source: 'lcb1'
      })).filter(s => s.eta); // Only include if we have valid ETA

    } catch (error) {
      // If error (no schedule found, timeout, etc), return empty array
      return [];
    }
  }

  parseDate(dateStr) {
    if (!dateStr || dateStr === '' || dateStr === '-') return null;

    try {
      // LCB1 format: "DD/MM/YYYY - HH:MM"
      const match = dateStr.match(/(\d{2})\/(\d{2})\/(\d{4})\s*-\s*(\d{2}):(\d{2})/);
      if (match) {
        const [, day, month, year, hour, minute] = match;
        return `${year}-${month}-${day} ${hour}:${minute}:00`;
      }

      return null;
    } catch (error) {
      return null;
    }
  }

  async close() {
    if (this.browser) {
      await this.browser.close();
      console.error('🧹 Browser closed');
    }
  }

  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

// Main execution
async function main() {
  const scraper = new LCB1FullScheduleScraper();

  try {
    await scraper.initialize();
    const result = await scraper.scrapeFullSchedule();
    console.log(JSON.stringify(result));
  } catch (error) {
    console.error('❌ Error:', error.message);
    console.log(JSON.stringify({ success: false, error: error.message }));
    process.exit(1);
  } finally {
    await scraper.close();
  }
}

main();
