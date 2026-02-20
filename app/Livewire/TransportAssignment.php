<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\GoogleSheetService;
use App\Services\SiamgpsService;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransportAssignment extends Component
{
    // Search mode: 'customer' or 'bl'
    public string $searchMode = 'customer';

    // Customer search
    public string $customerSearch = '';
    public array $customerSuggestions = [];
    public string $selectedCustomer = '';

    // BL search
    public string $blSearch = '';

    // Matching shipments from platform (BL mode)
    public array $matchingShipments = [];

    // Container-to-shipment assignment: containerNo => { shipmentId, shipmentLabel }
    // Loaded from DB on search + updated on new assignments
    public array $containerAssignments = [];

    // Date navigation
    public string $selectedDate = '';
    public array $availableDates = [];

    // Container data
    public array $containerRows = [];
    public array $selectedRows = [];
    public bool $selectAll = false;

    // GPS / Map
    public array $vehicleLocations = [];
    public bool $showMap = false;

    // Loading states
    public bool $loadingLocations = false;
    public bool $loadingSheet = false;

    // Errors
    public string $sheetError = '';
    public string $locationError = '';

    public function mount()
    {
        $this->selectedDate = Carbon::today()->format('d/m/Y');
    }

    /**
     * Switch between customer and BL search modes.
     */
    public function switchSearchMode(string $mode)
    {
        $this->searchMode = $mode;
        $this->customerSearch = '';
        $this->customerSuggestions = [];
        $this->selectedCustomer = '';
        $this->blSearch = '';
        $this->containerRows = [];
        $this->availableDates = [];
        $this->sheetError = '';
        $this->matchingShipments = [];
        $this->containerAssignments = [];
        $this->resetSelection();
    }

    /**
     * Search rows by BL number.
     */
    public function searchByBl()
    {
        if (strlen(trim($this->blSearch)) < 2) {
            return;
        }

        $this->loadingSheet = true;
        $this->sheetError = '';
        $this->containerRows = [];
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->selectedCustomer = '';
        $this->availableDates = [];
        $this->matchingShipments = [];
        $this->containerAssignments = [];

        try {
            $service = app(GoogleSheetService::class);
            $this->containerRows = $service->searchByBl($this->blSearch);

            if (empty($this->containerRows)) {
                $this->sheetError = "No records found for BL \"{$this->blSearch}\"";
            }

            // Find matching shipments on our platform by MBL
            $this->loadMatchingShipments($this->blSearch);
        } catch (\Exception $e) {
            $this->sheetError = 'Failed to search: ' . $e->getMessage();
            Log::error('TransportAssignment: BL search error', ['error' => $e->getMessage()]);
        }

        $this->loadingSheet = false;
    }

    /**
     * Load shipments from the platform that match the BL number,
     * and build containerAssignments map from existing cargo_details.
     */
    protected function loadMatchingShipments(string $bl)
    {
        $shipments = Shipment::with('customer')
            ->where('mbl_number', 'like', "%{$bl}%")
            ->get();

        $this->containerAssignments = [];

        $this->matchingShipments = $shipments->map(function ($s) {
            $cargoDetails = $s->cargo_details ?? [];
            $assignedContainers = $cargoDetails['transport_containers'] ?? [];
            $assignedDrivers = $cargoDetails['transport_drivers'] ?? [];

            // Build the already-assigned map: containerNo => shipment info
            foreach ($assignedContainers as $containerNo) {
                $this->containerAssignments[$containerNo] = [
                    'shipmentId' => $s->id,
                    'shipmentLabel' => "#{$s->id} ({$s->customer?->name})",
                ];
            }

            return [
                'id' => $s->id,
                'mbl_number' => $s->mbl_number,
                'hbl_number' => $s->hbl_number,
                'customer_name' => $s->customer?->name ?? 'N/A',
                'quantity' => $s->quantity_number ? (int) $s->quantity_number : null,
                'quantity_unit' => $s->quantity_unit,
                'status' => $s->status,
                'port_terminal' => $s->port_terminal,
                'vessel_loading_status' => $s->vessel_loading_status,
                'notes' => $s->notes,
                'assigned_containers' => $assignedContainers,
                'assigned_drivers' => $assignedDrivers,
            ];
        })->toArray();
    }

    /**
     * Assign unassigned container rows (that have driver/license) to a shipment.
     */
    public function assignContainersToShipment(int $shipmentId)
    {
        $shipment = Shipment::with('customer')->find($shipmentId);
        if (!$shipment) {
            $this->sheetError = 'Shipment not found.';
            return;
        }

        // Get assignable containers that are NOT already assigned to any shipment
        $assignable = $this->getAssignableContainers(excludeAssigned: true);

        if (empty($assignable)) {
            $this->sheetError = 'No new containers with driver/license details to assign.';
            return;
        }

        $newContainers = collect($assignable)->pluck('container')->filter()->unique()->values()->toArray();

        // Merge with any existing assigned containers for this shipment
        $existing = $shipment->cargo_details ?? [];
        $previousContainers = $existing['transport_containers'] ?? [];
        $previousDrivers = $existing['transport_drivers'] ?? [];

        $mergedContainers = array_values(array_unique(array_merge($previousContainers, $newContainers)));

        $newDriverEntries = collect($assignable)->map(fn($r) => [
            'container' => $r['container'],
            'driver' => $r['driver'],
            'license' => $r['license'],
        ])->toArray();
        $mergedDrivers = array_merge($previousDrivers, $newDriverEntries);

        $existing['transport_containers'] = $mergedContainers;
        $existing['transport_assigned_at'] = now()->toDateTimeString();
        $existing['transport_bl'] = $this->blSearch;
        $existing['transport_drivers'] = $mergedDrivers;

        $shipment->cargo_details = $existing;
        $shipment->save();

        // Update in-memory assignment map
        $shipmentLabel = "#{$shipment->id} ({$shipment->customer?->name})";
        foreach ($newContainers as $containerNo) {
            $this->containerAssignments[$containerNo] = [
                'shipmentId' => $shipment->id,
                'shipmentLabel' => $shipmentLabel,
            ];
        }

        // Refresh matching shipments to reflect updated counts
        $this->loadMatchingShipments($this->blSearch);

        session()->flash('assignSuccess', count($newContainers) . ' container(s) assigned to shipment ' . $shipmentLabel);
    }

    /**
     * Detach a single container from a shipment.
     */
    public function detachContainer(int $shipmentId, string $containerNo)
    {
        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            $this->sheetError = 'Shipment not found.';
            return;
        }

        $existing = $shipment->cargo_details ?? [];
        $containers = $existing['transport_containers'] ?? [];
        $drivers = $existing['transport_drivers'] ?? [];

        $existing['transport_containers'] = array_values(array_filter($containers, fn($c) => $c !== $containerNo));
        $existing['transport_drivers'] = array_values(array_filter($drivers, fn($d) => ($d['container'] ?? '') !== $containerNo));

        if (empty($existing['transport_containers'])) {
            unset($existing['transport_containers'], $existing['transport_drivers'], $existing['transport_assigned_at'], $existing['transport_bl']);
        }

        $shipment->cargo_details = !empty($existing) ? $existing : null;
        $shipment->save();

        unset($this->containerAssignments[$containerNo]);

        $this->loadMatchingShipments($this->blSearch);
    }

    /**
     * Get container rows that have at least one driver + license plate filled in.
     * If excludeAssigned is true, skip containers already in containerAssignments.
     */
    protected function getAssignableContainers(bool $excludeAssigned = false): array
    {
        $assignable = [];

        foreach ($this->containerRows as $index => $row) {
            $containerNo = trim($row['CONTAINER NO'] ?? '');

            // Skip if already assigned
            if ($excludeAssigned && !empty($containerNo) && isset($this->containerAssignments[$containerNo])) {
                continue;
            }

            $driver = trim($row['1ST DRIVER NAME'] ?? '');
            $license = trim($row['1ST LICENSE'] ?? '');

            if (empty($driver) && empty($license)) {
                $driver = trim($row['2nd DRIVER NAME'] ?? '');
                $license = trim($row['2nd LICENSE'] ?? '');
            }
            if (empty($driver) && empty($license)) {
                $driver = trim($row['3rd DRIVER NAME'] ?? '');
                $license = trim($row['3 rd LICENSE'] ?? '');
            }

            if (!empty($driver) || !empty($license)) {
                $assignable[] = [
                    'index' => $index,
                    'container' => $containerNo,
                    'driver' => $driver,
                    'license' => $license,
                ];
            }
        }

        return $assignable;
    }

    /**
     * Autocomplete customer search (debounced from view).
     */
    public function updatedCustomerSearch($value)
    {
        $this->customerSuggestions = [];

        if (strlen(trim($value)) < 2) {
            return;
        }

        try {
            $service = app(GoogleSheetService::class);
            $this->customerSuggestions = $service->searchCustomers($value);
        } catch (\Exception $e) {
            Log::error('TransportAssignment: customer search error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Select a customer from autocomplete dropdown.
     */
    public function selectCustomer(string $name)
    {
        $this->selectedCustomer = $name;
        $this->customerSearch = $name;
        $this->customerSuggestions = [];
        $this->selectedDate = Carbon::today()->format('d/m/Y');
        $this->resetSelection();
        $this->loadAvailableDates();
        $this->loadContainerRows();
    }

    /**
     * Load available dates for the selected customer.
     */
    protected function loadAvailableDates()
    {
        try {
            $service = app(GoogleSheetService::class);
            $this->availableDates = $service->getAvailableDatesForCustomer($this->selectedCustomer);
        } catch (\Exception $e) {
            Log::error('TransportAssignment: load dates error', ['error' => $e->getMessage()]);
            $this->availableDates = [];
        }
    }

    /**
     * Load container rows for selected customer and date.
     */
    public function loadContainerRows()
    {
        if (empty($this->selectedCustomer)) {
            return;
        }

        $this->loadingSheet = true;
        $this->sheetError = '';
        $this->containerRows = [];
        $this->selectedRows = [];
        $this->selectAll = false;

        try {
            $service = app(GoogleSheetService::class);
            $this->containerRows = $service->getRowsForCustomer($this->selectedCustomer, $this->selectedDate);

            if (empty($this->containerRows)) {
                $this->sheetError = "No records found for {$this->selectedCustomer} on {$this->selectedDate}";
            }
        } catch (\Exception $e) {
            $this->sheetError = 'Failed to load sheet data: ' . $e->getMessage();
            Log::error('TransportAssignment: load rows error', ['error' => $e->getMessage()]);
        }

        $this->loadingSheet = false;
    }

    /**
     * Navigate to previous date.
     */
    public function previousDate()
    {
        $current = Carbon::createFromFormat('d/m/Y', $this->selectedDate);
        $this->selectedDate = $current->subDay()->format('d/m/Y');
        $this->resetMapState();
        $this->loadContainerRows();
    }

    /**
     * Navigate to next date.
     */
    public function nextDate()
    {
        $current = Carbon::createFromFormat('d/m/Y', $this->selectedDate);
        $this->selectedDate = $current->addDay()->format('d/m/Y');
        $this->resetMapState();
        $this->loadContainerRows();
    }

    /**
     * Jump to a specific date from the available dates list.
     */
    public function goToDate(string $date)
    {
        $this->selectedDate = $date;
        $this->resetMapState();
        $this->loadContainerRows();
    }

    /**
     * Toggle select-all checkbox.
     */
    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedRows = array_keys($this->containerRows);
        } else {
            $this->selectedRows = [];
        }
    }

    /**
     * Fetch GPS locations for all license plates in selected rows.
     */
    public function fetchGpsLocations()
    {
        $this->loadingLocations = true;
        $this->locationError = '';
        $this->vehicleLocations = [];
        $this->showMap = false;

        // Collect all license plates from selected rows (1st, 2nd, 3rd)
        $plates = [];
        foreach ($this->selectedRows as $index) {
            $row = $this->containerRows[$index] ?? null;
            if (!$row) continue;

            foreach (['1ST LICENSE', '2nd LICENSE', '3 rd LICENSE'] as $col) {
                $plate = trim($row[$col] ?? '');
                if (!empty($plate)) {
                    $plates[] = $plate;
                }
            }
        }

        $plates = array_unique($plates);

        if (empty($plates)) {
            $this->locationError = 'No license plates found in selected rows.';
            $this->loadingLocations = false;
            return;
        }

        try {
            $service = app(SiamgpsService::class);
            $results = $service->resolveMultiplePlates($plates);

            // Enrich results with row context (driver, container info)
            $this->vehicleLocations = $this->enrichLocations($results);
            $this->showMap = true;

            $foundCount = collect($this->vehicleLocations)->where('found', true)->count();
            if ($foundCount === 0) {
                $this->locationError = 'No GPS data found for the selected license plates.';
                $this->showMap = false;
            }
        } catch (\Exception $e) {
            $this->locationError = 'Failed to fetch GPS data: ' . $e->getMessage();
            Log::error('TransportAssignment: GPS fetch error', ['error' => $e->getMessage()]);
        }

        $this->loadingLocations = false;
    }

    /**
     * Enrich GPS results with driver/container info from selected rows.
     */
    protected function enrichLocations(array $gpsResults): array
    {
        $plateToRowInfo = [];

        foreach ($this->selectedRows as $index) {
            $row = $this->containerRows[$index] ?? null;
            if (!$row) continue;

            $driverMappings = [
                '1ST LICENSE' => ['driver' => '1ST DRIVER NAME', 'trip' => '1st Trip'],
                '2nd LICENSE' => ['driver' => '2nd DRIVER NAME', 'trip' => '2nd Trip'],
                '3 rd LICENSE' => ['driver' => '3rd DRIVER NAME', 'trip' => '3rd Trip'],
            ];

            foreach ($driverMappings as $plateCol => $info) {
                $plate = trim($row[$plateCol] ?? '');
                if (!empty($plate)) {
                    $plateToRowInfo[$plate] = [
                        'driver' => trim($row[$info['driver']] ?? ''),
                        'trip' => trim($row[$info['trip']] ?? ''),
                        'container' => trim($row['CONTAINER NO'] ?? ''),
                        'booking' => trim($row['BL'] ?? ''),
                        'deliveryType' => trim($row['TYPE  CONTAINER'] ?? ''),
                        'status' => trim($row['STATUS'] ?? ''),
                        'area' => trim($row['AREA'] ?? ''),
                    ];
                }
            }
        }

        foreach ($gpsResults as &$result) {
            $info = $plateToRowInfo[$result['plate']] ?? [];
            $result['driver'] = $info['driver'] ?? '';
            $result['trip'] = $info['trip'] ?? '';
            $result['container'] = $info['container'] ?? '';
            $result['booking'] = $info['booking'] ?? '';
            $result['deliveryType'] = $info['deliveryType'] ?? '';
            $result['containerStatus'] = $info['status'] ?? '';
            $result['area'] = $info['area'] ?? '';
        }

        return $gpsResults;
    }

    /**
     * Force refresh sheet data (clear cache).
     */
    public function refreshSheetData()
    {
        try {
            $service = app(GoogleSheetService::class);
            $service->getTransportData(forceRefresh: true);
            $this->loadAvailableDates();
            $this->loadContainerRows();
        } catch (\Exception $e) {
            $this->sheetError = 'Failed to refresh: ' . $e->getMessage();
        }
    }

    /**
     * Reset row selection and map state.
     */
    public function resetSelection()
    {
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->resetMapState();
    }

    /**
     * Reset map-related state.
     */
    protected function resetMapState()
    {
        $this->vehicleLocations = [];
        $this->showMap = false;
        $this->locationError = '';
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    public function render()
    {
        return view('livewire.transport-assignment')
            ->layout('layouts.app', ['header' => null]);
    }
}
