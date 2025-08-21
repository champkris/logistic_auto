const { EverbuildVesselScraper } = require('./scrapers/everbuild-scraper');

// Debug script to see what content is actually returned after search
(async () => {
    const vesselName = 'EVER BUILD';
    
    const scraper = new EverbuildVesselScraper();
    
    try {
        await scraper.initialize();
        
        console.log('🔍 Navigating to shipmentlink.com...');
        await scraper.page.goto('https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        console.log('📄 Page loaded, waiting for dropdown...');
        await scraper.page.waitForSelector('select', { timeout: 10000 });
        
        // Select vessel
        console.log('🔽 Selecting EVER BUILD from dropdown...');
        const selected = await scraper.page.evaluate(() => {
            const dropdown = document.querySelector('select');
            const options = Array.from(dropdown.options);
            const everBuildOption = options.find(opt => opt.text.includes('EVER BUILD'));
            
            if (everBuildOption) {
                dropdown.value = everBuildOption.value;
                dropdown.dispatchEvent(new Event('change', { bubbles: true }));
                return { success: true, selected: everBuildOption.text };
            }
            return { success: false };
        });
        
        if (!selected.success) {
            throw new Error('Could not select EVER BUILD from dropdown');
        }
        
        console.log(`✅ Selected: ${selected.selected}`);
        
        // Click search
        console.log('🔍 Clicking search button...');
        await scraper.page.evaluate(() => {
            const searchButton = document.querySelector('input[type="submit"], button[type="submit"]');
            if (searchButton) searchButton.click();
        });
        
        // Wait for results
        console.log('⏳ Waiting for results...');
        await new Promise(resolve => setTimeout(resolve, 5000));
        
        // Capture page content after search
        console.log('📊 Extracting page content...');
        const pageContent = await scraper.page.evaluate(() => {
            // Get all tables
            const tables = document.querySelectorAll('table');
            const tableData = [];
            
            tables.forEach((table, index) => {
                const rows = table.querySelectorAll('tr');
                const tableRows = [];
                
                rows.forEach((row, rowIndex) => {
                    const cells = row.querySelectorAll('td, th');
                    const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                    
                    if (rowData.length > 0) {
                        tableRows.push(rowData);
                    }
                });
                
                if (tableRows.length > 0) {
                    tableData.push({
                        tableIndex: index,
                        rows: tableRows,
                        rowCount: tableRows.length
                    });
                }
            });
            
            return {
                pageTitle: document.title,
                url: window.location.href,
                tables: tableData,
                bodyText: document.body.textContent.slice(0, 1000) // First 1000 chars
            };
        });
        
        console.log('\n=== PAGE CONTENT AFTER SEARCH ===');
        console.log('Title:', pageContent.pageTitle);
        console.log('URL:', pageContent.url);
        console.log('Tables found:', pageContent.tables.length);
        
        pageContent.tables.forEach((table, index) => {
            console.log(`\n--- TABLE ${index + 1} (${table.rowCount} rows) ---`);
            table.rows.slice(0, 5).forEach((row, rowIndex) => {
                console.log(`Row ${rowIndex + 1}:`, row);
            });
            if (table.rows.length > 5) {
                console.log(`... (${table.rows.length - 5} more rows)`);
            }
        });
        
        console.log('\n--- BODY TEXT PREVIEW ---');
        console.log(pageContent.bodyText.substring(0, 500));
        
        // Take screenshot
        await scraper.page.screenshot({ 
            path: `everbuild-search-results-${Date.now()}.png`, 
            fullPage: true 
        });
        console.log('\n📸 Screenshot saved for debugging');
        
    } catch (error) {
        console.error('❌ Error:', error.message);
        
        // Take error screenshot
        try {
            await scraper.page.screenshot({ 
                path: `everbuild-debug-error-${Date.now()}.png`, 
                fullPage: true 
            });
            console.log('📸 Error screenshot saved');
        } catch (screenshotError) {
            console.error('Failed to take screenshot');
        }
    } finally {
        await scraper.cleanup();
    }
})();
