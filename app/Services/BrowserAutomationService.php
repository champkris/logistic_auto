<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BrowserAutomationService
{
    /**
     * Find the correct Node.js binary path for browser automation
     * This helps resolve Node.js version mismatches between terminal and PHP execution
     */
    public static function getNodePath()
    {
        // Check for environment variable first
        $envNodePath = env('NODE_BINARY_PATH');
        if ($envNodePath) {
            // If it's a relative path like 'node', use it directly to avoid open_basedir issues
            if ($envNodePath === 'node' || !str_starts_with($envNodePath, '/')) {
                Log::info("Using Node.js binary from environment (relative path)", [
                    'path' => $envNodePath
                ]);
                return $envNodePath;
            }

            // For absolute paths, test without file_exists to avoid open_basedir restrictions
            $testCommand = escapeshellarg($envNodePath) . ' --version 2>&1';
            $output = shell_exec($testCommand);
            if ($output && strpos($output, 'v') === 0) {
                Log::info("Using Node.js binary from environment", [
                    'path' => $envNodePath,
                    'version' => trim($output)
                ]);
                return $envNodePath;
            }
        }

        // Common Node.js installation paths to check
        $possiblePaths = [
            'node',                        // Try PATH first (most hosting-friendly)
            '/usr/local/bin/node',         // Common on Linux/Mac
            '/usr/bin/node',                // Alternative Linux path
            '/opt/homebrew/bin/node',       // Homebrew on Apple Silicon
            '/opt/homebrew/opt/node@18/bin/node', // Homebrew versioned Node
            '/opt/homebrew/opt/node@20/bin/node', // Homebrew Node 20
            '/usr/local/n/versions/node/latest/bin/node', // n version manager
            '/home/deploy/.nvm/versions/node/latest/bin/node', // nvm on Linux
        ];

        // Test each path by running --version (avoid file_exists for hosting compatibility)
        foreach ($possiblePaths as $path) {
            try {
                $testCommand = escapeshellarg($path) . ' --version 2>&1';
                $output = shell_exec($testCommand);

                // Check if we got a valid version output
                if ($output && strpos($output, 'v') === 0) {
                    $version = trim($output);
                    Log::info("Found working Node.js binary", [
                        'path' => $path,
                        'version' => $version
                    ]);
                    return $path;
                }
            } catch (\Exception $e) {
                // Continue to next path if this one fails
                continue;
            }
        }

        // If no suitable Node.js found, log error and fallback
        Log::error("Could not find suitable Node.js binary. Browser automation may fail.");
        return 'node'; // Fallback to PATH
    }

    /**
     * Execute a Node.js script with proper error handling
     */
    public static function runNodeScript($scriptPath, $args = [], $timeout = 90)
    {
        $nodePath = self::getNodePath();

        // Build the command
        $command = escapeshellarg($nodePath) . ' ' . escapeshellarg($scriptPath);

        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        Log::info('Running browser automation', [
            'node_path' => $nodePath,
            'script' => basename($scriptPath),
            'command' => $command
        ]);

        // Use proc_open for better control
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        // Add timeout to the command
        if ($timeout > 0) {
            $command = "timeout {$timeout} " . $command;
        }

        $process = proc_open($command, $descriptors, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]); // Close stdin

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            // Log any stderr output for debugging
            if (!empty($stderr)) {
                Log::info("Browser automation stderr", ['output' => $stderr]);
            }

            if ($returnCode !== 0 && empty($stdout)) {
                throw new \Exception("Browser automation failed (exit code: {$returnCode}): " . $stderr);
            }

            return [
                'stdout' => $stdout,
                'stderr' => $stderr,
                'return_code' => $returnCode
            ];
        }

        throw new \Exception("Failed to start browser automation process");
    }

    /**
     * Scrape JWD Terminal vessel schedule
     */
    public function scrapeJWDVesselSchedule($vesselName, $voyageCode)
    {
        try {
            Log::info("Starting JWD scraper", [
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode
            ]);

            // Create JWD scraper script if it doesn't exist
            $this->ensureJWDScraperExists();

            $scriptPath = base_path('browser-automation/scrapers/jwd-scraper.js');
            $args = [$vesselName, $voyageCode];

            $result = self::runNodeScript($scriptPath, $args, 120);
            $jsonOutput = trim($result['stdout']);

            Log::info("JWD scraper raw output", [
                'output' => $jsonOutput,
                'stderr' => $result['stderr']
            ]);

            if (!empty($jsonOutput)) {
                $decoded = json_decode($jsonOutput, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    Log::info("JWD scraper completed", [
                        'vessel_name' => $vesselName,
                        'voyage_code' => $voyageCode,
                        'result' => $decoded
                    ]);

                    return $decoded;
                } else {
                    Log::error("Invalid JSON from JWD scraper: " . $jsonOutput);
                    throw new \Exception("Invalid JSON response from JWD scraper");
                }
            } else {
                Log::error("Empty output from JWD scraper", [
                    'stderr' => $result['stderr'],
                    'return_code' => $result['return_code']
                ]);
                throw new \Exception("Empty response from JWD scraper");
            }

        } catch (\Exception $e) {
            Log::error("JWD scraper error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'vessel_found' => false,
                'voyage_found' => false,
                'eta' => null
            ];
        }
    }

    /**
     * Ensure JWD scraper script exists
     */
    private function ensureJWDScraperExists()
    {
        $scraperPath = base_path('browser-automation/scrapers/jwd-scraper.js');

        if (!file_exists($scraperPath)) {
            $this->createJWDScraperScript($scraperPath);
        }
    }

    /**
     * Create JWD scraper script
     */
    private function createJWDScraperScript($path)
    {
        $script = <<<'JS'
const puppeteer = require('puppeteer');

async function scrapeJWDSchedule(vesselName, voyageCode) {
    let browser = null;

    try {
        console.error(`Starting JWD scraper for vessel: ${vesselName}, voyage: ${voyageCode}`);

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

        console.error('Navigating to JWD tracking page...');
        await page.goto('https://www.dg-net.org/th/service-tracking', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        console.error('Searching for vessel in form...');

        // Try to fill the vessel search form
        try {
            // Wait for vessel name input
            await page.waitForSelector('input[name*="vessel"], input[id*="vessel"], input[placeholder*="vessel"]', { timeout: 5000 });

            // Fill vessel name
            await page.type('input[name*="vessel"], input[id*="vessel"], input[placeholder*="vessel"]', vesselName);

            // Fill voyage if there's a voyage field
            const voyageSelector = 'input[name*="voyage"], input[name*="voy"], input[id*="voyage"], input[placeholder*="voyage"]';
            if (await page.$(voyageSelector)) {
                await page.type(voyageSelector, voyageCode);
            }

            // Click search/submit button
            const searchButton = await page.$('button[type="submit"], input[type="submit"], button:contains("Search"), button:contains("search")');
            if (searchButton) {
                await searchButton.click();
                await page.waitForTimeout(3000);
            }
        } catch (formError) {
            console.error('Form interaction error:', formError.message);
        }

        // Look for vessel schedule table or data
        console.error('Looking for vessel schedule data...');

        const vessels = await page.evaluate((searchVessel, searchVoyage) => {
            const results = [];

            // Look for tables with vessel data
            const tables = document.querySelectorAll('table');

            for (const table of tables) {
                const rows = table.querySelectorAll('tr');

                for (const row of rows) {
                    const cells = row.querySelectorAll('td, th');
                    const rowText = row.textContent || '';

                    // Check if this row contains our vessel
                    if (rowText.toLowerCase().includes(searchVessel.toLowerCase()) ||
                        rowText.includes(searchVoyage)) {

                        const cellTexts = Array.from(cells).map(cell => cell.textContent.trim());

                        // Look for ETA/date patterns
                        const etaPattern = /\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}/;
                        const timePattern = /\d{1,2}:\d{2}/;

                        let eta = null;
                        for (const cellText of cellTexts) {
                            const dateMatch = cellText.match(etaPattern);
                            if (dateMatch) {
                                eta = dateMatch[0];
                                // Look for time in the same cell
                                const timeMatch = cellText.match(timePattern);
                                if (timeMatch) {
                                    eta += ' ' + timeMatch[0];
                                }
                                break;
                            }
                        }

                        results.push({
                            vessel_name: searchVessel,
                            voyage_code: searchVoyage,
                            eta: eta,
                            row_text: rowText,
                            cells: cellTexts
                        });
                    }
                }
            }

            return results;
        }, vesselName, voyageCode);

        console.error(`Found ${vessels.length} matching vessels`);

        if (vessels.length > 0) {
            const vessel = vessels[0];
            return {
                success: true,
                vessel_found: true,
                voyage_found: true,
                vessel_name: vesselName,
                voyage_code: voyageCode,
                eta: vessel.eta,
                terminal: 'JWD Terminal',
                message: `Found vessel ${vesselName} voyage ${voyageCode}`,
                raw_data: vessel
            };
        } else {
            return {
                success: true,
                vessel_found: false,
                voyage_found: false,
                vessel_name: vesselName,
                voyage_code: voyageCode,
                eta: null,
                terminal: 'JWD Terminal',
                message: `Vessel ${vesselName} voyage ${voyageCode} not found in schedule`
            };
        }

    } catch (error) {
        console.error('JWD scraper error:', error);
        return {
            success: false,
            error: error.message,
            vessel_found: false,
            voyage_found: false,
            vessel_name: vesselName,
            voyage_code: voyageCode,
            eta: null
        };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Main execution
(async () => {
    const vesselName = process.argv[2];
    const voyageCode = process.argv[3];

    if (!vesselName || !voyageCode) {
        console.log(JSON.stringify({
            success: false,
            error: 'Missing vessel name or voyage code parameters'
        }));
        process.exit(1);
    }

    const result = await scrapeJWDSchedule(vesselName, voyageCode);
    console.log(JSON.stringify(result));
})();
JS;

        file_put_contents($path, $script);
        chmod($path, 0755);

        Log::info("Created JWD scraper script", ['path' => $path]);
    }

    /**
     * Scrape full schedule from Hutchison terminal
     * Returns all vessels without requiring vessel name input
     */
    public function scrapeHutchisonFullSchedule(string $terminal): ?array
    {
        $nodePath = self::getNodePath();
        $scriptPath = base_path('browser-automation/scrapers/hutchison-full-schedule-scraper.js');

        if (!file_exists($scriptPath)) {
            Log::error("Hutchison full schedule scraper not found", ['path' => $scriptPath]);
            return null;
        }

        $command = sprintf(
            '%s %s %s 2>/dev/null',
            escapeshellarg($nodePath),
            escapeshellarg($scriptPath),
            escapeshellarg($terminal)
        );

        Log::info("Running Hutchison full schedule scraper", [
            'terminal' => $terminal,
            'command' => $command
        ]);

        $output = shell_exec($command);

        if (!$output) {
            Log::error("No output from Hutchison scraper");
            return null;
        }

        // Parse JSON output
        $result = json_decode($output, true);

        if (!$result) {
            Log::error("Failed to parse Hutchison scraper output", ['output' => $output]);
            return null;
        }

        Log::info("Hutchison full schedule scraping complete", [
            'terminal' => $terminal,
            'vessel_count' => count($result['vessels'] ?? [])
        ]);

        return $result;
    }

    /**
     * Scrape full schedule from Shipmentlink terminal (SIAM, KERRY)
     */
    public function scrapeShipmentlinkFullSchedule(string $terminal): ?array
    {
        $nodePath = self::getNodePath();
        $scriptPath = base_path('browser-automation/scrapers/shipmentlink-full-schedule-scraper.js');

        if (!file_exists($scriptPath)) {
            Log::error("Shipmentlink full schedule scraper not found", ['path' => $scriptPath]);
            return null;
        }

        $command = sprintf(
            '%s %s %s 2>/dev/null',
            escapeshellarg($nodePath),
            escapeshellarg($scriptPath),
            escapeshellarg($terminal)
        );

        Log::info("Running Shipmentlink full schedule scraper", [
            'terminal' => $terminal,
            'command' => $command
        ]);

        $output = shell_exec($command);

        if (!$output) {
            Log::error("No output from Shipmentlink scraper");
            return null;
        }

        $result = json_decode($output, true);

        if (!$result) {
            Log::error("Failed to parse Shipmentlink scraper output", ['output' => $output]);
            return null;
        }

        Log::info("Shipmentlink full schedule scraping complete", [
            'terminal' => $terminal,
            'vessel_count' => count($result['vessels'] ?? [])
        ]);

        return $result;
    }

    /**
     * Scrape full schedule from TIPS terminal
     */
    public function scrapeTipsFullSchedule(): ?array
    {
        $nodePath = self::getNodePath();
        $scriptPath = base_path('browser-automation/scrapers/tips-full-schedule-scraper.js');

        if (!file_exists($scriptPath)) {
            Log::error("TIPS full schedule scraper not found", ['path' => $scriptPath]);
            return null;
        }

        $command = sprintf(
            '%s %s 2>/dev/null',
            escapeshellarg($nodePath),
            escapeshellarg($scriptPath)
        );

        Log::info("Running TIPS full schedule scraper", ['command' => $command]);

        $output = shell_exec($command);

        if (!$output) {
            Log::error("No output from TIPS scraper");
            return null;
        }

        $result = json_decode($output, true);

        if (!$result) {
            Log::error("Failed to parse TIPS scraper output", ['output' => $output]);
            return null;
        }

        Log::info("TIPS full schedule scraping complete", [
            'vessel_count' => count($result['vessels'] ?? [])
        ]);

        return $result;
    }

    /**
     * Scrape full schedule from ESCO (B3) terminal
     */
    public function scrapeEscoFullSchedule(): ?array
    {
        $nodePath = self::getNodePath();
        $scriptPath = base_path('browser-automation/scrapers/esco-full-schedule-scraper.js');

        if (!file_exists($scriptPath)) {
            Log::error("ESCO full schedule scraper not found", ['path' => $scriptPath]);
            return null;
        }

        $command = sprintf(
            '%s %s 2>/dev/null',
            escapeshellarg($nodePath),
            escapeshellarg($scriptPath)
        );

        Log::info("Running ESCO full schedule scraper", ['command' => $command]);

        $output = shell_exec($command);

        if (!$output) {
            Log::error("No output from ESCO scraper");
            return null;
        }

        $result = json_decode($output, true);

        if (!$result) {
            Log::error("Failed to parse ESCO scraper output", ['output' => $output]);
            return null;
        }

        Log::info("ESCO full schedule scraping complete", [
            'vessel_count' => count($result['vessels'] ?? [])
        ]);

        return $result;
    }

    /**
     * Scrape full schedule from LCIT (B5) terminal
     */
    public function scrapeLcitFullSchedule(): ?array
    {
        $nodePath = self::getNodePath();
        $scriptPath = base_path('browser-automation/scrapers/lcit-full-schedule-scraper.js');

        if (!file_exists($scriptPath)) {
            Log::error("LCIT full schedule scraper not found", ['path' => $scriptPath]);
            return null;
        }

        $command = sprintf(
            '%s %s 2>/dev/null',
            escapeshellarg($nodePath),
            escapeshellarg($scriptPath)
        );

        Log::info("Running LCIT full schedule scraper", ['command' => $command]);

        $output = shell_exec($command);

        if (!$output) {
            Log::error("No output from LCIT scraper");
            return null;
        }

        $result = json_decode($output, true);

        if (!$result) {
            Log::error("Failed to parse LCIT scraper output", ['output' => $output]);
            return null;
        }

        Log::info("LCIT full schedule scraping complete", [
            'vessel_count' => count($result['vessels'] ?? [])
        ]);

        return $result;
    }
}