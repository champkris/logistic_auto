#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\LineMessagingService;

try {
    $lineService = new LineMessagingService();

    // Replace with your actual LINE user ID for testing
    $testLineUserId = 'U1234567890abcdef1234567890abcde'; // You need to replace this

    $testMessage = "🧪 Test Message from Eastern Air Logistics\n\n";
    $testMessage .= "This is a test message to verify LINE messaging is working.\n";
    $testMessage .= "Time: " . now()->format('Y-m-d H:i:s');

    echo "Sending test message to LINE...\n";

    $result = $lineService->sendTextMessage($testLineUserId, $testMessage);

    if ($result) {
        echo "✅ Message sent successfully!\n";
    } else {
        echo "❌ Failed to send message. Check the logs for details.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}