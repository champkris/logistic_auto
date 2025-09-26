#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ShipmentClient;
use App\Services\LineMessagingService;
use Revolution\Line\Facades\Bot;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;

echo "\n=== LINE Messaging Debug Test ===\n\n";

// Check configuration
echo "1. Configuration Check:\n";
echo "   - Channel Token: " . (config('line.bot.channel_token') ? 'Set (' . strlen(config('line.bot.channel_token')) . ' chars)' : 'Not set') . "\n";
echo "   - Channel Secret: " . (config('line.bot.channel_secret') ? 'Set' : 'Not set') . "\n\n";

// Find a connected client
echo "2. Finding connected LINE clients:\n";
$clients = ShipmentClient::whereNotNull('line_user_id')->get();

if ($clients->isEmpty()) {
    echo "   ❌ No LINE-connected clients found.\n";
    echo "   Please connect a client first via the shipments page.\n";
    exit(1);
}

foreach ($clients as $client) {
    echo "   - Client: {$client->client_name}\n";
    echo "     LINE ID: {$client->line_user_id}\n";
    echo "     Shipment: {$client->shipment->invoice_number}\n\n";
}

// Test direct API call
echo "3. Testing direct LINE API call:\n";
$testClient = $clients->first();

try {
    // Create text message
    $textMessage = new TextMessage([
        'type' => 'text',
        'text' => "🧪 Debug Test Message\n\nTime: " . now()->format('Y-m-d H:i:s')
    ]);

    // Create push message request
    $pushMessageRequest = new PushMessageRequest([
        'to' => $testClient->line_user_id,
        'messages' => [$textMessage]
    ]);

    echo "   Sending to: {$testClient->line_user_id}\n";

    // Try to send message
    $response = Bot::pushMessage($pushMessageRequest);

    echo "   ✅ API call successful!\n";
    echo "   Response type: " . get_class($response) . "\n";

    // Check if response has any useful info
    if (method_exists($response, 'getSentMessages')) {
        echo "   Sent messages: " . json_encode($response->getSentMessages()) . "\n";
    }
    if (method_exists($response, 'getRequestId')) {
        echo "   Request ID: " . $response->getRequestId() . "\n";
    }

} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    echo "   Error class: " . get_class($e) . "\n";

    // Check for LINE API specific error
    if (method_exists($e, 'getResponse')) {
        $errorResponse = $e->getResponse();
        if ($errorResponse) {
            echo "   HTTP Status: " . $errorResponse->getStatusCode() . "\n";
            echo "   Response Body: " . $errorResponse->getBody() . "\n";
        }
    }
}

echo "\n4. Testing via LineMessagingService:\n";
try {
    $lineService = new LineMessagingService();
    $result = $lineService->sendShipmentEtaUpdate($testClient);

    if ($result) {
        echo "   ✅ Service call successful!\n";
    } else {
        echo "   ❌ Service call failed.\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Service error: " . $e->getMessage() . "\n";
}

echo "\n=== End of Debug Test ===\n";