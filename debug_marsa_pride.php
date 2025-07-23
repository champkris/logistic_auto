<?php

require_once 'vendor/autoload.php';

use App\Services\VesselTrackingService;
use App\Services\VesselNameParser;

echo "🔍 MARSA PRIDE Debug Test\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$vesselService = new VesselTrackingService();

// Test 1: Check current terminal configuration
echo "📋 Current Terminal Configuration:\n";
$terminals = $vesselService->getTerminals();
foreach ($terminals as $code => $config) {
    if (stripos($config['vessel_name'], 'MARSA PRIDE') !== false) {
        echo "Terminal: {$code} ({$config['name']})\n";
        echo "  - Full Vessel: {$config['vessel_full']}\n";
        echo "  - Vessel Name: {$config['vessel_name']}\n";
        echo "  - Voyage Code: {$config['voyage_code']}\n";
        echo "  - URL: {$config['url']}\n\n";
        
        // Test 2: Test the specific terminal
        echo "🧪 Testing Terminal {$code}...\n";
        $result = $vesselService->checkVesselETA($code, $config);
        
        echo "Results:\n";
        echo "  - Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
        echo "  - Vessel Found: " . ($result['vessel_found'] ? 'Yes' : 'No') . "\n";
        echo "  - Voyage Found: " . (($result['voyage_found'] ?? false) ? 'Yes' : 'No') . "\n";
        echo "  - Search Method: " . ($result['search_method'] ?? 'N/A') . "\n";
        
        if (isset($result['voyage_variations_tried'])) {
            echo "  - Voyage Variations Tried: " . implode(', ', $result['voyage_variations_tried']) . "\n";
        }
        
        if (isset($result['eta'])) {
            echo "  - ETA: " . ($result['eta'] ?: 'Not found') . "\n";
        }
        
        if (isset($result['error'])) {
            echo "  - Error: {$result['error']}\n";
        }
        
        echo "\n";
    }
}

// Test 3: Test VesselNameParser
echo "🔧 VesselNameParser Test:\n";
$testNames = [
    'MARSA PRIDE V.528S',
    'MARSA PRIDE 528S',
    'MARSA PRIDE'
];

foreach ($testNames as $testName) {
    $parsed = VesselNameParser::parse($testName);
    echo "Input: '{$testName}'\n";
    echo "  - Vessel Name: '{$parsed['vessel_name']}'\n";
    echo "  - Voyage Code: '{$parsed['voyage_code']}'\n";
    echo "  - Full Name: '{$parsed['full_name']}'\n\n";
}

echo "✅ Debug test completed.\n";
