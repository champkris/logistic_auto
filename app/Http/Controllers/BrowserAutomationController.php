<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Vessel;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BrowserAutomationController extends Controller
{
    /**
     * Receive vessel data from browser automation
     */
    public function updateVesselFromBrowser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'terminal' => 'required|string',
            'vessel_name' => 'required|string',
            'voyage_code' => 'nullable|string',
            'voyage_out' => 'nullable|string',
            'eta' => 'nullable|date_format:Y-m-d H:i:s',
            'etd' => 'nullable|date_format:Y-m-d H:i:s',
            'source' => 'required|string',
            'scraped_at' => 'required|date',
            'success' => 'required|boolean',
            'raw_data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        Log::info('Browser automation data received', [
            'terminal' => $data['terminal'],
            'vessel' => $data['vessel_name'],
            'source' => $data['source']
        ]);

        try {
            if ($data['success']) {
                // Update or create vessel
                $vessel = Vessel::updateOrCreate(
                    [
                        'vessel_name' => $data['vessel_name'],
                        'voyage_number' => $data['voyage_code']
                    ],
                    [
                        'full_vessel_name' => $data['vessel_name'] . ' ' . ($data['voyage_code'] ?? ''),
                        'eta' => $data['eta'] ? Carbon::parse($data['eta']) : null,
                        'actual_arrival' => $data['eta'] && Carbon::parse($data['eta'])->isPast() 
                            ? Carbon::parse($data['eta']) : null,
                        'port' => $data['terminal'],
                        'status' => $data['eta'] && Carbon::parse($data['eta'])->isPast() 
                            ? 'arrived' : 'scheduled',
                        'agent' => $data['source'],
                        'notes' => 'Updated via browser automation: ' . $data['source']
                    ]
                );

                // Update related shipments
                $shipments = Shipment::where('vessel_id', $vessel->id)->get();
                foreach ($shipments as $shipment) {
                    if ($vessel->status === 'arrived' && !$shipment->actual_delivery_date) {
                        $shipment->update(['status' => 'ready_for_delivery']);
                    }
                }

                $message = "Vessel {$data['vessel_name']} updated successfully";
                
            } else {
                $message = "Browser automation failed for {$data['vessel_name']}: " . 
                          ($request->input('error', 'Unknown error'));
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'vessel_id' => isset($vessel) ? $vessel->id : null,
                'status' => isset($vessel) ? $vessel->status : null,
                'updated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Browser automation update failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update vessel data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Receive automation run summary
     */
    public function receiveAutomationSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'run_id' => 'required|string',
            'started_at' => 'required|date',
            'completed_at' => 'required|date',
            'duration_seconds' => 'required|integer',
            'results' => 'required|array',
            'success_count' => 'required|integer',
            'total_count' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $summary = $validator->validated();
        
        Log::info('Browser automation summary received', [
            'run_id' => $summary['run_id'],
            'success_rate' => $summary['success_count'] . '/' . $summary['total_count'],
            'duration' => $summary['duration_seconds'] . 's'
        ]);

        // Store automation run log (you might want to create an AutomationRun model)
        // For now, just log it
        Log::channel('automation')->info('Automation Run Completed', $summary);

        return response()->json([
            'success' => true,
            'message' => 'Automation summary received',
            'run_id' => $summary['run_id']
        ]);
    }

    /**
     * Get browser automation status
     */
    public function getAutomationStatus()
    {
        // Return status of browser-dependent terminals
        $browserTerminals = [
            'LCB1' => [
                'name' => 'LCB1',
                'vessel' => 'MARSA PRIDE',
                'status' => 'browser_automation',
                'last_update' => $this->getLastUpdateTime('LCB1')
            ],
            'LCIT' => [
                'name' => 'LCIT', 
                'vessel' => 'ASL QINGDAO',
                'status' => 'pending_implementation',
                'last_update' => null
            ],
            'ECTT' => [
                'name' => 'ECTT',
                'vessel' => 'EVER BUILD', 
                'status' => 'pending_implementation',
                'last_update' => null
            ]
        ];

        return response()->json([
            'browser_terminals' => $browserTerminals,
            'summary' => [
                'total' => count($browserTerminals),
                'active' => collect($browserTerminals)->where('status', 'browser_automation')->count(),
                'last_run' => $this->getLastAutomationRun()
            ]
        ]);
    }

    private function getLastUpdateTime($terminal)
    {
        $vessel = Vessel::where('port', $terminal)
            ->where('agent', 'like', '%browser%')
            ->orderBy('updated_at', 'desc')
            ->first();
            
        return $vessel ? $vessel->updated_at->toISOString() : null;
    }

    private function getLastAutomationRun()
    {
        // In a real implementation, you'd query an automation_runs table
        // For now, return the most recent vessel update from browser automation
        $vessel = Vessel::where('agent', 'like', '%browser%')
            ->orderBy('updated_at', 'desc')
            ->first();
            
        return $vessel ? $vessel->updated_at->toISOString() : null;
    }
}
