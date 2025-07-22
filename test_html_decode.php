<?php

echo "🧪 HTML Entity Decoding Test\n";
echo "═══════════════════════════════\n\n";

// Test the HTML entity decoding issue we found
$encodedDate = "20&#x2F;07&#x2F;2025 15:40";
$decodedDate = html_entity_decode($encodedDate, ENT_QUOTES | ENT_HTML401, 'UTF-8');

echo "🔍 HTML Entity Test:\n";
echo "────────────────────────\n";
echo "Original (encoded): {$encodedDate}\n";
echo "Decoded:           {$decodedDate}\n\n";

// Test regex pattern matching
$pattern = '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/';

echo "📋 Pattern Matching Test:\n";
echo "───────────────────────────\n";
echo "Pattern: {$pattern}\n";

if (preg_match($pattern, $encodedDate, $matches)) {
    echo "❌ Encoded version matches: " . implode(', ', $matches) . "\n";
} else {
    echo "❌ Encoded version: NO MATCH (expected)\n";
}

if (preg_match($pattern, $decodedDate, $matches)) {
    echo "✅ Decoded version matches: " . implode(', ', $matches) . "\n";
    
    // Test Carbon parsing
    try {
        $dateStr = $matches[1] . ' ' . $matches[2];
        $eta = Carbon\Carbon::createFromFormat('d/m/Y H:i', $dateStr);
        echo "✅ Carbon parsed successfully: {$eta->format('Y-m-d H:i:s')}\n";
        echo "📅 Human readable: {$eta->format('l, F j, Y \\a\\t g:i A')}\n";
    } catch (\Exception $e) {
        echo "❌ Carbon parsing failed: {$e->getMessage()}\n";
    }
} else {
    echo "❌ Decoded version: NO MATCH\n";
}

echo "\n🎯 Expected Results for Web UI:\n";
echo "──────────────────────────────────\n";
echo "With this fix, the Hutchison Ports test should now show:\n";
echo "• ✅ Vessel Name: Found (WAN HAI 517)\n";
echo "• ✅ Voyage Code: Found (S093)\n";
echo "• ✅ ETA Found: 2025-07-20 15:40:00\n";
echo "• 📅 Display: Sunday, July 20, 2025 at 3:40 PM\n\n";

echo "🚀 Ready to test in Web UI!\n";
echo "──────────────────────────────\n";
echo "Go to: http://127.0.0.1:8001/vessel-test\n";
echo "Click 'Run Test' and check Hutchison Ports results!\n";
