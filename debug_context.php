<?php

echo "🔍 MARSA PRIDE Context Analysis\n";
echo str_repeat("=", 50) . "\n\n";

// Fetch data
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

echo "📄 HTML Size: " . number_format(strlen($html)) . " bytes\n\n";

// Find all occurrences of MARSA PRIDE and show context
echo "🔍 MARSA PRIDE CONTEXT ANALYSIS:\n";
$searchTerm = 'MARSA PRIDE';
$offset = 0;
$foundCount = 0;

while (($pos = stripos($html, $searchTerm, $offset)) !== false) {
    $foundCount++;
    echo "--- Occurrence #$foundCount at position $pos ---\n";
    
    // Show 500 characters before and after
    $contextStart = max(0, $pos - 500);
    $contextLength = min(1000, strlen($html) - $contextStart);
    $context = substr($html, $contextStart, $contextLength);
    
    // Clean up the context for readability
    $context = preg_replace('/\s+/', ' ', $context);
    
    // Highlight the search term
    $context = str_ireplace($searchTerm, "***{$searchTerm}***", $context);
    
    echo $context . "\n\n";
    
    $offset = $pos + strlen($searchTerm);
}

if ($foundCount === 0) {
    echo "❌ MARSA PRIDE not found in HTML\n";
} else {
    echo "✅ Found $foundCount occurrence(s) of MARSA PRIDE\n\n";
}

// Check for current date/time stamps
echo "📅 TIMESTAMP ANALYSIS:\n";
$currentDate = date('d/m/Y');
$yesterday = date('d/m/Y', strtotime('-1 day'));
$tomorrow = date('d/m/Y', strtotime('+1 day'));

echo "Looking for dates:\n";
echo "  - Yesterday: $yesterday\n";
echo "  - Today: $currentDate\n";
echo "  - Tomorrow: $tomorrow\n\n";

$dates = [$yesterday, $currentDate, $tomorrow];
foreach ($dates as $date) {
    $found = stripos($html, $date) !== false;
    echo "  - '$date' found: " . ($found ? "✅ YES" : "❌ NO") . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎯 Analysis complete - check context for vessel status\n";
