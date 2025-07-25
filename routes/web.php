<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

Route::get('/', App\Livewire\Dashboard::class)->name('dashboard');

Route::get('/welcome', function () {
    return view('welcome');
});

// Vessel Tracking Test Routes
Route::get('/vessel-test', function () {
    return view('vessel-test');
});

Route::get('/vessel-test/run', function () {
    // IMPROVED: Helper function to extract ETA from table structure with vessel + voyage validation
    $extractETAFromTable = function($html, $vesselName, $voyageCode = null) {
        // Find all table rows and check each one precisely
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $html, $allRows)) {
            foreach ($allRows[0] as $row) {
                // Check if this row contains our vessel name
                if (stripos($row, $vesselName) === false) {
                    continue;
                }
                
                // Extract all cells from this specific row
                if (preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cellMatches)) {
                    $cells = $cellMatches[1];
                    $cleanCells = [];
                    
                    // Clean up all cells
                    foreach ($cells as $cellIndex => $cell) {
                        $cellText = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML401, 'UTF-8');
                        $cellText = trim(preg_replace('/\s+/', ' ', $cellText));
                        $cleanCells[$cellIndex] = $cellText;
                    }
                    
                    // Validate this is the correct vessel by checking vessel name + voyage code
                    $isCorrectVessel = false;
                    
                    // Find vessel name cell
                    $vesselCellFound = false;
                    foreach ($cleanCells as $cellText) {
                        if (stripos($cellText, $vesselName) !== false) {
                            $vesselCellFound = true;
                            break;
                        }
                    }
                    
                    // If voyage code provided, validate it's in the same row
                    if ($voyageCode && $vesselCellFound) {
                        // Create voyage variations to try
                        $voyageVariations = [$voyageCode];
                        if (preg_match('/^([A-Z]+)\.?(.+)$/', $voyageCode, $matches)) {
                            $voyageVariations[] = $matches[2]; // Remove prefix like "V."
                        }
                        $voyageVariations[] = str_replace('.', '', $voyageCode); // Remove dots
                        
                        foreach ($cleanCells as $cellText) {
                            foreach ($voyageVariations as $variation) {
                                if (stripos($cellText, $variation) !== false) {
                                    $isCorrectVessel = true;
                                    break 2;
                                }
                            }
                        }
                    } else {
                        // If no voyage code, accept any row with vessel name
                        $isCorrectVessel = $vesselCellFound;
                    }
                    
                    if ($isCorrectVessel) {
                        // Extract ETA from this specific row
                        // Prioritize cells that look like ETA (with time format)
                        $bestETA = null;
                        $fallbackETA = null;
                        
                        $datePatterns = [
                            '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/', // DD/MM/YYYY HH:MM
                            '/(\d{1,2}\/\d{1,2}\/\d{4})/',                    // DD/MM/YYYY only
                        ];
                        
                        foreach ($cleanCells as $cellText) {
                            foreach ($datePatterns as $pattern) {
                                if (preg_match($pattern, $cellText, $matches)) {
                                    try {
                                        // Handle both full datetime and date-only formats
                                        if (isset($matches[2])) {
                                            $dateStr = $matches[1] . ' ' . $matches[2]; // Full datetime
                                        } else {
                                            $dateStr = $matches[1] . ' 00:00'; // Date only
                                        }
                                        
                                        $eta = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $dateStr);
                                        $formattedETA = $eta->format('Y-m-d H:i:s');
                                        
                                        if (isset($matches[2])) {
                                            // Prefer dates with specific times
                                            if (!$bestETA) {
                                                $bestETA = $formattedETA;
                                            }
                                        } else {
                                            // Keep as fallback if no better ETA found
                                            if (!$fallbackETA) {
                                                $fallbackETA = $formattedETA;
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                            }
                        }
                        
                        return $bestETA ?: $fallbackETA;
                    }
                }
            }
        }
        
        return null;
    };

    $terminals = [
        'C1C2' => [
            'name' => 'Hutchison Ports',
            'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17:6927160550678:::::',
            'vessel_full' => 'WAN HAI 517 S093',
            'vessel_name' => 'WAN HAI 517',
            'voyage_code' => 'S093',
        ],
        'B4' => [
            'name' => 'TIPS',
            'url' => 'https://www.tips.co.th/container/shipSched/List',
            'vessel_full' => 'SRI SUREE V.25080S',
            'vessel_name' => 'SRI SUREE',
            'voyage_code' => 'V.25080S',
        ],
        'B5C3' => [
            'name' => 'LCIT',
            'url' => 'https://www.lcit.com/home',
            'vessel_full' => 'ASL QINGDAO V.2508S',
            'vessel_name' => 'ASL QINGDAO',
            'voyage_code' => 'V.2508S',
        ],
        'B3' => [
            'name' => 'ESCO',
            'url' => 'https://service.esco.co.th/BerthSchedule',
            'vessel_full' => 'CUL NANSHA V. 2528S',
            'vessel_name' => 'CUL NANSHA',
            'voyage_code' => 'V. 2528S',
        ],
        'A0B1' => [
            'name' => 'LCB1',
            'url' => 'https://www.lcb1.com/BerthSchedule',
            'vessel_full' => 'MARSA PRIDE 528S',
            'vessel_name' => 'MARSA PRIDE',
            'voyage_code' => '528S',
            'note' => 'JavaScript-dependent - requires vessel selection and search',
            'status' => 'requires_js'
        ],
        'B2' => [
            'name' => 'ECTT',
            'url' => 'https://www.ectt.co.th/cookie-policy/',
            'vessel_full' => 'EVER BUILD V.0794-074S',
            'vessel_name' => 'EVER BUILD',
            'voyage_code' => 'V.0794-074S',
        ]
    ];

    $results = [];
    
    foreach ($terminals as $terminalCode => $config) {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($config['url']);

            if ($response->successful()) {
                $html = $response->body();
                
                // Search for vessel name and voyage code separately with variations
                $vesselName = $config['vessel_name'];
                $voyageCode = $config['voyage_code'];
                $fullVesselName = $config['vessel_full'];
                
                $vesselNameFound = str_contains(strtoupper($html), strtoupper($vesselName));
                
                // Try multiple voyage code variations (IMPROVED for handling spaces)
                $voyageCodeFound = false;
                $voyageVariations = [$voyageCode];
                
                // Add variations for voyage codes that might have prefixes
                if (preg_match('/^([A-Z]+)\.?\s*(.+)$/', $voyageCode, $matches)) {
                    // For "V. 2528S" -> also try "2528S" (remove prefix with space)
                    $voyageVariations[] = $matches[2];
                }
                if (preg_match('/^(.+?)([A-Z\d]+)$/', $voyageCode, $matches)) {
                    // For "V. 2528S" -> also try "V2528S" (remove dots and spaces)
                    $noPrefixVersion = str_replace(['.', ' '], '', $voyageCode);
                    if (!in_array($noPrefixVersion, $voyageVariations)) {
                        $voyageVariations[] = $noPrefixVersion;
                    }
                }
                
                // Additional handling for space-separated prefixes like "V. 2528S"
                if (str_contains($voyageCode, ' ')) {
                    $parts = explode(' ', $voyageCode);
                    if (count($parts) >= 2) {
                        // Take the last part (the actual voyage number)
                        $lastPart = end($parts);
                        if (!in_array($lastPart, $voyageVariations)) {
                            $voyageVariations[] = $lastPart;
                        }
                    }
                }
                
                foreach ($voyageVariations as $variation) {
                    if (str_contains(strtoupper($html), strtoupper($variation))) {
                        $voyageCodeFound = true;
                        break;
                    }
                }
                
                $fullNameFound = str_contains(strtoupper($html), strtoupper($fullVesselName));
                
                $vesselFound = $vesselNameFound || $fullNameFound;
                
                $results[$terminalCode] = [
                    'terminal' => $config['name'],
                    'vessel_name' => $vesselName,
                    'voyage_code' => $voyageCode,
                    'vessel_full' => $fullVesselName,
                    'success' => true,
                    'vessel_found' => $vesselNameFound,
                    'voyage_found' => $voyageCodeFound,
                    'full_name_found' => $fullNameFound,
                    'search_method' => $vesselNameFound && $voyageCodeFound ? 'both_found' : 
                                    ($vesselNameFound ? 'vessel_only' : 
                                    ($voyageCodeFound ? 'voyage_only' : 
                                    ($fullNameFound ? 'full_name' : 'none'))),
                    'html_size' => strlen($html),
                    'status_code' => $response->status(),
                    'checked_at' => now()->format('Y-m-d H:i:s')
                ];
                
                if ($vesselFound) {
                    // Check if this is a JavaScript-dependent terminal
                    if (isset($config['status']) && $config['status'] === 'requires_js') {
                        $results[$terminalCode]['eta'] = null;
                        $results[$terminalCode]['message'] = 'Terminal requires JavaScript interaction - vessel found in dropdown but schedule data not accessible';
                        $results[$terminalCode]['requires_browser'] = true;
                    } else {
                        // Try to extract ETA from table structure first (IMPROVED - now validates voyage code too)
                        $eta = $extractETAFromTable($html, $vesselNameFound ? $vesselName : $fullVesselName, $voyageCode);
                        
                        if (!$eta) {
                            // Fallback: Try to extract vessel section for ETA
                            $searchTerm = $vesselNameFound ? $vesselName : $fullVesselName;
                            $pos = stripos($html, $searchTerm);
                            if ($pos !== false) {
                                $start = max(0, $pos - 500);
                                $vesselSection = substr($html, $start, 1000);
                                $results[$terminalCode]['raw_data'] = strip_tags($vesselSection);
                                
                                // Try to find ETA in vessel section
                                if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/', $vesselSection, $matches)) {
                                    try {
                                        $eta = \Carbon\Carbon::createFromFormat('d/m/Y H:i', $matches[0])->format('Y-m-d H:i:s');
                                    } catch (\Exception $e) {
                                        $eta = $matches[0]; // Use raw format if parsing fails
                                    }
                                }
                            }
                        }
                        
                        if ($eta) {
                            $results[$terminalCode]['eta'] = $eta;
                        }
                    }
                }
            } else {
                $results[$terminalCode] = [
                    'terminal' => $config['name'],
                    'vessel_name' => $config['vessel_name'],
                    'voyage_code' => $config['voyage_code'],
                    'vessel_full' => $config['vessel_full'],
                    'success' => false,
                    'error' => 'HTTP ' . $response->status(),
                    'checked_at' => now()->format('Y-m-d H:i:s')
                ];
            }
        } catch (\Exception $e) {
            $results[$terminalCode] = [
                'terminal' => $config['name'],
                'vessel_name' => $config['vessel_name'],
                'voyage_code' => $config['voyage_code'],
                'vessel_full' => $config['vessel_full'],
                'success' => false,
                'error' => $e->getMessage(),
                'checked_at' => now()->format('Y-m-d H:i:s')
            ];
        }
        
        // Rate limiting - be respectful
        if (count($results) < count($terminals)) {
            sleep(2);
        }
    }

    return response()->json([
        'results' => $results,
        'summary' => [
            'total' => count($results),
            'successful' => collect($results)->where('success', true)->count(),
            'found' => collect($results)->where('vessel_found', true)->count(),
            'with_eta' => collect($results)->whereNotNull('eta')->count(),
        ]
    ]);
});
