<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\VesselNameParser;
use App\Services\BrowserAutomationService;
use App\Models\VesselSchedule;

class VesselTrackingService
{
    /**
     * Port to terminal mapping
     * Multiple ports can use the same terminal/scraper
     */
    protected $portToTerminal = [
        // Hutchison Ports (berths: C1C2, D1, D2, A2, A3)
        'C1' => 'hutchison',   // Legacy — actual berth is C1C2
        'C2' => 'hutchison',   // Legacy — actual berth is C1C2
        'C1C2' => 'hutchison',
        'D1' => 'hutchison',
        'D2' => 'hutchison',
        'A2' => 'hutchison',
        'A3' => 'hutchison',

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

        // Siam Commercial
        'SIAM' => 'siam',

        // Kerry Logistics
        'KERRY' => 'kerry',
        'KLN' => 'kerry',

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
            'ports' => ['C1', 'C2', 'C1C2', 'D1', 'D2', 'A2', 'A3']
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
            'ports' => ['A0', 'B1', 'A0B1', 'A3', 'D1']
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
            'ports' => ['KERRY', 'KLN']
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
        \Log::debug("VesselTrackingService parsing vessel name", [
            'input' => $vesselFullName,
            'length' => strlen($vesselFullName),
            'bytes' => bin2hex($vesselFullName)
        ]);

        $parsed = VesselNameParser::parse($vesselFullName);

        \Log::debug("VesselNameParser result", [
            'vessel_name' => $parsed['vessel_name'],
            'voyage_code' => $parsed['voyage_code'],
            'parsing_method' => $parsed['parsing_method']
        ]);

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
        // Normalize vessel name and voyage before any lookup
        $parsedVessel['vessel_name'] = preg_replace('/\s+/', ' ', trim($parsedVessel['vessel_name']));
        $parsedVessel['voyage_code'] = trim(preg_replace('/^V\.?\s*/i', '', trim($parsedVessel['voyage_code'] ?? '')));

        // OPTIMIZATION: Check local database first (from daily scrapes)
        $dbSchedule = VesselSchedule::findVessel($parsedVessel['vessel_name'], $portCode, $parsedVessel['voyage_code']);

        if ($dbSchedule) {
            Log::info('Vessel schedule found in database (instant lookup)', [
                'vessel' => $parsedVessel['vessel_name'],
                'voyage' => $parsedVessel['voyage_code'],
                'port' => $portCode,
                'eta' => $dbSchedule->eta,
                'source' => $dbSchedule->source,
                'age_hours' => $dbSchedule->scraped_at->diffInHours(now())
            ]);

            // Check if voyage code matches (if voyage code was provided)
            $voyageMatches = !$parsedVessel['voyage_code'] ||
                             strcasecmp($dbSchedule->voyage_code, $parsedVessel['voyage_code']) === 0;

            return [
                'success' => true,
                'vessel_found' => true,
                'voyage_found' => $voyageMatches,
                'vessel_name' => $dbSchedule->vessel_name,
                'voyage_code' => $dbSchedule->voyage_code,
                'port_terminal' => $dbSchedule->port_terminal,
                'berth' => $dbSchedule->berth,
                'eta' => $dbSchedule->eta->format('Y-m-d H:i:s'),
                'etd' => $dbSchedule->etd?->format('Y-m-d H:i:s'),
                'cutoff' => $dbSchedule->cutoff?->format('Y-m-d H:i:s'),
                'opengate' => $dbSchedule->opengate?->format('Y-m-d H:i:s'),
                'source' => $dbSchedule->source . '_db_cached',
                'scraped_at' => $dbSchedule->scraped_at->format('Y-m-d H:i:s'),
                'from_database' => true,
            ];
        }

        // Not in database - fall back to live scraping
        Log::info('Vessel not in database, falling back to live scraping', [
            'vessel' => $parsedVessel['vessel_name'],
            'port' => $portCode
        ]);

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
        $vesselName = $config['vessel_name'] ?? '';
        $voyageCode = $config['voyage_code'] ?? '';

        \Log::info("Hutchison check for vessel: {$vesselName}, voyage: {$voyageCode}");

        try {
            $command = sprintf(
                'cd %s && timeout 60s node scrapers/hutchison-full-schedule-scraper.js --vessel %s --voyage %s',
                escapeshellarg(base_path('browser-automation')),
                escapeshellarg($vesselName),
                escapeshellarg($voyageCode ?: '')
            );

            $descriptors = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];

            $process = proc_open($command, $descriptors, $pipes);

            if (is_resource($process)) {
                fclose($pipes[0]);

                $jsonOutput = stream_get_contents($pipes[1]);
                $logOutput = stream_get_contents($pipes[2]);

                fclose($pipes[1]);
                fclose($pipes[2]);

                $returnCode = proc_close($process);

                if (!empty($logOutput)) {
                    \Log::info("Hutchison scraper logs:", ['logs' => $logOutput]);
                }

                if (!$jsonOutput) {
                    \Log::warning("Hutchison scraper failed: no JSON output (exit code: {$returnCode})");

                    return [
                        'success' => true,
                        'terminal' => $config['name'],
                        'vessel_found' => false,
                        'voyage_found' => false,
                        'vessel_name' => $vesselName,
                        'voyage_code' => $voyageCode,
                        'eta' => null,
                        'etd' => null,
                        'search_method' => 'hutchison_timeout_fallback',
                        'message' => 'Hutchison terminal not accessible',
                        'no_data_reason' => 'Terminal website experiencing connectivity issues',
                        'checked_at' => now()
                    ];
                }
            } else {
                throw new \Exception("Failed to start Hutchison scraper process");
            }

            $result = json_decode(trim($jsonOutput), true);

            if (!$result) {
                throw new \Exception("Invalid JSON from Hutchison scraper: " . substr($jsonOutput, 0, 200));
            }

            if (!$result['success']) {
                throw new \Exception("Hutchison scraper error: " . ($result['error'] ?? 'Unknown error'));
            }

            // Handle vessel_found: false
            if (isset($result['vessel_found']) && $result['vessel_found'] === false) {
                \Log::info("Hutchison terminal accessible but {$vesselName} not found in current schedule");

                return [
                    'success' => true,
                    'terminal' => $config['name'],
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'eta' => null,
                    'etd' => null,
                    'search_method' => 'hutchison_not_found',
                    'message' => 'Vessel not in current Hutchison schedule',
                    'no_data_reason' => 'The terminal was accessible, but the specified vessel was not found in the current schedule.',
                    'checked_at' => now()
                ];
            }

            // Vessel found
            return [
                'success' => true,
                'terminal' => $config['name'],
                'vessel_found' => true,
                'voyage_found' => true,
                'vessel_name' => $result['vessel_name'] ?? $vesselName,
                'voyage_code' => $result['voyage_code'] ?? $voyageCode,
                'eta' => $result['eta'] ?? null,
                'etd' => $result['etd'] ?? null,
                'berth' => $result['berth'] ?? null,
                'cutoff' => $result['cutoff'] ?? null,
                'raw_data' => $result['raw_data'] ?? null,
                'search_method' => 'hutchison_scraper',
                'checked_at' => now()
            ];

        } catch (\Exception $e) {
            \Log::error("Hutchison scraper exception", [
                'error' => $e->getMessage(),
                'vessel' => $vesselName,
                'voyage' => $voyageCode
            ]);

            return [
                'success' => false,
                'terminal' => 'Hutchison Ports',
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'vessel_found' => false,
                'voyage_found' => false,
                'eta' => null,
                'error' => $e->getMessage(),
                'search_method' => 'hutchison_scraper_error',
                'checked_at' => now()
            ];
        }
    }


    protected function tips_browser($config)
    {
        $vesselName = $config['vessel_name'] ?? 'SRI SUREE';
        $voyageCode = $config['voyage_code'] ?? '';

        \Log::info("TIPS check for vessel: {$vesselName}, voyage: {$voyageCode}");

        try {
            // Use the unified TIPS full-schedule scraper in single mode
            $command = sprintf(
                'cd %s && timeout 30s node scrapers/tips-full-schedule-scraper.js --vessel %s --voyage %s',
                escapeshellarg(base_path('browser-automation')),
                escapeshellarg($vesselName),
                escapeshellarg($voyageCode ?: '')
            );

            $descriptors = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];

            $process = proc_open($command, $descriptors, $pipes);

            if (is_resource($process)) {
                fclose($pipes[0]);

                $jsonOutput = stream_get_contents($pipes[1]);
                $logOutput = stream_get_contents($pipes[2]);

                fclose($pipes[1]);
                fclose($pipes[2]);

                $returnCode = proc_close($process);

                if (!empty($logOutput)) {
                    \Log::info("TIPS scraper logs:", ['logs' => $logOutput]);
                }

                if (!$jsonOutput) {
                    \Log::warning("TIPS scraper failed: no JSON output (exit code: {$returnCode})");

                    return [
                        'success' => true,
                        'terminal' => $config['name'],
                        'vessel_found' => false,
                        'voyage_found' => false,
                        'vessel_name' => $vesselName,
                        'voyage_code' => $voyageCode,
                        'eta' => null,
                        'etd' => null,
                        'search_method' => 'tips_timeout_fallback',
                        'message' => 'TIPS terminal not accessible',
                        'no_data_reason' => 'Terminal website experiencing connectivity issues',
                        'checked_at' => now()
                    ];
                }
            } else {
                throw new \Exception("Failed to start TIPS scraper process");
            }

            $result = json_decode(trim($jsonOutput), true);

            if (!$result) {
                throw new \Exception("Invalid JSON from TIPS scraper: " . substr($jsonOutput, 0, 200));
            }

            if (!$result['success']) {
                throw new \Exception("TIPS scraper error: " . ($result['error'] ?? 'Unknown error'));
            }

            // Handle vessel_found: false
            if (isset($result['vessel_found']) && $result['vessel_found'] === false) {
                \Log::info("TIPS terminal accessible but {$vesselName} not found in current schedule");

                return [
                    'success' => true,
                    'terminal' => $config['name'],
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'eta' => null,
                    'etd' => null,
                    'search_method' => 'tips_not_found',
                    'message' => 'Vessel not in current TIPS schedule',
                    'no_data_reason' => 'The terminal was accessible, but the specified vessel was not found in the current schedule.',
                    'checked_at' => now()
                ];
            }

            // Vessel found
            return [
                'success' => true,
                'terminal' => $config['name'],
                'vessel_found' => true,
                'voyage_found' => true,
                'vessel_name' => $result['vessel_name'] ?? $vesselName,
                'voyage_code' => $result['voyage_code'] ?? $voyageCode,
                'eta' => $result['eta'] ?? null,
                'etd' => $result['etd'] ?? null,
                'berth' => $result['berth'] ?? 'B4',
                'cutoff' => $result['cutoff'] ?? null,
                'raw_data' => $result['raw_data'] ?? null,
                'search_method' => 'tips_scraper',
                'checked_at' => now()
            ];

        } catch (\Exception $e) {
            \Log::error("TIPS scraper exception", [
                'error' => $e->getMessage(),
                'vessel' => $vesselName,
                'voyage' => $voyageCode
            ]);

            return [
                'success' => false,
                'terminal' => 'TIPS',
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'vessel_found' => false,
                'voyage_found' => false,
                'eta' => null,
                'error' => $e->getMessage(),
                'search_method' => 'tips_scraper_error',
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
            // Use the unified LCIT full-schedule scraper in single mode
            $command = sprintf(
                'cd %s && timeout 120s node scrapers/lcit-full-schedule-scraper.js --vessel %s --voyage %s',
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

            // Handle vessel_found: false from unified scraper
            if (isset($result['vessel_found']) && $result['vessel_found'] === false) {
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
        $vesselName = $config['vessel_name'] ?? '';
        $voyageCode = $config['voyage_code'] ?? '';

        \Log::info("ESCO check for vessel: {$vesselName}, voyage: {$voyageCode}");

        try {
            // Use the unified ESCO full-schedule scraper in single mode
            $command = sprintf(
                'cd %s && timeout 30s node scrapers/esco-full-schedule-scraper.js --vessel %s --voyage %s',
                escapeshellarg(base_path('browser-automation')),
                escapeshellarg($vesselName),
                escapeshellarg($voyageCode ?: '')
            );

            $descriptors = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];

            $process = proc_open($command, $descriptors, $pipes);

            if (is_resource($process)) {
                fclose($pipes[0]);

                $jsonOutput = stream_get_contents($pipes[1]);
                $logOutput = stream_get_contents($pipes[2]);

                fclose($pipes[1]);
                fclose($pipes[2]);

                $returnCode = proc_close($process);

                if (!empty($logOutput)) {
                    \Log::info("ESCO scraper logs:", ['logs' => $logOutput]);
                }

                if (!$jsonOutput) {
                    \Log::warning("ESCO scraper failed: no JSON output (exit code: {$returnCode})");

                    return [
                        'success' => true,
                        'terminal' => $config['name'],
                        'vessel_found' => false,
                        'voyage_found' => false,
                        'vessel_name' => $vesselName,
                        'voyage_code' => $voyageCode,
                        'eta' => null,
                        'etd' => null,
                        'search_method' => 'esco_timeout_fallback',
                        'message' => 'ESCO terminal not accessible',
                        'no_data_reason' => 'Terminal website experiencing connectivity issues',
                        'checked_at' => now()
                    ];
                }
            } else {
                throw new \Exception("Failed to start ESCO scraper process");
            }

            $result = json_decode(trim($jsonOutput), true);

            if (!$result) {
                throw new \Exception("Invalid JSON from ESCO scraper: " . substr($jsonOutput, 0, 200));
            }

            if (!$result['success']) {
                throw new \Exception("ESCO scraper error: " . ($result['error'] ?? 'Unknown error'));
            }

            // Handle vessel_found: false
            if (isset($result['vessel_found']) && $result['vessel_found'] === false) {
                \Log::info("ESCO terminal accessible but {$vesselName} not found in current schedule");

                return [
                    'success' => true,
                    'terminal' => $config['name'],
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'eta' => null,
                    'etd' => null,
                    'search_method' => 'esco_not_found',
                    'message' => 'Vessel not found in ESCO schedule',
                    'no_data_reason' => 'Vessel not in current terminal schedule',
                    'checked_at' => now()
                ];
            }

            // Success - vessel found
            \Log::info("ESCO successful check for {$vesselName}", ['result' => $result]);

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
                'search_method' => 'esco_scraper',
                'raw_data' => $result['raw_data'] ?? null,
                'checked_at' => now()
            ];

        } catch (\Exception $e) {
            \Log::error("ESCO terminal error: " . $e->getMessage());

            return [
                'success' => false,
                'terminal' => $config['name'],
                'error' => $e->getMessage(),
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'search_method' => 'esco_error',
                'checked_at' => now()
            ];
        }
    }

    protected function lcb1($config)
    {
        // LCB1 - Use Browser Automation (required for dynamic content)
        try {
            $vesselName = $config['vessel_name'] ?? 'MARSA PRIDE';
            $voyageCode = $config['voyage_code'] ?? '';

            // Use the new BrowserAutomationService
            $browserAutomationPath = base_path('browser-automation');
            $scriptPath = $browserAutomationPath . '/laravel-wrapper.js';

            // Pass vessel name and voyage code to scraper
            $args = [$vesselName];
            if (!empty($voyageCode)) {
                $args[] = $voyageCode;
            }

            $result = BrowserAutomationService::runNodeScript($scriptPath, $args, 60);

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
                'port_terminal' => $result['port_terminal'] ?? null,  // Specific terminal (A0, B1, etc.)
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

            // Build search string with voyage if available
            $searchString = $voyageCode ? "{$vesselName} {$voyageCode}" : $vesselName;

            \Log::info("Starting ShipmentLink HTTPS scraper", [
                'vessel' => $vesselName,
                'voyage' => $voyageCode,
                'search_string' => $searchString
            ]);

            $browserAutomationPath = base_path('browser-automation');

            // Use the new HTTPS-based scraper (much faster than Puppeteer)
            $command = sprintf(
                "cd %s && timeout 30 node scrapers/shipmentlink-https-scraper.js %s",
                escapeshellarg($browserAutomationPath),
                escapeshellarg($searchString)
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
                'Cache-Control' => 'max-age=0',
                'Cookie' => 'UserToken=00000111112222233333'
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
