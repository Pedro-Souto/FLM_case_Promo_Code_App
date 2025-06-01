<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PromoCodeController;

Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    Route::group(['middleware' => 'auth:sanctum'], function() {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('promo-codes/use', [PromoCodeController::class, 'use']);        
    });
    
    Route::group(['middleware' => ['auth:sanctum', 'throttle:promo-validation']], function() {
        Route::post('promo-codes/validate', [PromoCodeController::class, 'validate']);
    });
    
    Route::group(['middleware' => ['auth:sanctum', 'admin']], function() {
        Route::get('user', [AuthController::class, 'user']);
        Route::get('users', [AuthController::class, 'all']);
        Route::post('promo-codes', [PromoCodeController::class, 'create']);
        Route::get('promo-codes', [PromoCodeController::class, 'index']);
    });
});