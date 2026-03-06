<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('kerry-api', function (object $job) {
            return Limit::perMinute((int) env('KERRY_RATE_LIMIT', 40));
        });

        RateLimiter::for('lcb1-api', function (object $job) {
            return Limit::perMinute((int) env('LCB1_RATE_LIMIT', 40));
        });
    }
}
