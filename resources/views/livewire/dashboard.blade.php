<div class="p-6 space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-3xl font-bold text-gray-900">CS Shipping LCB Dashboard</h1>
        <p class="text-gray-600 mt-2">ภาพรวมการดำเนินงานประจำวัน - {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-md">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-8V4a1 1 0 00-1-1H7a1 1 0 00-1 1v1m12 0H6"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-blue-600 font-medium">Total Shipments</p>
                    <p class="text-2xl font-bold text-blue-900">{{ $stats['total_shipments'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-md">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-green-600 font-medium">Active Shipments</p>
                    <p class="text-2xl font-bold text-green-900">{{ $stats['active_shipments'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-md">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-yellow-600 font-medium">Vessels Arriving</p>
                    <p class="text-2xl font-bold text-yellow-900">{{ $stats['vessels_arriving_soon'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-orange-50 border border-orange-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-2 bg-orange-100 rounded-md">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-orange-600 font-medium">Pending Documents</p>
                    <p class="text-2xl font-bold text-orange-900">{{ $stats['pending_documents'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-md">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.864-.833-2.634 0L4.182 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-red-600 font-medium">Overdue Documents</p>
                    <p class="text-2xl font-bold text-red-900">{{ $stats['overdue_documents'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Shipments -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">งานล่าสุด (Recent Shipments)</h2>
            </div>
            <div class="p-6">
                @if($recent_shipments->count() > 0)
                    <div class="space-y-4">
                        @foreach($recent_shipments as $shipment)
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $shipment->shipment_number }}</p>
                                    <p class="text-sm text-gray-600">{{ $shipment->customer->company }}</p>
                                    <p class="text-sm text-gray-500">{{ $shipment->vessel->vessel_name }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($shipment->status === 'new') bg-blue-100 text-blue-800
                                        @elseif($shipment->status === 'planning') bg-yellow-100 text-yellow-800
                                        @elseif($shipment->status === 'documents_preparation') bg-orange-100 text-orange-800
                                        @elseif($shipment->status === 'customs_clearance') bg-purple-100 text-purple-800
                                        @elseif($shipment->status === 'ready_for_delivery') bg-green-100 text-green-800
                                        @elseif($shipment->status === 'in_transit') bg-indigo-100 text-indigo-800
                                        @elseif($shipment->status === 'delivered') bg-emerald-100 text-emerald-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}
                                    </span>
                                    @if($shipment->planned_delivery_date)
                                        <p class="text-sm text-gray-500 mt-1">
                                            {{ $shipment->planned_delivery_date->format('d/m/Y') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">ไม่มีข้อมูล shipment</p>
                @endif
            </div>
        </div>

        <!-- Vessels Arriving Soon -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">เรือที่จะเข้าท่า (Vessels Arriving Soon)</h2>
            </div>
            <div class="p-6">
                @if($vessels_arriving->count() > 0)
                    <div class="space-y-4">
                        @foreach($vessels_arriving as $vessel)
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $vessel->vessel_name }}</p>
                                    <p class="text-sm text-gray-600">{{ $vessel->voyage_number }}</p>
                                    <p class="text-sm text-gray-500">{{ $vessel->port }}</p>
                                    <p class="text-sm text-gray-500">
                                        {{ $vessel->shipments->count() }} shipments
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-gray-900">
                                        {{ $vessel->eta->format('d/m/Y') }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ $vessel->eta->format('H:i') }}
                                    </p>
                                    <p class="text-xs text-blue-600 mt-1">
                                        {{ $vessel->eta->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-8">ไม่มีเรือที่จะเข้าท่าใน 48 ชั่วโมงข้างหน้า</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Urgent Tasks Section -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">งานเร่งด่วน (Urgent Tasks)</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="font-medium text-yellow-800">Pending D/O</h3>
                    <p class="text-2xl font-bold text-yellow-900">{{ $urgent_tasks['pending_dos'] }}</p>
                    <p class="text-sm text-yellow-600">รอจ่ายเงินและรับ D/O</p>
                </div>
                
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <h3 class="font-medium text-purple-800">Customs Clearance</h3>
                    <p class="text-2xl font-bold text-purple-900">{{ $urgent_tasks['customs_clearance'] }}</p>
                    <p class="text-sm text-purple-600">รอการตรวจปล่อยศุลกากร</p>
                </div>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="font-medium text-green-800">Ready for Delivery</h3>
                    <p class="text-2xl font-bold text-green-900">{{ $urgent_tasks['ready_for_delivery'] }}</p>
                    <p class="text-sm text-green-600">พร้อมส่งของให้ลูกค้า</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-refresh indicator -->
    <div class="text-center text-sm text-gray-500">
        <p>อัพเดตข้อมูลอัตโนมัติทุก 30 วินาที</p>
    </div>
</div>

<script>
    // Auto-refresh every 30 seconds
    setInterval(() => {
        Livewire.dispatch('refresh');
    }, 30000);
</script>
