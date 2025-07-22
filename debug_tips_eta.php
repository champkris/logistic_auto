<?php

echo "🔍 TIPS ETA Extraction Debug Tool\n";
echo "═════════════════════════════════════\n";
echo "Debugging why ETA shows wrong date...\n\n";

$url = 'https://www.tips.co.th/container/shipSched/List';
$vesselName = 'SRI SUREE';

echo "📍 URL: {$url}\n";
echo "🚢 Looking for: {$vesselName}\n";
echo "📅 Expected ETA: 22/07/2025 10:00 (from diagnostic output)\n";
echo "❌ Wrong ETA shown: 2025-08-02 00:00:00\n\n";

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
    
    // Find the exact table row for SRI SUREE
    if (preg_match('/<tr[^>]*>.*?' . preg_quote($vesselName, '/') . '.*?<\/tr>/is', $html, $rowMatch)) {
        echo "✅ Found SRI SUREE table row\n\n";
        
        $tableRow = $rowMatch[0];
        echo "📋 Complete SRI SUREE Row:\n";
        echo "─────────────────────────────────────────────────────────\n";
        echo htmlspecialchars($tableRow) . "\n";
        echo "─────────────────────────────────────────────────────────\n\n";
        
        // Extract all cells from this specific row
        if (preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $tableRow, $cellMatches)) {
            echo "📊 SRI SUREE Row Cells (decoded):\n";
            echo "──────────────────────────────────────\n";
            foreach ($cellMatches[1] as $index => $cell) {
                $cellText = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML401, 'UTF-8');
                $cellText = trim(preg_replace('/\s+/', ' ', $cellText));
                echo "Cell {$index}: '{$cellText}'\n";
                
                // Check for date patterns in each cell
                $datePatterns = [
                    '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/' => 'DD/MM/YYYY HH:MM',
                    '/(\d{1,2}\/\d{1,2}\/\d{4})/' => 'DD/MM/YYYY',
                ];
                
                foreach ($datePatterns as $pattern => $description) {
                    if (preg_match($pattern, $cellText, $matches)) {
                        echo "   📅 Found {$description}: '{$matches[0]}'\n";
                        
                        // Test Carbon parsing for this date
                        try {
                            if (isset($matches[2])) {
                                $dateStr = $matches[1] . ' ' . $matches[2];
                            } else {
                                $dateStr = $matches[1] . ' 00:00';
                            }
                            
                            $eta = Carbon\Carbon::createFromFormat('d/m/Y H:i', $dateStr);
                            echo "   ✅ Parsed as: {$eta->format('Y-m-d H:i:s')} ({$eta->format('l, F j, Y \\a\\t g:i A')})\n";
                        } catch (\Exception $e) {
                            echo "   ❌ Parse error: {$e->getMessage()}\n";
                        }
                    }
                }
            }
            echo "\n";
            
            // Now test the actual ETA extraction function
            echo "🧪 Testing Current ETA Extraction Logic:\n";
            echo "──────────────────────────────────────────────\n";
            
            // Test our table-based extraction
            echo "Method 1: Table-based extraction from SRI SUREE row\n";
            $extractedETA = null;
            
            foreach ($cellMatches[1] as $cell) {
                $cellContent = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML401, 'UTF-8');
                
                $datePatterns = [
                    '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/',
                    '/(\d{1,2}\/\d{1,2}\/\d{4})/',
                ];
                
                foreach ($datePatterns as $pattern) {
                    if (preg_match($pattern, $cellContent, $matches)) {
                        try {
                            if (isset($matches[2])) {
                                $dateStr = $matches[1] . ' ' . $matches[2];
                            } else {
                                $dateStr = $matches[1] . ' 00:00';
                            }
                            
                            $eta = Carbon\Carbon::createFromFormat('d/m/Y H:i', $dateStr);
                            if (!$extractedETA) { // Take the first valid date found
                                $extractedETA = $eta->format('Y-m-d H:i:s');
                                echo "First date found: {$extractedETA} from cell '{$cellContent}'\n";
                                break 2;
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            }
            
            if ($extractedETA) {
                echo "🎯 Extracted ETA: {$extractedETA}\n";
            } else {
                echo "❌ No ETA extracted from table method\n";
            }
            
            // Test fallback method
            echo "\nMethod 2: Section-based extraction around vessel name\n";
            $vesselPos = stripos($html, $vesselName);
            if ($vesselPos !== false) {
                $start = max(0, $vesselPos - 500);
                $vesselSection = substr($html, $start, 1000);
                
                echo "Vessel section preview:\n";
                echo substr(strip_tags($vesselSection), 0, 200) . "...\n\n";
                
                if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/', $vesselSection, $matches)) {
                    try {
                        $eta = Carbon\Carbon::createFromFormat('d/m/Y H:i', $matches[0]);
                        echo "Section method ETA: {$eta->format('Y-m-d H:i:s')}\n";
                    } catch (\Exception $e) {
                        echo "Section method parse error: {$e->getMessage()}\n";
                    }
                } else {
                    echo "No date pattern found in vessel section\n";
                }
            }
        }
    } else {
        echo "❌ Could not find SRI SUREE table row\n";
    }
    
    // Check if there are any dates that could be parsed as "2025-08-02"
    echo "\n🔍 Searching for any '02/08/2025' patterns in HTML:\n";
    echo "─────────────────────────────────────────────────────\n";
    if (preg_match_all('/02\/08\/2025/', $html, $matches)) {
        echo "Found '02/08/2025' " . count($matches[0]) . " times in HTML\n";
    } else {
        echo "No '02/08/2025' found in HTML\n";
    }
    
    if (preg_match_all('/2\/8\/2025/', $html, $matches)) {
        echo "Found '2/8/2025' " . count($matches[0]) . " times in HTML\n";
    } else {
        echo "No '2/8/2025' found in HTML\n";
    }
    
} else {
    echo "❌ Failed to fetch HTML\n";
}

echo "\n💡 Expected vs Actual:\n";
echo "─────────────────────────\n";
echo "Expected ETA: 22/07/2025 10:00 → 2025-07-22 10:00:00\n";
echo "Actual shown: 2025-08-02 00:00:00\n";
echo "Issue: Wrong date being extracted or parsing error\n\n";

echo "🎯 This will help us fix the ETA extraction for TIPS!\n";
