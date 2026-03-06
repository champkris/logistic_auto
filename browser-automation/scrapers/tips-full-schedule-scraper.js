const axios = require('axios');
const { parse } = require('node-html-parser');

/**
 * TIPS (B4) Full Schedule Scraper
 * Scrapes all vessel schedules from TIPS terminal via HTTP + HTML parsing
 * No Puppeteer needed — page serves static HTML table
 */
class TipsFullScheduleScraper {
  constructor() {
    this.baseUrl = 'https://www.tips.co.th/container/shipSched/List';
  }

  async scrapeFullSchedule() {
    try {
      console.error('🔍 Scraping full schedule from TIPS (B4) via HTTP');

      const html = await this.fetchPage();
      const vessels = this.parseHTML(html);
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

  async scrapeSingleVessel(vesselName, voyageCode) {
    try {
      console.error(`🔍 TIPS single vessel lookup: ${vesselName}, voyage: ${voyageCode || 'any'}`);

      const html = await this.fetchPage();
      const vessels = this.parseHTML(html);

      // Filter by vessel name (case-insensitive substring match)
      const vesselUpper = vesselName.toUpperCase();
      const matches = vessels.filter(v => {
        const name = (v.vessel_name || '').toUpperCase();
        return name === vesselUpper || name.includes(vesselUpper) || vesselUpper.includes(name);
      });

      if (matches.length === 0) {
        console.error(`❌ Vessel not found in TIPS schedule`);
        return {
          success: true,
          vessel_found: false,
          vessel_name: vesselName,
          voyage_code: voyageCode || null,
          message: 'Vessel not found in schedule',
          details: 'The terminal was accessible, but the specified vessel was not found in the current schedule.'
        };
      }

      // Find best match: prefer exact voyage match, then fuzzy voyage, otherwise first
      let match = matches[0];
      if (voyageCode) {
        const voyageUpper = voyageCode.toUpperCase();
        const variations = this.generateVoyageVariations(voyageCode).map(v => v.toUpperCase());

        // Try exact match first
        const exactMatch = matches.find(v => {
          const voy = (v.voyage || '').toUpperCase();
          return voy === voyageUpper || voy.includes(voyageUpper) || voyageUpper.includes(voy);
        });

        if (exactMatch) {
          match = exactMatch;
          console.error(`✅ Exact voyage match: ${match.voyage}`);
        } else {
          // Try fuzzy variations
          const fuzzyMatch = matches.find(v => {
            const voy = (v.voyage || '').toUpperCase();
            return variations.some(variation => voy.includes(variation) || variation.includes(voy));
          });

          if (fuzzyMatch) {
            match = fuzzyMatch;
            console.error(`✅ Fuzzy voyage match: ${match.voyage}`);
          } else {
            console.error(`⚠️ No voyage match for "${voyageCode}", using first: ${match.voyage}`);
          }
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
        raw_data: {
          table_row: [match.vessel_name, null, match.voyage, match.eta, match.etd, match.cutoff],
          service: match.service
        }
      };

    } catch (error) {
      console.error(`❌ TIPS single vessel error:`, error.message);
      return {
        success: false,
        error: error.message,
        vessel_name: vesselName,
        voyage_code: voyageCode || null,
        details: 'Scraper encountered an error while querying TIPS'
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

        // TIPS table: 11 columns per data row
        // [0] Vessel Name       [5] ETA (estimate)    [9]  Closing Time
        // [1] Id (abbreviation) [6] ETD (estimate)    [10] Service Code
        // [2] Radio Call Sign   [7] ATA (actual)
        // [3] I/B Voyage        [8] ATD (actual)
        // [4] O/B Voyage

        if (cells.length >= 11) {
          const vesselName = cells[0]?.text?.trim() || '';
          const voyageIn = cells[3]?.text?.trim() || '';
          const voyageOut = cells[4]?.text?.trim() || '';
          const etaEstimate = cells[5]?.text?.trim() || '';
          const etdEstimate = cells[6]?.text?.trim() || '';
          const ataActual = cells[7]?.text?.trim() || '';
          const atdActual = cells[8]?.text?.trim() || '';
          const closingTime = cells[9]?.text?.trim() || '';
          const service = cells[10]?.text?.trim() || '';

          if (vesselName &&
              vesselName.length > 2 &&
              !vesselName.toLowerCase().includes('vessel') &&
              vesselName !== 'Vessel Name') {

            // Use actual dates if available, otherwise estimates
            const eta = ataActual || etaEstimate;
            const etd = atdActual || etdEstimate;

            results.push({
              vessel_name: vesselName,
              voyage: voyageIn || voyageOut,
              eta: this.formatDate(eta),
              etd: this.formatDate(etd),
              cutoff: this.formatDate(closingTime) || null,
              service: service || null,
              berth: 'B4'
            });
          }
        }
      } catch (e) {
        // Skip invalid rows
      }
    }

    return results;
  }

  // TIPS date format: "DD/MM/YYYY HH:MM" → ISO "YYYY-MM-DDThh:mm:00"
  formatDate(dateStr) {
    if (!dateStr || dateStr.trim() === '') return null;

    const match = dateStr.trim().match(/(\d{2})\/(\d{2})\/(\d{4})\s*(\d{2}):(\d{2})/);
    if (match) {
      const [, day, month, year, hour, minute] = match;
      return `${year}-${month}-${day}T${hour}:${minute}:00`;
    }

    // Date only (no time)
    const dateOnly = dateStr.trim().match(/(\d{2})\/(\d{2})\/(\d{4})/);
    if (dateOnly) {
      const [, day, month, year] = dateOnly;
      return `${year}-${month}-${day}`;
    }

    return dateStr.trim();
  }

  // Generate voyage code variations for fuzzy matching
  // e.g. "V.25080S" → ["V.25080S", "25080S", "V 25080S", "V25080S"]
  generateVoyageVariations(voyageCode) {
    if (!voyageCode) return [];

    const variations = [voyageCode];

    // Remove common prefixes
    if (voyageCode.match(/^V\.?\s*/i)) {
      variations.push(voyageCode.replace(/^V\.?\s*/i, ''));
    }

    // Add/remove spaces and dots
    variations.push(voyageCode.replace(/[\s.]/g, ''));
    variations.push(voyageCode.replace(/\./g, ' '));

    // Add common prefixes if not present
    if (!voyageCode.match(/^V/i)) {
      variations.push(`V.${voyageCode}`);
      variations.push(`V${voyageCode}`);
    }

    return [...new Set(variations)];
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
  const scraper = new TipsFullScheduleScraper();
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

module.exports = TipsFullScheduleScraper;
