<?php

echo "🔍 Hutchison Ports HTML Diagnostic Tool\n";
echo "═════════════════════════════════════════\n";
echo "Let's examine the actual HTML structure...\n\n";

$url = 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17:6927160550678:::::';
$vesselName = 'WAN HAI 517';

echo "📍 URL: {$url}\n";
echo "🚢 Looking for: {$vesselName}\n\n";

echo "⏳ Fetching HTML...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, tempnam(sys_get_temp_dir(), 'cookies'));
curl_setopt($ch, CURLOPT_COOKIEFILE, tempnam(sys_get_temp_dir(), 'cookies'));

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($html !== false && $httpCode == 200) {
    echo "✅ Successfully fetched HTML ({$httpCode})\n";
    echo "📄 Content size: " . number_format(strlen($html)) . " bytes\n\n";
    
    // Find vessel name position
    $pos = stripos($html, $vesselName);
    if ($pos !== false) {
        echo "✅ Found '{$vesselName}' at position {$pos}\n\n";
        
        // Extract larger context around vessel name
        $start = max(0, $pos - 1000);
        $context = substr($html, $start, 2000);
        
        echo "🔍 RAW HTML Context (1000 chars before/after vessel name):\n";
        echo "════════════════════════════════════════════════════════\n";
        echo htmlspecialchars($context) . "\n";
        echo "════════════════════════════════════════════════════════\n\n";
        
        // Look for table row containing vessel
        if (preg_match('/<tr[^>]*>.*?' . preg_quote($vesselName, '/') . '.*?<\/tr>/is', $html, $rowMatch)) {
            echo "✅ Found table row containing vessel!\n\n";
            echo "📋 Table Row HTML:\n";
            echo "─────────────────────────────────────────────────────────\n";
            echo htmlspecialchars($rowMatch[0]) . "\n";
            echo "─────────────────────────────────────────────────────────\n\n";
            
            // Extract all cells from the row
            if (preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowMatch[0], $cellMatches)) {
                echo "📊 Table Cells Content:\n";
                echo "──────────────────────────\n";
                foreach ($cellMatches[1] as $index => $cell) {
                    $cellText = strip_tags($cell);
                    $cellText = trim(preg_replace('/\s+/', ' ', $cellText));
                    echo "Cell {$index}: '{$cellText}'\n";
                    
                    // Check for date patterns in each cell
                    $datePatterns = [
                        '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/',
                        '/(\d{1,2}\/\d{1,2}\/\d{4})/',
                        '/(\d{1,2}-\d{1,2}-\d{4})\s*(\d{1,2}:\d{2})/',
                        '/(\d{4}-\d{2}-\d{2})\s*(\d{2}:\d{2})/',
                    ];
                    
                    foreach ($datePatterns as $patternIndex => $pattern) {
                        if (preg_match($pattern, $cellText, $matches)) {
                            echo "   📅 Found date pattern {$patternIndex}: '{$matches[0]}'\n";
                        }
                    }
                }
                echo "\n";
            } else {
                echo "❌ Could not extract table cells\n\n";
            }
        } else {
            echo "❌ Could not find complete table row\n\n";
            
            // Try to find any dates near the vessel name
            echo "🔍 Searching for dates in vessel context...\n";
            $vesselContext = substr($html, max(0, $pos - 500), 1000);
            
            $allDatePatterns = [
                '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/' => 'DD/MM/YYYY HH:MM',
                '/(\d{1,2}\/\d{1,2}\/\d{4})/' => 'DD/MM/YYYY',
                '/(\d{2}-\d{2}-\d{4})\s*(\d{2}:\d{2})/' => 'DD-MM-YYYY HH:MM',
                '/(\d{4}-\d{2}-\d{2})\s*(\d{2}:\d{2})/' => 'YYYY-MM-DD HH:MM',
                '/\d{2}:\d{2}/' => 'Time pattern',
            ];
            
            foreach ($allDatePatterns as $pattern => $description) {
                if (preg_match_all($pattern, $vesselContext, $matches)) {
                    echo "  📅 Found {$description}: " . implode(', ', $matches[0]) . "\n";
                }
            }
        }
        
        // Look for common table structures
        echo "\n🏗️  HTML Structure Analysis:\n";
        echo "────────────────────────────────\n";
        echo "Table tags found: " . substr_count($html, '<table') . "\n";
        echo "TR tags found: " . substr_count($html, '<tr') . "\n";
        echo "TD tags found: " . substr_count($html, '<td') . "\n";
        echo "TH tags found: " . substr_count($html, '<th') . "\n";
        
        // Look for any element containing both vessel and dates
        $combinedPattern = '/[^>]*' . preg_quote($vesselName, '/') . '[^<]*\d{1,2}\/\d{1,2}\/\d{4}/is';
        if (preg_match($combinedPattern, $html, $match)) {
            echo "✅ Found vessel + date combination: " . htmlspecialchars(trim($match[0])) . "\n";
        }
        
    } else {
        echo "❌ Vessel name '{$vesselName}' not found in HTML\n";
    }
} else {
    echo "❌ Failed to fetch HTML (HTTP {$httpCode})\n";
}

echo "\n💡 Next Steps:\n";
echo "──────────────────\n";
echo "Based on the HTML structure above, we can:\n";
echo "1. Adjust our table parsing logic\n";
echo "2. Update date extraction patterns\n";
echo "3. Modify the web route ETA extraction\n";
echo "4. Test the improved extraction logic\n\n";

echo "🎯 Save this output to help debug the ETA extraction!\n";
