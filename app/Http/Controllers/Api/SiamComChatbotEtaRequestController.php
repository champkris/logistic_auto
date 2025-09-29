<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiamComChatbotEtaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SiamComChatbotEtaRequestController extends Controller
{
    /**
     * Start Siam Com ETA request process (from n8n HTTP Request)
     */
    public function startSiamComEtaRequest(Request $request)
    {
        $data = $request->validate([
            'vessel_name' => 'required|string|max:255',
            'voyage_code' => 'required|string|max:100'
            // Removed group_id from validation - it's now hardcoded
        ]);

        try {
            // Hardcoded Siam Com LINE group ID - never changes
            $groupId = env('SIAM_COM_LINE_GROUP_ID', 'siam_com_line_group_C123456789');
            
            // Find or create Siam Com ETA request
            $etaRequest = SiamComChatbotEtaRequest::where('group_id', $groupId)->first();
            
            if (!$etaRequest) {
                $etaRequest = SiamComChatbotEtaRequest::create([
                    'group_id' => $groupId,
                    'vessel_name' => $data['vessel_name'],
                    'voyage_code' => $data['voyage_code'],
                    'status' => 'READY'
                ]);
            }

            // Check if we should ask new question (enhanced logic)
            $shouldAskNew = $etaRequest->shouldAskNew(3); // 3 hours rate limit
            $askReason = $etaRequest->getAskNewReason(3);

            if ($shouldAskNew) {
                // Update with new request data
                $etaRequest->update([
                    'vessel_name' => $data['vessel_name'],
                    'voyage_code' => $data['voyage_code'],
                    'status' => 'PENDING',
                    'last_asked_at' => now(),
                    'conversation_history' => []
                ]);

                return response()->json([
                    'success' => true,
                    'action' => 'ask_new',
                    'message' => 'Starting new Siam Com ETA request to LINE group',
                    'reason' => $askReason, // never_asked, no_eta_data, or time_expired
                    'company' => 'Siam Com',
                    'data' => [
                        'group_id' => $groupId,
                        'vessel_name' => $data['vessel_name'],
                        'voyage_code' => $data['voyage_code'],
                        'status' => 'PENDING',
                        'should_ask_line' => true,
                        'ask_reason' => $askReason
                    ]
                ]);
            } else {
                // Return cached data
                $hoursAgo = $etaRequest->getHoursSinceLastRequest();
                
                return response()->json([
                    'success' => true,
                    'action' => 'return_cached',
                    'message' => "Using cached Siam Com ETA data (asked {$hoursAgo} hours ago)",
                    'company' => 'Siam Com',
                    'data' => [
                        'group_id' => $groupId,
                        'vessel_name' => $etaRequest->vessel_name,
                        'voyage_code' => $etaRequest->voyage_code,
                        'eta' => $etaRequest->last_known_eta,
                        'hours_ago' => $hoursAgo,
                        'should_ask_line' => false
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Siam Com ETA Request Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing Siam Com ETA request: ' . $e->getMessage(),
                'company' => 'Siam Com'
            ], 500);
        }
    }

    /**
     * Get pending Siam Com ETA request for LINE chat processing (n8n Workflow B)
     */
    public function getSiamComPendingEta(Request $request)
    {
        // Hardcoded Siam Com LINE group ID
        $groupId = env('SIAM_COM_LINE_GROUP_ID', 'siam_com_line_group_C123456789');
        
        $etaRequest = SiamComChatbotEtaRequest::where('group_id', $groupId)
            ->where('status', 'PENDING')
            ->first();

        if (!$etaRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No pending Siam Com ETA request found for this group',
                'company' => 'Siam Com'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'company' => 'Siam Com',
            'data' => [
                'group_id' => $etaRequest->group_id,
                'vessel_name' => $etaRequest->vessel_name,
                'voyage_code' => $etaRequest->voyage_code,
                'status' => $etaRequest->status,
                'conversation_history' => $etaRequest->conversation_history ?? [],
                'question_to_ask' => "มีใครรู้ ETA ของเรือ {$etaRequest->vessel_name} voyage {$etaRequest->voyage_code} ไหมครับ? (สำหรับ Siam Com)"
            ]
        ]);
    }

    /**
     * Update Siam Com ETA request with results from LINE conversation
     */
    public function updateSiamComEtaRequest(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|string',
            'status' => 'required|in:PENDING,COMPLETE,FAILED',
            'eta' => 'nullable|string',
            'conversation_message' => 'nullable|string'
        ]);

        try {
            $etaRequest = SiamComChatbotEtaRequest::where('group_id', $data['group_id'])
                ->where('status', 'PENDING')
                ->first();

            if (!$etaRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending Siam Com ETA request found for this group',
                    'company' => 'Siam Com'
                ], 404);
            }

            // Add conversation if provided
            if (!empty($data['conversation_message'])) {
                $etaRequest->addConversation($data['conversation_message']);
            }

            // Update status and ETA
            $updateData = ['status' => $data['status']];
            
            if ($data['status'] === 'COMPLETE' && !empty($data['eta'])) {
                $updateData['last_known_eta'] = $data['eta'];
                // Clear conversation history on completion
                $updateData['conversation_history'] = [];
            }

            $etaRequest->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Siam Com ETA request updated successfully',
                'company' => 'Siam Com',
                'data' => [
                    'group_id' => $etaRequest->group_id,
                    'vessel_name' => $etaRequest->vessel_name,
                    'voyage_code' => $etaRequest->voyage_code,
                    'status' => $etaRequest->status,
                    'eta' => $etaRequest->last_known_eta,
                    'final_result' => $data['status'] === 'COMPLETE' ? [
                        'company' => 'Siam Com',
                        'vessel_name' => $etaRequest->vessel_name,
                        'voyage_code' => $etaRequest->voyage_code,
                        'eta' => $etaRequest->last_known_eta
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Siam Com ETA Update Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating Siam Com ETA request: ' . $e->getMessage(),
                'company' => 'Siam Com'
            ], 500);
        }
    }

    /**
     * Get all Siam Com ETA requests (for testing/debugging)
     */
    public function getAllSiamComEtaRequests()
    {
        $requests = SiamComChatbotEtaRequest::orderBy('updated_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'company' => 'Siam Com',
            'data' => $requests
        ]);
    }
}

class EtaRequestController extends Controller
{
    /**
     * Start ETA request process (from n8n Form submission)
     */
    public function startRequest(Request $request)
    {
        $data = $request->validate([
            'vessel_name' => 'required|string|max:255',
            'voyage_number' => 'required|string|max:100',
            'group_id' => 'string|max:100' // Optional, will use default if not provided
        ]);

        try {
            // Use default group ID if not provided
            $groupId = $data['group_id'] ?? 'default_line_group';
            
            // Find or create ETA request
            $etaRequest = SiamComChatbotEtaRequest::where('group_id', $groupId)->first();
            
            if (!$etaRequest) {
                $etaRequest = SiamComChatbotEtaRequest::create([
                    'group_id' => $groupId,
                    'vessel_name' => $data['vessel_name'],
                    'voyage_number' => $data['voyage_number'],
                    'status' => 'READY'
                ]);
            }

            // Check if we should ask new question (rate limiting)
            $shouldAskNew = $etaRequest->shouldAskNew(3); // 3 hours rate limit

            if ($shouldAskNew) {
                // Update with new request data
                $etaRequest->update([
                    'vessel_name' => $data['vessel_name'],
                    'voyage_number' => $data['voyage_number'],
                    'status' => 'PENDING',
                    'last_asked_at' => now(),
                    'conversation_history' => []
                ]);

                return response()->json([
                    'success' => true,
                    'action' => 'ask_new',
                    'message' => 'Starting new ETA request to LINE group',
                    'data' => [
                        'group_id' => $groupId,
                        'vessel_name' => $data['vessel_name'],
                        'voyage_number' => $data['voyage_number'],
                        'status' => 'PENDING',
                        'should_ask_line' => true
                    ]
                ]);
            } else {
                // Return cached data
                $hoursAgo = $etaRequest->getHoursSinceLastRequest();
                
                return response()->json([
                    'success' => true,
                    'action' => 'return_cached',
                    'message' => "Using cached ETA data (asked {$hoursAgo} hours ago)",
                    'data' => [
                        'group_id' => $groupId,
                        'vessel_name' => $etaRequest->vessel_name,
                        'voyage_number' => $etaRequest->voyage_number,
                        'eta' => $etaRequest->last_known_eta,
                        'hours_ago' => $hoursAgo,
                        'should_ask_line' => false
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ETA Request Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing ETA request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending ETA request for LINE chat processing (n8n Workflow B)
     */
    public function getPending(Request $request)
    {
        $groupId = $request->query('group_id', 'default_line_group');
        
        $etaRequest = SiamComChatbotEtaRequest::where('group_id', $groupId)
            ->where('status', 'PENDING')
            ->first();

        if (!$etaRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No pending ETA request found for this group'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'group_id' => $etaRequest->group_id,
                'vessel_name' => $etaRequest->vessel_name,
                'voyage_number' => $etaRequest->voyage_number,
                'status' => $etaRequest->status,
                'conversation_history' => $etaRequest->conversation_history ?? [],
                'question_to_ask' => "มีใครรู้ ETA ของเรือ {$etaRequest->vessel_name} voyage {$etaRequest->voyage_number} ไหมครับ?"
            ]
        ]);
    }

    /**
     * Update ETA request with results from LINE conversation
     */
    public function updateRequest(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|string',
            'status' => 'required|in:PENDING,COMPLETE,FAILED',
            'eta' => 'nullable|string',
            'conversation_message' => 'nullable|string'
        ]);

        try {
            $etaRequest = SiamComChatbotEtaRequest::where('group_id', $data['group_id'])
                ->where('status', 'PENDING')
                ->first();

            if (!$etaRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending ETA request found for this group'
                ], 404);
            }

            // Add conversation if provided
            if (!empty($data['conversation_message'])) {
                $etaRequest->addConversation($data['conversation_message']);
            }

            // Update status and ETA
            $updateData = ['status' => $data['status']];
            
            if ($data['status'] === 'COMPLETE' && !empty($data['eta'])) {
                $updateData['last_known_eta'] = $data['eta'];
                // Clear conversation history on completion
                $updateData['conversation_history'] = [];
            }

            $etaRequest->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'ETA request updated successfully',
                'data' => [
                    'group_id' => $etaRequest->group_id,
                    'vessel_name' => $etaRequest->vessel_name,
                    'voyage_number' => $etaRequest->voyage_number,
                    'status' => $etaRequest->status,
                    'eta' => $etaRequest->last_known_eta,
                    'final_result' => $data['status'] === 'COMPLETE' ? [
                        'vessel_name' => $etaRequest->vessel_name,
                        'voyage_number' => $etaRequest->voyage_number,
                        'eta' => $etaRequest->last_known_eta
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('ETA Update Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating ETA request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all ETA requests (for testing/debugging)
     */
    public function getAllRequests()
    {
        $requests = SiamComChatbotEtaRequest::orderBy('updated_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Get current attempt count for a group
     * Used by n8n workflow to check if should continue asking
     */
    public function getAttempts(Request $request)
    {
        try {
            $groupId = $request->query('group_id');
            
            if (!$groupId) {
                return response()->json([
                    'success' => false,
                    'message' => 'group_id is required'
                ], 400);
            }

            $etaRequest = SiamComChatbotEtaRequest::where('group_id', $groupId)
                ->where('status', 'PENDING')
                ->latest('updated_at')
                ->first();
            
            if (!$etaRequest) {
                return response()->json([
                    'success' => true,
                    'attempts' => 0,
                    'found' => false,
                    'message' => 'No active ETA request found for this group'
                ]);
            }
            
            return response()->json([
                'success' => true,
                'attempts' => $etaRequest->attempts ?? 1,
                'found' => true,
                'vessel_name' => $etaRequest->vessel_name,
                'voyage_code' => $etaRequest->voyage_code,
                'last_asked_at' => $etaRequest->last_asked_at
            ]);

        } catch (\Exception $e) {
            Log::error('Get Attempts Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving attempts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Increment attempt count after sending follow-up question
     * Used by n8n workflow after sending a follow-up message to LINE
     */
    public function incrementAttempts(Request $request)
    {
        try {
            $groupId = $request->input('group_id');
            
            if (!$groupId) {
                return response()->json([
                    'success' => false,
                    'message' => 'group_id is required'
                ], 400);
            }

            $etaRequest = SiamComChatbotEtaRequest::where('group_id', $groupId)
                ->where('status', 'PENDING')
                ->latest('updated_at')
                ->first();
            
            if (!$etaRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active ETA request found for this group'
                ], 404);
            }

            // Increment attempts
            $etaRequest->increment('attempts');
            $etaRequest->touch(); // Update updated_at timestamp
            
            return response()->json([
                'success' => true,
                'message' => 'Attempt count incremented',
                'attempts' => $etaRequest->attempts,
                'vessel_name' => $etaRequest->vessel_name,
                'voyage_code' => $etaRequest->voyage_code
            ]);

        } catch (\Exception $e) {
            Log::error('Increment Attempts Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error incrementing attempts: ' . $e->getMessage()
            ], 500);
        }
    }
}
