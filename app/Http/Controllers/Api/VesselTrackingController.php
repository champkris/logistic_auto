<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vessel;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VesselTrackingController extends Controller
{
    /**
     * Update vessel data from browser automation scraper
     */
    public function updateVesselData(Request $request)
    {
        try {
            Log::info('🚢 Received vessel update from scraper', $request->all());
            
            $data = $request->validate([
                'success' => 'required|boolean',
                'terminal' => 'required|string',
                'vessel_name' => 'required|string',
                'voyage_code' => 'nullable|string',
                'voyage_out' => 'nullable|string', 
                'eta' => 'nullable|string',
                'etd' => 'nullable|string',
                'terminal_berth' => 'nullable|string',
                'raw_data' => 'nullable|array',
                'scraped_at' => 'required|string',
                'source' => 'required|string'
            ]);
            
            if (!$data['success']) {
                Log::warning('⚠️ Vessel scraping failed', $data);
                return response()->json([
                    'success' => false,
                    'message' => 'Vessel scraping was not successful'
                ], 400);
            }
            
            // Find or create the vessel
            $vessel = Vessel::where('name', $data['vessel_name'])->first();
            
            if (!$vessel) {
                // Create new vessel record
                $vessel = Vessel::create([
                    'name' => $data['vessel_name'],
                    'vessel_name' => $data['vessel_name'], // Also set vessel_name for consistency
                    'imo_number' => null, // Will be updated later if available
                    'port' => $data['terminal'],
                    'status' => 'scheduled' // Use existing enum value instead of 'tracked'
                ]);
                
                Log::info("✅ Created new vessel: {$data['vessel_name']}");
            }
            
            // Update vessel data with latest scraping results
            $updateData = [
                'port' => $data['terminal'],
                'status' => 'scheduled', // Use existing enum value instead of 'tracked'
                'last_scraped_at' => Carbon::parse($data['scraped_at']),
                'scraping_source' => $data['source']
            ];
            
            // Update ETA if provided
            if (!empty($data['eta'])) {
                $updateData['eta'] = Carbon::parse($data['eta']);
            }
            
            // Store voyage information in metadata
            $metadata = $vessel->metadata ?? [];
            
            // Extract terminal berth from raw_data if available
            $terminalBerth = $data['terminal_berth'] ?? 
                           ($data['raw_data']['terminal'] ?? null);
            
            $metadata['latest_scrape'] = [
                'voyage_code' => $data['voyage_code'],
                'voyage_out' => $data['voyage_out'],
                'terminal_berth' => $terminalBerth,
                'etd' => $data['etd'],
                'raw_data' => $data['raw_data'],
                'scraped_at' => $data['scraped_at']
            ];
            $updateData['metadata'] = $metadata;
            
            $vessel->update($updateData);
            
            // Update related shipments
            $updatedShipments = 0;
            $shipments = Shipment::where('vessel_id', $vessel->id)->get();
            
            foreach ($shipments as $shipment) {
                $shipmentUpdates = [];
                
                // Update ETA if vessel ETA changed
                if (!empty($data['eta']) && $shipment->planned_delivery_date != $vessel->eta) {
                    $shipmentUpdates['planned_delivery_date'] = $vessel->eta;
                }
                
                // Update status based on vessel status
                if ($shipment->status === 'planning' || $shipment->status === 'new') {
                    $shipmentUpdates['status'] = 'documents_preparation';
                }
                
                if (!empty($shipmentUpdates)) {
                    $shipment->update($shipmentUpdates);
                    $updatedShipments++;
                }
            }
            
            Log::info("✅ Updated vessel {$vessel->name} and {$updatedShipments} related shipments");
            
            return response()->json([
                'success' => true,
                'message' => 'Vessel data updated successfully',
                'data' => [
                    'vessel_id' => $vessel->id,
                    'vessel_name' => $vessel->name,
                    'eta' => $vessel->eta?->toISOString(),
                    'port' => $vessel->port,
                    'updated_shipments' => $updatedShipments,
                    'voyage_code' => $data['voyage_code'],
                    'terminal_berth' => $terminalBerth
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Error updating vessel data: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating vessel data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get vessel status by name or ID
     */
    public function getVesselStatus($vessel)
    {
        try {
            // Try to find vessel by ID or name
            $vesselRecord = is_numeric($vessel) 
                ? Vessel::findOrFail($vessel)
                : Vessel::where('name', $vessel)->firstOrFail();
            
            $shipments = Shipment::where('vessel_id', $vesselRecord->id)
                ->with('customer')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'vessel' => [
                        'id' => $vesselRecord->id,
                        'name' => $vesselRecord->name,
                        'imo_number' => $vesselRecord->imo_number,
                        'eta' => $vesselRecord->eta?->toISOString(),
                        'port' => $vesselRecord->port,
                        'status' => $vesselRecord->status,
                        'last_scraped_at' => $vesselRecord->last_scraped_at?->toISOString(),
                        'metadata' => $vesselRecord->metadata
                    ],
                    'shipments' => $shipments->map(function ($shipment) {
                        return [
                            'id' => $shipment->id,
                            'shipment_number' => $shipment->shipment_number,
                            'customer' => $shipment->customer->name,
                            'status' => $shipment->status,
                            'planned_delivery_date' => $shipment->planned_delivery_date
                        ];
                    })
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vessel not found: ' . $e->getMessage()
            ], 404);
        }
    }
}
