<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Vessel;
use App\Models\DropdownSetting;

class ShipmentManager extends Component
{
    use WithPagination;

    // Form properties
    public $shipment_number = '';
    public $hbl_number = '';
    public $mbl_number = '';
    public $invoice_number = '';
    public $quantity_days = '';
    public $do_pickup_date = '';
    public $weight_kgm = '';
    public $fcl_type = '';
    public $container_arrival = '';
    public $vessel_code = '';
    public $customer_id = '';
    public $vessel_id = '';
    public $port_of_discharge = '';
    public $berth_location = '';
    public $joint_pickup = '';
    public $customs_entry = '';
    public $customs_clearance_status = 'pending';
    public $overtime_status = 'none';
    public $do_status = 'pending';
    public $vessel_loading_status = '';
    public $voyage = '';
    public $port_terminal = '';
    public $shipping_team = '';
    public $cs_reference = '';
    public $vsl_payment_status = '';
    public $status = 'new';
    public $final_status = '';
    public $thai_status = '';
    public $planned_delivery_date = '';
    public $total_cost = '';
    public $notes = '';
    public $cargo_description = '';
    public $cargo_weight = '';
    public $shipping_line = '';
    public $cargo_volume = '';
    
    // Modal and state management
    public $showModal = false;
    public $editingShipment = null;
    public $search = '';
    public $statusFilter = '';

    // Available options
    public $customers = [];
    public $vessels = [];
    public $statusOptions = [
        'new' => 'New',
        'planning' => 'Planning',
        'documents_preparation' => 'Documents Preparation',
        'customs_clearance' => 'Customs Clearance',
        'ready_for_delivery' => 'Ready for Delivery',
        'in_transit' => 'In Transit',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
    ];

    public $customsClearanceOptions = [
        'no_clearance' => 'ยังไม่ได้ใบขน',
        'has_clearance' => 'ได้ใบขนแล้ว',
    ];

    public $overtimeOptions = [
        'no_ot' => 'ไม่มี OT',
        'ot_1_period' => 'OT 1 ช่วง',
        'ot_2_periods' => 'OT 2 ช่วง',
    ];

    public $doStatusOptions = [
        'not_received' => 'ไม่ได้รับ',
        'received' => 'ได้รับ',
    ];

    public $portOptions = [
        'Laem Chabang' => 'Laem Chabang',
        'Bangkok Port' => 'Bangkok Port',
        'Map Ta Phut' => 'Map Ta Phut',
        'Sattahip' => 'Sattahip',
    ];

    public $portTerminalOptions = [
        'A0' => 'A0',
        'A3' => 'A3',
        'B1' => 'B1',
        'B3' => 'B3',
        'B4' => 'B4',
        'C1' => 'C1',
        'C3' => 'C3',
    ];

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

    public $finalStatusOptions = [
        'MEW' => 'MEW',
        'MON' => 'MON',
        'NOON' => 'NOON',
        'ING' => 'ING',
        'JOW' => 'JOW',
    ];

    public $shippingLineOptions = [];
    public $vslPaymentOptions = [];
    public $pickupLocationOptions = [];
    public $csOptions = [];

    public function rules()
    {
        // Get dynamic validation rules for dropdown fields
        $customsOptions = array_keys($this->customsClearanceOptions);
        $overtimeOptions = array_keys($this->overtimeOptions);
        $doOptions = array_keys($this->doStatusOptions);

        return [
        'shipment_number' => 'required|string|max:255',
        'customer_id' => 'required|exists:customers,id',
        'hbl_number' => 'nullable|string|max:255',
        'mbl_number' => 'nullable|string|max:255',
        'invoice_number' => 'nullable|string|max:255',
        'quantity_days' => 'nullable|integer|min:0',
        'do_pickup_date' => 'nullable|date',
        'weight_kgm' => 'nullable|numeric|min:0',
        'fcl_type' => 'nullable|string|max:255',
        'container_arrival' => 'nullable|string|max:255',
        'vessel_code' => 'nullable|string|max:255',
        'vessel_id' => 'nullable|exists:vessels,id',
        'port_of_discharge' => 'required|string|max:255',
        'berth_location' => 'nullable|string|max:255',
        'joint_pickup' => 'nullable|string|max:255',
        'customs_entry' => 'nullable|string|max:255',
        'customs_clearance_status' => 'required|in:' . implode(',', $customsOptions),
        'overtime_status' => 'required|in:' . implode(',', $overtimeOptions),
        'do_status' => 'required|in:' . implode(',', $doOptions),
        'vessel_loading_status' => 'nullable|string|max:255',
        'voyage' => 'nullable|string|max:255',
        'port_terminal' => 'nullable|string|max:255',
        'shipping_team' => 'nullable|string|max:255',
        'cs_reference' => 'nullable|string|max:255',
        'vsl_payment_status' => 'nullable|string|max:255',
        'status' => 'required|in:new,planning,documents_preparation,customs_clearance,ready_for_delivery,in_transit,delivered,completed',
        'final_status' => 'nullable|string|max:255',
        'thai_status' => 'nullable|string|max:255',
        'planned_delivery_date' => 'nullable|date|after_or_equal:today',
        'total_cost' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string|max:1000',
        'cargo_description' => 'nullable|string|max:500',
        'cargo_weight' => 'nullable|numeric|min:0',
        'shipping_line' => 'nullable|string|max:255',
        'cargo_volume' => 'nullable|numeric|min:0',
        ];
    }

    protected $messages = [
        'shipment_number.required' => 'Shipment number is required.',
        'customer_id.required' => 'Please select a customer.',
        'customer_id.exists' => 'Selected customer does not exist.',
        'port_of_discharge.required' => 'Port of discharge is required.',
        'planned_delivery_date.after_or_equal' => 'Delivery date cannot be in the past.',
    ];

    public function mount()
    {
        $this->customers = Customer::active()->orderBy('company')->get();
        $this->vessels = Vessel::orderBy('name')->get();

        // Load dynamic dropdown options from settings
        $this->loadDynamicOptions();

        // Generate initial shipment number
        if (!$this->shipment_number) {
            $this->shipment_number = $this->generateShipmentNumber();
        }
    }

    /**
     * Load dropdown options from database settings
     */
    private function loadDynamicOptions()
    {
        // Load port options
        $dynamicPorts = DropdownSetting::getFieldOptions('port_of_discharge');
        if (!empty($dynamicPorts)) {
            $this->portOptions = $dynamicPorts;
        }

        // Load shipping team options
        $dynamicShippingTeam = DropdownSetting::getFieldOptions('shipping_team');
        if (!empty($dynamicShippingTeam)) {
            $this->shippingTeamOptions = $dynamicShippingTeam;
        }

        // Load final status options
        $dynamicFinalStatus = DropdownSetting::getFieldOptions('final_status');
        if (!empty($dynamicFinalStatus)) {
            $this->finalStatusOptions = $dynamicFinalStatus;
        }

        // Load customs clearance status options
        $dynamicCustoms = DropdownSetting::getFieldOptions('customs_clearance_status');
        if (!empty($dynamicCustoms)) {
            $this->customsClearanceOptions = $dynamicCustoms;
        }

        // Load overtime status options
        $dynamicOvertime = DropdownSetting::getFieldOptions('overtime_status');
        if (!empty($dynamicOvertime)) {
            $this->overtimeOptions = $dynamicOvertime;
        }

        // Load DO status options
        $dynamicDoStatus = DropdownSetting::getFieldOptions('do_status');
        if (!empty($dynamicDoStatus)) {
            $this->doStatusOptions = $dynamicDoStatus;
        }

        // Load shipping line options
        $dynamicShippingLine = DropdownSetting::getFieldOptions('shipping_line');
        if (!empty($dynamicShippingLine)) {
            $this->shippingLineOptions = $dynamicShippingLine;
        }

        // Load VSL payment status options
        $dynamicVslPayment = DropdownSetting::getFieldOptions('vsl_payment_status');
        if (!empty($dynamicVslPayment)) {
            $this->vslPaymentOptions = $dynamicVslPayment;
        }

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
    }

    public function render()
    {
        $query = Shipment::with(['customer', 'vessel']);

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('shipment_number', 'like', '%' . $this->search . '%')
                  ->orWhere('hbl_number', 'like', '%' . $this->search . '%')
                  ->orWhere('mbl_number', 'like', '%' . $this->search . '%')
                  ->orWhere('vessel_code', 'like', '%' . $this->search . '%')
                  ->orWhereHas('customer', function ($q) {
                      $q->where('company', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $shipments = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.shipment-manager', compact('shipments'));
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
        $this->shipment_number = $this->generateShipmentNumber();
        $this->hbl_number = '';
        $this->mbl_number = '';
        $this->invoice_number = '';
        $this->quantity_days = '';
        $this->do_pickup_date = '';
        $this->weight_kgm = '';
        $this->fcl_type = '';
        $this->container_arrival = '';
        $this->vessel_code = '';
        $this->customer_id = '';
        $this->vessel_id = '';
        $this->port_of_discharge = '';
        $this->berth_location = '';
        $this->joint_pickup = '';
        $this->customs_entry = '';
        $this->customs_clearance_status = 'pending';
        $this->overtime_status = 'none';
        $this->do_status = 'pending';
        $this->vessel_loading_status = '';
        $this->voyage = '';
        $this->port_terminal = '';
        $this->shipping_team = '';
        $this->cs_reference = '';
        $this->vsl_payment_status = '';
        $this->status = 'new';
        $this->final_status = '';
        $this->thai_status = '';
        $this->planned_delivery_date = '';
        $this->total_cost = '';
        $this->notes = '';
        $this->cargo_description = '';
        $this->cargo_weight = '';
        $this->shipping_line = '';
        $this->cargo_volume = '';
    }

    private function generateShipmentNumber()
    {
        $prefix = 'CSL';
        $year = date('Y');
        $month = date('m');
        
        // Get the last shipment number for this month
        $lastShipment = Shipment::where('shipment_number', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('shipment_number', 'desc')
            ->first();
            
        if ($lastShipment) {
            $lastNumber = (int) substr($lastShipment->shipment_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return "{$prefix}{$year}{$month}{$newNumber}";
    }

    public function save()
    {
        $this->validate();

        try {
            $cargoDetails = [];
            if ($this->cargo_description) $cargoDetails['description'] = $this->cargo_description;
            if ($this->cargo_weight) $cargoDetails['weight_kg'] = $this->cargo_weight;
            if ($this->cargo_volume) $cargoDetails['volume_cbm'] = $this->cargo_volume;

            $shipmentData = [
                'shipment_number' => $this->shipment_number,
                'hbl_number' => $this->hbl_number,
                'mbl_number' => $this->mbl_number,
                'invoice_number' => $this->invoice_number,
                'quantity_days' => $this->quantity_days ?: null,
                'do_pickup_date' => $this->do_pickup_date ?: null,
                'weight_kgm' => $this->weight_kgm ?: null,
                'fcl_type' => $this->fcl_type,
                'container_arrival' => $this->container_arrival,
                'vessel_code' => $this->vessel_code,
                'customer_id' => $this->customer_id,
                'vessel_id' => $this->vessel_id ?: null,
                'port_of_discharge' => $this->port_of_discharge,
                'berth_location' => $this->berth_location,
                'joint_pickup' => $this->joint_pickup,
                'customs_entry' => $this->customs_entry,
                'customs_clearance_status' => $this->customs_clearance_status,
                'overtime_status' => $this->overtime_status,
                'do_status' => $this->do_status,
                'vessel_loading_status' => $this->vessel_loading_status,
                'voyage' => $this->voyage,
                'port_terminal' => $this->port_terminal,
                'shipping_team' => $this->shipping_team,
                'cs_reference' => $this->cs_reference,
                'vsl_payment_status' => $this->vsl_payment_status,
                'status' => $this->status,
                'final_status' => $this->final_status,
                'thai_status' => $this->thai_status,
                'planned_delivery_date' => $this->planned_delivery_date ?: null,
                'total_cost' => $this->total_cost ?: null,
                'notes' => $this->notes,
                'cargo_details' => $cargoDetails,
                'shipping_line' => $this->shipping_line,
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
            $this->shipment_number = $this->editingShipment->shipment_number;
            $this->hbl_number = $this->editingShipment->hbl_number;
            $this->mbl_number = $this->editingShipment->mbl_number;
            $this->invoice_number = $this->editingShipment->invoice_number;
            $this->quantity_days = $this->editingShipment->quantity_days;
            $this->do_pickup_date = $this->editingShipment->do_pickup_date?->format('Y-m-d\\TH:i');
            $this->weight_kgm = $this->editingShipment->weight_kgm;
            $this->fcl_type = $this->editingShipment->fcl_type;
            $this->container_arrival = $this->editingShipment->container_arrival;
            $this->vessel_code = $this->editingShipment->vessel_code;
            $this->customer_id = $this->editingShipment->customer_id;
            $this->vessel_id = $this->editingShipment->vessel_id;
            $this->port_of_discharge = $this->editingShipment->port_of_discharge;
            $this->berth_location = $this->editingShipment->berth_location;
            $this->joint_pickup = $this->editingShipment->joint_pickup;
            $this->customs_entry = $this->editingShipment->customs_entry;
            $this->customs_clearance_status = $this->editingShipment->customs_clearance_status ?? 'pending';
            $this->overtime_status = $this->editingShipment->overtime_status ?? 'none';
            $this->do_status = $this->editingShipment->do_status ?? 'pending';
            $this->vessel_loading_status = $this->editingShipment->vessel_loading_status;
            $this->voyage = $this->editingShipment->voyage;
            $this->port_terminal = $this->editingShipment->port_terminal;
            $this->shipping_team = $this->editingShipment->shipping_team;
            $this->cs_reference = $this->editingShipment->cs_reference;
            $this->vsl_payment_status = $this->editingShipment->vsl_payment_status;
            $this->status = $this->editingShipment->status;
            $this->final_status = $this->editingShipment->final_status;
            $this->thai_status = $this->editingShipment->thai_status;
            $this->planned_delivery_date = $this->editingShipment->planned_delivery_date?->format('Y-m-d');
            $this->total_cost = $this->editingShipment->total_cost;
            $this->notes = $this->editingShipment->notes;

            $cargoDetails = $this->editingShipment->cargo_details ?? [];
            $this->cargo_description = $cargoDetails['description'] ?? '';
            $this->cargo_weight = $cargoDetails['weight_kg'] ?? '';
            $this->cargo_volume = $cargoDetails['volume_cbm'] ?? '';
            $this->shipping_line = $this->editingShipment->shipping_line ?? '';

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
}
