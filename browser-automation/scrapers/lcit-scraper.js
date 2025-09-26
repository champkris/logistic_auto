const { chromium } = require('playwright');

class LCITScraper {
    constructor() {
        this.baseUrl = 'https://www.lcit.com/vessel';
    }

    async scrapeVesselSchedule(vesselName, voyageCode = null) {
        console.error(`Starting LCIT scraper for vessel: ${vesselName}, voyage: ${voyageCode || 'any'}`);
        let browser = null;
        let context = null;

        try {
            browser = await chromium.launch({
                headless: true
            });

            context = await browser.newContext();
            const page = await context.newPage();
            page.setDefaultTimeout(30000);

            // Construct URL with query parameters
            const searchUrl = `${this.baseUrl}?vsl=${encodeURIComponent(vesselName)}&voy=${encodeURIComponent(voyageCode || '')}`;
            console.error(`Navigating to: ${searchUrl}`);

            await page.goto(searchUrl, {
                waitUntil: 'networkidle',
                timeout: 30000
            });

            console.error('Navigation successful');

            // Check if vessel is found
            const pageText = await page.textContent('body');

            if (!pageText.includes(vesselName.toUpperCase()) && !pageText.includes(vesselName)) {
                console.error('Vessel not found in page content');
                return {
                    success: false,
                    vessel_name: vesselName,
                    voyage_code: voyageCode,
                    message: 'Vessel not found in schedule',
                    details: 'The terminal was accessible, but the specified vessel was not found in the current schedule.'
                };
            }

            console.error('Vessel found in page content');

            // Extract table data
            const vesselData = await page.evaluate((targetVessel) => {
                const table = document.querySelector('table');
                if (!table) return null;

                const rows = table.querySelectorAll('tr');
                for (let i = 0; i < rows.length; i++) {
                    const cells = rows[i].querySelectorAll('td, th');
                    const rowData = [];

                    for (let cell of cells) {
                        rowData.push(cell.innerText.trim());
                    }

                    // Look for vessel name in row
                    const rowText = rowData.join(' ').toUpperCase();
                    if (rowText.includes(targetVessel.toUpperCase()) && rowData.length > 5) {
                        return {
                            berth: rowData[0] || null,
                            vessel: rowData[1] || null,
                            voyage: rowData[2] || null,
                            cutoff: rowData[4] || null,
                            opengate: rowData[5] || null,
                            estimate_berthing: rowData[6] || null,
                            estimate_unberthing: rowData[7] || null,
                            raw_row: rowData
                        };
                    }
                }
                return null;
            }, vesselName);

            if (!vesselData) {
                return {
                    success: false,
                    vessel_name: vesselName,
                    voyage_code: voyageCode,
                    message: 'Vessel found on page but could not extract schedule data',
                    details: 'Vessel appears on the page but table parsing failed.'
                };
            }

            console.error('Extracted vessel data:', vesselData);

            // Helper function to convert month abbreviation to number
            const getMonthNumber = (monthAbbr) => {
                const months = {
                    'JAN': '01', 'FEB': '02', 'MAR': '03', 'APR': '04',
                    'MAY': '05', 'JUN': '06', 'JUL': '07', 'AUG': '08',
                    'SEP': '09', 'OCT': '10', 'NOV': '11', 'DEC': '12'
                };
                return months[monthAbbr.toUpperCase()] || '01';
            };

            // Parse dates from the extracted data
            const parseDate = (dateStr) => {
                if (!dateStr || dateStr === '') return null;

                // Convert LCIT format "11 OCT 25/23:00" to standard format
                const dateString = dateStr.trim();

                // Match the pattern: "DD MMM YY/HH:MM"
                const match = dateString.match(/(\d{1,2})\s+(OCT|NOV|DEC|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP)\s+(\d{2})\/(\d{2}):(\d{2})/i);

                if (match) {
                    const [, day, month, year, hour, minute] = match;

                    // Convert 2-digit year to 4-digit (assuming 20xx)
                    const fullYear = `20${year}`;

                    // Return in ISO format that Carbon can parse
                    return `${fullYear}-${getMonthNumber(month)}-${day.padStart(2, '0')}T${hour}:${minute}:00`;
                }

                return dateString; // Return original if parsing fails
            };

            return {
                success: true,
                vessel_name: vesselData.vessel || vesselName,
                voyage_code: vesselData.voyage || voyageCode,
                berth: vesselData.berth,
                eta: parseDate(vesselData.estimate_berthing),
                etd: parseDate(vesselData.estimate_unberthing),
                cutoff: parseDate(vesselData.cutoff),
                opengate: parseDate(vesselData.opengate),
                raw_data: {
                    table_row: vesselData.raw_row
                }
            };

        } catch (error) {
            console.error('LCIT scraper error:', error.message);

            return {
                success: false,
                error: error.message,
                vessel_name: vesselName,
                voyage_code: voyageCode,
                details: 'Scraper encountered an error while processing the page'
            };
        } finally {
            if (context) {
                await context.close();
            }
            if (browser) {
                await browser.close();
            }
        }
    }
}

module.exports = LCITScraper;