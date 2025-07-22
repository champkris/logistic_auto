<?php

require_once __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;

echo "🔧 FIXED TIPS ETA Extractor\n";
echo "═══════════════════════════════════════\n";
echo "Testing improved table row matching...\n\n";

$url = 'https://www.tips.co.th/container/shipSched/List';
$vesselName = 'SRI SUREE';
$voyageCode = '25080S'; // This is what we should match in the I/B Vyg column

echo "📍 URL: {$url}\n";
echo "🚢 Looking for: {$vesselName}\n";
echo "🧭 Voyage Code: {$voyageCode}\n\n";

echo "⏳ Fetching HTML...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($html !== false && $httpCode == 200) {
    echo "✅ Successfully fetched HTML\n\n";
    
    // IMPROVED: Find the exact table row for the vessel using better regex
    $vesselFound = false;
    $extractedETA = null;
    
    // Method 1: More precise table row extraction
    echo "🔍 Method 1: Precise Table Row Extraction\n";
    echo "──────────────────────────────────────────────\n";
    
    if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $html, $allRows)) {
        echo "Found " . count($allRows[0]) . " table rows to check\n\n";
        
        foreach ($allRows[0] as $index => $row) {
            // Check if this row contains our vessel name
            if (stripos($row, $vesselName) !== false) {
                echo "✅ Found potential SRI SUREE row #{$index}\n";
                
                // Extract all cells from this specific row
                if (preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cellMatches)) {
                    $cells = $cellMatches[1];
                    $cleanCells = [];
                    
                    echo "📋 Row #{$index} Analysis:\n";
                    foreach ($cells as $cellIndex => $cell) {
                        $cellText = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML401, 'UTF-8');
                        $cellText = trim(preg_replace('/\s+/', ' ', $cellText));
                        $cleanCells[$cellIndex] = $cellText;
                        echo "  Cell {$cellIndex}: '{$cellText}'\n";
                    }
                    
                    // Validate this is the correct vessel by checking:
                    // 1. First cell should contain SRI SUREE
                    // 2. I/B Vyg cell (usually index 3 or 4) should contain voyage code
                    $isCorrectVessel = false;
                    $vesselCellIndex = -1;
                    $voyageCellIndex = -1;
                    
                    // Find vessel name cell
                    foreach ($cleanCells as $cellIndex => $cellText) {
                        if (stripos($cellText, $vesselName) !== false) {
                            $vesselCellIndex = $cellIndex;
                            break;
                        }
                    }
                    
                    // Find voyage code cell (should be near the vessel name)
                    foreach ($cleanCells as $cellIndex => $cellText) {
                        if (stripos($cellText, $voyageCode) !== false) {
                            $voyageCellIndex = $cellIndex;
                            $isCorrectVessel = true;
                            break;
                        }
                    }
                    
                    echo "  🎯 Vessel found at cell: {$vesselCellIndex}\n";
                    echo "  🧭 Voyage found at cell: {$voyageCellIndex}\n";
                    echo "  ✅ Is correct vessel: " . ($isCorrectVessel ? "YES" : "NO") . "\n\n";
                    
                    if ($isCorrectVessel) {
                        $vesselFound = true;
                        
                        // Now extract ETA from this specific row
                        echo "🕐 ETA Extraction from Correct Row:\n";
                        echo "────────────────────────────────────\n";
                        
                        $datePatterns = [
                            '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/', // DD/MM/YYYY HH:MM
                            '/(\d{1,2}\/\d{1,2}\/\d{4})/',                    // DD/MM/YYYY only
                        ];
                        
                        foreach ($cleanCells as $cellIndex => $cellText) {
                            foreach ($datePatterns as $pattern) {
                                if (preg_match($pattern, $cellText, $matches)) {
                                    try {
                                        // Handle both full datetime and date-only formats
                                        if (isset($matches[2])) {
                                            $dateStr = $matches[1] . ' ' . $matches[2]; // Full datetime
                                            $format = 'd/m/Y H:i';
                                        } else {
                                            $dateStr = $matches[1] . ' 00:00'; // Date only
                                            $format = 'd/m/Y H:i';
                                        }
                                        
                                        $eta = Carbon::createFromFormat($format, $dateStr);
                                        $formattedETA = $eta->format('Y-m-d H:i:s');
                                        
                                        echo "  Cell {$cellIndex}: '{$cellText}' → {$formattedETA}\n";
                                        
                                        // Determine what type of date this is based on cell position
                                        // From the HTML structure we saw:
                                        // - ETA should be around cell 6-7
                                        // - We want to prioritize cells with times (HH:MM)
                                        if (!$extractedETA) {
                                            $extractedETA = $formattedETA;
                                            echo "    ✅ First ETA found: {$formattedETA}\n";
                                        } elseif (isset($matches[2])) {
                                            // Prefer times with hours:minutes
                                            $extractedETA = $formattedETA;
                                            echo "    🎯 Better ETA (with time): {$formattedETA}\n";
                                        }
                                        
                                    } catch (\Exception $e) {
                                        echo "  Cell {$cellIndex}: Parse error - {$e->getMessage()}\n";
                                    }
                                }
                            }
                        }
                        
                        break; // Found the correct vessel, stop searching
                    }
                }
            }
        }
    }
    
    echo "\n" . str_repeat("═", 50) . "\n";
    echo "📊 FINAL RESULTS:\n";
    echo str_repeat("═", 50) . "\n";
    
    if ($vesselFound) {
        echo "✅ SRI SUREE: FOUND\n";
        echo "🧭 Voyage 25080S: FOUND\n";
        if ($extractedETA) {
            echo "🕐 Extracted ETA: {$extractedETA}\n";
            
            // Compare with expected
            $expected = '2025-07-22 10:00:00';
            if ($extractedETA == $expected) {
                echo "🎯 SUCCESS: ETA matches expected date!\n";
            } else {
                echo "⚠️  ETA differs from expected: {$expected}\n";
                echo "   This could be because the vessel status changed\n";
            }
        } else {
            echo "❌ ETA: Not extracted\n";
        }
    } else {
        echo "❌ SRI SUREE: Not found\n";
    }
    
    echo "\n💡 Next Steps:\n";
    echo "────────────────\n";
    echo "1. Update VesselTrackingService with improved row matching\n";
    echo "2. Test with other vessels to ensure reliability\n";
    echo "3. Add validation for vessel + voyage code combination\n";
    
} else {
    echo "❌ Failed to fetch HTML\n";
}

echo "\n🔧 Fixed ETA extraction complete!\n";
