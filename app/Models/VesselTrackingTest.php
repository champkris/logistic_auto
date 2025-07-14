<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class VesselTrackingTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint_key',
        'endpoint_name',
        'endpoint_url',
        'status',
        'status_code',
        'response_time',
        'content_type',
        'content_length',
        'accessible',
        'has_vessel_data',
        'content_analysis',
        'automation_potential',
        'error_message',
        'raw_response',
        'tested_at'
    ];

    protected $casts = [
        'accessible' => 'boolean',
        'has_vessel_data' => 'boolean',
        'content_analysis' => 'array',
        'automation_potential' => 'array',
        'tested_at' => 'datetime',
        'response_time' => 'decimal:2'
    ];

    /**
     * Get the latest test result for each endpoint
     */
    public static function getLatestResults()
    {
        return self::select('vessel_tracking_tests.*')
            ->from('vessel_tracking_tests')
            ->join(\DB::raw('(SELECT endpoint_key, MAX(tested_at) as max_tested_at FROM vessel_tracking_tests GROUP BY endpoint_key) latest'), function($join) {
                $join->on('vessel_tracking_tests.endpoint_key', '=', 'latest.endpoint_key')
                     ->on('vessel_tracking_tests.tested_at', '=', 'latest.max_tested_at');
            })
            ->orderBy('endpoint_name')
            ->get();
    }

    /**
     * Get success rate for an endpoint
     */
    public static function getSuccessRate($endpointKey, $days = 7)
    {
        $total = self::where('endpoint_key', $endpointKey)
            ->where('tested_at', '>=', Carbon::now()->subDays($days))
            ->count();

        if ($total === 0) {
            return 0;
        }

        $successful = self::where('endpoint_key', $endpointKey)
            ->where('tested_at', '>=', Carbon::now()->subDays($days))
            ->whereIn('status', ['success', 'accessible'])
            ->count();

        return round(($successful / $total) * 100, 2);
    }

    /**
     * Get average response time for an endpoint
     */
    public static function getAverageResponseTime($endpointKey, $days = 7)
    {
        return self::where('endpoint_key', $endpointKey)
            ->where('tested_at', '>=', Carbon::now()->subDays($days))
            ->whereNotNull('response_time')
            ->avg('response_time') ?? 0;
    }

    /**
     * Create test result from service response
     */
    public static function createFromResult(array $result)
    {
        return self::create([
            'endpoint_key' => $result['endpoint_key'],
            'endpoint_name' => $result['name'],
            'endpoint_url' => $result['url'],
            'status' => $result['status'],
            'status_code' => $result['status_code'] ?? null,
            'response_time' => $result['response_time'] ?? null,
            'content_type' => $result['content_type'] ?? null,
            'content_length' => $result['content_length'] ?? null,
            'accessible' => $result['accessible'] ?? false,
            'has_vessel_data' => $result['has_vessel_data'] ?? false,
            'content_analysis' => $result['content_analysis'] ?? null,
            'automation_potential' => $result['automation_potential'] ?? null,
            'error_message' => $result['error'] ?? null,
            'raw_response' => $result['raw_response'] ?? null,
            'tested_at' => $result['timestamp'] ?? now()
        ]);
    }
}