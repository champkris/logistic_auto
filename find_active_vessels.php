<?php

echo "🚢 LCB1 Active Vessel Finder\n";
echo str_repeat("=", 50) . "\n\n";

// Fetch current schedule
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.lcb1.com/BerthSchedule');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$html = curl_exec($ch);
curl_close($ch);

if ($html === false) {
    echo "❌ Failed to fetch HTML\n";
    exit(1);
}

echo "📡 Successfully fetched current LCB1 schedule\n\n";

// Look for active schedule table data
echo "🔍 SEARCHING FOR ACTIVE VESSELS:\n";

// Method 1: Look for table data with vessel names and voyage codes
if (preg_match_all('/<tr[^>]*>.*?<td[^>]*>.*?<\/td>.*?<td[^>]*>([^<]+)<\/td>.*?<td[^>]*>([^<]+)<\/td>.*?<td[^>]*>([^<]+)<\/td>.*?<\/tr>/si', $html, $matches, PREG_SET_ORDER)) {
    echo "Found " . count($matches) . " potential vessel rows:\n\n";
    
    foreach ($matches as $index => $match) {
        $vesselName = trim(strip_tags($match[1]));
        $voyageIn = trim(strip_tags($match[2]));
        $voyageOut = trim(strip_tags($match[3]));
        
        // Filter out header rows and empty data
        if (!empty($vesselName) && 
            !stripos($vesselName, 'vessel') && 
            !stripos($vesselName, 'name') && 
            strlen($vesselName) > 3 &&
            !empty($voyageIn) &&
            strlen($voyageIn) < 20) {
            
            echo "Row " . ($index + 1) . ":\n";
            echo "  Vessel: '$vesselName'\n";
            echo "  Voyage In: '$voyageIn'\n";
            echo "  Voyage Out: '$voyageOut'\n\n";
        }
    }
} else {
    echo "❌ No vessel rows found with Method 1\n\n";
}

// Method 2: Look for any table rows with recognizable patterns
echo "🔍 METHOD 2 - Looking for date patterns:\n";
$datePattern = '/(\d{1,2}\/\d{1,2}\/\d{4})/';
if (preg_match_all('/<tr[^>]*>(.*?<\/tr>)/si', $html, $allRows)) {
    $activeVessels = [];
    
    foreach ($allRows[1] as $row) {
        // Check if row contains a date (suggesting it's schedule data)
        if (preg_match($datePattern, $row)) {
            // Extract all cells
            if (preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cells)) {
                $cleanCells = [];
                foreach ($cells[1] as $cell) {
                    $cleanCell = trim(html_entity_decode(strip_tags($cell)));
                    if (!empty($cleanCell) && strlen($cleanCell) < 50) {
                        $cleanCells[] = $cleanCell;
                    }
                }
                
                if (count($cleanCells) >= 4) {
                    echo "Active vessel row found:\n";
                    foreach ($cleanCells as $index => $cell) {
                        echo "  [$index] '$cell'\n";
                    }
                    echo "\n";
                }
            }
        }
    }
}

// Method 3: Check if there are any vessels with future dates
echo "🔍 METHOD 3 - Future date search:\n";
$today = date('d/m/Y');
$tomorrow = date('d/m/Y', strtotime('+1 day'));
$dayAfter = date('d/m/Y', strtotime('+2 days'));

$futureDates = [$today, $tomorrow, $dayAfter];
foreach ($futureDates as $date) {
    if (stripos($html, $date) !== false) {
        echo "✅ Found date '$date' in schedule\n";
        
        // Show context around this date
        $pos = stripos($html, $date);
        $context = substr($html, max(0, $pos - 200), 400);
        echo "Context: " . htmlspecialchars($context) . "\n\n";
    } else {
        echo "❌ Date '$date' not found\n";
    }
}

echo str_repeat("=", 50) . "\n";
echo "🎯 Search complete - check results for next vessel candidate\n";
