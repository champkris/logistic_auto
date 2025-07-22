const { LCB1VesselScraper } = require('./scrapers/lcb1-scraper');
const cron = require('cron');
const winston = require('winston');

// Configure logging
const logger = winston.createLogger({
  level: 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.printf(({ timestamp, level, message }) => {
      return `${timestamp} [${level.toUpperCase()}]: ${message}`;
    })
  ),
  transports: [
    new winston.transports.Console(),
    new winston.transports.File({ filename: 'vessel-automation.log' })
  ]
});

class VesselAutomationOrchestrator {
  constructor(laravelApiUrl = 'http://localhost:8003') {
    this.apiUrl = laravelApiUrl;
    this.jobs = [];
    
    // Initialize scrapers
    this.scrapers = {
      lcb1: new LCB1VesselScraper(laravelApiUrl)
    };
  }

  // Run all browser-dependent scrapers
  async runAllScrapers() {
    logger.info('🚀 Starting vessel automation run...');
    const startTime = new Date();
    const results = {};

    try {
      // LCB1 Scraper
      logger.info('📋 Running LCB1 scraper...');
      try {
        await this.scrapers.lcb1.initialize();
        results.lcb1 = await this.scrapers.lcb1.scrapeVesselSchedule('MARSA PRIDE');
        
        if (results.lcb1.success) {
          await this.scrapers.lcb1.sendToLaravel(results.lcb1);
        }
        
        await this.scrapers.lcb1.cleanup();
        logger.info('✅ LCB1 scraper completed');
      } catch (error) {
        results.lcb1 = { success: false, error: error.message };
        logger.error(`❌ LCB1 scraper failed: ${error.message}`);
      }

      // Add more scrapers here (LCIT, ECTT)
      // results.lcit = await this.runLCITScraper();
      // results.ectt = await this.runECTTScraper();

      const endTime = new Date();
      const duration = Math.round((endTime - startTime) / 1000);
      
      logger.info(`🎯 Automation run completed in ${duration} seconds`);
      
      // Send summary to Laravel
      await this.sendSummaryToLaravel({
        run_id: `automation_${Date.now()}`,
        started_at: startTime.toISOString(),
        completed_at: endTime.toISOString(),
        duration_seconds: duration,
        results: results,
        success_count: Object.values(results).filter(r => r.success).length,
        total_count: Object.keys(results).length
      });
      
      return results;

    } catch (error) {
      logger.error(`💥 Automation run failed: ${error.message}`);
      throw error;
    }
  }

  // Send automation summary to Laravel
  async sendSummaryToLaravel(summary) {
    try {
      const axios = require('axios');
      
      await axios.post(`${this.apiUrl}/api/automation-summary`, summary, {
        headers: { 'Content-Type': 'application/json' },
        timeout: 30000
      });
      
      logger.info('✅ Sent automation summary to Laravel');
    } catch (error) {
      logger.error(`❌ Failed to send summary to Laravel: ${error.message}`);
    }
  }

  // Schedule automated runs
  startScheduler() {
    logger.info('⏰ Starting automation scheduler...');

    // Run every 6 hours: 00:00, 06:00, 12:00, 18:00
    const job = new cron.CronJob('0 0 */6 * * *', async () => {
      logger.info('⏰ Scheduled automation run triggered');
      try {
        await this.runAllScrapers();
      } catch (error) {
        logger.error(`💥 Scheduled run failed: ${error.message}`);
      }
    });

    // Also run a test shortly after startup (in 1 minute)
    const testJob = new cron.CronJob('0 */1 * * * *', async () => {
      logger.info('🧪 Running initial test automation...');
      try {
        await this.runAllScrapers();
        testJob.stop(); // Stop the test job after first run
      } catch (error) {
        logger.error(`💥 Test run failed: ${error.message}`);
      }
    });

    job.start();
    testJob.start();

    this.jobs.push(job, testJob);
    
    logger.info('✅ Scheduler started successfully');
    logger.info('📅 Next scheduled run: ' + job.nextDates().toString());
  }

  // Stop all scheduled jobs
  stopScheduler() {
    logger.info('⏹️ Stopping automation scheduler...');
    
    this.jobs.forEach(job => job.stop());
    this.jobs = [];
    
    logger.info('✅ Scheduler stopped');
  }

  // Manual test run
  async testRun() {
    logger.info('🧪 Running manual test...');
    return await this.runAllScrapers();
  }
}

// CLI interface
async function main() {
  const args = process.argv.slice(2);
  const command = args[0] || 'test';
  
  const orchestrator = new VesselAutomationOrchestrator();
  
  switch (command) {
    case 'test':
      logger.info('🧪 Running test mode...');
      try {
        const results = await orchestrator.testRun();
        console.log('🎉 Test Results:', JSON.stringify(results, null, 2));
        process.exit(0);
      } catch (error) {
        console.error('💥 Test failed:', error.message);
        process.exit(1);
      }
      break;
      
    case 'schedule':
      logger.info('⏰ Starting scheduled automation...');
      orchestrator.startScheduler();
      
      // Keep process running
      process.on('SIGINT', () => {
        logger.info('🛑 Received SIGINT, shutting down gracefully...');
        orchestrator.stopScheduler();
        process.exit(0);
      });
      
      // Keep alive
      setInterval(() => {
        logger.info('💓 Automation service is running...');
      }, 60 * 60 * 1000); // Log every hour
      break;
      
    case 'lcb1':
      logger.info('🚢 Running LCB1 scraper only...');
      try {
        const scraper = new LCB1VesselScraper();
        await scraper.initialize();
        const result = await scraper.scrapeVesselSchedule('MARSA PRIDE');
        await scraper.cleanup();
        
        console.log('🎉 LCB1 Result:', JSON.stringify(result, null, 2));
        process.exit(result.success ? 0 : 1);
      } catch (error) {
        console.error('💥 LCB1 scraper failed:', error.message);
        process.exit(1);
      }
      break;
      
    default:
      console.log(`
🚢 CS Shipping LCB - Vessel Automation

Usage:
  node vessel-scraper.js test      # Run test automation
  node vessel-scraper.js schedule  # Start scheduled automation (every 6 hours)
  node vessel-scraper.js lcb1      # Run LCB1 scraper only

Examples:
  npm start                        # Same as 'test'
  npm run lcb1                     # Run LCB1 scraper
      `);
      process.exit(0);
  }
}

// Run if this file is executed directly
if (require.main === module) {
  main().catch(error => {
    console.error('💥 Unhandled error:', error);
    process.exit(1);
  });
}

module.exports = { VesselAutomationOrchestrator };
