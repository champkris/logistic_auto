<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VesselTrackingTestController extends Controller
{
    private $portSites = [
        'kerry_logistics' => [
            'name' => 'Kerry Logistics (KSS)',
            'url' => 'https://ksspterminaltracking.gmr.kerrylogistics.com/ContainerQuickSearch/Index',
            'type' => 'container',
            'method' => 'GET'
        ],
        'lcb1' => [
            'name' => 'LCB1 Terminal',
            'url' => 'https://www.lcb1.com/BerthSchedule',
            'type' => 'berth_schedule',
            'method' => 'GET'
        ],
        'lcit' => [
            'name' => 'LCIT Terminal',
            'url' => 'https://www.lcit.com/vessel',
            'type' => 'vessel',
            'method' => 'GET'
        ],
        'tips' => [
            'name' => 'TIPS Terminal',
            'url' => 'https://www.tips.co.th/container/shipSched/List',
            'type' => 'ship_schedule',
            'method' => 'GET'
        ],
        'esco' => [
            'name' => 'ESCO Terminal',
            'url' => 'https://service.esco.co.th/BerthSchedule',
            'type' => 'berth_schedule',
            'method' => 'GET'
        ],
        'hutchison' => [
            'name' => 'Hutchison Ports Thailand',
            'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:13:::::',
            'type' => 'port_system',
            'method' => 'GET'
        ],
        'track_system' => [
            'name' => 'Track System',
            'url' => 'http://182.52.90.106:7543/track/index.php',
            'type' => 'tracking',
            'method' => 'GET'
        ],
        'dg_net' => [
            'name' => 'DG-NET Tracking',
            'url' => 'https://www.dg-net.org/th/service-tracking',
            'type' => 'service_tracking',
            'method' => 'GET'
        ],
        'ectt' => [
            'name' => 'ECTT Terminal',
            'url' => 'https://www.ectt.co.th/th/',
            'type' => 'terminal',
            'method' => 'GET'
        ]
    ];

    /**
     * Display the vessel tracking test dashboard
     */
    public function index()
    {
        return view('vessel-tracking.test-dashboard', [
            'portSites' => $this->portSites
        ]);
    }

    /**
     * Test a single port website
     */
    public function testSite(Request $request, $siteKey)
    {
        $startTime = microtime(true);
        
        if (!isset($this->portSites[$siteKey])) {
            return response()->json([
                'success' => false,
                'error' => 'Unknown site key',
                'site' => $siteKey
            ], 404);
        }

        $site = $this->portSites[$siteKey];
        
        try {
            // Configure HTTP client with common headers
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5,th;q=0.3',
                'Accept-Encoding' => 'gzip, deflate, br',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ])
            ->timeout(15)
            ->retry(2, 1000)
            ->get($site['url'], $request->query());

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            $result = [
                'success' => true,
                'site_key' => $siteKey,
                'site_name' => $site['name'],
                'url' => $site['url'],
                'status_code' => $response->status(),
                'response_time_ms' => $responseTime,
                'content_type' => $response->header('Content-Type'),
                'content_length' => strlen($response->body()),
                'timestamp' => Carbon::now()->toISOString(),
                'headers' => $response->headers(),
            ];

            // Parse content based on site type
            $parsedData = $this->parseContent($siteKey, $response->body(), $site['type']);
            $result['parsed_data'] = $parsedData;

            // Extract key information preview
            $result['preview'] = $this->generatePreview($response->body(), $site['type']);

            return response()->json($result);

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            Log::error("Vessel tracking test failed for {$siteKey}", [
                'error' => $e->getMessage(),
                'url' => $site['url']
            ]);

            return response()->json([
                'success' => false,
                'site_key' => $siteKey,
                'site_name' => $site['name'],
                'url' => $site['url'],
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'response_time_ms' => $responseTime,
                'timestamp' => Carbon::now()->toISOString(),
            ], 500);
        }
    }
    /**
     * Test all port websites simultaneously
     */
    public function testAllSites(Request $request)
    {
        $results = [];
        $startTime = microtime(true);

        foreach ($this->portSites as $siteKey => $site) {
            try {
                $siteStartTime = microtime(true);
                
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5,th;q=0.3',
                ])
                ->timeout(10)
                ->get($site['url']);

                $siteEndTime = microtime(true);
                $siteResponseTime = round(($siteEndTime - $siteStartTime) * 1000, 2);

                $results[$siteKey] = [
                    'success' => true,
                    'site_name' => $site['name'],
                    'url' => $site['url'],
                    'status_code' => $response->status(),
                    'response_time_ms' => $siteResponseTime,
                    'content_length' => strlen($response->body()),
                    'content_type' => $response->header('Content-Type'),
                    'accessibility' => $response->successful() ? 'accessible' : 'limited',
                    'preview' => $this->generatePreview($response->body(), $site['type']),
                ];

            } catch (\Exception $e) {
                $results[$siteKey] = [
                    'success' => false,
                    'site_name' => $site['name'],
                    'url' => $site['url'],
                    'error' => $e->getMessage(),
                    'accessibility' => 'blocked',
                ];
            }
        }

        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2);

        return response()->json([
            'summary' => [
                'total_sites' => count($this->portSites),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
                'failed' => count(array_filter($results, fn($r) => !$r['success'])),
                'total_time_ms' => $totalTime,
                'timestamp' => Carbon::now()->toISOString(),
            ],
            'results' => $results
        ]);
    }

    /**
     * Parse content based on site type
     */
    private function parseContent($siteKey, $content, $type)
    {
        $data = [
            'type' => $type,
            'data_found' => false,
            'extracted_info' => []
        ];

        try {
            // Create DOMDocument for HTML parsing
            $dom = new \DOMDocument();
            @$dom->loadHTML($content);
            $xpath = new \DOMXPath($dom);

            switch ($siteKey) {
                case 'kerry_logistics':
                    $data = $this->parseKerryLogistics($xpath, $content);
                    break;
                case 'lcb1':
                    $data = $this->parseLCB1($xpath, $content);
                    break;
                case 'lcit':
                    $data = $this->parseLCIT($xpath, $content);
                    break;
                case 'tips':
                    $data = $this->parseTIPS($xpath, $content);
                    break;
                case 'esco':
                    $data = $this->parseESCO($xpath, $content);
                    break;
                case 'hutchison':
                    $data = $this->parseHutchison($xpath, $content);
                    break;
                default:
                    $data = $this->parseGeneric($xpath, $content, $type);
                    break;
            }

        } catch (\Exception $e) {
            $data['error'] = $e->getMessage();
        }

        return $data;
    }

    /**
     * Parse Kerry Logistics content
     */
    private function parseKerryLogistics($xpath, $content)
    {
        $data = ['type' => 'container', 'data_found' => false, 'extracted_info' => []];
        
        // Look for container search forms and vessel information
        $forms = $xpath->query('//form');
        $tables = $xpath->query('//table');
        
        if ($forms->length > 0) {
            $data['data_found'] = true;
            $data['extracted_info']['form_found'] = true;
            $data['extracted_info']['search_capability'] = 'container_tracking';
        }

        // Look for vessel or container related keywords
        if (preg_match('/vessel|container|shipment|tracking/i', $content)) {
            $data['extracted_info']['contains_tracking_keywords'] = true;
        }

        return $data;
    }

    /**
     * Parse LCB1 terminal content  
     */
    private function parseLCB1($xpath, $content)
    {
        $data = ['type' => 'berth_schedule', 'data_found' => false, 'extracted_info' => []];
        
        // Look for berth schedule tables
        $tables = $xpath->query('//table[contains(@class, "schedule") or contains(@class, "berth")]');
        
        if ($tables->length > 0) {
            $data['data_found'] = true;
            $data['extracted_info']['schedule_table_found'] = true;
        }

        // Look for vessel names and berth information
        if (preg_match_all('/berth|vessel|ETA|ETD|arrival|departure/i', $content, $matches)) {
            $data['extracted_info']['berth_keywords_count'] = count($matches[0]);
        }

        return $data;
    }
    /**
     * Parse LCIT terminal content
     */
    private function parseLCIT($xpath, $content)
    {
        $data = ['type' => 'vessel', 'data_found' => false, 'extracted_info' => []];
        
        // Look for vessel information tables or forms
        $vesselForms = $xpath->query('//form[contains(@action, "vessel") or contains(@method, "get")]');
        $vesselTables = $xpath->query('//table');
        
        if ($vesselForms->length > 0 || $vesselTables->length > 0) {
            $data['data_found'] = true;
            $data['extracted_info']['vessel_search_available'] = true;
        }

        // Extract vessel-related information
        if (preg_match_all('/vessel|voyage|container|schedule/i', $content, $matches)) {
            $data['extracted_info']['vessel_keywords_count'] = count($matches[0]);
        }

        return $data;
    }

    /**
     * Parse TIPS terminal content
     */
    private function parseTIPS($xpath, $content)
    {
        $data = ['type' => 'ship_schedule', 'data_found' => false, 'extracted_info' => []];
        
        // Look for ship schedule information
        $scheduleTables = $xpath->query('//table[contains(@class, "schedule") or contains(@id, "ship")]');
        
        if ($scheduleTables->length > 0) {
            $data['data_found'] = true;
            $data['extracted_info']['schedule_data_found'] = true;
        }

        // Look for shipping related terms
        if (preg_match_all('/ship|schedule|arrival|departure|berth|terminal/i', $content, $matches)) {
            $data['extracted_info']['shipping_keywords_count'] = count($matches[0]);
        }

        return $data;
    }

    /**
     * Parse ESCO terminal content
     */
    private function parseESCO($xpath, $content)
    {
        $data = ['type' => 'berth_schedule', 'data_found' => false, 'extracted_info' => []];
        
        // Look for berth schedule
        $berthElements = $xpath->query('//*[contains(@class, "berth") or contains(@id, "schedule")]');
        
        if ($berthElements->length > 0) {
            $data['data_found'] = true;
            $data['extracted_info']['berth_schedule_found'] = true;
        }

        return $data;
    }

    /**
     * Parse Hutchison Ports content
     */
    private function parseHutchison($xpath, $content)
    {
        $data = ['type' => 'port_system', 'data_found' => false, 'extracted_info' => []];
        
        // Look for Oracle APEX application elements
        $apexElements = $xpath->query('//*[contains(@class, "apex") or contains(@id, "apex")]');
        
        if ($apexElements->length > 0 || strpos($content, 'apex') !== false) {
            $data['data_found'] = true;
            $data['extracted_info']['apex_system_detected'] = true;
        }

        return $data;
    }

    /**
     * Generic content parser for unknown sites
     */
    private function parseGeneric($xpath, $content, $type)
    {
        $data = ['type' => $type, 'data_found' => false, 'extracted_info' => []];
        
        // Look for common shipping/port related elements
        $tables = $xpath->query('//table');
        $forms = $xpath->query('//form');
        
        $data['extracted_info']['tables_count'] = $tables->length;
        $data['extracted_info']['forms_count'] = $forms->length;
        
        // Search for shipping keywords
        $keywords = ['vessel', 'container', 'shipment', 'berth', 'terminal', 'port', 'schedule', 'tracking'];
        $foundKeywords = [];
        
        foreach ($keywords as $keyword) {
            if (preg_match_all('/' . $keyword . '/i', $content, $matches)) {
                $foundKeywords[$keyword] = count($matches[0]);
            }
        }
        
        $data['extracted_info']['keywords_found'] = $foundKeywords;
        $data['data_found'] = !empty($foundKeywords);
        
        return $data;
    }

    /**
     * Generate content preview
     */
    private function generatePreview($content, $type)
    {
        $preview = [
            'title' => '',
            'description' => '',
            'key_elements' => [],
            'content_sample' => ''
        ];

        try {
            // Extract title
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $content, $matches)) {
                $preview['title'] = trim(strip_tags($matches[1]));
            }

            // Extract meta description
            if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/is', $content, $matches)) {
                $preview['description'] = trim($matches[1]);
            }

            // Extract key elements based on type
            switch ($type) {
                case 'container':
                case 'tracking':
                    $preview['key_elements'] = $this->extractTrackingElements($content);
                    break;
                case 'berth_schedule':
                case 'ship_schedule':
                    $preview['key_elements'] = $this->extractScheduleElements($content);
                    break;
                case 'vessel':
                    $preview['key_elements'] = $this->extractVesselElements($content);
                    break;
                default:
                    $preview['key_elements'] = $this->extractGenericElements($content);
                    break;
            }

            // Get content sample (first 500 chars of visible text)
            $textContent = strip_tags($content);
            $textContent = preg_replace('/\s+/', ' ', $textContent);
            $preview['content_sample'] = substr(trim($textContent), 0, 500) . '...';

        } catch (\Exception $e) {
            $preview['error'] = $e->getMessage();
        }

        return $preview;
    }
    /**
     * Extract tracking-related elements
     */
    private function extractTrackingElements($content)
    {
        $elements = [];
        
        // Look for input fields related to tracking
        if (preg_match_all('/<input[^>]*(?:name|id)=["\'][^"\']*(?:container|track|search|vessel)[^"\']*["\'][^>]*>/i', $content, $matches)) {
            $elements['tracking_inputs'] = count($matches[0]);
        }

        // Look for tracking numbers or container patterns
        if (preg_match_all('/[A-Z]{4}\d{7}/', $content, $matches)) {
            $elements['container_numbers_found'] = count($matches[0]);
        }

        return $elements;
    }

    /**
     * Extract schedule-related elements
     */
    private function extractScheduleElements($content)
    {
        $elements = [];
        
        // Look for date/time patterns
        if (preg_match_all('/\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}/', $content, $matches)) {
            $elements['dates_found'] = count($matches[0]);
        }

        // Look for time patterns
        if (preg_match_all('/\d{1,2}:\d{2}/', $content, $matches)) {
            $elements['times_found'] = count($matches[0]);
        }

        // Look for berth numbers
        if (preg_match_all('/berth\s*[#:]?\s*\d+/i', $content, $matches)) {
            $elements['berth_numbers_found'] = count($matches[0]);
        }

        return $elements;
    }

    /**
     * Extract vessel-related elements
     */
    private function extractVesselElements($content)
    {
        $elements = [];
        
        // Look for vessel name patterns (usually uppercase)
        if (preg_match_all('/[A-Z\s]{5,}/', $content, $matches)) {
            $elements['potential_vessel_names'] = min(10, count($matches[0])); // Limit to 10
        }

        // Look for voyage numbers
        if (preg_match_all('/\d{3,4}[NSEW]?/', $content, $matches)) {
            $elements['voyage_numbers_found'] = count($matches[0]);
        }

        return $elements;
    }

    /**
     * Extract generic elements
     */
    private function extractGenericElements($content)
    {
        $elements = [];
        
        // Count forms and tables
        $elements['forms_count'] = substr_count(strtolower($content), '<form');
        $elements['tables_count'] = substr_count(strtolower($content), '<table');
        $elements['links_count'] = substr_count(strtolower($content), '<a ');
        
        // Look for common shipping terms
        $shippingTerms = ['vessel', 'container', 'cargo', 'shipment', 'terminal', 'port', 'berth'];
        foreach ($shippingTerms as $term) {
            $count = substr_count(strtolower($content), strtolower($term));
            if ($count > 0) {
                $elements['term_' . $term] = $count;
            }
        }

        return $elements;
    }

    /**
     * Get site configuration for frontend
     */
    public function getSiteConfig()
    {
        return response()->json([
            'sites' => $this->portSites,
            'total_sites' => count($this->portSites),
            'last_updated' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Demo specific vessel search
     */
    public function searchVessel(Request $request)
    {
        $vesselName = $request->input('vessel_name');
        $voyage = $request->input('voyage');
        
        if (!$vesselName) {
            return response()->json(['error' => 'Vessel name is required'], 400);
        }

        $results = [];
        
        // Test specific sites that support vessel search
        $vesselSites = ['lcb1', 'lcit', 'tips'];
        
        foreach ($vesselSites as $siteKey) {
            $site = $this->portSites[$siteKey];
            
            try {
                $url = $site['url'];
                
                // Add vessel parameters based on site
                switch ($siteKey) {
                    case 'lcb1':
                        $url .= '?vesselName=' . urlencode($vesselName);
                        if ($voyage) $url .= '&voyageIn=' . urlencode($voyage);
                        break;
                    case 'lcit':
                        $url .= '?vsl=' . urlencode($vesselName);
                        if ($voyage) $url .= '&voy=' . urlencode($voyage);
                        break;
                    case 'tips':
                        $url .= '?vessel=' . urlencode($vesselName);
                        break;
                }

                $startTime = microtime(true);
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->timeout(10)
                ->get($url);

                $endTime = microtime(true);
                $responseTime = round(($endTime - $startTime) * 1000, 2);

                $results[$siteKey] = [
                    'success' => true,
                    'site_name' => $site['name'],
                    'url' => $url,
                    'status_code' => $response->status(),
                    'response_time_ms' => $responseTime,
                    'vessel_data_found' => $this->checkVesselInContent($response->body(), $vesselName),
                    'preview' => $this->generatePreview($response->body(), 'vessel')
                ];

            } catch (\Exception $e) {
                $results[$siteKey] = [
                    'success' => false,
                    'site_name' => $site['name'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'vessel_name' => $vesselName,
            'voyage' => $voyage,
            'search_results' => $results,
            'timestamp' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Check if vessel information is found in content
     */
    private function checkVesselInContent($content, $vesselName)
    {
        // Look for the vessel name in the content
        $found = stripos($content, $vesselName) !== false;
        
        $vesselInfo = [
            'vessel_name_found' => $found,
            'content_indicators' => []
        ];

        // Look for scheduling indicators
        if (preg_match('/ETA|ETD|arrival|departure/i', $content)) {
            $vesselInfo['content_indicators'][] = 'schedule_data';
        }

        if (preg_match('/berth|terminal|port/i', $content)) {
            $vesselInfo['content_indicators'][] = 'location_data';
        }

        if (preg_match('/container|cargo|shipment/i', $content)) {
            $vesselInfo['content_indicators'][] = 'cargo_data';
        }

        return $vesselInfo;
    }
}