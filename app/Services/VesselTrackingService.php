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
            'method' => 'hutchison'
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
            'vessel_full' => 'MARSA PRIDE V.528S',
            'method' => 'lcb1'
        ],
        'B2' => [
            'name' => 'ECTT',
            'url' => 'https://www.ectt.co.th/cookie-policy/',
            'vessel_full' => 'EVER BUILD V.0794-074S',
            'method' => 'ectt'
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

    protected function hutchison($config)
    {
        // Hutchison Ports - This looks like an Oracle APEX application
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive'
            ])
            ->get($config['url']);

        if (!$response->successful()) {
            throw new \Exception("HTTP Error: " . $response->status());
        }

        return $this->parseVesselData($response->body(), $config);
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
        // LCB1 - Berth Schedule
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
}
