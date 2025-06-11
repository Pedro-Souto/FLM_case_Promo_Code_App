<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__)) // Configure Laravel application with base path
    ->withRouting( // Define routing configuration
        web: __DIR__.'/../routes/web.php', // Web routes file path
        api: __DIR__.'/../routes/api.php', // API routes file path
        commands: __DIR__.'/../routes/console.php', // Console commands routes
        health: '/up', // Health check endpoint
    )
    ->withMiddleware(function (Middleware $middleware) { // Configure middleware aliases
        $middleware->alias([ // Register middleware aliases
            'admin' => \App\Http\Middleware\AdminMiddleware::class, // Alias 'admin' for AdminMiddleware
        ]);
    })
    ->withProviders([ // Register service providers
        App\Providers\RouteServiceProvider::class, // Custom route service provider for rate limiting
    ])
    ->withExceptions(function (Exceptions $exceptions) { // Configure exception handling
        //
    })->create(); // Create and return the application instance
