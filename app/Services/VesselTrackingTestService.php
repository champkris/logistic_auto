<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VesselTrackingTestService
{
    private $endpoints = [
        'kerry_logistics' => [
            'name' => 'Kerry Logistics (KSSP Terminal)',
            'url' => 'https://ksspterminaltracking.gmr.kerrylogistics.com/ContainerQuickSearch/Index',
            'type' => 'container_tracking',
            'method' => 'GET'
        ],
        'lcb1' => [
            'name' => 'LCB Port 1 (Berth Schedule)',
            'url' => 'https://www.lcb1.com/BerthSchedule',
            'type' => 'berth_schedule',
            'method' => 'GET',
            'params' => ['vesselName' => 'SMJK', 'voyageIn' => '', 'VoyageOut' => '']
        ],
        'lcit' => [
            'name' => 'LCIT (Vessel Information)',
            'url' => 'https://www.lcit.com/vessel',
            'type' => 'vessel_info',
            'method' => 'GET',
            'params' => ['vsl' => 'KHUNA BHUM', 'voy' => '058s']
        ],
        'tips' => [
            'name' => 'TIPS (Ship Schedule)',
            'url' => 'https://www.tips.co.th/container/shipSched/List',
            'type' => 'ship_schedule',
            'method' => 'GET'
        ],
        'esco' => [
            'name' => 'ESCO (Berth Schedule)',
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
        'custom_tracking' => [
            'name' => 'Custom Tracking System',
            'url' => 'http://182.52.90.106:7543/track/index.php',
            'type' => 'tracking',
            'method' => 'GET'
        ],
        'dg_net' => [
            'name' => 'DG-Net Service Tracking',
            'url' => 'https://www.dg-net.org/th/service-tracking',
            'type' => 'service_tracking',
            'method' => 'GET'
        ],
        'ectt' => [
            'name' => 'ECTT (Eastern Container Terminal)',
            'url' => 'https://www.ectt.co.th/th/',
            'type' => 'terminal',
            'method' => 'GET'
        ]
    ];

    public function getAllEndpoints()
    {
        return $this->endpoints;
    }

    public function testEndpoint($endpointKey)
    {
        $endpoint = $this->endpoints[$endpointKey] ?? null;
        
        if (!$endpoint) {
            return $this->createErrorResult($endpointKey, 'Endpoint not found');
        }

        $startTime = microtime(true);
        
        try {
            $url = $endpoint['url'];
            if (isset($endpoint['params'])) {
                $url .= '?' . http_build_query($endpoint['params']);
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                ])
                ->get($url);

            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);

            return $this->analyzeResponse($endpointKey, $endpoint, $response, $responseTime);

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            
            return $this->createErrorResult($endpointKey, $e->getMessage(), $responseTime);
        }
    }

    public function testAllEndpoints()
    {
        $results = [];
        
        foreach ($this->endpoints as $key => $endpoint) {
            $results[$key] = $this->testEndpoint($key);
            
            // Add small delay between requests to be respectful
            usleep(500000); // 0.5 second delay
        }

        return $results;
    }

    private function analyzeResponse($endpointKey, $endpoint, $response, $responseTime)
    {
        $statusCode = $response->status();
        $body = $response->body();
        $headers = $response->headers();
        
        // Analyze content
        $contentAnalysis = $this->analyzeContent($body, $endpoint['type']);
        
        // Determine status
        $status = $this->determineStatus($statusCode, $body, $contentAnalysis);
        
        return [
            'endpoint_key' => $endpointKey,
            'name' => $endpoint['name'],
            'url' => $endpoint['url'],
            'status' => $status,
            'status_code' => $statusCode,
            'response_time' => $responseTime,
            'content_type' => $headers['content-type'][0] ?? 'unknown',
            'content_length' => strlen($body),
            'content_analysis' => $contentAnalysis,
            'timestamp' => Carbon::now(),
            'accessible' => $statusCode >= 200 && $statusCode < 400,
            'has_vessel_data' => $contentAnalysis['has_vessel_data'],
            'automation_potential' => $this->assessAutomationPotential($contentAnalysis, $headers),
            'raw_response' => strlen($body) > 1000 ? substr($body, 0, 1000) . '...' : $body
        ];
    }

    private function analyzeContent($body, $type)
    {
        $analysis = [
            'has_vessel_data' => false,
            'has_forms' => false,
            'has_tables' => false,
            'has_search_functionality' => false,
            'data_format' => 'html',
            'vessel_indicators' => [],
            'tracking_elements' => []
        ];

        // Check for vessel-related keywords
        $vesselKeywords = ['vessel', 'ship', 'container', 'berth', 'schedule', 'eta', 'etd', 'port', 'terminal'];
        foreach ($vesselKeywords as $keyword) {
            if (stripos($body, $keyword) !== false) {
                $analysis['has_vessel_data'] = true;
                $analysis['vessel_indicators'][] = $keyword;
            }
        }

        // Check for forms (potential API endpoints)
        if (preg_match('/<form[^>]*>/i', $body)) {
            $analysis['has_forms'] = true;
        }

        // Check for tables (data display)
        if (preg_match('/<table[^>]*>/i', $body)) {
            $analysis['has_tables'] = true;
        }

        // Check for search functionality
        $searchIndicators = ['search', 'query', 'tracking', 'number', 'container'];
        foreach ($searchIndicators as $indicator) {
            if (stripos($body, $indicator) !== false) {
                $analysis['has_search_functionality'] = true;
                $analysis['tracking_elements'][] = $indicator;
            }
        }

        // Detect potential JSON/XML responses
        if (str_starts_with(trim($body), '{') || str_starts_with(trim($body), '[')) {
            $analysis['data_format'] = 'json';
        } elseif (str_starts_with(trim($body), '<') && !preg_match('/<!DOCTYPE html/i', $body)) {
            $analysis['data_format'] = 'xml';
        }

        return $analysis;
    }
    private function determineStatus($statusCode, $body, $contentAnalysis)
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            if ($contentAnalysis['has_vessel_data']) {
                return 'success';
            } elseif (strlen($body) > 100) {
                return 'accessible';
            } else {
                return 'warning';
            }
        } elseif ($statusCode >= 300 && $statusCode < 400) {
            return 'redirect';
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            return 'client_error';
        } else {
            return 'server_error';
        }
    }

    private function assessAutomationPotential($contentAnalysis, $headers)
    {
        $score = 0;
        $factors = [];

        // JSON/XML responses are easier to parse
        if ($contentAnalysis['data_format'] === 'json') {
            $score += 40;
            $factors[] = 'JSON API response';
        } elseif ($contentAnalysis['data_format'] === 'xml') {
            $score += 30;
            $factors[] = 'XML API response';
        }

        // Tables suggest structured data
        if ($contentAnalysis['has_tables']) {
            $score += 20;
            $factors[] = 'Structured table data';
        }

        // Forms suggest API endpoints
        if ($contentAnalysis['has_forms']) {
            $score += 15;
            $factors[] = 'Form-based API';
        }

        // Vessel data presence
        if ($contentAnalysis['has_vessel_data']) {
            $score += 15;
            $factors[] = 'Contains vessel data';
        }

        // CORS headers
        if (isset($headers['access-control-allow-origin'])) {
            $score += 10;
            $factors[] = 'CORS enabled';
        }

        return [
            'score' => min($score, 100),
            'level' => $this->getAutomationLevel($score),
            'factors' => $factors
        ];
    }

    private function getAutomationLevel($score)
    {
        if ($score >= 80) return 'Excellent';
        if ($score >= 60) return 'Good';
        if ($score >= 40) return 'Moderate';
        if ($score >= 20) return 'Difficult';
        return 'Very Difficult';
    }

    private function createErrorResult($endpointKey, $error, $responseTime = 0)
    {
        $endpoint = $this->endpoints[$endpointKey] ?? ['name' => 'Unknown', 'url' => 'Unknown'];
        
        return [
            'endpoint_key' => $endpointKey,
            'name' => $endpoint['name'],
            'url' => $endpoint['url'],
            'status' => 'error',
            'status_code' => 0,
            'response_time' => $responseTime,
            'error' => $error,
            'content_analysis' => ['has_vessel_data' => false],
            'timestamp' => Carbon::now(),
            'accessible' => false,
            'has_vessel_data' => false,
            'automation_potential' => ['score' => 0, 'level' => 'Error', 'factors' => []]
        ];
    }
}