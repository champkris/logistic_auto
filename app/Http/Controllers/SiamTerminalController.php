<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\SiamComChatbotEtaRequest;

class SiamTerminalController extends Controller
{
    /**
     * Start SIAM Commercial chatbot request
     * Triggers n8n workflow for vessel ETA inquiry via LINE chatbot
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function startChatbotRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'vessel_name' => 'required|string|max:255',
                'voyage_code' => 'required|string|max:100'
            ]);

            $vesselName = trim($validated['vessel_name']);
            $voyageCode = trim($validated['voyage_code']);

            // Trigger n8n workflow
            $n8nWebhookUrl = env('N8N_SIAM_WEBHOOK_URL', 'http://localhost:5678/webhook/siam-com-eta');
            
            $response = Http::timeout(10)->post($n8nWebhookUrl, [
                'vessel_name' => $vesselName,
                'voyage_code' => $voyageCode
            ]);

            if (!$response->successful()) {
                \Log::error('SIAM n8n webhook failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Failed to trigger n8n workflow',
                    'status' => 'error'
                ], 500);
            }

            $n8nResponse = $response->json();

            // Check if we got cached data
            if (isset($n8nResponse['action']) && $n8nResponse['action'] === 'return_cached') {
                return response()->json([
                    'success' => true,
                    'status' => 'cached',
                    'message' => 'Using cached ETA data',
                    'data' => [
                        'vessel_name' => $vesselName,
                        'voyage_code' => $voyageCode,
                        'eta' => $n8nResponse['data']['eta'] ?? null,
                        'hours_ago' => $n8nResponse['data']['hours_ago'] ?? 0,
                        'terminal' => 'Siam Commercial',
                        'vessel_found' => true,
                        'voyage_found' => true
                    ]
                ]);
            }

            // Chatbot is asking - client should poll for results
            return response()->json([
                'success' => true,
                'status' => 'asking',
                'message' => 'Chatbot is contacting Siam Commercial admin via LINE...',
                'data' => [
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'terminal' => 'Siam Commercial',
                    'estimated_wait_time' => '1-5 minutes'
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'validation_errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('SIAM chatbot start error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to start SIAM chatbot: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Poll for SIAM chatbot results
     * Checks database for chatbot status and ETA data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pollChatbotStatus(Request $request)
    {
        try {
            $vesselName = $request->query('vessel_name');
            $voyageCode = $request->query('voyage_code');

            if (!$vesselName || !$voyageCode) {
                return response()->json([
                    'success' => false,
                    'error' => 'vessel_name and voyage_code are required'
                ], 400);
            }

            // Get hardcoded SIAM group ID from environment
            $groupId = env('SIAM_COM_LINE_GROUP_ID', 'siam_com_line_group_C123456789');

            // Query database for latest chatbot status
            $etaRequest = SiamComChatbotEtaRequest::where('group_id', $groupId)
                ->where('vessel_name', $vesselName)
                ->where('voyage_code', $voyageCode)
                ->latest('updated_at')
                ->first();

            // No record found - chatbot still initializing
            if (!$etaRequest) {
                return response()->json([
                    'success' => true,
                    'status' => 'asking',
                    'message' => 'Chatbot is still asking...',
                    'attempts' => 1
                ]);
            }

            // Check status and return appropriate response
            switch ($etaRequest->status) {
                case 'COMPLETE':
                    return response()->json([
                        'success' => true,
                        'status' => 'complete',
                        'message' => 'ETA received from Siam Commercial admin',
                        'data' => [
                            'vessel_name' => $etaRequest->vessel_name,
                            'voyage_code' => $etaRequest->voyage_code,
                            'eta' => $etaRequest->last_known_eta,
                            'terminal' => 'Siam Commercial',
                            'vessel_found' => true,
                            'voyage_found' => true,
                            'search_method' => 'n8n_chatbot',
                            'checked_at' => $etaRequest->updated_at->format('Y-m-d H:i:s')
                        ]
                    ]);

                case 'FAILED':
                    return response()->json([
                        'success' => true,
                        'status' => 'failed',
                        'message' => 'Failed to get ETA from Siam Commercial admin',
                        'data' => [
                            'vessel_name' => $etaRequest->vessel_name,
                            'voyage_code' => $etaRequest->voyage_code,
                            'terminal' => 'Siam Commercial',
                            'vessel_found' => false,
                            'error' => 'No response from admin after multiple attempts'
                        ]
                    ]);

                case 'PENDING':
                default:
                    $attempts = $etaRequest->attempts ?? 1;
                    $elapsedSeconds = $etaRequest->last_asked_at ? 
                        now()->diffInSeconds($etaRequest->last_asked_at) : 0;

                    return response()->json([
                        'success' => true,
                        'status' => 'asking',
                        'message' => "Chatbot is asking Siam Commercial admin... (attempt {$attempts}/5)",
                        'attempts' => $attempts,
                        'elapsed_time' => $elapsedSeconds
                    ]);
            }

        } catch (\Exception $e) {
            \Log::error('SIAM poll error', [
                'error' => $e->getMessage(),
                'vessel_name' => $request->query('vessel_name'),
                'voyage_code' => $request->query('voyage_code')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to poll SIAM status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SIAM terminal configuration
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfig()
    {
        return response()->json([
            'terminal' => 'SIAM',
            'name' => 'Siam Commercial',
            'method' => 'n8n_chatbot',
            'default_vessel' => 'MAKHA BHUM',
            'default_voyage' => '119S',
            'polling_interval' => 5000, // milliseconds
            'max_wait_time' => 300, // seconds (5 minutes)
            'cache_duration' => 3, // hours
            'requires_voyage_code' => true
        ]);
    }
}
