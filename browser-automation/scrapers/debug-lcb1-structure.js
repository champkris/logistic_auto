const puppeteer = require('puppeteer');

/**
 * Debug script to inspect LCB1 table structure
 */
async function main() {
  const browser = await puppeteer.launch({
    headless: false,
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

  console.log('🔍 Loading LCB1 page...');
  await page.goto('https://www.lcb1.com/BerthSchedule', {
    waitUntil: 'networkidle2',
    timeout: 30000
  });

  await new Promise(resolve => setTimeout(resolve, 3000));

  // Extract table structure
  const tableInfo = await page.evaluate(() => {
    const tables = document.querySelectorAll('table');
    console.log('Found', tables.length, 'tables');

    const results = [];

    tables.forEach((table, tableIndex) => {
      // Get header row
      const headerRow = table.querySelector('thead tr, tr:first-child');
      const headers = [];

      if (headerRow) {
        headerRow.querySelectorAll('th, td').forEach(cell => {
          headers.push(cell.innerText.trim());
        });
      }

      // Get first data row
      const dataRows = table.querySelectorAll('tbody tr, tr');
      const firstDataRow = Array.from(dataRows).find(row => {
        const cells = row.querySelectorAll('td');
        return cells.length > 0;
      });

      const firstRowData = [];
      if (firstDataRow) {
        firstDataRow.querySelectorAll('td').forEach(cell => {
          firstRowData.push(cell.innerText.trim());
        });
      }

      results.push({
        tableIndex,
        headers,
        firstRowData,
        rowCount: dataRows.length
      });
    });

    return results;
  });

  console.log('\n=== LCB1 TABLE STRUCTURE ===\n');
  tableInfo.forEach((table, idx) => {
    console.log(`\nTable ${idx}:`);
    console.log('Rows:', table.rowCount);
    console.log('\nHeaders:');
    table.headers.forEach((header, i) => {
      console.log(`  [${i}] ${header}`);
    });
    console.log('\nFirst Data Row:');
    table.firstRowData.forEach((data, i) => {
      console.log(`  [${i}] ${data}`);
    });
  });

  console.log('\n\nPress Ctrl+C to exit...');
  // Keep browser open for inspection
  await new Promise(() => {});
}

main().catch(console.error);
