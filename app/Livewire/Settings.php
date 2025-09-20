<?php

namespace App\Livewire;

use App\Models\DropdownSetting;
use App\Models\Customer;
use App\Models\Vessel;
use Livewire\Component;

class Settings extends Component
{
    public $selectedField = '';
    public $editingItem = null;
    public $showModal = false;

    // Form fields
    public $value = '';
    public $label = '';
    public $sort_order = 0;
    public $is_active = true;

    // Search
    public $search = '';

    public $configurableFields = [];
    public $managedEntities = [
        'customers' => 'Customers',
        'vessels' => 'Vessels',
    ];

    protected $rules = [
        'value' => 'required|string|max:255',
        'label' => 'required|string|max:255',
        'sort_order' => 'integer|min:0',
        'is_active' => 'boolean',
    ];

    public function mount()
    {
        $this->configurableFields = DropdownSetting::getConfigurableFields();
        // Set default selected field
        $this->selectedField = array_key_first($this->configurableFields) ?? '';
    }

    public function render()
    {
        $items = collect();

        if ($this->selectedField) {
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
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit($id)
    {
        if ($this->selectedField === 'customers') {
            $this->dispatch('info', message: 'Please use the Customer Management page to edit customers.');
            return;
        }

        if ($this->selectedField === 'vessels') {
            $this->dispatch('info', message: 'Please use the Vessel Management page to edit vessels.');
            return;
        }

        $item = DropdownSetting::find($id);

        if ($item) {
            $this->editingItem = $item;
            $this->value = $item->value;
            $this->label = $item->label;
            $this->sort_order = $item->sort_order;
            $this->is_active = $item->is_active;
            $this->showModal = true;
        }
    }

    public function save()
    {
        $this->validate();

        try {
            $data = [
                'field_name' => $this->selectedField,
                'value' => $this->value,
                'label' => $this->label,
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

            $this->resetForm();
            $this->showModal = false;
        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Error saving option: ' . $e->getMessage());
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
        if ($this->selectedField === 'customers' || $this->selectedField === 'vessels') {
            $this->dispatch('error', message: 'Cannot delete this type of item from here.');
            return;
        }

        $item = DropdownSetting::find($id);
        if ($item) {
            $item->delete();
            $this->dispatch('success', message: 'Option deleted successfully!');
        }
    }

    public function resetForm()
    {
        $this->editingItem = null;
        $this->value = '';
        $this->label = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->resetErrorBag();
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
}