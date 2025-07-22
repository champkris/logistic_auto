<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Services/VesselNameParser.php';
require_once __DIR__ . '/app/Services/VesselTrackingService.php';

use App\Services\VesselTrackingService;
use Carbon\Carbon;

echo "🚢 Enhanced ETA Extraction Test for Hutchison Ports\n";
echo "════════════════════════════════════════════════════\n";
echo "Testing improved table-based ETA parsing...\n\n";

// Mock HTML table data similar to what we saw in the screenshot
$mockTableHTML = '
<table>
    <tr>
        <th>Vessel Name</th>
        <th>Vessel ID</th>
        <th>In Voy</th>
        <th>Out Voy</th>
        <th>Arrival</th>
        <th>Departure</th>
        <th>Berth Terminal</th>
    </tr>
    <tr>
        <td>WAN HAI 517</td>
        <td>5G</td>
        <td>S093</td>
        <td>N093</td>
        <td>20/07/2025<br>15:40</td>
        <td>22/07/2025<br>14:00</td>
        <td>C1C2</td>
    </tr>
    <tr>
        <td>OOCL YOKOHAMA</td>
        <td>OYK</td>
        <td>202N</td>
        <td>203S</td>
        <td>21/07/2025<br>22:32</td>
        <td>23/07/2025<br>17:00</td>
        <td>C1C2</td>
    </tr>
</table>
';

// Test the table extraction function
$service = new VesselTrackingService();

echo "🧪 Test 1: Table-based ETA Extraction\n";
echo "─────────────────────────────────────────\n";

// Test WAN HAI 517
$testVessel = "WAN HAI 517";
echo "Testing vessel: {$testVessel}\n";

// Use reflection to test the protected method
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('extractETAFromTable');
$method->setAccessible(true);

$extractedETA = $method->invoke($service, $mockTableHTML, $testVessel);

if ($extractedETA) {
    echo "✅ ETA Extracted: {$extractedETA}\n";
    
    // Parse and display in readable format
    try {
        $eta = Carbon::parse($extractedETA);
        echo "📅 Formatted: {$eta->format('d/m/Y H:i')} ({$eta->format('l, F j, Y \\a\\t g:i A')})\n";
    } catch (\Exception $e) {
        echo "⚠️  Could not format date: {$e->getMessage()}\n";
    }
} else {
    echo "❌ ETA not extracted\n";
}

echo "\n🧪 Test 2: Real Hutchison Ports URL Test\n";
echo "────────────────────────────────────────────────\n";

$config = [
    'name' => 'Hutchison Ports',
    'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17:6927160550678:::::',
    'vessel_full' => 'WAN HAI 517 S093',
    'vessel_name' => 'WAN HAI 517',
    'voyage_code' => 'S093',
    'method' => 'hutchison'
];

echo "🔍 Testing real URL with improved ETA extraction...\n";

try {
    $result = $service->checkVesselETA('C1C2', $config);
    
    echo "📊 Results:\n";
    echo "  Success: " . ($result['success'] ? '✅' : '❌') . "\n";
    echo "  Vessel Found: " . (($result['vessel_found'] ?? false) ? '✅' : '❌') . "\n";
    echo "  Voyage Found: " . (($result['voyage_found'] ?? false) ? '✅' : '❌') . "\n";
    
    if ($result['eta'] ?? false) {
        echo "  🎉 ETA FOUND: {$result['eta']}\n";
        try {
            $eta = Carbon::parse($result['eta']);
            echo "  📅 Human readable: {$eta->format('l, F j, Y \\a\\t g:i A')}\n";
        } catch (\Exception $e) {
            // ETA might be in raw format
            echo "  📅 Raw format: {$result['eta']}\n";
        }
    } else {
        echo "  ❌ ETA: Not found\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n💡 Expected Result:\n";
echo "─────────────────────────\n";
echo "Based on your screenshot, we should see:\n";
echo "• ✅ Vessel Found: WAN HAI 517\n";
echo "• ✅ Voyage Found: S093\n";
echo "• ✅ ETA Found: 2025-07-20 15:40:00\n";
echo "• 📅 Display: Sunday, July 20, 2025 at 3:40 PM\n\n";

echo "🚀 Ready to test in Web UI!\n";
echo "──────────────────────────────\n";
echo "1. Go to: http://127.0.0.1:8001/vessel-test\n";
echo "2. Click 'Run Test'\n";
echo "3. Hutchison Ports should now show ETA found!\n";
