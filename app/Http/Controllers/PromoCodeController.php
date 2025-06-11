<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class PromoCodeController extends Controller // Handles promo code management and validation
{
    /**
     * Create a new promo code (Admin only)
     */
    public function create(Request $request): JsonResponse // Creates new promo code with validation
    {
        $validator = Validator::make($request->all(), [ // Validate promo code creation data
            'code' => 'sometimes|string|max:20|unique:promo_codes', // Optional custom code, must be unique
            'type' => 'required|in:percentage,value', // Must be percentage or fixed value discount
            'value' => 'required|numeric|min:0', // Discount amount, must be positive
            'expiry_date' => 'sometimes|date|after:now', // Optional expiry, must be future date
            'max_usages' => 'sometimes|integer|min:1', // Optional total usage limit
            'max_usages_per_user' => 'sometimes|integer|min:1', // Optional per-user usage limit
            'user_ids' => 'sometimes|array', // Optional array of user IDs for restricted access
            'user_ids.*' => 'exists:users,id' // Each user ID must exist in users table
        ]);

        if ($validator->fails()) { // If validation fails
            return response()->json([
                'message' => 'Validation failed', // Error message
                'errors' => $validator->errors() // Return validation errors
            ], 422); // HTTP 422 Unprocessable Entity
        }

        // Additional validation for percentage type
        if ($request->type === 'percentage' && $request->value > 100) { // Percentage cannot exceed 100%
            return response()->json([
                'message' => 'Percentage value cannot exceed 100' // Business logic error
            ], 422); // HTTP 422 Unprocessable Entity
        }

        $promoCode = PromoCode::create([ // Create new promo code record
            'code' => $request->code ?? PromoCode::generateCode(), // Use provided code or generate unique one
            'type' => $request->type, // Discount type (percentage/value)
            'value' => $request->value, // Discount amount
            'expiry_date' => $request->expiry_date, // Optional expiration date
            'max_usages' => $request->max_usages, // Optional total usage limit
            'max_usages_per_user' => $request->max_usages_per_user, // Optional per-user limit
            'created_by' => $request->user()->id // Track which admin created this code
        ]);

        // Attach specific users if provided
        if ($request->user_ids) { // If promo code is restricted to specific users
            $promoCode->users()->attach($request->user_ids); // Create user access records in pivot table
        }

        return response()->json([
            'message' => 'Promo code created successfully', // Success message
            'promo_code' => $promoCode->load('users') // Return promo code with associated users
        ], 201); // HTTP 201 Created
    }

    /**
     * Get all promo codes (Admin only) with caching
     */
    public function index(): JsonResponse // Retrieves all promo codes with relationships
    {
        $promoCodes = Cache::remember( // Cache the query for 10 minutes
            'all_promo_codes', // Cache key for all promo codes
            now()->addMinutes(10), // Cache expiration time
            function () {
                return PromoCode::with(['users:id,name,email', 'creator:id,name']) // Eager load relationships
                              ->orderBy('created_at', 'desc') // Order by newest first
                              ->get(); // Get all records
            }
        );

        return response()->json($promoCodes); // Return cached or fresh data
    }

    /**
     * Validate promo code and calculate discount (Rate Limited & Cached)
     */
    public function validate(Request $request): JsonResponse // Validates promo code and calculates discount
    {
        $validator = Validator::make($request->all(), [ // Validate validation request data
            'price' => 'required|numeric|min:0', // Original price must be positive number
            'promo_code' => 'required|string' // Promo code is required
        ]);

        if ($validator->fails()) { // If validation fails
            return response()->json([
                'message' => 'Validation failed', // Error message
                'errors' => $validator->errors() // Return validation errors
            ], 422); // HTTP 422 Unprocessable Entity
        }

        $price = $request->price; // Original price from request
        $code = $request->promo_code; // Promo code from request
        $user = $request->user(); // Current authenticated user

        // Use cached lookup
        $promoCode = PromoCode::findByCodeCached($code); // Find promo code with caching

        // Check if promo code exists
        if (!$promoCode) { // Promo code not found in database
            return response()->json([
                'message' => 'Promo code not found', // User-friendly message
                'error' => 'PROMO_CODE_NOT_FOUND' // Error code for frontend
            ], 404); // HTTP 404 Not Found
        }

        // Check if promo code is active
        if (!$promoCode->is_active) { // Promo code has been deactivated
            return response()->json([
                'message' => 'Promo code is inactive', // User-friendly message
                'error' => 'PROMO_CODE_INACTIVE' // Error code for frontend
            ], 404); // HTTP 404 Not Found
        }

        // Check if promo code is expired
        if ($promoCode->expiry_date && $promoCode->expiry_date->isPast()) { // Check expiration date
            return response()->json([
                'message' => 'Promo code has expired', // User-friendly message
                'error' => 'PROMO_CODE_EXPIRED' // Error code for frontend
            ], 404); // HTTP 404 Not Found
        }

        // Check if max usages exceeded
        if ($promoCode->max_usages && $promoCode->current_usages >= $promoCode->max_usages) { // Total usage limit check
            return response()->json([
                'message' => 'Promo code usage limit exceeded', // User-friendly message
                'error' => 'PROMO_CODE_USAGE_LIMIT_EXCEEDED' // Error code for frontend
            ], 404); // HTTP 404 Not Found
        }

        // Check if user can use this promo code (uses cached methods)
        if (!$promoCode->canBeUsedByUser($user)) { // Comprehensive user eligibility check
            return response()->json([
                'message' => 'Promo code is not available for this user', // User-friendly message
                'error' => 'PROMO_CODE_NOT_AVAILABLE_FOR_USER' // Error code for frontend
            ], 404); // HTTP 404 Not Found
        }

        // Check if user has already used this promo code
        $userUsages = $promoCode->getUserUsageCount($user->id); // Get cached user usage count
        if ($promoCode->max_usages_per_user && $userUsages >= $promoCode->max_usages_per_user) { // Per-user limit check
            return response()->json([
                'message' => 'User has exceeded the maximum usage limit for this promo code', // User-friendly message
                'error' => 'PROMO_CODE_USER_USAGE_LIMIT_EXCEEDED' // Error code for frontend
            ], 404); // HTTP 404 Not Found
        }

        // Calculate discount
        $discountAmount = 0; // Initialize discount amount
        if ($promoCode->type === 'percentage') { // Percentage-based discount
            $discountAmount = ($price * $promoCode->value) / 100; // Calculate percentage discount
        } else { // Fixed value discount
            $discountAmount = min($promoCode->value, $price); // Discount cannot exceed original price
        }

        $finalPrice = max(0, $price - $discountAmount); // Final price cannot be negative

        // Record usage with cache clearing
        //$promoCode->recordUsage($user->id); // Commented out - only for actual usage, not validation

        return response()->json([
            'price' => $price, // Original price
            'promocode_discounted_amount' => round($discountAmount, 2), // Discount amount rounded to 2 decimals
            'final_price' => round($finalPrice, 2) // Final price after discount
        ], 200); // HTTP 200 OK
    }

    /**
     * Use a promo code (without price calculation)
     */
    public function use(Request $request): JsonResponse // Actually applies promo code and records usage
    {
        $validator = Validator::make($request->all(), [ // Validate usage request data
            'code' => 'required|string' // Promo code is required
        ]);

        if ($validator->fails()) { // If validation fails
            return response()->json([
                'message' => 'Validation failed', // Error message
                'errors' => $validator->errors() // Return validation errors
            ], 422); // HTTP 422 Unprocessable Entity
        }

        $promoCode = PromoCode::findByCodeCached($request->code); // Find promo code with caching

        if (!$promoCode) { // Promo code not found
            return response()->json([
                'message' => 'Promo code not found', // User-friendly message
                'error' => 'PROMO_CODE_NOT_FOUND' // Error code for frontend
            ], 404); // HTTP 404 Not Found
        }

        if (!$promoCode->canBeUsedByUser($request->user())) { // Check if user can use this code
            return response()->json([
                'message' => 'Promo code cannot be used', // User-friendly message
                'error' => 'PROMO_CODE_INVALID' // Error code for frontend
            ], 400); // HTTP 400 Bad Request
        }

        // Check if user has already used this promo code
        $userUsages = $promoCode->getUserUsageCount($request->user()->id); // Get user's usage count
        if ($userUsages > 0) { // User has already used this code
            return response()->json([
                'message' => 'Promo code already used by this user', // User-friendly message
                'error' => 'PROMO_CODE_ALREADY_USED' // Error code for frontend
            ], 400); // HTTP 400 Bad Request
        }

        // Record usage
        $promoCode->recordUsage($request->user()->id); // Record the usage and clear cache

        return response()->json([
            'message' => 'Promo code applied successfully', // Success message
            'promo_code' => [
                'code' => $promoCode->code, // Return promo code details
                'type' => $promoCode->type, // Discount type
                'value' => $promoCode->value // Discount value
            ]
        ]); // HTTP 200 OK (default)
    }
}
