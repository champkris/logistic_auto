<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;

Route::get('/', Dashboard::class)->name('dashboard');

Route::get('/dashboard', Dashboard::class)->name('dashboard');


// Project Dashboard Routes
use App\Http\Controllers\Admin\ProjectDashboardController;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/project', [ProjectDashboardController::class, 'index'])->name('project.dashboard');
    Route::post('/project/task/{id}', [ProjectDashboardController::class, 'updateTask'])->name('project.task.update');
    Route::get('/project/stats', [ProjectDashboardController::class, 'getProjectStats'])->name('project.stats');
    Route::get('/project/tasks-by-phase', [ProjectDashboardController::class, 'getTasksByPhase'])->name('project.tasks.by.phase');
    Route::get('/project/milestones', [ProjectDashboardController::class, 'getUpcomingMilestones'])->name('project.milestones');
    Route::get('/project/export', [ProjectDashboardController::class, 'exportProjectData'])->name('project.export');
});

// Vessel Tracking Test Routes (for A2 demo)
use App\Http\Controllers\VesselTrackingTestController;

Route::prefix('vessel-tracking')->name('vessel.tracking.')->group(function () {
    Route::get('/test', [VesselTrackingTestController::class, 'index'])->name('test.dashboard');
    Route::get('/test/site/{siteKey}', [VesselTrackingTestController::class, 'testSite'])->name('test.site');
    Route::get('/test/all', [VesselTrackingTestController::class, 'testAllSites'])->name('test.all');
    Route::get('/config', [VesselTrackingTestController::class, 'getSiteConfig'])->name('config');
    Route::post('/search', [VesselTrackingTestController::class, 'searchVessel'])->name('search.vessel');
});

// Port Tracking Testing Routes
use App\Http\Controllers\Admin\PortTrackingController;

Route::prefix('admin')->name('admin.')->group(function () {
    // Existing project dashboard routes...
    Route::get('/project', [ProjectDashboardController::class, 'index'])->name('project.dashboard');
    Route::post('/project/task/{id}', [ProjectDashboardController::class, 'updateTask'])->name('project.task.update');
    Route::get('/project/stats', [ProjectDashboardController::class, 'getProjectStats'])->name('project.stats');
    Route::get('/project/tasks-by-phase', [ProjectDashboardController::class, 'getTasksByPhase'])->name('project.tasks.by.phase');
    Route::get('/project/milestones', [ProjectDashboardController::class, 'getUpcomingMilestones'])->name('project.milestones');
    Route::get('/project/export', [ProjectDashboardController::class, 'exportProjectData'])->name('project.export');
    
    // Port tracking testing routes
    Route::prefix('port-tracking')->name('port.')->group(function () {
        Route::get('/', [PortTrackingController::class, 'index'])->name('dashboard');
        Route::post('/test-all', [PortTrackingController::class, 'testAllEndpoints'])->name('test.all');
        Route::post('/test/{index}', [PortTrackingController::class, 'testEndpoint'])->name('test.single');
        Route::post('/test-container-search', [PortTrackingController::class, 'testContainerSearch'])->name('test.container');
        Route::get('/report', [PortTrackingController::class, 'generateReport'])->name('report');
    });
});

// Vessel Tracking Tester Routes
use App\Livewire\VesselTrackingTester;

Route::get('/vessel-tracking-tester', VesselTrackingTester::class)->name('vessel.tracking.tester');
