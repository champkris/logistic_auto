const { ShipmentLinkVesselScraper } = require('./scrapers/shipmentlink-scraper');

async function fixShipmentLinkSelection() {
  console.log('🔧 Fixing ShipmentLink vessel selection...\n');
  
  const scraper = new ShipmentLinkVesselScraper();
  
  try {
    await scraper.initialize();
    
    // Navigate to the page
    await scraper.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
      waitUntil: 'networkidle0',
      timeout: 30000
    });
    
    // Handle cookies
    try {
      await scraper.page.waitForSelector('button[id*="accept"]', { timeout: 5000 });
      await scraper.page.click('button[id*="accept"]');
      console.log('✅ Accepted cookies');
      await new Promise(resolve => setTimeout(resolve, 2000));
    } catch (e) {
      console.log('ℹ️ No cookie popup found');
    }
    
    // Wait for dropdown
    await scraper.page.waitForSelector('select', { timeout: 10000 });
    
    // Debug: Check what's in the dropdown
    const dropdownOptions = await scraper.page.evaluate(() => {
      const select = document.querySelector('select');
      if (!select) return { error: 'No select found' };
      
      const options = Array.from(select.options).map(option => ({
        value: option.value,
        text: option.textContent.trim()
      }));
      
      return {
        totalOptions: options.length,
        currentValue: select.value,
        everBuildOptions: options.filter(opt => opt.text.includes('EVER BUILD')),
        firstFewOptions: options.slice(0, 10)
      };
    });
    
    console.log('📋 Dropdown Analysis:');
    console.log('- Total options:', dropdownOptions.totalOptions);
    console.log('- Current value:', dropdownOptions.currentValue);
    console.log('- EVER BUILD options found:', dropdownOptions.everBuildOptions?.length || 0);
    console.log('- First few options:', dropdownOptions.firstFewOptions);
    
    if (dropdownOptions.everBuildOptions && dropdownOptions.everBuildOptions.length > 0) {
      console.log('\n🚢 Available EVER BUILD options:');
      dropdownOptions.everBuildOptions.forEach((option, index) => {
        console.log(`  ${index + 1}. Value: "${option.value}" Text: "${option.text}"`);
      });
      
      // Try to select EVER BUILD
      const selectionResult = await scraper.page.evaluate(() => {
        const select = document.querySelector('select');
        const options = Array.from(select.options);
        
        // Look for exact "EVER BUILD" match
        const everBuildOption = options.find(option => 
          option.textContent.trim() === 'EVER BUILD'
        );
        
        if (everBuildOption) {
          select.value = everBuildOption.value;
          // Trigger change event
          select.dispatchEvent(new Event('change', { bubbles: true }));
          return {
            success: true,
            selectedValue: everBuildOption.value,
            selectedText: everBuildOption.textContent.trim()
          };
        } else {
          return {
            success: false,
            reason: 'EVER BUILD option not found'
          };
        }
      });
      
      console.log('\n🎯 Selection Result:', selectionResult);
      
      if (selectionResult.success) {
        console.log('✅ Successfully selected EVER BUILD');
        
        // Wait a bit for any dynamic loading
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Try to submit/search
        const submitResult = await scraper.page.evaluate(() => {
          // Look for submit button
          const submitBtn = document.querySelector('input[value="Submit"]');
          if (submitBtn) {
            submitBtn.click();
            return { success: true, method: 'Submit button' };
          }
          
          // Try other button types
          const buttons = Array.from(document.querySelectorAll('input[type="button"], input[type="submit"], button'));
          for (const button of buttons) {
            const text = button.value || button.textContent || '';
            if (text.toLowerCase().includes('search') || text.toLowerCase().includes('submit')) {
              button.click();
              return { success: true, method: 'Found by text: ' + text };
            }
          }
          
          return { success: false, reason: 'No submit button found' };
        });
        
        console.log('🔍 Submit Result:', submitResult);
        
        if (submitResult.success) {
          // Wait for results
          console.log('⏳ Waiting for results...');
          await new Promise(resolve => setTimeout(resolve, 5000));
          
          // Take screenshot of results
          await scraper.page.screenshot({ path: 'debug-fixed-results.png', fullPage: true });
          console.log('📸 Results screenshot: debug-fixed-results.png');
          
          // Check what we got
          const resultsAnalysis = await scraper.page.evaluate(() => {
            return {
              hasEverBuild: document.body.textContent.includes('EVER BUILD'),
              hasVoyageData: document.body.textContent.includes('0815-079S'),
              hasLaemChabang: document.body.textContent.includes('LAEM CHABANG'),
              hasScheduleData: document.body.textContent.includes('ARR') || document.body.textContent.includes('DEP'),
              tableCount: document.querySelectorAll('table').length,
              currentURL: window.location.href
            };
          });
          
          console.log('\n📊 Results Analysis:');
          console.log('- Has EVER BUILD:', resultsAnalysis.hasEverBuild);
          console.log('- Has voyage 0815-079S:', resultsAnalysis.hasVoyageData);  
          console.log('- Has LAEM CHABANG:', resultsAnalysis.hasLaemChabang);
          console.log('- Has schedule data (ARR/DEP):', resultsAnalysis.hasScheduleData);
          console.log('- Table count:', resultsAnalysis.tableCount);
          console.log('- Current URL:', resultsAnalysis.currentURL);
        }
      }
    } else {
      console.log('❌ No EVER BUILD options found in dropdown');
    }
    
    await scraper.cleanup();
    
  } catch (error) {
    console.error('❌ Fix attempt failed:', error);
    await scraper.cleanup();
  }
}

fixShipmentLinkSelection();
