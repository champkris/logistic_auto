const puppeteer = require('puppeteer');

async function debugJWDPage() {
    let browser = null;

    try {
        console.log('Starting comprehensive JWD page debug...');

        browser = await puppeteer.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

        console.log('Navigating to JWD shipping schedule page...');
        await page.goto('https://www.dg-net.org/th/service-shipping', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Wait a bit more for any dynamic content
        await new Promise(resolve => setTimeout(resolve, 5000));

        console.log('Capturing page information...');

        const pageInfo = await page.evaluate(() => {
            const info = {
                title: document.title,
                url: window.location.href,
                body_text: document.body ? document.body.textContent.substring(0, 1000) : 'No body',
                has_tables: document.querySelectorAll('table').length > 0,
                table_count: document.querySelectorAll('table').length,
                all_elements_with_josco: [],
                shipping_schedule_text: false
            };

            // Look for JOSCO HELEN anywhere in the page
            const allElements = document.querySelectorAll('*');
            for (const element of allElements) {
                const text = element.textContent || '';
                if (text.toLowerCase().includes('josco') || text.toLowerCase().includes('helen')) {
                    info.all_elements_with_josco.push({
                        tag: element.tagName,
                        text: text.substring(0, 200),
                        classes: element.className
                    });
                }
            }

            // Check if "SHIPPING SCHEDULE" text is on the page
            if (document.body && document.body.textContent.includes('SHIPPING SCHEDULE')) {
                info.shipping_schedule_text = true;
            }

            return info;
        });

        console.log('Page information:');
        console.log(JSON.stringify(pageInfo, null, 2));

        // Take a screenshot for debugging
        await page.screenshot({ path: '/tmp/jwd-debug.png', fullPage: true });
        console.log('Screenshot saved to /tmp/jwd-debug.png');

        return pageInfo;

    } catch (error) {
        console.error('Debug error:', error);
        return { error: error.message };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Main execution
(async () => {
    const result = await debugJWDPage();
    console.log('\n=== FINAL RESULT ===');
    console.log(JSON.stringify(result));
})();