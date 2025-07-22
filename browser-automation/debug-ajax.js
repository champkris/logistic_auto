const puppeteer = require('puppeteer');

async function debugAjaxContent() {
  console.log('🔍 Enhanced Debug: AJAX content analysis...');
  
  const browser = await puppeteer.launch({
    headless: false, // Show browser
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  // Monitor network requests
  page.on('response', response => {
    if (response.url().includes('BerthSchedule') || response.url().includes('ajax') || response.url().includes('api')) {
      console.log(`📡 Network: ${response.status()} ${response.url()}`);
    }
  });
  
  try {
    await page.goto('https://www.lcb1.com/BerthSchedule', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    console.log('📄 Initial page loaded');
    
    // Take screenshot before search
    await page.screenshot({ 
      path: '/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/debug-before-search.png'
    });
    
    // Select vessel and search
    await page.select('select', 'MARSA PRIDE');
    console.log('✅ Selected MARSA PRIDE');
    
    // Click search and wait for network activity
    console.log('🔍 Clicking search...');
    await page.click('button, input[type="submit"], input[value*="Search"]');
    
    // Wait for potential AJAX responses with enhanced monitoring
    console.log('⏳ Waiting for dynamic content...');
    
    let attempts = 0;
    const maxAttempts = 15; // 15 seconds
    let contentFound = false;
    
    while (attempts < maxAttempts && !contentFound) {
      await new Promise(resolve => setTimeout(resolve, 1000));
      attempts++;
      
      // Check for dynamic content every second
      const status = await page.evaluate(() => {
        const text = document.body.textContent;
        return {
          hasMarsa: text.includes('MARSA PRIDE'),
          hasScheduleData: text.includes('528') && text.includes('A0'),
          hasVoyage: text.includes('528S') || text.includes('528N'),
          hasBerthing: text.includes('Berthing Time'),
          hasDeparture: text.includes('Departure Time'),
          totalElements: document.querySelectorAll('*').length,
          tableCount: document.querySelectorAll('table').length,
          divCount: document.querySelectorAll('div').length
        };
      });
      
      console.log(`📊 Attempt ${attempts}: Tables=${status.tableCount}, Divs=${status.divCount}, HasScheduleData=${status.hasScheduleData}`);
      
      if (status.hasScheduleData) {
        contentFound = true;
        console.log('🎯 Schedule data detected!');
        break;
      }
    }
    
    // Take screenshot after waiting
    await page.screenshot({ 
      path: '/Users/apichakriskalambasuta/Sites/localhost/logistic_auto/browser-automation/debug-after-search.png',
      fullPage: true 
    });
    
    // Comprehensive content analysis
    const contentAnalysis = await page.evaluate(() => {
      const analysis = {
        // Look for all possible data containers
        allTables: [],
        allDivs: [],
        allSpans: [],
        marsaMatches: [],
        potentialScheduleElements: []
      };
      
      // Analyze all tables
      document.querySelectorAll('table').forEach((table, i) => {
        const rows = Array.from(table.querySelectorAll('tr')).map(row => 
          Array.from(row.querySelectorAll('td, th')).map(cell => cell.textContent.trim())
        );
        analysis.allTables.push({ index: i, rows });
      });
      
      // Look for MARSA PRIDE in any element
      const allElements = document.querySelectorAll('*');
      allElements.forEach(el => {
        const text = el.textContent;
        if (text.includes('MARSA PRIDE')) {
          analysis.marsaMatches.push({
            tagName: el.tagName,
            className: el.className,
            id: el.id,
            textContent: text.substring(0, 200),
            innerHTML: el.innerHTML.substring(0, 200)
          });
        }
        
        // Look for schedule-like data (dates, voyages, terminals)
        if (text.includes('528') || text.includes('A0') || text.includes('/2025')) {
          analysis.potentialScheduleElements.push({
            tagName: el.tagName,
            className: el.className,
            textContent: text.substring(0, 100)
          });
        }
      });
      
      // Look specifically for structured data patterns
      const pageHTML = document.body.innerHTML;
      
      return {
        ...analysis,
        htmlContainsMarsaPride: pageHTML.includes('MARSA PRIDE'),
        htmlContainsVoyage: pageHTML.includes('528S'),
        htmlContainsTerminal: pageHTML.includes('A0'),
        pageTextSample: document.body.textContent.substring(0, 1000)
      };
    });
    
    console.log('\n📈 Content Analysis Results:');
    console.log(`Tables found: ${contentAnalysis.allTables.length}`);
    console.log(`MARSA PRIDE matches: ${contentAnalysis.marsaMatches.length}`);
    console.log(`Potential schedule elements: ${contentAnalysis.potentialScheduleElements.length}`);
    
    // Display table contents
    contentAnalysis.allTables.forEach((table, i) => {
      console.log(`\n📋 Table ${i}:`);
      table.rows.forEach((row, j) => {
        if (row.some(cell => cell.length > 0)) {
          console.log(`  Row ${j}:`, row);
        }
      });
    });
    
    // Display MARSA PRIDE matches
    if (contentAnalysis.marsaMatches.length > 0) {
      console.log('\n🚢 MARSA PRIDE matches:');
      contentAnalysis.marsaMatches.forEach((match, i) => {
        console.log(`\nMatch ${i + 1}:`);
        console.log(`  Element: ${match.tagName}.${match.className}#${match.id}`);
        console.log(`  Text: ${match.textContent}`);
        console.log(`  HTML: ${match.innerHTML}`);
      });
    }
    
    // Display potential schedule data
    if (contentAnalysis.potentialScheduleElements.length > 0) {
      console.log('\n📅 Potential schedule data:');
      contentAnalysis.potentialScheduleElements.forEach((el, i) => {
        console.log(`${i + 1}. ${el.tagName}.${el.className}: ${el.textContent}`);
      });
    }
    
    console.log('\n📄 Page text sample:');
    console.log(contentAnalysis.pageTextSample);
    
  } catch (error) {
    console.error('❌ Debug error:', error.message);
  } finally {
    // Keep browser open for manual inspection
    console.log('🔍 Browser staying open for manual inspection...');
    console.log('Press Ctrl+C to close when ready');
    
    // Wait for manual termination
    process.on('SIGINT', async () => {
      await browser.close();
      process.exit(0);
    });
  }
}

debugAjaxContent().catch(console.error);