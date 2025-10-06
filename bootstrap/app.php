<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',  // Added API routes
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'vessel-test-public/*',
        ]);
    })
    ->withSchedule(function ($schedule) {
        // Check for all due schedules every minute (both vessel scrape and ETA checks)
        $schedule->call(function () {
            $schedules = \App\Models\EtaCheckSchedule::dueForExecution()->get();

            foreach ($schedules as $scheduleItem) {
                if ($scheduleItem->shouldRunNow()) {
                    if ($scheduleItem->schedule_type === 'vessel_scrape') {
                        // Run vessel scraping
                        \Illuminate\Support\Facades\Artisan::call('vessel:scrape-schedules');

                        \Illuminate\Support\Facades\Log::info("Executed scheduled vessel scraping", [
                            'schedule_id' => $scheduleItem->id,
                            'schedule_name' => $scheduleItem->name,
                            'executed_at' => now()->format('Y-m-d H:i:s')
                        ]);
                    } else {
                        // Run ETA check
                        \Illuminate\Support\Facades\Artisan::call('shipments:check-eta', [
                            '--schedule-id' => $scheduleItem->id,
                            '--limit' => 50,
                            '--delay' => 30
                        ]);

                        \Illuminate\Support\Facades\Log::info("Executed scheduled ETA check", [
                            'schedule_id' => $scheduleItem->id,
                            'schedule_name' => $scheduleItem->name,
                            'executed_at' => now()->format('Y-m-d H:i:s')
                        ]);
                    }

                    $scheduleItem->markAsExecuted();
                }
            }
        })->everyMinute()->name('check-all-schedules');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
