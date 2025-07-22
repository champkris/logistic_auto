<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Services/VesselNameParser.php';
require_once __DIR__ . '/app/Services/VesselTrackingService.php';

use App\Services\VesselTrackingService;

echo "🚢 Updated Hutchison Ports URL Test\n";
echo "═══════════════════════════════════════\n";
echo "Testing WAN HAI vessel with new URL...\n\n";

$service = new VesselTrackingService();
$terminals = $service->getTerminals();

// Show the updated terminal configuration
echo "📍 Terminal Configuration Update:\n";
echo "─────────────────────────────────────\n";
echo "Terminal: {$terminals['C1C2']['name']}\n";
echo "Old URL: https://online.hutchisonports.co.th/hptpcs/f?p=114:13:585527473627:::::\n";
echo "New URL: {$terminals['C1C2']['url']}\n";
echo "Vessel:  {$terminals['C1C2']['vessel_full']}\n\n";

// Analyze URL changes
echo "🔍 URL Analysis:\n";
echo "───────────────────\n";
echo "• Changed page from 'p=114:13' to 'p=114:17'\n";
echo "• Updated session ID from '585527473627' to '6927160550678'\n";
echo "• This likely points to a different schedule view in the Oracle APEX app\n\n";

echo "🧪 Testing the new URL accessibility...\n";
echo "──────────────────────────────────────────\n";

// Test just the URL accessibility (without full vessel search)
try {
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $terminals['C1C2']['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    
    curl_close($ch);
    
    if ($response !== false && $httpCode == 200) {
        $contentLength = strlen($response);
        echo "✅ SUCCESS: URL is accessible!\n";
        echo "   📊 HTTP Status: {$httpCode}\n";
        echo "   📄 Content Size: " . number_format($contentLength) . " bytes\n";
        echo "   ⏱️  Response Time: {$responseTime}ms\n";
        
        // Check if it looks like a valid schedule page
        if (stripos($response, 'vessel') !== false || 
            stripos($response, 'schedule') !== false || 
            stripos($response, 'berth') !== false ||
            stripos($response, 'eta') !== false) {
            echo "   🚢 Content appears to be vessel-related\n";
        }
        
        // Check for common Oracle APEX elements
        if (stripos($response, 'apex') !== false) {
            echo "   🔧 Oracle APEX application detected\n";
        }
    } else {
        echo "❌ ERROR: URL not accessible\n";
        echo "   📊 HTTP Status: {$httpCode}\n";
        echo "   ⚠️  This might be due to session expiry or access restrictions\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n💡 Next Steps:\n";
echo "─────────────────\n";
echo "1. Test full vessel tracking with the new URL:\n";
echo "   php vessel_test.php\n\n";
echo "2. Or test via web interface:\n";
echo "   php artisan serve\n";
echo "   Visit: http://localhost:8000/vessel-test\n\n";
echo "3. If the URL works better, you can test other vessels:\n";
echo "   - Try different WAN HAI vessels\n";
echo "   - Check if the new page shows more schedule data\n";
echo "   - Verify ETA extraction works correctly\n\n";

echo "🎯 The Hutchison Ports URL has been successfully updated!\n";
echo "   New URL: {$terminals['C1C2']['url']}\n";
