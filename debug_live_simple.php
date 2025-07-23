<?php

echo "🕵️ MARSA PRIDE Live Data Debug (Plain PHP)\n";
echo str_repeat("=", 60) . "\n\n";

// Fetch data using cURL
echo "📡 Fetching live data from LCB1...\n";

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

if ($html === false || !empty($error)) {
    echo "❌ cURL Error: $error\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "❌ HTTP Error: $httpCode\n";
    exit(1);
}

echo "✅ Successfully fetched HTML (" . number_format(strlen($html)) . " bytes)\n\n";

// Test 1: Search for vessel name
echo "1️⃣ VESSEL NAME SEARCH:\n";
$vesselNameFound = stripos($html, 'MARSA PRIDE') !== false;
echo "  - 'MARSA PRIDE' found: " . ($vesselNameFound ? "✅ YES" : "❌ NO") . "\n";

// Test 2: Search for various voyage codes
echo "\n2️⃣ VOYAGE CODE SEARCH:\n";
$voyageCodes = ['528S', 'V.528S', 'V528S', '528N', '529S', '529N'];

foreach ($voyageCodes as $code) {
    $found = stripos($html, $code) !== false;
    echo "  - '{$code}' found: " . ($found ? "✅ YES" : "❌ NO") . "\n";
}

// Test 3: Extract table data around MARSA PRIDE
echo "\n3️⃣ TABLE DATA EXTRACTION:\n";
if ($vesselNameFound) {
    // Find table rows containing MARSA PRIDE
    if (preg_match_all('/<tr[^>]*>.*?MARSA\s+PRIDE.*?<\/tr>/si', $html, $matches)) {
        echo "Found " . count($matches[0]) . " table row(s) with MARSA PRIDE:\n\n";
        
        foreach ($matches[0] as $index => $row) {
            echo "Row " . ($index + 1) . ":\n";
            
            // Extract cell contents
            if (preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cellMatches)) {
                echo "Cells found: " . count($cellMatches[1]) . "\n";
                foreach ($cellMatches[1] as $cellIndex => $cell) {
                    $cleanCell = trim(html_entity_decode(strip_tags($cell)));
                    if (!empty($cleanCell)) {
                        echo "  Cell[$cellIndex]: '$cleanCell'\n";
                    }
                }
            }
            echo "\n";
        }
    } else {
        echo "❌ No table rows found with MARSA PRIDE\n";
        
        // Fallback: Look for any mention of MARSA PRIDE with context
        $pos = stripos($html, 'MARSA PRIDE');
        if ($pos !== false) {
            echo "Found MARSA PRIDE at position $pos. Context:\n";
            $context = substr($html, max(0, $pos - 300), 600);
            echo "--- Context Start ---\n";
            echo htmlspecialchars($context);
            echo "\n--- Context End ---\n\n";
        }
    }
} else {
    echo "❌ MARSA PRIDE not found in HTML\n";
    
    // Check if page loaded correctly
    echo "\n4️⃣ PAGE STRUCTURE CHECK:\n";
    echo "  - HTML length: " . strlen($html) . " bytes\n";
    echo "  - Contains <table>: " . (stripos($html, '<table') !== false ? "✅ YES" : "❌ NO") . "\n";
    echo "  - Contains 'Schedule': " . (stripos($html, 'Schedule') !== false ? "✅ YES" : "❌ NO") . "\n";
    echo "  - Contains 'Vessel': " . (stripos($html, 'Vessel') !== false ? "✅ YES" : "❌ NO") . "\n";
    
    // Show first 500 characters of HTML
    echo "\n5️⃣ HTML PREVIEW:\n";
    echo substr($html, 0, 800) . "...\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 DIAGNOSIS COMPLETE\n";
