<div class="p-6 space-y-6" wire:poll.2s="refreshProgress">
    <!-- Header Section -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">🚀 ETA Check Report</h1>
            <p class="mt-2 text-sm text-gray-700">Real-time progress of ETA checking for all in-progress shipments</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('schedules') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                ← Back to Schedules
            </a>
        </div>
    </div>

    <!-- Progress Overview -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Progress Overview</h3>
                @if($isRunning)
                    <div class="flex items-center text-blue-600">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Running...
                    </div>
                @elseif($totalShipments === 0)
                    <div class="flex items-center text-gray-600">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        No shipments to check
                    </div>
                @else
                    <div class="flex items-center text-green-600">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Completed
                    </div>
                @endif
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="flex justify-between text-sm text-gray-600 mb-1">
                    <span>Progress</span>
                    <span>{{ $completedCount }}/{{ $totalShipments }} ({{ $this->getProgressPercentage() }}%)</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                         style="width: {{ $this->getProgressPercentage() }}%"></div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $totalShipments }}</div>
                    <div class="text-sm text-gray-600">Total Shipments</div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ $successCount }}</div>
                    <div class="text-sm text-gray-600">Successful</div>
                </div>
                <div class="bg-red-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-red-600">{{ $errorCount }}</div>
                    <div class="text-sm text-gray-600">Errors</div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-gray-600">{{ $totalShipments - $completedCount }}</div>
                    <div class="text-sm text-gray-600">Pending</div>
                </div>
            </div>

            <!-- Timing Info -->
            <div class="mt-4 text-sm text-gray-500">
                <div>Started: {{ $startTime->format('M j, Y H:i:s') }}</div>
                @if($endTime)
                    <div>Completed: {{ $endTime->format('M j, Y H:i:s') }}</div>
                    <div>Duration: {{ $startTime->diffForHumans($endTime, true) }}</div>
                @elseif($isRunning)
                    <div>Running for: {{ $startTime->diffForHumans() }}</div>
                @endif
            </div>
        </div>
    </div>

    <!-- Shipments Detail Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Shipment Details</h3>

            @if(count($shipments) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vessel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terminal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Checked At</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($shipments as $shipment)
                                <tr class="hover:bg-gray-50">
                                    <!-- Status -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($shipment['status'] === 'pending')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                ⏳ Pending
                                            </span>
                                        @elseif($shipment['status'] === 'checking')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <svg class="animate-spin -ml-1 mr-1.5 h-3 w-3" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Checking...
                                            </span>
                                        @elseif($shipment['status'] === 'completed')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✅ Completed
                                            </span>
                                        @elseif($shipment['status'] === 'error')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                ❌ Error
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Invoice -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $shipment['invoice_number'] }}</div>
                                    </td>

                                    <!-- Vessel -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $shipment['vessel_name'] }}</div>
                                        @if($shipment['voyage'])
                                            <div class="text-sm text-gray-500">{{ $shipment['voyage'] }}</div>
                                        @endif
                                    </td>

                                    <!-- Terminal -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $shipment['port_terminal'] }}</div>
                                    </td>

                                    <!-- Customer -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $shipment['customer_name'] }}</div>
                                    </td>

                                    <!-- Result -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($shipment['status'] === 'completed')
                                            @if($shipment['eta_found'])
                                                <div class="text-sm text-green-600 font-medium">ETA Found</div>
                                                <div class="text-xs text-gray-500">{{ $shipment['result'] }}</div>
                                            @else
                                                <div class="text-sm text-yellow-600 font-medium">No Data</div>
                                            @endif
                                        @elseif($shipment['status'] === 'error')
                                            <div class="text-sm text-red-600 font-medium">Failed</div>
                                            @if($shipment['error_message'])
                                                <div class="text-xs text-gray-500" title="{{ $shipment['error_message'] }}">
                                                    {{ Str::limit($shipment['error_message'], 30) }}
                                                </div>
                                            @endif
                                        @else
                                            <div class="text-sm text-gray-400">-</div>
                                        @endif
                                    </td>

                                    <!-- Checked At -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($shipment['checked_at'])
                                            {{ $shipment['checked_at'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">All caught up!</h3>
                    <p class="mt-1 text-sm text-gray-500">No shipments need ETA checking at this time.</p>
                    <p class="mt-2 text-xs text-gray-400">This could mean:</p>
                    <ul class="mt-1 text-xs text-gray-400 space-y-1">
                        <li>• All shipments are already tracked (on_track/delay status)</li>
                        <li>• All shipments were recently checked (within 4 hours)</li>
                        <li>• No shipments have vessel or terminal information</li>
                        <li>• Shipments with "not_found" status are excluded from checking</li>
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <!-- Auto-refresh indicator -->
    @if($isRunning)
        <div class="fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg">
            <div class="flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Auto-refreshing...
            </div>
        </div>
    @endif
</div>

<!-- JavaScript for real-time updates -->
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('refresh-progress', () => {
            // Additional client-side logic if needed
        });
    });
</script>