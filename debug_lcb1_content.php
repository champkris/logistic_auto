<?php

echo "🔍 LCB1 HTML Content Analysis\n";
echo "═════════════════════════════════════\n";
echo "Checking what's actually on LCB1 website...\n\n";

$url = 'https://www.lcb1.com/BerthSchedule';
$vesselName = 'MARSA PRIDE';
$voyageCode = '528S'; // What we expect to find (without V.)

echo "📍 URL: {$url}\n";
echo "🚢 Looking for vessel: {$vesselName}\n";  
echo "🧭 Looking for voyage: {$voyageCode}\n\n";

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

echo "📊 Response: HTTP {$httpCode}\n";
echo "📄 Content Length: " . strlen($html) . " bytes\n\n";

if ($html && $httpCode == 200) {
    // Check if vessel name exists
    $vesselPos = stripos($html, $vesselName);
    echo "🚢 '{$vesselName}' found: " . ($vesselPos !== false ? "✅ YES (pos: {$vesselPos})" : "❌ NO") . "\n";
    
    // Check if voyage code exists
    $voyagePos = stripos($html, $voyageCode);
    echo "🧭 '{$voyageCode}' found: " . ($voyagePos !== false ? "✅ YES (pos: {$voyagePos})" : "❌ NO") . "\n";
    
    // Also try variations
    $variations = ['V.528S', '528S', 'V528S'];
    echo "\n🔧 Checking all variations:\n";
    foreach ($variations as $variation) {
        $found = stripos($html, $variation) !== false;
        echo "   {$variation}: " . ($found ? "✅ FOUND" : "❌ Not found") . "\n";
    }
    
    if ($vesselPos !== false) {
        echo "\n📋 Content around MARSA PRIDE:\n";
        echo "─────────────────────────────────────\n";
        $start = max(0, $vesselPos - 200);
        $section = substr($html, $start, 400);
        $cleanSection = html_entity_decode(strip_tags($section));
        echo $cleanSection . "\n";
        echo "─────────────────────────────────────\n";
    }
    
    // Check if this is a JavaScript-rendered page
    if (stripos($html, 'javascript') !== false || stripos($html, 'ajax') !== false) {
        echo "\n⚠️  WARNING: This page may use JavaScript to load content\n";
        echo "   The vessel data might not be visible in the initial HTML\n";
    }
    
    // Check for common table structures
    if (stripos($html, '<table') !== false) {
        echo "\n✅ Page contains HTML tables\n";
    } else {
        echo "\n❌ No HTML tables detected - might be JavaScript-rendered\n";
    }
    
} else {
    echo "❌ Failed to fetch content\n";
    echo "HTTP Code: {$httpCode}\n";
}

echo "\n💡 This will help us understand why LCB1 voyage detection isn't working!\n";
