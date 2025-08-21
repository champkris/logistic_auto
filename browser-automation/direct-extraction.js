const puppeteer = require('puppeteer');

async function directShipmentLinkExtraction() {
  console.log('🚀 Direct ShipmentLink extraction for voyage 0815-079S...');
  
  const browser = await puppeteer.launch({
    headless: true, // Keep headless for now
    defaultViewport: { width: 1920, height: 1080 },
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  
  try {
    await page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    // Handle cookies
    await new Promise(resolve => setTimeout(resolve, 2000));
    try {
      await page.click('button[id*="accept"]');
    } catch (error) {
      // Continue if no cookie popup
    }
    
    // Select EVER BUILD and search
    await page.waitForSelector('select', { timeout: 10000 });
    await page.select('select', 'BULD');
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    await page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="button"], input[type="submit"]');
      for (const button of buttons) {
        const text = button.textContent || button.value || '';
        if (text.toLowerCase().includes('search') || text.toLowerCase().includes('submit')) {
          button.click();
          return true;
        }
      }
    });
    
    await new Promise(resolve => setTimeout(resolve, 4000));
    
    // Direct HTML parsing approach
    const htmlContent = await page.content();
    
    console.log('🔍 Parsing HTML directly for voyage 0815-079S...');
    
    // Parse the HTML content
    const targetVoyage = '0815-079S';
    const results = [];
    
    // Look for the specific voyage in the HTML
    const voyageIndex = htmlContent.indexOf(targetVoyage);
    if (voyageIndex !== -1) {
      console.log(`✅ Found voyage ${targetVoyage} at position ${voyageIndex}`);
      
      // Extract a section around the voyage code
      const sectionStart = Math.max(0, voyageIndex - 1000);
      const sectionEnd = Math.min(htmlContent.length, voyageIndex + 2000);
      const voyageSection = htmlContent.substring(sectionStart, sectionEnd);
      
      console.log('Voyage section length:', voyageSection.length);
      
      // Look for date patterns in this section (MM/DD format from screenshot)
      const datePattern = /(\d{2}\/\d{2})/g;
      const dates = [];
      let match;
      
      while ((match = datePattern.exec(voyageSection)) !== null) {
        dates.push(match[1]);
      }
      
      console.log('Found dates in voyage section:', dates);
      
      // Look for port names (based on screenshot: MANILA, LAEM CHABANG, HONG KONG, etc.)
      const portPattern = /(MANILA|LAEM\s+CHABANG|HONG\s+KONG|KAOHSIUNG|DALIAN|XINGANG|QINGDAO)/gi;
      const ports = [];
      let portMatch;
      
      while ((portMatch = portPattern.exec(voyageSection)) !== null) {
        if (!ports.includes(portMatch[1].toUpperCase())) {
          ports.push(portMatch[1].toUpperCase());
        }
      }
      
      console.log('Found ports in voyage section:', ports);
      
      // Try to extract ARR/DEP patterns
      const arrPattern = /ARR[^A-Z]*?(\d{2}\/\d{2})[^A-Z]*?(\d{2}\/\d{2})[^A-Z]*?(\d{2}\/\d{2})[^A-Z]*?(\d{2}\/\d{2})[^A-Z]*?(\d{2}\/\d{2})/i;
      const depPattern = /DEP[^A-Z]*?(\d{2}\/\d{2})[^A-Z]*?(\d{2}\/\d{2})[^A-Z]*?(\d{2}\/\d{2})[^A-Z]*?(\d{2}\/\d{2})[^A-Z]*?(\d{2}\/\d{2})/i;
      
      const arrMatch = voyageSection.match(arrPattern);
      const depMatch = voyageSection.match(depPattern);
      
      if (arrMatch) {
        console.log('Found ARR dates:', arrMatch.slice(1));
      }
      if (depMatch) {
        console.log('Found DEP dates:', depMatch.slice(1));
      }
      
      const extractedData = {
        voyage: targetVoyage,
        vessel: 'EVER BUILD',
        found: true,
        ports: ports,
        all_dates: dates,
        arrival_dates: arrMatch ? arrMatch.slice(1) : [],
        departure_dates: depMatch ? depMatch.slice(1) : []
      };
      
      // Try to match Laem Chabang specifically
      if (extractedData.ports.includes('LAEM CHABANG') && extractedData.arrival_dates.length > 0) {
        const laemChabangIndex = extractedData.ports.indexOf('LAEM CHABANG');
        if (laemChabangIndex < extractedData.arrival_dates.length) {
          extractedData.laem_chabang_eta = extractedData.arrival_dates[laemChabangIndex];
          console.log(`🎯 Laem Chabang ETA: ${extractedData.laem_chabang_eta}`);
        }
      }
      
      results.push(extractedData);
      
    } else {
      console.log(`❌ Voyage ${targetVoyage} not found in HTML`);
      
      // Check if EVER BUILD appears at all
      if (htmlContent.includes('EVER BUILD')) {
        console.log('✅ EVER BUILD found in HTML, but not the specific voyage');
        
        // List all voyage codes found
        const allVoyagePattern = /EVER BUILD (\d{4}-\d{3}[SN])/g;
        const foundVoyages = [];
        let voyageMatch;
        
        while ((voyageMatch = allVoyagePattern.exec(htmlContent)) !== null) {
          foundVoyages.push(voyageMatch[1]);
        }
        
        console.log('Found EVER BUILD voyages:', foundVoyages);
        
        results.push({
          voyage: targetVoyage,
          vessel: 'EVER BUILD',
          found: false,
          available_voyages: foundVoyages,
          message: `Target voyage ${targetVoyage} not available. Available voyages: ${foundVoyages.join(', ')}`
        });
      }
    }
    
    console.log('\n🔍 Final Results:');
    console.log(JSON.stringify(results, null, 2));
    
  } catch (error) {
    console.error('❌ Error:', error.message);
  }
  
  await browser.close();
}

directShipmentLinkExtraction().catch(console.error);