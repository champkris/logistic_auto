<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Thailand Port & Logistics API Testing</h1>
                    <p class="text-gray-600 mt-2">Testing vessel tracking and port system integration capabilities</p>
                </div>
                <div class="flex space-x-3">
                    <button 
                        wire:click="testAllEndpoints" 
                        wire:loading.attr="disabled"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="testAllEndpoints">Test All Endpoints</span>
                        <span wire:loading wire:target="testAllEndpoints" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Testing...
                        </span>
                    </button>
                    @if(count($testResults) > 0)
                        <button 
                            wire:click="clearResults"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200">
                            Clear Results
                        </button>
                        <button 
                            wire:click="exportResults"
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200">
                            Export Results
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('message') }}</span>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Summary Cards -->
    @if(count($testResults) > 0)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-center">
                        <h3 class="text-lg font-semibold text-gray-900">Total Endpoints</h3>
                        <p class="text-3xl font-bold text-blue-600 mt-2">{{ $summary['total'] }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-center">
                        <h3 class="text-lg font-semibold text-gray-900">Successful</h3>
                        <p class="text-3xl font-bold text-green-600 mt-2">{{ $summary['successful'] }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-center">
                        <h3 class="text-lg font-semibold text-gray-900">Accessible</h3>
                        <p class="text-3xl font-bold text-blue-600 mt-2">{{ $summary['accessible'] }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-center">
                        <h3 class="text-lg font-semibold text-gray-900">Errors</h3>
                        <p class="text-3xl font-bold text-red-600 mt-2">{{ $summary['errors'] }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="text-center">
                        <h3 class="text-lg font-semibold text-gray-900">Avg Response</h3>
                        <p class="text-3xl font-bold text-purple-600 mt-2">{{ $summary['average_response_time'] }}ms</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <!-- Endpoints List -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
        <div class="space-y-4">
            @foreach($endpoints as $key => $endpoint)
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 hover:shadow-lg transition-shadow duration-200">
                    <!-- Endpoint Header -->
                    <div class="px-6 py-4 border-b border-gray-200 cursor-pointer" wire:click="toggleDetails('{{ $key }}')">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900">{{ $endpoint['name'] }}</h3>
                                <p class="text-sm text-gray-600 font-mono break-all">{{ $endpoint['url'] }}</p>
                                <div class="mt-2 flex items-center space-x-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ strtoupper($endpoint['type']) }}
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ strtoupper($endpoint['method']) }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                @if(isset($testResults[$key]))
                                    @php
                                        $result = $testResults[$key];
                                        $statusColor = $this->getStatusColor($result['status']);
                                    @endphp
                                    
                                    <div class="text-right">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusColor }}">
                                            {{ ucfirst(str_replace('_', ' ', $result['status'])) }}
                                        </span>
                                        @if($result['response_time'] > 0)
                                            <p class="text-sm text-gray-600 mt-1">{{ $result['response_time'] }}ms</p>
                                        @endif
                                    </div>

                                    @if($result['automation_potential']['score'] > 0)
                                        @php
                                            $automationColor = $this->getAutomationPotentialColor($result['automation_potential']['level']);
                                        @endphp
                                        <div class="text-center">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $automationColor }}">
                                                {{ $result['automation_potential']['level'] }}
                                            </span>
                                            <p class="text-sm text-gray-600 mt-1">{{ $result['automation_potential']['score'] }}% Score</p>
                                        </div>
                                    @endif
                                @endif
                                
                                <button 
                                    wire:click="testSingleEndpoint('{{ $key }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="testSingleEndpoint('{{ $key }}')"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-200 disabled:opacity-50">
                                    
                                    <span wire:loading.remove wire:target="testSingleEndpoint('{{ $key }}')">
                                        Test
                                    </span>
                                    <span wire:loading wire:target="testSingleEndpoint('{{ $key }}')" class="flex items-center">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Testing...
                                    </span>
                                </button>

                                <!-- Expand/Collapse Icon -->
                                <svg class="w-5 h-5 text-gray-400 transform transition-transform duration-200 @if($showDetails[$key] ?? false) rotate-180 @endif" 
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <!-- Endpoint Details -->
                    @if(($showDetails[$key] ?? false) && isset($testResults[$key]))
                        @php $result = $testResults[$key]; @endphp
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Response Information -->
                                <div class="space-y-4">
                                    <h4 class="text-lg font-semibold text-gray-900">Response Information</h4>
                                    
                                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                                        <dl class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <dt class="font-medium text-gray-500">Status Code</dt>
                                                <dd class="mt-1 text-gray-900">{{ $result['status_code'] ?? 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-medium text-gray-500">Response Time</dt>
                                                <dd class="mt-1 text-gray-900">{{ $result['response_time'] ?? 0 }}ms</dd>
                                            </div>
                                            <div>
                                                <dt class="font-medium text-gray-500">Content Type</dt>
                                                <dd class="mt-1 text-gray-900 font-mono text-xs">{{ $result['content_type'] ?? 'unknown' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-medium text-gray-500">Content Length</dt>
                                                <dd class="mt-1 text-gray-900">{{ number_format($result['content_length'] ?? 0) }} bytes</dd>
                                            </div>
                                            <div>
                                                <dt class="font-medium text-gray-500">Accessible</dt>
                                                <dd class="mt-1">
                                                    @if($result['accessible'])
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            ✓ Yes
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            ✗ No
                                                        </span>
                                                    @endif
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="font-medium text-gray-500">Has Vessel Data</dt>
                                                <dd class="mt-1">
                                                    @if($result['has_vessel_data'])
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            ✓ Yes
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                            ✗ No
                                                        </span>
                                                    @endif
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    @if(isset($result['error']))
                                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                            <h5 class="text-sm font-semibold text-red-800">Error Details</h5>
                                            <p class="mt-2 text-sm text-red-700 font-mono">{{ $result['error'] }}</p>
                                        </div>
                                    @endif
                                </div>

                                <!-- Content Analysis -->
                                <div class="space-y-4">
                                    <h4 class="text-lg font-semibold text-gray-900">Content Analysis</h4>
                                    
                                    @if(isset($result['content_analysis']))
                                        @php $analysis = $result['content_analysis']; @endphp
                                        
                                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                                            <dl class="space-y-3 text-sm">
                                                <div>
                                                    <dt class="font-medium text-gray-500">Data Format</dt>
                                                    <dd class="mt-1">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{ strtoupper($analysis['data_format'] ?? 'HTML') }}
                                                        </span>
                                                    </dd>
                                                </div>
                                                
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <dt class="font-medium text-gray-500">Has Forms</dt>
                                                        <dd class="mt-1">
                                                            @if($analysis['has_forms'] ?? false)
                                                                <span class="text-green-600">✓ Yes</span>
                                                            @else
                                                                <span class="text-gray-400">✗ No</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="font-medium text-gray-500">Has Tables</dt>
                                                        <dd class="mt-1">
                                                            @if($analysis['has_tables'] ?? false)
                                                                <span class="text-green-600">✓ Yes</span>
                                                            @else
                                                                <span class="text-gray-400">✗ No</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                </div>

                                                @if(!empty($analysis['vessel_indicators']))
                                                    <div>
                                                        <dt class="font-medium text-gray-500">Vessel Keywords Found</dt>
                                                        <dd class="mt-1">
                                                            <div class="flex flex-wrap gap-1">
                                                                @foreach($analysis['vessel_indicators'] as $indicator)
                                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                                                        {{ $indicator }}
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        </dd>
                                                    </div>
                                                @endif
                                            </dl>
                                        </div>
                                    @endif
                                    <!-- Automation Potential -->
                                    @if(isset($result['automation_potential']))
                                        @php $automation = $result['automation_potential']; @endphp
                                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                                            <h5 class="text-sm font-semibold text-gray-900 mb-3">Automation Potential</h5>
                                            
                                            <div class="flex items-center justify-between mb-3">
                                                <span class="text-sm font-medium text-gray-500">Score</span>
                                                <div class="flex items-center">
                                                    <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                                                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $automation['score'] }}%"></div>
                                                    </div>
                                                    <span class="text-sm font-semibold text-gray-900">{{ $automation['score'] }}%</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <span class="text-sm font-medium text-gray-500">Level: </span>
                                                @php $levelColor = $this->getAutomationPotentialColor($automation['level']); @endphp
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $levelColor }}">
                                                    {{ $automation['level'] }}
                                                </span>
                                            </div>
                                            
                                            @if(!empty($automation['factors']))
                                                <div>
                                                    <span class="text-sm font-medium text-gray-500">Factors:</span>
                                                    <ul class="mt-1 text-xs text-gray-600">
                                                        @foreach($automation['factors'] as $factor)
                                                            <li class="flex items-center">
                                                                <span class="w-1 h-1 bg-blue-600 rounded-full mr-2"></span>
                                                                {{ $factor }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Raw Response Preview -->
                            @if(isset($result['raw_response']) && strlen($result['raw_response']) > 0)
                                <div class="mt-6">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Response Preview</h4>
                                    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                        <pre class="text-green-400 text-xs font-mono whitespace-pre-wrap">{{ $result['raw_response'] }}</pre>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Loading Overlay -->
    <div wire:loading.flex wire:target="testAllEndpoints" class="fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-sm mx-4 text-center">
            <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Testing All Endpoints</h3>
            <p class="text-gray-600">This may take a few moments...</p>
        </div>
    </div>
</div>