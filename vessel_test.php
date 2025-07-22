<?php

// Vessel Tracking Test Script
// Run this directly: php vessel_test.php

require_once 'vendor/autoload.php';

class VesselTrackingTest
{
    private $terminals = [
        'C1C2' => [
            'name' => 'Hutchison Ports',
            'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17:6927160550678:::::',
            'vessel_full' => 'WAN HAI 517 S093',
            'vessel_name' => 'WAN HAI 517',
            'voyage_code' => 'S093',
        ],
        'B4' => [
            'name' => 'TIPS',
            'url' => 'https://www.tips.co.th/container/shipSched/List',
            'vessel_full' => 'SRI SUREE V.25080S',
            'vessel_name' => 'SRI SUREE',
            'voyage_code' => 'V.25080S',
        ],
        'B5C3' => [
            'name' => 'LCIT',
            'url' => 'https://www.lcit.com/home',
            'vessel_full' => 'ASL QINGDAO V.2508S',
            'vessel_name' => 'ASL QINGDAO',
            'voyage_code' => 'V.2508S',
        ],
        'B3' => [
            'name' => 'ESCO',
            'url' => 'https://service.esco.co.th/BerthSchedule',
            'vessel_full' => 'CUL NANSHA V. 2528S',
            'vessel_name' => 'CUL NANSHA',
            'voyage_code' => 'V. 2528S',
        ],
        'A0B1' => [
            'name' => 'LCB1',
            'url' => 'https://www.lcb1.com/BerthSchedule',
            'vessel_full' => 'MARSA PRIDE V.528S',
            'vessel_name' => 'MARSA PRIDE',
            'voyage_code' => 'V.528S',
        ],
        'B2' => [
            'name' => 'ECTT',
            'url' => 'https://www.ectt.co.th/cookie-policy/',
            'vessel_full' => 'EVER BUILD V.0794-074S',
            'vessel_name' => 'EVER BUILD',
            'voyage_code' => 'V.0794-074S',
        ]
    ];

    public function testAllTerminals()
    {
        echo "🚢 CS Shipping LCB - Vessel Tracking Test\n";
        echo "═══════════════════════════════════════════\n\n";
        
        $results = [];
        
        foreach ($this->terminals as $terminalCode => $config) {
            echo "🚢 Testing Terminal {$terminalCode} ({$config['name']})\n";
            echo "📍 Vessel: {$config['vessel_name']} + Voyage: {$config['voyage_code']}\n";
            echo "🌐 URL: {$config['url']}\n";
            
            $result = $this->checkVessel($config);
            $results[$terminalCode] = $result;
            
            $this->displayResult($result);
            echo "─────────────────────────────────────────────────────────\n";
            
            // Be respectful - wait between requests
            sleep(2);
        }
        
        $this->displaySummary($results);
        return $results;
    }

    private function checkVessel($config)
    {
        try {
            $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        "User-Agent: {$userAgent}",
                        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                        "Accept-Language: en-US,en;q=0.5",
                        "Accept-Encoding: gzip, deflate",
                        "Connection: keep-alive"
                    ],
                    'timeout' => 30
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $html = @file_get_contents($config['url'], false, $context);
            
            if ($html === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch URL - may be blocked or down',
                    'terminal' => $config['name'],
                    'vessel' => $config['vessel_full'],
                    'checked_at' => date('Y-m-d H:i:s')
                ];
            }
            
            // Check if vessel name and voyage code exist separately in HTML
            $vesselName = $config['vessel_name'];
            $voyageCode = $config['voyage_code'];
            $fullVesselName = $config['vessel_full'];
            
            // Search for vessel name (most important)
            $vesselNameFound = stripos($html, $vesselName) !== false;
            
            // Search for voyage code
            $voyageCodeFound = stripos($html, $voyageCode) !== false;
            
            // Also try full name as fallback
            $fullNameFound = stripos($html, $fullVesselName) !== false;
            
            // Determine overall success
            $vesselFound = $vesselNameFound || $fullNameFound;
            
            if ($vesselFound) {
                // Try to extract ETA using different search patterns
                $eta = null;
                $vesselSection = '';
                
                if ($vesselNameFound) {
                    $eta = $this->extractETA($html, $vesselName);
                    $vesselSection = $this->extractVesselSection($html, $vesselName);
                } elseif ($fullNameFound) {
                    $eta = $this->extractETA($html, $fullVesselName);
                    $vesselSection = $this->extractVesselSection($html, $fullVesselName);
                }
                
                // If no ETA found with vessel name, try with voyage code
                if (!$eta && $voyageCodeFound) {
                    $voyageEta = $this->extractETA($html, $voyageCode);
                    if ($voyageEta) {
                        $eta = $voyageEta;
                        $vesselSection .= "\n--- Voyage Section ---\n" . $this->extractVesselSection($html, $voyageCode);
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
                    'eta' => $eta,
                    'raw_data' => $vesselSection,
                    'html_size' => strlen($html),
                    'checked_at' => date('Y-m-d H:i:s')
                ];
            } else {
                return [
                    'success' => true,
                    'terminal' => $config['name'],
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'vessel_full' => $fullVesselName,
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'full_name_found' => false,
                    'message' => 'Neither vessel name nor voyage code found in HTML content',
                    'html_size' => strlen($html),
                    'checked_at' => date('Y-m-d H:i:s')
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'terminal' => $config['name'],
                'vessel_name' => $config['vessel_name'],
                'voyage_code' => $config['voyage_code'],
                'checked_at' => date('Y-m-d H:i:s')
            ];
        }
    }

    private function getSearchMethod($vesselFound, $voyageFound, $fullFound)
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

    private function extractETA($html, $vesselName)
    {
        // Get the section around the vessel name
        $vesselSection = $this->extractVesselSection($html, $vesselName);
        
        // Common date/time patterns
        $datePatterns = [
            '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/',     // DD/MM/YYYY HH:MM
            '/(\d{4}-\d{2}-\d{2})\s*(\d{2}:\d{2})/',              // YYYY-MM-DD HH:MM
            '/(\d{1,2}-\d{1,2}-\d{4})\s*(\d{1,2}:\d{2})/',        // DD-MM-YYYY HH:MM
            '/ETA[:\s]*(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/i',
            '/Estimated[:\s]*(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/i',
            '/(\d{1,2}\/\d{1,2}\/\d{4})/',                        // Just date DD/MM/YYYY
            '/(\d{4}-\d{2}-\d{2})/',                              // Just date YYYY-MM-DD
        ];
        
        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $vesselSection, $matches)) {
                $dateStr = $matches[1];
                if (isset($matches[2])) {
                    $dateStr .= ' ' . $matches[2];
                }
                
                try {
                    $timestamp = strtotime($dateStr);
                    if ($timestamp !== false) {
                        return date('Y-m-d H:i:s', $timestamp);
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        return null;
    }

    private function extractVesselSection($html, $vesselName)
    {
        $pos = stripos($html, $vesselName);
        if ($pos === false) {
            return '';
        }
        
        // Extract 1000 characters around the vessel name for context
        $start = max(0, $pos - 500);
        $length = 1000;
        
        return substr($html, $start, $length);
    }

    private function displayResult($result)
    {
        if (!$result['success']) {
            echo "❌ ERROR: {$result['error']}\n";
            return;
        }

        echo "📄 HTML Size: " . number_format($result['html_size']) . " bytes\n";

        // Display vessel search results
        echo "🔍 Search Results:\n";
        echo "  📍 Vessel Name: " . ($result['vessel_found'] ? "✅ Found" : "❌ Not found") . " ({$result['vessel_name']})\n";
        echo "  🧭 Voyage Code: " . ($result['voyage_found'] ? "✅ Found" : "❌ Not found") . " ({$result['voyage_code']})\n";
        if (isset($result['search_method'])) {
            echo "  🎯 Match Method: {$result['search_method']}\n";
        }

        if ($result['vessel_found'] || $result['full_name_found']) {
            echo "✅ VESSEL FOUND!\n";
            
            if ($result['eta']) {
                echo "🕒 ETA: {$result['eta']}\n";
            } else {
                echo "⚠️  ETA: Not found or could not parse\n";
            }
            
            if (!empty($result['raw_data'])) {
                echo "📄 Raw Data Preview (first 300 chars):\n";
                $preview = substr(strip_tags($result['raw_data']), 0, 300) . "...\n";
                echo $preview . "\n";
            }
        } else {
            echo "❌ Vessel not found in HTML\n";
            if (isset($result['message'])) {
                echo "💬 {$result['message']}\n";
            }
        }
        
        echo "🕐 Checked at: {$result['checked_at']}\n";
    }

    private function displaySummary($results)
    {
        echo "\n📊 VESSEL TRACKING TEST SUMMARY\n";
        echo "═══════════════════════════════════════════\n";
        
        $successCount = 0;
        $foundCount = 0;
        $etaCount = 0;
        
        foreach ($results as $terminalCode => $result) {
            if ($result['success']) {
                $successCount++;
            }
            
            if ($result['vessel_found'] ?? false) {
                $foundCount++;
            }
            
            if (!empty($result['eta'])) {
                $etaCount++;
            }
            
            $status = $result['success'] ? '✅' : '❌';
            $found = ($result['vessel_found'] ?? false) ? '✅' : '❌';
            $eta = $result['eta'] ?? 'Not found';
            
            printf("%-6s %-15s %-3s %-3s %-20s\n", 
                $terminalCode, 
                $result['terminal'], 
                $status, 
                $found, 
                $eta
            );
        }
        
        echo "\n📈 Statistics:\n";
        echo "  • Total terminals tested: " . count($results) . "\n";
        echo "  • Successful requests: {$successCount}/" . count($results) . "\n";
        echo "  • Vessels found: {$foundCount}/" . count($results) . "\n";
        echo "  • ETAs extracted: {$etaCount}/" . count($results) . "\n";
        
        $successRate = round(($successCount / count($results)) * 100, 1);
        $foundRate = round(($foundCount / count($results)) * 100, 1);
        $etaRate = round(($etaCount / count($results)) * 100, 1);
        
        echo "\n📊 Success Rates:\n";
        echo "  • Request success: {$successRate}%\n";
        echo "  • Vessel detection: {$foundRate}%\n";
        echo "  • ETA extraction: {$etaRate}%\n";
        
        if ($etaRate > 50) {
            echo "\n🎉 Great! Vessel tracking automation is viable!\n";
        } elseif ($foundRate > 50) {
            echo "\n⚠️  Vessel detection works, but ETA parsing needs improvement.\n";
        } else {
            echo "\n❌ Vessel tracking may need different approach for these websites.\n";
        }
        
        echo "\n💡 Next Steps:\n";
        echo "  1. Focus on terminals with high success rates\n";
        echo "  2. Develop specific parsers for each terminal\n";
        echo "  3. Consider API integrations where available\n";
        echo "  4. Add this to your Laravel application\n";
    }
}

// Run the test
if (php_sapi_name() === 'cli') {
    $test = new VesselTrackingTest();
    $test->testAllTerminals();
} else {
    echo "This script should be run from command line: php vessel_test.php\n";
}
