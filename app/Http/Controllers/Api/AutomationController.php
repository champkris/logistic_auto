<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AutomationController extends Controller
{
    /**
     * Store automation run summary
     */
    public function storeSummary(Request $request)
    {
        try {
            Log::info('🤖 Received automation summary', $request->all());
            
            $data = $request->validate([
                'run_id' => 'required|string',
                'started_at' => 'required|string',
                'completed_at' => 'required|string', 
                'duration_seconds' => 'required|numeric',
                'results' => 'required|array',
                'success_count' => 'required|integer',
                'total_count' => 'required|integer'
            ]);
            
            // Store automation run data in cache for dashboard
            $runData = [
                'run_id' => $data['run_id'],
                'started_at' => $data['started_at'],
                'completed_at' => $data['completed_at'],
                'duration_seconds' => $data['duration_seconds'],
                'success_count' => $data['success_count'],
                'total_count' => $data['total_count'],
                'success_rate' => $data['total_count'] > 0 ? ($data['success_count'] / $data['total_count']) * 100 : 0,
                'results' => $data['results']
            ];
            
            // Store latest run data
            Cache::put('automation:latest_run', $runData, now()->addHours(24));
            
            // Store run history (keep last 10 runs)
            $history = Cache::get('automation:run_history', []);
            array_unshift($history, $runData);
            $history = array_slice($history, 0, 10); // Keep only last 10 runs
            Cache::put('automation:run_history', $history, now()->addDays(7));
            
            // Update automation statistics
            $stats = Cache::get('automation:stats', [
                'total_runs' => 0,
                'total_successes' => 0,
                'total_failures' => 0,
                'average_duration' => 0,
                'last_run_at' => null
            ]);
            
            $stats['total_runs']++;
            $stats['total_successes'] += $data['success_count'];
            $stats['total_failures'] += ($data['total_count'] - $data['success_count']);
            $stats['average_duration'] = (($stats['average_duration'] * ($stats['total_runs'] - 1)) + $data['duration_seconds']) / $stats['total_runs'];
            $stats['last_run_at'] = $data['completed_at'];
            
            Cache::put('automation:stats', $stats, now()->addDays(30));
            
            Log::info("✅ Stored automation summary: {$data['success_count']}/{$data['total_count']} successful");
            
            return response()->json([
                'success' => true,
                'message' => 'Automation summary stored successfully',
                'data' => [
                    'run_id' => $data['run_id'],
                    'success_rate' => round($runData['success_rate'], 2),
                    'duration' => $data['duration_seconds'],
                    'processed' => $data['total_count'],
                    'successful' => $data['success_count']
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Error storing automation summary: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error storing automation summary: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get automation system status
     */
    public function getStatus()
    {
        try {
            $latestRun = Cache::get('automation:latest_run');
            $stats = Cache::get('automation:stats', [
                'total_runs' => 0,
                'total_successes' => 0,
                'total_failures' => 0,
                'average_duration' => 0,
                'last_run_at' => null
            ]);
            $history = Cache::get('automation:run_history', []);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $latestRun ? 'active' : 'inactive',
                    'latest_run' => $latestRun,
                    'statistics' => [
                        'total_runs' => $stats['total_runs'],
                        'success_rate' => $stats['total_runs'] > 0 
                            ? round(($stats['total_successes'] / ($stats['total_successes'] + $stats['total_failures'])) * 100, 2)
                            : 0,
                        'average_duration' => round($stats['average_duration'], 2),
                        'last_run_at' => $stats['last_run_at']
                    ],
                    'recent_runs' => array_slice($history, 0, 5) // Last 5 runs
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting automation status: ' . $e->getMessage()
            ], 500);
        }
    }
}
