<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiamComChatbotEtaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SiamComEtaAttemptController extends Controller
{
    /**
     * Get current attempt count for a group
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

            $etaRequest->increment('attempts');
            $etaRequest->touch();
            
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
