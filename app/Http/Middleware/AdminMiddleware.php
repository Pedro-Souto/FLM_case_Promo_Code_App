<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware // Middleware to restrict access to admin-only routes
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response // Check if user has admin privileges
    {
        if (!$request->user() || !$request->user()->is_admin) { // User not authenticated or not admin
            return response()->json([ // Return JSON error response
                'message' => 'Access denied. Admin privileges required.' // Clear error message
            ], 403); // HTTP 403 Forbidden status code
        }

        return $next($request); // Continue to next middleware/controller if admin
    }
}
