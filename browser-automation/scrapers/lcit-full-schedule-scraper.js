const https = require('https');
const { DOMParser } = require('xmldom');

/**
 * LCIT (B5) Full Schedule Scraper
 * Scrapes all vessel schedules from LCIT terminal using their API endpoint via HTTPS
 */
class LcitFullScheduleScraper {
  constructor() {
    this.apiUrl = 'https://www.lcit.com/Lcit.asmx/GetFullVessel';
  }

  async scrapeFullSchedule() {
    try {
      console.error(`🔍 Scraping full schedule from LCIT (B5) via API`);

      // Use % as wildcard to get all vessels
      const url = `${this.apiUrl}?vessel=%&voy=`;

      const xmlData = await this.makeRequest(url);
      console.error('📄 API response received, parsing XML...');

      const vessels = this.parseXML(xmlData);
      console.error(`✅ Found ${vessels.length} vessels from LCIT`);

      return {
        success: true,
        terminal: 'B5',
        vessels: vessels,
        scraped_at: new Date().toISOString()
      };

    } catch (error) {
      console.error(`❌ Error scraping LCIT:`, error.message);
      return {
        success: false,
        terminal: 'B5',
        error: error.message,
        vessels: []
      };
    }
  }

  makeRequest(url) {
    return new Promise((resolve, reject) => {
      const options = {
        headers: {
          'Referer': 'https://www.lcit.com/vessel?vsl=%&voy=',
          'X-Requested-With': 'XMLHttpRequest',
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
          'Accept': 'application/xml, text/xml, */*; q=0.01'
        }
      };

      https.get(url, options, (res) => {
        let data = '';

        res.on('data', (chunk) => {
          data += chunk;
        });

        res.on('end', () => {
          resolve(data);
        });
      }).on('error', (err) => {
        reject(err);
      });
    });
  }

  parseXML(xmlText) {
    const parser = new DOMParser();
    const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
    const results = [];

    const schedules = xmlDoc.getElementsByTagName('AllVesselSchedule');

    // Calculate cutoff date (3 months ago)
    const threeMonthsAgo = new Date();
    threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);

    for (let i = 0; i < schedules.length; i++) {
      const schedule = schedules[i];

      try {
        const vesselName = this.getElementText(schedule, 'VISIT_VSL_NAME_AN');
        const vscBerth = this.getElementText(schedule, 'VSC_BERTH');
        const berth = vscBerth ? vscBerth.substring(0, 2) : 'B5';
        const voyageIn = this.getElementText(schedule, 'EXT_IN_VOY_C');
        const voyageOut = this.getElementText(schedule, 'EXT_VOY_C');
        const cutoff = this.getElementText(schedule, 'CUTOFF_TM');
        const openGate = this.getElementText(schedule, 'RECEIVE_TM');
        const estimateArrival = this.getElementText(schedule, 'INIT_ETB_TM');
        const estimateDeparture = this.getElementText(schedule, 'INIT_ETD_TM');
        const visitStateCode = this.getElementText(schedule, 'VISIT_STATE_CODE');

        // Get actual berthing/unberthing dates
        let actualBerthing = '';
        let actualUnberthing = '';

        if (visitStateCode === 'DP') { // Departed
          actualBerthing = this.getElementText(schedule, 'VSL_BERTH_D');
          actualUnberthing = this.getElementText(schedule, 'EST_DPTR_D');
        } else if (visitStateCode === 'OD') { // On Dock
          actualBerthing = this.getElementText(schedule, 'VSL_BERTH_D');
        }

        // Skip header rows and empty rows
        if (vesselName && vesselName.length > 2) {
          // Use actual berthing if available, otherwise estimate arrival
          const eta = actualBerthing || estimateArrival;
          const etd = actualUnberthing || estimateDeparture;

          // Filter out vessels older than 3 months
          if (eta) {
            const etaDate = this.parseLCITDate(eta);
            if (etaDate && etaDate < threeMonthsAgo) {
              continue; // Skip old vessels
            }
          }

          results.push({
            vessel_name: vesselName,
            voyage: voyageIn || voyageOut,
            eta: eta,
            etd: etd,
            cutoff: cutoff,
            opengate: openGate,
            berth: berth,
            status: visitStateCode
          });
        }
      } catch (e) {
        // Skip invalid entries
      }
    }

    return results;
  }

  parseLCITDate(dateStr) {
    // LCIT date format: "04 OCT 25/21:00" or "02 MAR 98/06:00"
    if (!dateStr) return null;

    try {
      const parts = dateStr.split('/');
      if (parts.length < 1) return null;

      const datePart = parts[0].trim();
      const dateParts = datePart.split(' ');
      if (dateParts.length < 3) return null;

      const day = parseInt(dateParts[0]);
      const month = dateParts[1].toUpperCase();
      const year = parseInt(dateParts[2]);

      const monthMap = {
        'JAN': 0, 'FEB': 1, 'MAR': 2, 'APR': 3, 'MAY': 4, 'JUN': 5,
        'JUL': 6, 'AUG': 7, 'SEP': 8, 'OCT': 9, 'NOV': 10, 'DEC': 11
      };

      const monthNum = monthMap[month];
      if (monthNum === undefined) return null;

      // Handle 2-digit year (25 = 2025, 98 = 1998)
      let fullYear = year;
      if (year < 100) {
        fullYear = year < 50 ? 2000 + year : 1900 + year;
      }

      return new Date(fullYear, monthNum, day);
    } catch (e) {
      return null;
    }
  }

  getElementText(parent, tagName) {
    const elements = parent.getElementsByTagName(tagName);
    if (elements && elements.length > 0 && elements[0].firstChild) {
      return elements[0].firstChild.nodeValue?.trim() || '';
    }
    return '';
  }
}

async function main() {
  const scraper = new LcitFullScheduleScraper();

  try {
    const result = await scraper.scrapeFullSchedule();
    console.log(JSON.stringify(result));
  } catch (error) {
    console.error('Fatal error:', error);
    console.log(JSON.stringify({
      success: false,
      error: error.message,
      vessels: []
    }));
  }
}

if (require.main === module) {
  main();
}

module.exports = LcitFullScheduleScraper;
