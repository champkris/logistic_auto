<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "🔧 Testing ETA Fix in Laravel Context\n";
echo "═════════════════════════════════════\n";
echo "Running vessel tracking through Laravel...\n\n";

// Change to the Laravel directory and run through Artisan
echo "🚀 Starting Laravel application...\n\n";

$output = [];
$return_var = 0;

// Create a simple Artisan command to test the VesselTrackingService
$testCommand = '
use App\Services\VesselTrackingService;

$service = new VesselTrackingService();

echo "🚢 Testing SRI SUREE V.25080S on TIPS...\n";
echo "=======================================\n";

try {
    $result = $service->checkVesselETAByName("SRI SUREE V.25080S", "B4");
    
    if ($result["success"]) {
        echo "✅ SUCCESS!\n";
        echo "Vessel: " . $result["vessel_name"] . "\n";
        echo "Voyage: " . $result["voyage_code"] . "\n";
        echo "Found: " . ($result["vessel_found"] ? "YES" : "NO") . "\n";
        echo "ETA: " . ($result["eta"] ?? "Not found") . "\n";
    } else {
        echo "❌ FAILED: " . $result["error"] . "\n";
    }
} catch (Exception $e) {
    echo "💥 ERROR: " . $e->getMessage() . "\n";
}
';

// Write the test command to a temporary file
file_put_contents('/tmp/vessel_test_command.php', "<?php\n" . $testCommand);

// Execute through Laravel's tinker
exec('cd /Users/apichakriskalambasuta/Sites/localhost/logistic_auto && php artisan tinker < /tmp/vessel_test_command.php', $output, $return_var);

// Display the output
echo "📊 LARAVEL OUTPUT:\n";
echo "─────────────────\n";
foreach ($output as $line) {
    // Skip Laravel boot messages
    if (strpos($line, 'Psy Shell') !== false || 
        strpos($line, 'exit') !== false || 
        strpos($line, '>>>') !== false ||
        empty(trim($line))) {
        continue;
    }
    echo $line . "\n";
}

echo "\n💡 The ETA extraction should now correctly identify:\n";
echo "   • SRI SUREE as the vessel name\n";
echo "   • 25080S as the voyage code (from V.25080S)\n";
echo "   • 2025-07-22 10:00:00 as the ETA (or actual arrival time)\n\n";

echo "🔍 If you're still seeing wrong dates, we may need to:\n";
echo "   1. Clear any cached data in your application\n";
echo "   2. Update the database with fresh vessel information\n";
echo "   3. Test with a different vessel to verify the fix\n\n";

echo "✅ Test completed!\n";
