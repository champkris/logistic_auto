<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VesselTrackingController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\SiamComChatbotEtaRequestController;
use App\Http\Controllers\Api\SiamComEtaAttemptController;

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

// Siam Com Chatbot ETA Request API Routes for n8n (no auth required for external integration)
Route::prefix('siam-com/chatbot/eta')->group(function () {
    Route::post('/start', [SiamComChatbotEtaRequestController::class, 'startSiamComEtaRequest'])->name('api.siam-com.eta.start');
    Route::get('/pending', [SiamComChatbotEtaRequestController::class, 'getSiamComPendingEta'])->name('api.siam-com.eta.pending');
    Route::put('/update', [SiamComChatbotEtaRequestController::class, 'updateSiamComEtaRequest'])->name('api.siam-com.eta.update');
    Route::get('/all', [SiamComChatbotEtaRequestController::class, 'getAllSiamComEtaRequests'])->name('api.siam-com.eta.all'); // For testing
    
    // New endpoints for n8n workflow (using separate controller to bypass cache)
    Route::get('/get-attempts', [SiamComEtaAttemptController::class, 'getAttempts'])->name('api.siam-com.eta.get-attempts');
    Route::post('/increment-attempts', [SiamComEtaAttemptController::class, 'incrementAttempts'])->name('api.siam-com.eta.increment-attempts');
});

// LINE Webhook to capture Group ID (temporary)
Route::post('/line/webhook', function (Request $request) {
    \Illuminate\Support\Facades\Log::info('LINE Webhook received:', $request->all());
    
    $events = $request->input('events', []);
    
    foreach ($events as $event) {
        if (isset($event['source']['groupId'])) {
            $groupId = $event['source']['groupId'];
            \Illuminate\Support\Facades\Log::info('🎯 GROUP ID FOUND: ' . $groupId);
            
            // Optional: Store in database for easy retrieval
            try {
                \DB::table('siam_com_chatbot_eta_requests')->updateOrInsert(
                    ['group_id' => $groupId],
                    [
                        'group_id' => $groupId,
                        'status' => 'READY',
                        'last_asked_at' => '2000-01-01 00:00:00',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Database error: ' . $e->getMessage());
            }
        }
    }
    
    return response('OK', 200);
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


// DIAGNOSTIC TEST ROUTE
Route::get('/test-controller', function() {
    $controller = new \App\Http\Controllers\Api\SiamComChatbotEtaRequestController();
    $methods = get_class_methods($controller);
    return response()->json([
        'controller_class' => get_class($controller),
        'methods' => $methods,
        'has_getAttempts' => method_exists($controller, 'getAttempts'),
        'has_incrementAttempts' => method_exists($controller, 'incrementAttempts')
    ]);
});
