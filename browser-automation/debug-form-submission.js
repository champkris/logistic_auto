const { EverbuildVesselScraper } = require('./scrapers/everbuild-scraper');

// Enhanced search with proper form submission
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
        
        console.log('📄 Page loaded, analyzing form structure...');
        
        // Analyze the form structure first
        const formInfo = await scraper.page.evaluate(() => {
            const forms = document.querySelectorAll('form');
            const selects = document.querySelectorAll('select');
            const inputs = document.querySelectorAll('input[type="submit"], button[type="submit"]');
            
            const formData = [];
            forms.forEach((form, index) => {
                formData.push({
                    index,
                    action: form.action,
                    method: form.method,
                    inputs: Array.from(form.querySelectorAll('input')).map(input => ({
                        name: input.name,
                        type: input.type,
                        value: input.value
                    }))
                });
            });
            
            return {
                forms: formData,
                selects: Array.from(selects).map(select => ({
                    name: select.name,
                    id: select.id,
                    optionsCount: select.options.length
                })),
                submitButtons: Array.from(inputs).map(input => ({
                    name: input.name,
                    value: input.value,
                    type: input.type
                }))
            };
        });
        
        console.log('📋 Form analysis:', JSON.stringify(formInfo, null, 2));
        
        // Select vessel and submit form properly
        console.log('🔽 Selecting EVER BUILD and submitting form...');
        
        const searchResult = await scraper.page.evaluate((vesselName) => {
            try {
                // Find the vessel dropdown
                const dropdown = document.querySelector('select');
                if (!dropdown) return { success: false, error: 'No dropdown found' };
                
                // Find EVER BUILD option
                const options = Array.from(dropdown.options);
                const everBuildOption = options.find(opt => opt.text.includes('EVER BUILD'));
                
                if (!everBuildOption) {
                    return { success: false, error: 'EVER BUILD not found in dropdown' };
                }
                
                // Select the vessel
                dropdown.value = everBuildOption.value;
                dropdown.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Find the form containing this dropdown
                let form = dropdown.closest('form');
                if (!form) {
                    // If dropdown is not in a form, find the nearest form
                    form = document.querySelector('form');
                }
                
                if (!form) {
                    return { success: false, error: 'No form found' };
                }
                
                console.log('Found form:', form.action, form.method);
                console.log('Selected vessel:', everBuildOption.text, 'value:', everBuildOption.value);
                
                // Submit the form
                form.submit();
                
                return { 
                    success: true, 
                    selectedVessel: everBuildOption.text,
                    formAction: form.action,
                    formMethod: form.method
                };
                
            } catch (error) {
                return { success: false, error: error.message };
            }
        }, vesselName);
        
        console.log('🔍 Search submission result:', searchResult);
        
        if (!searchResult.success) {
            throw new Error('Form submission failed: ' + searchResult.error);
        }
        
        // Wait for navigation to results page
        console.log('⏳ Waiting for navigation to results page...');
        
        try {
            await scraper.page.waitForNavigation({ 
                waitUntil: 'networkidle2', 
                timeout: 15000 
            });
            console.log('✅ Navigation completed');
        } catch (navError) {
            console.log('⚠️ Navigation timeout, checking current page...');
        }
        
        // Check current page
        const currentUrl = scraper.page.url();
        const currentTitle = await scraper.page.title();
        
        console.log(`📍 Current URL: ${currentUrl}`);
        console.log(`📋 Current Title: ${currentTitle}`);
        
        // Capture page content after submission
        const afterSubmissionContent = await scraper.page.evaluate(() => {
            const tables = document.querySelectorAll('table');
            const tableData = [];
            
            tables.forEach((table, index) => {
                const rows = table.querySelectorAll('tr');
                const tableRows = [];
                
                rows.forEach((row, rowIndex) => {
                    const cells = row.querySelectorAll('td, th');
                    const rowData = Array.from(cells).map(cell => cell.textContent.trim());
                    
                    if (rowData.length > 0 && rowData.some(cell => cell.length > 0)) {
                        tableRows.push(rowData);
                    }
                });
                
                if (tableRows.length > 0) {
                    tableData.push({
                        tableIndex: index,
                        rows: tableRows.slice(0, 10), // First 10 rows only
                        totalRows: tableRows.length
                    });
                }
            });
            
            // Check for EVER BUILD in page content
            const pageText = document.body.textContent;
            const hasEverBuild = pageText.includes('EVER BUILD');
            const hasScheduleData = pageText.includes('ETA') || pageText.includes('ETD') || pageText.includes('Schedule');
            
            return {
                tables: tableData,
                hasEverBuild,
                hasScheduleData,
                bodyTextPreview: pageText.substring(0, 1000)
            };
        });
        
        console.log('\n=== CONTENT AFTER FORM SUBMISSION ===');
        console.log('Has EVER BUILD in content:', afterSubmissionContent.hasEverBuild);
        console.log('Has schedule data:', afterSubmissionContent.hasScheduleData);
        console.log('Tables found:', afterSubmissionContent.tables.length);
        
        afterSubmissionContent.tables.forEach((table, index) => {
            console.log(`\n--- TABLE ${index + 1} (${table.totalRows} total rows) ---`);
            table.rows.slice(0, 5).forEach((row, rowIndex) => {
                console.log(`Row ${rowIndex + 1}:`, row);
            });
        });
        
        console.log('\n--- BODY TEXT PREVIEW ---');
        console.log(afterSubmissionContent.bodyTextPreview);
        
        // Take final screenshot
        await scraper.page.screenshot({ 
            path: `everbuild-final-results-${Date.now()}.png`, 
            fullPage: true 
        });
        console.log('\n📸 Final screenshot saved');
        
    } catch (error) {
        console.error('❌ Error:', error.message);
        
        try {
            await scraper.page.screenshot({ 
                path: `everbuild-submission-error-${Date.now()}.png`, 
                fullPage: true 
            });
            console.log('📸 Error screenshot saved');
        } catch (screenshotError) {
            console.error('Failed to take error screenshot');
        }
    } finally {
        await scraper.cleanup();
    }
})();
