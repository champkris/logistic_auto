const puppeteer = require('puppeteer');

async function testVesselScheduleAvailability() {
  console.log('🚀 Testing vessel schedule availability...');
  
  const browser = await puppeteer.launch({
    headless: true,
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
      console.log('✅ Accepted cookies');
    } catch (error) {
      console.log('ℹ️ No cookie popup');
    }
    
    // Test multiple vessels to see if any have schedule data
    const testVessels = ['EVER BUILD', 'EVER GIVEN', 'EVER ACE', 'CMA CGM JACQUES SAADE'];
    
    for (const vesselName of testVessels) {
      console.log(`\n🔍 Testing vessel: ${vesselName}`);
      
      // Select vessel
      await page.waitForSelector('select', { timeout: 10000 });
      const options = await page.$$eval('select option', options => 
        options.map(option => ({
          value: option.value,
          text: option.textContent.trim()
        }))
      );
      
      const targetOption = options.find(option => 
        option.text.toUpperCase().includes(vesselName.toUpperCase())
      );
      
      if (targetOption) {
        console.log(`✅ Found vessel: ${targetOption.text}`);
        await page.select('select', targetOption.value);
        
        // Click search
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
          return false;
        });
        
        // Wait and check results
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        const hasScheduleData = await page.evaluate(() => {
          // Look for actual schedule data (dates, ETA, etc.)
          const pageText = document.body.textContent.toUpperCase();
          
          // Check for schedule-related terms
          const scheduleIndicators = [
            'ETA', 'ETD', 'ARRIVAL', 'DEPARTURE', 
            'BERTH', 'TERMINAL', 'PORT',
            'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER',
            '2025', '2024'
          ];
          
          const hasScheduleTerms = scheduleIndicators.some(term => 
            pageText.includes(term)
          );
          
          // Check if we're still on the selection page
          const isStillOnSelectionPage = pageText.includes('PLEASE SELECT THE VESSEL NAME');
          
          // Look for date patterns
          const hasDatePattern = /\d{1,2}\/\d{1,2}\/\d{4}/.test(pageText);
          
          return {
            hasScheduleTerms,
            isStillOnSelectionPage,
            hasDatePattern,
            hasScheduleData: hasScheduleTerms && hasDatePattern && !isStillOnSelectionPage
          };
        });
        
        console.log(`  Schedule terms found: ${hasScheduleData.hasScheduleTerms}`);
        console.log(`  Still on selection page: ${hasScheduleData.isStillOnSelectionPage}`);
        console.log(`  Date patterns found: ${hasScheduleData.hasDatePattern}`);
        console.log(`  ✅ Has actual schedule data: ${hasScheduleData.hasScheduleData}`);
        
        if (hasScheduleData.hasScheduleData) {
          console.log(`🎉 SUCCESS: ${vesselName} has schedule data available!`);
          break;
        } else {
          console.log(`⚠️ No schedule data for ${vesselName}`);
        }
        
      } else {
        console.log(`❌ ${vesselName} not found in dropdown`);
      }
    }
    
  } catch (error) {
    console.error('❌ Error:', error.message);
  }
  
  await browser.close();
}

testVesselScheduleAvailability().catch(console.error);