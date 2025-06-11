<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PromoCodeController;

// Authentication routes group
Route::group(['prefix' => 'auth'], function () {
    // Public authentication routes
    Route::post('login', [AuthController::class, 'login']); // User login
    Route::post('register', [AuthController::class, 'register']); // User registration

    // Protected routes requiring authentication
    Route::group(['middleware' => 'auth:sanctum'], function() {
        Route::post('logout', [AuthController::class, 'logout']); // User logout
        Route::post('promo-codes/use', [PromoCodeController::class, 'use']); // Use/redeem promo code        
    });
    
    // Promo code validation with rate limiting
    Route::group(['middleware' => ['auth:sanctum', 'throttle:promo-validation']], function() {
        Route::post('promo-codes/validate', [PromoCodeController::class, 'validate']); // Validate promo code (rate limited)
    });
    
    // Admin-only routes requiring authentication and admin privileges
    Route::group(['middleware' => ['auth:sanctum', 'admin']], function() {
        Route::get('user', [AuthController::class, 'user']); // Get current authenticated user
        Route::get('users', [AuthController::class, 'all']); // Get all users
        Route::post('promo-codes', [PromoCodeController::class, 'create']); // Create new promo code
        Route::get('promo-codes', [PromoCodeController::class, 'index']); // List all promo codes
    });
});