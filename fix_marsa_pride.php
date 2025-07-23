<?php

// Quick fix for MARSA PRIDE voyage code issue
// Update the vessel configuration in VesselTrackingService.php

echo "🔧 MARSA PRIDE Fix Suggestions:\n";
echo str_repeat("=", 50) . "\n\n";

echo "📊 Current Situation:\n";
echo "  - Vessel: MARSA PRIDE ✅ (found on website)\n";
echo "  - Configured Voyage: V.528S ❌ (not found)\n";
echo "  - Actual Voyage: 528S ✅ (exists on website)\n";
echo "  - Status: Vessel departing TODAY at 11:00\n\n";

echo "🛠️ Solutions:\n\n";

echo "1. IMMEDIATE FIX - Update voyage code in config:\n";
echo "   Change: 'vessel_full' => 'MARSA PRIDE V.528S'\n";
echo "   To:     'vessel_full' => 'MARSA PRIDE 528S'\n\n";

echo "2. Check for newer vessels:\n";
echo "   MARSA PRIDE is departing July 23 at 11:00\n";
echo "   You might want to look for upcoming vessels\n\n";

echo "3. Test the voyage variation logic:\n";
echo "   The system should handle 'V.528S' -> '528S' conversion\n";
echo "   But it might not be working properly\n\n";

// Create test for voyage variations
echo "🧪 Testing Voyage Variations:\n";
$voyageCode = "V.528S";
$voyageSearchVariations = [$voyageCode];

// Replicate the logic from VesselTrackingService
if (preg_match('/^([A-Z]+)\.?\s*(.+)$/', $voyageCode, $matches)) {
    $voyageSearchVariations[] = $matches[2]; // Remove prefix like "V. "
    echo "  - Pattern match found: '{$matches[1]}' + '{$matches[2]}'\n";
}
$voyageSearchVariations[] = str_replace(['.', ' '], '', $voyageCode); // Remove dots and spaces

// Additional handling for space-separated prefixes
if (str_contains($voyageCode, ' ')) {
    $parts = explode(' ', $voyageCode);
    if (count($parts) >= 2) {
        $lastPart = end($parts);
        if (!in_array($lastPart, $voyageSearchVariations)) {
            $voyageSearchVariations[] = $lastPart;
        }
    }
}

echo "  - All variations to try: " . implode(', ', $voyageSearchVariations) . "\n\n";

// Check if any variation would match the actual website data
$websiteVoyage = "528S";
$wouldMatch = false;
foreach ($voyageSearchVariations as $variation) {
    if (strcasecmp($variation, $websiteVoyage) === 0) {
        $wouldMatch = true;
        echo "  ✅ Variation '{$variation}' WOULD match website voyage '{$websiteVoyage}'\n";
        break;
    }
}

if (!$wouldMatch) {
    echo "  ❌ None of the variations would match website voyage '{$websiteVoyage}'\n";
    echo "  🔧 This suggests the regex pattern isn't working as expected\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎯 RECOMMENDED ACTION:\n";
echo "Update VesselTrackingService.php line ~40 to:\n";
echo "'vessel_full' => 'MARSA PRIDE 528S'\n";
echo "(or check for newer vessels since this one departs today)\n";
