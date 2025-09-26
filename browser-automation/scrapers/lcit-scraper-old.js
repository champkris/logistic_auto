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
                headless: true,
                args: [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-extensions',
                    '--disable-gpu',
                    '--disable-web-security',
                    '--disable-features=VizDisplayCompositor'
                ]
            });

            context = await browser.newContext({
                userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            });

            const page = await context.newPage();

            // Set more reasonable timeout
            page.setDefaultTimeout(30000);

            // Construct URL with query parameters
            const searchUrl = `${this.baseUrl}?vsl=${encodeURIComponent(vesselName)}&voy=${encodeURIComponent(voyageCode || '')}`;
            console.error(`Navigating to: ${searchUrl}`);

            let navigationSuccess = false;
            for (let attempt = 1; attempt <= 3; attempt++) {
                try {
                    await page.goto(searchUrl, {
                        waitUntil: 'networkidle',
                        timeout: 30000
                    });
                    navigationSuccess = true;
                    console.error(`Navigation successful on attempt ${attempt}`);
                    break;
                } catch (navError) {
                    console.error(`Navigation attempt ${attempt} failed:`, navError.message);
                    if (attempt === 3) throw navError;
                    await page.waitForTimeout(2000);
                }
            }

            if (!navigationSuccess) {
                throw new Error('Failed to navigate to LCIT website after 3 attempts');
            }

            // Wait for content to load
            await page.waitForTimeout(5000);

            // Take a screenshot for debugging
            const debugScreenshot = `browser-automation/lcit-debug-${Date.now()}.png`;
            await page.screenshot({ path: debugScreenshot });
            console.error(`Debug screenshot saved: ${debugScreenshot}`);

            // Get comprehensive page information
            const pageInfo = await page.evaluate(() => {
                return {
                    title: document.title,
                    url: window.location.href,
                    hasTable: !!document.querySelector('table'),
                    tableCount: document.querySelectorAll('table').length,
                    hasRows: document.querySelectorAll('tr').length,
                    bodyText: document.body ? document.body.innerText.substring(0, 1000) : 'No body',
                    allText: document.documentElement.innerText.substring(0, 2000)
                };
            });

            console.error('Page info:', pageInfo);

            // Enhanced vessel search with multiple strategies
            const vesselData = await page.evaluate((targetVessel, targetVoyage) => {
                const results = [];
                const cleanName = name => name.toUpperCase().replace(/[^A-Z0-9\s]/g, '').trim();
                const targetClean = cleanName(targetVessel);

                console.error(`Searching for vessel: "${targetVessel}" (cleaned: "${targetClean}")`);

                // Strategy 1: Search in tables
                const tables = document.querySelectorAll('table');
                console.error(`Found ${tables.length} tables on page`);

                for (let tableIndex = 0; tableIndex < tables.length; tableIndex++) {
                    const table = tables[tableIndex];
                    const rows = table.querySelectorAll('tbody tr, tr');
                    console.error(`Table ${tableIndex + 1}: ${rows.length} rows`);

                    for (let rowIndex = 0; rowIndex < rows.length; rowIndex++) {
                        const row = rows[rowIndex];
                        const cells = row.querySelectorAll('td, th');

                        if (cells.length >= 3) {
                            const rowData = {};
                            let rowText = '';

                            // Capture all cell data
                            cells.forEach((cell, index) => {
                                const text = cell?.innerText?.trim() || '';
                                rowData[`cell_${index}`] = text;
                                rowText += ' ' + text;
                            });

                            const rowTextClean = cleanName(rowText);

                            // Check if vessel name appears anywhere in the row
                            if (rowTextClean.includes(targetClean) ||
                                targetClean.includes(rowTextClean.substring(0, Math.min(targetClean.length, rowTextClean.length)))) {

                                console.error(`Found potential match in table ${tableIndex + 1}, row ${rowIndex + 1}`);
                                console.error('Row data:', rowData);

                                // Check voyage if specified
                                let voyageMatch = !targetVoyage;
                                if (targetVoyage) {
                                    voyageMatch = rowText.includes(targetVoyage);
                                }

                                if (voyageMatch) {
                                    results.push({
                                        vessel_name: targetVessel,
                                        voyage_match: voyageMatch,
                                        row_data: rowData,
                                        cells_count: cells.length,
                                        table_index: tableIndex,
                                        row_index: rowIndex,
                                        match_text: rowText.substring(0, 200)
                                    });
                                }
                            }
                        }
                    }
                }

                // Strategy 2: Search in all text content for vessel name
                if (results.length === 0) {
                    const bodyText = document.body?.innerText || '';
                    const bodyTextClean = cleanName(bodyText);

                    if (bodyTextClean.includes(targetClean)) {
                        console.error('Found vessel name in page text but not in structured table data');

                        // Try to extract context around the vessel name
                        const vesselIndex = bodyText.toUpperCase().indexOf(targetVessel.toUpperCase());
                        if (vesselIndex !== -1) {
                            const context = bodyText.substring(
                                Math.max(0, vesselIndex - 100),
                                Math.min(bodyText.length, vesselIndex + targetVessel.length + 100)
                            );

                            results.push({
                                vessel_name: targetVessel,
                                voyage_match: !targetVoyage || context.includes(targetVoyage),
                                row_data: { context: context },
                                cells_count: 1,
                                table_index: -1,
                                row_index: -1,
                                match_text: context,
                                search_strategy: 'text_search'
                            });
                        }
                    }
                }

                return {
                    results: results,
                    search_info: {
                        target_vessel: targetVessel,
                        target_clean: targetClean,
                        tables_found: tables.length,
                        total_rows: document.querySelectorAll('tr').length
                    }
                };
            }, vesselName, voyageCode);

            console.error(`Search completed. Found ${vesselData.results.length} potential matches`);
            console.error('Search info:', vesselData.search_info);

            if (!vesselData.results || vesselData.results.length === 0) {
                console.error('Vessel not found in any table or text content');

                return {
                    success: false,
                    vessel_name: vesselName,
                    voyage_code: voyageCode,
                    message: 'Vessel not found in schedule',
                    details: 'The terminal was accessible, but the specified vessel was not found in the current schedule.',
                    page_info: pageInfo,
                    search_info: vesselData.search_info,
                    screenshot: debugScreenshot
                };
            }

            // Use the first matching result
            const match = vesselData.results[0];
            console.error('Using vessel match:', match);

            // Enhanced date parsing
            const parseDate = (dateStr) => {
                if (!dateStr || dateStr === '-' || dateStr === '' || dateStr === 'N/A') return null;

                try {
                    // Handle various date formats
                    const datePatterns = [
                        /(\d{1,2}\/\d{1,2}\/\d{4})/g,
                        /(\d{4}-\d{2}-\d{2})/g,
                        /(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/g
                    ];

                    for (let pattern of datePatterns) {
                        const matches = dateStr.match(pattern);
                        if (matches && matches.length > 0) {
                            return matches[0];
                        }
                    }

                    // If it contains common month abbreviations
                    if (/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\b/i.test(dateStr)) {
                        return dateStr.trim();
                    }
                } catch (e) {
                    console.error('Date parse error:', e);
                }

                return dateStr;
            };

            // Extract relevant information from the row data
            const extractedData = {
                success: true,
                vessel_name: match.vessel_name,
                voyage_code: voyageCode || 'Unknown',
                eta: null,
                etd: null,
                berth: null,
                raw_data: {
                    row_data: match.row_data,
                    page_info: pageInfo,
                    all_matches: vesselData.results,
                    search_info: vesselData.search_info
                }
            };

            // Enhanced date and berth extraction
            const allText = Object.values(match.row_data).join(' ');

            // Look for dates
            const dateRegex = /\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}(?:\s+\d{1,2}:\d{2})?)\b/g;
            const dates = allText.match(dateRegex) || [];

            if (dates.length > 0) {
                extractedData.eta = parseDate(dates[0]);
                if (dates.length > 1) {
                    extractedData.etd = parseDate(dates[1]);
                }
            }

            // Look for berth information (B followed by numbers/letters)
            const berthMatch = allText.match(/\bB\d+[A-Z]?\b/i);
            if (berthMatch) {
                extractedData.berth = berthMatch[0];
            }

            // Look for voyage code in the data if not provided
            if (!voyageCode) {
                const voyageMatch = allText.match(/\b\d{4}[A-Z]\b/);
                if (voyageMatch) {
                    extractedData.voyage_code = voyageMatch[0];
                }
            }

            return extractedData;

        } catch (error) {
            console.error('LCIT scraper error:', error.message);

            // Take error screenshot
            if (context) {
                try {
                    const pages = await context.pages();
                    if (pages.length > 0) {
                        const errorScreenshot = `browser-automation/lcit-error-${Date.now()}.png`;
                        await pages[0].screenshot({ path: errorScreenshot });
                        console.error(`Error screenshot saved: ${errorScreenshot}`);
                    }
                } catch (screenshotError) {
                    console.error('Could not take error screenshot:', screenshotError);
                }
            }

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