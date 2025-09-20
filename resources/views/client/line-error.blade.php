<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LINE Connection Error - Eastern Air Logistics</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
        <!-- Header -->
        <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">❌ Connection Failed</h1>
            <p class="text-gray-600 mt-2">Unable to connect your LINE account to shipment tracking.</p>
        </div>

        <!-- Error Message -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-red-900 mb-2">Error Details</h3>
            <p class="text-sm text-red-700">{{ $error }}</p>
        </div>

        @if($shipment)
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
            </div>
        </div>
        @endif

        <!-- What to do next -->
        <div class="bg-yellow-50 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-yellow-900 mb-3">🔧 What to do next?</h3>
            <div class="text-sm text-yellow-800 space-y-2">
                <p>Please try the following:</p>
                <ul class="list-disc list-inside ml-2 space-y-1">
                    <li>Make sure you have LINE app installed</li>
                    <li>Check your internet connection</li>
                    <li>Try the connection link again</li>
                    <li>Contact Eastern Air Logistics support if the issue persists</li>
                </ul>
            </div>
        </div>

        <!-- Action Button -->
        <div class="text-center">
            <button onclick="window.history.back()"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                🔄 Try Again
            </button>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <div class="border-t pt-4">
                <p class="text-xs text-gray-400">
                    🌟 Eastern Air Logistics<br>
                    Your trusted logistics partner
                </p>
                <p class="text-xs text-gray-400 mt-2">
                    Need help? Contact our support team
                </p>
            </div>
        </div>
    </div>
</body>
</html>