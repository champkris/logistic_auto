<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Shipment;
use App\Models\Customer;
use App\Models\Vessel;
use Illuminate\Validation\Rule;

class ShipmentManager extends Component
{
    use WithPagination;

    // Form properties
    public $shipment_number = '';
    public $consignee = '';
    public $hbl_number = '';
    public $mbl_number = '';
    public $invoice_number = '';
    public $customer_id = '';
    public $vessel_id = '';
    public $port_of_discharge = '';
    public $status = 'new';
    public $planned_delivery_date = '';
    public $total_cost = '';
    public $notes = '';
    public $cargo_description = '';
    public $cargo_weight = '';
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

    public $portOptions = [
        'Laem Chabang' => 'Laem Chabang',
        'Bangkok Port' => 'Bangkok Port',
        'Map Ta Phut' => 'Map Ta Phut',
        'Sattahip' => 'Sattahip',
    ];

    protected $rules = [
        'shipment_number' => 'required|string|max:255',
        'consignee' => 'required|string|max:255',
        'customer_id' => 'required|exists:customers,id',
        'hbl_number' => 'nullable|string|max:255',
        'mbl_number' => 'nullable|string|max:255',
        'invoice_number' => 'nullable|string|max:255',
        'vessel_id' => 'nullable|exists:vessels,id',
        'port_of_discharge' => 'required|string|max:255',
        'status' => 'required|in:new,planning,documents_preparation,customs_clearance,ready_for_delivery,in_transit,delivered,completed',
        'planned_delivery_date' => 'nullable|date|after_or_equal:today',
        'total_cost' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string|max:1000',
        'cargo_description' => 'nullable|string|max:500',
        'cargo_weight' => 'nullable|numeric|min:0',
        'cargo_volume' => 'nullable|numeric|min:0',
    ];

    protected $messages = [
        'shipment_number.required' => 'Shipment number is required.',
        'consignee.required' => 'Consignee name is required.',
        'customer_id.required' => 'Please select a customer.',
        'customer_id.exists' => 'Selected customer does not exist.',
        'port_of_discharge.required' => 'Port of discharge is required.',
        'planned_delivery_date.after_or_equal' => 'Delivery date cannot be in the past.',
    ];

    public function mount()
    {
        $this->customers = Customer::active()->orderBy('company')->get();
        $this->vessels = Vessel::orderBy('name')->get();
        
        // Generate initial shipment number
        if (!$this->shipment_number) {
            $this->shipment_number = $this->generateShipmentNumber();
        }
    }

    public function render()
    {
        $query = Shipment::with(['customer', 'vessel']);

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('shipment_number', 'like', '%' . $this->search . '%')
                  ->orWhere('consignee', 'like', '%' . $this->search . '%')
                  ->orWhere('hbl_number', 'like', '%' . $this->search . '%')
                  ->orWhere('mbl_number', 'like', '%' . $this->search . '%')
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

        return view('livewire.shipment-manager', compact('shipments'))
            ->layout('layouts.app', ['title' => 'Shipment Management']);
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
        $this->consignee = '';
        $this->hbl_number = '';
        $this->mbl_number = '';
        $this->invoice_number = '';
        $this->customer_id = '';
        $this->vessel_id = '';
        $this->port_of_discharge = '';
        $this->status = 'new';
        $this->planned_delivery_date = '';
        $this->total_cost = '';
        $this->notes = '';
        $this->cargo_description = '';
        $this->cargo_weight = '';
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
                'consignee' => $this->consignee,
                'hbl_number' => $this->hbl_number,
                'mbl_number' => $this->mbl_number,
                'invoice_number' => $this->invoice_number,
                'customer_id' => $this->customer_id,
                'vessel_id' => $this->vessel_id ?: null,
                'port_of_discharge' => $this->port_of_discharge,
                'status' => $this->status,
                'planned_delivery_date' => $this->planned_delivery_date ?: null,
                'total_cost' => $this->total_cost ?: null,
                'notes' => $this->notes,
                'cargo_details' => $cargoDetails,
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
            $this->consignee = $this->editingShipment->consignee;
            $this->hbl_number = $this->editingShipment->hbl_number;
            $this->mbl_number = $this->editingShipment->mbl_number;
            $this->invoice_number = $this->editingShipment->invoice_number;
            $this->customer_id = $this->editingShipment->customer_id;
            $this->vessel_id = $this->editingShipment->vessel_id;
            $this->port_of_discharge = $this->editingShipment->port_of_discharge;
            $this->status = $this->editingShipment->status;
            $this->planned_delivery_date = $this->editingShipment->planned_delivery_date?->format('Y-m-d');
            $this->total_cost = $this->editingShipment->total_cost;
            $this->notes = $this->editingShipment->notes;
            
            $cargoDetails = $this->editingShipment->cargo_details ?? [];
            $this->cargo_description = $cargoDetails['description'] ?? '';
            $this->cargo_weight = $cargoDetails['weight_kg'] ?? '';
            $this->cargo_volume = $cargoDetails['volume_cbm'] ?? '';
            
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
