<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PortTrackingController extends Controller
{
    private $portEndpoints = [
        [
            'name' => 'Kerry Logistics - KSSP Terminal',
            'url' => 'https://ksspterminaltracking.gmr.kerrylogistics.com/ContainerQuickSearch/Index',
            'type' => 'container_tracking',
            'method' => 'GET',
            'description' => 'Container quick search at KSSP Terminal'
        ],
        [
            'name' => 'LCB Port - Berth Schedule',
            'url' => 'https://www.lcb1.com/BerthSchedule',
            'type' => 'berth_schedule',
            'method' => 'GET',
            'description' => 'Laem Chabang Port berth scheduling',
            'params' => ['vesselName' => 'SMJK', 'voyageIn' => '', 'VoyageOut' => '']
        ],
        [
            'name' => 'LCIT - Vessel Information',
            'url' => 'https://www.lcit.com/vessel',
            'type' => 'vessel_info',
            'method' => 'GET',
            'description' => 'Laem Chabang International Terminal vessel info',
            'params' => ['vsl' => 'KHUNA BHUM', 'voy' => '058s']
        ],
        [
            'name' => 'TIPS - Ship Schedule',
            'url' => 'https://www.tips.co.th/container/shipSched/List',
            'type' => 'ship_schedule',
            'method' => 'GET',
            'description' => 'Total International Port Services ship schedule'
        ],
        [
            'name' => 'ESCO - Berth Schedule',
            'url' => 'https://service.esco.co.th/BerthSchedule',
            'type' => 'berth_schedule',
            'method' => 'GET',
            'description' => 'Eastern Sea Container Operations berth schedule'
        ],
        [
            'name' => 'Hutchison Ports Thailand',
            'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:13:::::',
            'type' => 'port_operations',
            'method' => 'GET',
            'description' => 'Hutchison Ports Thailand online services'
        ],
        [
            'name' => 'Generic Tracking System',
            'url' => 'http://182.52.90.106:7543/track/index.php',
            'type' => 'tracking',
            'method' => 'GET',
            'description' => 'Generic container/cargo tracking system'
        ],
        [
            'name' => 'DG-Net Service Tracking',
            'url' => 'https://www.dg-net.org/th/service-tracking',
            'type' => 'service_tracking',
            'method' => 'GET',
            'description' => 'DG-Net logistics service tracking'
        ],
        [
            'name' => 'ECTT - Eastern Container Terminal',
            'url' => 'https://www.ectt.co.th/th/',
            'type' => 'terminal_info',
            'method' => 'GET',
            'description' => 'Eastern Container Terminal Thailand'
        ]
    ];

    /**
     * Display the port tracking testing dashboard
     */
    public function index()
    {
        return view('admin.port-tracking', [
            'endpoints' => $this->portEndpoints,
            'totalEndpoints' => count($this->portEndpoints)
        ]);
    }

    /**
     * Test all port endpoints
     */
    public function testAllEndpoints()
    {
        $results = [];
        $summary = [
            'total' => count($this->portEndpoints),
            'success' => 0,
            'warning' => 0,
            'error' => 0,
            'total_time' => 0
        ];

        foreach ($this->portEndpoints as $index => $endpoint) {
            $result = $this->testSingleEndpoint($endpoint, $index);
            $results[] = $result;
            
            // Update summary
            $summary['total_time'] += $result['response_time'];
            
            if ($result['status'] === 'success') {
                $summary['success']++;
            } elseif ($result['status'] === 'warning') {
                $summary['warning']++;
            } else {
                $summary['error']++;
            }
        }

        return response()->json([
            'results' => $results,
            'summary' => $summary,
            'tested_at' => now()->toISOString()
        ]);
    }

    /**
     * Test a single endpoint
     */
    public function testEndpoint($index)
    {
        if (!isset($this->portEndpoints[$index])) {
            return response()->json(['error' => 'Endpoint not found'], 404);
        }

        $endpoint = $this->portEndpoints[$index];
        $result = $this->testSingleEndpoint($endpoint, $index);

        return response()->json($result);
    }
    /**
     * Test a single endpoint implementation
     */
    private function testSingleEndpoint($endpoint, $index)
    {
        $startTime = microtime(true);
        $result = [
            'index' => $index,
            'name' => $endpoint['name'],
            'url' => $endpoint['url'],
            'type' => $endpoint['type'],
            'method' => $endpoint['method'],
            'description' => $endpoint['description'],
            'status' => 'error',
            'response_time' => 0,
            'status_code' => null,
            'response_size' => 0,
            'headers' => [],
            'content_type' => null,
            'error_message' => null,
            'response_preview' => null,
            'cors_enabled' => false,
            'ssl_valid' => false,
            'redirect_count' => 0
        ];

        try {
            // Build URL with parameters if provided
            $url = $endpoint['url'];
            if (isset($endpoint['params']) && !empty($endpoint['params'])) {
                $url .= '?' . http_build_query($endpoint['params']);
            }

            // Create HTTP client with timeout and options
            $response = Http::timeout(15)
                ->withOptions([
                    'verify' => false, // Skip SSL verification for demo
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true
                    ]
                ])
                ->withHeaders([
                    'User-Agent' => 'CS-Shipping-LCB-Automation/1.0 (Laravel Testing)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'th-TH,th;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache'
                ])
                ->get($url);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds

            // Analyze response
            $result['response_time'] = $responseTime;
            $result['status_code'] = $response->status();
            $result['headers'] = $response->headers();
            $result['content_type'] = $response->header('Content-Type');
            $result['response_size'] = strlen($response->body());

            // Check for CORS headers
            $result['cors_enabled'] = $response->hasHeader('Access-Control-Allow-Origin');

            // Check SSL (for HTTPS URLs)
            $result['ssl_valid'] = strpos($url, 'https://') === 0;

            // Get response preview (first 500 characters)
            $body = $response->body();
            $result['response_preview'] = substr(strip_tags($body), 0, 500);

            // Determine status based on response
            if ($response->successful()) {
                if ($this->isValidPortResponse($body, $endpoint['type'])) {
                    $result['status'] = 'success';
                } else {
                    $result['status'] = 'warning';
                    $result['error_message'] = 'Response received but content may not be valid for ' . $endpoint['type'];
                }
            } elseif ($response->clientError()) {
                $result['status'] = 'warning';
                $result['error_message'] = 'Client error: ' . $response->status();
            } else {
                $result['status'] = 'error';
                $result['error_message'] = 'Server error: ' . $response->status();
            }

            // Additional analysis for specific endpoints
            $result = $this->analyzeEndpointSpecifics($result, $body, $endpoint);

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
            $result['status'] = 'error';
            $result['error_message'] = $e->getMessage();
            
            Log::warning('Port tracking test failed', [
                'endpoint' => $endpoint['name'],
                'url' => $endpoint['url'],
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Check if response is valid for the specific port type
     */
    private function isValidPortResponse($body, $type)
    {
        $body = strtolower($body);
        
        switch ($type) {
            case 'container_tracking':
                return strpos($body, 'container') !== false || 
                       strpos($body, 'tracking') !== false ||
                       strpos($body, 'search') !== false;
                       
            case 'berth_schedule':
                return strpos($body, 'berth') !== false || 
                       strpos($body, 'schedule') !== false ||
                       strpos($body, 'vessel') !== false;
                       
            case 'vessel_info':
                return strpos($body, 'vessel') !== false || 
                       strpos($body, 'ship') !== false ||
                       strpos($body, 'voyage') !== false;
                       
            case 'ship_schedule':
                return strpos($body, 'ship') !== false || 
                       strpos($body, 'schedule') !== false ||
                       strpos($body, 'departure') !== false;
                       
            case 'port_operations':
                return strpos($body, 'port') !== false || 
                       strpos($body, 'terminal') !== false ||
                       strpos($body, 'operation') !== false;
                       
            case 'tracking':
                return strpos($body, 'track') !== false || 
                       strpos($body, 'cargo') !== false ||
                       strpos($body, 'shipment') !== false;
                       
            case 'service_tracking':
                return strpos($body, 'service') !== false || 
                       strpos($body, 'tracking') !== false ||
                       strpos($body, 'logistics') !== false;
                       
            case 'terminal_info':
                return strpos($body, 'terminal') !== false || 
                       strpos($body, 'container') !== false ||
                       strpos($body, 'port') !== false;
                       
            default:
                return strlen($body) > 100; // Basic check for substantial content
        }
    }
    /**
     * Analyze endpoint-specific details
     */
    private function analyzeEndpointSpecifics($result, $body, $endpoint)
    {
        $analysis = [];
        
        // Extract specific information based on endpoint type
        switch ($endpoint['type']) {
            case 'container_tracking':
                $analysis['has_search_form'] = strpos($body, '<form') !== false;
                $analysis['has_input_fields'] = strpos($body, '<input') !== false;
                $analysis['requires_javascript'] = strpos($body, '<script') !== false;
                break;
                
            case 'berth_schedule':
                $analysis['has_schedule_table'] = strpos($body, '<table') !== false;
                $analysis['has_vessel_data'] = preg_match('/vessel|ship|berth/i', $body);
                $analysis['date_format_detected'] = $this->detectDateFormat($body);
                break;
                
            case 'vessel_info':
                $analysis['has_vessel_details'] = preg_match('/eta|etd|arrival|departure/i', $body);
                $analysis['voyage_info_present'] = strpos($body, 'voyage') !== false;
                break;
        }
        
        // Check for common port system indicators
        $analysis['has_login_system'] = strpos($body, 'login') !== false || strpos($body, 'signin') !== false;
        $analysis['has_api_endpoints'] = strpos($body, '/api/') !== false || strpos($body, '.json') !== false;
        $analysis['mobile_responsive'] = strpos($body, 'viewport') !== false;
        $analysis['uses_ajax'] = strpos($body, 'ajax') !== false || strpos($body, 'xhr') !== false;
        
        $result['analysis'] = $analysis;
        return $result;
    }

    /**
     * Detect date format in response
     */
    private function detectDateFormat($body)
    {
        if (preg_match('/\d{4}-\d{2}-\d{2}/', $body)) {
            return 'YYYY-MM-DD';
        } elseif (preg_match('/\d{2}\/\d{2}\/\d{4}/', $body)) {
            return 'DD/MM/YYYY';
        } elseif (preg_match('/\d{2}-\d{2}-\d{4}/', $body)) {
            return 'DD-MM-YYYY';
        }
        return 'unknown';
    }

    /**
     * Test specific container search
     */
    public function testContainerSearch(Request $request)
    {
        $request->validate([
            'container_number' => 'required|string|max:20',
            'endpoint_index' => 'required|integer|min:0'
        ]);

        $endpoint = $this->portEndpoints[$request->endpoint_index] ?? null;
        if (!$endpoint) {
            return response()->json(['error' => 'Endpoint not found'], 404);
        }

        // This would implement actual container search testing
        // For demo purposes, we'll simulate the search
        $containerNumber = $request->container_number;
        
        try {
            $searchUrl = $endpoint['url'];
            
            // Add container search parameters based on endpoint type
            $searchParams = $this->buildSearchParams($endpoint, $containerNumber);
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'CS-Shipping-LCB-Automation/1.0',
                    'Accept' => 'application/json,text/html',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ])
                ->post($searchUrl, $searchParams);

            return response()->json([
                'success' => true,
                'container_number' => $containerNumber,
                'endpoint' => $endpoint['name'],
                'status_code' => $response->status(),
                'response_preview' => substr($response->body(), 0, 500),
                'search_params' => $searchParams
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'container_number' => $containerNumber
            ], 500);
        }
    }

    /**
     * Build search parameters for different endpoints
     */
    private function buildSearchParams($endpoint, $containerNumber)
    {
        switch ($endpoint['type']) {
            case 'container_tracking':
                return [
                    'containerNo' => $containerNumber,
                    'container_number' => $containerNumber,
                    'search' => $containerNumber
                ];
                
            case 'berth_schedule':
                return [
                    'vesselName' => $containerNumber,
                    'vessel' => $containerNumber
                ];
                
            default:
                return ['q' => $containerNumber, 'search' => $containerNumber];
        }
    }

    /**
     * Generate test report for client demo
     */
    public function generateReport()
    {
        // Test all endpoints
        $testResults = json_decode($this->testAllEndpoints()->getContent(), true);
        
        $report = [
            'report_generated_at' => now()->format('Y-m-d H:i:s'),
            'project_info' => [
                'name' => 'CS Shipping LCB - Port Tracking Integration',
                'version' => '1.0.0',
                'total_endpoints' => count($this->portEndpoints)
            ],
            'summary' => $testResults['summary'],
            'detailed_results' => $testResults['results'],
            'recommendations' => $this->generateRecommendations($testResults['results']),
            'integration_feasibility' => $this->assessIntegrationFeasibility($testResults['results'])
        ];

        return response()->json($report);
    }

    /**
     * Generate recommendations based on test results
     */
    private function generateRecommendations($results)
    {
        $recommendations = [];
        
        foreach ($results as $result) {
            $rec = [
                'endpoint' => $result['name'],
                'priority' => 'medium',
                'integration_method' => 'web_scraping',
                'complexity' => 'medium',
                'notes' => []
            ];

            // Analyze based on response
            if ($result['status'] === 'success') {
                $rec['priority'] = 'high';
                $rec['notes'][] = 'Endpoint is accessible and responding correctly';
                
                if (isset($result['analysis']['has_api_endpoints']) && $result['analysis']['has_api_endpoints']) {
                    $rec['integration_method'] = 'api_direct';
                    $rec['complexity'] = 'low';
                    $rec['notes'][] = 'API endpoints detected - direct integration possible';
                }
                
                if (isset($result['analysis']['requires_javascript']) && $result['analysis']['requires_javascript']) {
                    $rec['integration_method'] = 'browser_automation';
                    $rec['complexity'] = 'high';
                    $rec['notes'][] = 'JavaScript required - use Selenium/Puppeteer';
                }
                
            } elseif ($result['status'] === 'warning') {
                $rec['priority'] = 'medium';
                $rec['notes'][] = 'Endpoint accessible but may require authentication or special handling';
                
            } else {
                $rec['priority'] = 'low';
                $rec['integration_method'] = 'manual_fallback';
                $rec['complexity'] = 'high';
                $rec['notes'][] = 'Endpoint not accessible - investigate authentication requirements';
            }

            $recommendations[] = $rec;
        }

        return $recommendations;
    }

    /**
     * Assess overall integration feasibility
     */
    private function assessIntegrationFeasibility($results)
    {
        $total = count($results);
        $accessible = count(array_filter($results, fn($r) => $r['status'] !== 'error'));
        $successRate = $total > 0 ? ($accessible / $total) * 100 : 0;

        return [
            'overall_feasibility' => $successRate >= 70 ? 'high' : ($successRate >= 40 ? 'medium' : 'low'),
            'success_rate' => round($successRate, 1),
            'accessible_endpoints' => $accessible,
            'total_endpoints' => $total,
            'estimated_development_time' => $this->estimateDevelopmentTime($results),
            'recommended_approach' => $successRate >= 70 ? 'hybrid_api_scraping' : 'primarily_web_scraping'
        ];
    }

    /**
     * Estimate development time for integration
     */
    private function estimateDevelopmentTime($results)
    {
        $totalWeeks = 0;
        
        foreach ($results as $result) {
            if ($result['status'] === 'success') {
                if (isset($result['analysis']['has_api_endpoints']) && $result['analysis']['has_api_endpoints']) {
                    $totalWeeks += 1; // API integration
                } elseif (isset($result['analysis']['requires_javascript']) && $result['analysis']['requires_javascript']) {
                    $totalWeeks += 3; // Browser automation
                } else {
                    $totalWeeks += 2; // Web scraping
                }
            } else {
                $totalWeeks += 4; // Complex integration with authentication
            }
        }

        return [
            'estimated_weeks' => $totalWeeks,
            'development_cost_estimate' => $totalWeeks * 50000, // ฿50,000 per week
            'testing_weeks' => ceil($totalWeeks * 0.3), // 30% additional for testing
        ];
    }
}