@extends('layouts.app')

@section('title', 'Port Tracking API Testing - CS Shipping LCB')

@section('content')
<div id="app" class="port-tracking-dashboard">
    <!-- Header Section -->
    <div class="header-section">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">🚢 Thailand Port & Logistics API Testing</h1>
                    <p class="page-subtitle">CS Shipping LCB - Vessel Tracking Integration Demo</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="header-actions">
                        <button class="btn btn-primary" @click="testAllEndpoints" :disabled="testing">
                            <span v-if="testing" class="spinner-border spinner-border-sm me-2" role="status"></span>
                            {{ testing ? 'Testing...' : 'Test All Endpoints' }}
                        </button>
                        <button class="btn btn-outline-info ms-2" @click="generateReport">
                            📊 Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-section" v-if="summary">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-3">
                    <div class="summary-card success">
                        <div class="card-icon">✅</div>
                        <div class="card-content">
                            <h3>{{ summary.success }}</h3>
                            <p>Successful</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card warning">
                        <div class="card-icon">⚠️</div>
                        <div class="card-content">
                            <h3>{{ summary.warning }}</h3>
                            <p>Warning</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card error">
                        <div class="card-icon">❌</div>
                        <div class="card-content">
                            <h3>{{ summary.error }}</h3>
                            <p>Failed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card info">
                        <div class="card-icon">⏱️</div>
                        <div class="card-content">
                            <h3>{{ formatTime(summary.total_time) }}</h3>
                            <p>Total Time</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Endpoints List -->
    <div class="endpoints-section">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="endpoints-grid">
                        <div v-for="(endpoint, index) in endpoints" :key="index" 
                             class="endpoint-card" 
                             :class="getEndpointCardClass(index)">
                            
                            <!-- Endpoint Header -->
                            <div class="endpoint-header" @click="toggleEndpoint(index)">
                                <div class="endpoint-info">
                                    <h4 class="endpoint-name">
                                        <span class="endpoint-number">#{{ index + 1 }}</span>
                                        {{ endpoint.name }}
                                    </h4>
                                    <p class="endpoint-url">{{ endpoint.url }}</p>
                                    <div class="endpoint-meta">
                                        <span class="badge badge-type">{{ endpoint.type }}</span>
                                        <span class="badge badge-method">{{ endpoint.method }}</span>
                                    </div>
                                </div>
                                
                                <div class="endpoint-status">
                                    <div class="status-indicators">
                                        <div v-if="results[index]" class="status-badge" :class="'status-' + results[index].status">
                                            {{ results[index].status.toUpperCase() }}
                                        </div>
                                        <div v-if="results[index] && results[index].response_time" class="response-time">
                                            {{ results[index].response_time }}ms
                                        </div>
                                    </div>
                                    
                                    <div class="endpoint-actions">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                @click.stop="testSingleEndpoint(index)"
                                                :disabled="testingEndpoints[index]">
                                            <span v-if="testingEndpoints[index]" class="spinner-border spinner-border-sm" role="status"></span>
                                            {{ testingEndpoints[index] ? 'Testing...' : 'Test' }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Endpoint Details (Expandable) -->
                            <div v-if="expandedEndpoints[index]" class="endpoint-details">
                                <div class="row">
                                    <!-- Basic Info -->
                                    <div class="col-md-6">
                                        <div class="detail-section">
                                            <h5>📋 Basic Information</h5>
                                            <ul class="detail-list">
                                                <li><strong>Description:</strong> {{ endpoint.description }}</li>
                                                <li><strong>Type:</strong> {{ endpoint.type }}</li>
                                                <li><strong>Method:</strong> {{ endpoint.method }}</li>
                                                <li v-if="endpoint.params"><strong>Parameters:</strong> {{ Object.keys(endpoint.params).join(', ') }}</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Test Results -->
                                    <div class="col-md-6" v-if="results[index]">
                                        <div class="detail-section">
                                            <h5>🔍 Test Results</h5>
                                            <ul class="detail-list">
                                                <li><strong>Status Code:</strong> 
                                                    <span :class="'status-code-' + Math.floor(results[index].status_code / 100)">
                                                        {{ results[index].status_code }}
                                                    </span>
                                                </li>
                                                <li><strong>Response Time:</strong> {{ results[index].response_time }}ms</li>
                                                <li><strong>Response Size:</strong> {{ formatBytes(results[index].response_size) }}</li>
                                                <li><strong>Content Type:</strong> {{ results[index].content_type || 'N/A' }}</li>
                                                <li><strong>CORS Enabled:</strong> 
                                                    <span :class="results[index].cors_enabled ? 'text-success' : 'text-warning'">
                                                        {{ results[index].cors_enabled ? 'Yes' : 'No' }}
                                                    </span>
                                                </li>
                                                <li><strong>SSL Valid:</strong> 
                                                    <span :class="results[index].ssl_valid ? 'text-success' : 'text-warning'">
                                                        {{ results[index].ssl_valid ? 'Yes' : 'No' }}
                                                    </span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Advanced Analysis -->
                                    <div class="col-12" v-if="results[index] && results[index].analysis">
                                        <div class="detail-section">
                                            <h5>🔬 Advanced Analysis</h5>
                                            <div class="analysis-grid">
                                                <div v-for="(value, key) in results[index].analysis" :key="key" class="analysis-item">
                                                    <span class="analysis-label">{{ formatLabel(key) }}:</span>
                                                    <span class="analysis-value" :class="typeof value === 'boolean' ? (value ? 'text-success' : 'text-muted') : ''">
                                                        {{ formatValue(value) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Response Preview -->
                                    <div class="col-12" v-if="results[index] && results[index].response_preview">
                                        <div class="detail-section">
                                            <h5>📄 Response Preview</h5>
                                            <div class="response-preview">
                                                <pre>{{ results[index].response_preview }}</pre>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Error Details -->
                                    <div class="col-12" v-if="results[index] && results[index].error_message">
                                        <div class="detail-section error-section">
                                            <h5>❌ Error Details</h5>
                                            <div class="error-message">
                                                {{ results[index].error_message }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Container Search Testing -->
    <div class="container-search-section">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>🔍 Container Search Testing</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="containerNumber" class="form-label">Container Number</label>
                                    <input type="text" class="form-control" id="containerNumber" 
                                           v-model="containerSearch.containerNumber" 
                                           placeholder="ABCD1234567">
                                </div>
                                <div class="col-md-4">
                                    <label for="endpointSelect" class="form-label">Test Endpoint</label>
                                    <select class="form-select" id="endpointSelect" v-model="containerSearch.endpointIndex">
                                        <option v-for="(endpoint, index) in endpoints" :key="index" :value="index">
                                            {{ endpoint.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button class="btn btn-success" @click="testContainerSearch" 
                                                :disabled="!containerSearch.containerNumber || testingContainer">
                                            <span v-if="testingContainer" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                            {{ testingContainer ? 'Searching...' : 'Test Container Search' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('styles')
<style>
.port-tracking-dashboard {
    background: #f8f9fa;
    min-height: 100vh;
}

.header-section {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 300;
    margin-bottom: 0.5rem;
}

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.summary-section {
    margin-bottom: 2rem;
}

.summary-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    transition: transform 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-4px);
}

.summary-card .card-icon {
    font-size: 2.5rem;
    margin-right: 1rem;
}

.summary-card.success { border-left: 5px solid #27ae60; }
.summary-card.warning { border-left: 5px solid #f39c12; }
.summary-card.error { border-left: 5px solid #e74c3c; }
.summary-card.info { border-left: 5px solid #3498db; }

.summary-card h3 {
    font-size: 2rem;
    font-weight: bold;
    margin: 0;
    color: #2c3e50;
}

.summary-card p {
    margin: 0;
    color: #7f8c8d;
    font-weight: 500;
}

.endpoints-grid {
    display: grid;
    gap: 1.5rem;
}

.endpoint-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: all 0.3s ease;
}

.endpoint-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.endpoint-card.status-success {
    border-left: 5px solid #27ae60;
}

.endpoint-card.status-warning {
    border-left: 5px solid #f39c12;
}

.endpoint-card.status-error {
    border-left: 5px solid #e74c3c;
}

.endpoint-header {
    padding: 1.5rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #ecf0f1;
    transition: background-color 0.3s ease;
}

.endpoint-header:hover {
    background-color: #f8f9fa;
}

.endpoint-info {
    flex: 1;
}

.endpoint-number {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-right: 0.5rem;
}

.endpoint-name {
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.endpoint-url {
    color: #7f8c8d;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    word-break: break-all;
}

.endpoint-meta {
    display: flex;
    gap: 0.5rem;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-type {
    background: #e8f4fd;
    color: #2980b9;
}

.badge-method {
    background: #e8f6f3;
    color: #27ae60;
}

.endpoint-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.status-indicators {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-success {
    background: #d4edda;
    color: #155724;
}

.status-warning {
    background: #fff3cd;
    color: #856404;
}

.status-error {
    background: #f8d7da;
    color: #721c24;
}

.response-time {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.endpoint-details {
    padding: 1.5rem;
    background: #f8f9fa;
    border-top: 1px solid #ecf0f1;
}

.detail-section {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    border-left: 4px solid #3498db;
}

.detail-section h5 {
    color: #2c3e50;
    margin-bottom: 1rem;
    font-weight: 600;
}

.detail-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.detail-list li {
    padding: 0.5rem 0;
    border-bottom: 1px solid #ecf0f1;
}

.detail-list li:last-child {
    border-bottom: none;
}

.status-code-2 { color: #27ae60; font-weight: 600; }
.status-code-3 { color: #f39c12; font-weight: 600; }
.status-code-4 { color: #e74c3c; font-weight: 600; }
.status-code-5 { color: #8e44ad; font-weight: 600; }

.analysis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
}

.analysis-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.analysis-label {
    font-weight: 500;
    text-transform: capitalize;
}

.response-preview {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 1rem;
    max-height: 200px;
    overflow-y: auto;
}

.response-preview pre {
    margin: 0;
    font-size: 0.8rem;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.error-section {
    border-left-color: #e74c3c;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 1rem;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
}

.container-search-section {
    margin-top: 2rem;
}

.btn {
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .endpoint-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .endpoint-status {
        align-items: flex-start;
    }
    
    .analysis-grid {
        grid-template-columns: 1fr;
    }
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s;
}

.fade-enter, .fade-leave-to {
    opacity: 0;
}
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script>
const { createApp } = Vue;

createApp({
    data() {
        return {
            endpoints: @json($endpoints),
            results: {},
            summary: null,
            testing: false,
            testingEndpoints: {},
            testingContainer: false,
            expandedEndpoints: {},
            containerSearch: {
                containerNumber: '',
                endpointIndex: 0
            },
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        }
    },
    methods: {
        async testAllEndpoints() {
            this.testing = true;
            this.results = {};
            this.summary = null;
            
            try {
                const response = await fetch('{{ route("admin.port.test.all") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.results) {
                    data.results.forEach((result, index) => {
                        this.$set(this.results, index, result);
                    });
                }
                
                this.summary = data.summary;
                
            } catch (error) {
                console.error('Error testing endpoints:', error);
                alert('Error testing endpoints. Please check the console for details.');
            } finally {
                this.testing = false;
            }
        },
        
        async testSingleEndpoint(index) {
            this.$set(this.testingEndpoints, index, true);
            
            try {
                const response = await fetch(`{{ url('admin/port-tracking/test') }}/${index}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    }
                });
                
                const result = await response.json();
                this.$set(this.results, index, result);
                
            } catch (error) {
                console.error(`Error testing endpoint ${index}:`, error);
                alert(`Error testing endpoint. Please check the console for details.`);
            } finally {
                this.$set(this.testingEndpoints, index, false);
            }
        },
        
        async testContainerSearch() {
            if (!this.containerSearch.containerNumber) {
                alert('Please enter a container number');
                return;
            }
            
            this.testingContainer = true;
            
            try {
                const response = await fetch('{{ route("admin.port.test.container") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        container_number: this.containerSearch.containerNumber,
                        endpoint_index: this.containerSearch.endpointIndex
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Container search test completed!\nEndpoint: ${result.endpoint}\nContainer: ${result.container_number}\nStatus: ${result.status_code}`);
                } else {
                    alert(`Container search failed: ${result.error}`);
                }
                
            } catch (error) {
                console.error('Error testing container search:', error);
                alert('Error testing container search. Please check the console for details.');
            } finally {
                this.testingContainer = false;
            }
        },
        
        async generateReport() {
            try {
                const response = await fetch('{{ route("admin.port.report") }}', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                const report = await response.json();
                
                // Create downloadable report
                const blob = new Blob([JSON.stringify(report, null, 2)], { 
                    type: 'application/json' 
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `port-tracking-report-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                alert('Report generated and downloaded successfully!');
                
            } catch (error) {
                console.error('Error generating report:', error);
                alert('Error generating report. Please check the console for details.');
            }
        },
        
        toggleEndpoint(index) {
            this.$set(this.expandedEndpoints, index, !this.expandedEndpoints[index]);
        },
        
        getEndpointCardClass(index) {
            if (this.results[index]) {
                return `status-${this.results[index].status}`;
            }
            return '';
        },
        
        formatTime(ms) {
            if (ms < 1000) return `${ms}ms`;
            return `${(ms / 1000).toFixed(1)}s`;
        },
        
        formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        formatLabel(key) {
            return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },
        
        formatValue(value) {
            if (typeof value === 'boolean') {
                return value ? 'Yes' : 'No';
            }
            return value;
        }
    },
    
    mounted() {
        // Add Vue 2 compatibility
        if (!this.$set) {
            this.$set = (obj, key, value) => {
                obj[key] = value;
                this.$forceUpdate();
            };
        }
        
        console.log('Port Tracking Dashboard initialized');
        console.log(`Total endpoints: ${this.endpoints.length}`);
    }
}).mount('#app');
</script>
@endpush
