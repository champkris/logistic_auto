<?php

echo "🧪 TIPS Voyage Code Variations Test\n";
echo "═══════════════════════════════════════\n";
echo "Testing improved voyage code search logic...\n\n";

// Test voyage code variations logic
$voyageCode = "V.25080S";
$htmlContent = "Cell 268: '25080S'"; // What we actually found in TIPS HTML

echo "🔍 Test 1: Voyage Code Variation Generation\n";
echo "──────────────────────────────────────────────\n";
echo "Original voyage code: {$voyageCode}\n";

$voyageVariations = [$voyageCode];

// Add variations for voyage codes that might have prefixes  
if (preg_match('/^([A-Z]+)\.?(.+)$/', $voyageCode, $matches)) {
    // For "V.25080S" -> also try "25080S"
    $voyageVariations[] = $matches[2];
    echo "Variation 1 (remove prefix): {$matches[2]}\n";
}
if (preg_match('/^(.+?)([A-Z\d]+)$/', $voyageCode, $matches)) {
    // For "V.25080S" -> also try "V25080S"
    $noDotsVersion = str_replace('.', '', $voyageCode);
    if (!in_array($noDotsVersion, $voyageVariations)) {
        $voyageVariations[] = $noDotsVersion;
        echo "Variation 2 (remove dots): {$noDotsVersion}\n";
    }
}

echo "All variations to try: " . implode(', ', $voyageVariations) . "\n\n";

echo "🔍 Test 2: Search in HTML Content\n";
echo "────────────────────────────────────────\n";
echo "HTML content: {$htmlContent}\n\n";

$voyageCodeFound = false;
foreach ($voyageVariations as $variation) {
    $found = str_contains(strtoupper($htmlContent), strtoupper($variation));
    echo "Searching for '{$variation}': " . ($found ? "✅ FOUND" : "❌ Not found") . "\n";
    if ($found) {
        $voyageCodeFound = true;
    }
}

echo "\n📊 Final Result:\n";
echo "──────────────────\n";
echo "Voyage code found: " . ($voyageCodeFound ? "✅ YES" : "❌ NO") . "\n";

if ($voyageCodeFound) {
    echo "🎉 SUCCESS! The variation logic works!\n";
    echo "TIPS should now show 'Voyage: Found' in the web UI test.\n";
} else {
    echo "❌ Something is still wrong with the logic.\n";
}

echo "\n🚀 Ready to Test Web UI\n";
echo "──────────────────────────\n";
echo "1. Go to: http://127.0.0.1:8001/vessel-test\n";
echo "2. Click 'Run Test'\n";
echo "3. Check TIPS (Terminal B4) results:\n";
echo "   • ✅ Vessel Name: Should be Found (SRI SUREE)\n";
echo "   • ✅ Voyage Code: Should NOW be Found (25080S variation)\n";
echo "   • 🎯 Method: Should show 'both_found'\n\n";

echo "💡 What was fixed:\n";
echo "─────────────────────\n";
echo "• TIPS website shows '25080S' in I/B Vyg column\n";
echo "• We were searching for 'V.25080S' (with prefix)\n";
echo "• Added logic to try variations without prefixes\n";
echo "• Now searches for: V.25080S, 25080S, V25080S\n";
echo "• Should find '25080S' in the HTML\n";
