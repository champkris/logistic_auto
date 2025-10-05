const https = require('https');

// All vessel codes from ShipmentLink (scraped from dropdown)
const VESSEL_CODES = require('./shipmentlink-vessel-codes.json');

class ShipmentLinkHTTPSScraper {
  constructor() {
    // Use the complete vessel code mappings
    this.vesselCodes = VESSEL_CODES;
  }

  async scrapeVessel(vesselName, voyageCode = null) {
    // Extract voyage code from vessel name if not provided separately
    if (!voyageCode) {
      const voyageMatch = vesselName.match(/\s+(\d{3,4}[A-Z-]+\d*[A-Z]?)$/i);
      if (voyageMatch) {
        voyageCode = voyageMatch[1].toUpperCase();
      }
    }

    // Extract base vessel name (remove voyage if included)
    // Supports formats: 0815-079S, 071W, 2512S, etc.
    const baseVessel = vesselName.replace(/\s+\d{3,4}[A-Z-]+\d*[A-Z]?$/i, '').trim().toUpperCase();

    // Get vessel code
    const vslCode = this.vesselCodes[baseVessel];

    if (!vslCode) {
      console.error(`No vessel code mapping for: ${baseVessel}`);
      return {
        success: false,
        error: `No vessel code mapping for ${baseVessel}`
      };
    }

    console.error(`🔍 Fetching schedule for ${baseVessel} (code: ${vslCode})...`);

    const url = `https://ss.shipmentlink.com/tvs2/jsp/TVS2_QueryScheduleByVessel.jsp?vslCode=${vslCode}`;
    const html = await this.makeRequest(url);

    const schedules = this.parseHTML(html, voyageCode);

    return {
      success: true,
      vessel_name: baseVessel,
      vessel_code: vslCode,
      schedules: schedules,
      scraped_at: new Date().toISOString()
    };
  }

  makeRequest(url) {
    return new Promise((resolve, reject) => {
      https.get(url, (res) => {
        let data = '';
        res.on('data', (chunk) => { data += chunk; });
        res.on('end', () => { resolve(data); });
      }).on('error', reject);
    });
  }

  parseHTML(html, targetVoyage = null) {
    const schedules = [];

    // Find all voyage sections
    const voyagePattern = /<span class='f12wrdb2'>\s*(.*?)\s*<\/span>/g;
    let voyageMatch;

    while ((voyageMatch = voyagePattern.exec(html)) !== null) {
      const fullVoyageName = voyageMatch[1].trim(); // e.g., "EVER BUILD 0815-079S" or "CSCL BOHAI SEA 071W"
      // Match various voyage formats: 0815-079S, 071W, 2512S, etc.
      const voyageCodeMatch = fullVoyageName.match(/(\d{3,4}[-]?\d{0,3}[A-Z])$/);
      const voyage = voyageCodeMatch ? voyageCodeMatch[1] : null;

      // If target voyage specified, skip others
      if (targetVoyage && voyage !== targetVoyage) {
        continue;
      }

      console.error(`📦 Found voyage: ${fullVoyageName}`);

      // Extract the table section for this voyage
      const sectionStart = voyageMatch.index;
      const nextVoyageIndex = html.indexOf('<span class=\'f12wrdb2\'>', sectionStart + 10);
      const sectionEnd = nextVoyageIndex > 0 ? nextVoyageIndex : html.length;
      const voyageSection = html.substring(sectionStart, sectionEnd);

      // Extract ports from header row
      const ports = [];
      const portPattern = /<td align='center' width='15'>\s*(.*?)\s*<\/td>/g;
      let portMatch;
      while ((portMatch = portPattern.exec(voyageSection)) !== null) {
        if (portMatch.index > voyageSection.indexOf('<!-- 印港口列資料 -->')) {
          break; // Stop at first occurrence (header row)
        }
      }

      // Re-extract ports properly
      const headerStart = voyageSection.indexOf('<!-- 印港口列資料 -->');
      const headerEnd = voyageSection.indexOf('</tr>', headerStart);
      const headerSection = voyageSection.substring(headerStart, headerEnd);

      const portMatches = headerSection.matchAll(/<td align='center' width='15'>\s*(.*?)\s*<\/td>/g);
      for (const match of portMatches) {
        const port = match[1].replace(/&#x[0-9a-fA-F]+;/g, (m) => {
          const code = parseInt(m.slice(3, -1), 16);
          return String.fromCharCode(code);
        }).trim();
        ports.push(port);
      }

      // Extract dates (ARR/DEP rows)
      const datePattern = /<td nowrap class='f09rown2'>\s*(.*?)<br>\s*(.*?)\s*<\/td>/g;
      const dates = [];
      let dateMatch;
      while ((dateMatch = datePattern.exec(voyageSection)) !== null) {
        const arr = dateMatch[1].replace(/&#x2f;/g, '/').trim();
        const dep = dateMatch[2].replace(/&#x2f;/g, '/').trim();
        dates.push({ arr, dep });
      }

      // Match ports with dates
      const portSchedules = [];
      for (let i = 0; i < Math.min(ports.length, dates.length); i++) {
        const port = ports[i];

        // Check if this is LAEM CHABANG (for B2 terminal)
        if (port.includes('LAEM CHABANG') || port.includes('LAEMCHABANG')) {
          portSchedules.push({
            port: 'LAEM CHABANG',
            port_terminal: 'B2',
            eta: dates[i].arr,
            etd: dates[i].dep
          });
        }
      }

      if (portSchedules.length > 0) {
        schedules.push({
          voyage: voyage,
          full_name: fullVoyageName,
          ports: portSchedules
        });
      }
    }

    return schedules;
  }

  formatDate(dateStr) {
    if (!dateStr || dateStr === '') return null;

    // Format: MM/DD -> YYYY-MM-DD
    const [month, day] = dateStr.split('/');
    const currentYear = new Date().getFullYear();

    // Handle year rollover (if month is Jan-Mar and current month is Oct-Dec, use next year)
    const currentMonth = new Date().getMonth() + 1;
    const targetMonth = parseInt(month);
    const year = (currentMonth >= 10 && targetMonth <= 3) ? currentYear + 1 : currentYear;

    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
  }
}

// Main execution
async function main() {
  const args = process.argv.slice(2);
  const vesselInput = args[0] || 'EVER BUILD';

  // Extract voyage from input if present
  const voyageMatch = vesselInput.match(/(\d{4}-\d{3}[SN])/);
  const voyageCode = voyageMatch ? voyageMatch[1] : null;
  const vesselName = vesselInput.replace(/\s+\d{4}-\d{3}[SN]/i, '').trim();

  const scraper = new ShipmentLinkHTTPSScraper();

  try {
    const result = await scraper.scrapeVessel(vesselName, voyageCode);

    if (result.success && result.schedules.length > 0) {
      // Return first LAEM CHABANG schedule found
      const schedule = result.schedules[0];
      const lcbSchedule = schedule.ports.find(p => p.port === 'LAEM CHABANG');

      if (lcbSchedule) {
        const output = {
          success: true,
          terminal: 'ShipmentLink',
          port_terminal: 'B2',
          vessel_name: result.vessel_name,
          voyage_code: schedule.voyage,
          eta: scraper.formatDate(lcbSchedule.eta) + ' 00:00:00',
          etd: scraper.formatDate(lcbSchedule.etd) + ' 00:00:00',
          raw_data: {
            full_voyage: schedule.full_name,
            eta_raw: lcbSchedule.eta,
            etd_raw: lcbSchedule.etd
          },
          scraped_at: result.scraped_at
        };

        console.log(JSON.stringify(output));
        console.error(`✅ Found ${schedule.full_name} at LAEM CHABANG: ARR ${lcbSchedule.eta}, DEP ${lcbSchedule.etd}`);
      } else {
        console.log(JSON.stringify({ success: false, error: 'LAEM CHABANG not found in schedule' }));
      }
    } else {
      console.log(JSON.stringify(result));
    }

  } catch (error) {
    console.error('❌ Error:', error.message);
    console.log(JSON.stringify({ success: false, error: error.message }));
    process.exit(1);
  }
}

main();
