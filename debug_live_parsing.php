<?php

echo "🔍 MARSA PRIDE Live Debug - Post Fix (cURL version)\n";
echo str_repeat("=", 60) . "\n\n";

// Test actual HTTP request to LCB1 using cURL
echo "📡 Making live request to LCB1...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.lcb1.com/BerthSchedule');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: $error\n";
    exit;
}

if ($httpCode !== 200) {
    echo "❌ HTTP Error: $httpCode\n";
    exit;
}

$htmlSize = strlen($html);
echo "✅ HTTP Request successful\n";
echo "📏 HTML Size: " . number_format($htmlSize) . " bytes\n\n";

// Check what we can find
echo "🔍 Content Analysis:\n";

// 1. Look for MARSA PRIDE
$marsaPrideFound = stripos($html, 'MARSA PRIDE') !== false;
echo "  - MARSA PRIDE found: " . ($marsaPrideFound ? '✅ YES' : '❌ NO') . "\n";

if ($marsaPrideFound) {
    // 2. Look for voyage codes
    $voyageCodes = ['528S', 'V.528S', '528N'];
    foreach ($voyageCodes as $voyage) {
        $found = stripos($html, $voyage) !== false;
        echo "  - Voyage '{$voyage}' found: " . ($found ? '✅ YES' : '❌ NO') . "\n";
    }
    
    echo "\n📄 Extract MARSA PRIDE section:\n";
    // Extract the section around MARSA PRIDE
    $pos = stripos($html, 'MARSA PRIDE');
    $start = max(0, $pos - 300);
    $section = substr($html, $start, 1000);
    
    // Clean up for display
    $cleanSection = strip_tags($section);
    $cleanSection = preg_replace('/\s+/', ' ', $cleanSection);
    echo "Context: " . trim($cleanSection) . "\n\n";
    
    // 3. Look for table structure
    echo "🔍 Table Structure Analysis:\n";
    if (preg_match_all('/<tr[^>]*>.*?MARSA PRIDE.*?<\/tr>/si', $html, $matches)) {
        echo "  - Found " . count($matches[0]) . " table row(s) containing MARSA PRIDE\n";
        
        foreach ($matches[0] as $i => $row) {
            echo "\n  🔍 Row " . ($i + 1) . " HTML:\n";
            echo "    " . trim($row) . "\n";
            
            // Extract cells
            if (preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cellMatches)) {
                $cells = $cellMatches[1];
                echo "\n  📊 Parsed Cells:\n";
                foreach ($cells as $j => $cell) {
                    $cellText = trim(strip_tags($cell));
                    echo "    Cell $j: '$cellText'\n";
                }
            }
        }
    } else {
        echo "  - No table rows found with MARSA PRIDE\n";
        
        // Alternative: look for any structure containing MARSA PRIDE
        echo "\n🔍 Alternative search patterns:\n";
        if (preg_match('/MARSA PRIDE.*?(\d{3,4}[NS])/i', $html, $matches)) {
            echo "  - Found voyage pattern: " . $matches[1] . "\n";
        }
    }
    
} else {
    echo "\n❌ MARSA PRIDE not found in response\n";
    echo "🔍 Let's check if the page structure changed...\n";
    
    // Look for common schedule indicators
    $indicators = ['vessel', 'schedule', 'berth', 'ETA', 'arrival', 'departure'];
    foreach ($indicators as $indicator) {
        $found = stripos($html, $indicator) !== false;
        echo "  - '$indicator' found: " . ($found ? '✅ YES' : '❌ NO') . "\n";
    }
    
    echo "\n📊 Response preview (first 1000 chars):\n";
    echo substr(strip_tags($html), 0, 1000) . "...\n";
}

// Check current time
echo "\n🕐 Current time: " . date('Y-m-d H:i:s T') . "\n";
echo "⚠️  Note: MARSA PRIDE was scheduled to depart at 11:00 today\n";
echo "   It may have already left the terminal\n";

echo "\n" . str_repeat("=", 60) . "\n";
