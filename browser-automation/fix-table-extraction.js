const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');

(async () => {
  console.log('🎯 FIXING TABLE EXTRACTION - Based on your screenshot');
  
  const scraper = new LCB1VesselScraper();
  await scraper.initialize();
  
  try {
    // Navigate and search (same as before)
    await scraper.page.goto('https://www.lcb1.com/BerthSchedule', { waitUntil: 'networkidle2' });
    await scraper.page.waitForSelector('select');
    const dropdowns = await scraper.page.$$('select');
    await dropdowns[0].select('MARSA PRIDE');
    
    console.log('✅ MARSA PRIDE selected, clicking Search...');
    
    // Click the blue Search button
    const searchClicked = await scraper.page.evaluate(() => {
      const buttons = document.querySelectorAll('button, input[type="submit"], input[type="button"]');
      for (const btn of buttons) {
        const text = (btn.textContent || btn.value || '').toLowerCase().trim();
        const style = window.getComputedStyle(btn);
        
        // Look for search button or blue colored button
        if (text.includes('search') || 
            style.backgroundColor.includes('blue') || 
            btn.className.includes('btn') ||
            text === 'search') {
          btn.click();
          console.log('Clicked button with text:', text);
          return true;
        }
      }
      return false;
    });
    
    console.log('Search button clicked:', searchClicked);
    
    console.log('🔍 Search clicked, waiting for table...');
    
    // Enhanced wait for the "Overview Calls" table to appear
    await scraper.page.waitForFunction(() => {
      // Look for the specific table structure from your screenshot
      const text = document.body.textContent;
      return text.includes('Overview Calls') && 
             text.includes('Voyage In') && 
             text.includes('Voyage Out') &&
             text.includes('Berthing Time') &&
             text.includes('MARSA PRIDE') &&
             text.includes('528S'); // The specific voyage code from your screenshot
    }, { timeout: 30000 });
    
    console.log('📊 Table detected! Extracting data...');
    
    // Extract the table data using the exact structure from your screenshot
    const scheduleData = await scraper.page.evaluate(() => {
      const results = [];
      
      // Look for the table containing the schedule data
      const tables = document.querySelectorAll('table');
      
      for (const table of tables) {
        const tableText = table.textContent;
        
        // Skip if this table doesn't contain our vessel schedule
        if (!tableText.includes('MARSA PRIDE') || !tableText.includes('Voyage In')) {
          continue;
        }
        
        console.log('Found schedule table');
        
        const rows = table.querySelectorAll('tr');
        let headerRowIndex = -1;
        let dataRowIndex = -1;
        
        // Find the header row
        for (let i = 0; i < rows.length; i++) {
          const rowText = rows[i].textContent.toLowerCase();
          if (rowText.includes('vessel name') && rowText.includes('voyage in')) {
            headerRowIndex = i;
            break;
          }
        }
        
        console.log('Header row found at index:', headerRowIndex);
        
        // Find the data row with MARSA PRIDE
        for (let i = headerRowIndex + 1; i < rows.length; i++) {
          const rowText = rows[i].textContent;
          if (rowText.includes('MARSA PRIDE')) {
            dataRowIndex = i;
            break;
          }
        }
        
        console.log('Data row found at index:', dataRowIndex);
        
        if (headerRowIndex >= 0 && dataRowIndex >= 0) {
          const headerCells = rows[headerRowIndex].querySelectorAll('th, td');
          const dataCells = rows[dataRowIndex].querySelectorAll('td, th');
          
          console.log('Header cells:', headerCells.length);
          console.log('Data cells:', dataCells.length);
          
          // Map the data based on column positions
          const headers = Array.from(headerCells).map(cell => cell.textContent.trim().toLowerCase());
          const data = Array.from(dataCells).map(cell => cell.textContent.trim());
          
          console.log('Headers:', headers);
          console.log('Data:', data);
          
          // Create the structured result
          const vesselData = {};
          
          // Map each header to its corresponding data
          headers.forEach((header, index) => {
            if (data[index]) {
              if (header.includes('vessel')) vesselData.vessel_name = data[index];
              if (header.includes('voyage in')) vesselData.voyage_in = data[index];
              if (header.includes('voyage out')) vesselData.voyage_out = data[index];
              if (header.includes('berthing')) vesselData.berthing_time = data[index];
              if (header.includes('departure')) vesselData.departure_time = data[index];
              if (header.includes('terminal')) vesselData.terminal = data[index];
            }
          });
          
          // If we didn't get headers properly, use positional mapping (based on your screenshot)
          if (!vesselData.vessel_name && data.length >= 6) {
            vesselData.vessel_name = data[1]; // Column 2: Vessel Name
            vesselData.voyage_in = data[2];   // Column 3: Voyage In  
            vesselData.voyage_out = data[3];  // Column 4: Voyage Out
            vesselData.berthing_time = data[4]; // Column 5: Berthing Time
            vesselData.departure_time = data[5]; // Column 6: Departure Time
            vesselData.terminal = data[6];    // Column 7: Terminal
          }
          
          vesselData.raw_table_data = data;
          vesselData.extraction_method = 'table_structure';
          
          results.push(vesselData);
        }
      }
      
      return results;
    });
    
    console.log('\\n🎉 EXTRACTION RESULTS:');
    console.log('====================');
    console.log('Results found:', scheduleData.length);
    
    if (scheduleData.length > 0) {
      const result = scheduleData[0];
      console.log('\\n📋 MARSA PRIDE SCHEDULE:');
      console.log('Vessel Name:', result.vessel_name);
      console.log('Voyage In:', result.voyage_in);
      console.log('Voyage Out:', result.voyage_out);
      console.log('Berthing Time:', result.berthing_time);
      console.log('Departure Time:', result.departure_time);
      console.log('Terminal:', result.terminal);
      console.log('Raw Data:', result.raw_table_data);
      console.log('Extraction Method:', result.extraction_method);
      
      // Test date parsing
      const eta = parseDateTime(result.berthing_time);
      const etd = parseDateTime(result.departure_time);
      console.log('\\n📅 PARSED DATES:');
      console.log('ETA (parsed):', eta);
      console.log('ETD (parsed):', etd);
    } else {
      console.log('❌ No schedule data extracted - debugging needed');
    }
    
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await scraper.cleanup();
  }
})();

// Date parsing function (same as in your scraper)
function parseDateTime(dateTimeString) {
  if (!dateTimeString) return null;
  
  // Handle "22/07/2025 - 04:00" format from your screenshot
  const datePatterns = [
    /(\d{2})\/(\d{2})\/(\d{4})\s*[-–]\s*(\d{2}):(\d{2})/,
    /(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/,
    /(\d{2})\/(\d{2})\/(\d{4})/
  ];
  
  for (const pattern of datePatterns) {
    const match = dateTimeString.match(pattern);
    if (match) {
      try {
        const day = parseInt(match[1]);
        const month = parseInt(match[2]) - 1;
        const year = parseInt(match[3]);
        const hour = match[4] ? parseInt(match[4]) : 0;
        const minute = match[5] ? parseInt(match[5]) : 0;
        
        const date = new Date(year, month, day, hour, minute);
        return date.toISOString().slice(0, 19).replace('T', ' ');
      } catch (parseError) {
        continue;
      }
    }
  }
  
  return null;
}
