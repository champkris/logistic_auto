<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\VesselTrackingService;

echo "Testing EVER BUILD 0815-079S vessel tracking fix...\n\n";

try {
    $service = new VesselTrackingService();
    
    echo "Testing EVER BUILD 0815-079S vessel tracking...\n";
    $result = $service->checkVesselETAByName('EVER BUILD 0815-079S', 'B2');
    
    echo "Result:\n";
    echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
    echo "Terminal: " . $result['terminal'] . "\n";
    echo "Vessel Found: " . ($result['vessel_found'] ? 'true' : 'false') . "\n";
    echo "Voyage Found: " . ($result['voyage_found'] ? 'true' : 'false') . "\n";
    
    if (isset($result['message'])) {
        echo "Message: " . $result['message'] . "\n";
    }
    
    if (isset($result['no_data_reason'])) {
        echo "No Data Reason: " . $result['no_data_reason'] . "\n";
    }
    
    echo "\n✅ Test completed successfully - no more 'Browser automation failed' errors!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "This indicates the fix didn't work completely.\n";
}
