const https = require('https');
const fs = require('fs');
const path = require('path');
const { parse } = require('node-html-parser');

const CACHE_FILE = path.join(__dirname, 'lcb1-vessel-codes.json');

class LCB1Scraper {
  constructor() {
    this.baseUrl = 'https://www.lcb1.com';
    this.vesselCache = null;
  }

  loadCache() {
    if (this.vesselCache) return this.vesselCache;
    try {
      const data = fs.readFileSync(CACHE_FILE, 'utf8');
      this.vesselCache = JSON.parse(data);
      console.error(`Loaded ${Object.keys(this.vesselCache).length} vessels from cache`);
    } catch {
      this.vesselCache = {};
    }
    return this.vesselCache;
  }

  saveCache(mapping) {
    this.vesselCache = mapping;
    fs.writeFileSync(CACHE_FILE, JSON.stringify(mapping, null, 2));
    console.error(`Saved ${Object.keys(mapping).length} vessels to cache`);
  }

  async fetchLiveVesselMapping() {
    console.error('Fetching live vessel list from LCB1...');
    const html = await this.makeRequest('/BerthSchedule', 'GET');
    const root = parse(html);
    const select = root.querySelector('#txtVesselName');
    if (!select) return null;

    const mapping = {};
    for (const opt of select.querySelectorAll('option')) {
      const name = opt.text.trim().toUpperCase();
      const code = (opt.getAttribute('value') || '').trim();
      if (name && code) {
        if (!mapping[name]) {
          mapping[name] = [];
        }
        if (!mapping[name].includes(code)) {
          mapping[name].push(code);
        }
      }
    }
    return mapping;
  }

  lookupInCache(vesselName) {
    const cache = this.loadCache();
    const normalizedName = vesselName.toUpperCase().trim();

    // Exact match
    if (cache[normalizedName]) {
      const val = cache[normalizedName];
      return Array.isArray(val) ? val : [val];
    }

    // Partial match (contains)
    for (const [name, codes] of Object.entries(cache)) {
      if (name.includes(normalizedName) || normalizedName.includes(name)) {
        return Array.isArray(codes) ? codes : [codes];
      }
    }

    return null;
  }

  async findVesselCodes(vesselName, forceRefresh = false) {
    // Try cache first (unless forced refresh)
    if (!forceRefresh) {
      const cached = this.lookupInCache(vesselName);
      if (cached) {
        console.error(`Cache hit: ${vesselName} → ${cached.length} code(s): ${cached.join(', ')}`);
        return cached;
      }
      console.error(`Cache miss for "${vesselName}", fetching live...`);
    }

    // Live fetch and refresh entire cache
    const mapping = await this.fetchLiveVesselMapping();
    if (!mapping) return null;

    this.saveCache(mapping);
    return this.lookupInCache(vesselName);
  }

  async scrapeSingleVessel(vesselName, voyageCode) {
    console.error(`Looking up vessel: ${vesselName}, voyage: ${voyageCode || '(any)'}`);

    let vesselCodes = await this.findVesselCodes(vesselName);
    if (!vesselCodes || vesselCodes.length === 0) {
      console.error(`Vessel "${vesselName}" not found in LCB1 dropdown`);
      return {
        success: true,
        vessel_found: false,
        vessel_name: vesselName,
        voyage_code: voyageCode || null,
        message: 'Vessel not found in LCB1 vessel list'
      };
    }

    // Try each code — duplicate vessel names may have different codes, only one has data
    for (let i = 0; i < vesselCodes.length; i++) {
      const code = vesselCodes[i];
      console.error(`Trying code ${i + 1}/${vesselCodes.length}: ${code} for ${vesselName}`);
      const postData = `vesselName=${encodeURIComponent(code)}&voyageIn=&voyageOut=&pageSize=100&page=1`;
      const html = await this.makeRequest('/BerthSchedule/Detail', 'POST', postData);
      const schedules = this.parseScheduleHTML(html, vesselName);

      if (schedules.length > 0) {
        console.error(`Found ${schedules.length} schedule(s) with code ${code}`);
        const match = this.findVesselMatch(schedules, voyageCode);
        return this.buildSuccessResult(match, voyageCode);
      }
    }

    // All cached codes returned no data — refresh cache and retry with any new codes
    console.error(`No data with ${vesselCodes.length} cached code(s), refreshing cache...`);
    const freshCodes = await this.findVesselCodes(vesselName, true);
    if (freshCodes) {
      const newCodes = freshCodes.filter(c => !vesselCodes.includes(c));
      for (const code of newCodes) {
        console.error(`Trying new code from refresh: ${code}`);
        const postData = `vesselName=${encodeURIComponent(code)}&voyageIn=&voyageOut=&pageSize=100&page=1`;
        const html = await this.makeRequest('/BerthSchedule/Detail', 'POST', postData);
        const schedules = this.parseScheduleHTML(html, vesselName);

        if (schedules.length > 0) {
          const match = this.findVesselMatch(schedules, voyageCode);
          return this.buildSuccessResult(match, voyageCode);
        }
      }
    }

    console.error(`No schedule data found for ${vesselName}`);
    return {
      success: true,
      vessel_found: false,
      vessel_name: vesselName,
      voyage_code: voyageCode || null,
      message: 'No schedule data found for this vessel'
    };
  }

  buildSuccessResult(match, voyageCode) {
    return {
      success: true,
      vessel_found: true,
      vessel_name: match.vessel_name,
      voyage_code: match.voyage || voyageCode || null,
      berth: match.port_terminal || null,
      eta: match.eta,
      etd: match.etd,
      raw_data: match.raw_data
    };
  }

  findVesselMatch(schedules, voyageCode) {
    if (!voyageCode || schedules.length === 1) {
      return schedules[0];
    }

    const normalizedVoyage = voyageCode.toUpperCase().trim();

    // Exact match on voyage_in or voyage_out
    const exactMatch = schedules.find(s => {
      const voyIn = (s.raw_data.voyage_in || '').toUpperCase().trim();
      const voyOut = (s.raw_data.voyage_out || '').toUpperCase().trim();
      return voyIn === normalizedVoyage || voyOut === normalizedVoyage;
    });

    if (exactMatch) return exactMatch;

    // Partial match (contains)
    const partialMatch = schedules.find(s => {
      const voyIn = (s.raw_data.voyage_in || '').toUpperCase().trim();
      const voyOut = (s.raw_data.voyage_out || '').toUpperCase().trim();
      return voyIn.includes(normalizedVoyage) || normalizedVoyage.includes(voyIn) ||
             voyOut.includes(normalizedVoyage) || normalizedVoyage.includes(voyOut);
    });

    if (partialMatch) return partialMatch;

    // Fallback to first result (P4.5 -- voyage mismatch, will be fixed later)
    return schedules[0];
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
          const eta = this.formatDate(berthingTime);
          const etd = this.formatDate(departureTime);

          if (eta) {
            schedules.push({
              vessel_name: rowVessel,
              voyage: voyageIn || voyageOut || null,
              port_terminal: terminal || 'A0',
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

  formatDate(dateStr) {
    if (!dateStr || dateStr === '' || dateStr === '-') return null;

    try {
      // LCB1 format: "DD/MM/YYYY - HH:MM"
      const match = dateStr.match(/(\d{2})\/(\d{2})\/(\d{4})\s*-\s*(\d{2}):(\d{2})/);
      if (match) {
        const [, day, month, year, hour, minute] = match;
        return `${year}-${month}-${day}T${hour}:${minute}:00`;
      }

      return null;
    } catch (error) {
      return null;
    }
  }

  async makeRequest(path, method = 'GET', postData = null, retries = 2) {
    for (let attempt = 1; attempt <= retries; attempt++) {
      try {
        return await this._doRequest(path, method, postData);
      } catch (err) {
        if (attempt < retries) {
          console.error(`Request failed (attempt ${attempt}/${retries}): ${err.message}, retrying...`);
        } else {
          throw err;
        }
      }
    }
  }

  _doRequest(path, method = 'GET', postData = null) {
    return new Promise((resolve, reject) => {
      const options = {
        hostname: 'www.lcb1.com',
        path: path,
        method: method,
        timeout: 30000,
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

      req.on('timeout', () => {
        req.destroy();
        reject(new Error('Request timed out after 30s'));
      });

      req.on('error', reject);

      if (postData) {
        req.write(postData);
      }

      req.end();
    });
  }
}

function parseArgs() {
  const args = process.argv.slice(2);
  const parsed = {};

  for (let i = 0; i < args.length; i++) {
    if (args[i] === '--vessel' && args[i + 1]) {
      parsed.vessel = args[++i];
    } else if (args[i] === '--voyage' && args[i + 1]) {
      parsed.voyage = args[++i];
    }
  }

  return parsed;
}

async function main() {
  const scraper = new LCB1Scraper();
  const args = parseArgs();

  if (!args.vessel) {
    console.error('Usage: node lcb1-full-schedule-scraper.js --vessel "VESSEL NAME" [--voyage "VOYAGE"]');
    console.log(JSON.stringify({ success: false, error: 'Missing --vessel argument' }));
    process.exit(1);
  }

  try {
    const result = await scraper.scrapeSingleVessel(args.vessel, args.voyage || '');
    console.log(JSON.stringify(result));
  } catch (error) {
    console.error('Error:', error.message);
    console.log(JSON.stringify({ success: false, error: error.message }));
    process.exit(1);
  }
}

main();
