const { chromium } = require('playwright');

async function testLCIT() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        const url = 'https://www.lcit.com/vessel?vsl=SKY%20SUNSHINE&voy=2513S';
        console.log('Navigating to:', url);

        await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });

        console.log('Page loaded successfully');
        console.log('Title:', await page.title());

        // Check if vessel data is in the page
        const pageText = await page.textContent('body');
        if (pageText.includes('SKY SUNSHINE')) {
            console.log('✅ Vessel found on page');

            // Try to extract the table data
            const tableData = await page.evaluate(() => {
                const table = document.querySelector('table');
                if (!table) return 'No table found';

                const rows = table.querySelectorAll('tr');
                const results = [];

                for (let row of rows) {
                    const cells = row.querySelectorAll('td, th');
                    const rowData = [];
                    for (let cell of cells) {
                        rowData.push(cell.innerText.trim());
                    }
                    if (rowData.length > 0) results.push(rowData);
                }

                return results;
            });

            console.log('Table data:', JSON.stringify(tableData, null, 2));
        } else {
            console.log('❌ Vessel not found on page');
        }

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
}

testLCIT();