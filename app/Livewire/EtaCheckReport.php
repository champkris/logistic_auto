<?php

namespace App\Livewire;

use App\Models\Shipment;
use Livewire\Component;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EtaCheckReport extends Component
{
    public $reportId;
    public $shipments = [];
    public $totalShipments = 0;
    public $completedCount = 0;
    public $successCount = 0;
    public $errorCount = 0;
    public $isRunning = false;
    public $startTime;
    public $endTime;

    public function mount()
    {
        $this->reportId = 'eta-check-' . now()->format('YmdHis') . '-' . uniqid();
        $this->startTime = now();
        $this->initializeReport();
        $this->startEtaCheck();
    }

    public function initializeReport()
    {
        // Get all shipments that need ETA checking
        $shipmentQuery = Shipment::with(['vessel', 'customer'])
            ->where('status', 'in-progress')
            ->whereNotNull('vessel_id')
            ->whereNotNull('port_terminal')
            ->orderBy('planned_delivery_date', 'asc')
            ->limit(100);

        $allShipments = $shipmentQuery->get();

        $this->totalShipments = $allShipments->count();

        // Initialize shipment tracking data
        foreach ($allShipments as $shipment) {
            $this->shipments[] = [
                'id' => $shipment->id,
                'invoice_number' => $shipment->invoice_number,
                'vessel_name' => $shipment->vessel ? $shipment->vessel->name : $shipment->vessel_code,
                'voyage' => $shipment->voyage,
                'port_terminal' => $shipment->port_terminal,
                'customer_name' => $shipment->customer ? $shipment->customer->company : 'Unknown',
                'planned_delivery_date' => $shipment->planned_delivery_date,
                'status' => 'pending', // pending, checking, completed, error
                'result' => null,
                'error_message' => null,
                'checked_at' => null,
                'eta_found' => null,
            ];
        }

        // Only set running if there are shipments to check
        $this->isRunning = $this->totalShipments > 0;
    }

    public function startEtaCheck()
    {
        // Don't start if there are no shipments to check
        if ($this->totalShipments === 0) {
            $this->isRunning = false;
            $this->endTime = now();
            Log::info("ETA check report completed immediately - no shipments found", ['report_id' => $this->reportId]);
            return;
        }

        try {
            // Store report data in cache for the command to access
            Cache::put("eta-report-{$this->reportId}", [
                'shipments' => collect($this->shipments)->pluck('id')->toArray(),
                'started_at' => now(),
                'status' => 'running'
            ], now()->addHours(2));

            // Start the ETA check command in background
            Artisan::call('shipments:check-eta', [
                '--limit' => 100,
                '--delay' => 2, // Faster for better UX
                '--force' => true,
                '--report-id' => $this->reportId
            ]);

            Log::info("Started ETA check report", ['report_id' => $this->reportId]);

        } catch (\Exception $e) {
            Log::error("Failed to start ETA check", ['error' => $e->getMessage()]);
            $this->isRunning = false;
            $this->endTime = now();
        }
    }

    public function refreshProgress()
    {
        if (!$this->isRunning) {
            return;
        }

        // Get progress from cache
        $progress = Cache::get("eta-report-progress-{$this->reportId}", []);

        if (empty($progress)) {
            return;
        }

        // Update shipment statuses based on progress
        foreach ($this->shipments as $index => $shipment) {
            $shipmentId = $shipment['id'];

            if (isset($progress[$shipmentId])) {
                $this->shipments[$index] = array_merge($shipment, $progress[$shipmentId]);
            }
        }

        // Update counters
        $this->completedCount = collect($this->shipments)->whereIn('status', ['completed', 'error'])->count();
        $this->successCount = collect($this->shipments)->where('status', 'completed')->count();
        $this->errorCount = collect($this->shipments)->where('status', 'error')->count();

        // Check if all completed
        if ($this->completedCount >= $this->totalShipments) {
            $this->isRunning = false;
            $this->endTime = now();

            // Clean up cache
            Cache::forget("eta-report-{$this->reportId}");
            Cache::forget("eta-report-progress-{$this->reportId}");
        }
    }

    public function getProgressPercentage()
    {
        if ($this->totalShipments === 0) {
            return 0;
        }

        return round(($this->completedCount / $this->totalShipments) * 100);
    }

    public function render()
    {
        // Auto-refresh progress every 2 seconds
        $this->dispatch('refresh-progress');

        return view('livewire.eta-check-report')
            ->layout('layouts.app', ['title' => 'ETA Check Report']);
    }
}