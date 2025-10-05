const https = require('https');

class ShipmentLinkFullScheduleScraper {
  constructor() {
    this.baseUrl = 'https://ss.shipmentlink.com/tvs2/jsp';
  }

  async scrapeFullSchedule() {
    console.error('🔍 Fetching all vessel codes...');
    const vesselCodes = await this.getAllVesselCodes();
    console.error(`📦 Found ${vesselCodes.length} vessels`);

    const allSchedules = [];
    let processedCount = 0;

    for (const vesselCode of vesselCodes) {
      try {
        const schedules = await this.getVesselSchedule(vesselCode);
        allSchedules.push(...schedules);
        processedCount++;

        if (processedCount % 50 === 0) {
          console.error(`   Processed ${processedCount}/${vesselCodes.length} vessels...`);
        }
      } catch (error) {
        console.error(`   ⚠️  Error with ${vesselCode}: ${error.message}`);
      }

      // Rate limiting - small delay to avoid overwhelming the server
      await this.delay(50);
    }

    console.error(`✅ Scraped ${allSchedules.length} schedules from ${vesselCodes.length} vessels`);

    return {
      success: true,
      terminal: 'B2',
      vessels: allSchedules,
      scraped_at: new Date().toISOString()
    };
  }

  async getAllVesselCodes() {
    const url = `${this.baseUrl}/TVS2_QueryVessel.jsp?vslCode=`;
    const html = await this.makeRequest(url);

    const codes = [];
    const regex = /value='([A-Z]+)'/g;
    let match;

    while ((match = regex.exec(html)) !== null) {
      if (match[1] && match[1] !== '') {
        codes.push(match[1]);
      }
    }

    return codes;
  }

  async getVesselSchedule(vesselCode) {
    const url = `${this.baseUrl}/TVS2_QueryScheduleByVessel.jsp?vslCode=${vesselCode}`;
    const html = await this.makeRequest(url);

    return this.parseHTML(html);
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

  parseHTML(html) {
    const schedules = [];

    // Find all voyage sections
    const voyagePattern = /<span class='f12wrdb2'>\s*(.*?)\s*<\/span>/g;
    let voyageMatch;

    while ((voyageMatch = voyagePattern.exec(html)) !== null) {
      const fullVoyageName = voyageMatch[1].trim();
      const voyageCodeMatch = fullVoyageName.match(/(\d{4}-\d{3}[SN])/);
      const voyage = voyageCodeMatch ? voyageCodeMatch[1] : null;

      // Extract vessel name (everything before voyage code)
      const vesselName = fullVoyageName.replace(/\s*\d{4}-\d{3}[SN]\s*$/, '').trim();

      // Extract the table section for this voyage
      const sectionStart = voyageMatch.index;
      const nextVoyageIndex = html.indexOf('<span class=\'f12wrdb2\'>', sectionStart + 10);
      const sectionEnd = nextVoyageIndex > 0 ? nextVoyageIndex : html.length;
      const voyageSection = html.substring(sectionStart, sectionEnd);

      // Extract ports from header row
      const headerStart = voyageSection.indexOf('<!-- 印港口列資料 -->');
      const headerEnd = voyageSection.indexOf('</tr>', headerStart);
      const headerSection = voyageSection.substring(headerStart, headerEnd);

      const ports = [];
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

      // Match ports with dates - look for LAEM CHABANG
      for (let i = 0; i < Math.min(ports.length, dates.length); i++) {
        const port = ports[i];

        if (port.includes('LAEM CHABANG') || port.includes('LAEMCHABANG')) {
          const eta = this.formatDate(dates[i].arr);
          const etd = this.formatDate(dates[i].dep);

          // Only include if we have valid dates
          if (eta) {
            schedules.push({
              vessel_name: vesselName,
              voyage: voyage,
              port_terminal: 'B2',
              berth: 'B2',
              eta: eta,
              etd: etd,
              source: 'shipmentlink',
              raw_data: {
                full_voyage: fullVoyageName,
                eta_raw: dates[i].arr,
                etd_raw: dates[i].dep
              }
            });
          }
        }
      }
    }

    return schedules;
  }

  formatDate(dateStr) {
    if (!dateStr || dateStr === '') return null;

    // Format: MM/DD -> YYYY-MM-DD HH:mm:ss
    const parts = dateStr.split('/');
    if (parts.length !== 2) return null;

    const [month, day] = parts;
    const currentYear = new Date().getFullYear();

    // Handle year rollover
    const currentMonth = new Date().getMonth() + 1;
    const targetMonth = parseInt(month);
    const year = (currentMonth >= 10 && targetMonth <= 3) ? currentYear + 1 : currentYear;

    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')} 00:00:00`;
  }

  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

// Main execution
async function main() {
  const scraper = new ShipmentLinkFullScheduleScraper();

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
