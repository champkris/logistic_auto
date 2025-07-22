<?php

require_once __DIR__ . '/app/Services/VesselNameParser.php';

use App\Services\VesselNameParser;

echo "🚢 Vessel Name Parser - Test Suite\n";
echo "═══════════════════════════════════════\n\n";

// Test with your actual vessel examples
$testVessels = [
    'WAN HAI 517 S093',
    'SRI SUREE V.25080S', 
    'ASL QINGDAO V.2508S',
    'CUL NANSHA V. 2528S',
    'MARSA PRIDE V.528S',
    'EVER BUILD V.0794-074S',
    'MSC PARIS 2024',
    'OOCL SHENZHEN N456A',
    'COSCO SHIPPING ARIES',
    'EVER GIVEN V2024S',
    'MAERSK DETROIT 456',
    'CMA CGM MARCO POLO S123',
    '',  // Empty test
    'SINGLE', // Single word test
];

echo "📋 Test Results:\n";
echo "┌─────────────────────────┬─────────────────┬──────────────┬──────────────────────┐\n";
echo "│ Full Vessel Name        │ Vessel Name     │ Voyage Code  │ Parsing Method       │\n";
echo "├─────────────────────────┼─────────────────┼──────────────┼──────────────────────┤\n";

foreach ($testVessels as $vessel) {
    $result = VesselNameParser::parse($vessel);
    
    // Format for display
    $fullName = empty($vessel) ? '(empty)' : $vessel;
    $vesselName = empty($result['vessel_name']) ? '-' : $result['vessel_name'];
    $voyageCode = empty($result['voyage_code']) ? '-' : $result['voyage_code'];
    
    printf("│ %-23s │ %-15s │ %-12s │ %-20s │\n", 
        substr($fullName, 0, 23),
        substr($vesselName, 0, 15),
        substr($voyageCode, 0, 12),
        substr($result['parsing_method'], 0, 20)
    );
}

echo "└─────────────────────────┴─────────────────┴──────────────┴──────────────────────┘\n\n";

// Test parsing reliability
echo "📊 Parsing Reliability Check:\n";
echo "─────────────────────────────────\n";

$reliableCount = 0;
$totalCount = 0;

foreach ($testVessels as $vessel) {
    if (empty($vessel)) continue; // Skip empty test
    
    $result = VesselNameParser::parse($vessel);
    $totalCount++;
    
    $isReliable = VesselNameParser::isReliableParsing($result);
    if ($isReliable) $reliableCount++;
    
    $status = $isReliable ? '✅' : '⚠️ ';
    echo "{$status} {$vessel} → {$result['parsing_method']}\n";
}

$successRate = $totalCount > 0 ? round(($reliableCount / $totalCount) * 100, 1) : 0;
echo "\n📈 Success Rate: {$reliableCount}/{$totalCount} ({$successRate}%)\n";

// Usage examples
echo "\n💡 Usage Examples:\n";
echo "─────────────────\n";

$exampleVessel = "WAN HAI 517 S093";
$parsed = VesselNameParser::parse($exampleVessel);

echo "// Parse a vessel name\n";
echo '$result = VesselNameParser::parse("' . $exampleVessel . '");' . "\n";
echo "// Result:\n";
foreach ($parsed as $key => $value) {
    echo "//   {$key}: '{$value}'\n";
}

echo "\n// Quick access methods\n";
echo '$vesselName = VesselNameParser::getVesselName("' . $exampleVessel . '");' . "\n";
echo "// Result: '{$parsed['vessel_name']}'\n";

echo '$voyageCode = VesselNameParser::getVoyageCode("' . $exampleVessel . '");' . "\n";
echo "// Result: '{$parsed['voyage_code']}'\n";

echo "\n🔍 This parser can now be used throughout your Laravel app:\n";
echo "─────────────────────────────────────────────────────────────\n";
echo "• Vessel model - auto-parse vessel names on save\n";
echo "• Shipment forms - split user input automatically\n"; 
echo "• ETA checking - extract vessel names for port searches\n";
echo "• Data imports - clean up vessel data from Excel/CSV\n";
echo "• API endpoints - standardize vessel name format\n";

echo "\n✅ Parser is ready for integration into your logistics system!\n";
