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
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">CUSTOMERS</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">INV.</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">HBL</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">MBL</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">สถานะใบขน</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">ล่วงเวลา(OT)</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">สถานะDO</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">สถานที่รับ</th>
                                <th class="px-1 py-2 text-right text-xs font-medium text-gray-700 uppercase">KGM</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">FCL</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">ETA</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">VESSEL NAME</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">Voyage</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">ท่าเรือ</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">ชิ้ปปิ้ง</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">CS</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">STATUS</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($shipments as $shipment)
                                @php
                                    $rowClass = '';
                                    if($shipment->customs_clearance_status === 'received' && $shipment->overtime_status === 'none') {
                                        $rowClass = 'bg-red-50';
                                    } elseif($shipment->customs_clearance_status === 'received' && $shipment->overtime_status !== 'none') {
                                        $rowClass = 'bg-yellow-50';
                                    }
                                @endphp
                                <tr class="hover:bg-gray-100 {{ $rowClass }}">
                                    <!-- Customer -->
                                    <td class="px-1 py-1 text-xs">{{ $shipment->customer->company ?? 'N/A' }}</td>

                                    <!-- Invoice -->
                                    <td class="px-1 py-1 text-xs">{{ $shipment->invoice_number ?? '-' }}</td>

                                    <!-- HBL -->
                                    <td class="px-1 py-1 text-xs">{{ $shipment->hbl_number ?? '-' }}</td>

                                    <!-- MBL -->
                                    <td class="px-1 py-1 text-xs">{{ $shipment->mbl_number ?? '-' }}</td>

                                    <!-- Customs Status -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        <span class="px-1 py-0.5 rounded text-xs font-medium
                                            @if($shipment->customs_clearance_status === 'received')
                                                bg-yellow-200 text-yellow-800
                                            @else
                                                bg-gray-100 text-gray-700
                                            @endif">
                                            {{ $this->customsClearanceOptions[$shipment->customs_clearance_status] ?? '-' }}
                                        </span>
                                    </td>

                                    <!-- OT Status -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        <span class="px-1 py-0.5 rounded text-xs font-medium
                                            @if($shipment->overtime_status === 'none')
                                                bg-red-200 text-red-800
                                            @else
                                                bg-yellow-200 text-yellow-800
                                            @endif">
                                            {{ $this->overtimeOptions[$shipment->overtime_status] ?? '-' }}
                                        </span>
                                    </td>

                                    <!-- DO Status -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        <span class="px-1 py-0.5 rounded text-xs font-medium
                                            @if($shipment->do_status === 'received')
                                                bg-green-200 text-green-800
                                            @else
                                                bg-gray-100 text-gray-700
                                            @endif">
                                            {{ $this->doStatusOptions[$shipment->do_status] ?? '-' }}
                                        </span>
                                    </td>

                                    <!-- Pickup Location (สถานที่รับ) -->
                                    <td class="px-1 py-1 text-xs">{{ $shipment->shipping_line ?? '-' }}</td>

                                    <!-- KGM -->
                                    <td class="px-1 py-1 text-xs text-right">
                                        @if($shipment->weight_kgm)
                                            {{ number_format($shipment->weight_kgm, 2) }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <!-- FCL -->
                                    <td class="px-1 py-1 text-xs text-center">{{ $shipment->fcl_type ?? '-' }}</td>

                                    <!-- ETA -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        @if($shipment->actual_delivery_date)
                                            {{ $shipment->actual_delivery_date->format('d-M') }}
                                        @elseif($shipment->planned_delivery_date)
                                            {{ $shipment->planned_delivery_date->format('d-M') }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <!-- Vessel Name -->
                                    <td class="px-1 py-1 text-xs">{{ $shipment->vessel->name ?? $shipment->vessel_code ?? '-' }}</td>

                                    <!-- Voyage -->
                                    <td class="px-1 py-1 text-xs text-center">{{ $shipment->voyage ?? '-' }}</td>

                                    <!-- Port Terminal -->
                                    <td class="px-1 py-1 text-xs text-center">{{ $shipment->port_terminal ?? '-' }}</td>

                                    <!-- Shipping Team -->
                                    <td class="px-1 py-1 text-xs text-center">{{ $shipment->shipping_team ?? '-' }}</td>

                                    <!-- CS Reference -->
                                    <td class="px-1 py-1 text-xs text-center">{{ $shipment->cs_reference ?? '-' }}</td>

                                    <!-- Status -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            @if($shipment->status === 'completed')
                                                bg-green-100 text-green-800
                                            @else
                                                bg-blue-100 text-blue-800
                                            @endif">
                                            {{ ucfirst(str_replace('-', ' ', $shipment->status)) }}
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        <button wire:click="edit({{ $shipment->id }})"
                                                class="text-blue-600 hover:text-blue-900 text-xs">
                                            ✏️
                                        </button>
                                        <button wire:click="delete({{ $shipment->id }})"
                                                onclick="return confirm('Are you sure you want to delete this shipment?')"
                                                class="text-red-600 hover:text-red-900 text-xs ml-2">
                                            🗑️
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
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <!-- Row 1: Basic Info -->
                        <!-- Customer -->
                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer *</label>
                            <select wire:model="customer_id"
                                    id="customer_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select Customer</option>
                                @foreach($this->customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->company }}</option>
                                @endforeach
                            </select>
                            @error('customer_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Invoice Number -->
                        <div>
                            <label for="invoice_number" class="block text-sm font-medium text-gray-700">INV.</label>
                            <input wire:model="invoice_number"
                                   type="text"
                                   id="invoice_number"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('invoice_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- HBL Number -->
                        <div>
                            <label for="hbl_number" class="block text-sm font-medium text-gray-700">HBL</label>
                            <input wire:model="hbl_number"
                                   type="text"
                                   id="hbl_number"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('hbl_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- MBL Number -->
                        <div>
                            <label for="mbl_number" class="block text-sm font-medium text-gray-700">MBL</label>
                            <input wire:model="mbl_number"
                                   type="text"
                                   id="mbl_number"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('mbl_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Row 2: Status Fields -->
                        <!-- Customs Clearance Status -->
                        <div>
                            <label for="customs_clearance_status" class="block text-sm font-medium text-gray-700">สถานะใบขน *</label>
                            <select wire:model="customs_clearance_status"
                                    id="customs_clearance_status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @foreach($this->customsClearanceOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('customs_clearance_status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Overtime Status -->
                        <div>
                            <label for="overtime_status" class="block text-sm font-medium text-gray-700">ล่วงเวลา (OT) *</label>
                            <select wire:model="overtime_status"
                                    id="overtime_status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @foreach($this->overtimeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('overtime_status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- DO Status -->
                        <div>
                            <label for="do_status" class="block text-sm font-medium text-gray-700">สถานะ DO *</label>
                            <select wire:model="do_status"
                                    id="do_status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                @foreach($this->doStatusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('do_status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Pickup Location (สถานที่รับ) -->
                        <div>
                            <label for="shipping_line" class="block text-sm font-medium text-gray-700">สถานที่รับ</label>
                            <input wire:model="shipping_line"
                                   type="text"
                                   id="shipping_line"
                                   placeholder="เช่น โกดัง A, ลานจอดรถ B, ศูนย์กระจายสินค้า"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <!-- Row 3: Weight and Container Info -->
                        <!-- Weight KGM -->
                        <div>
                            <label for="weight_kgm" class="block text-sm font-medium text-gray-700">KGM</label>
                            <input wire:model="weight_kgm"
                                   type="number"
                                   step="0.01"
                                   id="weight_kgm"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('weight_kgm') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- FCL Type -->
                        <div>
                            <label for="fcl_type" class="block text-sm font-medium text-gray-700">FCL</label>
                            <input wire:model="fcl_type"
                                   type="text"
                                   id="fcl_type"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('fcl_type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Planned Delivery Date (ETA) -->
                        <div>
                            <label for="planned_delivery_date" class="block text-sm font-medium text-gray-700">ETA</label>
                            <input wire:model="planned_delivery_date"
                                   type="date"
                                   id="planned_delivery_date"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('planned_delivery_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Vessel -->
                        <div>
                            <label for="vessel_id" class="block text-sm font-medium text-gray-700">VESSEL NAME</label>
                            <select wire:model="vessel_id"
                                    id="vessel_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select Vessel</option>
                                @foreach($this->vessels as $vessel)
                                    <option value="{{ $vessel->id }}">{{ $vessel->name }}</option>
                                @endforeach
                            </select>
                            @error('vessel_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Row 4: Additional Fields -->
                        <!-- Voyage -->
                        <div>
                            <label for="voyage" class="block text-sm font-medium text-gray-700">Voyage</label>
                            <input wire:model="voyage"
                                   type="text"
                                   id="voyage"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('voyage') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Port Terminal -->
                        <div>
                            <label for="port_terminal" class="block text-sm font-medium text-gray-700">ท่าเรือ</label>
                            <select wire:model="port_terminal"
                                    id="port_terminal"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select Terminal</option>
                                @foreach($this->portTerminalOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('port_terminal') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Shipping Team -->
                        <div>
                            <label for="shipping_team" class="block text-sm font-medium text-gray-700">ชิ้ปปิ้ง</label>
                            <select wire:model="shipping_team"
                                    id="shipping_team"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select Team Member</option>
                                @foreach($this->shippingTeamOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('shipping_team') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- CS Reference -->
                        <div>
                            <label for="cs_reference" class="block text-sm font-medium text-gray-700">CS</label>
                            <select wire:model="cs_reference"
                                    id="cs_reference"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select CS Member</option>
                                @foreach($this->csOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('cs_reference') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>



                        <!-- Notes (full width) -->
                        <div class="md:col-span-4">
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
