<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vessel Tracking Test - CS Shipping LCB</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">🚢 Vessel Tracking Test</h1>
                <p class="text-gray-600">CS Shipping LCB - Port Terminal Integration Test</p>
                <p class="text-sm text-gray-500 mt-2">Testing vessel ETA retrieval from 6 terminal websites</p>
            </div>

            <!-- Test Controls -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">Run Vessel Test</h2>
                        <p class="text-gray-600">This will check all 6 terminals for vessel ETA data</p>
                    </div>
                    <button id="runTest" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                        Run Test
                    </button>
                </div>
            </div>

            <!-- Test Results -->
            <div id="results" class="hidden">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">📊 Test Results</h2>
                    <div id="summary" class="mb-6"></div>
                    <div id="detailed-results"></div>
                </div>
            </div>

            <!-- Terminal Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🌐 Terminal Information</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-blue-600">Terminal C1C2</h3>
                        <p class="text-sm text-gray-600">Hutchison Ports</p>
                        <p class="text-xs text-gray-500 mt-1">Vessel: WAN HAI 517</p>
                        <p class="text-xs text-gray-400">Voyage: S093</p>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-blue-600">Terminal B4</h3>
                        <p class="text-sm text-gray-600">TIPS</p>
                        <p class="text-xs text-gray-500 mt-1">Vessel: SRI SUREE</p>
                        <p class="text-xs text-gray-400">Voyage: V.25080S</p>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-blue-600">Terminal B5/C3</h3>
                        <p class="text-sm text-gray-600">LCIT</p>
                        <p class="text-xs text-gray-500 mt-1">Vessel: ASL QINGDAO</p>
                        <p class="text-xs text-gray-400">Voyage: V.2508S</p>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-blue-600">Terminal B3</h3>
                        <p class="text-sm text-gray-600">ESCO</p>
                        <p class="text-xs text-gray-500 mt-1">Vessel: CUL NANSHA</p>
                        <p class="text-xs text-gray-400">Voyage: V. 2528S</p>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-blue-600">Terminal A0/B1</h3>
                        <p class="text-sm text-gray-600">LCB1</p>
                        <p class="text-xs text-gray-500 mt-1">Vessel: MARSA PRIDE</p>
                        <p class="text-xs text-gray-400">Voyage: 528S</p>
                    </div>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-blue-600">Terminal B2</h3>
                        <p class="text-sm text-gray-600">ECTT</p>
                        <p class="text-xs text-gray-500 mt-1">Vessel: EVER BUILD</p>
                        <p class="text-xs text-gray-400">Voyage: V.0794-074S</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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
                            `;
                            
                            if (result.raw_data) {
                                const preview = result.raw_data.substring(0, 200) + '...';
                                detailedHtml += `
                                    <details class="mt-2">
                                        <summary class="text-xs text-blue-600 cursor-pointer">View Raw Data Preview</summary>
                                        <pre class="text-xs text-gray-700 mt-1 bg-gray-100 p-2 rounded overflow-x-auto">${preview}</pre>
                                    </details>
                                `;
                            }
                            
                            detailedHtml += '</div>';
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
                button.innerHTML = 'Run Test Again';
                button.disabled = false;
            }
        });
    </script>
</body>
</html>
