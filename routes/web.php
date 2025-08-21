<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

// Root route redirects to dashboard (requires authentication)
Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified']);

// Optional: Keep welcome page accessible for development/info
Route::view('/welcome', 'welcome')->name('welcome');

// Protected Routes (Authentication Required)
Route::middleware(['auth', 'verified'])->group(function () {
    // Main Logistics Routes
    Route::get('/dashboard', App\Livewire\Dashboard::class)->name('dashboard');
    Route::get('/customers', App\Livewire\CustomerManager::class)->name('customers');
    Route::get('/shipments', App\Livewire\ShipmentManager::class)->name('shipments');
    
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
});

// Profile Route (Authentication Required)
Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Authentication Routes
require __DIR__.'/auth.php';
