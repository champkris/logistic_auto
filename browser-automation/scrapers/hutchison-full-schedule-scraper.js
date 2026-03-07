const axios = require('axios');
const { parse } = require('node-html-parser');

/**
 * Hutchison Full Schedule Scraper
 * Scrapes all vessel schedules from Hutchison terminals via HTTP + HTML parsing
 * No Puppeteer needed — page serves static HTML with AJAX pagination
 *
 * How it works:
 * 1. GET page → static HTML with first 15 vessels + APEX session/token
 * 2. POST wwv_flow.show → AJAX returns next 15 vessels (repeat for each page)
 * Cookies from step 1 must be sent in step 2 (session validation)
 *
 * Table structure (10 columns):
 * [0] Vessel Name    [4] Arrival (ETA)       [8] Status (Berthed/Departed/Gate-Opened/Gate-Closed)
 * [1] Vessel ID      [5] Departure (ETD)     [9] Gate Closing Time (DD-MMM-YYYY HH:MM or empty)
 * [2] In Voy         [6] Berth Terminal
 * [3] Out Voy        [7] Release port
 */
class HutchisonFullScheduleScraper {
  constructor() {
    this.baseUrl = 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17';
    this.ajaxUrl = 'https://online.hutchisonports.co.th/hptpcs/wwv_flow.show';
  }

  async scrapeFullSchedule(terminal) {
    try {
      console.error(`🔍 Scraping full schedule from Hutchison via HTTP`);

      const { html, cookies, sessionId, regionId, token } = await this.fetchPage1();
      const page1Vessels = this.parseTableRows(html);
      console.error(`   Page 1: ${page1Vessels.length} vessels`);

      // Parse pagination options from page 1
      const pageParams = this.parsePaginationOptions(html);
      let allVessels = [...page1Vessels];

      // Fetch remaining pages
      for (let i = 0; i < pageParams.length; i++) {
        const params = pageParams[i];
        const pageHtml = await this.fetchNextPage(cookies, sessionId, regionId, token, params);
        const pageVessels = this.parseTableRows(pageHtml);
        allVessels = allVessels.concat(pageVessels);
        console.error(`   Page ${i + 2}: ${pageVessels.length} vessels (total: ${allVessels.length})`);
      }

      console.error(`✅ Found ${allVessels.length} total vessels across ${pageParams.length + 1} pages`);

      return {
        success: true,
        terminal: terminal || 'hutchison',
        vessels: allVessels,
        scraped_at: new Date().toISOString()
      };

    } catch (error) {
      console.error(`❌ Error scraping Hutchison:`, error.message);
      return {
        success: false,
        terminal: terminal || 'hutchison',
        error: error.message,
        vessels: []
      };
    }
  }

  async scrapeSingleVessel(vesselName, voyageCode) {
    try {
      console.error(`🔍 Hutchison single vessel lookup: ${vesselName}, voyage: ${voyageCode || 'any'}`);

      const { html, cookies, sessionId, regionId, token } = await this.fetchPage1();

      // Check page 1
      const page1Vessels = this.parseTableRows(html);
      let match = this.findVesselMatch(page1Vessels, vesselName, voyageCode);

      if (!match) {
        // Parse pagination and check remaining pages (early exit when found)
        const pageParams = this.parsePaginationOptions(html);

        for (let i = 0; i < pageParams.length; i++) {
          const pageHtml = await this.fetchNextPage(cookies, sessionId, regionId, token, pageParams[i]);
          const pageVessels = this.parseTableRows(pageHtml);
          match = this.findVesselMatch(pageVessels, vesselName, voyageCode);
          if (match) {
            console.error(`✅ Found on page ${i + 2}`);
            break;
          }
        }
      } else {
        console.error(`✅ Found on page 1`);
      }

      if (!match) {
        console.error(`❌ Vessel not found in Hutchison schedule`);
        return {
          success: true,
          vessel_found: false,
          vessel_name: vesselName,
          voyage_code: voyageCode || null,
          message: 'Vessel not found in schedule',
          details: 'The terminal was accessible, but the specified vessel was not found in the current schedule.'
        };
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
          table_row: [match.vessel_name, null, match.voyage, match.eta, match.etd, match.berth, match.cutoff],
          status: match.status
        }
      };

    } catch (error) {
      console.error(`❌ Hutchison single vessel error:`, error.message);
      return {
        success: false,
        error: error.message,
        vessel_name: vesselName,
        voyage_code: voyageCode || null,
        details: 'Scraper encountered an error while querying Hutchison'
      };
    }
  }

  /**
   * Step 1: GET the page — returns HTML with first 15 vessels + session info
   */
  /**
   * Format date as DD-MMM-YY for APEX URL (e.g. "01-MAR-26")
   */
  formatApexDate(date) {
    const months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    const dd = String(date.getDate()).padStart(2, '0');
    const mmm = months[date.getMonth()];
    const yy = String(date.getFullYear()).slice(-2);
    return `${dd}-${mmm}-${yy}`;
  }

  async fetchPage1() {
    // Set From Date to 7 days ago so recently departed vessels still appear
    // (Hutchison default is today, which misses vessels that departed in the past week)
    const fromDate = new Date();
    fromDate.setDate(fromDate.getDate() - 7);
    const toDate = new Date();
    toDate.setDate(toDate.getDate() + 7);

    const url = `${this.baseUrl}:::::P17_FROM_DATE,P17_TO_DATE:${this.formatApexDate(fromDate)},${this.formatApexDate(toDate)}`;
    console.error(`📅 Date range: ${this.formatApexDate(fromDate)} to ${this.formatApexDate(toDate)}`);

    const response = await axios.get(url, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      },
      timeout: 30000,
      // Keep response as string (don't let axios auto-parse)
      transformResponse: [data => data]
    });

    const html = response.data;

    // Extract cookies from Set-Cookie headers (required for AJAX pagination)
    const setCookies = response.headers['set-cookie'] || [];
    const cookies = setCookies.map(c => c.split(';')[0]).join('; ');

    // Extract APEX session ID from hidden form field
    const sessionMatch = html.match(/p_instance" value="(\d+)"/);
    if (!sessionMatch) {
      throw new Error('Could not extract APEX session from page - site structure may have changed');
    }
    const sessionId = sessionMatch[1];

    // Extract pagination token and region ID from JavaScript onchange handler
    const tokenMatch = html.match(/paginate\('(\d+)',\s*'([^']+)'/);
    if (!tokenMatch) {
      // No pagination means all vessels fit on page 1 (or 0 vessels)
      return { html, cookies, sessionId, regionId: null, token: null };
    }
    const regionId = tokenMatch[1];
    const token = tokenMatch[2];

    return { html, cookies, sessionId, regionId, token };
  }

  /**
   * Step 2: POST for additional pages — AJAX returns HTML table fragment
   */
  async fetchNextPage(cookies, sessionId, regionId, token, params) {
    const response = await axios.post(this.ajaxUrl,
      new URLSearchParams({
        p_flow_id: '114',
        p_flow_step_id: '17',
        p_instance: sessionId,
        p_request: 'PLUGIN=' + token,
        x01: regionId,
        p_widget_action: 'paginate',
        p_pg_min_row: String(params.min),
        p_pg_max_rows: String(params.max),
        p_pg_rows_fetched: String(params.fetched)
      }).toString(),
      {
        headers: {
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded',
          'Cookie': cookies
        },
        timeout: 15000,
        transformResponse: [data => data]
      }
    );

    // Validate we got a table fragment, not a full page (session expired)
    if (response.data.includes('"error"') && response.data.includes('session has ended')) {
      throw new Error('APEX session expired during pagination');
    }

    return response.data;
  }

  /**
   * Parse pagination <select> options from page 1 HTML
   * Returns array of {min, max, fetched} for each additional page
   */
  parsePaginationOptions(html) {
    const options = [];
    const regex = /"min":(\d+),"max":(\d+),"fetched":(\d+)/g;
    let match;
    while ((match = regex.exec(html)) !== null) {
      options.push({
        min: parseInt(match[1]),
        max: parseInt(match[2]),
        fetched: parseInt(match[3])
      });
    }
    return options;
  }

  /**
   * Parse vessel table rows from HTML (works for both page 1 and AJAX fragments)
   */
  parseTableRows(html) {
    const root = parse(html);
    const results = [];

    for (const row of root.querySelectorAll('tr')) {
      try {
        const cells = row.querySelectorAll('td');
        if (cells.length < 7) continue;

        const vesselName = cells[0]?.text?.trim() || '';

        // Skip header rows and pagination row
        if (!vesselName ||
            vesselName.length <= 2 ||
            vesselName === 'Vessel Name' ||
            vesselName.includes('row(s)') ||
            vesselName === 'Select Pagination') continue;

        const voyageIn = cells[2]?.text?.trim() || '';
        const voyageOut = cells[3]?.text?.trim() || '';
        const eta = cells[4]?.text?.trim() || '';
        const etd = cells[5]?.text?.trim() || '';
        const berth = cells[6]?.text?.trim() || '';
        const status = cells[8]?.text?.trim() || '';
        const gateClosing = cells[9]?.text?.trim() || '';

        results.push({
          vessel_name: vesselName,
          voyage: voyageIn || voyageOut,
          eta: this.formatDate(eta),
          etd: this.formatDate(etd),
          berth: berth,
          status: status || null,
          cutoff: this.formatGateClosingDate(gateClosing) || null
        });
      } catch (e) {
        // Skip invalid rows
      }
    }

    return results;
  }

  /**
   * Find best vessel match from a list
   */
  findVesselMatch(vessels, vesselName, voyageCode) {
    const vesselUpper = vesselName.toUpperCase();
    const matches = vessels.filter(v => {
      const name = (v.vessel_name || '').toUpperCase();
      return name === vesselUpper || name.includes(vesselUpper) || vesselUpper.includes(name);
    });

    if (matches.length === 0) return null;

    // Find best match: prefer exact voyage match, otherwise first
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

    return match;
  }

  // ETA/ETD date format: "DD/MM/YYYY HH:MM" → ISO "YYYY-MM-DDThh:mm:00"
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

  // Gate Closing date format: "DD-MMM-YYYY HH:MM" → ISO "YYYY-MM-DDThh:mm:00"
  // e.g. "05-MAR-2026 19:00" → "2026-03-05T19:00:00"
  formatGateClosingDate(dateStr) {
    if (!dateStr || dateStr.trim() === '') return null;

    const months = {
      'JAN': '01', 'FEB': '02', 'MAR': '03', 'APR': '04',
      'MAY': '05', 'JUN': '06', 'JUL': '07', 'AUG': '08',
      'SEP': '09', 'OCT': '10', 'NOV': '11', 'DEC': '12'
    };

    const match = dateStr.trim().match(/(\d{2})-([A-Z]{3})-(\d{4})\s+(\d{2}):(\d{2})/);
    if (match) {
      const [, day, monthStr, year, hour, minute] = match;
      const month = months[monthStr];
      if (month) {
        return `${year}-${month}-${day}T${hour}:${minute}:00`;
      }
    }

    // Not a recognized date format (could be "Gate-Closed" status text)
    return null;
  }
}

function parseArgs(argv) {
  const args = { vessel: null, voyage: null, terminal: null };
  for (let i = 2; i < argv.length; i++) {
    if (argv[i] === '--vessel' && argv[i + 1]) {
      args.vessel = argv[++i];
    } else if (argv[i] === '--voyage' && argv[i + 1]) {
      args.voyage = argv[++i];
    } else if (!argv[i].startsWith('--')) {
      // Positional arg = terminal (backward compatible with cron caller)
      args.terminal = argv[i];
    }
  }
  return args;
}

async function main() {
  const scraper = new HutchisonFullScheduleScraper();
  const args = parseArgs(process.argv);

  try {
    if (args.vessel) {
      const result = await scraper.scrapeSingleVessel(args.vessel, args.voyage);
      console.log(JSON.stringify(result));
    } else {
      const result = await scraper.scrapeFullSchedule(args.terminal);
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

module.exports = HutchisonFullScheduleScraper;
