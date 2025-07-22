<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VesselTrackingController;
use App\Http\Controllers\Api\AutomationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Vessel Tracking API Routes
Route::post('/vessel-update', [VesselTrackingController::class, 'updateVesselData'])
    ->name('api.vessel.update');

Route::get('/vessel/{vessel}/status', [VesselTrackingController::class, 'getVesselStatus'])
    ->name('api.vessel.status');

// Automation System API Routes  
Route::post('/automation-summary', [AutomationController::class, 'storeSummary'])
    ->name('api.automation.summary');

Route::get('/automation/status', [AutomationController::class, 'getStatus'])
    ->name('api.automation.status');

// Test endpoint
Route::get('/test', function () {
    return response()->json([
        'message' => 'CS Shipping LCB API is working!',
        'timestamp' => now(),
        'endpoints' => [
            'POST /api/vessel-update' => 'Update vessel data from scraper',
            'GET /api/vessel/{vessel}/status' => 'Get vessel status',
            'POST /api/automation-summary' => 'Store automation run summary',
            'GET /api/automation/status' => 'Get automation system status'
        ]
    ]);
});
