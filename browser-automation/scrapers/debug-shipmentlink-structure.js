const puppeteer = require('puppeteer');

/**
 * Debug script to inspect Shipmentlink table structure
 */
async function main() {
  const browser = await puppeteer.launch({
    headless: false,
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

  console.log('🔍 Loading Shipmentlink page...');

  try {
    await page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
      waitUntil: 'domcontentloaded',
      timeout: 30000
    });
  } catch (error) {
    console.error('Navigation error:', error.message);
  }

  await new Promise(resolve => setTimeout(resolve, 3000));

  // Check the current URL after navigation
  const currentUrl = page.url();
  console.log('Current URL:', currentUrl);

  // Check page content
  const pageInfo = await page.evaluate(() => {
    return {
      title: document.title,
      bodyText: document.body?.innerText?.substring(0, 500) || '',
      tableCount: document.querySelectorAll('table').length,
      hasScheduleClass: document.querySelectorAll('.schedule-table, .vessel-schedule').length,
      allClasses: Array.from(document.querySelectorAll('[class]'))
        .slice(0, 10)
        .map(el => el.className)
    };
  });

  console.log('\n=== PAGE INFO ===');
  console.log('Title:', pageInfo.title);
  console.log('Table count:', pageInfo.tableCount);
  console.log('Body text preview:', pageInfo.bodyText);
  console.log('Classes found:', pageInfo.allClasses);

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

  console.log('\n=== SHIPMENTLINK TABLE STRUCTURE ===\n');
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
