<?php

namespace App\Services;

use Illuminate\Support\Str;

class VesselNameParser
{
    /**
     * Parse a full vessel name into its components
     * 
     * Examples:
     * "WAN HAI 517 S093" → vessel: "WAN HAI 517", voyage: "S093"
     * "SRI SUREE V.25080S" → vessel: "SRI SUREE", voyage: "V.25080S"
     * "ASL QINGDAO V.2508S" → vessel: "ASL QINGDAO", voyage: "V.2508S"
     */
    public static function parse($fullVesselName)
    {
        $fullName = trim($fullVesselName);
        
        if (empty($fullName)) {
            return [
                'vessel_name' => '',
                'voyage_code' => '',
                'full_name' => '',
                'parsing_method' => 'empty_input'
            ];
        }

        // Clean up the input
        $fullName = preg_replace('/\s+/', ' ', $fullName); // Multiple spaces to single space
        
        // Try different parsing patterns
        $patterns = [
            // Pattern 1: Space + V.xxxxx (e.g., "SRI SUREE V.25080S")
            '/^(.+?)\s+(V\.\d+[A-Z\d]*)$/i',

            // Pattern 2: Space + Vxxxxx (e.g., "EVER GIVEN V2024S")
            '/^(.+?)\s+(V\d+[A-Z\d]*)$/i',

            // Pattern 3: Space + single letter + numbers (e.g., "WAN HAI 517 S093", "SAMAL OQILRS1NC")
            '/^(.+?)\s+([A-Z]\d+[A-Z\d]*)$/i',

            // Pattern 4: Space + digit + alphanumeric mix (e.g., "SAMAL 0QILRS1NC")
            '/^(.+?)\s+(\d[A-Z\d]{5,})$/i',

            // Pattern 5: Space + numbers only (e.g., "MSC PARIS 2024")
            '/^(.+?)\s+(\d{3,})$/i',

            // Pattern 6: Space + combination with dot and spaces (e.g., "CUL NANSHA V. 2528S")
            '/^(.+?)\s+(V\.\s*\d+[A-Z\d]*)$/i',

            // Pattern 7: Complex voyage codes (e.g., "EVER BUILD V.0794-074S")
            '/^(.+?)\s+(V\.\d+-\d+[A-Z]*)$/i',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $fullName, $matches)) {
                return [
                    'vessel_name' => trim($matches[1]),
                    'voyage_code' => trim($matches[2]),
                    'full_name' => $fullName,
                    'parsing_method' => 'pattern_' . ($index + 1)
                ];
            }
        }

        // If no pattern matches, try to find last "word" that looks like a voyage code
        $words = explode(' ', $fullName);
        
        if (count($words) >= 2) {
            $lastWord = end($words);
            
            // Check if last word looks like a voyage code
            if (self::looksLikeVoyageCode($lastWord)) {
                array_pop($words); // Remove last word
                return [
                    'vessel_name' => implode(' ', $words),
                    'voyage_code' => $lastWord,
                    'full_name' => $fullName,
                    'parsing_method' => 'last_word_detection'
                ];
            }
        }

        // Fallback: treat entire input as vessel name
        return [
            'vessel_name' => $fullName,
            'voyage_code' => '',
            'full_name' => $fullName,
            'parsing_method' => 'no_voyage_detected'
        ];
    }

    /**
     * Check if a string looks like a voyage code
     */
    protected static function looksLikeVoyageCode($word)
    {
        $voyagePatterns = [
            '/^V\.\d+[A-Z\d]*$/i',  // V.2508S, V.25080S
            '/^V\d+[A-Z\d]*$/i',    // V2024S
            '/^[A-Z]\d+[A-Z\d]*$/i', // S093, N123A, OQILRS1NC
            '/^\d[A-Z\d]{5,}$/i',   // 0QILRS1NC (starts with digit, has letters)
            '/^\d{3,}[A-Z]*$/i',    // 2024, 2024S
            '/^V\.\d+-\d+[A-Z]*$/i', // V.0794-074S
        ];

        foreach ($voyagePatterns as $pattern) {
            if (preg_match($pattern, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse multiple vessel names (for batch processing)
     */
    public static function parseMultiple(array $vesselNames)
    {
        return array_map([self::class, 'parse'], $vesselNames);
    }

    /**
     * Get just the vessel name from a full vessel string
     */
    public static function getVesselName($fullVesselName)
    {
        return self::parse($fullVesselName)['vessel_name'];
    }

    /**
     * Get just the voyage code from a full vessel string
     */
    public static function getVoyageCode($fullVesselName)
    {
        return self::parse($fullVesselName)['voyage_code'];
    }

    /**
     * Format a vessel name for display
     */
    public static function formatForDisplay($vesselName, $voyageCode = null)
    {
        if (empty($voyageCode)) {
            return $vesselName;
        }
        
        return $vesselName . ' ' . $voyageCode;
    }

    /**
     * Validate if a vessel name parsing result is reliable
     */
    public static function isReliableParsing($parseResult)
    {
        $reliableMethods = [
            'pattern_1', 'pattern_2', 'pattern_3', 
            'pattern_5', 'pattern_6', 'last_word_detection'
        ];

        return in_array($parseResult['parsing_method'], $reliableMethods) && 
               !empty($parseResult['vessel_name']);
    }

    /**
     * Test the parser with sample data
     */
    public static function runTests()
    {
        $testCases = [
            'WAN HAI 517 S093',
            'SRI SUREE V.25080S', 
            'ASL QINGDAO V.2508S',
            'CUL NANSHA V. 2528S',
            'MARSA PRIDE V.528S',
            'EVER BUILD V.0794-074S',
            'MSC PARIS 2024',
            'OOCL SHENZHEN',
            'COSCO SHIPPING ARIES N123',
            '',
            'SINGLE NAME',
        ];

        $results = [];
        foreach ($testCases as $testCase) {
            $results[$testCase] = self::parse($testCase);
        }

        return $results;
    }
}
