<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\VesselNameParser;

class VesselTrackingService
{
    protected $terminals = [
        'C1C2' => [
            'name' => 'Hutchison Ports',
            'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17:6927160550678:::::',
            'vessel_full' => 'WAN HAI 517 S093',
            'method' => 'hutchison_browser'
        ],
        'B4' => [
            'name' => 'TIPS',
            'url' => 'https://www.tips.co.th/container/shipSched/List',
            'vessel_full' => 'SRI SUREE V.25080S',
            'method' => 'tips'
        ],
        'B5C3' => [
            'name' => 'LCIT',
            'url' => 'https://www.lcit.com/home',
            'vessel_full' => 'ASL QINGDAO V.2508S',
            'method' => 'lcit'
        ],
        'B3' => [
            'name' => 'ESCO',
            'url' => 'https://service.esco.co.th/BerthSchedule',
            'vessel_full' => 'CUL NANSHA V. 2528S',
            'method' => 'esco'
        ],
        'A0B1' => [
            'name' => 'LCB1',
            'url' => 'https://www.lcb1.com/BerthSchedule',
            'vessel_full' => 'MARSA PRIDE 528S',
            'method' => 'lcb1'
        ],
        'B2' => [
            'name' => 'ShipmentLink',
            'url' => 'https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp',
            'vessel_full' => 'EVER BUILD 0815-079S',
            'method' => 'shipmentlink_browser'
        ],
        'KERRY' => [
            'name' => 'Kerry Logistics',
            'url' => 'https://terminaltracking.ksp.kln.com/SearchVesselVisit', // Manual search URL - not used in automated HTTP requests
            'vessel_full' => 'BUXMELODY 230N',
            'method' => 'kerry_http_request'
        ]
    ];

    public function __construct()
    {
        // Auto-parse vessel names in terminal config
        foreach ($this->terminals as $code => &$config) {
            $parsed = VesselNameParser::parse($config['vessel_full']);
            $config['vessel_name'] = $parsed['vessel_name'];
            $config['voyage_code'] = $parsed['voyage_code'];
        }
    }

    /**
     * Check ETA for a specific vessel by parsing the vessel name automatically
     */
    public function checkVesselETAByName($vesselFullName, $terminalCode = null)
    {
        $parsed = VesselNameParser::parse($vesselFullName);
        
        // If no terminal specified, try all terminals
        if (!$terminalCode) {
            $results = [];
            foreach ($this->terminals as $code => $config) {
                $results[$code] = $this->checkVesselETAWithParsedName($parsed, $code);
            }
            return $results;
        }
        
        return $this->checkVesselETAWithParsedName($parsed, $terminalCode);
    }

    /**
     * Check ETA using parsed vessel name components
     */
    protected function checkVesselETAWithParsedName($parsedVessel, $terminalCode)
    {
        $config = $this->terminals[$terminalCode] ?? null;
        if (!$config) {
            throw new \Exception("Unknown terminal code: {$terminalCode}");
        }

        // Override config with the vessel we're actually searching for
        $config['vessel_full'] = $parsedVessel['full_name'];
        $config['vessel_name'] = $parsedVessel['vessel_name'];
        $config['voyage_code'] = $parsedVessel['voyage_code'];

        try {
            $method = $config['method'];
            return $this->$method($config);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'terminal' => $config['name'],
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
        
        foreach ($this->terminals as $terminalCode => $config) {
            echo "🚢 Testing Terminal {$terminalCode} ({$config['name']}) - Vessel: {$config['vessel_name']} + Voyage: {$config['voyage_code']}\n";
            echo "📍 URL: {$config['url']}\n";
            
            $result = $this->checkVesselETA($terminalCode, $config);
            $results[$terminalCode] = $result;
            
            $this->displayResult($terminalCode, $result);
            echo "─────────────────────────────────────────────────────────\n";
            
            // Be respectful - wait between requests
            sleep(2);
        }
        
        return $results;
    }

    public function checkVesselETA($terminalCode, $config)
    {
        try {
            $method = $config['method'];
            return $this->$method($config);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'terminal' => $config['name'],
                'vessel_full' => $config['vessel_full'],
                'vessel_name' => $config['vessel_name'] ?? '',
                'voyage_code' => $config['voyage_code'] ?? '',
                'checked_at' => now()
            ];
        }
    }

    protected function hutchison_browser($config)
    {
        try {
            $vesselName = $config['vessel_name'];
            \Log::info("Starting Hutchison Ports browser automation for vessel: {$vesselName}");

            $browserAutomationPath = base_path('browser-automation');

            // Use proc_open to separate stdout (JSON) from stderr (logs)
            $command = "cd {$browserAutomationPath} && timeout 90 node hutchison-wrapper.js '{$vesselName}'";

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
                    \Log::info("Hutchison browser automation logs: " . $logOutput);
                }

                // Check if we have valid JSON output regardless of return code
                if (!empty($jsonOutput)) {
                    $result = json_decode($jsonOutput, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($result['success'])) {
                        if ($result['success']) {
                            \Log::info("Hutchison browser automation completed successfully", [
                                'vessel_name' => $vesselName,
                                'result' => $result
                            ]);
                        } else {
                            \Log::info("Hutchison browser automation completed - vessel not found", [
                                'vessel_name' => $vesselName,
                                'result' => $result
                            ]);
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
            } else {
                throw new \Exception("Failed to start browser automation process");
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

    protected function lcit($config)
    {
        // LCIT - Home page, may need to navigate to vessel schedule
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])
            ->get($config['url']);

        if (!$response->successful()) {
            throw new \Exception("HTTP Error: " . $response->status());
        }

        // Try to find vessel schedule link
        $html = $response->body();
        $scheduleLinks = $this->findScheduleLinks($html);
        
        if (!empty($scheduleLinks)) {
            // Try the first schedule link found
            $scheduleUrl = $scheduleLinks[0];
            $scheduleResponse = Http::timeout(30)->get($scheduleUrl);
            
            if ($scheduleResponse->successful()) {
                return $this->parseVesselData($scheduleResponse->body(), $config);
            }
        }

        return $this->parseVesselData($html, $config);
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
            
            // Call the browser automation wrapper
            $browserAutomationPath = base_path('browser-automation');
            
            // FIXED: Use proc_open to separate stdout (JSON) from stderr (logs)
            $command = "cd {$browserAutomationPath} && timeout 60 node laravel-wrapper.js '{$vesselName}'";
            
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
                    \Log::info("Browser automation logs:", ['logs' => $logOutput]);
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
            $vesselName = $config['vessel_name'];
            \Log::info("Starting ShipmentLink browser automation for vessel: {$vesselName}");
            
            $browserAutomationPath = base_path('browser-automation');
            
            // Use proc_open to separate stdout (JSON) from stderr (logs)
            $command = "cd {$browserAutomationPath} && timeout 60 node shipmentlink-wrapper.js '{$vesselName}'";
            
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

    public function getTerminals()
    {
        return $this->terminals;
    }

    public function getTerminalByCode($code)
    {
        return $this->terminals[$code] ?? null;
    }

    protected function kerry_http_request($config)
    {
        try {
            $vesselName = strtolower($config['vessel_name'] ?? 'buxmelody');
            $voyageCode = strtolower($config['voyage_code'] ?? '230n');
            
            \Log::info("Starting Kerry Logistics HTTP request for vessel: {$vesselName}, voyage: {$voyageCode}");

            // Build the URL with query parameters
            $url = "https://terminaltracking.ksp.kln.com/SearchVesselVisit/List";
            $queryParams = [
                'PARM_VESSELNAME' => $vesselName,
                'PARM_VOY' => $voyageCode,
                'pageNumber' => 'undefined'
            ];
            $fullUrl = $url . '?' . http_build_query($queryParams);

            // Make the POST request with exact headers
            $response = Http::withHeaders([
                'Host' => 'terminaltracking.ksp.kln.com',
                'Accept-Encoding' => 'gzip, deflate, br, zstd',
                'Accept-Language' => 'en-GB,en;q=0.9,th-TH;q=0.8,th;q=0.7,en-US;q=0.6',
                'Cookie' => 'UserToken=0xxx0xxxxx0xx0xxxx00; SearchVessel=Search',
                'Origin' => 'https://terminaltracking.ksp.kln.com',
                'Priority' => 'u=1, i',
                'Referer' => 'https://terminaltracking.ksp.kln.com/SearchVesselVisit',
                'Sec-CH-UA' => '"Chromium";v="140", "Not=A?Brand";v="24", "Google Chrome";v="140"',
                'Sec-CH-UA-Mobile' => '?0',
                'Sec-CH-UA-Platform' => '"Windows"',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-origin',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
                'X-Requested-With' => 'XMLHttpRequest'
            ])
            ->timeout(30)
            ->post($fullUrl); // Empty body as specified

            if ($response->successful()) {
                $responseBody = $response->body();
                $responseSize = strlen($responseBody);

                // Parse ETA from HTML table
                $eta = $this->parseKerryETA($responseBody, $vesselName, $voyageCode);
                $vesselFound = $eta !== null; // If we found ETA, vessel exists
                
                \Log::info("Kerry HTTP request successful", [
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'response_size' => $responseSize,
                    'status_code' => $response->status(),
                    'eta_found' => $eta,
                    'vessel_found' => $vesselFound
                ]);

                return [
                    'success' => true,
                    'terminal' => 'Kerry Logistics',
                    'vessel_name' => $config['vessel_name'] ?? '',
                    'voyage_code' => $config['voyage_code'] ?? '',
                    'vessel_found' => $vesselFound,
                    'voyage_found' => $vesselFound, // Same as vessel found for Kerry
                    'search_method' => 'http_request_table_parse',
                    'eta' => $eta,
                    'raw_data' => $responseBody,
                    'html_size' => $responseSize,
                    'status_code' => $response->status(),
                    'checked_at' => now()->format('Y-m-d H:i:s'),
                    'message' => $vesselFound ? 'Vessel found with ETA data' : 'Vessel not found in schedule'
                ];
            } else {
                \Log::error("Kerry HTTP request failed", [
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'terminal' => 'Kerry Logistics',
                    'vessel_name' => $config['vessel_name'] ?? '',
                    'voyage_code' => $config['voyage_code'] ?? '',
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'search_method' => 'http_request',
                    'error' => "HTTP request failed with status: {$response->status()}",
                    'status_code' => $response->status(),
                    'checked_at' => now()->format('Y-m-d H:i:s')
                ];
            }

        } catch (\Exception $e) {
            \Log::error("Kerry HTTP request exception", [
                'vessel_name' => $vesselName ?? 'unknown',
                'voyage_code' => $voyageCode ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'terminal' => 'Kerry Logistics',
                'vessel_name' => $config['vessel_name'] ?? '',
                'voyage_code' => $config['voyage_code'] ?? '',
                'vessel_found' => false,
                'voyage_found' => false,
                'search_method' => 'http_request',
                'error' => 'HTTP request exception: ' . $e->getMessage(),
                'checked_at' => now()->format('Y-m-d H:i:s')
            ];
        }
    }

    protected function parseKerryETA($html, $vesselName, $voyageCode)
    {
        try {
            // Find all table rows in the response
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows);
            
            foreach ($rows[1] as $rowContent) {
                // Extract all td cells from this row
                preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowContent, $cells);
                
                if (count($cells[1]) >= 6) {
                    $cellData = array_map('trim', $cells[1]);
                    
                    // Check if this row contains our vessel
                    // Cell 0: Vessel code (BMY)
                    // Cell 1: Vessel name (M.V.BUXMELODY) 
                    // Cell 2: I/B Voyage (230N)
                    // Cell 3: O/B Voyage (230N)
                    // Cell 4: Phase
                    // Cell 5: ETA <- This is what we want
                    
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
                        // Extract ETA from the 6th cell (index 5)
                        $etaRaw = strip_tags($cellData[5]);
                        $etaRaw = trim($etaRaw);
                        
                        \Log::info("Kerry ETA parsing", [
                            'vessel_name_in_row' => $vesselNameInRow,
                            'voyage_in_row' => $voyageInRow,
                            'eta_raw' => $etaRaw,
                            'search_vessel' => $vesselName,
                            'search_voyage' => $voyageCode
                        ]);
                        
                        // Parse the ETA format: "18/09 16:00" 
                        if (preg_match('/(\d{1,2}\/\d{1,2})\s+(\d{1,2}:\d{2})/', $etaRaw, $matches)) {
                            $datePart = $matches[1]; // "18/09"
                            $timePart = $matches[2]; // "16:00"
                            
                            // Assume current year if not specified
                            $currentYear = date('Y');
                            $fullDate = $datePart . '/' . $currentYear . ' ' . $timePart;
                            
                            try {
                                // Parse DD/MM/YYYY HH:MM format
                                $eta = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $fullDate);
                                return $eta->format('Y-m-d H:i:s');
                            } catch (\Exception $e) {
                                \Log::warning("Kerry ETA date parsing failed", [
                                    'raw_eta' => $etaRaw,
                                    'full_date' => $fullDate,
                                    'error' => $e->getMessage()
                                ]);
                                // Return raw ETA if parsing fails
                                return $etaRaw;
                            }
                        } else {
                            // Return raw ETA if format doesn't match expected pattern
                            return $etaRaw ?: null;
                        }
                    }
                }
            }
            
            // No matching vessel found
            \Log::info("Kerry vessel not found in response", [
                'search_vessel' => $vesselName,
                'search_voyage' => $voyageCode,
                'rows_found' => count($rows[1])
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            \Log::error("Kerry ETA parsing exception", [
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
