<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        // Rate-limit de ingesta por device-key. Valor desde config (config:cache-safe),
        // no env() directo en runtime.
        RateLimiter::for('ingesta', function (Request $request) {
            $porMinuto = (int) config('ingesta.rate_limit', 120);

            return Limit::perMinute(max(1, $porMinuto))
                ->by($request->header('X-Device-Key') ?: $request->ip());
        });
    }
}
