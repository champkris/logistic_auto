<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

// Root route redirects to dashboard (requires authentication)
Route::get('/', App\Livewire\Dashboard::class)->name('home')->middleware(['auth']);

// Optional: Keep welcome page accessible for development/info
Route::view('/welcome', 'welcome')->name('welcome');

// Diagnostic route for remote debugging
Route::get('/debug', function () {
    try {
        $data = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_connection' => 'testing...',
            'cache_working' => 'testing...',
            'user_count' => 0,
            'shipment_count' => 0,
            'vessel_count' => 0,
        ];

        // Test database connection
        try {
            $data['user_count'] = \App\Models\User::count();
            $data['shipment_count'] = \App\Models\Shipment::count();
            $data['vessel_count'] = \App\Models\Vessel::count();
            $data['database_connection'] = '✅ Connected';
        } catch (\Exception $e) {
            $data['database_connection'] = '❌ Failed: ' . $e->getMessage();
        }

        // Test cache
        try {
            \Cache::put('test_key', 'test_value', 60);
            $cached = \Cache::get('test_key');
            $data['cache_working'] = $cached === 'test_value' ? '✅ Working' : '❌ Failed';
        } catch (\Exception $e) {
            $data['cache_working'] = '❌ Failed: ' . $e->getMessage();
        }

        return response()->json($data, 200, [], JSON_PRETTY_PRINT);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], 500, [], JSON_PRETTY_PRINT);
    }
})->name('debug');

// Simple test route without authentication
Route::get('/test', function () {
    return response('✅ Laravel is working! Time: ' . now()->toDateTimeString(), 200)
        ->header('Content-Type', 'text/plain');
})->name('test');

// Test dashboard without authentication
Route::get('/test-dashboard', function () {
    try {
        return view('livewire.dashboard', [
            'stats' => [
                'total_shipments' => 0,
                'active_shipments' => 0,
                'vessels_arriving_soon' => 0,
                'pending_documents' => 0,
                'overdue_documents' => 0,
            ],
            'recent_shipments' => collect([]),
            'vessels_arriving' => collect([]),
            'urgent_tasks' => [
                'pending_dos' => 0,
                'customs_pending' => 0,
                'in_progress' => 0,
            ]
        ]);
    } catch (\Exception $e) {
        return response('Dashboard Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 500)
            ->header('Content-Type', 'text/plain');
    }
})->name('test-dashboard');

// Protected Routes (Authentication Required)
Route::middleware(['auth', 'verified'])->group(function () {
    // Main Logistics Routes
    Route::get('/dashboard', App\Livewire\Dashboard::class)->name('dashboard');
    Route::get('/customers', App\Livewire\CustomerManager::class)->name('customers');
    Route::get('/shipments', App\Livewire\ShipmentManager::class)->name('shipments');
    Route::get('/settings', App\Livewire\Settings::class)->name('settings');

    // LINE Login Routes (require user to be authenticated first)
    Route::get('/line/connect', [App\Http\Controllers\LineLoginController::class, 'redirectToLine'])->name('line.connect');
    Route::get('/line/callback', [App\Http\Controllers\LineLoginController::class, 'handleLineCallback'])->name('line.callback');
    Route::post('/line/disconnect', [App\Http\Controllers\LineLoginController::class, 'disconnectLine'])->name('line.disconnect');

    // LINE Test Message Route
    Route::post('/line/test-message', function () {
        $user = Auth::user();
        if (!$user || !$user->hasLineAccount()) {
            return redirect()->route('profile')->with('error', 'Please connect your LINE account first.');
        }

        try {
            $lineMessaging = new \App\Services\LineMessagingService();
            $success = $lineMessaging->sendTestMessage($user);

            if ($success) {
                return redirect()->route('profile')->with('success', 'Test message sent to your LINE account!');
            } else {
                return redirect()->route('profile')->with('error', 'Failed to send test message. Please check your LINE connection.');
            }
        } catch (\Exception $e) {
            return redirect()->route('profile')->with('error', 'Error sending test message: ' . $e->getMessage());
        }
    })->name('line.test-message');

    // Shipment Client LINE Routes (Admin only)
    Route::post('/shipments/generate-client-link', [App\Http\Controllers\ShipmentClientController::class, 'generateClientLink'])->name('shipments.generate-client-link');
    Route::post('/shipments/send-test-notification', [App\Http\Controllers\ShipmentClientController::class, 'sendTestNotification'])->name('shipments.send-test-notification');
    Route::post('/shipments/check-eta', [App\Http\Controllers\ShipmentClientController::class, 'checkShipmentETA'])->name('shipments.check-eta');
    
    // Vessel Tracking Test Routes
    Route::get('/vessel-test', function () {
        return view('vessel-test');
    })->name('vessel-test');

    Route::get('/vessel-test/run', function () {
        // Use the proper VesselTrackingService with browser automation
        $vesselService = new \App\Services\VesselTrackingService();

        try {
            // Get all terminal results using the service
            $results = [];
            $terminals = $vesselService->getTerminals();

            foreach ($terminals as $terminalCode => $config) {
                try {
                    $result = $vesselService->checkVesselETA($terminalCode, $config);

                    // Transform result to match frontend expectations
                    $results[$terminalCode] = [
                        'terminal' => $result['terminal'] ?? $config['name'],
                        'vessel_name' => $result['vessel_name'] ?? $config['vessel_name'] ?? '',
                        'voyage_code' => $result['voyage_code'] ?? $config['voyage_code'] ?? '',
                        'vessel_full' => $config['vessel_full'] ?? '',
                        'successful' => $result['success'] ?? false, // Frontend expects 'successful'
                        'success' => $result['success'] ?? false,
                        'vessel_found' => $result['vessel_found'] ?? false,
                        'voyage_found' => $result['voyage_found'] ?? false,
                        'full_name_found' => $result['vessel_found'] ?? false,
                        'search_method' => $result['search_method'] ?? 'unknown',
                        'eta' => $result['eta'] ?? null,
                        'error' => $result['error'] ?? null,
                        'html_size' => 32813, // Placeholder for compatibility
                        'status_code' => 200, // Placeholder for compatibility
                        'checked_at' => $result['checked_at'] ?? now()->format('Y-m-d H:i:s')
                    ];

                } catch (\Exception $e) {
                    $results[$terminalCode] = [
                        'terminal' => $config['name'],
                        'vessel_name' => $config['vessel_name'] ?? '',
                        'voyage_code' => $config['voyage_code'] ?? '',
                        'vessel_full' => $config['vessel_full'] ?? '',
                        'successful' => false, // Frontend expects 'successful'
                        'success' => false,
                        'vessel_found' => false,
                        'voyage_found' => false,
                        'full_name_found' => false,
                        'search_method' => 'service_error',
                        'error' => $e->getMessage(),
                        'html_size' => 0,
                        'status_code' => 500,
                        'checked_at' => now()->format('Y-m-d H:i:s')
                    ];
                }
            }

            return response()->json([
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'successful' => collect($results)->where('successful', true)->count(),
                    'found' => collect($results)->where('vessel_found', true)->count(),
                    'with_eta' => collect($results)->whereNotNull('eta')->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'VesselTrackingService error: ' . $e->getMessage(),
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'results' => []
            ], 500);
        }
    })->name('vessel-test.run');

    // Single Vessel Test Route
    Route::post('/vessel-test/single', function (Request $request) {
        $vesselService = new \App\Services\VesselTrackingService();

        try {
            // Validate input
            $request->validate([
                'vessel_name' => 'required|string|max:255',
                'voyage_code' => 'nullable|string|max:100',
                'terminal' => 'required|string|in:C1C2,B4,B5C3,B3,A0B1,B2'
            ]);

            $vesselName = trim($request->input('vessel_name'));
            $voyageCode = trim($request->input('voyage_code'));
            $terminalCode = $request->input('terminal');

            // Get terminal configuration
            $terminals = $vesselService->getTerminals();
            if (!isset($terminals[$terminalCode])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid terminal code: ' . $terminalCode
                ], 400);
            }

            $terminalConfig = $terminals[$terminalCode];
            
            // Override with user-provided vessel data
            $testConfig = array_merge($terminalConfig, [
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'vessel_full' => $voyageCode ? "$vesselName $voyageCode" : $vesselName
            ]);

            // Run the test
            $result = $vesselService->checkVesselETA($terminalCode, $testConfig);

            // Transform result to match frontend expectations
            $transformedResult = [
                'terminal' => $result['terminal'] ?? $terminalConfig['name'],
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'vessel_full' => $testConfig['vessel_full'],
                'successful' => $result['success'] ?? false,
                'success' => $result['success'] ?? false,
                'vessel_found' => $result['vessel_found'] ?? false,
                'voyage_found' => $result['voyage_found'] ?? false,
                'full_name_found' => $result['vessel_found'] ?? false,
                'search_method' => $result['search_method'] ?? 'unknown',
                'eta' => $result['eta'] ?? null,
                'error' => $result['error'] ?? null,
                'html_size' => $result['html_size'] ?? 0,
                'status_code' => $result['status_code'] ?? 200,
                'checked_at' => $result['checked_at'] ?? now()->format('Y-m-d H:i:s'),
                'raw_data' => isset($result['raw_data']) && is_string($result['raw_data']) ? substr($result['raw_data'], 0, 500) : null
            ];

            return response()->json([
                'success' => true,
                'result' => $transformedResult,
                'terminal_code' => $terminalCode,
                'search_query' => [
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'terminal' => $terminalConfig['name']
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'validation_errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Single vessel test failed: ' . $e->getMessage(),
                'timestamp' => now()->format('Y-m-d H:i:s')
            ], 500);
        }
    })->name('vessel-test.single');
});

// Profile Route (Authentication Required)
Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Public Shipment Client LINE Routes (no authentication required)
Route::get('/client/line/connect/{token}', [App\Http\Controllers\ShipmentClientController::class, 'redirectToLineLogin'])->name('client.line.connect');
Route::get('/client/line/callback', [App\Http\Controllers\ShipmentClientController::class, 'handleLineCallback'])->name('client.line.callback');

// Public vessel test page (for testing without authentication)
Route::get('/vessel-test-public', function () {
    return view('vessel-test');
})->name('vessel-test-public');

// Test route for vessel tracking (temporarily outside auth for testing)
Route::post('/vessel-test-public/single', function (Request $request) {
    $vesselService = new \App\Services\VesselTrackingService();

    try {
        // Validate input
        $request->validate([
            'vessel_name' => 'required|string|max:255',
            'voyage_code' => 'nullable|string|max:100',
            'terminal' => 'required|string|in:C1C2,B4,B5C3,B3,A0B1,B2'
        ]);

        $vesselName = trim($request->input('vessel_name'));
        $voyageCode = trim($request->input('voyage_code'));
        $terminalCode = $request->input('terminal');

        // Get terminal configuration
        $terminals = $vesselService->getTerminals();
        if (!isset($terminals[$terminalCode])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid terminal code: ' . $terminalCode
            ], 400);
        }

        $terminalConfig = $terminals[$terminalCode];
        
        // Override with user-provided vessel data
        $testConfig = array_merge($terminalConfig, [
            'vessel_name' => $vesselName,
            'voyage_code' => $voyageCode,
            'vessel_full' => $voyageCode ? "$vesselName $voyageCode" : $vesselName
        ]);

        // Run the test
        $result = $vesselService->checkVesselETA($terminalCode, $testConfig);

        // Transform result to match frontend expectations
        $transformedResult = [
            'terminal' => $result['terminal'] ?? $terminalConfig['name'],
            'vessel_name' => $vesselName,
            'voyage_code' => $voyageCode,
            'vessel_full' => $testConfig['vessel_full'],
            'successful' => $result['success'] ?? false,
            'success' => $result['success'] ?? false,
            'vessel_found' => $result['vessel_found'] ?? false,
            'voyage_found' => $result['voyage_found'] ?? false,
            'full_name_found' => $result['vessel_found'] ?? false,
            'search_method' => $result['search_method'] ?? 'unknown',
            'eta' => $result['eta'] ?? null,
            'error' => $result['error'] ?? null,
            'html_size' => $result['html_size'] ?? 0,
            'status_code' => $result['status_code'] ?? 200,
            'checked_at' => $result['checked_at'] ?? now()->format('Y-m-d H:i:s'),
            'raw_data' => isset($result['raw_data']) && is_string($result['raw_data']) ? substr($result['raw_data'], 0, 500) : null
        ];

        return response()->json([
            'success' => true,
            'result' => $transformedResult,
            'terminal_code' => $terminalCode,
            'search_query' => [
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode,
                'terminal' => $terminalConfig['name']
            ]
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'error' => 'Validation failed',
            'validation_errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Single vessel test failed: ' . $e->getMessage(),
            'timestamp' => now()->format('Y-m-d H:i:s')
        ], 500);
    }
})->name('vessel-test-public.single');

// Authentication Routes
require __DIR__.'/auth.php';
