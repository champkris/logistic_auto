<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Vessel;
use App\Models\DropdownSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ShipmentManager extends Component
{
    use WithPagination;

    protected $listeners = ['refreshComponent' => '$refresh'];

    // Form properties
    public $client_requested_delivery_date = '';
    public $hbl_number = '';
    public $mbl_number = '';
    public $invoice_number = '';
    public $quantity_days = '';
    public $weight_kgm = '';
    public $quantity_number = '';    public $quantity_unit = '';
    public $customer_id = '';
    public $vessel_id = '';
    public $vessel_name = '';  // New field for vessel name input
    public $vessel_suggestions = [];  // For autocomplete suggestions
    public $vessel_exists = false;  // Track if vessel exists in database
    public $joint_pickup = '';
    public $customs_clearance_status = 'pending';
    public $overtime_status = 'none';
    public $do_status = 'pending';
    public $status = 'in-progress';
    public $vessel_loading_status = '';
    public $voyage = '';
    public $port_terminal = '';
    public $shipping_team = '';
    public $cs_reference = '';
    public $thai_status = '';
    public $planned_delivery_date = '';
    public $notes = '';
    public $cargo_description = '';
    public $cargo_weight = '';
    public $pickup_location = '';
    public $cargo_volume = '';
    public $last_eta_check_date = '';
    public $bot_received_eta_date = '';
    public $tracking_status = '';

    // Modal and state management
    public $showModal = false;
    public $editingShipment = null;
    public $search = '';
    public $statusFilter = '';

    // Filter properties
    public $filterCustomer = '';
    public $filterVessel = '';
    public $filterPortTerminal = '';
    public $filterShippingTeam = '';
    public $filterCsReference = '';

    // Sorting properties
    public $sortField = 'client_requested_delivery_date';
    public $sortDirection = 'desc';

    // Expandable rows for ETA history
    public $expandedRows = [];

    // Available options
    public $customers = [];
    public $vessels = [];
    public $portUrls = [];

    // Auto port selection
    public $searchingPort = false;
    public $currentSearchPort = '';

    // Track which fields were auto-filled for highlighting
    public $autoFilledFields = [];

    public $customsClearanceOptions = [];
    public $overtimeOptions = [];
    public $doStatusOptions = [];

    public $portTerminalOptions = [];

    public $shippingTeamOptions = [
        'pui' => 'PUI',
        'frank' => 'FRANK',
        'gus' => 'GUS',
        'mon' => 'MON',
        'noon' => 'NOON',
        'toon' => 'TOON',
        'ing' => 'ING',
        'jow' => 'JOW',
    ];

    public $pickupLocationOptions = [];
    public $csOptions = [];    public $quantityUnitOptions = [];

    public $trackingStatusOptions = [
        'on_track' => 'On Track',
        'delay' => 'Delay',
        'not_found' => 'Not Found',
    ];

    public function rules()
    {
        // Get dynamic validation rules for dropdown fields
        $customsOptions = array_keys($this->customsClearanceOptions);
        $overtimeOptions = array_keys($this->overtimeOptions);
        $doOptions = array_keys($this->doStatusOptions);

        return [
        'client_requested_delivery_date' => 'nullable|date',
        'customer_id' => 'required|exists:customers,id',
        'hbl_number' => 'nullable|string|max:255',
        'mbl_number' => 'nullable|string|max:255',
        'invoice_number' => 'nullable|string|max:255',
        'quantity_days' => 'nullable|integer|min:0',
        'weight_kgm' => 'nullable|numeric|min:0',
        'quantity_number' => 'nullable|numeric|min:0',        'quantity_unit' => 'nullable|string|max:255',
        'vessel_id' => 'nullable|exists:vessels,id',
        'vessel_name' => 'nullable|string|max:255',
        'joint_pickup' => 'nullable|string|max:255',
        'customs_clearance_status' => 'required|in:' . implode(',', $customsOptions),
        'overtime_status' => 'required|in:' . implode(',', $overtimeOptions),
        'do_status' => 'required|in:' . implode(',', $doOptions),
        'vessel_loading_status' => 'nullable|string|max:255',
        'voyage' => 'nullable|string|max:255',
        'port_terminal' => 'nullable|string|max:255',
        'shipping_team' => 'nullable|string|max:255',
        'cs_reference' => 'nullable|string|max:255',
        'thai_status' => 'nullable|string|max:255',
        'planned_delivery_date' => 'nullable|date',
        'notes' => 'nullable|string|max:1000',
        'cargo_description' => 'nullable|string|max:500',
        'cargo_weight' => 'nullable|numeric|min:0',
        'pickup_location' => 'nullable|string|max:255',
        'cargo_volume' => 'nullable|numeric|min:0',
        'last_eta_check_date' => 'nullable|date',
        'bot_received_eta_date' => 'nullable|date',
        'tracking_status' => 'nullable|in:on_track,delay,not_found',
        'status' => 'required|in:in-progress,completed',
        ];
    }

    protected $messages = [
        'customer_id.required' => 'Please select a customer.',
        'customer_id.exists' => 'Selected customer does not exist.',
        'planned_delivery_date.after_or_equal' => 'Delivery date cannot be in the past.',
    ];

    public function mount()
    {
        $this->customers = Customer::active()->orderBy('company')->get();
        $this->vessels = Vessel::orderBy('name')->get();

        // Load dynamic dropdown options from settings
        $this->loadDynamicOptions();

        // Load port URLs for clickable links
        $this->portUrls = DropdownSetting::where('field_name', 'port_terminal')
            ->whereNotNull('url')
            ->pluck('url', 'value')
            ->toArray();
    }

    /**
     * Load dropdown options from database settings
     */
    private function loadDynamicOptions()
    {
        // Load shipping team options
        $dynamicShippingTeam = DropdownSetting::getFieldOptions('shipping_team');
        if (!empty($dynamicShippingTeam)) {
            $this->shippingTeamOptions = $dynamicShippingTeam;
        }

        // Load customs clearance status options
        $dynamicCustoms = DropdownSetting::getFieldOptions('customs_clearance_status');
        $this->customsClearanceOptions = !empty($dynamicCustoms) ? $dynamicCustoms : [
            'pending' => 'ยังไม่ได้ใบขน',
            'received' => 'ได้ใบขนแล้ว',
            'processing' => 'กำลังดำเนินการ',
        ];

        // Load overtime status options
        $dynamicOvertime = DropdownSetting::getFieldOptions('overtime_status');
        $this->overtimeOptions = !empty($dynamicOvertime) ? $dynamicOvertime : [
            'none' => 'ไม่มี OT',
            'ot1' => 'OT 1 ช่วง',
            'ot2' => 'OT 2 ช่วง',
            'ot3' => 'OT 3 ช่วง',
        ];

        // Load DO status options
        $dynamicDoStatus = DropdownSetting::getFieldOptions('do_status');
        $this->doStatusOptions = !empty($dynamicDoStatus) ? $dynamicDoStatus : [
            'pending' => 'ไม่ได้รับ',
            'received' => 'ได้รับ',
            'processing' => 'กำลังดำเนินการ',
        ];


        // Load pickup location options
        $dynamicPickupLocation = DropdownSetting::getFieldOptions('pickup_location');
        if (!empty($dynamicPickupLocation)) {
            $this->pickupLocationOptions = $dynamicPickupLocation;
        }

        // Load CS team options
        $dynamicCs = DropdownSetting::getFieldOptions('cs_reference');
        if (!empty($dynamicCs)) {
            $this->csOptions = $dynamicCs;
        }

        // Load port terminal options
        $dynamicPortTerminal = DropdownSetting::getFieldOptions('port_terminal');
        $this->portTerminalOptions = !empty($dynamicPortTerminal) ? $dynamicPortTerminal : [
            'A0' => 'A0',
            'A3' => 'A3',
            'B1' => 'B1',
            'B3' => 'B3',
            'B4' => 'B4',
            'C1' => 'C1',
            'C3' => 'C3',
        ];

        // Load quantity unit options
        $dynamicQuantityUnit = DropdownSetting::getFieldOptions('quantity_unit');
        $this->quantityUnitOptions = !empty($dynamicQuantityUnit) ? $dynamicQuantityUnit : [];
    }

    /**
     * Update vessel suggestions based on input
     * Note: Livewire v3 lifecycle method naming
     */
    public function updatedVesselName($value)
    {
        Log::info('updatedVesselName called with value: ' . $value);

        if (strlen($value) >= 2) {
            // Search for vessels matching the input
            $this->vessel_suggestions = Vessel::where('name', 'like', '%' . $value . '%')
                ->orderBy('name')
                ->take(10)
                ->pluck('name')
                ->toArray();

            // Check if exact vessel name exists in database
            $this->vessel_exists = Vessel::where('name', $value)->exists();

            Log::info('Found suggestions: ' . json_encode($this->vessel_suggestions));
            Log::info('Vessel exists: ' . ($this->vessel_exists ? 'true' : 'false'));
        } else {
            $this->vessel_suggestions = [];
            $this->vessel_exists = false;
        }
    }

    /**
     * Alternative method name for Livewire updates - try with underscore naming
     */
    public function updated($propertyName, $value)
    {
        Log::info('updated called with property: ' . $propertyName . ' value: ' . $value);

        if ($propertyName === 'vessel_name') {
            $this->updatedVesselName($value);
        }
    }

    /**
     * Search vessels based on current input
     */
    public function searchVessels()
    {
        Log::info('searchVessels called with vessel_name: ' . $this->vessel_name);

        if (!empty($this->vessel_name) && strlen($this->vessel_name) >= 2) {
            // Search for vessels matching the input (case insensitive)
            $this->vessel_suggestions = Vessel::where('name', 'like', '%' . $this->vessel_name . '%')
                ->orderBy('name')
                ->take(10)
                ->pluck('name')
                ->toArray();

            // Check if exact vessel name exists in database
            $this->vessel_exists = Vessel::where('name', $this->vessel_name)->exists();

            Log::info('Found suggestions via searchVessels: ' . json_encode($this->vessel_suggestions));
            Log::info('Vessel exists: ' . ($this->vessel_exists ? 'true' : 'false'));
        } else {
            $this->vessel_suggestions = [];
            $this->vessel_exists = false;
            Log::info('Cleared suggestions - input too short or empty');
        }
    }

    /**
     * Select a vessel from suggestions
     */
    public function selectVessel($vesselName)
    {
        Log::info('selectVessel called with: ' . $vesselName);

        $vessel = Vessel::where('name', $vesselName)->first();
        if ($vessel) {
            $this->vessel_id = $vessel->id;
            $this->vessel_name = $vessel->name;
            $this->vessel_exists = true;
            Log::info('Selected existing vessel: ' . $vessel->name);
        } else {
            $this->vessel_id = null;
            $this->vessel_name = $vesselName;
            $this->vessel_exists = false;
            Log::info('Set vessel name for new vessel: ' . $vesselName);
        }

        // Clear suggestions after selection
        $this->vessel_suggestions = [];
        Log::info('Cleared suggestions after selection');
    }

    /**
     * Create or find vessel by name
     */
    private function resolveVessel()
    {
        if (!empty($this->vessel_name)) {
            // Try to find existing vessel
            $vessel = Vessel::where('name', $this->vessel_name)->first();

            if (!$vessel) {
                // Create new vessel if it doesn't exist
                $vessel = Vessel::create([
                    'name' => $this->vessel_name,
                    'vessel_code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $this->vessel_name), 0, 10)),
                    'imo_number' => null,
                    'call_sign' => null,
                    'flag' => null
                ]);
            }

            return $vessel->id;
        }

        return $this->vessel_id ?: null;
    }

    /**
     * Sort by field
     */
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            // Toggle direction if clicking the same field
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            // Set new field and default to ascending
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        // Reset pagination when sorting
        $this->resetPage();
    }

    public function render()
    {
        $query = Shipment::with(['customer', 'vessel', 'shipmentClients']);

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('hbl_number', 'like', '%' . $this->search . '%')
                  ->orWhere('mbl_number', 'like', '%' . $this->search . '%')
                  ->orWhere('invoice_number', 'like', '%' . $this->search . '%')
                  ->orWhere('voyage', 'like', '%' . $this->search . '%')
                  ->orWhere('pickup_location', 'like', '%' . $this->search . '%')
                  ->orWhereHas('customer', function ($q) {
                      $q->where('company', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('vessel', function ($q) {
                      $q->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Apply customer filter
        if ($this->filterCustomer) {
            $query->where('customer_id', $this->filterCustomer);
        }

        // Apply vessel filter
        if ($this->filterVessel) {
            $query->where('vessel_id', $this->filterVessel);
        }

        // Apply port terminal filter
        if ($this->filterPortTerminal) {
            $query->where('port_terminal', $this->filterPortTerminal);
        }

        // Apply shipping team filter
        if ($this->filterShippingTeam) {
            $query->where('shipping_team', $this->filterShippingTeam);
        }

        // Apply CS reference filter
        if ($this->filterCsReference) {
            $query->where('cs_reference', $this->filterCsReference);
        }

        // Apply sorting
        if ($this->sortField === 'customer_name') {
            // Sort by customer name (relationship)
            $query->leftJoin('customers', 'shipments.customer_id', '=', 'customers.id')
                  ->orderBy('customers.name', $this->sortDirection)
                  ->select('shipments.*');
        } elseif ($this->sortField === 'vessel_name') {
            // Sort by vessel name (relationship)
            $query->leftJoin('vessels', 'shipments.vessel_id', '=', 'vessels.id')
                  ->orderBy('vessels.name', $this->sortDirection)
                  ->select('shipments.*');
        } else {
            // Sort by direct shipment fields
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $shipments = $query->paginate(10);

        return view('livewire.shipment-manager', compact('shipments'))->layout('layouts.app', ['title' => 'Shipment Management']);
    }

    public function openModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function resetForm()
    {
        $this->editingShipment = null;
        $this->client_requested_delivery_date = '';
        $this->hbl_number = '';
        $this->mbl_number = '';
        $this->invoice_number = '';
        $this->quantity_days = '';
        $this->weight_kgm = '';
        $this->quantity_number = '';        $this->quantity_unit = '';
        $this->customer_id = '';
        $this->vessel_id = '';
        $this->vessel_name = '';
        $this->vessel_suggestions = [];
        $this->vessel_exists = false;
        $this->joint_pickup = '';
        $this->customs_clearance_status = 'pending';
        $this->overtime_status = 'none';
        $this->do_status = 'pending';
        $this->status = 'in-progress';
        $this->vessel_loading_status = '';
        $this->voyage = '';
        $this->port_terminal = '';
        $this->shipping_team = '';
        $this->cs_reference = '';
        $this->thai_status = '';
        $this->planned_delivery_date = '';
        $this->notes = '';
        $this->cargo_description = '';
        $this->cargo_weight = '';
        $this->pickup_location = '';
        $this->cargo_volume = '';
        $this->last_eta_check_date = '';
        $this->bot_received_eta_date = '';
        $this->tracking_status = '';
        $this->autoFilledFields = [];
    }


    public function save()
    {
        $this->validate();

        try {
            $cargoDetails = [];
            if ($this->cargo_description) $cargoDetails['description'] = $this->cargo_description;
            if ($this->cargo_weight) $cargoDetails['weight_kg'] = $this->cargo_weight;
            if ($this->cargo_volume) $cargoDetails['volume_cbm'] = $this->cargo_volume;

            // Resolve vessel (create if new or find existing)
            $resolvedVesselId = $this->resolveVessel();

            $shipmentData = [
                'client_requested_delivery_date' => $this->client_requested_delivery_date ?: null,
                'hbl_number' => $this->hbl_number,
                'mbl_number' => $this->mbl_number,
                'invoice_number' => $this->invoice_number,
                'quantity_days' => $this->quantity_days ?: null,
                'weight_kgm' => $this->weight_kgm ?: null,
                'quantity_number' => $this->quantity_number ?: null,                'quantity_unit' => $this->quantity_unit,
                'customer_id' => $this->customer_id,
                'vessel_id' => $resolvedVesselId,
                'joint_pickup' => $this->joint_pickup,
                'customs_clearance_status' => $this->customs_clearance_status,
                'overtime_status' => $this->overtime_status,
                'do_status' => $this->do_status,
                'status' => $this->status,
                'vessel_loading_status' => $this->vessel_loading_status,
                'voyage' => $this->voyage,
                'port_terminal' => $this->port_terminal,
                'shipping_team' => $this->shipping_team,
                'cs_reference' => $this->cs_reference,
                'thai_status' => $this->thai_status,
                'planned_delivery_date' => $this->planned_delivery_date ?: null,
                'notes' => $this->notes,
                'cargo_details' => $cargoDetails,
                'pickup_location' => $this->pickup_location,
            ];

            if ($this->editingShipment) {
                // Update existing shipment
                $this->editingShipment->update($shipmentData);
                session()->flash('message', 'Shipment updated successfully!');
            } else {
                // Create new shipment
                Shipment::create($shipmentData);
                session()->flash('message', 'Shipment created successfully!');
            }

            $this->closeModal();
            $this->resetPage();

        } catch (\Exception $e) {
            session()->flash('error', 'Error saving shipment: ' . $e->getMessage());
        }
    }

    public function edit($shipmentId)
    {
        Log::info('Edit method called', ['shipment_id' => $shipmentId]);

        $this->editingShipment = Shipment::find($shipmentId);

        if ($this->editingShipment) {
            Log::info('Shipment found, loading data');
            // Clear auto-filled fields array when editing existing shipment
            $this->autoFilledFields = [];

            $this->client_requested_delivery_date = $this->editingShipment->client_requested_delivery_date?->format('Y-m-d\TH:i');
            $this->hbl_number = $this->editingShipment->hbl_number;
            $this->mbl_number = $this->editingShipment->mbl_number;
            $this->invoice_number = $this->editingShipment->invoice_number;
            $this->quantity_days = $this->editingShipment->quantity_days;
            $this->weight_kgm = $this->editingShipment->weight_kgm;
            $this->quantity_number = $this->editingShipment->quantity_number;            $this->quantity_unit = $this->editingShipment->quantity_unit;
            $this->customer_id = $this->editingShipment->customer_id;
            $this->vessel_id = $this->editingShipment->vessel_id;
            $this->vessel_name = $this->editingShipment->vessel ? $this->editingShipment->vessel->name : '';
            $this->vessel_exists = !empty($this->vessel_name);
            $this->joint_pickup = $this->editingShipment->joint_pickup;
            $this->customs_clearance_status = $this->editingShipment->customs_clearance_status ?? 'pending';
            $this->overtime_status = $this->editingShipment->overtime_status ?? 'none';
            $this->do_status = $this->editingShipment->do_status ?? 'pending';
            $this->status = $this->editingShipment->status ?? 'in-progress';
            $this->vessel_loading_status = $this->editingShipment->vessel_loading_status;
            $this->voyage = $this->editingShipment->voyage;
            $this->port_terminal = $this->editingShipment->port_terminal;
            $this->shipping_team = $this->editingShipment->shipping_team;
            $this->cs_reference = $this->editingShipment->cs_reference;
            $this->thai_status = $this->editingShipment->thai_status;
            $this->planned_delivery_date = $this->editingShipment->planned_delivery_date?->format('Y-m-d\TH:i');
            $this->notes = $this->editingShipment->notes;

            $cargoDetails = $this->editingShipment->cargo_details ?? [];
            $this->cargo_description = $cargoDetails['description'] ?? '';
            $this->cargo_weight = $cargoDetails['weight_kg'] ?? '';
            $this->cargo_volume = $cargoDetails['volume_cbm'] ?? '';
            $this->pickup_location = $this->editingShipment->pickup_location ?? '';
            $this->last_eta_check_date = $this->editingShipment->last_eta_check_date?->format('Y-m-d\TH:i');
            $this->bot_received_eta_date = $this->editingShipment->bot_received_eta_date?->format('Y-m-d\TH:i');
            $this->tracking_status = $this->editingShipment->tracking_status ?? '';

            $this->showModal = true;
        }
    }

    public function delete($shipmentId)
    {
        try {
            $shipment = Shipment::find($shipmentId);
            
            if ($shipment) {
                // Check if shipment can be deleted based on status
                if (in_array($shipment->status, ['in_transit', 'delivered'])) {
                    session()->flash('error', 'Cannot delete shipment that is in transit or delivered.');
                    return;
                }

                $shipment->delete();
                session()->flash('message', 'Shipment deleted successfully!');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting shipment: ' . $e->getMessage());
        }
    }

    public function updateStatus($shipmentId, $newStatus)
    {
        try {
            $shipment = Shipment::find($shipmentId);
            
            if ($shipment) {
                $shipment->update(['status' => $newStatus]);
                session()->flash('message', 'Shipment status updated successfully!');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating shipment status: ' . $e->getMessage());
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingFilterCustomer()
    {
        $this->resetPage();
    }

    public function updatingFilterVessel()
    {
        $this->resetPage();
    }

    public function updatingFilterPortTerminal()
    {
        $this->resetPage();
    }

    public function updatingFilterShippingTeam()
    {
        $this->resetPage();
    }

    public function updatingFilterCsReference()
    {
        $this->resetPage();
    }

    /**
     * Clear auto-filled highlighting when user manually edits these fields
     */
    public function updatedVoyage()
    {
        $this->autoFilledFields = array_diff($this->autoFilledFields, ['voyage']);
    }

    public function updatedPortTerminal()
    {
        $this->autoFilledFields = array_diff($this->autoFilledFields, ['port_terminal']);
    }

    public function updatedPlannedDeliveryDate()
    {
        $this->autoFilledFields = array_diff($this->autoFilledFields, ['planned_delivery_date']);
    }

    public function resetFilters()
    {
        $this->filterCustomer = '';
        $this->filterVessel = '';
        $this->filterPortTerminal = '';
        $this->filterShippingTeam = '';
        $this->filterCsReference = '';
        $this->statusFilter = '';
        $this->search = '';
        $this->resetPage();
    }

    public function toggleRowExpansion($shipmentId)
    {
        if (in_array($shipmentId, $this->expandedRows)) {
            $this->expandedRows = array_filter($this->expandedRows, fn($id) => $id !== $shipmentId);
        } else {
            $this->expandedRows[] = $shipmentId;
        }
    }

    public function isRowExpanded($shipmentId)
    {
        return in_array($shipmentId, $this->expandedRows);
    }

    public function getEtaHistory($shipmentId)
    {
        $shipment = Shipment::with('etaCheckLogs.initiatedBy')->find($shipmentId);

        if (!$shipment) {
            return null;
        }

        return $shipment->etaCheckLogs->map(function ($log) {
            return [
                'id' => $log->id,
                'checked_at' => $log->created_at->format('Y-m-d H:i:s'),
                'terminal' => $log->terminal,
                'vessel_name' => $log->vessel_name,
                'voyage_code' => $log->voyage_code,
                'scraped_eta' => $log->scraped_eta ? $log->scraped_eta->format('Y-m-d H:i:s') : null,
                'shipment_eta' => $log->shipment_eta_at_time ? $log->shipment_eta_at_time->format('Y-m-d H:i:s') : null,
                'tracking_status' => $log->tracking_status,
                'status_text' => $log->status_text,
                'status_color' => $log->status_color,
                'vessel_found' => $log->vessel_found,
                'voyage_found' => $log->voyage_found,
                'error_message' => $log->error_message,
                'initiated_by' => $log->initiatedBy ? $log->initiatedBy->name : 'System',
            ];
        });
    }

    /**
     * Auto-select port terminal based on vessel name
     * Searches across all configured ports to find where the vessel is scheduled
     * Only selects ports with current year ETA that are not in the past
     *
     * OPTIMIZED: Uses caching (15 min TTL) and early exit for faster performance
     */
    public function autoSelectPort()
    {
        if (empty($this->vessel_name)) {
            session()->flash('error', 'Please enter a vessel name first');
            return;
        }

        // Check cache first (15 minute TTL) for significant speed improvement
        // Include voyage in cache key so different voyages are cached separately
        $cacheKeySuffix = !empty($this->voyage) ? '_' . strtolower(trim($this->voyage)) : '';
        $cacheKey = 'vessel_port_' . md5(strtolower(trim($this->vessel_name)) . $cacheKeySuffix);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['checked_at'])) {
            $ageMinutes = now()->diffInMinutes($cached['checked_at']);

            if ($ageMinutes < 15) {
                // Track which fields are being auto-filled
                $this->autoFilledFields = [];

                // Use cached result
                if (isset($cached['port_terminal']) && $cached['port_terminal']) {
                    $this->port_terminal = $cached['port_terminal'];
                    $this->autoFilledFields[] = 'port_terminal';
                }

                // Only fill voyage from cache if user didn't pre-fill it
                if (empty($this->voyage) && isset($cached['voyage']) && $cached['voyage']) {
                    $this->voyage = $cached['voyage'];
                    $this->autoFilledFields[] = 'voyage';
                }

                if (isset($cached['eta']) && $cached['eta']) {
                    $this->planned_delivery_date = $cached['eta'];
                    $this->autoFilledFields[] = 'planned_delivery_date';
                }

                $portLabel = $this->portTerminalOptions[$this->port_terminal] ?? $this->port_terminal;
                session()->flash('message', "✅ Found vessel at port: {$portLabel} (cached, {$ageMinutes} min ago)");

                Log::info('Auto port detection - cache hit', [
                    'vessel' => $this->vessel_name,
                    'port_terminal' => $this->port_terminal,
                    'cache_age_minutes' => $ageMinutes
                ]);

                return;
            }
        }

        $this->searchingPort = true;

        try {
            // OPTIMIZATION: Check database FIRST before any live scraping
            $dbQuery = \App\Models\VesselSchedule::query()
                ->fresh()
                ->forVessel($this->vessel_name)
                ->futureEta()
                ->currentYear();

            // If user pre-filled voyage code, filter by it
            if (!empty($this->voyage)) {
                $dbQuery->where('voyage_code', 'LIKE', '%' . $this->voyage . '%');
                Log::info('Searching with pre-filled voyage code', [
                    'vessel' => $this->vessel_name,
                    'voyage' => $this->voyage
                ]);
            }

            $dbVessel = $dbQuery->orderBy('eta', 'asc')->first();

            if ($dbVessel) {
                Log::info('Vessel found in database (pre-search optimization)', [
                    'vessel' => $this->vessel_name,
                    'port' => $dbVessel->port_terminal,
                    'voyage' => $dbVessel->voyage_code,
                    'eta' => $dbVessel->eta,
                    'matched_user_voyage' => !empty($this->voyage)
                ]);

                // Track which fields are being auto-filled
                $this->autoFilledFields = [];

                $this->port_terminal = $dbVessel->port_terminal;
                $this->autoFilledFields[] = 'port_terminal';

                // Only fill voyage if user didn't already provide it
                if (empty($this->voyage) && $dbVessel->voyage_code) {
                    $this->voyage = $dbVessel->voyage_code;
                    $this->autoFilledFields[] = 'voyage';
                }

                if ($dbVessel->eta) {
                    $this->planned_delivery_date = $dbVessel->eta->format('Y-m-d');
                    $this->autoFilledFields[] = 'planned_delivery_date';
                }

                $portLabel = $this->portTerminalOptions[$this->port_terminal] ?? $this->port_terminal;
                $voyageMsg = !empty($this->voyage) ? " (voyage: {$this->voyage})" : "";
                session()->flash('message', "✅ Found vessel at port: {$portLabel}{$voyageMsg} (from database)");

                $this->searchingPort = false;
                return;
            }

            Log::info('Vessel not in database, proceeding with live scraping', [
                'vessel' => $this->vessel_name
            ]);

            // Get the vessel tracking service
            $vesselTrackingService = new \App\Services\VesselTrackingService();

            // Get all available port terminals
            $availablePorts = array_keys($this->portTerminalOptions);

            // Limit search to common ports first for speed
            $priorityPorts = ['C1', 'C2', 'B5', 'C3', 'A0', 'B1', 'B3', 'B4', 'SIAM', 'KERRY', 'JWD', 'D1'];
            $otherPorts = array_diff($availablePorts, $priorityPorts);
            $portsToSearch = array_merge(
                array_intersect($priorityPorts, $availablePorts),
                $otherPorts
            );

            // Get current year for filtering
            $currentYear = now()->year;

            // Try to find the vessel in each port - EARLY EXIT on first valid match
            foreach ($portsToSearch as $portCode) {
                $this->currentSearchPort = $portCode;

                try {
                    // Build vessel search string with voyage code if user provided it
                    $vesselSearchString = $this->vessel_name;
                    if (!empty($this->voyage)) {
                        $vesselSearchString .= ' ' . $this->voyage;
                        Log::info("Searching with user-provided voyage code", [
                            'vessel' => $this->vessel_name,
                            'voyage' => $this->voyage,
                            'port' => $portCode
                        ]);
                    }

                    // Check if vessel exists in this port
                    $result = $vesselTrackingService->checkVesselETAByName($vesselSearchString, $portCode);

                    if ($result && $result['success'] && $result['vessel_found']) {
                        // Check if ETA exists and is in current year and not in the past
                        $etaDate = null;
                        $isValidEta = false;

                        if (isset($result['eta']) && $result['eta']) {
                            try {
                                $etaDate = \Carbon\Carbon::parse($result['eta']);
                                $now = now();

                                // Check if ETA is in current year and not in the past (same month or future)
                                if ($etaDate->year === $currentYear && $etaDate->isSameMonth($now) || $etaDate->isFuture()) {
                                    $isValidEta = true;
                                } else {
                                    if ($etaDate->year !== $currentYear) {
                                        Log::info("Skipping port {$portCode} - ETA year {$etaDate->year} does not match current year {$currentYear}");
                                    } else {
                                        Log::info("Skipping port {$portCode} - ETA date {$etaDate->format('Y-m-d')} is in the past");
                                    }
                                }
                            } catch (\Exception $e) {
                                // Could not parse ETA, skip this port
                                Log::debug("Could not parse ETA for {$portCode}: " . $e->getMessage());
                            }
                        }

                        // OPTIMIZATION: Early exit when valid vessel found - don't check other ports
                        if ($isValidEta && $etaDate) {
                            Log::info("Found valid vessel - using immediately without checking other ports", [
                                'searched_port' => $portCode,
                                'returned_port_terminal' => $result['port_terminal'] ?? 'not set',
                                'voyage' => $result['voyage_code'] ?? 'not set',
                                'eta' => $etaDate->format('Y-m-d H:i')
                            ]);

                            // Track which fields are being auto-filled
                            $this->autoFilledFields = [];

                            // Set port terminal - use specific terminal from scraper if available (e.g., A0, B1 from LCB1)
                            if (isset($result['port_terminal']) && !empty($result['port_terminal'])) {
                                $this->port_terminal = $result['port_terminal'];
                                $this->autoFilledFields[] = 'port_terminal';
                                Log::info("Using specific terminal from scraper: {$result['port_terminal']}");
                            } else {
                                $this->port_terminal = $portCode;
                                $this->autoFilledFields[] = 'port_terminal';
                            }

                            // If voyage was also found and user didn't pre-fill it, set it
                            if (empty($this->voyage) && isset($result['voyage_code']) && $result['voyage_code']) {
                                $this->voyage = $result['voyage_code'];
                                $this->autoFilledFields[] = 'voyage';
                            }

                            // If ETA was found, set it
                            if ($etaDate) {
                                $this->planned_delivery_date = $etaDate->format('Y-m-d\TH:i');
                                $this->autoFilledFields[] = 'planned_delivery_date';
                            }

                            $this->searchingPort = false;
                            $this->currentSearchPort = '';

                            $portLabel = $this->portTerminalOptions[$portCode] ?? $portCode;
                            session()->flash('message', "✅ Found vessel at port: {$portLabel}");

                            // Cache the result for 15 minutes
                            Cache::put($cacheKey, [
                                'port_terminal' => $this->port_terminal,
                                'port_label' => $portLabel,
                                'voyage' => $this->voyage,
                                'eta' => $this->planned_delivery_date,
                                'checked_at' => now()
                            ], now()->addMinutes(15));

                            // Log successful detection
                            Log::info('Auto port detection successful (early exit)', [
                                'vessel' => $this->vessel_name,
                                'searched_port_code' => $portCode,
                                'selected_terminal' => $this->port_terminal,
                                'voyage' => $this->voyage ?? null,
                                'eta' => $this->planned_delivery_date ?? null,
                                'current_year_filter' => $currentYear
                            ]);

                            return; // EARLY EXIT - stop checking other ports
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next port if this one fails
                    Log::debug("Port check failed for {$portCode}: " . $e->getMessage());
                    continue;
                }
            }

            // Vessel not found in any port with valid ETA
            $this->searchingPort = false;
            $this->currentSearchPort = '';
            session()->flash('error', "❌ Vessel '{$this->vessel_name}' not found in any port terminal with current or future {$currentYear} ETA");

        } catch (\Exception $e) {
            $this->searchingPort = false;
            $this->currentSearchPort = '';

            Log::error('Auto port selection failed', [
                'vessel_name' => $this->vessel_name,
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to auto-detect port. Please select manually.');
        }
    }
}
