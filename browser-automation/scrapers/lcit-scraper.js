const https = require('https');
const { DOMParser } = require('xmldom');

class LCITScraper {
    constructor() {
        this.apiUrl = 'https://www.lcit.com/Lcit.asmx/GetFullVessel';
    }

    async scrapeVesselSchedule(vesselName, voyageCode = null) {
        console.error(`Starting LCIT API scraper for vessel: ${vesselName}, voyage: ${voyageCode || 'any'}`);

        try {
            // Query LCIT API with the vessel name
            const url = `${this.apiUrl}?vessel=${encodeURIComponent(vesselName)}&voy=${encodeURIComponent(voyageCode || '')}`;
            console.error(`Requesting: ${url}`);

            const xmlData = await this.makeRequest(url);
            console.error('API response received, parsing XML...');

            const vessels = this.parseXML(xmlData);
            console.error(`Found ${vessels.length} matching vessel(s)`);

            if (vessels.length === 0) {
                return {
                    success: false,
                    vessel_name: vesselName,
                    voyage_code: voyageCode,
                    message: 'Vessel not found in schedule',
                    details: 'The terminal API was accessible, but the specified vessel was not found in the current schedule.'
                };
            }

            // Find best match: prefer exact voyage match, otherwise take first result
            let match = vessels[0];
            if (voyageCode) {
                const voyageUpper = voyageCode.toUpperCase();
                const exactMatch = vessels.find(v => {
                    const voy = (v.voyage || '').toUpperCase();
                    return voy === voyageUpper || voy.includes(voyageUpper) || voyageUpper.includes(voy);
                });
                if (exactMatch) {
                    match = exactMatch;
                    console.error(`Exact voyage match found: ${match.voyage}`);
                } else {
                    console.error(`No exact voyage match for "${voyageCode}", using first result: ${match.voyage}`);
                }
            }

            console.error('Matched vessel data:', JSON.stringify(match));

            return {
                success: true,
                vessel_name: match.vessel_name || vesselName,
                voyage_code: match.voyage || voyageCode,
                berth: match.berth,
                eta: this.parseDate(match.eta),
                etd: this.parseDate(match.etd),
                cutoff: this.parseDate(match.cutoff),
                opengate: this.parseDate(match.opengate),
                raw_data: {
                    table_row: [match.berth, match.vessel_name, match.voyage, null, match.cutoff, match.opengate, match.eta, match.etd],
                    status: match.status
                }
            };

        } catch (error) {
            console.error('LCIT API scraper error:', error.message);

            return {
                success: false,
                error: error.message,
                vessel_name: vesselName,
                voyage_code: voyageCode,
                details: 'Scraper encountered an error while querying LCIT API'
            };
        }
    }

    makeRequest(url) {
        return new Promise((resolve, reject) => {
            const options = {
                headers: {
                    'Referer': 'https://www.lcit.com/vessel',
                    'X-Requested-With': 'XMLHttpRequest',
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept': 'application/xml, text/xml, */*; q=0.01'
                },
                timeout: 30000
            };

            const req = https.get(url, options, (res) => {
                if (res.statusCode !== 200) {
                    reject(new Error(`HTTP ${res.statusCode} from LCIT API`));
                    return;
                }

                let data = '';
                res.on('data', (chunk) => { data += chunk; });
                res.on('end', () => { resolve(data); });
            });

            req.on('error', (err) => { reject(err); });
            req.on('timeout', () => {
                req.destroy();
                reject(new Error('Request timeout connecting to LCIT API'));
            });
        });
    }

    parseXML(xmlText) {
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
        const results = [];

        const schedules = xmlDoc.getElementsByTagName('AllVesselSchedule');

        for (let i = 0; i < schedules.length; i++) {
            const schedule = schedules[i];

            try {
                const vesselNameVal = this.getElementText(schedule, 'VISIT_VSL_NAME_AN');
                const vscBerth = this.getElementText(schedule, 'VSC_BERTH');
                const berth = vscBerth ? vscBerth.substring(0, 2) : 'B5';
                const voyageIn = this.getElementText(schedule, 'EXT_IN_VOY_C');
                const voyageOut = this.getElementText(schedule, 'EXT_VOY_C');
                const cutoff = this.getElementText(schedule, 'CUTOFF_TM');
                const openGate = this.getElementText(schedule, 'RECEIVE_TM');
                const estimateArrival = this.getElementText(schedule, 'INIT_ETB_TM');
                const estimateDeparture = this.getElementText(schedule, 'INIT_ETD_TM');
                const visitStateCode = this.getElementText(schedule, 'VISIT_STATE_CODE');

                // Use actual dates if vessel has already berthed/departed
                let actualBerthing = '';
                let actualUnberthing = '';

                if (visitStateCode === 'DP') {
                    actualBerthing = this.getElementText(schedule, 'VSL_BERTH_D');
                    actualUnberthing = this.getElementText(schedule, 'EST_DPTR_D');
                } else if (visitStateCode === 'OD') {
                    actualBerthing = this.getElementText(schedule, 'VSL_BERTH_D');
                }

                if (vesselNameVal && vesselNameVal.length > 2) {
                    results.push({
                        vessel_name: vesselNameVal,
                        voyage: voyageIn || voyageOut,
                        eta: actualBerthing || estimateArrival,
                        etd: actualUnberthing || estimateDeparture,
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

    getElementText(parent, tagName) {
        const elements = parent.getElementsByTagName(tagName);
        if (elements && elements.length > 0 && elements[0].firstChild) {
            return elements[0].firstChild.nodeValue?.trim() || '';
        }
        return '';
    }

    parseDate(dateStr) {
        if (!dateStr || dateStr === '') return null;

        const dateString = dateStr.trim();

        // Match LCIT format: "DD MMM YY/HH:MM"
        const match = dateString.match(/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{2})\/(\d{2}):(\d{2})/i);

        if (match) {
            const [, day, month, year, hour, minute] = match;
            const months = {
                'JAN': '01', 'FEB': '02', 'MAR': '03', 'APR': '04',
                'MAY': '05', 'JUN': '06', 'JUL': '07', 'AUG': '08',
                'SEP': '09', 'OCT': '10', 'NOV': '11', 'DEC': '12'
            };
            const fullYear = `20${year}`;
            return `${fullYear}-${months[month.toUpperCase()]}-${day.padStart(2, '0')}T${hour}:${minute}:00`;
        }

        return dateString;
    }
}

module.exports = LCITScraper;
