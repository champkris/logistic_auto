<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Vessel;
use App\Models\DropdownSetting;
use Illuminate\Support\Facades\Log;

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
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // Expandable rows for ETA history
    public $expandedRows = [];

    // Available options
    public $customers = [];
    public $vessels = [];

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
        $this->editingShipment = Shipment::find($shipmentId);
        
        if ($this->editingShipment) {
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
}
