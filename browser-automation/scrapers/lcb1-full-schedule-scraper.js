const https = require('https');
const { parse } = require('node-html-parser');

class LCB1CurlScraper {
  constructor() {
    this.baseUrl = 'https://www.lcb1.com';
  }

  async scrapeFullSchedule() {
    console.error('🔍 Fetching vessel list...');
    const vessels = await this.getAllVessels();
    console.error(`📦 Found ${vessels.length} vessels`);

    const allSchedules = [];
    let processedCount = 0;

    for (const vessel of vessels) {
      try {
        const schedules = await this.getVesselSchedule(vessel);
        allSchedules.push(...schedules);
        processedCount++;

        if (processedCount % 50 === 0) {
          console.error(`   Processed ${processedCount}/${vessels.length} vessels...`);
        }
      } catch (error) {
        console.error(`   ⚠️  Error with ${vessel}: ${error.message}`);
      }

      await this.delay(100);
    }

    console.error(`✅ Scraped ${allSchedules.length} schedules from ${vessels.length} vessels`);

    return {
      success: true,
      terminals: ['A0', 'B1', 'A3'],
      vessels: allSchedules,
      scraped_at: new Date().toISOString()
    };
  }

  async getAllVessels() {
    const html = await this.makeRequest('/BerthSchedule', 'GET');
    const root = parse(html);

    const select = root.querySelector('#txtVesselName');
    if (!select) return [];

    const options = select.querySelectorAll('option');
    return options
      .map(opt => opt.text.trim())
      .filter(name => name && name !== 'Select' && name.length > 2);
  }

  async getVesselSchedule(vesselName) {
    const postData = `vesselName=${encodeURIComponent(vesselName)}&voyageIn=&voyageOut=&pageSize=100&page=1`;

    const html = await this.makeRequest('/BerthSchedule/Detail', 'POST', postData);
    return this.parseScheduleHTML(html, vesselName);
  }

  parseScheduleHTML(html, vesselName) {
    const root = parse(html);
    const schedules = [];

    const rows = root.querySelectorAll('tr');

    for (const row of rows) {
      const cells = row.querySelectorAll('td');

      // LCB1 table format: [#, Vessel, Voyage In, Voyage Out, Berthing Time, Departure Time, Terminal]
      if (cells.length >= 7) {
        const rowVessel = cells[1]?.text.trim() || '';
        const voyageIn = cells[2]?.text.trim() || '';
        const voyageOut = cells[3]?.text.trim() || '';
        const berthingTime = cells[4]?.text.trim() || '';
        const departureTime = cells[5]?.text.trim() || '';
        const terminal = cells[6]?.text.trim() || '';

        // Skip header row and "No data" row
        if (rowVessel === 'Vessel Name' || rowVessel === 'No data found.') {
          continue;
        }

        // Check if this row matches our vessel
        if (rowVessel && rowVessel.toUpperCase().includes(vesselName.toUpperCase())) {
          const eta = this.parseDate(berthingTime);
          const etd = this.parseDate(departureTime);

          if (eta) {
            schedules.push({
              vessel_name: vesselName,
              voyage: voyageIn || voyageOut || null,
              port_terminal: terminal || 'A0',
              berth: terminal || null,
              eta: eta,
              etd: etd,
              source: 'lcb1',
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

    return schedules;
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

  makeRequest(path, method = 'GET', postData = null) {
    return new Promise((resolve, reject) => {
      const options = {
        hostname: 'www.lcb1.com',
        path: path,
        method: method,
        headers: {
          'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
          'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        }
      };

      if (method === 'POST' && postData) {
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        options.headers['Content-Length'] = Buffer.byteLength(postData);
      }

      const req = https.request(options, (res) => {
        let data = '';
        res.on('data', (chunk) => { data += chunk; });
        res.on('end', () => { resolve(data); });
      });

      req.on('error', reject);

      if (postData) {
        req.write(postData);
      }

      req.end();
    });
  }

  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

// Main execution
async function main() {
  const scraper = new LCB1CurlScraper();

  try {
    const result = await scraper.scrapeFullSchedule();
    console.log(JSON.stringify(result));
  } catch (error) {
    console.error('❌ Error:', error.message);
    console.log(JSON.stringify({ success: false, error: error.message }));
    process.exit(1);
  }
}

main();
