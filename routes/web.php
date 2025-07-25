<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

Route::get('/', App\Livewire\Dashboard::class)->name('dashboard');

Route::get('/welcome', function () {
    return view('welcome');
});

// Vessel Tracking Test Routes
Route::get('/vessel-test', function () {
    return view('vessel-test');
});

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
                $results[$terminalCode] = $result;
            } catch (\Exception $e) {
                $results[$terminalCode] = [
                    'success' => false,
                    'terminal' => $config['name'],
                    'vessel_name' => $config['vessel_name'] ?? '',
                    'voyage_code' => $config['voyage_code'] ?? '',
                    'error' => $e->getMessage(),
                    'search_method' => 'service_error',
                    'checked_at' => now()
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'results' => $results,
            'timestamp' => now()->format('Y-m-d H:i:s')
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'VesselTrackingService error: ' . $e->getMessage(),
            'timestamp' => now()->format('Y-m-d H:i:s')
        ], 500);
    }
});
