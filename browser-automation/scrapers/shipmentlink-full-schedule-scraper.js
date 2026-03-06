const https = require('https');
const fs = require('fs');
const path = require('path');

const CACHE_FILE = path.join(__dirname, 'shipmentlink-vessel-codes.json');

class ShipmentLinkScraper {
  constructor() {
    this.baseUrl = 'https://ss.shipmentlink.com/tvs2/jsp';
    this.vesselCache = null;
  }

  // --- Cache management (same pattern as LCB1) ---

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
    console.error('Fetching live vessel list from ShipmentLink...');
    const html = await this.makeRequest(`${this.baseUrl}/TVS2_QueryVessel.jsp?vslCode=`);

    const mapping = {};
    const regex = /value='([A-Z]+)'[^>]*>([^<]+)/g;
    let match;
    while ((match = regex.exec(html)) !== null) {
      const code = match[1].trim();
      const name = match[2].trim().toUpperCase();
      if (code && name) {
        mapping[name] = code;
      }
    }

    if (Object.keys(mapping).length > 0) {
      return mapping;
    }
    return null;
  }

  lookupInCache(vesselName) {
    const cache = this.loadCache();
    const normalized = vesselName.toUpperCase().trim();

    // Exact match
    if (cache[normalized]) return cache[normalized];

    // Partial match (contains)
    for (const [name, code] of Object.entries(cache)) {
      if (name.includes(normalized) || normalized.includes(name)) {
        return code;
      }
    }

    return null;
  }

  async findVesselCode(vesselName, forceRefresh = false) {
    if (!forceRefresh) {
      const cached = this.lookupInCache(vesselName);
      if (cached) {
        console.error(`Cache hit: ${vesselName} -> ${cached}`);
        return cached;
      }
      console.error(`Cache miss for "${vesselName}", fetching live...`);
    }

    const mapping = await this.fetchLiveVesselMapping();
    if (!mapping) return null;

    this.saveCache(mapping);
    return this.lookupInCache(vesselName);
  }

  // --- Single vessel mode ---

  async scrapeSingleVessel(vesselName, voyageCode) {
    console.error(`Looking up vessel: ${vesselName}, voyage: ${voyageCode || '(any)'}`);

    const vesselCode = await this.findVesselCode(vesselName);
    if (!vesselCode) {
      console.error(`Vessel "${vesselName}" not found in ShipmentLink dropdown`);
      return {
        success: true,
        vessel_found: false,
        vessel_name: vesselName,
        voyage_code: voyageCode || null,
        message: 'Vessel not found in ShipmentLink vessel list'
      };
    }

    console.error(`Found vessel code: ${vesselCode} for ${vesselName}`);
    const html = await this.makeRequest(`${this.baseUrl}/TVS2_QueryScheduleByVessel.jsp?vslCode=${vesselCode}`);
    const schedules = this.parseHTML(html);

    // If no data and code came from cache, might be stale — refresh and retry
    if (schedules.length === 0 && !this.lastWasLiveRefresh) {
      console.error(`No data with cached code ${vesselCode}, refreshing cache...`);
      this.lastWasLiveRefresh = true;
      const freshCode = await this.findVesselCode(vesselName, true);
      if (freshCode && freshCode !== vesselCode) {
        console.error(`Code changed: ${vesselCode} -> ${freshCode}, retrying...`);
        const retryHtml = await this.makeRequest(`${this.baseUrl}/TVS2_QueryScheduleByVessel.jsp?vslCode=${freshCode}`);
        const retrySchedules = this.parseHTML(retryHtml);
        if (retrySchedules.length > 0) {
          const match = this.findVesselMatch(retrySchedules, voyageCode);
          return this.buildSuccessResult(match, voyageCode);
        }
      }
    }

    if (schedules.length === 0) {
      console.error(`No schedule data found for ${vesselName}`);
      return {
        success: true,
        vessel_found: true,
        vessel_name: vesselName,
        voyage_code: voyageCode || null,
        eta: null,
        message: 'Vessel exists but no current LAEM CHABANG schedule'
      };
    }

    const match = this.findVesselMatch(schedules, voyageCode);
    return this.buildSuccessResult(match, voyageCode);
  }

  buildSuccessResult(match, voyageCode) {
    return {
      success: true,
      vessel_found: true,
      vessel_name: match.vessel_name,
      voyage_code: match.voyage || voyageCode || null,
      berth: match.berth || 'B2',
      eta: match.eta,
      etd: match.etd,
      raw_data: match.raw_data
    };
  }

  findVesselMatch(schedules, voyageCode) {
    if (!voyageCode || schedules.length === 1) {
      return schedules[0];
    }

    const normalized = voyageCode.toUpperCase().trim();

    // Exact match
    const exact = schedules.find(s => {
      const v = (s.voyage || '').toUpperCase().trim();
      return v === normalized;
    });
    if (exact) return exact;

    // Partial match (contains)
    const partial = schedules.find(s => {
      const v = (s.voyage || '').toUpperCase().trim();
      return v.includes(normalized) || normalized.includes(v);
    });
    if (partial) return partial;

    // Fallback to first (P4.5 -- voyage mismatch, will be fixed later)
    return schedules[0];
  }

  // --- HTML parsing ---

  parseHTML(html) {
    const schedules = [];

    const voyagePattern = /<span class='f12wrdb2'>\s*(.*?)\s*<\/span>/g;
    let voyageMatch;

    while ((voyageMatch = voyagePattern.exec(html)) !== null) {
      const fullVoyageName = voyageMatch[1].trim();
      // Voyage is the last space-separated token (formats: 1179-084B, 26002N, 0FDGTW1MA, etc.)
      const parts = fullVoyageName.split(/\s+/);
      const voyage = parts.length > 1 ? parts[parts.length - 1] : null;
      const vesselName = parts.length > 1 ? parts.slice(0, -1).join(' ') : fullVoyageName;

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

    // Format: MM/DD -> YYYY-MM-DD 00:00:00
    const parts = dateStr.split('/');
    if (parts.length !== 2) return null;

    const [month, day] = parts;
    const now = new Date();
    const currentMonth = now.getMonth() + 1;
    const targetMonth = parseInt(month);

    // Handle year rollover
    let year = now.getFullYear();
    if (currentMonth >= 10 && targetMonth <= 3) {
      year++;
    } else if (currentMonth <= 3 && targetMonth >= 10) {
      year--;
    }

    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')} 00:00:00`;
  }

  // --- HTTP request with retry + timeout ---

  makeRequest(url, retries = 2) {
    return this._tryRequest(url, retries);
  }

  async _tryRequest(url, retries) {
    for (let attempt = 1; attempt <= retries; attempt++) {
      try {
        return await this._doRequest(url);
      } catch (err) {
        if (attempt < retries) {
          console.error(`Request failed (attempt ${attempt}/${retries}): ${err.message}, retrying...`);
        } else {
          throw err;
        }
      }
    }
  }

  _doRequest(url) {
    return new Promise((resolve, reject) => {
      const req = https.get(url, { timeout: 30000 }, (res) => {
        let data = '';
        res.on('data', (chunk) => { data += chunk; });
        res.on('end', () => { resolve(data); });
      });

      req.on('timeout', () => {
        req.destroy();
        reject(new Error('Request timed out after 30s'));
      });

      req.on('error', reject);
    });
  }
}

// --- CLI ---

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
  const scraper = new ShipmentLinkScraper();
  const args = parseArgs();

  if (!args.vessel) {
    console.error('Usage: node shipmentlink-full-schedule-scraper.js --vessel "VESSEL NAME" [--voyage "VOYAGE"]');
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
