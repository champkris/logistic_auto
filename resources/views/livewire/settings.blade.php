<div>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <h2 class="text-2xl font-semibold text-gray-800">⚙️ System Settings</h2>
                    </div>
                    <p class="mt-2 text-gray-600">Configure system settings and manage users</p>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                        <button wire:click="setActiveTab('dropdown')"
                                class="@if($activeTab === 'dropdown') border-blue-500 text-blue-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            📋 Dropdown Configuration
                        </button>
                        @if(auth()->user()->canManageUsers())
                            <button wire:click="setActiveTab('users')"
                                    class="@if($activeTab === 'users') border-blue-500 text-blue-600 @else border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                👥 User Management
                            </button>
                        @endif
                    </nav>
                </div>
            </div>

            <!-- Tab Content -->
            @if($activeTab === 'dropdown')
                <!-- Dropdown Settings Tab Content -->
                <!-- Field Selector -->
                <div class="bg-white shadow-sm rounded-lg mb-6">
                    <div class="p-4">
                        @if($selectedField)
                            <div class="flex justify-end space-x-2 mb-4">
                                @if($selectedField === 'customers')
                                    <button wire:click="create"
                                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                        👥 Add New Customer
                                    </button>
                                @else
                                    @if(!in_array($selectedField, ['vessels']))
                                        <button wire:click="seedDefaultValues"
                                                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                            🌱 Seed Defaults
                                        </button>
                                    @endif
                                    <button wire:click="create"
                                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                        @if($selectedField === 'vessels')
                                            🚢 Add New Vessel
                                        @else
                                            ➕ Add New Option
                                        @endif
                                    </button>
                                @endif
                            </div>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            <!-- Dropdown Fields -->
                            <div class="w-full mb-2">
                                <span class="text-sm font-medium text-gray-600">Configurable Fields:</span>
                            </div>
                            @foreach($configurableFields as $key => $label)
                                <button wire:click="selectField('{{ $key }}')"
                                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors
                                        @if($selectedField === $key)
                                            bg-blue-600 text-white
                                        @else
                                            bg-gray-100 text-gray-700 hover:bg-gray-200
                                        @endif">
                                    {{ $label }}
                                </button>
                            @endforeach

                            <!-- Entity Management -->
                            <div class="w-full mt-4 mb-2">
                                <span class="text-sm font-medium text-gray-600">View Related Entities:</span>
                            </div>
                            @foreach($managedEntities as $key => $label)
                                <button wire:click="selectField('{{ $key }}')"
                                        class="px-4 py-2 rounded-md text-sm font-medium transition-colors
                                        @if($selectedField === $key)
                                            bg-green-600 text-white
                                        @else
                                            bg-gray-100 text-gray-700 hover:bg-gray-200
                                        @endif">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Search Bar -->
                @if($selectedField)
                    <div class="bg-white shadow-sm rounded-lg mb-6">
                        <div class="p-4">
                            <input wire:model.live="search"
                                   type="text"
                                   placeholder="Search {{ $selectedField === 'customers' ? 'customers' : ($selectedField === 'vessels' ? 'vessels' : 'options') }}..."
                                   class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                @endif

                <!-- Items Table -->
                @if($selectedField)
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            @if($items->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                @if(in_array($selectedField, ['customers', 'vessels']))
                                                    @if($selectedField === 'customers')
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact Name</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                                    @else
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vessel Name</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Terminal</th>
                                                    @endif
                                                @else
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                                                    @if($selectedField === 'port_terminal')
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">URL</th>
                                                    @endif
                                                    @if($selectedField === 'cs_reference')
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                                    @endif
                                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Sort Order</th>
                                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @if(in_array($selectedField, ['customers', 'vessels']))
                                                @if($selectedField === 'customers')
                                                    @foreach($items as $customer)
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $customer->company }}</td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $customer->name }}</td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $customer->email }}</td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $customer->phone }}</td>
                                                        </tr>
                                                    @endforeach
                                                @else
                                                    @foreach($items as $vessel)
                                                        <tr>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $vessel->name }}</td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $vessel->code ?? '-' }}</td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $vessel->terminal ?? '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                            @else
                                                @foreach($items as $item)
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                            {{ $item->value }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                            {{ $item->label }}
                                                        </td>
                                                        @if($selectedField === 'port_terminal')
                                                            <td class="px-6 py-4 text-sm text-gray-900 max-w-xs">
                                                                @if($item->url)
                                                                    <a href="{{ $item->url }}" target="_blank" class="text-blue-600 hover:text-blue-800 truncate block">
                                                                        {{ $item->url }}
                                                                    </a>
                                                                @else
                                                                    <span class="text-gray-400">-</span>
                                                                @endif
                                                            </td>
                                                        @endif
                                                        @if($selectedField === 'cs_reference')
                                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                                @if($item->email)
                                                                    <a href="mailto:{{ $item->email }}" class="text-blue-600 hover:text-blue-800">
                                                                        {{ $item->email }}
                                                                    </a>
                                                                @else
                                                                    <span class="text-gray-400">-</span>
                                                                @endif
                                                            </td>
                                                        @endif
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                                            {{ $item->sort_order }}
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                                            <button wire:click="toggleActive({{ $item->id }})"
                                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                                                                    @if($item->is_active)
                                                                        bg-green-100 text-green-800
                                                                    @else
                                                                        bg-gray-100 text-gray-800
                                                                    @endif">
                                                                {{ $item->is_active ? 'Active' : 'Inactive' }}
                                                            </button>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center space-x-2">
                                                            <button wire:click="edit({{ $item->id }})"
                                                                    class="text-blue-600 hover:text-blue-900">
                                                                ✏️ Edit
                                                            </button>
                                                            <button wire:click="delete({{ $item->id }})"
                                                                    onclick="return confirm('Are you sure you want to delete this option?')"
                                                                    class="text-red-600 hover:text-red-900">
                                                                🗑️ Delete
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <div class="mt-4">
                                    {{ $items->links() }}
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-600">
                                        @if($selectedField)
                                            No {{ $selectedField === 'customers' ? 'customers' : ($selectedField === 'vessels' ? 'vessels' : 'options') }} found.
                                        @else
                                            Select a field to manage its options.
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

            @elseif($activeTab === 'users')
                <!-- User Management Tab Content -->
                <livewire:user-manager />
            @endif
        </div>
    </div>

    <!-- Modal for Add/Edit (Dropdown Settings only) -->
    @if($showModal && $activeTab === 'dropdown')
        <div class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" wire:click="$set('showModal', false)">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle @if(in_array($selectedField, ['vessels', 'customers'])) sm:max-w-4xl @else sm:max-w-lg @endif sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    @if($selectedField === 'vessels')
                                        {{ $editingItem ? 'Edit Vessel' : 'Add New Vessel' }}
                                    @elseif($selectedField === 'customers')
                                        {{ $editingItem ? 'Edit Customer' : 'Add New Customer' }}
                                    @else
                                        {{ $editingItem ? 'Edit Option' : 'Add New Option' }}
                                    @endif
                                </h3>

                                <form wire:submit.prevent="save">
                                    <div class="space-y-4">
                                        @if($selectedField === 'vessels')
                                            <!-- Vessel Form Fields -->
                                            <div class="grid grid-cols-2 gap-4">
                                                <!-- Vessel Name -->
                                                <div>
                                                    <label for="vessel_name" class="block text-sm font-medium text-gray-700">Vessel Name *</label>
                                                    <input wire:model="vessel_name"
                                                           type="text"
                                                           id="vessel_name"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('vessel_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- Voyage Number -->
                                                <div>
                                                    <label for="voyage_number" class="block text-sm font-medium text-gray-700">Voyage Number</label>
                                                    <input wire:model="voyage_number"
                                                           type="text"
                                                           id="voyage_number"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('voyage_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>
                                            </div>

                                            <!-- Full Vessel Name -->
                                            <div>
                                                <label for="full_vessel_name" class="block text-sm font-medium text-gray-700">Full Vessel Name</label>
                                                <input wire:model="full_vessel_name"
                                                       type="text"
                                                       id="full_vessel_name"
                                                       placeholder="e.g., WAN HAI 517 S095"
                                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                @error('full_vessel_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                <p class="mt-1 text-xs text-gray-500">Complete vessel name as it appears in schedules</p>
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <!-- ETA -->
                                                <div>
                                                    <label for="eta" class="block text-sm font-medium text-gray-700">ETA</label>
                                                    <input wire:model="eta"
                                                           type="datetime-local"
                                                           id="eta"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('eta') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- Status -->
                                                <div>
                                                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                                    <select wire:model="status"
                                                            id="status"
                                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        <option value="scheduled">Scheduled</option>
                                                        <option value="arrived">Arrived</option>
                                                        <option value="departed">Departed</option>
                                                        <option value="delayed">Delayed</option>
                                                    </select>
                                                    @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <!-- Port -->
                                                <div>
                                                    <label for="port" class="block text-sm font-medium text-gray-700">Port</label>
                                                    <input wire:model="port"
                                                           type="text"
                                                           id="port"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('port') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- IMO Number -->
                                                <div>
                                                    <label for="imo_number" class="block text-sm font-medium text-gray-700">IMO Number</label>
                                                    <input wire:model="imo_number"
                                                           type="text"
                                                           id="imo_number"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('imo_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>
                                            </div>

                                            <!-- Agent -->
                                            <div>
                                                <label for="agent" class="block text-sm font-medium text-gray-700">Agent</label>
                                                <input wire:model="agent"
                                                       type="text"
                                                       id="agent"
                                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                @error('agent') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- Notes -->
                                            <div>
                                                <label for="vessel_notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                                <textarea wire:model="vessel_notes"
                                                          id="vessel_notes"
                                                          rows="3"
                                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                                                @error('vessel_notes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                        @elseif($selectedField === 'customers')
                                            <!-- Customer Form Fields -->
                                            <div class="grid grid-cols-2 gap-4">
                                                <!-- Company Name -->
                                                <div>
                                                    <label for="company" class="block text-sm font-medium text-gray-700">Company Name *</label>
                                                    <input wire:model="company"
                                                           type="text"
                                                           id="company"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('company') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- Contact Name -->
                                                <div>
                                                    <label for="contact_name" class="block text-sm font-medium text-gray-700">Contact Name</label>
                                                    <input wire:model="contact_name"
                                                           type="text"
                                                           id="contact_name"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('contact_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- Email -->
                                                <div>
                                                    <label for="customer_email" class="block text-sm font-medium text-gray-700">Email</label>
                                                    <input wire:model="customer_email"
                                                           type="email"
                                                           id="customer_email"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('customer_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- Phone -->
                                                <div>
                                                    <label for="customer_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                                    <input wire:model="customer_phone"
                                                           type="text"
                                                           id="customer_phone"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('customer_phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>

                                                <!-- Address -->
                                                <div class="col-span-2">
                                                    <label for="customer_address" class="block text-sm font-medium text-gray-700">Address</label>
                                                    <textarea wire:model="customer_address"
                                                              id="customer_address"
                                                              rows="3"
                                                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                                                    @error('customer_address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                </div>
                                            </div>
                                        @else
                                            <!-- Dropdown Option Form Fields -->
                                            <!-- Value -->
                                            <div>
                                                <label for="value" class="block text-sm font-medium text-gray-700">Value *</label>
                                                <input wire:model="value"
                                                       type="text"
                                                       id="value"
                                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                @error('value') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- Label -->
                                            <div>
                                                <label for="label" class="block text-sm font-medium text-gray-700">Display Label *</label>
                                                <input wire:model="label"
                                                       type="text"
                                                       id="label"
                                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                @error('label') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- URL (only for port terminals) -->
                                            @if($selectedField === 'port_terminal')
                                                <div>
                                                    <label for="url" class="block text-sm font-medium text-gray-700">Port Website URL</label>
                                                    <input wire:model="url"
                                                           type="url"
                                                           id="url"
                                                           placeholder="https://example.com"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('url') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                    <p class="mt-1 text-xs text-gray-500">URL for the port's website to check vessel ETA automatically</p>
                                                </div>
                                            @endif

                                            @if($selectedField === 'cs_reference')
                                                <div>
                                                    <label for="email" class="block text-sm font-medium text-gray-700">CS Email</label>
                                                    <input wire:model="email"
                                                           type="email"
                                                           id="email"
                                                           placeholder="cs@easternair.co.th"
                                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                    @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                    <p class="mt-1 text-xs text-gray-500">Email address for this CS team member</p>
                                                </div>
                                            @endif

                                            <!-- Sort Order -->
                                            <div>
                                                <label for="sort_order" class="block text-sm font-medium text-gray-700">Sort Order</label>
                                                <input wire:model="sort_order"
                                                       type="number"
                                                       id="sort_order"
                                                       min="0"
                                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                @error('sort_order') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                        @endif

                                        @if($selectedField !== 'vessels')
                                            <!-- Is Active -->
                                            <div>
                                                <label class="flex items-center">
                                                    <input wire:model="is_active"
                                                           type="checkbox"
                                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                    <span class="ml-2 text-sm text-gray-700">Active</span>
                                                </label>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mt-6 flex justify-end space-x-2">
                                        <button type="button"
                                                wire:click="closeModal"
                                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                            Cancel
                                        </button>
                                        <button type="submit"
                                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            {{ $editingItem ? 'Update' : 'Create' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>