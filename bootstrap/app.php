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
        // Daily scraping of vessel schedules from all terminals (3 AM)
        $schedule->command('vessel:scrape-schedules')
            ->dailyAt('03:00')
            ->name('daily-vessel-scrape')
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Daily vessel schedule scraping completed successfully');
            })
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Daily vessel schedule scraping failed');
            });

        // Check for due ETA schedules every minute
        $schedule->call(function () {
            $schedules = \App\Models\EtaCheckSchedule::dueForExecution()->get();

            foreach ($schedules as $schedule) {
                if ($schedule->shouldRunNow()) {
                    \Illuminate\Support\Facades\Artisan::call('shipments:check-eta', [
                        '--schedule-id' => $schedule->id,
                        '--limit' => 50,
                        '--delay' => 30
                    ]);

                    $schedule->markAsExecuted();

                    \Illuminate\Support\Facades\Log::info("Executed scheduled ETA check", [
                        'schedule_id' => $schedule->id,
                        'schedule_name' => $schedule->name,
                        'executed_at' => now()->format('Y-m-d H:i:s')
                    ]);
                }
            }
        })->everyMinute()->name('check-eta-schedules');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
