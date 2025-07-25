<?php

// Simple HTML check for LCB1 website
echo "🔍 LCB1 HTML Content Check\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$url = 'https://www.lcb1.com/BerthSchedule';

// Use curl to fetch the page
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
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

echo "✅ Successfully fetched HTML (" . strlen($html) . " bytes)\n\n";

// Check for MARSA PRIDE
$marsaPridePos = stripos($html, 'MARSA PRIDE');
if ($marsaPridePos !== false) {
    echo "✅ MARSA PRIDE found at position $marsaPridePos\n\n";
    
    // Show snippet around MARSA PRIDE
    $start = max(0, $marsaPridePos - 300);
    $length = 600;
    $snippet = substr($html, $start, $length);
    
    echo "📄 HTML snippet around MARSA PRIDE:\n";
    echo "---\n";
    echo $snippet;
    echo "\n---\n\n";
} else {
    echo "❌ MARSA PRIDE NOT found in HTML\n\n";
}

// Check for various voyage code formats
echo "🔍 Checking for voyage codes:\n";
$voyageVariations = ['528S', 'V.528S', 'V528S', '528', 'V 528S', 'v.528s', 'v528s'];

foreach ($voyageVariations as $variation) {
    $pos = stripos($html, $variation);
    if ($pos !== false) {
        echo "✅ '$variation' found at position $pos\n";
        
        // Show small snippet around this voyage code
        $start = max(0, $pos - 50);
        $length = 100;
        $voyageSnippet = substr($html, $start, $length);
        echo "   Context: ..." . trim($voyageSnippet) . "...\n";
    } else {
        echo "❌ '$variation' NOT found\n";
    }
}

// Check if there are any vessel names that contain numbers (might be different vessels)
echo "\n🚢 Looking for vessel names with voyage-like patterns:\n";
preg_match_all('/[A-Z\s]+\s+[0-9]{3,4}[A-Z]?/i', $html, $matches);
if (!empty($matches[0])) {
    foreach (array_unique($matches[0]) as $match) {
        echo "Found: '$match'\n";
    }
} else {
    echo "No vessel names with voyage patterns found\n";
}

echo "\n✅ Analysis complete.\n";
