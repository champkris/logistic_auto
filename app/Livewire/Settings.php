<?php

namespace App\Livewire;

use App\Models\DropdownSetting;
use App\Models\Customer;
use App\Models\Vessel;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;

class Settings extends Component
{
    use WithPagination;

    // Tab management
    public $activeTab = 'dropdown';

    // Dropdown settings properties
    public $selectedField = '';
    public $editingItem = null;
    public $showModal = false;

    // Form fields
    public $value = '';
    public $label = '';
    public $url = '';
    public $email = '';
    public $sort_order = 0;
    public $is_active = true;

    // Vessel form fields
    public $vessel_name = '';
    public $full_vessel_name = '';
    public $voyage_number = '';
    public $eta = '';
    public $port = '';
    public $status = 'scheduled';
    public $imo_number = '';
    public $agent = '';
    public $vessel_notes = '';

    // Customer form fields
    public $company = '';
    public $contact_name = '';
    public $customer_email = '';
    public $customer_phone = '';
    public $customer_address = '';

    // Search
    public $search = '';

    public $configurableFields = [];
    public $managedEntities = [
        'customers' => 'Customers',
        'vessels' => 'Vessels',
    ];

    protected $rules = [
        'value' => 'sometimes|required|string|max:255',
        'label' => 'sometimes|required|string|max:255',
        'url' => 'nullable|url|max:500',
        'email' => 'nullable|email|max:255',
        'sort_order' => 'integer|min:0',
        'is_active' => 'boolean',
        // Vessel validation rules
        'vessel_name' => 'sometimes|required|string|max:255',
        'full_vessel_name' => 'nullable|string|max:255',
        'voyage_number' => 'nullable|string|max:100',
        'eta' => 'nullable|date_format:Y-m-d\TH:i',
        'port' => 'nullable|string|max:255',
        'status' => 'nullable|in:scheduled,arrived,departed,delayed',
        'imo_number' => 'nullable|string|max:20',
        'agent' => 'nullable|string|max:255',
        'vessel_notes' => 'nullable|string|max:1000',
        // Customer validation rules
        'company' => 'sometimes|required|string|max:255',
        'contact_name' => 'nullable|string|max:255',
        'customer_email' => 'nullable|email|max:255',
        'customer_phone' => 'nullable|string|max:20',
        'customer_address' => 'nullable|string|max:1000',
    ];

    public function mount()
    {
        $this->configurableFields = DropdownSetting::getConfigurableFields();
        // Set default selected field
        $this->selectedField = array_key_first($this->configurableFields) ?? '';
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
        if ($tab === 'dropdown' && !$this->selectedField && count($this->configurableFields) > 0) {
            $this->selectedField = array_key_first($this->configurableFields);
        }
    }

    public function render()
    {
        // Check if current user can access settings
        if ($this->activeTab === 'users' && !auth()->user()->canManageUsers()) {
            $this->activeTab = 'dropdown';
        }

        $items = collect();

        if ($this->selectedField && $this->activeTab === 'dropdown') {
            if ($this->selectedField === 'customers') {
                $query = Customer::query();
                if ($this->search) {
                    $query->where(function ($q) {
                        $q->where('company', 'like', '%' . $this->search . '%')
                          ->orWhere('name', 'like', '%' . $this->search . '%');
                    });
                }
                $items = $query->orderBy('company')->paginate(15);
            } elseif ($this->selectedField === 'vessels') {
                $query = Vessel::query();
                if ($this->search) {
                    $query->where('name', 'like', '%' . $this->search . '%');
                }
                $items = $query->orderBy('name')->paginate(15);
            } else {
                $query = DropdownSetting::where('field_name', $this->selectedField);

                if ($this->search) {
                    $query->where(function ($q) {
                        $q->where('value', 'like', '%' . $this->search . '%')
                          ->orWhere('label', 'like', '%' . $this->search . '%');
                    });
                }

                $items = $query->orderBy('sort_order')
                              ->orderBy('label')
                              ->paginate(15);
            }
        }

        return view('livewire.settings', [
            'items' => $items
        ])->layout('layouts.app', ['title' => 'Settings']);
    }

    public function selectField($field)
    {
        $this->selectedField = $field;
        $this->search = '';
        $this->resetForm();
    }

    public function create()
    {
        Log::info('Create method called', [
            'selectedField' => $this->selectedField,
            'activeTab' => $this->activeTab
        ]);
        $this->resetForm();
        $this->showModal = true;
        Log::info('Modal should now be visible', ['showModal' => $this->showModal]);
    }

    public function edit($id)
    {
        if ($this->selectedField === 'customers') {
            $customer = \App\Models\Customer::find($id);
            if ($customer) {
                $this->editingItem = $customer;
                $this->company = $customer->company;
                $this->contact_name = $customer->name;
                $this->customer_email = $customer->email;
                $this->customer_phone = $customer->phone;
                $this->customer_address = $customer->address;
                $this->showModal = true;
            }
            return;
        }

        if ($this->selectedField === 'vessels') {
            $vessel = Vessel::find($id);
            if ($vessel) {
                $this->editingItem = $vessel;
                $this->vessel_name = $vessel->vessel_name;
                $this->full_vessel_name = $vessel->full_vessel_name;
                $this->voyage_number = $vessel->voyage_number;
                $this->eta = $vessel->eta ? $vessel->eta->format('Y-m-d\TH:i') : '';
                $this->port = $vessel->port;
                $this->status = $vessel->status;
                $this->imo_number = $vessel->imo_number;
                $this->agent = $vessel->agent;
                $this->vessel_notes = $vessel->notes;
                $this->showModal = true;
            }
            return;
        }

        $item = DropdownSetting::find($id);

        if ($item) {
            $this->editingItem = $item;
            $this->value = $item->value;
            $this->label = $item->label;
            $this->url = $item->url ?? '';
            $this->email = $item->email ?? '';
            $this->sort_order = $item->sort_order;
            $this->is_active = $item->is_active;
            $this->showModal = true;
        }
    }

    public function save()
    {
        Log::info('Save method called', [
            'selectedField' => $this->selectedField,
            'vessel_name' => $this->vessel_name ?? 'not set',
            'value' => $this->value ?? 'not set'
        ]);

        // Dynamic validation based on selected field
        if ($this->selectedField === 'vessels') {
            $this->validate([
                'vessel_name' => 'required|string|max:255',
                'full_vessel_name' => 'nullable|string|max:255',
                'voyage_number' => 'nullable|string|max:100',
                'eta' => 'nullable|date_format:Y-m-d\TH:i',
                'port' => 'nullable|string|max:255',
                'status' => 'nullable|in:scheduled,arrived,departed,delayed',
                'imo_number' => 'nullable|string|max:20',
                'agent' => 'nullable|string|max:255',
                'vessel_notes' => 'nullable|string|max:1000',
            ]);
        } elseif ($this->selectedField === 'customers') {
            $this->validate([
                'company' => 'required|string|max:255',
                'contact_name' => 'nullable|string|max:255',
                'customer_email' => 'nullable|email|max:255',
                'customer_phone' => 'nullable|string|max:20',
                'customer_address' => 'nullable|string|max:1000',
            ]);
        } else {
            $this->validate([
                'value' => 'required|string|max:255',
                'label' => 'required|string|max:255',
                'url' => 'nullable|url|max:500',
                'sort_order' => 'integer|min:0',
                'is_active' => 'boolean',
            ]);
        }

        try {
            if ($this->selectedField === 'vessels') {
                // Handle vessel creation/update
                $vesselData = [
                    'vessel_name' => $this->vessel_name,
                    'name' => $this->vessel_name, // For API compatibility
                    'full_vessel_name' => $this->full_vessel_name,
                    'voyage_number' => $this->voyage_number,
                    'eta' => $this->eta ? \Carbon\Carbon::parse($this->eta) : null,
                    'port' => $this->port,
                    'status' => $this->status,
                    'imo_number' => $this->imo_number,
                    'agent' => $this->agent,
                    'notes' => $this->vessel_notes,
                ];

                if ($this->editingItem) {
                    $this->editingItem->update($vesselData);
                    $this->dispatch('success', message: 'Vessel updated successfully!');
                } else {
                    Vessel::create($vesselData);
                    $this->dispatch('success', message: 'Vessel created successfully!');
                }
            } elseif ($this->selectedField === 'customers') {
                // Handle customer creation/update
                $customerData = [
                    'company' => $this->company,
                    'name' => $this->contact_name,
                    'email' => $this->customer_email,
                    'phone' => $this->customer_phone,
                    'address' => $this->customer_address,
                ];

                if ($this->editingItem) {
                    $this->editingItem->update($customerData);
                    $this->dispatch('success', message: 'Customer updated successfully!');
                } else {
                    \App\Models\Customer::create($customerData);
                    $this->dispatch('success', message: 'Customer created successfully!');
                }
            } else {
                // Handle dropdown setting creation/update
                $data = [
                    'field_name' => $this->selectedField,
                    'value' => $this->value,
                    'label' => $this->label,
                    'url' => $this->url ?: null,
                    'email' => $this->email ?: null,
                    'sort_order' => $this->sort_order,
                    'is_active' => $this->is_active,
                ];

                if ($this->editingItem) {
                    // Check for duplicate value if changed
                    if ($this->editingItem->value !== $this->value) {
                        $exists = DropdownSetting::where('field_name', $this->selectedField)
                                                ->where('value', $this->value)
                                                ->exists();
                        if ($exists) {
                            $this->addError('value', 'This value already exists for this field.');
                            return;
                        }
                    }

                    $this->editingItem->update($data);
                    $this->dispatch('success', message: 'Option updated successfully!');
                } else {
                    // Check for duplicate value
                    $exists = DropdownSetting::where('field_name', $this->selectedField)
                                            ->where('value', $this->value)
                                            ->exists();
                    if ($exists) {
                        $this->addError('value', 'This value already exists for this field.');
                        return;
                    }

                    DropdownSetting::create($data);
                    $this->dispatch('success', message: 'Option created successfully!');
                }
            }

            $this->resetForm();
            $this->showModal = false;
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Error saving: ' . $e->getMessage());
        }
    }

    public function toggleActive($id)
    {
        if ($this->selectedField === 'customers' || $this->selectedField === 'vessels') {
            return;
        }

        $item = DropdownSetting::find($id);
        if ($item) {
            $item->update(['is_active' => !$item->is_active]);
            $this->dispatch('success', message: 'Status updated successfully!');
        }
    }

    public function delete($id)
    {
        try {
            if ($this->selectedField === 'customers') {
                $customer = Customer::find($id);
                if ($customer) {
                    // Check if customer has related shipments
                    if ($customer->shipments()->exists()) {
                        $this->dispatch('error', message: 'Cannot delete customer with existing shipments.');
                        return;
                    }
                    $customer->delete();
                    $this->dispatch('success', message: 'Customer deleted successfully!');
                }
                return;
            }

            if ($this->selectedField === 'vessels') {
                $vessel = Vessel::find($id);
                if ($vessel) {
                    // Check if vessel has related shipments
                    $shipmentCount = $vessel->shipments()->count();
                    if ($shipmentCount > 0) {
                        $this->dispatch('error', message: "Cannot delete vessel '{$vessel->name}' - it has {$shipmentCount} related shipment(s).");
                        return;
                    }

                    $vesselName = $vessel->name;
                    $vessel->delete();
                    $this->dispatch('success', message: "Vessel '{$vesselName}' (ID: {$id}) deleted successfully!");

                    // Force refresh the vessels list
                    $this->resetPage();
                } else {
                    $this->dispatch('error', message: "Vessel with ID {$id} not found.");
                }
                return;
            }

            $item = DropdownSetting::find($id);
            if ($item) {
                $item->delete();
                $this->dispatch('success', message: 'Option deleted successfully!');
            }
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Error deleting: ' . $e->getMessage());
        }
    }

    public function resetForm()
    {
        $this->editingItem = null;
        $this->value = '';
        $this->label = '';
        $this->url = '';
        $this->email = '';
        $this->sort_order = 0;
        $this->is_active = true;

        // Reset vessel fields
        $this->vessel_name = '';
        $this->full_vessel_name = '';
        $this->voyage_number = '';
        $this->eta = '';
        $this->port = '';
        $this->status = 'scheduled';
        $this->imo_number = '';
        $this->agent = '';
        $this->vessel_notes = '';

        // Reset customer fields
        $this->company = '';
        $this->contact_name = '';
        $this->customer_email = '';
        $this->customer_phone = '';
        $this->customer_address = '';

        $this->resetErrorBag();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function seedDefaultValues()
    {
        try {
            // Seed default port terminals
            $portTerminals = [
                ['value' => 'A0', 'label' => 'A0'],
                ['value' => 'A3', 'label' => 'A3'],
                ['value' => 'B1', 'label' => 'B1'],
                ['value' => 'B3', 'label' => 'B3'],
                ['value' => 'B4', 'label' => 'B4'],
                ['value' => 'C1', 'label' => 'C1'],
                ['value' => 'C3', 'label' => 'C3'],
            ];

            foreach ($portTerminals as $index => $terminal) {
                DropdownSetting::updateOrCreate(
                    ['field_name' => 'port_terminal', 'value' => $terminal['value']],
                    ['label' => $terminal['label'], 'sort_order' => $index, 'is_active' => true]
                );
            }

            // Seed default transport types
            $transportTypes = [
                ['value' => 'PUI', 'label' => 'PUI'],
                ['value' => 'FRANK', 'label' => 'FRANK'],
                ['value' => 'GUS', 'label' => 'GUS'],
                ['value' => 'MON', 'label' => 'MON'],
                ['value' => 'NOON', 'label' => 'NOON'],
                ['value' => 'TOON', 'label' => 'TOON'],
                ['value' => 'ING', 'label' => 'ING'],
                ['value' => 'JOW', 'label' => 'JOW'],
            ];

            foreach ($transportTypes as $index => $type) {
                DropdownSetting::updateOrCreate(
                    ['field_name' => 'transport_type', 'value' => $type['value']],
                    ['label' => $type['label'], 'sort_order' => $index, 'is_active' => true]
                );
            }

            // Seed default ports
            $ports = [
                ['value' => 'laem_chabang', 'label' => 'Laem Chabang'],
                ['value' => 'bangkok_port', 'label' => 'Bangkok Port'],
                ['value' => 'map_ta_phut', 'label' => 'Map Ta Phut'],
                ['value' => 'sattahip', 'label' => 'Sattahip'],
            ];

            foreach ($ports as $index => $port) {
                DropdownSetting::updateOrCreate(
                    ['field_name' => 'port_of_discharge', 'value' => $port['value']],
                    ['label' => $port['label'], 'sort_order' => $index, 'is_active' => true]
                );
            }

            // Seed default final status
            $finalStatuses = [
                ['value' => 'MEW', 'label' => 'MEW'],
                ['value' => 'MON', 'label' => 'MON'],
                ['value' => 'NOON', 'label' => 'NOON'],
                ['value' => 'ING', 'label' => 'ING'],
                ['value' => 'JOW', 'label' => 'JOW'],
            ];

            foreach ($finalStatuses as $index => $status) {
                DropdownSetting::updateOrCreate(
                    ['field_name' => 'final_status', 'value' => $status['value']],
                    ['label' => $status['label'], 'sort_order' => $index, 'is_active' => true]
                );
            }

            $this->dispatch('success', message: 'Default values seeded successfully!');
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Error seeding values: ' . $e->getMessage());
        }
    }

    /**
     * Reset pagination when selected field changes
     */
    public function updatingSelectedField()
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when active tab changes
     */
    public function updatingActiveTab()
    {
        $this->resetPage();
    }
}