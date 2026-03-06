const puppeteer = require('puppeteer');
(async () => {
  const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.goto('https://www.lcb1.com/BerthSchedule', { waitUntil: 'networkidle2', timeout: 30000 });
  await new Promise(r => setTimeout(r, 2000));

  // Extract all inline script content
  const scripts = await page.evaluate(() => {
    const allScripts = document.querySelectorAll('script');
    return Array.from(allScripts).map(s => s.textContent).filter(s => s.length > 10);
  });

  scripts.forEach((s, i) => {
    if (s.includes('vesselName') || s.includes('txtVesselName') || s.includes('Detail') || s.includes('ajax') || s.includes('change')) {
      console.log('--- Script ' + i + ' (relevant) ---');
      console.log(s.substring(0, 3000));
      console.log('---\n');
    }
  });

  // Check select element attributes
  const selectInfo = await page.evaluate(() => {
    const select = document.querySelector('#txtVesselName');
    if (!select) return 'NO SELECT';
    return JSON.stringify({
      onchange: select.getAttribute('onchange'),
      id: select.id,
      parentForm: select.closest('form') ? select.closest('form').action : 'no form',
      events: select.getAttribute('data-bind') || 'none'
    });
  });
  console.log('Select info:', selectInfo);

  // Check for jQuery event handlers
  const jqEvents = await page.evaluate(() => {
    if (typeof jQuery !== 'undefined') {
      const events = jQuery._data(jQuery('#txtVesselName')[0], 'events');
      if (events) return JSON.stringify(Object.keys(events));
    }
    return 'no jQuery events';
  });
  console.log('jQuery events:', jqEvents);

  await browser.close();
})();
