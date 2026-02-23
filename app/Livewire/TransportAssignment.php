<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use App\Services\GoogleSheetService;
use App\Services\SiamgpsService;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransportAssignment extends Component
{
    use WithPagination;

    // Search mode: 'customer' or 'bl'
    public string $searchMode = 'customer';

    // Shipment list properties
    public string $shipmentSearch = '';
    public string $shipmentStatusFilter = 'in-progress';
    public string $sortField = 'client_requested_delivery_date';
    public string $sortDirection = 'desc';

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

    // Plate => GPS status map (populated on container row load)
    public array $plateStatuses = [];

    // GPS / Map
    public array $vehicleLocations = [];
    public bool $showMap = false;
    public int $mapVersion = 0;

    // Loading states
    public bool $loadingLocations = false;
    public bool $loadingSheet = false;
    public bool $loadingGps = false;

    // Errors
    public string $sheetError = '';
    public string $locationError = '';

    public function mount()
    {
        $this->selectedDate = Carbon::today()->format('d/m/Y');

        // Support URL query param: ?plate=XX&driver=YY&container=ZZ&bl=AA
        $plate = request()->query('plate');
        if ($plate) {
            $this->trackPlateFromUrl(
                $plate,
                request()->query('driver', ''),
                request()->query('container', ''),
                request()->query('bl', '')
            );
        }
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
        $this->plateStatuses = [];
        $this->availableDates = [];
        $this->sheetError = '';
        $this->matchingShipments = [];
        $this->containerAssignments = [];
        $this->resetMapState();
    }

    /**
     * Search rows by BL number.
     */
    public function searchByBl()
    {
        if (strlen(trim($this->blSearch)) < 2) {
            $this->dispatch('bl-search-done');
            return;
        }

        $this->loadingSheet = true;
        $this->sheetError = '';
        $this->containerRows = [];
        $this->plateStatuses = [];
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

        // Signal Alpine to clear the loading overlay
        $this->dispatch('bl-search-done');

        // Defer GPS status loading — fires after this response renders
        // Set loadingGps now so spinners show in the initial response
        if (!empty($this->containerRows)) {
            $this->loadingGps = true;
            $this->dispatch('load-plate-statuses');
        }
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
        $this->resetMapState();
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
        $this->plateStatuses = [];

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

        // Defer GPS status loading — fires after this response renders
        // Set loadingGps now so spinners show in the initial response
        if (!empty($this->containerRows)) {
            $this->loadingGps = true;
            $this->dispatch('load-plate-statuses');
        }
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
     * Track a single vehicle from a container row's driver slot (1, 2, or 3).
     */
    public function trackVehicle(int $rowIndex, int $slot)
    {
        $row = $this->containerRows[$rowIndex] ?? null;
        if (!$row) return;

        $plateCol = match($slot) {
            1 => '1ST LICENSE',
            2 => '2nd LICENSE',
            3 => '3 rd LICENSE',
            default => null,
        };
        $driverCol = match($slot) {
            1 => '1ST DRIVER NAME',
            2 => '2nd DRIVER NAME',
            3 => '3rd DRIVER NAME',
            default => null,
        };
        $tripCol = match($slot) {
            1 => '1st Trip',
            2 => '2nd Trip',
            3 => '3rd Trip',
            default => null,
        };

        if (!$plateCol) return;

        $plate = trim($row[$plateCol] ?? '');
        $driver = trim($row[$driverCol] ?? '');

        if (empty($plate)) return;

        $context = [
            'driver' => $driver,
            'trip' => trim($row[$tripCol] ?? ''),
            'container' => trim($row['CONTAINER NO'] ?? ''),
            'booking' => trim($row['BL'] ?? ''),
            'deliveryType' => trim($row['TYPE  CONTAINER'] ?? ''),
            'containerStatus' => trim($row['STATUS'] ?? ''),
            'area' => trim($row['AREA'] ?? ''),
        ];

        $this->fetchSinglePlateGps($plate, $context);
    }

    /**
     * Track a vehicle from URL query parameters.
     */
    public function trackPlateFromUrl(string $plate, string $driver = '', string $container = '', string $bl = '')
    {
        $plate = trim($plate);
        if (empty($plate)) return;

        $context = [
            'driver' => $driver,
            'trip' => '',
            'container' => $container,
            'booking' => $bl,
            'deliveryType' => '',
            'containerStatus' => '',
            'area' => '',
        ];

        $this->fetchSinglePlateGps($plate, $context);
    }

    /**
     * Fetch GPS for a single plate and enrich with context.
     */
    protected function fetchSinglePlateGps(string $plate, array $context): void
    {
        $this->loadingLocations = true;
        $this->locationError = '';
        $this->vehicleLocations = [];
        $this->showMap = false;

        try {
            $service = app(SiamgpsService::class);
            $results = $service->resolveMultiplePlates([$plate]);

            foreach ($results as &$result) {
                $result['driver'] = $context['driver'] ?? '';
                $result['trip'] = $context['trip'] ?? '';
                $result['container'] = $context['container'] ?? '';
                $result['booking'] = $context['booking'] ?? '';
                $result['deliveryType'] = $context['deliveryType'] ?? '';
                $result['containerStatus'] = $context['containerStatus'] ?? '';
                $result['area'] = $context['area'] ?? '';
            }

            $this->vehicleLocations = $results;
            $this->showMap = true;
            $this->mapVersion++;

            $foundCount = collect($results)->where('found', true)->count();
            if ($foundCount === 0) {
                $this->locationError = "No GPS data found for plate \"{$plate}\".";
                $this->showMap = false;
            }
        } catch (\Exception $e) {
            $this->locationError = 'Failed to fetch GPS data: ' . $e->getMessage();
            Log::error('TransportAssignment: GPS fetch error', ['error' => $e->getMessage()]);
        }

        $this->loadingLocations = false;
    }

    /**
     * Bulk-fetch GPS status for all license plates in containerRows.
     * Populates $plateStatuses as plate => { vehicleStatus, speed, geoLocation }.
     * Deferred via event dispatch so sheet data renders first.
     */
    #[On('load-plate-statuses')]
    public function loadPlateStatuses(): void
    {
        $this->plateStatuses = [];

        if (empty($this->containerRows)) {
            $this->loadingGps = false;
            return;
        }

        $plates = [];
        foreach ($this->containerRows as $row) {
            foreach (['1ST LICENSE', '2nd LICENSE', '3 rd LICENSE'] as $col) {
                $plate = trim($row[$col] ?? '');
                if (!empty($plate)) {
                    $plates[] = $plate;
                }
            }
        }

        $plates = array_values(array_unique($plates));
        if (empty($plates)) {
            $this->loadingGps = false;
            return;
        }

        try {
            $service = app(SiamgpsService::class);
            $results = $service->resolveMultiplePlates($plates);

            foreach ($results as $result) {
                $this->plateStatuses[$result['plate']] = [
                    'found' => $result['found'],
                    'vehicleStatus' => $result['vehicleStatus'] ?? 'NOT_FOUND',
                    'speed' => $result['speed'] ?? 0,
                    'geoLocation' => $result['geoLocation'] ?? '',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('TransportAssignment: Failed to load plate statuses', ['error' => $e->getMessage()]);
        }

        $this->loadingGps = false;
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
     * Reset map-related state.
     */
    protected function resetMapState()
    {
        $this->vehicleLocations = [];
        $this->showMap = false;
        $this->locationError = '';
    }

    /**
     * Sort shipment list by column.
     */
    public function sortBy(string $field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Reset pagination when shipment search changes.
     */
    public function updatedShipmentSearch()
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when shipment status filter changes.
     */
    public function updatedShipmentStatusFilter()
    {
        $this->resetPage();
    }

    /**
     * Switch to BL search tab and auto-search a shipment's MBL.
     * The wire:loading overlay on the UI covers stale data instantly (client-side).
     */
    public function viewShipmentBl(int $shipmentId)
    {
        $shipment = Shipment::find($shipmentId);
        if (!$shipment || empty($shipment->mbl_number)) {
            $this->sheetError = 'Shipment or MBL not found.';
            $this->dispatch('bl-search-done');
            return;
        }

        $this->searchMode = 'bl';
        $this->blSearch = $shipment->mbl_number;
        $this->searchByBl();
    }

    public function render()
    {
        // Build shipment list query
        $query = Shipment::with(['customer', 'vessel']);

        if ($this->shipmentStatusFilter && $this->shipmentStatusFilter !== 'all') {
            $query->where('status', $this->shipmentStatusFilter);
        }

        if ($this->shipmentSearch) {
            $search = $this->shipmentSearch;
            $query->where(function ($q) use ($search) {
                $q->where('mbl_number', 'like', "%{$search}%")
                  ->orWhere('hbl_number', 'like', "%{$search}%")
                  ->orWhere('voyage', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($q) use ($search) {
                      $q->where('company', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('vessel', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($this->sortField === 'customer_name') {
            $query->leftJoin('customers', 'shipments.customer_id', '=', 'customers.id')
                  ->orderBy('customers.name', $this->sortDirection)
                  ->select('shipments.*');
        } elseif ($this->sortField === 'vessel_name') {
            $query->leftJoin('vessels', 'shipments.vessel_id', '=', 'vessels.id')
                  ->orderBy('vessels.name', $this->sortDirection)
                  ->select('shipments.*');
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $shipments = $query->paginate(15);

        // Compute transport assignment stats
        $assignedCount = 0;
        $unassignedCount = 0;
        foreach ($shipments as $shipment) {
            $transportContainers = $shipment->cargo_details['transport_containers'] ?? [];
            $shipment->has_transport = !empty($transportContainers);
            $shipment->transport_container_count = count($transportContainers);
            if ($shipment->has_transport) {
                $assignedCount++;
            } else {
                $unassignedCount++;
            }
        }

        return view('livewire.transport-assignment', [
            'shipments' => $shipments,
            'assignedCount' => $assignedCount,
            'unassignedCount' => $unassignedCount,
        ])->layout('layouts.app', ['header' => null]);
    }
}
