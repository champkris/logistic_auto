<div class="p-6 space-y-6">
    @push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #transport-map { height: 500px; width: 100%; border-radius: 0.5rem; }
    </style>
    @endpush

    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Transport Assignment</h1>
            <p class="mt-1 text-sm text-gray-600">View daily container transport jobs and track vehicle GPS locations</p>
        </div>
        @if($selectedCustomer)
        <button wire:click="refreshSheetData"
                class="mt-3 sm:mt-0 inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
            <svg class="h-4 w-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Refresh Data
        </button>
        @endif
    </div>

    <!-- Section 0: Shipment List -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-700">Shipment Overview</h2>
                <div class="flex items-center gap-3">
                    <!-- Summary stats -->
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ $shipments->total() }} total
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ $assignedCount }} assigned
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                        {{ $unassignedCount }} unassigned
                    </span>
                </div>
            </div>
            <div class="mt-3 flex flex-col sm:flex-row sm:items-center gap-3">
                <!-- Search -->
                <div class="relative flex-1 max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input wire:model.live.debounce.300ms="shipmentSearch"
                           type="text"
                           placeholder="Search MBL, HBL, customer, vessel..."
                           class="block w-full pl-9 pr-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <!-- Status filter -->
                <select wire:model.live="shipmentStatusFilter"
                        class="border border-gray-300 rounded-md text-sm py-1.5 px-3 focus:ring-blue-500 focus:border-blue-500">
                    <option value="in-progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="all">All</option>
                </select>
            </div>
        </div>

        @if($shipments->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            <button wire:click="sortBy('client_requested_delivery_date')" class="flex items-center hover:text-blue-600">
                                Client Date
                                @if($sortField === 'client_requested_delivery_date')
                                    @if($sortDirection === 'asc')
                                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                                    @else
                                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/></svg>
                                    @endif
                                @endif
                            </button>
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            <button wire:click="sortBy('customer_name')" class="flex items-center hover:text-blue-600">
                                Customer
                                @if($sortField === 'customer_name')
                                    @if($sortDirection === 'asc')
                                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                                    @else
                                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/></svg>
                                    @endif
                                @endif
                            </button>
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">MBL</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">HBL</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">QTY</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">ETA</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            <button wire:click="sortBy('vessel_name')" class="flex items-center hover:text-blue-600">
                                Vessel
                                @if($sortField === 'vessel_name')
                                    @if($sortDirection === 'asc')
                                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                                    @else
                                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/></svg>
                                    @endif
                                @endif
                            </button>
                        </th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Voyage</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Port</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Containers</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                            <button wire:click="sortBy('status')" class="flex items-center justify-center w-full hover:text-blue-600">
                                Status
                                @if($sortField === 'status')
                                    @if($sortDirection === 'asc')
                                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                                    @else
                                        <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20"><path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/></svg>
                                    @endif
                                @endif
                            </button>
                        </th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($shipments as $index => $shipment)
                    @php
                        $rowClass = $shipment->has_transport ? 'bg-green-50' : '';
                    @endphp
                    <tr class="{{ $rowClass }} hover:bg-gray-100">
                        <td class="px-2 py-1.5 text-center text-gray-500">{{ $shipments->firstItem() + $index }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap text-gray-700">
                            @if($shipment->client_requested_delivery_date)
                                {{ $shipment->client_requested_delivery_date->format('d-M-y') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-gray-700">{{ $shipment->customer->company ?? $shipment->customer->name ?? 'N/A' }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap text-gray-700 font-medium">{{ $shipment->mbl_number ?? '-' }}</td>
                        <td class="px-2 py-1.5 whitespace-nowrap text-gray-700">{{ $shipment->hbl_number ?? '-' }}</td>
                        <td class="px-2 py-1.5 text-center text-gray-700">
                            @if($shipment->quantity_number)
                                {{ (int)$shipment->quantity_number }} {{ $shipment->quantity_unit }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-2 py-1.5 whitespace-nowrap text-gray-700">
                            @if($shipment->vessel && $shipment->vessel->eta)
                                {{ \Carbon\Carbon::parse($shipment->vessel->eta)->format('d-M') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-gray-700">{{ $shipment->vessel->name ?? '-' }}</td>
                        <td class="px-2 py-1.5 text-gray-700">{{ $shipment->voyage ?? '-' }}</td>
                        <td class="px-2 py-1.5 text-gray-700">{{ $shipment->port_terminal ?? '-' }}</td>
                        <td class="px-2 py-1.5 text-center">
                            @if($shipment->has_transport)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $shipment->transport_container_count }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                    None
                                </span>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $shipment->status === 'in-progress' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                {{ $shipment->status }}
                            </span>
                        </td>
                        <td class="px-2 py-1.5 text-center">
                            @if($shipment->mbl_number)
                            <button wire:click="viewShipmentBl({{ $shipment->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="viewShipmentBl({{ $shipment->id }})"
                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100 disabled:opacity-50">
                                <svg wire:loading wire:target="viewShipmentBl({{ $shipment->id }})" class="animate-spin h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="viewShipmentBl({{ $shipment->id }})">View BL</span>
                                <span wire:loading wire:target="viewShipmentBl({{ $shipment->id }})">Loading...</span>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $shipments->links() }}
        </div>
        @else
        <div class="px-4 py-8 text-center text-sm text-gray-500">
            No shipments found.
        </div>
        @endif
    </div>

    <!-- Section 1: Container Search (BL / Customer) -->
    <div class="bg-white rounded-lg shadow p-4">
        <!-- Search mode tabs -->
        <div class="flex gap-2 mb-3">
            <button wire:click="switchSearchMode('customer')"
                    class="px-3 py-1.5 text-sm font-medium rounded-md {{ $searchMode === 'customer' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                Customer
            </button>
            <button wire:click="switchSearchMode('bl')"
                    class="px-3 py-1.5 text-sm font-medium rounded-md {{ $searchMode === 'bl' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                BL
            </button>
        </div>

        @if($searchMode === 'customer')
        <!-- Customer search -->
        <div class="relative max-w-md" x-data="{ open: false }" @click.away="open = false">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input wire:model.live.debounce.300ms="customerSearch"
                       @focus="open = true"
                       @input="open = true"
                       type="text"
                       placeholder="Type customer name to search..."
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            @if(count($customerSuggestions) > 0)
            <div x-show="open" class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-y-auto">
                @foreach($customerSuggestions as $suggestion)
                <button wire:click="selectCustomer('{{ addslashes($suggestion) }}')"
                        @click="open = false"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 border-b border-gray-100 last:border-0">
                    {{ $suggestion }}
                </button>
                @endforeach
            </div>
            @endif
        </div>

        @if($selectedCustomer)
        <div class="mt-3 flex items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                {{ $selectedCustomer }}
            </span>
            <button wire:click="$set('selectedCustomer', '')" class="text-gray-400 hover:text-red-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        @endif

        @else
        <!-- BL search -->
        <form wire:submit="searchByBl" class="flex gap-2 max-w-md">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input wire:model="blSearch"
                       type="text"
                       placeholder="Enter BL number..."
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="searchByBl"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50">
                <svg wire:loading wire:target="searchByBl" class="animate-spin h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span wire:loading.remove wire:target="searchByBl">Search</span>
                <span wire:loading wire:target="searchByBl">Searching...</span>
            </button>
        </form>
        @endif
    </div>

    <!-- Section 2: Container Table with Date Navigator -->
    @if($selectedCustomer || ($searchMode === 'bl' && count($containerRows) > 0) || ($searchMode === 'bl' && $sheetError))
    <div class="bg-white rounded-lg shadow">
        <!-- Top bar -->
        <div class="px-4 py-3 border-b border-gray-200">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <!-- Date nav (customer mode only) -->
                @if($searchMode === 'customer')
                <div class="flex items-center space-x-3">
                    <button wire:click="previousDate"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm rounded-md hover:bg-gray-50">
                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Prev
                    </button>

                    <span class="text-sm font-semibold text-gray-800 bg-gray-100 px-3 py-1.5 rounded-md min-w-[120px] text-center">
                        {{ $selectedDate }}
                    </span>

                    <button wire:click="nextDate"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm rounded-md hover:bg-gray-50">
                        Next
                        <svg class="h-4 w-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>

                    <button wire:click="goToDate('{{ \Carbon\Carbon::today()->format('d/m/Y') }}')"
                            class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-sm rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100">
                        Today
                    </button>
                </div>
                @else
                <div class="text-sm text-gray-600">
                    Results for BL: <span class="font-semibold">{{ $blSearch }}</span>
                </div>
                @endif

                <div class="flex items-center gap-3">
                    <!-- Row count badge -->
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ count($containerRows) > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ count($containerRows) }} record{{ count($containerRows) !== 1 ? 's' : '' }}
                    </span>
                </div>
            </div>

            <!-- Available dates quick jump (customer mode only) -->
            @if($searchMode === 'customer' && count($availableDates) > 0)
            <div class="mt-2 flex flex-wrap gap-1">
                <span class="text-xs text-gray-500 mr-1 py-1">Dates with data:</span>
                @foreach(array_slice($availableDates, -10) as $date)
                <button wire:click="goToDate('{{ $date }}')"
                        class="px-2 py-0.5 text-xs rounded {{ $date === $selectedDate ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    {{ $date }}
                </button>
                @endforeach
                @if(count($availableDates) > 10)
                <span class="text-xs text-gray-400 py-0.5">+{{ count($availableDates) - 10 }} more</span>
                @endif
            </div>
            @endif
        </div>

        <!-- Matching Shipments (BL mode) -->
        @if($searchMode === 'bl' && count($matchingShipments) > 0)
        <div class="px-4 py-3 border-b border-gray-200 bg-blue-50/50">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Platform Shipments matching this BL</h3>

            @if(session()->has('assignSuccess'))
            <div class="mb-3 p-2 bg-green-100 border border-green-300 rounded text-sm text-green-800">
                {{ session('assignSuccess') }}
            </div>
            @endif

            @php
                // Count containers that have drivers AND are NOT already assigned
                $newAssignableCount = collect($containerRows)->filter(function($row) use ($containerAssignments) {
                    $containerNo = trim($row['CONTAINER NO'] ?? '');
                    if (!empty($containerNo) && isset($containerAssignments[$containerNo])) return false;
                    return !empty(trim($row['1ST DRIVER NAME'] ?? '')) || !empty(trim($row['1ST LICENSE'] ?? ''))
                        || !empty(trim($row['2nd DRIVER NAME'] ?? '')) || !empty(trim($row['2nd LICENSE'] ?? ''))
                        || !empty(trim($row['3rd DRIVER NAME'] ?? '')) || !empty(trim($row['3 rd LICENSE'] ?? ''));
                })->count();
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($matchingShipments as $shipment)
                @php
                    $assignedToThis = collect($containerAssignments)->filter(fn($info) => $info['shipmentId'] === $shipment['id'])->count();
                    $assignedContainersList = $shipment['assigned_containers'] ?? [];
                @endphp
                <div class="bg-white rounded-lg border {{ $assignedToThis > 0 ? 'border-green-300' : 'border-gray-200' }} p-3">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-gray-900">#{{ $shipment['id'] }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $shipment['status'] === 'in-progress' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $shipment['status'] }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 mt-0.5">{{ $shipment['customer_name'] }}</p>
                            <div class="mt-1 text-xs text-gray-500 space-y-0.5">
                                <p>MBL: {{ $shipment['mbl_number'] }}</p>
                                @if($shipment['hbl_number'] && $shipment['hbl_number'] !== $shipment['mbl_number'])
                                <p>HBL: {{ $shipment['hbl_number'] }}</p>
                                @endif
                                @if($shipment['quantity'])
                                <p>Qty: {{ $shipment['quantity'] }} x {{ $shipment['quantity_unit'] }}</p>
                                @endif
                                @if($shipment['port_terminal'])
                                <p>Terminal: {{ $shipment['port_terminal'] }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="ml-3 flex-shrink-0">
                            @if($newAssignableCount > 0)
                            <button wire:click="assignContainersToShipment({{ $shipment['id'] }})"
                                    wire:confirm="Assign {{ $newAssignableCount }} new container(s) with driver details to shipment #{{ $shipment['id'] }}?"
                                    class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700">
                                <svg class="h-3.5 w-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                </svg>
                                Assign ({{ $newAssignableCount }})
                            </button>
                            @else
                            <span class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-500 text-xs font-medium rounded-md cursor-not-allowed">
                                {{ $assignedToThis > 0 && count($containerRows) === count($containerAssignments) ? 'All assigned' : 'No new to assign' }}
                            </span>
                            @endif
                        </div>
                    </div>

                    {{-- Show assigned containers for this shipment --}}
                    @if($assignedToThis > 0)
                    <div class="mt-2 p-2 bg-green-50 rounded border border-green-200">
                        <p class="text-xs font-medium text-green-800 mb-1">{{ $assignedToThis }} container(s) assigned:</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach($assignedContainersList as $cno)
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">
                                {{ $cno }}
                                <button wire:click="detachContainer({{ $shipment['id'] }}, '{{ $cno }}')"
                                        wire:confirm="Detach container {{ $cno }} from shipment #{{ $shipment['id'] }}?"
                                        class="ml-0.5 text-green-500 hover:text-red-600"
                                        title="Detach {{ $cno }}">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            @if($newAssignableCount === 0 && count($containerAssignments) === 0)
            <p class="mt-2 text-xs text-amber-600">
                Containers cannot be assigned until driver and license plate details are filled in the transport sheet.
            </p>
            @endif
        </div>
        @elseif($searchMode === 'bl' && count($containerRows) > 0 && count($matchingShipments) === 0)
        <div class="px-4 py-3 border-b border-gray-200 bg-yellow-50/50">
            <p class="text-sm text-yellow-700">No matching shipments found on the platform for this BL.</p>
        </div>
        @endif

        <!-- Loading indicator -->
        <div wire:loading wire:target="loadContainerRows, previousDate, nextDate, goToDate, trackVehicle, searchByBl, viewShipmentBl" class="p-4">
            <div class="flex items-center justify-center text-gray-500">
                <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Loading container data...
            </div>
        </div>

        <!-- Error message -->
        @if($sheetError)
        <div class="mx-4 mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
            <p class="text-sm text-yellow-700">{{ $sheetError }}</p>
        </div>
        @endif

        <!-- Container Table -->
        @if(count($containerRows) > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Load Date</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Delivery Date</th>
                        @if($searchMode === 'bl')
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        @endif
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">BL</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Container No</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Job Status</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Terminal</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">1st Driver</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">1st License</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">1st Trip</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-8"></th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">2nd Driver</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">2nd License</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">2nd Trip</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-8"></th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">3rd Driver</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">3rd License</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">3rd Trip</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase w-8"></th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($containerRows as $index => $row)
                    @php
                        $containerNo = trim($row['CONTAINER NO'] ?? '');
                        $isAssigned = !empty($containerNo) && isset($containerAssignments[$containerNo]);
                        $assignedTo = $isAssigned ? $containerAssignments[$containerNo] : null;
                        $canTrack1 = !empty(trim($row['1ST DRIVER NAME'] ?? '')) && !empty(trim($row['1ST LICENSE'] ?? ''));
                        $canTrack2 = !empty(trim($row['2nd DRIVER NAME'] ?? '')) && !empty(trim($row['2nd LICENSE'] ?? ''));
                        $canTrack3 = !empty(trim($row['3rd DRIVER NAME'] ?? '')) && !empty(trim($row['3 rd LICENSE'] ?? ''));
                    @endphp
                    <tr class="{{ $isAssigned ? 'bg-green-50/70' : 'hover:bg-gray-50' }}">
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['LOAD DATE'] ?? '' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['DELIVERY DATE'] ?? '' }}</td>
                        @if($searchMode === 'bl')
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['CUSTOMER'] ?? '' }}</td>
                        @endif
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['AREA'] ?? '' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['BL'] ?? '' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap font-medium {{ $isAssigned ? 'text-green-800' : 'text-gray-900' }}">
                            {{ $containerNo }}
                            @if($isAssigned)
                            <span class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700" title="Assigned to {{ $assignedTo['shipmentLabel'] ?? '' }}">
                                {{ $assignedTo['shipmentLabel'] ?? 'Assigned' }}
                                <button wire:click="detachContainer({{ $assignedTo['shipmentId'] }}, '{{ $containerNo }}')"
                                        wire:confirm="Detach container {{ $containerNo }} from {{ $assignedTo['shipmentLabel'] ?? 'shipment' }}?"
                                        class="text-green-500 hover:text-red-600"
                                        title="Detach">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-center">
                            @php $returnNo = trim($row['Retrun No.(ใบเสร็จ)'] ?? ''); @endphp
                            @if(!empty($returnNo))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800" title="{{ $returnNo }}">Completed - {{ $returnNo }}</span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">On going</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['UNIT'] ?? '' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['TYPE  CONTAINER'] ?? '' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['TERMINAL'] ?? '' }}</td>
                        {{-- 1st driver slot --}}
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['1ST DRIVER NAME'] ?? '' }}</td>
                        <td class="px-3 py-2 text-gray-700">
                            @if(!empty($row['1ST LICENSE']))
                            @php $gps1 = $plateStatuses[trim($row['1ST LICENSE'])] ?? null; @endphp
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">{{ $row['1ST LICENSE'] }}</span>
                                @if($gps1)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                    {{ $gps1['vehicleStatus'] === 'MOVING' || $gps1['vehicleStatus'] === 'RUNNING' ? 'bg-green-100 text-green-800' : ($gps1['vehicleStatus'] === 'IDLE' ? 'bg-yellow-100 text-yellow-800' : ($gps1['vehicleStatus'] === 'PARK' ? 'bg-gray-100 text-gray-700' : 'bg-red-100 text-red-700')) }}">{{ $gps1['vehicleStatus'] }}</span>
                                @elseif($loadingGps)
                                <svg class="animate-spin h-3.5 w-3.5 text-blue-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                @endif
                            </div>
                            @if($gps1 && !empty($gps1['geoLocation']))
                            <p class="mt-0.5 text-xs text-gray-500 truncate max-w-[200px]" title="{{ $gps1['geoLocation'] }}">{{ $gps1['geoLocation'] }}</p>
                            @elseif($loadingGps && !$gps1)
                            <p class="mt-0.5 text-xs text-blue-400 animate-pulse">loading GPS...</p>
                            @endif
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['1st Trip'] ?? '' }}</td>
                        <td class="px-1 py-2 text-center">
                            <button wire:click="trackVehicle({{ $index }}, 1)"
                                    {{ !$canTrack1 ? 'disabled' : '' }}
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-full {{ $canTrack1 ? 'text-green-600 hover:bg-green-100 hover:text-green-800' : 'text-gray-300 cursor-not-allowed' }}"
                                    title="{{ $canTrack1 ? 'Track ' . trim($row['1ST LICENSE'] ?? '') : 'No driver/plate' }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </button>
                        </td>
                        {{-- 2nd driver slot --}}
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['2nd DRIVER NAME'] ?? '' }}</td>
                        <td class="px-3 py-2 text-gray-700">
                            @if(!empty($row['2nd LICENSE']))
                            @php $gps2 = $plateStatuses[trim($row['2nd LICENSE'])] ?? null; @endphp
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">{{ $row['2nd LICENSE'] }}</span>
                                @if($gps2)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                    {{ $gps2['vehicleStatus'] === 'MOVING' || $gps2['vehicleStatus'] === 'RUNNING' ? 'bg-green-100 text-green-800' : ($gps2['vehicleStatus'] === 'IDLE' ? 'bg-yellow-100 text-yellow-800' : ($gps2['vehicleStatus'] === 'PARK' ? 'bg-gray-100 text-gray-700' : 'bg-red-100 text-red-700')) }}">{{ $gps2['vehicleStatus'] }}</span>
                                @elseif($loadingGps)
                                <svg class="animate-spin h-3.5 w-3.5 text-blue-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                @endif
                            </div>
                            @if($gps2 && !empty($gps2['geoLocation']))
                            <p class="mt-0.5 text-xs text-gray-500 truncate max-w-[200px]" title="{{ $gps2['geoLocation'] }}">{{ $gps2['geoLocation'] }}</p>
                            @elseif($loadingGps && !$gps2)
                            <p class="mt-0.5 text-xs text-blue-400 animate-pulse">loading GPS...</p>
                            @endif
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['2nd Trip'] ?? '' }}</td>
                        <td class="px-1 py-2 text-center">
                            <button wire:click="trackVehicle({{ $index }}, 2)"
                                    {{ !$canTrack2 ? 'disabled' : '' }}
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-full {{ $canTrack2 ? 'text-green-600 hover:bg-green-100 hover:text-green-800' : 'text-gray-300 cursor-not-allowed' }}"
                                    title="{{ $canTrack2 ? 'Track ' . trim($row['2nd LICENSE'] ?? '') : 'No driver/plate' }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </button>
                        </td>
                        {{-- 3rd driver slot --}}
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['3rd DRIVER NAME'] ?? '' }}</td>
                        <td class="px-3 py-2 text-gray-700">
                            @if(!empty($row['3 rd LICENSE']))
                            @php $gps3 = $plateStatuses[trim($row['3 rd LICENSE'])] ?? null; @endphp
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">{{ $row['3 rd LICENSE'] }}</span>
                                @if($gps3)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                                    {{ $gps3['vehicleStatus'] === 'MOVING' || $gps3['vehicleStatus'] === 'RUNNING' ? 'bg-green-100 text-green-800' : ($gps3['vehicleStatus'] === 'IDLE' ? 'bg-yellow-100 text-yellow-800' : ($gps3['vehicleStatus'] === 'PARK' ? 'bg-gray-100 text-gray-700' : 'bg-red-100 text-red-700')) }}">{{ $gps3['vehicleStatus'] }}</span>
                                @elseif($loadingGps)
                                <svg class="animate-spin h-3.5 w-3.5 text-blue-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                @endif
                            </div>
                            @if($gps3 && !empty($gps3['geoLocation']))
                            <p class="mt-0.5 text-xs text-gray-500 truncate max-w-[200px]" title="{{ $gps3['geoLocation'] }}">{{ $gps3['geoLocation'] }}</p>
                            @elseif($loadingGps && !$gps3)
                            <p class="mt-0.5 text-xs text-blue-400 animate-pulse">loading GPS...</p>
                            @endif
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $row['3rd Trip'] ?? '' }}</td>
                        <td class="px-1 py-2 text-center">
                            <button wire:click="trackVehicle({{ $index }}, 3)"
                                    {{ !$canTrack3 ? 'disabled' : '' }}
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-full {{ $canTrack3 ? 'text-green-600 hover:bg-green-100 hover:text-green-800' : 'text-gray-300 cursor-not-allowed' }}"
                                    title="{{ $canTrack3 ? 'Track ' . trim($row['3 rd LICENSE'] ?? '') : 'No driver/plate' }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </button>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @if(!empty($row['STATUS']))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ $row['STATUS'] }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    @endif

    <!-- Section 3: GPS Map -->
    @if($locationError)
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-sm text-red-700">{{ $locationError }}</p>
    </div>
    @endif

    @if($showMap && count($vehicleLocations) > 0)
    <div class="space-y-4" wire:key="gps-results-{{ $mapVersion }}">
        <!-- Vehicle Info Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach($vehicleLocations as $loc)
            @if($loc['found'])
            <div class="bg-white rounded-lg shadow p-3 border-l-4 {{ $loc['vehicleStatus'] === 'MOVING' || $loc['vehicleStatus'] === 'RUNNING' ? 'border-green-500' : ($loc['vehicleStatus'] === 'IDLE' ? 'border-yellow-500' : 'border-gray-400') }}">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ $loc['vehiclePlate'] }}</p>
                        @if($loc['driver'])
                        <p class="text-xs text-gray-600">{{ $loc['driver'] }}</p>
                        @endif
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                        {{ $loc['vehicleStatus'] === 'MOVING' || $loc['vehicleStatus'] === 'RUNNING' ? 'bg-green-100 text-green-800' : ($loc['vehicleStatus'] === 'IDLE' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                        {{ $loc['vehicleStatus'] }}
                    </span>
                </div>
                @if(!empty($loc['container']) || !empty($loc['booking']))
                <div class="mt-1 text-xs text-gray-500 space-y-0.5">
                    @if(!empty($loc['container']))
                    <p>Container: {{ $loc['container'] }}</p>
                    @endif
                    @if(!empty($loc['booking']))
                    <p>BL: {{ $loc['booking'] }}</p>
                    @endif
                </div>
                @endif
                <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>{{ $loc['speed'] }} km/h</span>
                    @if($loc['geoLocation'])
                    <span class="truncate">{{ $loc['geoLocation'] }}</span>
                    @endif
                </div>
            </div>
            @else
            <div class="bg-white rounded-lg shadow p-3 border-l-4 border-red-300 opacity-60">
                <p class="text-sm font-bold text-gray-700">{{ $loc['plate'] }}</p>
                <p class="text-xs text-red-500">GPS not found</p>
                @if($loc['driver'])
                <p class="text-xs text-gray-500">{{ $loc['driver'] }}</p>
                @endif
            </div>
            @endif
            @endforeach
        </div>

        <!-- Map -->
        <div class="bg-white rounded-lg shadow p-4"
             x-data="transportMap(@js(collect($vehicleLocations)->where('found', true)->values()))"
             x-init="$nextTick(() => initMap())">
            <div id="transport-map" style="height: 500px; width: 100%; border-radius: 0.5rem;"></div>
        </div>
    </div>
    @endif

    @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        function transportMap(locations) {
            return {
                map: null,
                markers: [],
                locations: locations || [],

                initMap() {
                    if (!this.locations.length) return;
                    if (typeof L === 'undefined') return;

                    const el = this.$el.querySelector('#transport-map');
                    if (!el) return;

                    this.map = L.map(el).setView([13.7563, 100.5018], 10);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 18,
                    }).addTo(this.map);

                    const bounds = [];

                    this.locations.forEach(loc => {
                        if (!loc.lat || !loc.lng) return;

                        const statusColors = {
                            'MOVING': '#22c55e',
                            'RUNNING': '#22c55e',
                            'IDLE': '#eab308',
                            'PARK': '#6b7280',
                        };
                        const color = statusColors[loc.vehicleStatus] || '#6b7280';

                        const icon = L.divIcon({
                            html: `<div style="background:${color};color:white;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:14px;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white" viewBox="0 0 16 16"><path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5v-7zM1.294 7.456A.5.5 0 0 0 1.5 8h5V4.51a.5.5 0 0 0-.5-.51h-5a.5.5 0 0 0-.5.51v2.946zM12 10a2 2 0 1 1 .001 4.001A2 2 0 0 1 12 10zm-9 0a2 2 0 1 1 .001 4.001A2 2 0 0 1 3 10z"/></svg>
                            </div>`,
                            className: '',
                            iconSize: [32, 32],
                            iconAnchor: [16, 16],
                        });

                        const popup = `
                            <div style="min-width:180px">
                                <strong>${loc.vehiclePlate}</strong><br>
                                ${loc.driver ? '<small>Driver: ' + loc.driver + '</small><br>' : ''}
                                ${loc.container ? '<small>Container: ' + loc.container + '</small><br>' : ''}
                                ${loc.booking ? '<small>BL: ' + loc.booking + '</small><br>' : ''}
                                <small>Speed: ${loc.speed} km/h</small><br>
                                <small>Status: <span style="color:${color};font-weight:bold">${loc.vehicleStatus}</span></small><br>
                                ${loc.geoLocation ? '<small>' + loc.geoLocation + '</small>' : ''}
                            </div>
                        `;

                        const marker = L.marker([loc.lat, loc.lng], { icon })
                            .bindPopup(popup)
                            .addTo(this.map);

                        this.markers.push(marker);
                        bounds.push([loc.lat, loc.lng]);
                    });

                    if (bounds.length > 0) {
                        this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
                    }
                }
            };
        }
    </script>
    @endpush
</div>
