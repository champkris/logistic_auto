const puppeteer = require('puppeteer');

async function findJWDSchedule() {
    let browser = null;

    try {
        console.log('Starting search for actual JWD schedule data...');

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

        // Try multiple potential URLs
        const urlsToTry = [
            'https://www.dg-net.org/th/service-shipping',
            'https://www.dg-net.org/en/service-shipping',
            'https://www.dg-net.org/th/service-shipping/schedule',
            'https://www.dg-net.org/shipping-schedule',
            'https://www.dg-net.org/schedule'
        ];

        for (const url of urlsToTry) {
            try {
                console.log(`Trying URL: ${url}`);

                await page.goto(url, {
                    waitUntil: 'networkidle2',
                    timeout: 20000
                });

                await new Promise(resolve => setTimeout(resolve, 3000));

                // Check for schedule data
                const hasScheduleData = await page.evaluate(() => {
                    const tables = document.querySelectorAll('table');
                    const hasJosco = document.body.textContent.toLowerCase().includes('josco');
                    const hasVesselName = document.body.textContent.toLowerCase().includes('vessel name');

                    return {
                        url: window.location.href,
                        table_count: tables.length,
                        has_josco: hasJosco,
                        has_vessel_name: hasVesselName,
                        page_title: document.title,
                        body_snippet: document.body.textContent.substring(0, 500)
                    };
                });

                console.log(`Result for ${url}:`, hasScheduleData);

                if (hasScheduleData.table_count > 0 || hasScheduleData.has_josco) {
                    console.log(`Found potential schedule data at: ${url}`);

                    // Get detailed table structure
                    const tableDetails = await page.evaluate(() => {
                        const tables = document.querySelectorAll('table');
                        const details = [];

                        for (let i = 0; i < tables.length; i++) {
                            const table = tables[i];
                            const rows = table.querySelectorAll('tr');
                            const tableInfo = {
                                table_index: i,
                                row_count: rows.length,
                                first_rows: []
                            };

                            for (let j = 0; j < Math.min(3, rows.length); j++) {
                                const cells = rows[j].querySelectorAll('td, th');
                                const cellTexts = Array.from(cells).map(cell => cell.textContent.trim());
                                tableInfo.first_rows.push(cellTexts);
                            }

                            details.push(tableInfo);
                        }

                        return details;
                    });

                    return {
                        found_url: url,
                        schedule_data: hasScheduleData,
                        table_details: tableDetails
                    };
                }

            } catch (error) {
                console.log(`Error accessing ${url}: ${error.message}`);
                continue;
            }
        }

        // Try clicking on Shipping Schedule link if it exists
        console.log('Trying to find and click Shipping Schedule link...');

        await page.goto('https://www.dg-net.org/th/service-shipping', {
            waitUntil: 'networkidle2',
            timeout: 20000
        });

        const clickResult = await page.evaluate(() => {
            // Look for shipping schedule links
            const links = document.querySelectorAll('a');
            for (const link of links) {
                const text = link.textContent.toLowerCase();
                if (text.includes('shipping schedule') || text.includes('schedule')) {
                    return {
                        found_link: true,
                        link_text: link.textContent,
                        link_href: link.href
                    };
                }
            }
            return { found_link: false };
        });

        return {
            search_complete: true,
            click_result: clickResult,
            message: 'No direct schedule data found'
        };

    } catch (error) {
        console.error('Search error:', error);
        return { error: error.message };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Main execution
(async () => {
    const result = await findJWDSchedule();
    console.log('\n=== SEARCH RESULT ===');
    console.log(JSON.stringify(result, null, 2));
})();