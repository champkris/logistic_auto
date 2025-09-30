const puppeteer = require('puppeteer');

async function debugJWDTable() {
    let browser = null;

    try {
        console.log('Starting JWD table debug...');

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

        console.log('Navigating to JWD shipping schedule page...');
        await page.goto('https://www.dg-net.org/th/service-shipping', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        console.log('Analyzing table structure...');

        const tableInfo = await page.evaluate(() => {
            const tables = document.querySelectorAll('table');
            const info = {
                total_tables: tables.length,
                tables_data: []
            };

            for (let i = 0; i < tables.length; i++) {
                const table = tables[i];
                const rows = table.querySelectorAll('tr');

                const tableData = {
                    table_index: i,
                    total_rows: rows.length,
                    sample_rows: []
                };

                // Get first 5 rows for analysis
                for (let j = 0; j < Math.min(5, rows.length); j++) {
                    const row = rows[j];
                    const cells = row.querySelectorAll('td, th');
                    const cellTexts = Array.from(cells).map(cell => cell.textContent.trim());

                    tableData.sample_rows.push({
                        row_index: j,
                        cell_count: cells.length,
                        cells: cellTexts
                    });
                }

                info.tables_data.push(tableData);
            }

            return info;
        });

        console.log('Table structure analysis:');
        console.log(JSON.stringify(tableInfo, null, 2));

        return tableInfo;

    } catch (error) {
        console.error('Debug error:', error);
        return { error: error.message };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Main execution
(async () => {
    const result = await debugJWDTable();
    console.log('\n=== FINAL RESULT ===');
    console.log(JSON.stringify(result));
})();