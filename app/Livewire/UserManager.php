<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserManager extends Component
{
    use WithPagination;

    public $showModal = false;
    public $editingUser = null;
    public $search = '';
    public $roleFilter = '';
    public $statusFilter = '';

    // Form fields
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = User::ROLE_USER;
    public $is_active = true;

    protected function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => 'required|in:' . implode(',', array_keys(User::getRoles())),
            'is_active' => 'boolean',
        ];

        // Password rules
        if (!$this->editingUser) {
            // New user - password required
            $rules['password'] = ['required', 'confirmed', Password::defaults()];
        } else {
            // Editing user - password optional
            if ($this->password) {
                $rules['password'] = ['confirmed', Password::defaults()];
            }
            // Email unique rule should exclude current user
            $rules['email'] = 'required|email|max:255|unique:users,email,' . $this->editingUser->id;
        }

        return $rules;
    }

    protected $messages = [
        'name.required' => 'Name is required.',
        'email.required' => 'Email is required.',
        'email.email' => 'Please provide a valid email address.',
        'email.unique' => 'This email is already registered.',
        'password.required' => 'Password is required for new users.',
        'password.confirmed' => 'Password confirmation does not match.',
    ];

    public function mount()
    {
        // Check if current user can manage users
        if (!auth()->user()->canManageUsers()) {
            abort(403, 'Unauthorized access to user management.');
        }
    }

    public function render()
    {
        $query = User::query();

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        // Apply role filter
        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        // Apply status filter
        if ($this->statusFilter !== '') {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.user-manager', [
            'users' => $users,
            'roles' => User::getRoles(),
        ]);
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
        $this->editingUser = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->role = User::ROLE_USER;
        $this->is_active = true;
    }

    public function save()
    {
        $this->validate();

        try {
            $userData = [
                'name' => $this->name,
                'email' => $this->email,
                'role' => $this->role,
                'is_active' => $this->is_active,
            ];

            if ($this->editingUser) {
                // Update existing user
                if ($this->password) {
                    $userData['password'] = Hash::make($this->password);
                }

                $this->editingUser->update($userData);
                session()->flash('message', 'User updated successfully!');
            } else {
                // Create new user
                $userData['password'] = Hash::make($this->password);
                $userData['email_verified_at'] = now();

                User::create($userData);
                session()->flash('message', 'User created successfully!');
            }

            $this->closeModal();
            $this->resetPage();

        } catch (\Exception $e) {
            session()->flash('error', 'Error saving user: ' . $e->getMessage());
        }
    }

    public function edit($userId)
    {
        $this->editingUser = User::find($userId);

        if ($this->editingUser) {
            $this->name = $this->editingUser->name;
            $this->email = $this->editingUser->email;
            $this->role = $this->editingUser->role;
            $this->is_active = $this->editingUser->is_active;
            $this->password = '';
            $this->password_confirmation = '';

            $this->showModal = true;
        }
    }

    public function toggleStatus($userId)
    {
        $user = User::find($userId);

        if ($user) {
            // Prevent deactivating your own account
            if ($user->id === auth()->id()) {
                session()->flash('error', 'You cannot deactivate your own account.');
                return;
            }

            $user->update(['is_active' => !$user->is_active]);

            $status = $user->is_active ? 'activated' : 'deactivated';
            session()->flash('message', "User {$status} successfully!");
        }
    }

    public function delete($userId)
    {
        try {
            $user = User::find($userId);

            if ($user) {
                // Prevent deleting your own account
                if ($user->id === auth()->id()) {
                    session()->flash('error', 'You cannot delete your own account.');
                    return;
                }

                // Prevent deleting last admin
                if ($user->role === User::ROLE_ADMIN && User::where('role', User::ROLE_ADMIN)->count() === 1) {
                    session()->flash('error', 'Cannot delete the last administrator.');
                    return;
                }

                $user->delete();
                session()->flash('message', 'User deleted successfully!');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting user: ' . $e->getMessage());
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
}