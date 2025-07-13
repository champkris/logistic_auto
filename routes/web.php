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
