<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Customer;
use Illuminate\Validation\Rule;

class CustomerManager extends Component
{
    use WithPagination;

    // Form properties
    public $name = '';
    public $company = '';
    public $email = '';
    public $phone = '';
    public $address = '';
    public $is_active = true;
    
    // Modal and state management
    public $showModal = false;
    public $editingCustomer = null;
    public $search = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'company' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'nullable|string|max:20',
        'address' => 'nullable|string|max:500',
        'is_active' => 'boolean',
    ];

    protected $messages = [
        'name.required' => 'Customer name is required.',
        'company.required' => 'Company name is required.',
        'email.required' => 'Email address is required.',
        'email.email' => 'Please enter a valid email address.',
    ];

    public function render()
    {
        $customers = Customer::where('name', 'like', '%' . $this->search . '%')
            ->orWhere('company', 'like', '%' . $this->search . '%')
            ->orWhere('email', 'like', '%' . $this->search . '%')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.customer-manager', compact('customers'));
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
        $this->editingCustomer = null;
        $this->name = '';
        $this->company = '';
        $this->email = '';
        $this->phone = '';
        $this->address = '';
        $this->is_active = true;
    }

    public function save()
    {
        $this->validate();

        try {
            if ($this->editingCustomer) {
                // Update existing customer
                $this->editingCustomer->update([
                    'name' => $this->name,
                    'company' => $this->company,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'address' => $this->address,
                    'is_active' => $this->is_active,
                ]);

                session()->flash('message', 'Customer updated successfully!');
            } else {
                // Create new customer
                Customer::create([
                    'name' => $this->name,
                    'company' => $this->company,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'address' => $this->address,
                    'is_active' => $this->is_active,
                    'notification_preferences' => [
                        'email_updates' => true,
                        'sms_notifications' => false,
                        'daily_reports' => true,
                    ],
                ]);

                session()->flash('message', 'Customer created successfully!');
            }

            $this->closeModal();
            $this->resetPage();

        } catch (\Exception $e) {
            session()->flash('error', 'Error saving customer: ' . $e->getMessage());
        }
    }

    public function edit($customerId)
    {
        $this->editingCustomer = Customer::find($customerId);
        
        if ($this->editingCustomer) {
            $this->name = $this->editingCustomer->name;
            $this->company = $this->editingCustomer->company;
            $this->email = $this->editingCustomer->email;
            $this->phone = $this->editingCustomer->phone;
            $this->address = $this->editingCustomer->address;
            $this->is_active = $this->editingCustomer->is_active;
            $this->showModal = true;
        }
    }

    public function delete($customerId)
    {
        try {
            $customer = Customer::find($customerId);
            
            if ($customer) {
                // Check if customer has active shipments
                if ($customer->activeShipments()->count() > 0) {
                    session()->flash('error', 'Cannot delete customer with active shipments. Please complete or transfer shipments first.');
                    return;
                }

                $customer->delete();
                session()->flash('message', 'Customer deleted successfully!');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting customer: ' . $e->getMessage());
        }
    }

    public function toggleStatus($customerId)
    {
        try {
            $customer = Customer::find($customerId);
            
            if ($customer) {
                $customer->update(['is_active' => !$customer->is_active]);
                
                $status = $customer->is_active ? 'activated' : 'deactivated';
                session()->flash('message', "Customer {$status} successfully!");
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating customer status: ' . $e->getMessage());
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }
}
