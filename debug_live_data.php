<?php

require_once 'vendor/autoload.php';

use App\Services\VesselTrackingService;
use Illuminate\Support\Facades\Http;

echo "🕵️ MARSA PRIDE Deep Debug - Live Data Analysis\n";
echo str_repeat("=", 60) . "\n\n";

// Test 1: Verify configuration
echo "1️⃣ CONFIGURATION CHECK:\n";
$vesselService = new VesselTrackingService();
$lcb1Config = $vesselService->getTerminalByCode('A0B1');

echo "Current Config:\n";
echo "  - Vessel Full: '{$lcb1Config['vessel_full']}'\n";
echo "  - Vessel Name: '{$lcb1Config['vessel_name']}'\n";
echo "  - Voyage Code: '{$lcb1Config['voyage_code']}'\n\n";

// Test 2: Get actual HTML from website
echo "2️⃣ LIVE WEBSITE DATA:\n";
echo "Fetching current data from LCB1...\n";

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])
        ->get('https://www.lcb1.com/BerthSchedule');
        
    if ($response->successful()) {
        $html = $response->body();
        echo "✅ Successfully fetched HTML (" . number_format(strlen($html)) . " bytes)\n\n";
        
        // Test 3: Search for vessel name
        echo "3️⃣ VESSEL NAME SEARCH:\n";
        $vesselNameFound = str_contains(strtoupper($html), strtoupper('MARSA PRIDE'));
        echo "  - 'MARSA PRIDE' found: " . ($vesselNameFound ? "✅ YES" : "❌ NO") . "\n";
        
        // Test 4: Search for various voyage codes
        echo "\n4️⃣ VOYAGE CODE SEARCH:\n";
        $voyageCodes = ['528S', 'V.528S', 'V528S', '528N'];
        
        foreach ($voyageCodes as $code) {
            $found = str_contains(strtoupper($html), strtoupper($code));
            echo "  - '{$code}' found: " . ($found ? "✅ YES" : "❌ NO") . "\n";
        }
        
        // Test 5: Extract table data around MARSA PRIDE
        echo "\n5️⃣ TABLE DATA EXTRACTION:\n";
        if ($vesselNameFound) {
            // Find table rows containing MARSA PRIDE
            if (preg_match_all('/<tr[^>]*>.*?MARSA\s+PRIDE.*?<\/tr>/si', $html, $matches)) {
                echo "Found " . count($matches[0]) . " table row(s) with MARSA PRIDE:\n\n";
                
                foreach ($matches[0] as $index => $row) {
                    echo "Row " . ($index + 1) . ":\n";
                    echo "HTML: " . substr($row, 0, 300) . "...\n";
                    
                    // Extract cell contents
                    if (preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cellMatches)) {
                        echo "Cells:\n";
                        foreach ($cellMatches[1] as $cellIndex => $cell) {
                            $cleanCell = trim(html_entity_decode(strip_tags($cell)));
                            if (!empty($cleanCell)) {
                                echo "  [$cellIndex] '$cleanCell'\n";
                            }
                        }
                    }
                    echo "\n";
                }
            } else {
                echo "❌ No table rows found with MARSA PRIDE\n";
                
                // Fallback: Look for any mention of MARSA PRIDE
                $pos = stripos($html, 'MARSA PRIDE');
                if ($pos !== false) {
                    echo "Found MARSA PRIDE at position $pos. Context:\n";
                    $context = substr($html, max(0, $pos - 200), 400);
                    echo htmlspecialchars($context) . "\n\n";
                }
            }
        }
        
        // Test 6: Check if website structure changed
        echo "6️⃣ WEBSITE STRUCTURE CHECK:\n";
        echo "  - Contains <table>: " . (str_contains($html, '<table') ? "✅ YES" : "❌ NO") . "\n";
        echo "  - Contains 'Vessel Name': " . (stripos($html, 'Vessel Name') !== false ? "✅ YES" : "❌ NO") . "\n";
        echo "  - Contains 'Voyage': " . (stripos($html, 'Voyage') !== false ? "✅ YES" : "❌ NO") . "\n";
        echo "  - Contains 'Berthing Time': " . (stripos($html, 'Berthing Time') !== false ? "✅ YES" : "❌ NO") . "\n";
        
    } else {
        echo "❌ Failed to fetch HTML. Status: " . $response->status() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error fetching data: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 DIAGNOSIS COMPLETE\n";
