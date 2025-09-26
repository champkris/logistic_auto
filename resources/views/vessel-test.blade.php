<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            🚢 Vessel Tracking Test
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">🚢 Vessel Tracking Test</h1>
                <p class="text-gray-600">CS Shipping LCB - Port Terminal Integration Test</p>
                <p class="text-sm text-gray-500 mt-2">Test vessel ETA retrieval from port terminals</p>
            </div>


            <!-- Single Vessel Test Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🎯 Test Single Vessel</h2>
                <p class="text-gray-600 mb-4">Test a specific vessel on a selected terminal</p>

                <form id="singleVesselForm" class="space-y-4">
                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Vessel Name -->
                        <div>
                            <label for="vesselName" class="block text-sm font-medium text-gray-700 mb-2">
                                Vessel Name *
                            </label>
                            <input
                                type="text"
                                id="vesselName"
                                name="vesselName"
                                placeholder="e.g., WAN HAI 517, MARSA PRIDE"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required
                            >
                            <p class="text-xs text-gray-500 mt-1">Enter the vessel name as it appears in the terminal</p>
                        </div>

                        <!-- Voyage Code -->
                        <div>
                            <label for="voyageCode" class="block text-sm font-medium text-gray-700 mb-2">
                                Voyage Code
                            </label>
                            <input
                                type="text"
                                id="voyageCode"
                                name="voyageCode"
                                placeholder="e.g., S093, V.25080S (optional)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                            <p class="text-xs text-gray-500 mt-1">Optional: Include voyage code if available</p>
                        </div>
                    </div>

                    <!-- Terminal Selection -->
                    <div>
                        <label for="terminal" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Terminal *
                        </label>
                        <select
                            id="terminal"
                            name="terminal"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                        >
                            <option value="">Choose a terminal...</option>
                            <option value="C1C2">C1C2 - Hutchison Ports</option>
                            <option value="B4">B4 - TIPS</option>
                            <option value="B5C3">B5C3 - LCIT</option>
                            <option value="B3">B3 - ESCO</option>
                            <option value="A0B1">A0B1 - LCB1</option>
                            <option value="B2">B2 - ShipmentLink (ECTT)</option>
                            <option value="SIAM">SIAM - Siam Commercial (n8n LINE)</option>
                            <option value="KERRY">KERRY - Kerry Logistics</option>
                        </select>

                        <!-- Terminal URLs Information -->
                        <div class="mt-3 p-3 bg-gray-50 rounded-md">
                            <p class="text-sm font-medium text-gray-700 mb-2">🌐 Terminal URLs for automated checking:</p>
                            @php
                                $portTerminals = \App\Models\DropdownSetting::getFieldOptionsWithUrls('port_terminal');
                            @endphp
                            <div class="space-y-1">
                                @foreach($portTerminals as $terminal)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-medium text-gray-600">{{ $terminal['label'] }}:</span>
                                        @if($terminal['url'])
                                            <a href="{{ $terminal['url'] }}" target="_blank"
                                               class="text-blue-600 hover:text-blue-800 truncate max-w-xs">
                                                {{ $terminal['url'] }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">No URL configured</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            id="testSingleVessel"
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition flex items-center space-x-2"
                        >
                            <span>🔍</span>
                            <span>Test This Vessel</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Single Vessel Results -->
            <div id="singleResults" class="hidden">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">📋 Single Vessel Test Results</h2>
                    <div id="singleVesselResult"></div>
                </div>
            </div>

            <!-- Test All Terminals -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">🌐 Test All Terminals</h2>
                        <p class="text-gray-600">This will check all 8 terminals with their default test vessels</p>
                    </div>
                    <button id="runTest" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                        Run Full Test
                    </button>
                </div>
            </div>

            <!-- Full Test Results -->
            <div id="results" class="hidden">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">📊 Full Test Results</h2>
                    <div id="summary" class="mb-6"></div>
                    <div id="detailed-results"></div>
                </div>
            </div>

            <!-- Terminal Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🌐 Terminal Information</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="border rounded-lg p-4 hover:bg-blue-50 transition">
                        <h3 class="font-semibold text-blue-600">Terminal C1C2</h3>
                        <p class="text-sm text-gray-600">Hutchison Ports</p>
                        <p class="text-xs text-gray-500 mt-1">🚢 Default: WAN HAI 517</p>
                        <p class="text-xs text-gray-400">🧭 Voyage: S093</p>
                    </div>
                    <div class="border rounded-lg p-4 hover:bg-blue-50 transition">
                        <h3 class="font-semibold text-blue-600">Terminal B4</h3>
                        <p class="text-sm text-gray-600">TIPS</p>
                        <p class="text-xs text-gray-500 mt-1">🚢 Default: SRI SUREE</p>
                        <p class="text-xs text-gray-400">🧭 Voyage: V.25080S</p>
                    </div>
                    <div class="border rounded-lg p-4 hover:bg-blue-50 transition">
                        <h3 class="font-semibold text-blue-600">Terminal B5/C3</h3>
                        <p class="text-sm text-gray-600">LCIT</p>
                        <p class="text-xs text-gray-500 mt-1">🚢 Default: SKY SUNSHINE</p>
                        <p class="text-xs text-gray-400">🧭 Voyage: V.2513S</p>
                    </div>
                    <div class="border rounded-lg p-4 hover:bg-blue-50 transition">
                        <h3 class="font-semibold text-blue-600">Terminal B3</h3>
                        <p class="text-sm text-gray-600">ESCO</p>
                        <p class="text-xs text-gray-500 mt-1">🚢 Default: CUL NANSHA</p>
                        <p class="text-xs text-gray-400">🧭 Voyage: V. 2528S</p>
                    </div>
                    <div class="border rounded-lg p-4 hover:bg-blue-50 transition">
                        <h3 class="font-semibold text-blue-600">Terminal A0/B1</h3>
                        <p class="text-sm text-gray-600">LCB1</p>
                        <p class="text-xs text-gray-500 mt-1">🚢 Default: MARSA PRIDE</p>
                        <p class="text-xs text-gray-400">🧭 Voyage: 528S</p>
                    </div>
                    <div class="border rounded-lg p-4 hover:bg-blue-50 transition">
                        <h3 class="font-semibold text-blue-600">Terminal B2</h3>
                        <p class="text-sm text-gray-600">ShipmentLink (ECTT)</p>
                        <p class="text-xs text-gray-500 mt-1">🚢 Default: EVER BASIS</p>
                        <p class="text-xs text-gray-400">🧭 Voyage: 0813-068S</p>
                    </div>
                    <div class="border rounded-lg p-4 hover:bg-blue-50 transition">
                        <h3 class="font-semibold text-blue-600">Terminal SIAM</h3>
                        <p class="text-sm text-gray-600">Siam Commercial (n8n LINE)</p>
                        <p class="text-xs text-gray-500 mt-1">🚢 Default: SAMPLE VESSEL</p>
                        <p class="text-xs text-gray-400">🧭 Voyage: V.001S</p>
                    </div>
                    <div class="border rounded-lg p-4 hover:bg-blue-50 transition">
                        <h3 class="font-semibold text-blue-600">Terminal KERRY</h3>
                        <p class="text-sm text-gray-600">Kerry Logistics</p>
                        <p class="text-xs text-gray-500 mt-1">🚢 Default: BUXMELODY</p>
                        <p class="text-xs text-gray-400">🧭 Voyage: 230N</p>
                    </div>
                </div>

                <div class="mt-6 bg-blue-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">💡 Usage Tips:</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Use the single vessel test to check specific vessels you're tracking</li>
                        <li>• Voyage codes help improve accuracy but are optional</li>
                        <li>• Try the full test to see overall system performance</li>
                        <li>• Each terminal has different data formats and search methods</li>
                        <li>• Results show whether vessel was found and if ETA data is available</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom CSS for loading animation -->
    <style>
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <!-- JavaScript for vessel testing functionality -->
    <script>
        // Single Vessel Test
        document.getElementById('singleVesselForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const button = document.getElementById('testSingleVessel');
            const results = document.getElementById('singleResults');
            const resultContainer = document.getElementById('singleVesselResult');

            const vesselName = document.getElementById('vesselName').value.trim();
            const voyageCode = document.getElementById('voyageCode').value.trim();
            const terminal = document.getElementById('terminal').value;

            if (!vesselName || !terminal) {
                alert('Please fill in vessel name and select a terminal');
                return;
            }

            // Show loading state
            button.innerHTML = '<span class="loading"></span> Testing...';
            button.disabled = true;
            results.classList.remove('hidden');
            resultContainer.innerHTML = '<p class="text-blue-600">🔄 Testing vessel on selected terminal...</p>';

            try {
                const response = await fetch('/vessel-test-public/single', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        vessel_name: vesselName,
                        voyage_code: voyageCode,
                        terminal: terminal
                    })
                });

                const data = await response.json();

                if (data.success) {
                    const result = data.result;
                    const statusIcon = result.success ? '✅' : '❌';
                    const vesselIcon = result.vessel_found ? '🚢' : (result.voyage_found ? '🧭' : '🔍');
                    const statusColor = result.success ? 'text-green-600' : 'text-red-600';

                    let resultHtml = `
                        <div class="border rounded-lg p-4 ${result.success ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'}">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-semibold ${statusColor} text-lg">
                                        ${statusIcon} Terminal ${terminal} - ${result.terminal}
                                    </h3>
                                    <p class="text-gray-700">${vesselIcon} ${vesselName}${voyageCode ? ` (${voyageCode})` : ''}</p>
                                </div>
                                <span class="text-sm text-gray-500">${result.checked_at}</span>
                            </div>
                    `;

                    if (result.success) {
                        if (result.vessel_found || result.full_name_found) {
                            resultHtml += `
                                <div class="bg-white p-4 rounded border-l-4 border-green-500">
                                    <p class="font-medium text-green-800 mb-2">🎉 Vessel Found Successfully!</p>
                                    <div class="grid md:grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <span class="font-medium">Search Results:</span>
                                            <ul class="mt-1 space-y-1 text-green-700">
                                                <li>${result.vessel_found ? '✅ Vessel Name: Found' : '❌ Vessel Name: Not found'}</li>
                                                <li>${result.voyage_found ? '✅ Voyage Code: Found' : '❌ Voyage Code: Not found'}</li>
                                                <li>🎯 Search Method: ${result.search_method || 'Unknown'}</li>
                                            </ul>
                                        </div>
                                        <div>
                                            <span class="font-medium">ETA Information:</span>
                                            ${result.eta ?
                                                `<p class="text-green-700 font-medium mt-1">🕒 ETA: ${result.eta}</p>` :
                                                '<p class="text-yellow-600 mt-1">⚠️ ETA: Not available</p>'
                                            }
                                        </div>
                                    </div>
                                    <div class="mt-3 text-xs text-gray-600">
                                        📊 Response Size: ${Number(result.html_size || 0).toLocaleString()} bytes
                                    </div>
                            `;

                            if (result.raw_data) {
                                const preview = result.raw_data.substring(0, 300) + '...';
                                resultHtml += `
                                    <details class="mt-3">
                                        <summary class="text-sm text-blue-600 cursor-pointer hover:text-blue-800">📄 View Raw Data Preview</summary>
                                        <pre class="text-xs text-gray-700 mt-2 bg-gray-100 p-3 rounded overflow-x-auto">${preview}</pre>
                                    </details>
                                `;
                            }

                            resultHtml += '</div>';
                        } else {
                            resultHtml += `
                                <div class="bg-yellow-50 p-4 rounded border-l-4 border-yellow-500">
                                    <p class="text-yellow-800 font-medium">⚠️ Vessel not found in schedule</p>
                                    <div class="text-sm text-yellow-700 mt-2">
                                        <p>The terminal was accessible, but the specified vessel was not found in the current schedule.</p>
                                        <div class="mt-2">
                                            <span class="font-medium">Search Details:</span>
                                            <ul class="mt-1 space-y-1">
                                                <li>📍 Vessel Name: ${result.vessel_found ? 'Found' : 'Not found'}</li>
                                                <li>🧭 Voyage Code: ${result.voyage_found ? 'Found' : 'Not found'}</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-600 mt-3">📊 Response Size: ${Number(result.html_size || 0).toLocaleString()} bytes</p>
                                </div>
                            `;
                        }
                    } else {
                        resultHtml += `
                            <div class="bg-red-50 p-4 rounded border-l-4 border-red-500">
                                <p class="text-red-800 font-medium">❌ Test Failed</p>
                                <p class="text-red-700 mt-2">${result.error || 'Unknown error occurred'}</p>
                                <div class="mt-3 text-sm text-red-600">
                                    <p>Possible causes:</p>
                                    <ul class="mt-1 space-y-1 ml-4">
                                        <li>• Terminal website is down or inaccessible</li>
                                        <li>• Network connectivity issues</li>
                                        <li>• Website structure has changed</li>
                                        <li>• Vessel name format doesn't match terminal requirements</li>
                                    </ul>
                                </div>
                            </div>
                        `;
                    }

                    resultHtml += '</div>';

                    // Add recommendations
                    resultHtml += `
                        <div class="mt-4 bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-blue-800 mb-2">💡 Recommendations:</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                    `;

                    if (result.success && (result.vessel_found || result.full_name_found)) {
                        resultHtml += '<li>🎉 Great! This vessel can be tracked automatically on this terminal</li>';
                        if (!result.eta) {
                            resultHtml += '<li>⚠️ Consider improving ETA extraction for this terminal</li>';
                        }
                    } else if (result.success) {
                        resultHtml += '<li>🔍 Try different vessel name formats or check if vessel is actually scheduled</li>';
                        resultHtml += '<li>📅 Verify the vessel is expected at this specific terminal</li>';
                    } else {
                        resultHtml += '<li>🔧 This terminal may need different automation approach</li>';
                        resultHtml += '<li>🌐 Check if terminal website is accessible manually</li>';
                    }

                    resultHtml += '</ul></div>';

                    resultContainer.innerHTML = resultHtml;
                } else {
                    resultContainer.innerHTML = `
                        <div class="bg-red-50 p-4 rounded-lg border-l-4 border-red-500">
                            <p class="text-red-800 font-medium">❌ Test Failed</p>
                            <p class="text-red-700 mt-2">${data.error || 'Unknown error occurred'}</p>
                        </div>
                    `;
                }

            } catch (error) {
                resultContainer.innerHTML = `
                    <div class="bg-red-50 p-4 rounded-lg border-l-4 border-red-500">
                        <p class="text-red-800 font-medium">❌ Connection Error</p>
                        <p class="text-red-700 mt-2">Failed to connect to test service: ${error.message}</p>
                    </div>
                `;
            } finally {
                button.innerHTML = '<span>🔍</span><span>Test This Vessel</span>';
                button.disabled = false;
            }
        });

        // Full Test (continues with rest of JavaScript...)
        document.getElementById('runTest').addEventListener('click', async function() {
            const button = this;
            const results = document.getElementById('results');
            const summary = document.getElementById('summary');
            const detailedResults = document.getElementById('detailed-results');

            // Show loading state
            button.innerHTML = '<span class="loading"></span> Testing...';
            button.disabled = true;
            results.classList.remove('hidden');
            summary.innerHTML = '<p class="text-blue-600">🔄 Running tests on all terminals...</p>';
            detailedResults.innerHTML = '';

            try {
                const response = await fetch('/vessel-test/run');
                const data = await response.json();

                // Display summary
                const summaryStats = data.summary;
                const successRate = ((summaryStats.successful / summaryStats.total) * 100).toFixed(1);
                const foundRate = ((summaryStats.found / summaryStats.total) * 100).toFixed(1);
                const etaRate = ((summaryStats.with_eta / summaryStats.total) * 100).toFixed(1);

                summary.innerHTML = `
                    <div class="grid md:grid-cols-4 gap-4 mb-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">${summaryStats.total}</div>
                            <div class="text-sm text-gray-600">Total Terminals</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">${summaryStats.successful}</div>
                            <div class="text-sm text-gray-600">Successful (${successRate}%)</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">${summaryStats.found}</div>
                            <div class="text-sm text-gray-600">Vessels Found (${foundRate}%)</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">${summaryStats.with_eta}</div>
                            <div class="text-sm text-gray-600">ETAs Extracted (${etaRate}%)</div>
                        </div>
                    </div>
                `;

                // Display detailed results
                let detailedHtml = '<div class="space-y-4">';

                for (const [terminalCode, result] of Object.entries(data.results)) {
                    const statusIcon = result.success ? '✅' : '❌';
                    const vesselIcon = result.vessel_found ? '🚢' : (result.voyage_found ? '🧭' : '🔍');
                    const statusColor = result.success ? 'text-green-600' : 'text-red-600';

                    detailedHtml += `
                        <div class="border rounded-lg p-4 ${result.success ? 'border-green-200' : 'border-red-200'}">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-semibold ${statusColor}">
                                        ${statusIcon} Terminal ${terminalCode} - ${result.terminal}
                                    </h3>
                                    <p class="text-sm text-gray-600">${vesselIcon} ${result.vessel_name || result.vessel_full}</p>
                                    ${result.voyage_code ? `<p class="text-xs text-gray-500">🧭 Voyage: ${result.voyage_code}</p>` : ''}
                                </div>
                                <span class="text-xs text-gray-500">${result.checked_at}</span>
                            </div>
                    `;

                    if (result.success) {
                        if (result.vessel_found || result.full_name_found) {
                            detailedHtml += `
                                <div class="bg-green-50 p-3 rounded mt-2">
                                    <p class="text-sm font-medium text-green-800">Vessel Found!</p>
                                    <div class="text-xs text-green-600 mt-1">
                                        ${result.vessel_found ? '📍 Vessel Name: Found' : '📍 Vessel Name: Not found'}
                                        ${result.voyage_found ? ' | 🧭 Voyage: Found' : ' | 🧭 Voyage: Not found'}
                                        ${result.search_method ? ` | 🎯 Method: ${result.search_method}` : ''}
                                    </div>
                                    ${result.eta ? `<p class="text-sm text-green-600">🕒 ETA: ${result.eta}</p>` : '<p class="text-sm text-yellow-600">⚠️ ETA: Not found</p>'}
                                    <p class="text-xs text-gray-500">HTML Size: ${Number(result.html_size).toLocaleString()} bytes</p>
                                </div>
                            `;
                        } else {
                            detailedHtml += `
                                <div class="bg-yellow-50 p-3 rounded mt-2">
                                    <p class="text-sm text-yellow-800">Vessel not found in schedule</p>
                                    <div class="text-xs text-yellow-600 mt-1">
                                        📍 Vessel Name: Not found | 🧭 Voyage: ${result.voyage_found ? 'Found' : 'Not found'}
                                    </div>
                                    <p class="text-xs text-gray-500">HTML Size: ${Number(result.html_size).toLocaleString()} bytes</p>
                                </div>
                            `;
                        }
                    } else {
                        detailedHtml += `
                            <div class="bg-red-50 p-3 rounded mt-2">
                                <p class="text-sm text-red-800">Error: ${result.error}</p>
                            </div>
                        `;
                    }

                    detailedHtml += '</div>';
                }

                detailedHtml += '</div>';
                detailedResults.innerHTML = detailedHtml;

                // Show recommendations
                let recommendations = '<div class="mt-6 bg-blue-50 p-4 rounded-lg"><h3 class="font-semibold text-blue-800 mb-2">💡 Recommendations:</h3><ul class="text-sm text-blue-700 space-y-1">';

                if (etaRate > 50) {
                    recommendations += '<li>🎉 Great success! Vessel tracking automation is highly viable</li>';
                } else if (foundRate > 50) {
                    recommendations += '<li>⚠️ Vessel detection works but ETA parsing needs improvement</li>';
                } else {
                    recommendations += '<li>❌ Consider alternative approaches for these specific terminals</li>';
                }

                recommendations += `
                    <li>Focus development on terminals with ${successRate}% success rate</li>
                    <li>Develop specific HTML parsers for each terminal structure</li>
                    <li>Implement retry logic and error handling</li>
                    <li>Consider API integrations where available</li>
                `;

                recommendations += '</ul></div>';
                summary.innerHTML += recommendations;

            } catch (error) {
                summary.innerHTML = `<p class="text-red-600">❌ Error running test: ${error.message}</p>`;
            } finally {
                button.innerHTML = 'Run Full Test Again';
                button.disabled = false;
            }
        });
    </script>
</x-app-layout>