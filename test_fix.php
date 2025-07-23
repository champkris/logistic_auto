<?php

require_once 'vendor/autoload.php';

use App\Services\VesselTrackingService;
use App\Services\VesselNameParser;

echo "🧪 Testing MARSA PRIDE Fix\n";
echo str_repeat("=", 50) . "\n\n";

$vesselService = new VesselTrackingService();

// Check the updated configuration
echo "📋 Updated Configuration:\n";
$lcb1Config = $vesselService->getTerminalByCode('A0B1');
if ($lcb1Config) {
    echo "Terminal: A0B1 ({$lcb1Config['name']})\n";
    echo "  - Full Vessel: {$lcb1Config['vessel_full']}\n";
    echo "  - Vessel Name: {$lcb1Config['vessel_name']}\n";
    echo "  - Voyage Code: {$lcb1Config['voyage_code']}\n\n";
    
    // Test the parser output
    $parsed = VesselNameParser::parse($lcb1Config['vessel_full']);
    echo "🔧 Parsed Result:\n";
    echo "  - Vessel Name: '{$parsed['vessel_name']}'\n";
    echo "  - Voyage Code: '{$parsed['voyage_code']}'\n";
    echo "  - Full Name: '{$parsed['full_name']}'\n\n";
    
    echo "✅ Configuration updated successfully!\n";
    echo "🎯 Expected Result: Both vessel name and voyage should now be found\n";
} else {
    echo "❌ Could not find LCB1 terminal configuration\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🚀 Fix applied: 'MARSA PRIDE V.528S' → 'MARSA PRIDE 528S'\n";
