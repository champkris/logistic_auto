<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\VesselNameParser;
use App\Services\BrowserAutomationService;

class VesselTrackingService
{
    /**
     * Port to terminal mapping
     * Multiple ports can use the same terminal/scraper
     */
    protected $portToTerminal = [
        // Hutchison Ports
        'C1' => 'hutchison',
        'C2' => 'hutchison',
        'C1C2' => 'hutchison', // Legacy support

        // TIPS
        'B4' => 'tips',

        // LCIT
        'B5' => 'lcit',
        'C3' => 'lcit',
        'B5C3' => 'lcit', // Legacy support

        // ESCO
        'B3' => 'esco',

        // LCB1
        'A0' => 'lcb1',
        'B1' => 'lcb1',
        'A0B1' => 'lcb1', // Legacy support

        // ShipmentLink
        'B2' => 'shipmentlink',

        // Additional ports that may use existing scrapers
        'A3' => 'lcb1', // Assuming A3 uses same system as A0/B1

        // Siam Commercial
        'SIAM' => 'siam',

        // Kerry Logistics
        'KERRY' => 'kerry',

        // JWD Terminal
        'JWD' => 'jwd',
    ];

    /**
     * Terminal configurations
     * Contains the scraper settings for each terminal operator
     */
    protected $terminals = [
        'hutchison' => [
            'name' => 'Hutchison Ports',
            'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:13:::::',
            'vessel_full' => 'WAN HAI 517 S093',
            'method' => 'hutchison_browser',
            'ports' => ['C1', 'C2', 'C1C2']
        ],
        'tips' => [
            'name' => 'TIPS',
            'url' => 'https://www.tips.co.th/container/shipSched/List',
            'vessel_full' => 'SRI SUREE V.25080S',
            'method' => 'tips_browser',
            'ports' => ['B4']
        ],
        'lcit' => [
            'name' => 'LCIT',
            'url' => 'https://www.lcit.com/home',
            'vessel_full' => 'SKY SUNSHINE V.2513S',
            'vessel_name' => 'SKY SUNSHINE',
            'voyage_code' => '2513S',
            'method' => 'lcit',
            'ports' => ['B5', 'C3', 'B5C3']
        ],
        'esco' => [
            'name' => 'ESCO',
            'url' => 'https://service.esco.co.th/BerthSchedule',
            'vessel_full' => 'CUL NANSHA V. 2528S',
            'method' => 'esco',
            'ports' => ['B3']
        ],
        'lcb1' => [
            'name' => 'LCB1',
            'url' => 'https://www.lcb1.com/BerthSchedule',
            'vessel_full' => 'MARSA PRIDE 528S',
            'method' => 'lcb1',
            'ports' => ['A0', 'B1', 'A0B1', 'A3']
        ],
        'shipmentlink' => [
            'name' => 'ShipmentLink',
            'url' => 'https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp',
            'vessel_full' => 'EVER BASIS 0813-068S',
            'vessel_name' => 'EVER BASIS',
            'voyage_code' => '0813-068S',
            'vessel_code' => 'BASS',
            'method' => 'shipmentlink_browser',
            'ports' => ['B2']
        ],
        'siam' => [
            'name' => 'Siam Commercial',
            'url' => 'n8n_integration',
            'vessel_full' => 'SAMPLE VESSEL V.001S',
            'vessel_name' => 'SAMPLE VESSEL',
            'voyage_code' => '001S',
            'method' => 'siam_n8n_line',
            'ports' => ['SIAM']
        ],
        'kerry' => [
            'name' => 'Kerry Logistics',
            'url' => 'https://terminaltracking.ksp.kln.com/SearchVesselVisit',
            'vessel_full' => 'BUXMELODY 230N',
            'vessel_name' => 'BUXMELODY',
            'voyage_code' => '230N',
            'search_url' => 'https://terminaltracking.ksp.kln.com/SearchVesselVisit/List',
            'method' => 'kerry_http_request',
            'ports' => ['KERRY']
        ],
        'jwd' => [
            'name' => 'JWD Terminal',
            'url' => 'https://www.dg-net.org/th/service-shipping',
            'api_url' => 'https://www.dg-net.org/th/service-api/shipping-schedule',
            'vessel_full' => 'JOSCO HELEN 2520S',
            'vessel_name' => 'JOSCO HELEN',
            'voyage_code' => '2520S',
            'method' => 'jwd_http_request',
            'ports' => ['JWD']
        ]
    ];

    public function __construct()
    {
        // Auto-parse vessel names in terminal config
        foreach ($this->terminals as $code => &$config) {
            if (isset($config['vessel_full'])) {
                $parsed = VesselNameParser::parse($config['vessel_full']);
                $config['vessel_name'] = $parsed['vessel_name'];
                $config['voyage_code'] = $parsed['voyage_code'];
            }
        }
    }

    /**
     * Check ETA for a specific vessel by parsing the vessel name automatically
     */
    public function checkVesselETAByName($vesselFullName, $portCode = null)
    {
        $parsed = VesselNameParser::parse($vesselFullName);

        // If no port specified, try all unique terminals
        if (!$portCode) {
            $results = [];
            $processedTerminals = [];

            foreach ($this->portToTerminal as $port => $terminalKey) {
                // Skip if we've already processed this terminal
                if (in_array($terminalKey, $processedTerminals)) {
                    continue;
                }

                $results[$port] = $this->checkVesselETAWithParsedName($parsed, $port);
                $processedTerminals[] = $terminalKey;
            }
            return $results;
        }

        return $this->checkVesselETAWithParsedName($parsed, $portCode);
    }

    /**
     * Check ETA using parsed vessel name components
     */
    protected function checkVesselETAWithParsedName($parsedVessel, $portCode)
    {
        // Get the terminal key from port code
        $terminalKey = $this->portToTerminal[strtoupper($portCode)] ?? null;

        if (!$terminalKey) {
            // Try legacy format (e.g., C1C2 instead of C1/C2)
            $terminalKey = $this->portToTerminal[$portCode] ?? null;
        }

        if (!$terminalKey) {
            throw new \Exception("Unknown port code: {$portCode}");
        }

        $config = $this->terminals[$terminalKey] ?? null;
        if (!$config) {
            throw new \Exception("Terminal configuration not found for port: {$portCode}");
        }

        // Override config with the vessel we're actually searching for
        $config['vessel_full'] = $parsedVessel['full_name'];
        $config['vessel_name'] = $parsedVessel['vessel_name'];
        $config['voyage_code'] = $parsedVessel['voyage_code'];
        $config['port_code'] = $portCode; // Keep track of which port was requested

        try {
            $method = $config['method'];
            return $this->$method($config);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'terminal' => $config['name'],
                'port_code' => $portCode,
                'vessel_full' => $parsedVessel['full_name'],
                'vessel_name' => $parsedVessel['vessel_name'],
                'voyage_code' => $parsedVessel['voyage_code'],
                'checked_at' => now()
            ];
        }
    }

    public function testAllTerminals()
    {
        $results = [];
        $processedTerminals = [];

        foreach ($this->portToTerminal as $portCode => $terminalKey) {
            // Skip if we've already tested this terminal
            if (in_array($terminalKey, $processedTerminals)) {
                continue;
            }

            $config = $this->terminals[$terminalKey];
            echo "🚢 Testing Terminal {$terminalKey} ({$config['name']}) - Ports: " . implode(', ', $config['ports']) . "\n";
            if (isset($config['vessel_name']) && isset($config['voyage_code'])) {
                echo "   Test Vessel: {$config['vessel_name']} + Voyage: {$config['voyage_code']}\n";
            }
            echo "📍 URL: {$config['url']}\n";

            $result = $this->checkVesselETA($portCode, $config);
            $results[$terminalKey] = $result;

            $this->displayResult($terminalKey, $result);
            echo "─────────────────────────────────────────────────────────\n";

            $processedTerminals[] = $terminalKey;

            // Be respectful - wait between requests
            sleep(2);
        }

        return $results;
    }

    public function checkVesselETA($portCode, $config)
    {
        try {
            $method = $config['method'];
            $config['port_code'] = $portCode; // Add port code to config
            return $this->$method($config);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'terminal' => $config['name'],
                'port_code' => $portCode,
                'vessel_full' => $config['vessel_full'] ?? '',
                'vessel_name' => $config['vessel_name'] ?? '',
                'voyage_code' => $config['voyage_code'] ?? '',
                'checked_at' => now()
            ];
        }
    }

    protected function hutchison_browser($config)
    {
        try {
            // Hutchison site has vessel and voyage in separate columns, so only pass vessel name
            $vesselName = $config['vessel_name'];
            $expectedVoyageCode = $config['voyage_code'] ?? null;

            \Log::info("Starting Hutchison Ports browser automation", [
                'vessel_name' => $vesselName,
                'expected_voyage' => $expectedVoyageCode
            ]);

            $browserAutomationPath = base_path('browser-automation');
            $scriptPath = $browserAutomationPath . '/hutchison-wrapper.js';

            // Use the new BrowserAutomationService to handle Node.js version issues
            $result = BrowserAutomationService::runNodeScript($scriptPath, [$vesselName], 90);

            $jsonOutput = $result['stdout'];
            $logOutput = $result['stderr'];
            $returnCode = $result['return_code'];

            // Log the browser automation logs for debugging
            if (!empty($logOutput)) {
                \Log::info("Hutchison browser automation logs: " . $logOutput);
            }

            // Check if we have valid JSON output regardless of return code
            if (!empty($jsonOutput)) {
                $result = json_decode($jsonOutput, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($result['success'])) {
                        if ($result['success']) {
                            // Validate voyage code if we have an expected one
                            $voyageFound = true;
                            if ($expectedVoyageCode && isset($result['voyage_code'])) {
                                $voyageFound = (strtoupper($result['voyage_code']) === strtoupper($expectedVoyageCode));

                                if (!$voyageFound) {
                                    \Log::info("Vessel found but voyage code mismatch", [
                                        'expected' => $expectedVoyageCode,
                                        'found' => $result['voyage_code']
                                    ]);

                                    // Update result to indicate voyage not found
                                    $result['voyage_found'] = false;
                                    $result['message'] = "Vessel {$vesselName} found but voyage {$expectedVoyageCode} not found (found: {$result['voyage_code']})";
                                } else {
                                    $result['voyage_found'] = true;
                                }
                            }

                            $result['vessel_found'] = true;

                            \Log::info("Hutchison browser automation completed", [
                                'vessel_name' => $vesselName,
                                'vessel_found' => true,
                                'voyage_found' => $voyageFound,
                                'result' => $result
                            ]);
                        } else {
                            \Log::info("Hutchison browser automation completed - vessel not found", [
                                'vessel_name' => $vesselName,
                                'result' => $result
                            ]);

                            // Ensure success field is properly set when vessel not found
                            $result['success'] = true; // The scraping succeeded, just no vessel found
                            $result['vessel_found'] = false;
                            $result['voyage_found'] = false;
                            $result['eta'] = null;

                            // Add terminal and vessel info for consistency
                            if (!isset($result['terminal'])) {
                                $result['terminal'] = 'Hutchison Ports';
                            }
                            if (!isset($result['vessel_name'])) {
                                $result['vessel_name'] = $vesselName;
                            }
                            if (!isset($result['voyage_code'])) {
                                $result['voyage_code'] = $expectedVoyageCode ?? null;
                            }
                        }

                return $result;
            } else {
                \Log::error("Invalid JSON from Hutchison browser automation: " . $jsonOutput);
                throw new \Exception("Invalid JSON response from browser automation");
            }
        } else {
            \Log::error("Hutchison browser automation failed - no output", [
                'return_code' => $returnCode,
                'log_output' => $logOutput
            ]);
            throw new \Exception("Browser automation failed with no output. Return code: {$returnCode}");
        }

        } catch (\Exception $e) {
            \Log::error("Hutchison browser automation exception: " . $e->getMessage());

            return [
                'success' => false,
                'terminal' => 'Hutchison Ports',
                'vessel_name' => $config['vessel_name'] ?? '',
                'voyage_code' => $config['voyage_code'] ?? '',
                'vessel_full' => $config['vessel_full'] ?? '',
                'vessel_found' => false,
                'voyage_found' => false,
                'full_name_found' => false,
                'search_method' => 'browser_automation_failed',
                'eta' => null,
                'error' => $e->getMessage(),
                'checked_at' => now()
            ];
        }
    }

    protected function tips($config)
    {
        // TIPS - Container ship schedule
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Referer' => 'https://www.tips.co.th/',
            ])
            ->get($config['url']);

        if (!$response->successful()) {
            throw new \Exception("HTTP Error: " . $response->status());
        }

        return $this->parseVesselData($response->body(), $config);
    }

    protected function tips_browser($config)
    {
        try {
            $vesselName = $config['vessel_name'] ?? 'SRI SUREE';
            $voyageCode = $config['voyage_code'] ?? '';

            \Log::info("Starting TIPS browser automation", [
                'vessel' => $vesselName,
                'voyage' => $voyageCode,
                'terminal' => 'B4'
            ]);

            $browserAutomationPath = base_path('browser-automation');

            // Use proc_open to call the TIPS wrapper with vessel name and voyage
            $command = sprintf(
                "cd %s && timeout 120 node tips-wrapper.js %s %s 2>/dev/null",
                escapeshellarg($browserAutomationPath),
                escapeshellarg($vesselName),
                escapeshellarg($voyageCode ?: '')
            );

            $descriptors = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout (JSON)
                2 => ['pipe', 'w']   // stderr (logs)
            ];

            $process = proc_open($command, $descriptors, $pipes);

            if (is_resource($process)) {
                fclose($pipes[0]); // Close stdin

                $jsonOutput = stream_get_contents($pipes[1]);
                $logOutput = stream_get_contents($pipes[2]);

                fclose($pipes[1]);
                fclose($pipes[2]);

                $returnCode = proc_close($process);

                // Log the browser automation logs for debugging
                if (!empty($logOutput)) {
                    \Log::info("TIPS browser automation logs:", ['logs' => $logOutput]);
                }

                if (!empty($jsonOutput)) {
                    $result = json_decode($jsonOutput, true);

                    if ($result && isset($result['success'])) {
                        \Log::info("TIPS browser automation result:", $result);

                        return [
                            'success' => $result['success'],
                            'terminal' => $result['terminal'] ?? 'TIPS',
                            'vessel_name' => $result['vessel_name'] ?? $vesselName,
                            'voyage_code' => $result['voyage_code'] ?? $voyageCode,
                            'vessel_found' => $result['vessel_found'] ?? false,
                            'voyage_found' => $result['voyage_found'] ?? false,
                            'eta' => $result['eta'],
                            'search_method' => 'tips_browser_automation',
                            'pages_scanned' => $result['pages_scanned'] ?? null,
                            'checked_at' => now()
                        ];
                    }
                }

                // If we get here, something went wrong
                \Log::error("TIPS browser automation failed", [
                    'return_code' => $returnCode,
                    'json_output' => $jsonOutput,
                    'log_output' => $logOutput
                ]);

                return [
                    'success' => false,
                    'terminal' => 'TIPS',
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'eta' => null,
                    'error' => 'Browser automation process failed',
                    'search_method' => 'tips_browser_automation_failed',
                    'checked_at' => now()
                ];
            }

        } catch (\Exception $e) {
            \Log::error("TIPS browser automation exception", [
                'error' => $e->getMessage(),
                'vessel' => $vesselName ?? 'unknown',
                'voyage' => $voyageCode ?? 'unknown'
            ]);

            return [
                'success' => false,
                'terminal' => 'TIPS',
                'vessel_name' => $vesselName ?? 'unknown',
                'voyage_code' => $voyageCode ?? 'unknown',
                'vessel_found' => false,
                'voyage_found' => false,
                'eta' => null,
                'error' => $e->getMessage(),
                'search_method' => 'tips_browser_automation_error',
                'checked_at' => now()
            ];
        }
    }

    protected function lcit($config)
    {
        $vesselName = $config['vessel_name'] ?? 'SKY SUNSHINE';
        $voyageCode = $config['voyage_code'] ?? '';

        \Log::info("LCIT check for vessel: {$vesselName}, voyage: {$voyageCode}");

        try {
            // Use the LCIT scraper wrapper for cleaner output
            $command = sprintf(
                'cd %s && timeout 120s node lcit-wrapper.js %s %s 2>/dev/null',
                escapeshellarg(base_path('browser-automation')),
                escapeshellarg($vesselName),
                escapeshellarg($voyageCode ?: '')
            );

            $descriptors = [
                0 => ["pipe", "r"],  // stdin
                1 => ["pipe", "w"],  // stdout
                2 => ["pipe", "w"]   // stderr
            ];

            $output = '';
            $process = proc_open($command, $descriptors, $pipes);

            if (is_resource($process)) {
                fclose($pipes[0]); // Close stdin

                $jsonOutput = stream_get_contents($pipes[1]);
                $logOutput = stream_get_contents($pipes[2]);

                fclose($pipes[1]);
                fclose($pipes[2]);

                $returnCode = proc_close($process);

                // Log the browser automation logs for debugging
                if (!empty($logOutput)) {
                    \Log::info("LCIT browser automation logs:", ['logs' => $logOutput]);
                }

                if (!$jsonOutput) {
                    // Handle timeout or connection issues gracefully
                    \Log::warning("LCIT browser automation failed: no JSON output (exit code: {$returnCode})");

                    return [
                        'success' => true,
                        'terminal' => $config['name'],
                        'vessel_found' => false,
                        'voyage_found' => false,
                        'vessel_name' => $vesselName,
                        'voyage_code' => $voyageCode,
                        'eta' => null,
                        'etd' => null,
                        'search_method' => 'lcit_timeout_fallback',
                        'message' => 'LCIT terminal not accessible - connection timeout',
                        'no_data_reason' => 'Terminal website experiencing connectivity issues',
                        'checked_at' => now()
                    ];
                }

                $output = $jsonOutput;
            } else {
                throw new \Exception("Failed to start LCIT browser automation process");
            }

            if (!$output) {
                throw new \Exception("LCIT browser automation failed: no output");
            }

            // Parse the JSON result
            $result = json_decode(trim($output), true);

            if (!$result) {
                throw new \Exception("Invalid JSON from LCIT browser automation: " . substr($output, 0, 200));
            }

            // Check if this is a connectivity issue vs vessel not found
            if (!$result['success']) {
                $errorMessage = $result['error'] ?? $result['message'] ?? 'Unknown error';

                // Handle connection timeouts as expected behavior
                if (str_contains($errorMessage, 'ERR_CONNECTION_TIMED_OUT') ||
                    str_contains($errorMessage, 'Timeout') ||
                    str_contains($errorMessage, 'navigation') ||
                    str_contains($errorMessage, 'Failed to navigate')) {

                    \Log::info("LCIT terminal connection timeout for {$vesselName} - website may be temporarily unavailable");

                    return [
                        'success' => true,
                        'terminal' => $config['name'],
                        'vessel_found' => false,
                        'voyage_found' => false,
                        'vessel_name' => $vesselName,
                        'voyage_code' => $voyageCode,
                        'eta' => null,
                        'etd' => null,
                        'search_method' => 'lcit_connection_timeout',
                        'message' => 'LCIT terminal not accessible - connection timeout',
                        'no_data_reason' => 'Terminal website experiencing connectivity issues',
                        'checked_at' => now()
                    ];
                }

                // Handle vessel not found in accessible terminal
                if (str_contains($errorMessage, 'not found in schedule') ||
                    isset($result['details']) && str_contains($result['details'], 'not found in the current schedule')) {

                    \Log::info("LCIT terminal accessible but {$vesselName} not found in current schedule");

                    return [
                        'success' => true,
                        'terminal' => $config['name'],
                        'vessel_found' => false,
                        'voyage_found' => false,
                        'vessel_name' => $vesselName,
                        'voyage_code' => $voyageCode,
                        'eta' => null,
                        'etd' => null,
                        'search_method' => 'lcit_not_found',
                        'message' => 'Vessel not found in LCIT schedule',
                        'no_data_reason' => 'Vessel not in current terminal schedule',
                        'checked_at' => now()
                    ];
                }

                // Other errors
                throw new \Exception("LCIT scraper error: " . $errorMessage);
            }

            // Success - vessel found
            \Log::info("LCIT successful check for {$vesselName}", ['result' => $result]);

            return [
                'success' => true,
                'terminal' => $config['name'],
                'vessel_found' => true,
                'voyage_found' => !empty($result['voyage_code']) && $result['voyage_code'] !== 'Unknown',
                'vessel_name' => $result['vessel_name'] ?? $vesselName,
                'voyage_code' => $result['voyage_code'] ?? $voyageCode,
                'eta' => $result['eta'],
                'etd' => $result['etd'],
                'berth' => $result['berth'],
                'search_method' => 'lcit_scraper',
                'raw_data' => $result['raw_data'] ?? null,
                'checked_at' => now()
            ];

        } catch (\Exception $e) {
            \Log::error("LCIT terminal error: " . $e->getMessage());

            return [
                'success' => false,
                'terminal' => $config['name'],
                'error' => $e->getMessage(),
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'search_method' => 'lcit_error',
                'checked_at' => now()
            ];
        }
    }

    protected function esco($config)
    {
        // ESCO - Berth Schedule
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])
            ->get($config['url']);

        if (!$response->successful()) {
            throw new \Exception("HTTP Error: " . $response->status());
        }

        return $this->parseVesselData($response->body(), $config);
    }

    protected function lcb1($config)
    {
        // LCB1 - Use Browser Automation (required for dynamic content)
        try {
            $vesselName = $config['vessel_name'] ?? 'MARSA PRIDE';

            // Use the new BrowserAutomationService
            $browserAutomationPath = base_path('browser-automation');
            $scriptPath = $browserAutomationPath . '/laravel-wrapper.js';

            $result = BrowserAutomationService::runNodeScript($scriptPath, [$vesselName], 60);

            $jsonOutput = $result['stdout'];
            $logOutput = $result['stderr'];
            $returnCode = $result['return_code'];
                
            // Log the browser automation logs for debugging
            if (!empty($logOutput)) {
                \Log::info("Browser automation logs:", ['logs' => $logOutput]);
            }

            if (!$jsonOutput) {
                throw new \Exception("Browser automation failed: no JSON output (exit code: {$returnCode})");
            }

            $output = $jsonOutput;
            
            if (!$output) {
                throw new \Exception("Browser automation failed: no output");
            }
            
            // Parse the JSON result
            $result = json_decode(trim($output), true);
            
            if (!$result) {
                throw new \Exception("Invalid JSON from browser automation: " . substr($output, 0, 200));
            }
            
            // Log the parsed result for debugging
            \Log::info("LCB1 Browser Automation Result:", ['result' => $result]);
            
            // Check if this is a "no data found" scenario vs actual error
            if (!$result['success']) {
                $errorMessage = $result['error'] ?? $result['message'] ?? 'Unknown error';
                
                // Handle "no data found" as a valid result, not an error
                if (str_contains($errorMessage, 'No current schedule data') || 
                    str_contains($errorMessage, 'no schedule data available') ||
                    str_contains($errorMessage, 'no schedule data') ||
                    isset($result['details']) && str_contains($result['details'], 'no schedule data')) {
                    
                    \Log::info("Terminal {$config['name']}: No schedule data found for {$vesselName} - this is expected for vessels without current schedules");
                    
                    return [
                        'success' => true,
                        'terminal' => $config['name'],
                        'vessel_found' => true,
                        'voyage_found' => false,
                        'vessel_name' => $result['vessel_name'] ?? $vesselName,
                        'voyage_code' => null,
                        'eta' => null,
                        'etd' => null,
                        'search_method' => 'browser_automation',
                        'message' => $errorMessage,
                        'no_data_reason' => 'Vessel exists but no current schedule available',
                        'raw_data' => $result,
                        'checked_at' => now()
                    ];
                }
                
                // This is an actual automation error
                throw new \Exception("Browser automation error: " . $errorMessage);
            }
            
            // Convert to Laravel expected format
            return [
                'success' => true,
                'terminal' => $config['name'],
                'vessel_found' => true,
                'voyage_found' => !empty($result['voyage_code']),
                'vessel_name' => $result['vessel_name'] ?? $vesselName,
                'voyage_code' => $result['voyage_code'],
                'eta' => $result['eta'],
                'etd' => $result['etd'],
                'search_method' => 'browser_automation',
                'raw_data' => $result['raw_data'] ?? null,
                'checked_at' => now()
            ];
            
        } catch (\Exception $e) {
            \Log::error("LCB1 Browser Automation Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'terminal' => $config['name'],
                'vessel_found' => false,
                'voyage_found' => false,
                'eta' => null,
                'error' => 'Browser automation failed: ' . $e->getMessage(),
                'search_method' => 'browser_automation_failed',
                'checked_at' => now()
            ];
        }
    }

    protected function shipmentlink_browser($config)
    {
        try {
            $vesselName = $config['vessel_name'] ?? 'EVER BUILD';
            $voyageCode = $config['voyage_code'] ?? '';

            // Extract vessel code from vessel name (last word or configured code)
            $vesselCode = $config['vessel_code'] ?? $this->extractVesselCode($vesselName);

            \Log::info("Starting ShipmentLink browser automation", [
                'vessel' => $vesselName,
                'code' => $vesselCode,
                'voyage' => $voyageCode
            ]);

            $browserAutomationPath = base_path('browser-automation');

            // Use proc_open with vessel name, code, and voyage
            $command = sprintf(
                "cd %s && timeout 120 node shipmentlink-wrapper.js %s %s %s 2>/dev/null",
                escapeshellarg($browserAutomationPath),
                escapeshellarg($vesselName),
                escapeshellarg($vesselCode),
                escapeshellarg($voyageCode ?: '')
            );
            
            $descriptors = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout (JSON)
                2 => ['pipe', 'w']   // stderr (logs)
            ];
            
            $process = proc_open($command, $descriptors, $pipes);
            
            if (is_resource($process)) {
                fclose($pipes[0]); // Close stdin
                
                $jsonOutput = stream_get_contents($pipes[1]);
                $logOutput = stream_get_contents($pipes[2]);
                
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $returnCode = proc_close($process);
                
                // Log the browser automation logs for debugging
                if (!empty($logOutput)) {
                    \Log::info("ShipmentLink browser automation logs:", ['logs' => $logOutput]);
                }
                
                if (!$jsonOutput) {
                    throw new \Exception("Browser automation failed: no JSON output (exit code: {$returnCode})");
                }
                
                $output = $jsonOutput;
            } else {
                throw new \Exception("Failed to start browser automation process");
            }
            
            if (!$output) {
                throw new \Exception("Browser automation failed: no output");
            }
            
            // Parse the JSON result
            $result = json_decode(trim($output), true);
            
            if (!$result) {
                throw new \Exception("Invalid JSON from browser automation: " . substr($output, 0, 200));
            }
            
            // Check if this is a "no data found" scenario vs actual error
            if (!$result['success']) {
                $errorMessage = $result['error'] ?? $result['message'] ?? 'Unknown error';
                
                // Handle "no data found" as a valid result, not an error
                if (str_contains($errorMessage, 'No current schedule data') || 
                    str_contains($errorMessage, 'no schedule data available') ||
                    str_contains($errorMessage, 'no schedule data') ||
                    isset($result['details']) && str_contains($result['details'], 'no schedule data')) {
                    
                    \Log::info("Terminal {$config['name']}: No schedule data found for {$vesselName} - this is expected for vessels without current schedules");
                    
                    return [
                        'success' => true,
                        'terminal' => $config['name'],
                        'vessel_found' => true,
                        'voyage_found' => false,
                        'vessel_name' => $result['vessel_name'] ?? $vesselName,
                        'voyage_code' => null,
                        'eta' => null,
                        'etd' => null,
                        'search_method' => 'browser_automation',
                        'message' => $errorMessage,
                        'no_data_reason' => 'Vessel exists but no current schedule available',
                        'raw_data' => $result,
                        'checked_at' => now()
                    ];
                }
                
                // This is an actual automation error
                throw new \Exception("Browser automation error: " . $errorMessage);
            }
            
            // Convert to Laravel expected format
            return [
                'success' => true,
                'terminal' => $config['name'],
                'vessel_found' => true,
                'voyage_found' => !empty($result['voyage_code']),
                'vessel_name' => $result['vessel_name'] ?? $vesselName,
                'voyage_code' => $result['voyage_code'],
                'eta' => $result['eta'],
                'etd' => $result['etd'],
                'port' => $result['port'] ?? null,
                'service' => $result['service'] ?? null,
                'search_method' => 'shipmentlink_browser_automation',
                'raw_data' => $result['raw_data'] ?? null,
                'checked_at' => now()
            ];
            
        } catch (\Exception $e) {
            \Log::error("ShipmentLink Browser Automation Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'terminal' => $config['name'],
                'vessel_found' => false,
                'voyage_found' => false,
                'eta' => null,
                'error' => 'Browser automation failed: ' . $e->getMessage(),
                'search_method' => 'shipmentlink_browser_automation_failed',
                'checked_at' => now()
            ];
        }
    }

    protected function everbuild_browser($config)
    {
        try {
            $vesselName = $config['vessel_name'];
            \Log::info("Starting Everbuild browser automation for vessel: {$vesselName}");
            
            $browserAutomationPath = base_path('browser-automation');
            
            // FIXED: Use proc_open to separate stdout (JSON) from stderr (logs)
            $command = "cd {$browserAutomationPath} && timeout 60 node everbuild-wrapper.js '{$vesselName}'";
            
            $descriptors = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout (JSON)
                2 => ['pipe', 'w']   // stderr (logs)
            ];
            
            $process = proc_open($command, $descriptors, $pipes);
            
            if (is_resource($process)) {
                fclose($pipes[0]); // Close stdin
                
                $jsonOutput = stream_get_contents($pipes[1]);
                $logOutput = stream_get_contents($pipes[2]);
                
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $returnCode = proc_close($process);
                
                // Log the browser automation logs for debugging
                if (!empty($logOutput)) {
                    \Log::info("Everbuild browser automation logs:", ['logs' => $logOutput]);
                }
                
                if (!$jsonOutput) {
                    throw new \Exception("Browser automation failed: no JSON output (exit code: {$returnCode})");
                }
                
                $output = $jsonOutput;
            } else {
                throw new \Exception("Failed to start browser automation process");
            }
            
            if (!$output) {
                throw new \Exception("Browser automation failed: no output");
            }
            
            // Parse the JSON result
            $result = json_decode(trim($output), true);
            
            if (!$result) {
                throw new \Exception("Invalid JSON from browser automation: " . substr($output, 0, 200));
            }
            
            // Check if this is a "no data found" scenario vs actual error
            if (!$result['success']) {
                $errorMessage = $result['error'] ?? $result['message'] ?? 'Unknown error';
                
                // Handle "no data found" as a valid result, not an error
                if (str_contains($errorMessage, 'No current schedule data') || 
                    str_contains($errorMessage, 'no schedule data available') ||
                    str_contains($errorMessage, 'no schedule data') ||
                    isset($result['details']) && str_contains($result['details'], 'no schedule data')) {
                    
                    \Log::info("Terminal {$config['name']}: No schedule data found for {$vesselName} - this is expected for vessels without current schedules");
                    
                    return [
                        'success' => true,
                        'terminal' => $config['name'],
                        'vessel_found' => true,
                        'voyage_found' => false,
                        'vessel_name' => $result['vessel_name'] ?? $vesselName,
                        'voyage_code' => null,
                        'eta' => null,
                        'etd' => null,
                        'search_method' => 'browser_automation',
                        'message' => $errorMessage,
                        'no_data_reason' => 'Vessel exists but no current schedule available',
                        'raw_data' => $result,
                        'checked_at' => now()
                    ];
                }
                
                // This is an actual automation error
                throw new \Exception("Browser automation error: " . $errorMessage);
            }
            
            // Convert to Laravel expected format
            return [
                'success' => true,
                'terminal' => $config['name'],
                'vessel_found' => true,
                'voyage_found' => !empty($result['voyage_code']),
                'vessel_name' => $result['vessel_name'] ?? $vesselName,
                'voyage_code' => $result['voyage_code'],
                'eta' => $result['eta'],
                'etd' => $result['etd'],
                'search_method' => 'browser_automation',
                'raw_data' => $result['raw_data'] ?? null,
                'checked_at' => now()
            ];
            
        } catch (\Exception $e) {
            \Log::error("Everbuild Browser Automation Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'terminal' => $config['name'],
                'vessel_found' => false,
                'voyage_found' => false,
                'eta' => null,
                'error' => 'Browser automation failed: ' . $e->getMessage(),
                'search_method' => 'browser_automation_failed',
                'checked_at' => now()
            ];
        }
    }

    protected function ectt($config)
    {
        // ECTT - Note: This URL goes to cookie policy, may need to find actual schedule page
        $baseUrl = 'https://www.ectt.co.th';
        
        // First, get the main page to find the actual schedule link
        $response = Http::timeout(30)->get($baseUrl);
        
        if (!$response->successful()) {
            throw new \Exception("HTTP Error: " . $response->status());
        }

        // Look for schedule or vessel links
        $html = $response->body();
        $scheduleLinks = $this->findScheduleLinks($html, $baseUrl);
        
        if (!empty($scheduleLinks)) {
            foreach ($scheduleLinks as $link) {
                try {
                    $scheduleResponse = Http::timeout(30)->get($link);
                    if ($scheduleResponse->successful()) {
                        $result = $this->parseVesselData($scheduleResponse->body(), $config);
                        if ($result['vessel_found']) {
                            return $result;
                        }
                    }
                } catch (\Exception $e) {
                    continue; // Try next link
                }
            }
        }

        return [
            'success' => true,
            'terminal' => $config['name'],
            'vessel' => $config['vessel'],
            'vessel_found' => false,
            'eta' => null,
            'message' => 'Could not locate vessel schedule page',
            'checked_at' => now()
        ];
    }

    protected function parseVesselData($html, $config)
    {
        $vesselName = $config['vessel_name'];
        $voyageCode = $config['voyage_code'];
        $fullVesselName = $config['vessel_full'];
        
        // First try to find the vessel name (most important)
        $vesselNameFound = str_contains(strtoupper($html), strtoupper($vesselName));
        
        // Try to find the voyage code - with multiple variations for different websites
        $voyageCodeFound = false;
        $voyageSearchVariations = [$voyageCode];
        
        // Add variations for voyage codes that might have prefixes (IMPROVED for spaces)
        if (preg_match('/^([A-Z]+)\.?\s*(.+)$/', $voyageCode, $matches)) {
            // For "V. 2528S" -> also try "2528S" (remove prefix with space)
            $voyageSearchVariations[] = $matches[2];
        }
        if (preg_match('/^(.+?)([A-Z\d]+)$/', $voyageCode, $matches)) {
            // For "V. 2528S" -> also try "V2528S" (remove dots and spaces)
            $noPrefixVersion = str_replace(['.', ' '], '', $voyageCode);
            if (!in_array($noPrefixVersion, $voyageSearchVariations)) {
                $voyageSearchVariations[] = $noPrefixVersion;
            }
        }
        
        // Additional handling for space-separated prefixes like "V. 2528S"
        if (str_contains($voyageCode, ' ')) {
            $parts = explode(' ', $voyageCode);
            if (count($parts) >= 2) {
                // Take the last part (the actual voyage number)
                $lastPart = end($parts);
                if (!in_array($lastPart, $voyageSearchVariations)) {
                    $voyageSearchVariations[] = $lastPart;
                }
            }
        }
        
        foreach ($voyageSearchVariations as $variation) {
            if (str_contains(strtoupper($html), strtoupper($variation))) {
                $voyageCodeFound = true;
                break;
            }
        }
        
        // Also try the full name as fallback
        $fullNameFound = str_contains(strtoupper($html), strtoupper($fullVesselName));
        
        // Determine if vessel is found (vessel name is most important)
        $vesselFound = $vesselNameFound || $fullNameFound;
        
        if ($vesselFound) {
            // Extract ETA - try multiple search patterns
            $eta = null;
            $vesselSection = '';
            
            if ($vesselNameFound) {
                $eta = $this->extractETAFromHTML($html, $vesselName, $voyageCode);
                $vesselSection = $this->extractVesselSection($html, $vesselName);
            } elseif ($fullNameFound) {
                $eta = $this->extractETAFromHTML($html, $fullVesselName, $voyageCode);
                $vesselSection = $this->extractVesselSection($html, $fullVesselName);
            }
            
            // If no ETA found with vessel name, try with voyage code variations
            if (!$eta && $voyageCodeFound) {
                foreach ($voyageSearchVariations as $variation) {
                    if (str_contains(strtoupper($html), strtoupper($variation))) {
                        $voyageEta = $this->extractETAFromHTML($html, $variation, $voyageCode);
                        if ($voyageEta) {
                            $eta = $voyageEta;
                            $vesselSection .= "\n--- Voyage Section ---\n" . $this->extractVesselSection($html, $variation);
                            break;
                        }
                    }
                }
            }
            
            return [
                'success' => true,
                'terminal' => $config['name'],
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'vessel_full' => $fullVesselName,
                'vessel_found' => $vesselNameFound,
                'voyage_found' => $voyageCodeFound,
                'full_name_found' => $fullNameFound,
                'search_method' => $this->getSearchMethod($vesselNameFound, $voyageCodeFound, $fullNameFound),
                'voyage_variations_tried' => $voyageSearchVariations,
                'eta' => $eta,
                'raw_data' => $vesselSection,
                'checked_at' => now()
            ];
        }

        return [
            'success' => true,
            'terminal' => $config['name'],
            'vessel_name' => $vesselName,
            'voyage_code' => $voyageCode,
            'vessel_full' => $fullVesselName,
            'vessel_found' => false,
            'voyage_found' => false,
            'full_name_found' => false,
            'eta' => null,
            'message' => 'Neither vessel name nor voyage code found in schedule',
            'voyage_variations_tried' => $voyageSearchVariations,
            'checked_at' => now()
        ];
    }
    
    protected function getSearchMethod($vesselFound, $voyageFound, $fullFound)
    {
        if ($vesselFound && $voyageFound) {
            return 'vessel_name_and_voyage';
        } elseif ($vesselFound) {
            return 'vessel_name_only';
        } elseif ($voyageFound) {
            return 'voyage_code_only';
        } elseif ($fullFound) {
            return 'full_name_match';
        }
        
        return 'not_found';
    }

    protected function extractETAFromHTML($html, $vesselName, $voyageCode = null)
    {
        // First try: Extract ETA from table row containing the vessel (IMPROVED)
        $tableEta = $this->extractETAFromTable($html, $vesselName, $voyageCode);
        if ($tableEta) {
            return $tableEta;
        }

        // Fallback: Original method for non-table formats
        $datePatterns = [
            '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/', // DD/MM/YYYY HH:MM
            '/(\d{4}-\d{2}-\d{2})\s*(\d{2}:\d{2})/', // YYYY-MM-DD HH:MM
            '/(\d{1,2}-\d{1,2}-\d{4})\s*(\d{1,2}:\d{2})/', // DD-MM-YYYY HH:MM
            '/ETA[:\s]*(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/i',
            '/Estimated[:\s]*(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/i',
        ];

        // Find vessel section in HTML
        $vesselSection = $this->extractVesselSection($html, $vesselName);
        
        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $vesselSection, $matches)) {
                try {
                    $dateStr = $matches[1] . ' ' . ($matches[2] ?? '00:00');
                    $eta = Carbon::parse($dateStr);
                    return $eta->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Extract ETA from HTML table structure (improved for both TIPS and Hutchison)
     */
    protected function extractETAFromTable($html, $vesselName, $voyageCode = null)
    {
        // Method 1: Find all table rows and check each one precisely
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $html, $allRows)) {
            foreach ($allRows[0] as $row) {
                // Check if this row contains our vessel name
                if (stripos($row, $vesselName) === false) {
                    continue;
                }
                
                // Extract all cells from this specific row
                if (preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cellMatches)) {
                    $cells = $cellMatches[1];
                    $cleanCells = [];
                    
                    // Clean up all cells
                    foreach ($cells as $cellIndex => $cell) {
                        $cellText = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML401, 'UTF-8');
                        $cellText = trim(preg_replace('/\s+/', ' ', $cellText));
                        $cleanCells[$cellIndex] = $cellText;
                    }
                    
                    // Validate this is the correct vessel by checking vessel name + voyage code
                    $isCorrectVessel = false;
                    $vesselCellIndex = -1;
                    $voyageCellIndex = -1;
                    
                    // Find vessel name cell
                    foreach ($cleanCells as $cellIndex => $cellText) {
                        if (stripos($cellText, $vesselName) !== false) {
                            $vesselCellIndex = $cellIndex;
                            break;
                        }
                    }
                    
                    // If voyage code provided, validate it's in the same row
                    if ($voyageCode) {
                        // Create voyage variations to try (IMPROVED for spaces)
                        $voyageVariations = [$voyageCode];
                        if (preg_match('/^([A-Z]+)\.?\s*(.+)$/', $voyageCode, $matches)) {
                            $voyageVariations[] = $matches[2]; // Remove prefix like "V. "
                        }
                        $voyageVariations[] = str_replace(['.', ' '], '', $voyageCode); // Remove dots and spaces
                        
                        // Additional handling for space-separated prefixes
                        if (str_contains($voyageCode, ' ')) {
                            $parts = explode(' ', $voyageCode);
                            if (count($parts) >= 2) {
                                $lastPart = end($parts);
                                if (!in_array($lastPart, $voyageVariations)) {
                                    $voyageVariations[] = $lastPart;
                                }
                            }
                        }
                        
                        foreach ($cleanCells as $cellIndex => $cellText) {
                            foreach ($voyageVariations as $variation) {
                                if (stripos($cellText, $variation) !== false) {
                                    $voyageCellIndex = $cellIndex;
                                    $isCorrectVessel = true;
                                    break 2;
                                }
                            }
                        }
                    } else {
                        // If no voyage code, accept any row with vessel name
                        $isCorrectVessel = ($vesselCellIndex >= 0);
                    }
                    
                    if ($isCorrectVessel) {
                        // Extract ETA from this specific row
                        // Prioritize cells that look like ETA (with time format)
                        $bestETA = null;
                        $fallbackETA = null;
                        
                        $datePatterns = [
                            '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/', // DD/MM/YYYY HH:MM
                            '/(\d{1,2}\/\d{1,2}\/\d{4})/',                    // DD/MM/YYYY only
                        ];
                        
                        foreach ($cleanCells as $cellIndex => $cellText) {
                            foreach ($datePatterns as $patternIndex => $pattern) {
                                if (preg_match($pattern, $cellText, $matches)) {
                                    try {
                                        // Handle both full datetime and date-only formats
                                        if (isset($matches[2])) {
                                            $dateStr = $matches[1] . ' ' . $matches[2]; // Full datetime
                                        } else {
                                            $dateStr = $matches[1] . ' 00:00'; // Date only
                                        }
                                        
                                        $eta = Carbon::createFromFormat('d/m/Y H:i', $dateStr);
                                        $formattedETA = $eta->format('Y-m-d H:i:s');
                                        
                                        // For TIPS: ETA should be around cell 6 (estimate column)
                                        // For Hutchison: Could be different structure
                                        if (isset($matches[2])) {
                                            // Prefer dates with specific times
                                            if (!$bestETA) {
                                                $bestETA = $formattedETA;
                                            }
                                        } else {
                                            // Keep as fallback if no better ETA found
                                            if (!$fallbackETA) {
                                                $fallbackETA = $formattedETA;
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                            }
                        }
                        
                        return $bestETA ?: $fallbackETA;
                    }
                }
            }
        }
        
        return null;
    }

    protected function extractVesselSection($html, $vesselName)
    {
        // Find the section of HTML that contains the vessel information
        $pos = stripos($html, $vesselName);
        if ($pos === false) {
            return '';
        }

        // Extract ~500 characters around the vessel name for context
        $start = max(0, $pos - 250);
        $length = 500;
        
        return substr($html, $start, $length);
    }

    protected function findScheduleLinks($html, $baseUrl = '')
    {
        $links = [];
        
        // Common schedule-related link patterns
        $patterns = [
            '/href=["\']([^"\']*schedule[^"\']*)["\']/',
            '/href=["\']([^"\']*vessel[^"\']*)["\']/',
            '/href=["\']([^"\']*berth[^"\']*)["\']/',
            '/href=["\']([^"\']*ship[^"\']*)["\']/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $link) {
                    if (strpos($link, 'http') !== 0 && $baseUrl) {
                        $link = rtrim($baseUrl, '/') . '/' . ltrim($link, '/');
                    }
                    $links[] = $link;
                }
            }
        }

        return array_unique($links);
    }

    protected function displayResult($terminalCode, $result)
    {
        if (!$result['success']) {
            echo "❌ ERROR: {$result['error']}\n";
            return;
        }

        // Display vessel search results
        echo "🔍 Search Results:\n";
        if (isset($result['vessel_name'])) {
            echo "  📍 Vessel Name: " . ($result['vessel_found'] ? "✅ Found" : "❌ Not found") . " ({$result['vessel_name']})\n";
        }
        if (isset($result['voyage_code'])) {
            echo "  🧭 Voyage Code: " . (($result['voyage_found'] ?? false) ? "✅ Found" : "❌ Not found") . " ({$result['voyage_code']})\n";
        }
        if (isset($result['search_method'])) {
            echo "  🎯 Match Method: {$result['search_method']}\n";
        }

        if ($result['vessel_found'] || (isset($result['full_name_found']) && $result['full_name_found'])) {
            echo "✅ VESSEL FOUND!\n";
            
            if ($result['eta']) {
                echo "🕒 ETA: {$result['eta']}\n";
            } else {
                echo "⚠️  ETA: Not found or could not parse\n";
            }
            
            if (!empty($result['raw_data'])) {
                echo "📄 Raw Data Preview:\n";
                echo substr(strip_tags($result['raw_data']), 0, 200) . "...\n";
            }
        } else {
            echo "❌ Vessel not found in schedule\n";
            if (isset($result['message'])) {
                echo "💬 Message: {$result['message']}\n";
            }
        }
        
        echo "🕐 Checked at: {$result['checked_at']}\n";
    }

    /**
     * Get all terminal configurations
     */
    public function getTerminals()
    {
        return $this->terminals;
    }

    /**
     * Get terminal configuration by port code
     */
    public function getTerminalByPortCode($portCode)
    {
        $terminalKey = $this->portToTerminal[strtoupper($portCode)] ?? null;

        if (!$terminalKey) {
            // Try legacy format
            $terminalKey = $this->portToTerminal[$portCode] ?? null;
        }

        if (!$terminalKey) {
            return null;
        }

        return $this->terminals[$terminalKey] ?? null;
    }

    /**
     * Get terminal configuration by terminal key
     */
    public function getTerminalByKey($terminalKey)
    {
        return $this->terminals[$terminalKey] ?? null;
    }

    /**
     * Get all port codes
     */
    public function getPortCodes()
    {
        return array_keys($this->portToTerminal);
    }

    /**
     * Get ports for a specific terminal
     */
    public function getPortsForTerminal($terminalKey)
    {
        $terminal = $this->terminals[$terminalKey] ?? null;
        return $terminal ? $terminal['ports'] : [];
    }

    /**
     * Check if port code is supported
     */
    public function isPortSupported($portCode)
    {
        return isset($this->portToTerminal[strtoupper($portCode)]) ||
               isset($this->portToTerminal[$portCode]);
    }

    /**
     * Extract vessel code from vessel name for ShipmentLink
     * Tries to intelligently guess the vessel code from the vessel name
     */
    private function extractVesselCode($vesselName)
    {
        // Common patterns for extracting vessel codes
        $name = strtoupper(trim($vesselName));

        // For EVER series vessels (EVER BUILD -> BUILD, EVER BASIS -> BASIS)
        if (preg_match('/EVER\s+([A-Z]+)/', $name, $matches)) {
            return $matches[1];
        }

        // For other vessel patterns, try to get the last significant word
        $words = explode(' ', $name);
        $words = array_filter($words, function($word) {
            // Filter out voyage codes and common prefixes
            return !preg_match('/^\d/', $word) && strlen($word) > 2;
        });

        if (count($words) >= 2) {
            return end($words); // Last significant word
        }

        // Fallback to first 4-6 characters if no pattern matches
        return substr($name, 0, min(6, strlen($name)));
    }

    /**
     * Siam Commercial terminal integration using n8n to send LINE messages to port staff
     */
    protected function siam_n8n_line($config)
    {
        try {
            $vesselName = $config['vessel_name'] ?? 'SAMPLE VESSEL';
            $voyageCode = $config['voyage_code'] ?? '';

            \Log::info("Starting Siam Commercial n8n LINE integration", [
                'vessel' => $vesselName,
                'voyage' => $voyageCode
            ]);

            // For now, return a placeholder response until n8n integration is set up
            // In a full implementation, this would trigger an n8n workflow that:
            // 1. Sends LINE message to port staff with vessel details
            // 2. Waits for response with JSON structure
            // 3. Parses and returns the vessel tracking data

            return [
                'success' => true,
                'terminal' => $config['name'],
                'vessel_found' => false, // Will be true when staff responds with vessel data
                'voyage_found' => false,
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'eta' => null,
                'etd' => null,
                'search_method' => 'n8n_line_integration',
                'message' => 'n8n LINE integration not yet configured - placeholder response',
                'details' => 'This integration will send LINE messages to port staff for manual vessel tracking',
                'checked_at' => now()
            ];

        } catch (\Exception $e) {
            \Log::error("Siam Commercial n8n integration error: " . $e->getMessage());

            return [
                'success' => false,
                'terminal' => $config['name'],
                'error' => $e->getMessage(),
                'vessel_name' => $vesselName ?? 'Unknown',
                'voyage_code' => $voyageCode ?? '',
                'search_method' => 'n8n_line_error',
                'checked_at' => now()
            ];
        }
    }

    /**
     * Kerry Logistics terminal integration using HTTP requests
     * Based on implementation from remote branch: add-terminal-Kerry-vessel-BUXMELODY-voyage-230N
     */
    protected function kerry_http_request($config)
    {
        try {
            $vesselName = strtolower($config['vessel_name'] ?? 'buxmelody');
            $voyageCode = strtolower($config['voyage_code'] ?? '230n');

            \Log::info("Starting Kerry Logistics HTTP request", [
                'vessel' => $vesselName,
                'voyage' => $voyageCode
            ]);

            $url = $config['search_url'] ?? 'https://terminaltracking.ksp.kln.com/SearchVesselVisit/List';
            $queryParams = [
                'PARM_VESSELNAME' => $vesselName,
                'PARM_VOY' => $voyageCode,
                'pageNumber' => 'undefined'
            ];
            $fullUrl = $url . '?' . http_build_query($queryParams);

            $response = Http::withHeaders([
                'Host' => 'terminaltracking.ksp.kln.com',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Cache-Control' => 'max-age=0'
            ])
            ->timeout(30)
            ->post($fullUrl);

            if ($response->successful()) {
                $responseBody = $response->body();
                $eta = $this->parseKerryETA($responseBody, $vesselName, $voyageCode);
                $vesselFound = $eta !== null;

                \Log::info("Kerry Logistics request successful", [
                    'vessel_found' => $vesselFound,
                    'eta' => $eta
                ]);

                return [
                    'success' => true,
                    'terminal' => $config['name'],
                    'vessel_name' => $config['vessel_name'] ?? '',
                    'voyage_code' => $config['voyage_code'] ?? '',
                    'vessel_found' => $vesselFound,
                    'voyage_found' => $vesselFound,
                    'search_method' => 'http_request_table_parse',
                    'eta' => $eta,
                    'etd' => null, // Kerry typically only provides ETA
                    'manual_search_url' => $config['url'] ?? '',
                    'checked_at' => now()
                ];

            } else {
                \Log::warning("Kerry Logistics HTTP request failed", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500)
                ]);

                return [
                    'success' => false,
                    'terminal' => $config['name'],
                    'error' => 'HTTP request failed: ' . $response->status(),
                    'vessel_name' => $config['vessel_name'] ?? '',
                    'voyage_code' => $config['voyage_code'] ?? '',
                    'search_method' => 'http_request_failed',
                    'checked_at' => now()
                ];
            }

        } catch (\Exception $e) {
            \Log::error("Kerry Logistics integration error: " . $e->getMessage());

            return [
                'success' => false,
                'terminal' => $config['name'],
                'error' => $e->getMessage(),
                'vessel_name' => $config['vessel_name'] ?? 'Unknown',
                'voyage_code' => $config['voyage_code'] ?? '',
                'search_method' => 'kerry_error',
                'checked_at' => now()
            ];
        }
    }

    /**
     * Parse Kerry Logistics ETA from HTML response
     * Based on implementation from remote branch
     */
    protected function parseKerryETA($html, $vesselName, $voyageCode)
    {
        try {
            \Log::info("Parsing Kerry ETA", [
                'vessel' => $vesselName,
                'voyage' => $voyageCode,
                'html_length' => strlen($html)
            ]);

            // Find all table rows in the response
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows);

            foreach ($rows[1] as $rowContent) {
                // Extract all td cells from this row
                preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowContent, $cells);

                if (count($cells[1]) >= 6) {
                    $cellData = array_map('trim', $cells[1]);

                    $vesselNameInRow = strtoupper(strip_tags($cellData[1]));
                    $voyageInRow = strtoupper(strip_tags($cellData[2])); // I/B Voyage

                    // Check if vessel name matches (flexible matching)
                    $vesselMatches = (
                        stripos($vesselNameInRow, strtoupper($vesselName)) !== false ||
                        stripos(strtoupper($vesselName), str_replace('M.V.', '', $vesselNameInRow)) !== false
                    );

                    // Check if voyage matches
                    $voyageMatches = (
                        stripos($voyageInRow, strtoupper($voyageCode)) !== false ||
                        stripos(strtoupper($voyageCode), $voyageInRow) !== false
                    );

                    if ($vesselMatches && $voyageMatches) {
                        $etaRaw = strip_tags($cellData[5]);
                        $etaRaw = trim($etaRaw);

                        \Log::info("Found matching Kerry vessel", [
                            'vessel_in_row' => $vesselNameInRow,
                            'voyage_in_row' => $voyageInRow,
                            'eta_raw' => $etaRaw
                        ]);

                        // Parse the ETA format: "18/09 16:00"
                        if (preg_match('/(\d{1,2}\/\d{1,2})\s+(\d{1,2}:\d{2})/', $etaRaw, $matches)) {
                            $datePart = $matches[1]; // "18/09"
                            $timePart = $matches[2]; // "16:00"

                            // Convert to proper date format (assuming current year)
                            $currentYear = date('Y');
                            list($day, $month) = explode('/', $datePart);

                            $etaFormatted = sprintf(
                                '%s-%02d-%02d %s:00',
                                $currentYear,
                                intval($month),
                                intval($day),
                                $timePart
                            );

                            \Log::info("Parsed Kerry ETA successfully", [
                                'raw' => $etaRaw,
                                'formatted' => $etaFormatted
                            ]);

                            return $etaFormatted;
                        }
                    }
                }
            }

            \Log::info("No matching vessel found in Kerry response");
            return null;

        } catch (\Exception $e) {
            \Log::error("Kerry ETA parsing error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * JWD Terminal HTTP Request
     */
    protected function jwd_http_request($config)
    {
        try {
            $vesselName = $config['vessel_name'];
            $voyageCode = $config['voyage_code'];
            $apiUrl = $config['api_url'];

            \Log::info("Starting JWD HTTP request for vessel: {$vesselName} voyage: {$voyageCode}");

            // Make HTTP request to JWD API
            $response = Http::timeout(30)->get($apiUrl);

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch JWD schedule data: HTTP " . $response->status());
            }

            $html = $response->body();

            // Parse the HTML table data
            $vesselData = $this->parseJWDScheduleHTML($html, $vesselName, $voyageCode);

            if ($vesselData) {
                \Log::info("JWD HTTP request completed successfully", [
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'eta_found' => !empty($vesselData['eta'])
                ]);

                return [
                    'success' => true,
                    'vessel_found' => true,
                    'voyage_found' => true,
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'eta' => $vesselData['eta'],
                    'berth' => $vesselData['berth'] ?? null,
                    'eta_type' => $vesselData['eta_type'] ?? 'arrival',
                    'terminal' => 'JWD Terminal',
                    'method' => 'jwd_http_request',
                    'checked_at' => now()
                ];
            } else {
                \Log::info("JWD vessel not found", [
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode
                ]);

                return [
                    'success' => true,
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'eta' => null,
                    'terminal' => 'JWD Terminal',
                    'method' => 'jwd_http_request',
                    'checked_at' => now()
                ];
            }

        } catch (\Exception $e) {
            \Log::error("JWD HTTP request error: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'vessel_found' => false,
                'voyage_found' => false,
                'vessel_name' => $config['vessel_name'] ?? '',
                'voyage_code' => $config['voyage_code'] ?? '',
                'eta' => null,
                'terminal' => 'JWD Terminal',
                'method' => 'jwd_http_request',
                'checked_at' => now()
            ];
        }
    }

    /**
     * Parse JWD schedule HTML and extract vessel data
     */
    protected function parseJWDScheduleHTML($html, $vesselName, $voyageCode)
    {
        try {
            // Load HTML into DOMDocument
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $xpath = new \DOMXPath($dom);

            // Find all table rows
            $rows = $xpath->query('//tr');

            foreach ($rows as $row) {
                $cells = $xpath->query('td', $row);

                // Skip if not enough cells (header row or incomplete data)
                if ($cells->length < 6) {
                    continue;
                }

                // Extract cell values based on table structure:
                // No. | VESSEL NAME | VOYAGE (IN/OUT) | ESTIMATE (ARRIVAL/DEPARTURE) | BERTH
                $vesselNameCell = $cells->item(1) ? trim($cells->item(1)->textContent) : '';
                $voyageInCell = $cells->item(2) ? trim($cells->item(2)->textContent) : '';
                $voyageOutCell = $cells->item(3) ? trim($cells->item(3)->textContent) : '';
                $arrivalCell = $cells->item(4) ? trim($cells->item(4)->textContent) : '';
                $departureCell = $cells->item(5) ? trim($cells->item(5)->textContent) : '';
                $berthCell = $cells->item(6) ? trim($cells->item(6)->textContent) : '';

                // Check if this row matches our search criteria
                $vesselMatch = stripos($vesselNameCell, $vesselName) !== false;
                $voyageInMatch = $voyageInCell === $voyageCode;
                $voyageOutMatch = $voyageOutCell === $voyageCode;

                if ($vesselMatch && ($voyageInMatch || $voyageOutMatch)) {
                    // Determine which date to use (arrival or departure) based on which voyage matched
                    $eta = null;
                    $etaType = 'arrival';

                    if ($voyageInMatch && !empty($arrivalCell)) {
                        $eta = $arrivalCell;
                        $etaType = 'arrival';
                    } elseif ($voyageOutMatch && !empty($departureCell)) {
                        $eta = $departureCell;
                        $etaType = 'departure';
                    }

                    // Format ETA to standard format
                    if ($eta) {
                        $eta = $this->formatJWDDateTime($eta);
                    }

                    return [
                        'vessel_name' => $vesselNameCell,
                        'voyage_in' => $voyageInCell,
                        'voyage_out' => $voyageOutCell,
                        'matched_voyage' => $voyageInMatch ? $voyageInCell : $voyageOutCell,
                        'eta' => $eta,
                        'eta_type' => $etaType,
                        'berth' => $berthCell,
                        'raw_data' => [
                            'vessel' => $vesselNameCell,
                            'voyage_in' => $voyageInCell,
                            'voyage_out' => $voyageOutCell,
                            'arrival' => $arrivalCell,
                            'departure' => $departureCell,
                            'berth' => $berthCell
                        ]
                    ];
                }
            }

            return null; // Vessel not found

        } catch (\Exception $e) {
            \Log::error("JWD HTML parsing error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Format JWD datetime to standard format
     */
    protected function formatJWDDateTime($dateTimeString)
    {
        try {
            // JWD format: "30 Sep 2025 05:00:00"
            $pattern = '/(\d{1,2})\s+(\w{3})\s+(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2})/';
            if (preg_match($pattern, $dateTimeString, $matches)) {
                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $month = $matches[2];
                $year = $matches[3];
                $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
                $minute = $matches[5];
                $second = $matches[6];

                $monthMap = [
                    'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04',
                    'May' => '05', 'Jun' => '06', 'Jul' => '07', 'Aug' => '08',
                    'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12'
                ];

                if (isset($monthMap[$month])) {
                    return "{$year}-{$monthMap[$month]}-{$day} {$hour}:{$minute}:{$second}";
                }
            }

            // If format doesn't match, return original
            return $dateTimeString;

        } catch (\Exception $e) {
            \Log::warning("JWD datetime formatting error: " . $e->getMessage());
            return $dateTimeString;
        }
    }

    /**
     * JWD Terminal Browser Automation
     */
    protected function jwd_browser($config)
    {
        try {
            $vesselName = $config['vessel_name'];
            $voyageCode = $config['voyage_code'];

            \Log::info("Starting JWD browser automation for vessel: {$vesselName} voyage: {$voyageCode}");

            $browserService = new BrowserAutomationService();
            $result = $browserService->scrapeJWDVesselSchedule($vesselName, $voyageCode);

            if ($result && isset($result['success']) && $result['success']) {
                \Log::info("JWD browser automation completed successfully", [
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'vessel_found' => $result['vessel_found'] ?? false,
                    'eta_found' => !empty($result['eta'])
                ]);

                // Add terminal and vessel info for consistency
                $result['terminal'] = 'JWD Terminal';
                $result['vessel_name'] = $vesselName;
                $result['voyage_code'] = $voyageCode;
                $result['method'] = 'jwd_browser';
                $result['checked_at'] = now();

                return $result;
            } else {
                \Log::error("JWD browser automation failed", [
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'result' => $result
                ]);

                return [
                    'success' => false,
                    'error' => 'JWD browser automation failed',
                    'terminal' => 'JWD Terminal',
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'eta' => null,
                    'checked_at' => now()
                ];
            }

        } catch (\Exception $e) {
            \Log::error("JWD browser automation exception: " . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'terminal' => 'JWD Terminal',
                'vessel_name' => $config['vessel_name'] ?? '',
                'voyage_code' => $config['voyage_code'] ?? '',
                'vessel_found' => false,
                'voyage_found' => false,
                'eta' => null,
                'checked_at' => now()
            ];
        }
    }
}
