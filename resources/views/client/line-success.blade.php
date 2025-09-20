<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LINE Connected Successfully - Eastern Air Logistics</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
        <!-- Header -->
        <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">🎉 LINE Connected Successfully!</h1>
            <p class="text-gray-600 mt-2">Your LINE account has been linked to your shipment tracking.</p>
        </div>

        <!-- Shipment Details -->
        <div class="bg-blue-50 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-blue-900 mb-3">📦 Shipment Information</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-blue-700">Invoice:</span>
                    <span class="font-medium text-blue-900">{{ $shipment->invoice_number }}</span>
                </div>
                @if($shipment->hbl_number)
                <div class="flex justify-between">
                    <span class="text-blue-700">HBL:</span>
                    <span class="font-medium text-blue-900">{{ $shipment->hbl_number }}</span>
                </div>
                @endif
                @if($shipment->vessel)
                <div class="flex justify-between">
                    <span class="text-blue-700">Vessel:</span>
                    <span class="font-medium text-blue-900">{{ $shipment->vessel->name }}</span>
                </div>
                @endif
                @if($shipment->voyage)
                <div class="flex justify-between">
                    <span class="text-blue-700">Voyage:</span>
                    <span class="font-medium text-blue-900">{{ $shipment->voyage }}</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span class="text-blue-700">Status:</span>
                    <span class="font-medium text-blue-900">{{ ucfirst(str_replace('_', ' ', $shipment->status)) }}</span>
                </div>
            </div>
        </div>

        <!-- Client Details -->
        <div class="bg-green-50 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-green-900 mb-3">👤 Connected Account</h3>
            <div class="flex items-center space-x-3">
                @if($client->line_picture_url)
                    <img src="{{ $client->line_picture_url }}" alt="{{ $client->line_display_name }}" class="w-10 h-10 rounded-full">
                @else
                    <div class="w-10 h-10 bg-green-200 rounded-full flex items-center justify-center">
                        <span class="text-green-700 font-medium">{{ substr($client->line_display_name, 0, 1) }}</span>
                    </div>
                @endif
                <div>
                    <p class="font-medium text-green-900">{{ $client->line_display_name }}</p>
                    <p class="text-sm text-green-700">Connected to: {{ $client->client_name }}</p>
                </div>
            </div>
        </div>

        <!-- What's Next -->
        <div class="bg-yellow-50 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-yellow-900 mb-3">📲 What's Next?</h3>
            <div class="text-sm text-yellow-800 space-y-2">
                <p>You'll now receive LINE notifications for:</p>
                <ul class="list-disc list-inside ml-2 space-y-1">
                    <li>🚢 Vessel arrival updates</li>
                    <li>📋 Document status changes</li>
                    <li>🚛 Delivery notifications</li>
                    <li>⏰ ETA updates</li>
                </ul>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center">
            <div class="text-sm text-gray-500 mb-4">
                Connected at {{ $client->line_connected_at->format('M d, Y H:i') }}
            </div>
            <div class="border-t pt-4">
                <p class="text-xs text-gray-400">
                    🌟 Eastern Air Logistics<br>
                    Your trusted logistics partner
                </p>
            </div>
        </div>
    </div>
</body>
</html>