<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider // Extends Laravel's base RouteServiceProvider to configure routing and rate limiting
{
    public function boot(): void // Called when the service provider boots up
    {
        $this->configureRateLimiting(); // Set up rate limiting rules

        $this->routes(function () { // Define route groups and their middleware
            Route::prefix('api') // All API routes will be prefixed with /api
                ->middleware('api') // Apply API middleware group
                ->group(base_path('routes/api.php')); // Load routes from api.php file

            Route::middleware('web') // Apply web middleware group
                ->group(base_path('routes/web.php')); // Load routes from web.php file
        });
    }

    protected function configureRateLimiting(): void // Configure custom rate limiting rules
    {
        RateLimiter::for('api', function (Request $request) { // General API rate limiter
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()); // 60 requests per minute per user or IP
        });

        // Custom rate limiter for promo code validation
        RateLimiter::for('promo-validation', function (Request $request) { // Specific rate limiter for promo code validation
            return [
                // 10 attempts per minute per user (fallback to IP if no user)
                Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()), // Limit by authenticated user ID or IP address
                // 50 attempts per minute per IP (for additional protection)
                Limit::perMinute(50)->by($request->ip()) // Additional IP-based protection layer
            ];
        });
    }
}