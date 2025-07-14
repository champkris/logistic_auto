<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\VesselTrackingTestService;
use Illuminate\Support\Facades\Log;

class VesselTrackingTester extends Component
{
    public $testResults = [];
    public $isTestingAll = false;
    public $testingEndpoint = null;
    public $selectedEndpoint = null;
    public $showDetails = [];
    public $saveToDatabase = true;
    public $summary = [
        'total' => 0,
        'successful' => 0,
        'accessible' => 0,
        'errors' => 0,
        'average_response_time' => 0
    ];

    protected $vesselTrackingService;

    public function mount()
    {
        $this->vesselTrackingService = new VesselTrackingTestService();
        $this->loadPreviousResults();
        $this->calculateSummary();
    }

    private function loadPreviousResults()
    {
        if ($this->saveToDatabase) {
            $previousResults = \App\Models\VesselTrackingTest::getLatestResults();
            
            foreach ($previousResults as $test) {
                $this->testResults[$test->endpoint_key] = [
                    'endpoint_key' => $test->endpoint_key,
                    'name' => $test->endpoint_name,
                    'url' => $test->endpoint_url,
                    'status' => $test->status,
                    'status_code' => $test->status_code,
                    'response_time' => $test->response_time,
                    'content_type' => $test->content_type,
                    'content_length' => $test->content_length,
                    'content_analysis' => $test->content_analysis ?? [],
                    'timestamp' => $test->tested_at,
                    'accessible' => $test->accessible,
                    'has_vessel_data' => $test->has_vessel_data,
                    'automation_potential' => $test->automation_potential ?? ['score' => 0, 'level' => 'Unknown', 'factors' => []],
                    'raw_response' => $test->raw_response,
                    'error' => $test->error_message
                ];
            }
        }
    }
    public function testAllEndpoints()
    {
        $this->isTestingAll = true;
        $this->testResults = [];
        
        try {
            $results = $this->vesselTrackingService->testAllEndpoints();
            
            foreach ($results as $key => $result) {
                $this->testResults[$key] = $result;
                
                // Save to database if enabled
                if ($this->saveToDatabase) {
                    \App\Models\VesselTrackingTest::createFromResult($result);
                }
            }
            
            $this->calculateSummary();
            session()->flash('message', 'All endpoints tested successfully! Results saved to database.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error testing endpoints: ' . $e->getMessage());
            Log::error('Vessel tracking test error: ' . $e->getMessage());
        } finally {
            $this->isTestingAll = false;
        }
    }

    public function testSingleEndpoint($endpointKey)
    {
        $this->testingEndpoint = $endpointKey;
        
        try {
            $result = $this->vesselTrackingService->testEndpoint($endpointKey);
            $this->testResults[$endpointKey] = $result;
            
            // Save to database if enabled
            if ($this->saveToDatabase) {
                \App\Models\VesselTrackingTest::createFromResult($result);
            }
            
            $this->calculateSummary();
            session()->flash('message', "Endpoint '{$result['name']}' tested successfully!");
        } catch (\Exception $e) {
            session()->flash('error', 'Error testing endpoint: ' . $e->getMessage());
            Log::error("Vessel tracking test error for {$endpointKey}: " . $e->getMessage());
        } finally {
            $this->testingEndpoint = null;
        }
    }

    public function toggleDetails($endpointKey)
    {
        $this->showDetails[$endpointKey] = !($this->showDetails[$endpointKey] ?? false);
    }

    public function clearResults()
    {
        $this->testResults = [];
        $this->showDetails = [];
        $this->calculateSummary();
        session()->flash('message', 'Results cleared successfully!');
    }

    public function exportResults()
    {
        $exportData = [
            'test_session' => [
                'timestamp' => now()->toISOString(),
                'total_endpoints' => count($this->testResults),
                'summary' => $this->summary
            ],
            'results' => $this->testResults
        ];

        // For now, just log the export data
        Log::info('Vessel tracking test results exported', $exportData);
        
        session()->flash('message', 'Results exported to logs!');
    }
    private function calculateSummary()
    {
        $total = count($this->testResults);
        $successful = 0;
        $accessible = 0;
        $errors = 0;
        $totalResponseTime = 0;

        foreach ($this->testResults as $result) {
            if ($result['status'] === 'success') {
                $successful++;
            } elseif ($result['accessible']) {
                $accessible++;
            } else {
                $errors++;
            }
            
            $totalResponseTime += $result['response_time'] ?? 0;
        }

        $this->summary = [
            'total' => $total,
            'successful' => $successful,
            'accessible' => $accessible,
            'errors' => $errors,
            'average_response_time' => $total > 0 ? round($totalResponseTime / $total, 2) : 0
        ];
    }

    public function getStatusColor($status)
    {
        return match($status) {
            'success' => 'text-green-600 bg-green-100',
            'accessible' => 'text-blue-600 bg-blue-100',
            'warning' => 'text-yellow-600 bg-yellow-100',
            'error', 'client_error', 'server_error' => 'text-red-600 bg-red-100',
            'redirect' => 'text-purple-600 bg-purple-100',
            default => 'text-gray-600 bg-gray-100'
        };
    }

    public function getAutomationPotentialColor($level)
    {
        return match($level) {
            'Excellent' => 'text-green-600 bg-green-100',
            'Good' => 'text-blue-600 bg-blue-100',
            'Moderate' => 'text-yellow-600 bg-yellow-100',
            'Difficult' => 'text-orange-600 bg-orange-100',
            'Very Difficult', 'Error' => 'text-red-600 bg-red-100',
            default => 'text-gray-600 bg-gray-100'
        };
    }

    public function render()
    {
        $endpoints = $this->vesselTrackingService->getAllEndpoints();
        
        return view('livewire.vessel-tracking-tester', [
            'endpoints' => $endpoints
        ])->layout('layouts.app');
    }
}