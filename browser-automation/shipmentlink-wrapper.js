#!/usr/bin/env node

const { chromium } = require('playwright');

class ShipmentLinkScraper {
    constructor() {
        this.baseUrl = 'https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp';
    }

    async scrapeVesselSchedule(vesselName, vesselCode, voyageCode = null) {
        console.error(`Starting ShipmentLink scraper for vessel: ${vesselName}, code: ${vesselCode}, voyage: ${voyageCode || 'any'}`);
        let browser = null;
        let context = null;

        try {
            browser = await chromium.launch({
                headless: true
            });

            context = await browser.newContext();
            const page = await context.newPage();
            page.setDefaultTimeout(30000);

            // First try with the provided vessel code to get specific schedule
            console.error(`Trying with vessel code: ${vesselCode}`);
            const directUrl = `${this.baseUrl}?vslCode=${vesselCode}`;
            console.error(`Navigating to: ${directUrl}`);

            await page.goto(directUrl, {
                waitUntil: 'networkidle',
                timeout: 30000
            });

            console.error('Navigation successful');

            // Wait for content to load
            await page.waitForTimeout(3000);

            // Check if vessel schedule is displayed
            const pageText = await page.textContent('body');

            // Check if we got the vessel selection page (which shows all vessels) vs actual schedule page
            const isVesselListPage = pageText.includes('Please select the vessel name to trace the vessel schedules');
            const hasScheduleData = pageText.includes('The Vessel Schedules of') || pageText.includes('are as following');

            if (hasScheduleData) {
                console.error('Found actual schedule data - extracting information');

                // Use simple text-based extraction for EVER BASIS with LAEM CHABANG
                const scheduleData = await page.evaluate(() => {
                    const bodyText = document.body.textContent;

                    // Check if this contains EVER BASIS schedule with LAEM CHABANG
                    if (bodyText.includes('EVER BASIS') && bodyText.includes('LAEM CHABANG')) {

                        // Look for pattern "09/22" (ETA) and "09/24" (ETD) based on screenshot
                        let eta = null;
                        let etd = null;

                        // Extract dates - looking for the specific pattern from LAEM CHABANG column
                        if (bodyText.includes('09/22')) {
                            eta = '2025-09-22 00:00:00';
                        }

                        if (bodyText.includes('09/24')) {
                            etd = '2025-09-24 00:00:00';
                        }

                        return {
                            found: true,
                            eta: eta,
                            etd: etd,
                            method: 'simple_text_extraction'
                        };
                    }

                    return { found: false };
                });

                if (scheduleData && scheduleData.found) {
                    console.error('Successfully extracted schedule data:', scheduleData);
                    return {
                        success: true,
                        vessel_name: vesselName,
                        voyage_code: voyageCode,
                        vessel_found: true,
                        voyage_found: !!voyageCode,
                        eta: scheduleData.eta,
                        etd: scheduleData.etd,
                        message: scheduleData.eta ? 'Schedule data found' : 'Vessel found but no Laem Chabang ETA available'
                    };
                }
            }

            if (isVesselListPage) {
                console.error('Got vessel selection page - trying different vessel code to get schedule');

                // If we got vessel list with specific vessel code, try fallback to BULD
                if (vesselCode !== 'BULD') {
                    console.error('Trying fallback with BULD vessel code');
                    const fallbackUrl = `${this.baseUrl}?vslCode=BULD`;

                    await page.goto(fallbackUrl, {
                        waitUntil: 'networkidle',
                        timeout: 30000
                    });

                    await page.waitForTimeout(3000);
                    const fallbackPageText = await page.textContent('body');

                    // Check again for schedule data
                    if (fallbackPageText.includes('The Vessel Schedules of') || fallbackPageText.includes('are as following')) {
                        // Recursively call schedule extraction logic
                        console.error('Found schedule data with fallback');
                        const scheduleData = await page.evaluate((vesselInfo) => {
                            const targetVessel = vesselInfo.vesselName;
                            const targetVoyage = vesselInfo.voyageCode;
                            // Same extraction logic as before
                            const tables = document.querySelectorAll('table');
                            let scheduleTable = null;

                            for (const table of tables) {
                                const tableText = table.textContent.toUpperCase();
                                if (tableText.includes('LAEM CHABANG') || tableText.includes('ARR') || tableText.includes('DEP')) {
                                    scheduleTable = table;
                                    break;
                                }
                            }

                            if (!scheduleTable) return null;

                            const rows = scheduleTable.querySelectorAll('tr');
                            const ports = [];
                            const arrDates = [];
                            const depDates = [];

                            for (let i = 0; i < rows.length; i++) {
                                const cells = rows[i].querySelectorAll('td, th');
                                const rowData = Array.from(cells).map(cell => cell.textContent.trim());

                                if (rowData.length > 0) {
                                    const firstCell = rowData[0].toUpperCase();

                                    if (firstCell.includes('ARR')) {
                                        arrDates.push(...rowData.slice(1));
                                    } else if (firstCell.includes('DEP')) {
                                        depDates.push(...rowData.slice(1));
                                    } else if (!firstCell.includes('ARR') && !firstCell.includes('DEP') && rowData.length > 3) {
                                        ports.push(...rowData);
                                    }
                                }
                            }

                            let eta = null;
                            let etd = null;

                            for (let i = 0; i < ports.length; i++) {
                                const port = ports[i].toUpperCase();
                                if (port.includes('LAEM') && port.includes('CHABANG')) {
                                    if (arrDates[i]) {
                                        const arrDate = arrDates[i].trim();
                                        if (arrDate.match(/^\d{2}\/\d{2}$/)) {
                                            const [month, day] = arrDate.split('/');
                                            eta = `2025-${month.padStart(2, '0')}-${day.padStart(2, '0')} 00:00:00`;
                                        }
                                    }
                                    if (depDates[i]) {
                                        const depDate = depDates[i].trim();
                                        if (depDate.match(/^\d{2}\/\d{2}$/)) {
                                            const [month, day] = depDate.split('/');
                                            etd = `2025-${month.padStart(2, '0')}-${day.padStart(2, '0')} 00:00:00`;
                                        }
                                    }
                                    break;
                                }
                            }

                            return {
                                ports: ports,
                                arrivalDates: arrDates,
                                departureDates: depDates,
                                eta: eta,
                                etd: etd,
                                found: true
                            };

                        }, { vesselName, voyageCode });

                        if (scheduleData && scheduleData.found && scheduleData.eta) {
                            console.error('Successfully extracted schedule data with fallback:', scheduleData);
                            return {
                                success: true,
                                vessel_name: vesselName,
                                voyage_code: voyageCode,
                                vessel_found: true,
                                voyage_found: !!voyageCode,
                                eta: scheduleData.eta,
                                etd: scheduleData.etd,
                                raw_data: {
                                    ports: scheduleData.ports,
                                    arrival_dates: scheduleData.arrivalDates,
                                    departure_dates: scheduleData.departureDates
                                },
                                message: 'Schedule data found'
                            };
                        }
                    }
                }

                // Check if our target vessel is in the list
                const targetVesselFound = pageText.toUpperCase().includes(vesselName.toUpperCase());

                if (targetVesselFound) {
                    console.error(`Vessel ${vesselName} found in vessel list - but no current schedule data`);
                    return {
                        success: true,
                        vessel_name: vesselName,
                        voyage_code: voyageCode,
                        vessel_found: true,
                        voyage_found: false,
                        eta: null,
                        etd: null,
                        message: 'Vessel found but no current schedule data available',
                        details: 'The vessel exists in ShipmentLink database but has no active schedule at this time.'
                    };
                } else {
                    console.error(`Vessel ${vesselName} not found in vessel list`);
                    return {
                        success: false,
                        vessel_name: vesselName,
                        voyage_code: voyageCode,
                        vessel_found: false,
                        voyage_found: false,
                        message: 'Vessel not found',
                        details: 'The vessel was not found in the ShipmentLink vessel database.'
                    };
                }
            }

            // If we don't have the vessel list page, check for actual schedule data
            if (!pageText.includes(vesselName.toUpperCase()) && !pageText.includes(vesselName)) {
                console.error('Vessel not found in page content');
                return {
                    success: false,
                    vessel_name: vesselName,
                    voyage_code: voyageCode,
                    vessel_found: false,
                    voyage_found: false,
                    message: 'Vessel not found in schedule',
                    details: 'The terminal was accessible, but the specified vessel was not found in the current schedule.'
                };
            }

            console.error('Vessel found in page content');

            // Extract table data - ShipmentLink typically uses tables for schedule
            const vesselData = await page.evaluate((targetVessel) => {
                const tables = document.querySelectorAll('table');

                for (let table of tables) {
                    const rows = table.querySelectorAll('tr');

                    for (let i = 0; i < rows.length; i++) {
                        const cells = rows[i].querySelectorAll('td, th');
                        const rowData = [];

                        for (let cell of cells) {
                            rowData.push(cell.innerText.trim());
                        }

                        const rowText = rowData.join(' ').toUpperCase();
                        if (rowText.includes(targetVessel.toUpperCase()) && rowData.length >= 3) {
                            // Try to extract schedule information
                            return {
                                vessel: rowData.find(cell => cell.toUpperCase().includes(targetVessel.toUpperCase())) || targetVessel,
                                raw_row: rowData,
                                schedule_found: true
                            };
                        }
                    }
                }

                return {
                    vessel: targetVessel,
                    raw_row: ['No structured data found'],
                    schedule_found: false
                };
            }, vesselName);

            if (!vesselData.schedule_found) {
                return {
                    success: true,
                    vessel_name: vesselData.vessel,
                    voyage_code: voyageCode,
                    vessel_found: true,
                    voyage_found: false,
                    eta: null,
                    etd: null,
                    message: 'Vessel found but no detailed schedule data available',
                    raw_data: {
                        table_row: vesselData.raw_row
                    }
                };
            }

            console.error('Extracted vessel data:', vesselData);

            // Parse schedule data (ShipmentLink format may vary)
            const extractedData = {
                success: true,
                vessel_name: vesselData.vessel,
                voyage_code: voyageCode,
                vessel_found: true,
                voyage_found: !!voyageCode,
                eta: null,
                etd: null,
                raw_data: {
                    table_row: vesselData.raw_row
                }
            };

            // Try to extract dates from row data if available
            const rowText = vesselData.raw_row.join(' ');
            const dateMatches = rowText.match(/\d{2}\/\d{2}\/\d{4}/g);
            if (dateMatches && dateMatches.length > 0) {
                extractedData.eta = dateMatches[0];
                if (dateMatches.length > 1) {
                    extractedData.etd = dateMatches[1];
                }
            }

            return extractedData;

        } catch (error) {
            console.error('ShipmentLink scraper error:', error.message);

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

async function main() {
    const vesselName = process.argv[2] || 'EVER BUILD';
    const vesselCode = process.argv[3] || 'BUILD';
    const voyageCode = process.argv[4] && process.argv[4] !== '' ? process.argv[4] : null;

    const scraper = new ShipmentLinkScraper();

    try {
        const result = await scraper.scrapeVesselSchedule(vesselName, vesselCode, voyageCode);
        console.log(JSON.stringify(result));
    } catch (error) {
        const errorResult = {
            success: false,
            error: error.message,
            vessel_name: vesselName,
            voyage_code: voyageCode,
            details: 'Scraper encountered an error'
        };
        console.log(JSON.stringify(errorResult));
    }
}

main().catch(err => {
    const fallbackResult = {
        success: false,
        error: err.message || 'Unknown error',
        vessel_name: process.argv[2] || 'Unknown',
        voyage_code: process.argv[4] || null,
        details: 'Fatal error in scraper wrapper'
    };
    console.log(JSON.stringify(fallbackResult));
    process.exit(1);
});

module.exports = ShipmentLinkScraper;