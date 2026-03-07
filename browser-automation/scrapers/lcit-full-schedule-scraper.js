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

  async scrapeSingleVessel(vesselName, voyageCode) {
    try {
      console.error(`🔍 LCIT single vessel lookup: ${vesselName}, voyage: ${voyageCode || 'any'}`);

      // Pass real vessel/voyage to API (not wildcard %) — returns only matching results
      const url = `${this.apiUrl}?vessel=${encodeURIComponent(vesselName)}&voy=${encodeURIComponent(voyageCode || '')}`;

      let xmlData = await this.makeRequest(url);
      let vessels = this.parseXML(xmlData);

      // If API returned 0 results with a voyage filter, retry without voyage
      // (LCIT stores voyages like "0N806S1NC" which won't match user's "N806S" server-side)
      if (vessels.length === 0 && voyageCode) {
        console.error(`⚠️ No results with voyage filter "${voyageCode}", retrying without voyage...`);
        const retryUrl = `${this.apiUrl}?vessel=${encodeURIComponent(vesselName)}&voy=`;
        xmlData = await this.makeRequest(retryUrl);
        vessels = this.parseXML(xmlData);
        console.error(`🔄 Retry returned ${vessels.length} vessels`);
      }

      if (vessels.length === 0) {
        console.error(`❌ Vessel not found in LCIT schedule`);
        return {
          success: true,
          vessel_found: false,
          vessel_name: vesselName,
          voyage_code: voyageCode || null,
          message: 'Vessel not found in schedule',
          details: 'The terminal API was accessible, but the specified vessel was not found in the current schedule.'
        };
      }

      // Find best match: prefer exact voyage match, otherwise take first
      let match = vessels[0];
      if (voyageCode) {
        const voyageUpper = voyageCode.toUpperCase();
        const exactMatch = vessels.find(v => {
          const voy = (v.voyage || '').toUpperCase();
          return voy === voyageUpper || voy.includes(voyageUpper) || voyageUpper.includes(voy);
        });
        if (exactMatch) {
          match = exactMatch;
          console.error(`✅ Exact voyage match: ${match.voyage}`);
        } else {
          console.error(`⚠️ No exact voyage match for "${voyageCode}", using first: ${match.voyage}`);
        }
      }

      console.error(`✅ Found: ${match.vessel_name} / ${match.voyage}`);

      return {
        success: true,
        vessel_found: true,
        vessel_name: match.vessel_name || vesselName,
        voyage_code: match.voyage || voyageCode,
        berth: match.berth,
        eta: this.formatDate(match.eta),
        etd: this.formatDate(match.etd),
        cutoff: this.formatDate(match.cutoff),
        opengate: this.formatDate(match.opengate),
        raw_data: {
          table_row: [match.berth, match.vessel_name, match.voyage, null, match.cutoff, match.opengate, match.eta, match.etd],
          status: match.status
        }
      };

    } catch (error) {
      console.error(`❌ LCIT single vessel error:`, error.message);
      return {
        success: false,
        error: error.message,
        vessel_name: vesselName,
        voyage_code: voyageCode || null,
        details: 'Scraper encountered an error while querying LCIT API'
      };
    }
  }

  // Format LCIT date "DD MMM YY/HH:MM" → ISO format "YYYY-MM-DDThh:mm:00"
  formatDate(dateStr) {
    if (!dateStr || dateStr === '') return null;
    const match = dateStr.trim().match(/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{2})\/(\d{2}):(\d{2})/i);
    if (match) {
      const [, day, month, year, hour, minute] = match;
      const months = {
        'JAN': '01', 'FEB': '02', 'MAR': '03', 'APR': '04',
        'MAY': '05', 'JUN': '06', 'JUL': '07', 'AUG': '08',
        'SEP': '09', 'OCT': '10', 'NOV': '11', 'DEC': '12'
      };
      return `20${year}-${months[month.toUpperCase()]}-${day.padStart(2, '0')}T${hour}:${minute}:00`;
    }
    return dateStr.trim();
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

function parseArgs(argv) {
  const args = { vessel: null, voyage: null };
  for (let i = 2; i < argv.length; i++) {
    if (argv[i] === '--vessel' && argv[i + 1]) {
      args.vessel = argv[++i];
    } else if (argv[i] === '--voyage' && argv[i + 1]) {
      args.voyage = argv[++i];
    }
  }
  return args;
}

async function main() {
  const scraper = new LcitFullScheduleScraper();
  const args = parseArgs(process.argv);

  try {
    if (args.vessel) {
      // Single vessel mode — pass real params to API (no wildcard)
      const result = await scraper.scrapeSingleVessel(args.vessel, args.voyage);
      console.log(JSON.stringify(result));
    } else {
      // Cron mode — scrape all vessels
      const result = await scraper.scrapeFullSchedule();
      console.log(JSON.stringify(result));
    }
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
