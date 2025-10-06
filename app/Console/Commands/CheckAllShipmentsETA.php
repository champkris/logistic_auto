<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shipment;
use App\Models\EtaCheckLog;
use App\Services\VesselTrackingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CheckAllShipmentsETA extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipments:check-eta
                            {--schedule-id= : Specific schedule ID that triggered this check}
                            {--limit=50 : Maximum number of shipments to check}
                            {--delay=30 : Delay in seconds between checks}
                            {--force : Force check all in-progress shipments regardless of recent checks}
                            {--report-id= : Report ID for real-time progress tracking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically check ETA for all active shipments';

    protected $vesselTrackingService;

    public function __construct()
    {
        parent::__construct();
        $this->vesselTrackingService = new VesselTrackingService();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = now();
        $this->info("🤖 Starting automated ETA check at {$startTime->format('Y-m-d H:i:s')}");

        $scheduleId = $this->option('schedule-id');
        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');
        $force = $this->option('force');
        $reportId = $this->option('report-id');

        if ($force) {
            $this->info("🚀 Force mode enabled - checking all in-progress shipments regardless of recent checks");
        }

        if ($reportId) {
            $this->info("📊 Real-time reporting enabled - Report ID: {$reportId}");
        }

        // Get shipments that need ETA checking
        $shipments = $this->getShipmentsForChecking($limit, $force);

        if ($shipments->isEmpty()) {
            $this->info("ℹ️  No shipments found requiring ETA check");
            return 0;
        }

        $this->info("📦 Found {$shipments->count()} shipments to check");

        $successCount = 0;
        $errorCount = 0;
        $progressBar = $this->output->createProgressBar($shipments->count());

        foreach ($shipments as $shipment) {
            $progressBar->advance();

            // Update progress for real-time reporting
            if ($reportId) {
                $this->updateShipmentProgress($reportId, $shipment->id, 'checking');
            }

            try {
                $result = $this->checkShipmentETA($shipment, $scheduleId);

                if ($result['success']) {
                    $successCount++;
                    $this->line("  ✅ {$shipment->invoice_number}: {$result['status']}");

                    // Update progress with success
                    if ($reportId) {
                        $this->updateShipmentProgress($reportId, $shipment->id, 'completed', [
                            'eta_found' => !empty($result['eta']),
                            'result' => $result['status'],
                            'checked_at' => now()->format('H:i:s')
                        ]);
                    }
                } else {
                    $errorCount++;
                    $this->line("  ❌ {$shipment->invoice_number}: {$result['error']}");

                    // Update progress with error
                    if ($reportId) {
                        $this->updateShipmentProgress($reportId, $shipment->id, 'error', [
                            'error_message' => $result['error'],
                            'checked_at' => now()->format('H:i:s')
                        ]);
                    }
                }

                // Add delay between checks to be polite to terminal websites
                if ($delay > 0 && !$shipments->last()->is($shipment)) {
                    sleep($delay);
                }

            } catch (\Exception $e) {
                $errorCount++;
                $this->error("  💥 {$shipment->invoice_number}: {$e->getMessage()}");

                // Update progress with exception
                if ($reportId) {
                    $this->updateShipmentProgress($reportId, $shipment->id, 'error', [
                        'error_message' => $e->getMessage(),
                        'checked_at' => now()->format('H:i:s')
                    ]);
                }

                Log::error("ETA check failed for shipment {$shipment->id}: " . $e->getMessage());
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $duration = $startTime->diffInSeconds(now());
        $this->info("✅ Automated ETA check completed");
        $this->info("📊 Results: {$successCount} successful, {$errorCount} failed");
        $this->info("⏱️  Duration: {$duration} seconds");

        // Log summary
        Log::info("Automated ETA check completed", [
            'schedule_id' => $scheduleId,
            'shipments_checked' => $shipments->count(),
            'successful' => $successCount,
            'failed' => $errorCount,
            'duration_seconds' => $duration
        ]);

        return 0;
    }

    /**
     * Get shipments that need ETA checking.
     */
    protected function getShipmentsForChecking($limit, $force = false)
    {
        $query = Shipment::with(['vessel', 'customer'])
            ->where('status', 'in-progress')
            ->whereNotNull('vessel_id') // Must have vessel
            ->whereNotNull('port_terminal'); // Must have terminal

        if (!$force) {
            // Normal mode: respect timing restrictions
            $query->where(function ($q) {
                // Either never checked, or last check was more than 4 hours ago
                $q->whereNull('last_eta_check_date')
                  ->orWhere('last_eta_check_date', '<', now()->subHours(4));
            })
            ->where(function ($q) {
                // Only check shipments with ETA in reasonable timeframe
                $q->where('planned_delivery_date', '>', now()->subDays(7))
                  ->where('planned_delivery_date', '<', now()->addDays(30));
            });
        }
        // Force mode: skip timing restrictions and check all in-progress shipments

        return $query->orderBy('planned_delivery_date', 'asc') // Check earliest ETAs first
            ->limit($limit)
            ->get();
    }

    /**
     * Update shipment progress for real-time reporting.
     */
    protected function updateShipmentProgress($reportId, $shipmentId, $status, $additionalData = [])
    {
        $cacheKey = "eta-report-progress-{$reportId}";
        $progress = Cache::get($cacheKey, []);

        $progress[$shipmentId] = array_merge([
            'status' => $status,
            'updated_at' => now()->format('H:i:s')
        ], $additionalData);

        Cache::put($cacheKey, $progress, now()->addHours(2));
    }

    /**
     * Check ETA for a single shipment.
     */
    protected function checkShipmentETA(Shipment $shipment, $scheduleId = null)
    {
        // Build vessel name for tracking
        $vesselName = $shipment->vessel ? $shipment->vessel->name : $shipment->vessel_code;
        $vesselFullName = $vesselName . ($shipment->voyage ? ' ' . $shipment->voyage : '');

        // Initialize log data
        $logData = [
            'shipment_id' => $shipment->id,
            'terminal' => $shipment->port_terminal,
            'vessel_name' => $vesselName,
            'voyage_code' => $shipment->voyage,
            'shipment_eta_at_time' => $shipment->planned_delivery_date,
            'initiated_by' => null, // System-initiated
        ];

        try {
            // Check ETA using the vessel tracking service
            $result = $this->vesselTrackingService->checkVesselETAByName($vesselFullName, $shipment->port_terminal);

            if ($result && $result['success']) {
                $updateData = [];
                $vesselFound = isset($result['vessel_found']) ? $result['vessel_found'] : $result['success'];

                // Store bot ETA if found
                if ($vesselFound && isset($result['eta']) && $result['eta']) {
                    try {
                        // Try to parse ETA with different formats
                        $etaString = $result['eta'];
                        $etaDate = null;

                        // Try common formats in order of preference
                        $formats = [
                            'Y-m-d',           // 2025-09-23
                            'm/d/Y',           // 09/23/2025 (US format)
                            'd/m/Y',           // 23/09/2025 (DD/MM/YYYY format from TIPS)
                            'Y/m/d',           // 2025/09/23
                            'd-m-Y',           // 23-09-2025
                            'Y-m-d H:i:s',     // 2025-09-23 08:00:00
                            'd/m/Y H:i',       // 23/09/2025 08:00
                        ];

                        // First try Carbon::parse (handles most standard formats)
                        try {
                            $etaDate = Carbon::parse($etaString);
                        } catch (\Exception $e) {
                            // If parse fails, try specific formats
                            foreach ($formats as $format) {
                                try {
                                    $etaDate = Carbon::createFromFormat($format, $etaString);
                                    break;
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                        }

                        if ($etaDate) {
                            $updateData['bot_received_eta_date'] = $etaDate;
                        }
                    } catch (\Exception $e) {
                        // Log ETA parsing errors for debugging
                        Log::warning("Failed to parse ETA date", [
                            'shipment_id' => $shipment->id,
                            'eta_string' => $result['eta'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Determine tracking status
                // Both vessel AND voyage must be found to proceed with comparison
                $voyageFound = isset($result['voyage_found']) ? $result['voyage_found'] : false;
                if ($vesselFound && $voyageFound && isset($result['eta']) && $result['eta']) {
                    try {
                        // Use the same ETA parsing logic as above
                        $etaString = $result['eta'];
                        $scrapedEta = null;

                        // First try Carbon::parse (handles most standard formats)
                        try {
                            $scrapedEta = Carbon::parse($etaString);
                        } catch (\Exception $e) {
                            // If parse fails, try specific formats
                            $formats = [
                                'Y-m-d',           // 2025-09-23
                                'm/d/Y',           // 09/23/2025 (US format)
                                'd/m/Y',           // 23/09/2025 (DD/MM/YYYY format from TIPS)
                                'Y/m/d',           // 2025/09/23
                                'd-m-Y',           // 23-09-2025
                                'Y-m-d H:i:s',     // 2025-09-23 08:00:00
                                'd/m/Y H:i',       // 23/09/2025 08:00
                            ];

                            foreach ($formats as $format) {
                                try {
                                    $scrapedEta = Carbon::createFromFormat($format, $etaString);
                                    break;
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                        }

                        if ($scrapedEta) {
                            $shipmentEta = $shipment->planned_delivery_date;
                            if ($shipmentEta) {
                                // Compare dates with tolerance of 1 day for "on_track"
                                $daysDifference = $scrapedEta->diffInDays($shipmentEta, false);

                                if ($scrapedEta->lt($shipmentEta)) {
                                    // Scraped ETA is earlier than planned
                                    $updateData['tracking_status'] = 'early';
                                } elseif ($scrapedEta->equalTo($shipmentEta) || $daysDifference <= 1) {
                                    // Same day or within 1 day tolerance
                                    $updateData['tracking_status'] = 'on_track';
                                } else {
                                    // Scraped ETA is later than planned
                                    $updateData['tracking_status'] = 'delay';
                                }
                            } else {
                                $updateData['tracking_status'] = 'on_track';
                            }

                            // Clear departed flag when vessel is found again
                            $updateData['is_departed'] = false;
                        } else {
                            $updateData['tracking_status'] = 'on_track';
                            $updateData['is_departed'] = false;
                        }
                    } catch (\Exception $e) {
                        $updateData['tracking_status'] = 'on_track';
                    }
                } else {
                    // Either vessel or voyage (or both) not found
                    // Check if vessel was previously found (departed detection)
                    $lastSuccessfulCheck = \App\Models\EtaCheckLog::where('shipment_id', $shipment->id)
                        ->where('vessel_found', true)
                        ->where('voyage_found', true)
                        ->whereNotNull('updated_eta')
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($lastSuccessfulCheck) {
                        // Vessel was found before but not now - likely departed
                        // Keep the last tracking status (early/on_track/delay) but mark as departed
                        $lastStatus = $lastSuccessfulCheck->tracking_status;

                        // If last status was one of the trackable statuses, keep it and add departed flag
                        if (in_array($lastStatus, ['early', 'on_track', 'delay'])) {
                            $updateData['tracking_status'] = $lastStatus;
                            $updateData['is_departed'] = true;
                        } else {
                            // Fallback to departed if last status was not trackable
                            $updateData['tracking_status'] = 'departed';
                        }

                        // Keep the last known ETA
                        if (!isset($updateData['bot_received_eta_date']) && $lastSuccessfulCheck->updated_eta) {
                            $updateData['bot_received_eta_date'] = $lastSuccessfulCheck->updated_eta;
                        }
                    } else {
                        // Never found before
                        $updateData['tracking_status'] = 'not_found';
                    }
                }

                // Update shipment
                $updateData['last_eta_check_date'] = now();
                $shipment->update($updateData);

                // Log the check
                $logData = array_merge($logData, [
                    'updated_eta' => isset($updateData['bot_received_eta_date']) ? $updateData['bot_received_eta_date'] : null,
                    'tracking_status' => $updateData['tracking_status'],
                    'vessel_found' => $vesselFound,
                    'voyage_found' => $result['voyage_found'] ?? false,
                    'raw_response' => $result,
                ]);

                EtaCheckLog::create($logData);

                return [
                    'success' => true,
                    'status' => $updateData['tracking_status'],
                    'eta' => $result['eta'] ?? null
                ];

            } else {
                // Failed to get ETA
                $errorMessage = $result['error'] ?? 'Unknown error during vessel tracking';

                $shipment->update([
                    'tracking_status' => 'not_found',
                    'last_eta_check_date' => now()
                ]);

                // Log the failed check
                $logData = array_merge($logData, [
                    'tracking_status' => 'not_found',
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'error_message' => $errorMessage,
                    'raw_response' => $result,
                ]);

                EtaCheckLog::create($logData);

                return [
                    'success' => false,
                    'error' => $errorMessage
                ];
            }

        } catch (\Exception $e) {
            // Exception during ETA check
            $shipment->update([
                'tracking_status' => 'not_found',
                'last_eta_check_date' => now()
            ]);

            // Log the exception
            $logData = array_merge($logData, [
                'tracking_status' => 'not_found',
                'vessel_found' => false,
                'voyage_found' => false,
                'error_message' => 'Exception: ' . $e->getMessage(),
            ]);

            EtaCheckLog::create($logData);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
