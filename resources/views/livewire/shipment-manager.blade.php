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
        <div class="space-y-4">
            <!-- Search Bar -->
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
                               placeholder="Search by HBL, MBL, Invoice, Voyage..."
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                </div>

                <!-- Reset Filters Button -->
                @if($search || $filterCustomer || $filterVessel || $filterPortTerminal || $filterShippingTeam || $filterCsReference || $statusFilter)
                <button wire:click="resetFilters"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="h-4 w-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Reset Filters
                </button>
                @endif
            </div>

            <!-- Filter Dropdowns -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
                <!-- Customer Filter -->
                <div>
                    <label for="filterCustomer" class="block text-xs font-medium text-gray-700 mb-1">Customer</label>
                    <select wire:model.live="filterCustomer"
                            id="filterCustomer"
                            class="block w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->company }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Vessel Filter -->
                <div>
                    <label for="filterVessel" class="block text-xs font-medium text-gray-700 mb-1">Vessel</label>
                    <select wire:model.live="filterVessel"
                            id="filterVessel"
                            class="block w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Vessels</option>
                        @foreach($vessels as $vessel)
                            <option value="{{ $vessel->id }}">{{ $vessel->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Port Terminal Filter (ท่าเรือ) -->
                <div>
                    <label for="filterPortTerminal" class="block text-xs font-medium text-gray-700 mb-1">ท่าเรือ</label>
                    <select wire:model.live="filterPortTerminal"
                            id="filterPortTerminal"
                            class="block w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Ports</option>
                        @foreach($portTerminalOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Shipping Team Filter (ชิ้ปปิ้ง) -->
                <div>
                    <label for="filterShippingTeam" class="block text-xs font-medium text-gray-700 mb-1">ชิ้ปปิ้ง</label>
                    <select wire:model.live="filterShippingTeam"
                            id="filterShippingTeam"
                            class="block w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Teams</option>
                        @foreach($shippingTeamOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- CS Reference Filter -->
                <div>
                    <label for="filterCsReference" class="block text-xs font-medium text-gray-700 mb-1">CS</label>
                    <select wire:model.live="filterCsReference"
                            id="filterCsReference"
                            class="block w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All CS</option>
                        @foreach($csOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="statusFilter" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select wire:model.live="statusFilter"
                            id="statusFilter"
                            class="block w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Status</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
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
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">📋</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">Client Request</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">CUSTOMERS</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">INV.</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">HBL</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">MBL</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">สถานะใบขน</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">ล่วงเวลา(OT)</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">สถานะDO</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">สถานที่รับ</th>
                                <th class="px-1 py-2 text-right text-xs font-medium text-gray-700 uppercase">KGM</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">QTY</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">UNIT</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">ETA</th>
                                <th class="px-1 py-2 text-left text-xs font-medium text-gray-700 uppercase">VESSEL NAME</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">Voyage</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">ท่าเรือ</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">ชิ้ปปิ้ง</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">CS</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">Scraped ETA</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">On Track?</th>
                                <th class="px-1 py-2 text-center text-xs font-medium text-gray-700 uppercase">LINE</th>
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
                                    <!-- Expand/Collapse Button -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        <button wire:click="toggleRowExpansion({{ $shipment->id }})"
                                                class="text-blue-600 hover:text-blue-900 transform transition-transform duration-200
                                                {{ $this->isRowExpanded($shipment->id) ? 'rotate-90' : '' }}"
                                                title="Toggle ETA history">
                                            ▶️
                                        </button>
                                    </td>

                                    <!-- Client Requested Delivery Date -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        @if($shipment->client_requested_delivery_date)
                                            {{ $shipment->client_requested_delivery_date->format('d-M H:i') }}
                                        @else
                                            -
                                        @endif
                                    </td>

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
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            @if($shipment->customs_clearance_status === 'received')
                                                bg-green-100 text-green-800
                                            @elseif($shipment->customs_clearance_status === 'processing')
                                                bg-yellow-100 text-yellow-800
                                            @elseif($shipment->customs_clearance_status === 'pending')
                                                bg-red-100 text-red-800
                                            @else
                                                bg-gray-100 text-gray-600
                                            @endif">
                                            {{ $this->customsClearanceOptions[$shipment->customs_clearance_status] ?? '-' }}
                                        </span>
                                    </td>

                                    <!-- OT Status -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        {{ $this->overtimeOptions[$shipment->overtime_status] ?? '-' }}
                                    </td>

                                    <!-- DO Status -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            @if($shipment->do_status === 'received')
                                                bg-green-100 text-green-800
                                            @elseif($shipment->do_status === 'processing')
                                                bg-blue-100 text-blue-800
                                            @elseif($shipment->do_status === 'pending')
                                                bg-orange-100 text-orange-800
                                            @else
                                                bg-gray-100 text-gray-600
                                            @endif">
                                            {{ $this->doStatusOptions[$shipment->do_status] ?? '-' }}
                                        </span>
                                    </td>

                                    <!-- Pickup Location (สถานที่รับ) -->
                                    <td class="px-1 py-1 text-xs">{{ $shipment->pickup_location ?? '-' }}</td>

                                    <!-- KGM -->
                                    <td class="px-1 py-1 text-xs text-right">
                                        @if($shipment->weight_kgm)
                                            {{ number_format($shipment->weight_kgm, 2) }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <!-- Quantity -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        @if($shipment->quantity_number)
                                            {{ $shipment->quantity_number }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <!-- Unit -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        @if($shipment->quantity_unit)
                                            {{ $shipment->quantity_unit }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <!-- ETA -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        @if($shipment->actual_delivery_date)
                                            {{ $shipment->actual_delivery_date->format('d-M H:i') }}
                                        @elseif($shipment->planned_delivery_date)
                                            {{ $shipment->planned_delivery_date->format('d-M H:i') }}
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

                                    <!-- Scraped ETA -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        @if($shipment->bot_received_eta_date)
                                            <div class="text-green-600" title="Scraped ETA from terminal">
                                                📅 {{ $shipment->bot_received_eta_date->format('d/m H:i') }}
                                            </div>
                                        @else
                                            <span class="text-gray-500">-</span>
                                        @endif
                                    </td>

                                    <!-- Tracking Status -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        @if($shipment->tracking_status)
                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                @if($shipment->tracking_status === 'on_track')
                                                    bg-green-100 text-green-800
                                                @else
                                                    bg-red-100 text-red-800
                                                @endif">
                                                @if($shipment->tracking_status === 'on_track')
                                                    ✅ On Track
                                                @else
                                                    ⚠️ Delay
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-gray-500">-</span>
                                        @endif
                                    </td>

                                    <!-- LINE Status -->
                                    <td class="px-1 py-1 text-xs text-center">
                                        @php
                                            $lineClients = $shipment->shipmentClients()->whereNotNull('line_user_id')->count();
                                        @endphp
                                        @if($lineClients > 0)
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800" title="{{ $lineClients }} client(s) connected">
                                                ✅ {{ $lineClients }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                                -
                                            </span>
                                        @endif
                                    </td>

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
                                        <div class="flex flex-col space-y-1">
                                            <!-- Regular Actions -->
                                            <div class="flex space-x-1">
                                                <button wire:click="edit({{ $shipment->id }})"
                                                        class="text-blue-600 hover:text-blue-900 text-xs">
                                                    ✏️
                                                </button>
                                                <button wire:click="delete({{ $shipment->id }})"
                                                        onclick="return confirm('Are you sure you want to delete this shipment?')"
                                                        class="text-red-600 hover:text-red-900 text-xs">
                                                    🗑️
                                                </button>
                                            </div>

                                            <!-- LINE Actions (Available to all users) -->
                                            @if(auth()->check())
                                                <div class="flex space-x-1">
                                                    <button onclick="openClientLinkModal({{ $shipment->id }}, '{{ $shipment->invoice_number }}')"
                                                            class="text-green-600 hover:text-green-900 text-xs"
                                                            title="Generate client LINE link">
                                                        📱
                                                    </button>
                                                    <button onclick="sendTestNotification({{ $shipment->id }})"
                                                            class="text-purple-600 hover:text-purple-900 text-xs"
                                                            title="Send test ETA notification">
                                                        📨
                                                    </button>
                                                </div>
                                                <!-- ETA Check Button -->
                                                <div class="flex space-x-1 mt-1">
                                                    <button onclick="checkShipmentETA(event, {{ $shipment->id }})"
                                                            class="text-blue-600 hover:text-blue-900 text-xs"
                                                            title="Check vessel ETA using bot automation">
                                                        🤖
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                <!-- Expandable ETA History Row -->
                                @if($this->isRowExpanded($shipment->id))
                                    <tr class="bg-gray-50">
                                        <td colspan="24" class="px-4 py-3">
                                            <div class="bg-white rounded-lg border p-4">
                                                <h4 class="text-sm font-semibold text-gray-900 mb-3">📊 ETA Check History</h4>

                                                @php
                                                    $etaHistory = $this->getEtaHistory($shipment->id);
                                                @endphp

                                                @if($etaHistory && $etaHistory->count() > 0)
                                                    <div class="overflow-x-auto">
                                                        <table class="min-w-full text-xs">
                                                            <thead class="bg-gray-100">
                                                                <tr>
                                                                    <th class="px-2 py-1 text-left font-medium text-gray-700">Checked At</th>
                                                                    <th class="px-2 py-1 text-center font-medium text-gray-700">Terminal</th>
                                                                    <th class="px-2 py-1 text-center font-medium text-gray-700">Vessel</th>
                                                                    <th class="px-2 py-1 text-center font-medium text-gray-700">Voyage</th>
                                                                    <th class="px-2 py-1 text-center font-medium text-gray-700">Scraped ETA</th>
                                                                    <th class="px-2 py-1 text-center font-medium text-gray-700">Shipment ETA</th>
                                                                    <th class="px-2 py-1 text-center font-medium text-gray-700">Status</th>
                                                                    <th class="px-2 py-1 text-center font-medium text-gray-700">Found</th>
                                                                    <th class="px-2 py-1 text-center font-medium text-gray-700">By</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-200">
                                                                @foreach($etaHistory as $log)
                                                                    <tr class="hover:bg-gray-50">
                                                                        <td class="px-2 py-1 text-xs">{{ $log['checked_at'] }}</td>
                                                                        <td class="px-2 py-1 text-xs text-center">{{ $log['terminal'] }}</td>
                                                                        <td class="px-2 py-1 text-xs text-center">{{ $log['vessel_name'] }}</td>
                                                                        <td class="px-2 py-1 text-xs text-center">{{ $log['voyage_code'] }}</td>
                                                                        <td class="px-2 py-1 text-xs text-center">
                                                                            @if($log['scraped_eta'])
                                                                                <span class="text-blue-600 font-medium">{{ $log['scraped_eta'] }}</span>
                                                                            @else
                                                                                <span class="text-gray-400">-</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-2 py-1 text-xs text-center">
                                                                            @if($log['shipment_eta'])
                                                                                <span class="text-gray-600">{{ $log['shipment_eta'] }}</span>
                                                                            @else
                                                                                <span class="text-gray-400">-</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-2 py-1 text-xs text-center">
                                                                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                                                                @if($log['status_color'] === 'green')
                                                                                    bg-green-100 text-green-800
                                                                                @elseif($log['status_color'] === 'red')
                                                                                    bg-red-100 text-red-800
                                                                                @else
                                                                                    bg-gray-100 text-gray-600
                                                                                @endif">
                                                                                {{ $log['status_text'] }}
                                                                            </span>
                                                                        </td>
                                                                        <td class="px-2 py-1 text-xs text-center">
                                                                            @if($log['vessel_found'])
                                                                                <span class="text-green-600">✅ Vessel</span>
                                                                            @else
                                                                                <span class="text-red-600">❌ Vessel</span>
                                                                            @endif
                                                                            @if($log['voyage_found'])
                                                                                <span class="text-green-600">✅ Voyage</span>
                                                                            @elseif($log['vessel_found'])
                                                                                <span class="text-yellow-600">⚠️ Voyage</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-2 py-1 text-xs text-center">{{ $log['initiated_by'] }}</td>
                                                                    </tr>
                                                                    @if($log['error_message'])
                                                                        <tr class="bg-red-50">
                                                                            <td colspan="9" class="px-2 py-1 text-xs text-red-600">
                                                                                <strong>Error:</strong> {{ $log['error_message'] }}
                                                                            </td>
                                                                        </tr>
                                                                    @endif
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="text-center py-6 text-gray-500">
                                                        <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                        </svg>
                                                        <p class="text-sm">No ETA check history available</p>
                                                        <p class="text-xs text-gray-400">Use the 🤖 button to check vessel ETA</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
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
                        <!-- Row 1: Client Request & Basic Info -->
                        <!-- Client Requested Delivery Date -->
                        <div>
                            <label for="client_requested_delivery_date" class="block text-sm font-medium text-gray-700">Client Request Date</label>
                            <input wire:model="client_requested_delivery_date"
                                   type="datetime-local"
                                   id="client_requested_delivery_date"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('client_requested_delivery_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

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
                            <label for="pickup_location" class="block text-sm font-medium text-gray-700">สถานที่รับ</label>
                            <input wire:model="pickup_location"
                                   type="text"
                                   id="pickup_location"
                                   placeholder="เช่น โกดัง A, ลานจอดรถ B, ศูนย์กระจายสินค้า"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('pickup_location') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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

                        <!-- Quantity Number -->
                        <div>
                            <label for="quantity_number" class="block text-sm font-medium text-gray-700">Quantity</label>
                            <input wire:model="quantity_number"
                                   type="number"
                                   step="0.01"
                                   id="quantity_number"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('quantity_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Quantity Unit -->
                        <div>
                            <label for="quantity_unit" class="block text-sm font-medium text-gray-700">Unit Type</label>
                            <select wire:model="quantity_unit"
                                    id="quantity_unit"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Select Unit</option>
                                @foreach($quantityUnitOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('quantity_unit') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Planned Delivery Date (ETA) -->
                        <div>
                            <label for="planned_delivery_date" class="block text-sm font-medium text-gray-700">ETA</label>
                            <input wire:model="planned_delivery_date"
                                   type="datetime-local"
                                   id="planned_delivery_date"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('planned_delivery_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <!-- Vessel -->
                        <div class="relative">
                            <label for="vessel_name" class="block text-sm font-medium text-gray-700">VESSEL NAME</label>
                            <input wire:model="vessel_name"
                                   wire:keyup.debounce.300ms="searchVessels"
                                   type="text"
                                   id="vessel_name"
                                   placeholder="Type to search or enter new vessel name"
                                   autocomplete="off"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">

                            <!-- Autocomplete suggestions dropdown -->
                            @if(!empty($vessel_suggestions))
                                <div class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                                    @foreach($vessel_suggestions as $suggestion)
                                        <div wire:click="selectVessel('{{ $suggestion }}')"
                                             class="w-full text-left px-3 py-2 hover:bg-blue-50 hover:text-blue-700 transition cursor-pointer border-b border-gray-100 last:border-0">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                </svg>
                                                {{ $suggestion }}
                                            </div>
                                        </div>
                                    @endforeach
                                    @if($vessel_name && !empty($vessel_suggestions) && !$vessel_exists && !in_array($vessel_name, $vessel_suggestions))
                                        <div class="px-3 py-2 text-sm text-green-600 bg-green-50 border-t">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                </svg>
                                                Will create new vessel: "{{ $vessel_name }}"
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @elseif($vessel_name && strlen($vessel_name) >= 2 && !$vessel_exists)
                                <div class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg">
                                    <div class="px-3 py-2 text-sm text-green-600 bg-green-50">
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                            </svg>
                                            Will create new vessel: "{{ $vessel_name }}"
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @error('vessel_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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

                        <!-- Row 5: Status Management -->
                        <!-- Shipment Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Shipment Status *</label>
                            <select wire:model="status"
                                    id="status"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="in-progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                            @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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

    <!-- Client LINE Link Modal -->
    <div id="clientLinkModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">📱 Generate Client LINE Link</h3>

                <form onsubmit="generateClientLink(event)">
                    <div class="space-y-4">
                        <input type="hidden" id="clientShipmentId" value="">

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Shipment</label>
                            <input type="text" id="clientInvoiceNumber" readonly class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-50 sm:text-sm">
                        </div>

                        <div>
                            <label for="clientName" class="block text-sm font-medium text-gray-700">Client Name *</label>
                            <input type="text" id="clientName" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="clientEmail" class="block text-sm font-medium text-gray-700">Client Email</label>
                            <input type="email" id="clientEmail" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="clientPhone" class="block text-sm font-medium text-gray-700">Client Phone</label>
                            <input type="text" id="clientPhone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-4 space-x-3">
                        <button type="button" onclick="closeClientLinkModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Generate Link
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">🎉 Link Generated Successfully!</h3>

                <div class="bg-blue-50 p-4 rounded-lg mb-4">
                    <p class="text-sm text-blue-700 mb-2">Share this link with your client:</p>
                    <div class="bg-white p-2 rounded border break-all text-xs">
                        <span id="generatedLink"></span>
                    </div>
                </div>

                <div class="flex justify-center space-x-3">
                    <button onclick="copyLink()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        📋 Copy Link
                    </button>
                    <button onclick="closeSuccessModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openClientLinkModal(shipmentId, invoiceNumber) {
        document.getElementById('clientShipmentId').value = shipmentId;
        document.getElementById('clientInvoiceNumber').value = invoiceNumber;
        document.getElementById('clientLinkModal').classList.remove('hidden');
    }

    function closeClientLinkModal() {
        document.getElementById('clientLinkModal').classList.add('hidden');
        // Reset form
        document.getElementById('clientName').value = '';
        document.getElementById('clientEmail').value = '';
        document.getElementById('clientPhone').value = '';
    }

    function closeSuccessModal() {
        document.getElementById('successModal').classList.add('hidden');
    }

    async function generateClientLink(event) {
        event.preventDefault();

        const formData = {
            shipment_id: document.getElementById('clientShipmentId').value,
            client_name: document.getElementById('clientName').value,
            client_email: document.getElementById('clientEmail').value,
            client_phone: document.getElementById('clientPhone').value,
        };

        try {
            const response = await fetch('/shipments/generate-client-link', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                document.getElementById('generatedLink').textContent = result.login_url;
                closeClientLinkModal();
                document.getElementById('successModal').classList.remove('hidden');
            } else {
                alert('Error: ' + (result.error || 'Failed to generate link'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    function copyLink() {
        const linkText = document.getElementById('generatedLink').textContent;
        navigator.clipboard.writeText(linkText).then(() => {
            alert('Link copied to clipboard!');
        });
    }

    async function sendTestNotification(shipmentId) {
        if (!confirm('Send test ETA notification to all connected clients for this shipment?')) {
            return;
        }

        try {
            const response = await fetch('/shipments/send-test-notification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ shipment_id: shipmentId })
            });

            const result = await response.json();

            if (result.success) {
                alert('✅ ' + result.message);
            } else {
                alert('❌ ' + (result.message || 'Failed to send notifications'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    async function checkShipmentETA(event, shipmentId) {
        if (!confirm('Send bot to check vessel ETA for this shipment? This will update the tracking status.')) {
            return;
        }

        // Find the button and show loading state
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '⏳';
        button.disabled = true;

        try {
            const response = await fetch('/shipments/check-eta', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ shipment_id: shipmentId })
            });

            const result = await response.json();

            if (result.success) {
                let message = '✅ ETA check completed!\n\n';
                message += `Terminal: ${result.terminal}\n`;
                message += `Vessel Found: ${result.vessel_found ? 'Yes' : 'No'}\n`;
                message += `Voyage Found: ${result.voyage_found ? 'Yes' : 'No'}\n`;
                message += `Tracking Status: ${result.tracking_status === 'on_track' ? 'On Track' : 'Delay'}\n`;

                if (result.eta) {
                    message += `ETA: ${result.eta}\n`;
                }

                message += `\n${result.message}`;

                alert(message);

                // Refresh the page after a short delay to show updated tracking status
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                alert('❌ ETA check failed: ' + (result.error || 'Unknown error'));

                // Still refresh to show updated tracking status
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        } catch (error) {
            alert('🔧 Error checking ETA: ' + error.message);
        } finally {
            button.textContent = originalText;
            button.disabled = false;
        }
    }
</script>
