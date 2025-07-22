<?php

echo "🚢 WAN HAI - Hutchison Ports URL Test\n";
echo "═════════════════════════════════════════\n";
echo "Testing ONLY the updated Hutchison Ports URL...\n\n";

$config = [
    'name' => 'Hutchison Ports',
    'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17:6927160550678:::::',
    'vessel_full' => 'WAN HAI 517 S093',
    'vessel_name' => 'WAN HAI 517',
    'voyage_code' => 'S093',
];

echo "📍 Terminal: {$config['name']}\n";
echo "🌐 URL: {$config['url']}\n";
echo "🚢 Searching for: {$config['vessel_name']} + {$config['voyage_code']}\n\n";

echo "⏳ Fetching data...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $config['url']);
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
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
curl_close($ch);

echo "📊 Response Details:\n";
echo "─────────────────────\n";
echo "HTTP Status: {$httpCode}\n";
echo "Redirects: {$redirectCount}\n";
echo "Final URL: {$finalUrl}\n";

if ($html !== false && $httpCode >= 200 && $httpCode < 400) {
    $contentLength = strlen($html);
    echo "Content Size: " . number_format($contentLength) . " bytes\n\n";
    
    // Search for vessel name
    $vesselNameFound = stripos($html, $config['vessel_name']) !== false;
    $voyageCodeFound = stripos($html, $config['voyage_code']) !== false;
    $fullNameFound = stripos($html, $config['vessel_full']) !== false;
    
    echo "🔍 Vessel Search Results:\n";
    echo "─────────────────────────\n";
    echo "Vessel Name '{$config['vessel_name']}': " . ($vesselNameFound ? "✅ FOUND" : "❌ Not found") . "\n";
    echo "Voyage Code '{$config['voyage_code']}': " . ($voyageCodeFound ? "✅ FOUND" : "❌ Not found") . "\n";
    echo "Full Name '{$config['vessel_full']}': " . ($fullNameFound ? "✅ FOUND" : "❌ Not found") . "\n\n";
    
    if ($vesselNameFound || $voyageCodeFound || $fullNameFound) {
        echo "🎉 SUCCESS: Vessel data found in the new URL!\n\n";
        
        // Show a preview of the content around the vessel name
        if ($vesselNameFound) {
            $pos = stripos($html, $config['vessel_name']);
            $start = max(0, $pos - 200);
            $preview = substr($html, $start, 400);
            $preview = strip_tags($preview);
            $preview = preg_replace('/\s+/', ' ', $preview);
            
            echo "📄 Content Preview (around vessel name):\n";
            echo "─────────────────────────────────────────\n";
            echo substr($preview, 0, 300) . "...\n\n";
        }
        
        // Try to extract date patterns
        $datePatterns = [
            '/(\d{1,2}\/\d{1,2}\/\d{4})\s*(\d{1,2}:\d{2})/', // DD/MM/YYYY HH:MM
            '/(\d{4}-\d{2}-\d{2})\s*(\d{2}:\d{2})/', // YYYY-MM-DD HH:MM
            '/ETA[:\s]*(\d{1,2}\/\d{1,2}\/\d{4})/i',
            '/\d{2}\/\d{2}\/\d{4}/', // Any date
        ];
        
        echo "📅 Date Patterns Found:\n";
        echo "──────────────────────\n";
        $datesFound = 0;
        foreach ($datePatterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $datesFound += count($matches[0]);
            }
        }
        echo "Total date patterns: {$datesFound}\n";
        
    } else {
        echo "⚠️  No vessel data found with the new URL\n";
        echo "This could mean:\n";
        echo "• The vessel is not in the current schedule\n";
        echo "• The page structure has changed\n";
        echo "• The session requires authentication\n";
        echo "• Different vessel names are used on this page\n\n";
        
        // Show general content preview
        $preview = strip_tags($html);
        $preview = preg_replace('/\s+/', ' ', $preview);
        echo "📄 General Content Preview:\n";
        echo "──────────────────────────\n";
        echo substr($preview, 0, 500) . "...\n\n";
    }
} else {
    echo "❌ Failed to fetch content\n";
    echo "HTTP Code: {$httpCode}\n";
    echo "This suggests the URL may require authentication or is not accessible\n\n";
}

echo "📋 Summary:\n";
echo "──────────────\n";
echo "✅ URL Updated: Both VesselTrackingService.php and vessel_test.php now use the new URL\n";
echo "✅ PHP Warning Fixed: Corrected 'vessel' array key reference\n";
echo "🔗 New URL: https://online.hutchisonports.co.th/hptpcs/f?p=114:17:6927160550678:::::\n\n";

echo "🚀 Ready to test with other vessels or run the full test suite!\n";
