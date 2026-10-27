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
        // Sign-in limits: per email+IP and per IP, so one address cannot be hammered and one client cannot spray many.
        RateLimiter::for('otp-request', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')) . '|' . $r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('otp-verify', fn (Request $r) => [
            Limit::perMinute(10)->by(strtolower((string) $r->input('email')) . '|' . $r->ip()),
            Limit::perMinute(40)->by($r->ip()),
        ]);
    }
}
