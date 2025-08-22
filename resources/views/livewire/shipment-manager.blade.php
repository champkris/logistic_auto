<div class="p-6 space-y-6">
    <!-- Header Section -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">📦 Shipment Management</h1>
            <p class="mt-2 text-sm text-gray-700">Track and manage all shipments from arrival to delivery</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button wire:click="openModal" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                ➕ New Shipment
            </button>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white p-4 rounded-lg shadow">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0 sm:space-x-4">
            <div class="flex-1 max-w-md">
                <label for="search" class="sr-only">Search shipments</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input wire:model.live="search" 
                           type="text" 
                           placeholder="Search shipments..." 
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>
            <div class="flex space-x-3">
                <select wire:model.live="statusFilter" 
                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <option value="">All Status</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="bg-green-50 border border-green-200 rounded-md p-4">
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
        <div class="bg-red-50 border border-red-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Shipments Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            @if($shipments->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shipment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Consignee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delivery</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($shipments as $shipment)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $shipment->shipment_number }}</div>
                                            <div class="text-sm text-gray-500">
                                                @if($shipment->hbl_number)
                                                    HBL: {{ $shipment->hbl_number }}
                                                @endif
                                                @if($shipment->mbl_number)
                                                    <br>MBL: {{ $shipment->mbl_number }}
                                                @endif
                                                @if($shipment->vessel_code)
                                                    <br>Vessel Code: {{ $shipment->vessel_code }}
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $shipment->customer->company ?? 'N/A' }}</div>
                                        <div class="text-sm text-gray-500">{{ $shipment->customer->name ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $shipment->consignee }}</div>
                                        <div class="text-sm text-gray-500">{{ $shipment->port_of_discharge }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            @switch($shipment->status_color)
                                                @case('blue') bg-blue-100 text-blue-800 @break
                                                @case('yellow') bg-yellow-100 text-yellow-800 @break
                                                @case('orange') bg-orange-100 text-orange-800 @break
                                                @case('purple') bg-purple-100 text-purple-800 @break
                                                @case('green') bg-green-100 text-green-800 @break
                                                @case('indigo') bg-indigo-100 text-indigo-800 @break
                                                @case('emerald') bg-emerald-100 text-emerald-800 @break
                                                @default bg-gray-100 text-gray-800
                                            @endswitch">
                                            {{ $statusOptions[$shipment->status] ?? $shipment->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($shipment->planned_delivery_date)
                                            <div class="text-sm text-gray-900">
                                                📅 {{ $shipment->planned_delivery_date->format('M j, Y') }}
                                            </div>
                                        @endif
                                        @if($shipment->vessel)
                                            <div class="text-sm text-gray-500">
                                                🚢 {{ $shipment->vessel->name }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <button wire:click="edit({{ $shipment->id }})" 
                                                class="text-blue-600 hover:text-blue-900">
                                            ✏️ Edit
                                        </button>
                                        
                                        <!-- Quick Status Updates -->
                                        @if($shipment->status !== 'completed')
                                            <div class="relative inline-block text-left">
                                                <select wire:change="updateStatus({{ $shipment->id }}, $event.target.value)" 
                                                        class="text-xs border-gray-300 rounded-md">
                                                    <option value="">Status...</option>
                                                    @foreach($statusOptions as $value => $label)
                                                        @if($value !== $shipment->status)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                        
                                        <button wire:click="delete({{ $shipment->id }})" 
                                                onclick="return confirm('Are you sure you want to delete this shipment?')"
                                                class="text-red-600 hover:text-red-900">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $shipments->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8l-4 4-6-6-4 4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No shipments found</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first shipment.</p>
                    <div class="mt-6">
                        <button wire:click="openModal" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                            ➕ Add Shipment
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="shipment-modal">
            <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ $editingShipment ? '✏️ Edit Shipment' : '➕ Create New Shipment' }}
                    </h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form wire:submit.prevent="save" class="mt-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Shipment Number -->
                        <div class="md:col-span-1">
                            <label for="shipment_number" class="block text-sm font-medium text-gray-700">Shipment Number *</label>
                            <input wire:model="shipment_number" 
                                   type="text" 
                                   id="shipment_number"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('shipment_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Customer -->
                        <div class="md:col-span-1">
                            <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer *</label>
                            <select wire:model="customer_id" 
                                    id="customer_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select Customer</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->company }} ({{ $customer->name }})</option>
                                @endforeach
                            </select>
                            @error('customer_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Consignee -->
                        <div class="md:col-span-1">
                            <label for="consignee" class="block text-sm font-medium text-gray-700">Consignee *</label>
                            <input wire:model="consignee" 
                                   type="text" 
                                   id="consignee"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('consignee') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- HBL Number -->
                        <div class="md:col-span-1">
                            <label for="hbl_number" class="block text-sm font-medium text-gray-700">HBL Number</label>
                            <input wire:model="hbl_number" 
                                   type="text" 
                                   id="hbl_number"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('hbl_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- MBL Number -->
                        <div class="md:col-span-1">
                            <label for="mbl_number" class="block text-sm font-medium text-gray-700">MBL Number</label>
                            <input wire:model="mbl_number" 
                                   type="text" 
                                   id="mbl_number"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('mbl_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Invoice Number -->
                        <div class="md:col-span-1">
                            <label for="invoice_number" class="block text-sm font-medium text-gray-700">Invoice Number</label>
                            <input wire:model="invoice_number" 
                                   type="text" 
                                   id="invoice_number"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('invoice_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Vessel Code -->
                        <div class="md:col-span-1">
                            <label for="vessel_code" class="block text-sm font-medium text-gray-700">Vessel Code</label>
                            <input wire:model="vessel_code" 
                                   type="text" 
                                   id="vessel_code"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('vessel_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Vessel -->
                        <div class="md:col-span-1">
                            <label for="vessel_id" class="block text-sm font-medium text-gray-700">Vessel</label>
                            <select wire:model="vessel_id" 
                                    id="vessel_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select Vessel</option>
                                @foreach($vessels as $vessel)
                                    <option value="{{ $vessel->id }}">{{ $vessel->name }}</option>
                                @endforeach
                            </select>
                            @error('vessel_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Port of Discharge -->
                        <div class="md:col-span-1">
                            <label for="port_of_discharge" class="block text-sm font-medium text-gray-700">Port of Discharge *</label>
                            <select wire:model="port_of_discharge" 
                                    id="port_of_discharge"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select Port</option>
                                @foreach($portOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('port_of_discharge') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Status -->
                        <div class="md:col-span-1">
                            <label for="status" class="block text-sm font-medium text-gray-700">Status *</label>
                            <select wire:model="status" 
                                    id="status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @foreach($statusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Planned Delivery Date -->
                        <div class="md:col-span-1">
                            <label for="planned_delivery_date" class="block text-sm font-medium text-gray-700">Planned Delivery Date</label>
                            <input wire:model="planned_delivery_date" 
                                   type="date" 
                                   id="planned_delivery_date"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('planned_delivery_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Total Cost -->
                        <div class="md:col-span-1">
                            <label for="total_cost" class="block text-sm font-medium text-gray-700">Total Cost (THB)</label>
                            <input wire:model="total_cost" 
                                   type="number" 
                                   step="0.01"
                                   id="total_cost"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('total_cost') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Cargo Description -->
                        <div class="md:col-span-1">
                            <label for="cargo_description" class="block text-sm font-medium text-gray-700">Cargo Description</label>
                            <input wire:model="cargo_description" 
                                   type="text" 
                                   id="cargo_description"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <!-- Cargo Weight -->
                        <div class="md:col-span-1">
                            <label for="cargo_weight" class="block text-sm font-medium text-gray-700">Weight (KG)</label>
                            <input wire:model="cargo_weight" 
                                   type="number" 
                                   step="0.01"
                                   id="cargo_weight"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <!-- Cargo Volume -->
                        <div class="md:col-span-1">
                            <label for="cargo_volume" class="block text-sm font-medium text-gray-700">Volume (CBM)</label>
                            <input wire:model="cargo_volume" 
                                   type="number" 
                                   step="0.01"
                                   id="cargo_volume"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <!-- Notes -->
                        <div class="md:col-span-3">
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea wire:model="notes" 
                                      id="notes"
                                      rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                            @error('notes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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
                            {{ $editingShipment ? 'Update Shipment' : 'Create Shipment' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
