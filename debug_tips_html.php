<?php

echo "🚢 TIPS Website HTML Diagnostic Tool\n";
echo "═══════════════════════════════════════\n";
echo "Let's examine the TIPS HTML structure for I/B Vyg column...\n\n";

$url = 'https://www.tips.co.th/container/shipSched/List';
$vesselName = 'SRI SUREE';
$voyageCode = 'V.25080S';

echo "📍 URL: {$url}\n";
echo "🚢 Looking for vessel: {$vesselName}\n";
echo "🧭 Looking for voyage: {$voyageCode}\n\n";

echo "⏳ Fetching HTML...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_REFERER, 'https://www.tips.co.th/');

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($html !== false && $httpCode == 200) {
    echo "✅ Successfully fetched HTML ({$httpCode})\n";
    echo "📄 Content size: " . number_format(strlen($html)) . " bytes\n";
    echo "🔗 Final URL: {$finalUrl}\n\n";
    
    // Check if vessel name is found
    $vesselPos = stripos($html, $vesselName);
    if ($vesselPos !== false) {
        echo "✅ Found vessel '{$vesselName}' at position {$vesselPos}\n\n";
        
        // Extract context around vessel name
        $start = max(0, $vesselPos - 1000);
        $context = substr($html, $start, 2000);
        
        echo "🔍 RAW HTML Context (1000 chars before/after vessel name):\n";
        echo "════════════════════════════════════════════════════════\n";
        echo htmlspecialchars($context) . "\n";
        echo "════════════════════════════════════════════════════════\n\n";
        
        // Check specifically for voyage code
        $voyagePos = stripos($html, $voyageCode);
        if ($voyagePos !== false) {
            echo "✅ Found voyage code '{$voyageCode}' at position {$voyagePos}\n";
            
            // Show context around voyage code
            $voyageStart = max(0, $voyagePos - 500);
            $voyageContext = substr($html, $voyageStart, 1000);
            echo "🧭 Voyage Code Context:\n";
            echo "─────────────────────────────────────────────────────────\n";
            echo htmlspecialchars($voyageContext) . "\n";
            echo "─────────────────────────────────────────────────────────\n\n";
        } else {
            echo "❌ Voyage code '{$voyageCode}' not found in HTML\n\n";
            
            // Look for similar voyage patterns
            echo "🔍 Searching for similar voyage patterns...\n";
            $voyagePatterns = [
                '/V\.\d+[A-Z\d]*/i',
                '/V\d+[A-Z\d]*/i', 
                '/[A-Z]\d+[A-Z\d]*/i',
                '/\d+[A-Z]+/i'
            ];
            
            foreach ($voyagePatterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    echo "  Pattern {$pattern}: " . implode(', ', array_unique($matches[0])) . "\n";
                }
            }
            echo "\n";
        }
        
        // Look for table structure specifically
        if (preg_match('/<tr[^>]*>.*?' . preg_quote($vesselName, '/') . '.*?<\/tr>/is', $html, $rowMatch)) {
            echo "✅ Found table row containing vessel!\n\n";
            echo "📋 Complete Table Row HTML:\n";
            echo "─────────────────────────────────────────────────────────\n";
            echo htmlspecialchars($rowMatch[0]) . "\n";
            echo "─────────────────────────────────────────────────────────\n\n";
            
            // Extract all cells from the row
            if (preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowMatch[0], $cellMatches)) {
                echo "📊 Table Cells Content:\n";
                echo "──────────────────────────\n";
                foreach ($cellMatches[1] as $index => $cell) {
                    $cellText = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML401, 'UTF-8');
                    $cellText = trim(preg_replace('/\s+/', ' ', $cellText));
                    echo "Cell {$index}: '{$cellText}'\n";
                    
                    // Check if this cell contains the voyage code
                    if (stripos($cellText, $voyageCode) !== false) {
                        echo "   🎯 ← VOYAGE CODE FOUND HERE!\n";
                    }
                }
                echo "\n";
            }
        } else {
            echo "❌ Could not find complete table row containing vessel\n\n";
            
            // Try different table row patterns
            echo "🔍 Searching for alternative table structures...\n";
            $tablePatterns = [
                '/<tr[^>]*>[^<]*' . preg_quote($vesselName, '/') . '.*?<\/tr>/is',
                '/<row[^>]*>.*?' . preg_quote($vesselName, '/') . '.*?<\/row>/is',
                '/<div[^>]*class[^>]*row[^>]*>.*?' . preg_quote($vesselName, '/') . '.*?<\/div>/is',
            ];
            
            foreach ($tablePatterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    echo "✅ Alternative structure found:\n";
                    echo htmlspecialchars(substr($matches[0], 0, 300)) . "...\n\n";
                    break;
                }
            }
        }
        
    } else {
        echo "❌ Vessel name '{$vesselName}' not found in HTML\n";
        
        // Check if the page shows any vessel data at all
        echo "🔍 Looking for any vessel names in the page...\n";
        $vesselPatterns = [
            '/[A-Z\s]+\d+/',  // Names with numbers
            '/\b[A-Z]{3,}\s+[A-Z]{3,}/',  // Multiple capital words
        ];
        
        foreach ($vesselPatterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $vessels = array_unique($matches[0]);
                $vessels = array_filter($vessels, function($v) { return strlen($v) > 5; });
                if (!empty($vessels)) {
                    echo "  Possible vessels found: " . implode(', ', array_slice($vessels, 0, 10)) . "\n";
                }
            }
        }
    }
    
    // Check for column headers
    echo "\n🏗️  Table Structure Analysis:\n";
    echo "────────────────────────────────\n";
    if (preg_match_all('/<th[^>]*>(.*?)<\/th>/is', $html, $headerMatches)) {
        echo "Column headers found:\n";
        foreach ($headerMatches[1] as $index => $header) {
            $headerText = html_entity_decode(strip_tags($header), ENT_QUOTES | ENT_HTML401, 'UTF-8');
            echo "  Column {$index}: '{$headerText}'\n";
            if (stripos($headerText, 'vyg') !== false || stripos($headerText, 'voy') !== false) {
                echo "    🎯 ← Potential voyage column!\n";
            }
        }
    } else {
        echo "No standard table headers found, checking for alternative structures...\n";
    }
    
} else {
    echo "❌ Failed to fetch HTML (HTTP {$httpCode})\n";
    echo "Final URL: {$finalUrl}\n";
}

echo "\n💡 Next Steps:\n";
echo "──────────────────\n";
echo "Based on the HTML structure above, we can:\n";
echo "1. Identify the correct column containing voyage codes\n";
echo "2. Update our search logic for TIPS website structure\n";
echo "3. Handle different HTML formats\n";
echo "4. Test the improved logic\n\n";

echo "🎯 This will help us find the I/B Vyg column data!\n";
