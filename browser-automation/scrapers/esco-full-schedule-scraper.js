const axios = require('axios');
const { parse } = require('node-html-parser');

/**
 * ESCO (B3) Full Schedule Scraper
 * Scrapes all vessel schedules from ESCO terminal via HTTP + HTML parsing
 * No Puppeteer needed — page serves static HTML table
 */
class EscoFullScheduleScraper {
  constructor() {
    this.baseUrl = 'https://service.esco.co.th/BerthSchedule';
  }

  async scrapeFullSchedule() {
    try {
      console.error('🔍 Scraping full schedule from ESCO (B3) via HTTP');

      const html = await this.fetchPage();
      const vessels = this.parseHTML(html);
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

  async scrapeSingleVessel(vesselName, voyageCode) {
    try {
      console.error(`🔍 ESCO single vessel lookup: ${vesselName}, voyage: ${voyageCode || 'any'}`);

      const html = await this.fetchPage();
      const vessels = this.parseHTML(html);

      // Filter by vessel name (case-insensitive substring match)
      const vesselUpper = vesselName.toUpperCase();
      const matches = vessels.filter(v => {
        const name = (v.vessel_name || '').toUpperCase();
        return name === vesselUpper || name.includes(vesselUpper) || vesselUpper.includes(name);
      });

      if (matches.length === 0) {
        console.error(`❌ Vessel not found in ESCO schedule`);
        return {
          success: true,
          vessel_found: false,
          vessel_name: vesselName,
          voyage_code: voyageCode || null,
          message: 'Vessel not found in schedule',
          details: 'The terminal was accessible, but the specified vessel was not found in the current schedule.'
        };
      }

      // Find best match: prefer exact voyage match, otherwise take first
      let match = matches[0];
      if (voyageCode) {
        const voyageUpper = voyageCode.toUpperCase();
        const exactMatch = matches.find(v => {
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
        eta: match.eta,
        etd: match.etd,
        cutoff: match.cutoff,
        opengate: match.opengate,
        raw_data: {
          table_row: [match.vessel_name, null, match.voyage, match.status, match.eta, match.etd, match.opengate, match.cutoff],
          status: match.status
        }
      };

    } catch (error) {
      console.error(`❌ ESCO single vessel error:`, error.message);
      return {
        success: false,
        error: error.message,
        vessel_name: vesselName,
        voyage_code: voyageCode || null,
        details: 'Scraper encountered an error while querying ESCO'
      };
    }
  }

  async fetchPage() {
    const response = await axios.get(this.baseUrl, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      },
      timeout: 30000
    });
    return response.data;
  }

  parseHTML(html) {
    const root = parse(html);
    const rows = root.querySelectorAll('table tbody tr');
    const results = [];

    for (const row of rows) {
      try {
        const cells = row.querySelectorAll('td');

        // ESCO table: 8 columns
        // [0] Vessel Name (e.g., "M.V. XIN MING ZHOU 108")
        // [1] VOY IN
        // [2] VOY OUT
        // [3] STATUS (e.g., "DEPARTED", "BERTHED")
        // [4] ETB/ETA (e.g., "03/03/2026 02:12")
        // [5] ETD
        // [6] GATE OPEN
        // [7] GATE CLOSE

        if (cells.length >= 6) {
          const rawName = cells[0]?.text?.trim() || '';
          const vesselName = rawName.replace(/^M\.V\.\s*/i, ''); // Remove "M.V." prefix
          const voyageIn = cells[1]?.text?.trim() || '';
          const voyageOut = cells[2]?.text?.trim() || '';
          const status = cells[3]?.text?.trim() || '';
          const etb = cells[4]?.text?.trim() || '';
          const etd = cells[5]?.text?.trim() || '';
          const gateOpen = cells[6]?.text?.trim() || '';
          const gateClose = cells[7]?.text?.trim() || '';

          if (vesselName &&
              vesselName.length > 2 &&
              !vesselName.toLowerCase().includes('vessel') &&
              vesselName !== 'VESSEL') {

            results.push({
              vessel_name: vesselName,
              voyage: voyageIn || voyageOut,
              eta: this.formatDate(etb),
              etd: this.formatDate(etd),
              status: status,
              opengate: this.formatDate(gateOpen) || null,
              cutoff: this.formatDate(gateClose) || null,
              berth: 'B3'
            });
          }
        }
      } catch (e) {
        // Skip invalid rows
      }
    }

    return results;
  }

  // ESCO date format: "DD/MM/YYYY HH:MM" → ISO "YYYY-MM-DDThh:mm:00"
  // Also handles "DD/MM/YYYY" (no time)
  formatDate(dateStr) {
    if (!dateStr || dateStr.trim() === '') return null;

    const match = dateStr.trim().match(/(\d{2})\/(\d{2})\/(\d{4})\s*(\d{2}):(\d{2})/);
    if (match) {
      const [, day, month, year, hour, minute] = match;
      return `${year}-${month}-${day}T${hour}:${minute}:00`;
    }

    // Date only (no time) — e.g. "25/02/2026"
    const dateOnly = dateStr.trim().match(/(\d{2})\/(\d{2})\/(\d{4})/);
    if (dateOnly) {
      const [, day, month, year] = dateOnly;
      return `${year}-${month}-${day}`;
    }

    return dateStr.trim();
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
  const scraper = new EscoFullScheduleScraper();
  const args = parseArgs(process.argv);

  try {
    if (args.vessel) {
      const result = await scraper.scrapeSingleVessel(args.vessel, args.voyage);
      console.log(JSON.stringify(result));
    } else {
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

module.exports = EscoFullScheduleScraper;
