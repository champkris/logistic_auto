#!/usr/bin/env node

// Test Puppeteer 13.7.0 compatibility with Node.js 14
console.log('Testing Puppeteer 13.7.0 compatibility...');
console.log('Node.js version:', process.version);

try {
    const puppeteer = require('puppeteer');
    console.log('✅ Puppeteer loaded successfully');
    console.log('Puppeteer version:', puppeteer.version || 'Unknown');

    // Test basic Puppeteer functionality
    (async () => {
        try {
            console.log('🚀 Testing browser launch...');
            const browser = await puppeteer.launch({
                headless: true,
                args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
            });

            console.log('✅ Browser launched successfully');

            const page = await browser.newPage();
            console.log('✅ New page created');

            await page.goto('data:text/html,<h1>Test Page</h1>');
            const title = await page.evaluate(() => document.querySelector('h1').textContent);
            console.log('✅ Page navigation and evaluation works:', title);

            await browser.close();
            console.log('✅ Browser closed successfully');

            console.log('\n🎉 Puppeteer 13.7.0 is fully compatible!');
            process.exit(0);

        } catch (error) {
            console.error('❌ Browser test failed:', error.message);
            process.exit(1);
        }
    })();

} catch (error) {
    console.error('❌ Puppeteer failed to load:', error.message);
    process.exit(1);
}