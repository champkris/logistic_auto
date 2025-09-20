<div>
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <div>
            <h3 class="text-lg font-medium text-gray-900">👥 User Management</h3>
            <p class="mt-1 text-sm text-gray-700">Manage system users and their access levels</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button wire:click="openModal"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                ➕ Add New User
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="grid md:grid-cols-3 gap-4">
            <!-- Search -->
            <div>
                <label for="search" class="sr-only">Search users</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input wire:model.live="search"
                           type="text"
                           placeholder="Search by name or email..."
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>

            <!-- Role Filter -->
            <div>
                <select wire:model.live="roleFilter"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">All Roles</option>
                    @foreach($roles as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <select wire:model.live="statusFilter"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('message') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Users Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            @if($users->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">LINE</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($users as $user)
                                <tr class="hover:bg-gray-50">
                                    <!-- User Info -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                @if($user->line_picture_url)
                                                    <img class="h-10 w-10 rounded-full" src="{{ $user->line_picture_url }}" alt="{{ $user->name }}">
                                                @else
                                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                        <span class="text-gray-600 font-medium text-sm">{{ substr($user->name, 0, 1) }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $user->name }}
                                                    @if($user->id === auth()->id())
                                                        <span class="text-xs text-blue-600">(You)</span>
                                                    @endif
                                                </div>
                                                <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Role -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($user->role === 'admin') bg-purple-100 text-purple-800
                                            @elseif($user->role === 'manager') bg-blue-100 text-blue-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ $user->getRoleDisplayName() }}
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <button wire:click="toggleStatus({{ $user->id }})"
                                                @if($user->id === auth()->id()) disabled @endif
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @if($user->is_active)
                                                    bg-green-100 text-green-800
                                                @else
                                                    bg-red-100 text-red-800
                                                @endif
                                                @if($user->id !== auth()->id()) hover:opacity-75 cursor-pointer @else opacity-50 cursor-not-allowed @endif">
                                            {{ $user->is_active ? '✅ Active' : '❌ Inactive' }}
                                        </button>
                                    </td>

                                    <!-- LINE Status -->
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @if($user->hasLineAccount())
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                📱 Connected
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                ❌ Not Connected
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Registered Date -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $user->created_at->format('M d, Y') }}
                                        <div class="text-xs text-gray-400">{{ $user->created_at->diffForHumans() }}</div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <button wire:click="edit({{ $user->id }})"
                                                class="text-blue-600 hover:text-blue-900 mr-3">
                                            ✏️ Edit
                                        </button>
                                        @if($user->id !== auth()->id())
                                            <button wire:click="delete({{ $user->id }})"
                                                    onclick="return confirm('Are you sure you want to delete this user?')"
                                                    class="text-red-600 hover:text-red-900">
                                                🗑️ Delete
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $users->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No users found</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new user.</p>
                    <div class="mt-6">
                        <button wire:click="openModal"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                            ➕ Add New User
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- User Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ $editingUser ? '✏️ Edit User' : '➕ Create New User' }}
                    </h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form wire:submit.prevent="save" class="mt-4">
                    <div class="space-y-4">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name *</label>
                            <input wire:model="name"
                                   type="text"
                                   id="name"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                            <input wire:model="email"
                                   type="email"
                                   id="email"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Password {{ $editingUser ? '(leave blank to keep current)' : '*' }}
                            </label>
                            <input wire:model="password"
                                   type="password"
                                   id="password"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Password Confirmation -->
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                Confirm Password {{ $editingUser ? '' : '*' }}
                            </label>
                            <input wire:model="password_confirmation"
                                   type="password"
                                   id="password_confirmation"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <!-- Role -->
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Role *</label>
                            <select wire:model="role"
                                    id="role"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @foreach($roles as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="flex items-center">
                                <input wire:model="is_active"
                                       type="checkbox"
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Active Account</span>
                            </label>
                        </div>

                        <!-- Role Description -->
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-900 mb-2">📋 Role Permissions:</h4>
                            <ul class="text-xs text-blue-800 space-y-1">
                                <li><strong>Administrator:</strong> Full system access, manage all users</li>
                                <li><strong>Manager:</strong> Manage users and shipments, view reports</li>
                                <li><strong>User:</strong> View and manage own shipments only</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end pt-4 border-t mt-6 space-x-3">
                        <button type="button"
                                wire:click="closeModal"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            {{ $editingUser ? 'Update User' : 'Create User' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>