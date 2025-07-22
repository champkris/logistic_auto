@extends('layouts.app')

@section('title', 'Vessel Tracking Test Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">🚢 Vessel Tracking Test Dashboard</h1>
        <p class="text-lg text-gray-600">Test connectivity and data extraction from Thai port websites</p>
    </div>

    <!-- Quick Actions -->
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 mb-8 text-white">
        <h2 class="text-2xl font-bold mb-4">Quick Actions</h2>
        <div class="flex flex-wrap gap-4">
            <button onclick="testAllSites()" class="bg-white text-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                🌐 Test All Sites
            </button>
            <button onclick="showVesselSearch()" class="bg-white text-purple-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                🔍 Search Vessel
            </button>
            <a href="{{ route('vessel.tracking.config') }}" class="bg-white text-green-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                ⚙️ View Config
            </a>
        </div>
    </div>

    <!-- Vessel Search Modal -->
    <div id="vesselSearchModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-xl font-bold mb-4">Search Vessel</h3>
                <form id="vesselSearchForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Vessel Name</label>
                        <input type="text" id="vesselName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., EVER GIVEN" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Voyage (Optional)</label>
                        <input type="text" id="voyage" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., 001E">
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition-colors">Search</button>
                        <button type="button" onclick="hideVesselSearch()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-md hover:bg-gray-400 transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Results Area -->
    <div id="resultsArea" class="mb-8 hidden">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-4">Test Results</h2>
            <div id="resultsContent"></div>
        </div>
    </div>

    <!-- Port Sites Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($portSites as $siteKey => $site)
        <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="p-6">
                <!-- Site Header -->
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">{{ $site['name'] }}</h3>
                        @php
                            $typeColors = [
                                'container' => 'blue',
                                'berth_schedule' => 'green', 
                                'vessel' => 'purple',
                                'ship_schedule' => 'indigo',
                                'port_system' => 'orange',
                                'tracking' => 'cyan',
                                'service_tracking' => 'pink',
                                'terminal' => 'yellow'
                            ];
                            $color = $typeColors[$site['type']] ?? 'gray';
                        @endphp
                        <span class="inline-block px-3 py-1 bg-{{ $color }}-100 text-{{ $color }}-800 text-sm rounded-full mt-2">
                            {{ ucfirst(str_replace('_', ' ', $site['type'])) }}
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-sm text-gray-500">{{ strtoupper($site['method']) }}</span>
                    </div>
                </div>

                <!-- Site URL -->
                <div class="mb-4">
                    <p class="text-sm text-gray-600 break-all">
                        <a href="{{ $site['url'] }}" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline">
                            {{ $site['url'] }}
                        </a>
                    </p>
                </div>

                <!-- Test Status -->
                <div id="status-{{ $siteKey }}" class="mb-4">
                    <div class="flex items-center text-gray-500">
                        <span class="w-3 h-3 bg-gray-300 rounded-full mr-2"></span>
                        <span class="text-sm">Ready to test</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-2">
                    <button onclick="testSingleSite('{{ $siteKey }}')" 
                            class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium">
                        Test Site
                    </button>
                    <button onclick="showSiteDetails('{{ $siteKey }}')" 
                            class="bg-gray-200 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-300 transition-colors text-sm font-medium">
                        Details
                    </button>
                </div>

                <!-- Results Preview -->
                <div id="preview-{{ $siteKey }}" class="mt-4 hidden">
                    <div class="bg-gray-50 rounded-md p-3">
                        <div class="text-sm text-gray-600">
                            <div class="flex justify-between items-center">
                                <span>Response Time:</span>
                                <span id="time-{{ $siteKey }}" class="font-mono">-</span>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span>Status:</span>
                                <span id="http-status-{{ $siteKey }}" class="font-mono">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Statistics Summary -->
    <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Site Statistics</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center">
                <div class="text-3xl font-bold text-blue-600">{{ count($portSites) }}</div>
                <div class="text-sm text-gray-600">Total Sites</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-green-600" id="successCount">0</div>
                <div class="text-sm text-gray-600">Successful</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-red-600" id="failedCount">0</div>
                <div class="text-sm text-gray-600">Failed</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-purple-600" id="avgResponseTime">-</div>
                <div class="text-sm text-gray-600">Avg Response (ms)</div>
            </div>
        </div>
    </div>
</div>

<script>
// Test all sites simultaneously
async function testAllSites() {
    showLoading('Testing all sites...');
    
    try {
        const response = await fetch('{{ route("vessel.tracking.test.all") }}');
        const data = await response.json();
        
        displayAllSitesResults(data);
        updateStatistics(data);
    } catch (error) {
        showError('Failed to test sites: ' + error.message);
    }
}

// Test single site
async function testSingleSite(siteKey) {
    updateSiteStatus(siteKey, 'testing', 'Testing...');
    
    try {
        const response = await fetch(`{{ url('vessel-tracking/test/site') }}/${siteKey}`);
        const data = await response.json();
        
        if (data.success) {
            updateSiteStatus(siteKey, 'success', `Success (${data.response_time_ms}ms)`);
            showSitePreview(siteKey, data);
        } else {
            updateSiteStatus(siteKey, 'error', `Error: ${data.error}`);
        }
    } catch (error) {
        updateSiteStatus(siteKey, 'error', `Failed: ${error.message}`);
    }
}

// Search vessel
async function searchVessel(vesselName, voyage = '') {
    showLoading(`Searching for vessel: ${vesselName}`);
    
    try {
        const response = await fetch('{{ route("vessel.tracking.search.vessel") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                vessel_name: vesselName,
                voyage: voyage
            })
        });
        
        const data = await response.json();
        displayVesselSearchResults(data);
    } catch (error) {
        showError('Failed to search vessel: ' + error.message);
    }
}

// Update site status indicator
function updateSiteStatus(siteKey, status, message) {
    const statusElement = document.getElementById(`status-${siteKey}`);
    const colors = {
        testing: { bg: 'bg-yellow-400', text: 'text-yellow-800' },
        success: { bg: 'bg-green-400', text: 'text-green-800' },
        error: { bg: 'bg-red-400', text: 'text-red-800' }
    };
    
    const color = colors[status] || colors.testing;
    statusElement.innerHTML = `
        <div class="flex items-center ${color.text}">
            <span class="w-3 h-3 ${color.bg} rounded-full mr-2"></span>
            <span class="text-sm">${message}</span>
        </div>
    `;
}

// Show site preview results
function showSitePreview(siteKey, data) {
    const previewElement = document.getElementById(`preview-${siteKey}`);
    const timeElement = document.getElementById(`time-${siteKey}`);
    const statusElement = document.getElementById(`http-status-${siteKey}`);
    
    timeElement.textContent = `${data.response_time_ms}ms`;
    statusElement.textContent = data.status_code;
    
    previewElement.classList.remove('hidden');
}

// Display all sites results
function displayAllSitesResults(data) {
    const resultsArea = document.getElementById('resultsArea');
    const resultsContent = document.getElementById('resultsContent');
    
    let html = `
        <div class="mb-4 p-4 bg-blue-50 rounded-lg">
            <h3 class="font-bold text-lg mb-2">Summary</h3>
            <p><strong>Total Sites:</strong> ${data.summary.total_sites}</p>
            <p><strong>Successful:</strong> ${data.summary.successful}</p>
            <p><strong>Failed:</strong> ${data.summary.failed}</p>
            <p><strong>Total Time:</strong> ${data.summary.total_time_ms}ms</p>
        </div>
        <div class="space-y-4">
    `;
    
    Object.entries(data.results).forEach(([siteKey, result]) => {
        const statusColor = result.success ? 'text-green-600' : 'text-red-600';
        const statusIcon = result.success ? '✅' : '❌';
        
        html += `
            <div class="border rounded-lg p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h4 class="font-bold ${statusColor}">${statusIcon} ${result.site_name}</h4>
                        <p class="text-sm text-gray-600">${result.url}</p>
                    </div>
                    <div class="text-right text-sm">
                        ${result.success ? 
                            `<span class="text-green-600">${result.status_code}</span><br>
                             <span class="text-gray-500">${result.response_time_ms}ms</span>` :
                            `<span class="text-red-600">Error</span>`
                        }
                    </div>
                </div>
                ${result.success && result.preview ? `
                    <div class="mt-2 text-sm">
                        <strong>Title:</strong> ${result.preview.title || 'N/A'}<br>
                        <strong>Accessibility:</strong> ${result.accessibility}
                    </div>
                ` : ''}
                ${!result.success ? `
                    <div class="mt-2 text-sm text-red-600">
                        <strong>Error:</strong> ${result.error}
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    html += '</div>';
    resultsContent.innerHTML = html;
    resultsArea.classList.remove('hidden');
}

// Display vessel search results
function displayVesselSearchResults(data) {
    const resultsArea = document.getElementById('resultsArea');
    const resultsContent = document.getElementById('resultsContent');
    
    let html = `
        <div class="mb-4 p-4 bg-purple-50 rounded-lg">
            <h3 class="font-bold text-lg mb-2">Vessel Search Results</h3>
            <p><strong>Vessel:</strong> ${data.vessel_name}</p>
            ${data.voyage ? `<p><strong>Voyage:</strong> ${data.voyage}</p>` : ''}
        </div>
        <div class="space-y-4">
    `;
    
    Object.entries(data.search_results).forEach(([siteKey, result]) => {
        const statusColor = result.success ? 'text-green-600' : 'text-red-600';
        const statusIcon = result.success ? '✅' : '❌';
        
        html += `
            <div class="border rounded-lg p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h4 class="font-bold ${statusColor}">${statusIcon} ${result.site_name}</h4>
                        ${result.success ? `<p class="text-sm text-gray-600">${result.url}</p>` : ''}
                    </div>
                    <div class="text-right text-sm">
                        ${result.success ? 
                            `<span class="text-green-600">${result.status_code}</span><br>
                             <span class="text-gray-500">${result.response_time_ms}ms</span>` :
                            `<span class="text-red-600">Error</span>`
                        }
                    </div>
                </div>
                ${result.success && result.vessel_data_found ? `
                    <div class="mt-2 text-sm">
                        <strong>Vessel Found:</strong> ${result.vessel_data_found.vessel_name_found ? 'Yes' : 'No'}<br>
                        <strong>Data Indicators:</strong> ${result.vessel_data_found.content_indicators.join(', ') || 'None'}
                    </div>
                ` : ''}
                ${!result.success ? `
                    <div class="mt-2 text-sm text-red-600">
                        <strong>Error:</strong> ${result.error}
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    html += '</div>';
    resultsContent.innerHTML = html;
    resultsArea.classList.remove('hidden');
}

// Update statistics
function updateStatistics(data) {
    document.getElementById('successCount').textContent = data.summary.successful;
    document.getElementById('failedCount').textContent = data.summary.failed;
    
    // Calculate average response time
    const successfulSites = Object.values(data.results).filter(r => r.success);
    if (successfulSites.length > 0) {
        const avgTime = successfulSites.reduce((sum, site) => sum + site.response_time_ms, 0) / successfulSites.length;
        document.getElementById('avgResponseTime').textContent = Math.round(avgTime) + 'ms';
    }
}

// Show vessel search modal
function showVesselSearch() {
    document.getElementById('vesselSearchModal').classList.remove('hidden');
}

// Hide vessel search modal
function hideVesselSearch() {
    document.getElementById('vesselSearchModal').classList.add('hidden');
}

// Handle vessel search form
document.getElementById('vesselSearchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const vesselName = document.getElementById('vesselName').value;
    const voyage = document.getElementById('voyage').value;
    
    hideVesselSearch();
    searchVessel(vesselName, voyage);
});

// Show loading state
function showLoading(message) {
    const resultsArea = document.getElementById('resultsArea');
    const resultsContent = document.getElementById('resultsContent');
    
    resultsContent.innerHTML = `
        <div class="text-center py-8">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-600">${message}</p>
        </div>
    `;
    resultsArea.classList.remove('hidden');
}

// Show error message
function showError(message) {
    const resultsArea = document.getElementById('resultsArea');
    const resultsContent = document.getElementById('resultsContent');
    
    resultsContent.innerHTML = `
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-center">
                <span class="text-red-600 text-xl mr-2">❌</span>
                <div>
                    <h3 class="text-red-800 font-bold">Error</h3>
                    <p class="text-red-700">${message}</p>
                </div>
            </div>
        </div>
    `;
    resultsArea.classList.remove('hidden');
}

// Show site details
function showSiteDetails(siteKey) {
    fetch(`{{ url('vessel-tracking/config') }}`)
        .then(response => response.json())
        .then(data => {
            const site = data.sites[siteKey];
            alert(`Site: ${site.name}\nURL: ${site.url}\nType: ${site.type}\nMethod: ${site.method}`);
        });
}
</script>
@endsection
