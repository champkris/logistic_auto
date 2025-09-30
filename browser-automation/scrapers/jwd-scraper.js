const puppeteer = require('puppeteer');

async function scrapeJWDSchedule(vesselName, voyageCode) {
    let browser = null;

    try {
        console.error(`Starting JWD scraper for vessel: ${vesselName}, voyage: ${voyageCode}`);

        browser = await puppeteer.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

        console.error('Navigating to JWD shipping schedule page...');
        await page.goto('https://www.dg-net.org/th/service-shipping', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Wait longer for potential dynamic content loading
        console.error('Waiting for content to load...');
        await new Promise(resolve => setTimeout(resolve, 10000));

        // Try to trigger any AJAX content loading
        console.error('Attempting to trigger content loading...');
        await page.evaluate(() => {
            // Scroll to trigger any lazy loading
            window.scrollTo(0, document.body.scrollHeight);

            // Try clicking any potential trigger elements
            const buttons = document.querySelectorAll('button, .btn, [onclick]');
            for (const button of buttons) {
                if (button.textContent.toLowerCase().includes('schedule') ||
                    button.textContent.toLowerCase().includes('load') ||
                    button.textContent.toLowerCase().includes('show')) {
                    button.click();
                    break;
                }
            }
        });

        // Wait after potential interaction
        await new Promise(resolve => setTimeout(resolve, 5000));

        console.error('Looking for shipping schedule table...');

        // Parse the shipping schedule table
        const vessels = await page.evaluate((searchVessel, searchVoyage) => {
            const results = [];

            // Look for the shipping schedule table
            const tables = document.querySelectorAll('table');

            for (const table of tables) {
                const rows = table.querySelectorAll('tr');

                // Skip header row, start from index 1
                for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    const cells = row.querySelectorAll('td');

                    if (cells.length < 6) continue; // Ensure we have enough columns

                    // Extract data based on the table structure from screenshot:
                    // No. | VESSEL NAME | VOYAGE (IN/OUT) | ESTIMATE (ARRIVAL/DEPARTURE) | BERTH
                    const vesselNameCell = cells[1]; // Column 2: VESSEL NAME
                    const voyageInCell = cells[2];   // Column 3: IN voyage
                    const voyageOutCell = cells[3];  // Column 4: OUT voyage
                    const arrivalCell = cells[4];    // Column 5: ARRIVAL
                    const departureCell = cells[5];  // Column 6: DEPARTURE
                    const berthCell = cells[6];      // Column 7: BERTH

                    if (!vesselNameCell || !voyageInCell) continue;

                    const vesselNameText = vesselNameCell.textContent.trim();
                    const voyageInText = voyageInCell.textContent.trim();
                    const voyageOutText = voyageOutCell ? voyageOutCell.textContent.trim() : '';
                    const arrivalText = arrivalCell ? arrivalCell.textContent.trim() : '';
                    const departureText = departureCell ? departureCell.textContent.trim() : '';
                    const berthText = berthCell ? berthCell.textContent.trim() : '';

                    // Check if this row matches our search criteria
                    const vesselMatch = vesselNameText.toLowerCase().includes(searchVessel.toLowerCase());
                    const voyageInMatch = voyageInText === searchVoyage;
                    const voyageOutMatch = voyageOutText === searchVoyage;

                    if (vesselMatch && (voyageInMatch || voyageOutMatch)) {
                        // Determine which date to use (arrival or departure) based on which voyage matched
                        let eta = null;
                        let isArrival = true;

                        if (voyageInMatch && arrivalText) {
                            eta = arrivalText;
                            isArrival = true;
                        } else if (voyageOutMatch && departureText) {
                            eta = departureText;
                            isArrival = false;
                        }

                        results.push({
                            vessel_name: vesselNameText,
                            voyage_in: voyageInText,
                            voyage_out: voyageOutText,
                            matched_voyage: voyageInMatch ? voyageInText : voyageOutText,
                            eta: eta,
                            eta_type: isArrival ? 'arrival' : 'departure',
                            berth: berthText,
                            row_data: {
                                vessel: vesselNameText,
                                voyage_in: voyageInText,
                                voyage_out: voyageOutText,
                                arrival: arrivalText,
                                departure: departureText,
                                berth: berthText
                            }
                        });
                    }
                }
            }

            return results;
        }, vesselName, voyageCode);

        console.error(`Found ${vessels.length} matching vessels`);

        if (vessels.length > 0) {
            const vessel = vessels[0];

            // Format the ETA to standard format if possible
            let formattedEta = vessel.eta;
            if (vessel.eta) {
                try {
                    // Try to parse JWD date format: "30 Sep 2025 05:00:00"
                    const etaMatch = vessel.eta.match(/(\d{1,2})\s+(\w{3})\s+(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2})/);
                    if (etaMatch) {
                        const [, day, month, year, hour, minute, second] = etaMatch;
                        const monthMap = {
                            'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04',
                            'May': '05', 'Jun': '06', 'Jul': '07', 'Aug': '08',
                            'Sep': '09', 'Oct': '10', 'Nov': '11', 'Dec': '12'
                        };

                        if (monthMap[month]) {
                            formattedEta = `${year}-${monthMap[month]}-${day.padStart(2, '0')} ${hour.padStart(2, '0')}:${minute}:${second}`;
                        }
                    }
                } catch (formatError) {
                    console.error('ETA formatting error:', formatError);
                    // Keep original format if parsing fails
                }
            }

            return {
                success: true,
                vessel_found: true,
                voyage_found: true,
                vessel_name: vesselName,
                voyage_code: voyageCode,
                eta: formattedEta,
                eta_type: vessel.eta_type,
                berth: vessel.berth,
                terminal: 'JWD Terminal',
                message: `Found vessel ${vesselName} voyage ${voyageCode} (${vessel.eta_type})`,
                raw_data: vessel
            };
        } else {
            return {
                success: true,
                vessel_found: false,
                voyage_found: false,
                vessel_name: vesselName,
                voyage_code: voyageCode,
                eta: null,
                terminal: 'JWD Terminal',
                message: `Vessel ${vesselName} voyage ${voyageCode} not found in shipping schedule`
            };
        }

    } catch (error) {
        console.error('JWD scraper error:', error);
        return {
            success: false,
            error: error.message,
            vessel_found: false,
            voyage_found: false,
            vessel_name: vesselName,
            voyage_code: voyageCode,
            eta: null
        };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Main execution
(async () => {
    const vesselName = process.argv[2];
    const voyageCode = process.argv[3];

    if (!vesselName || !voyageCode) {
        console.log(JSON.stringify({
            success: false,
            error: 'Missing vessel name or voyage code parameters'
        }));
        process.exit(1);
    }

    const result = await scrapeJWDSchedule(vesselName, voyageCode);
    console.log(JSON.stringify(result));
})();