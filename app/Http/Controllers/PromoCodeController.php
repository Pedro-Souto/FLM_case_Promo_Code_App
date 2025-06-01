<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class PromoCodeController extends Controller
{
    /**
     * Create a new promo code (Admin only)
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:20|unique:promo_codes',
            'type' => 'required|in:percentage,value',
            'value' => 'required|numeric|min:0',
            'expiry_date' => 'sometimes|date|after:now',
            'max_usages' => 'sometimes|integer|min:1',
            'max_usages_per_user' => 'sometimes|integer|min:1',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Additional validation for percentage type
        if ($request->type === 'percentage' && $request->value > 100) {
            return response()->json([
                'message' => 'Percentage value cannot exceed 100'
            ], 422);
        }

        $promoCode = PromoCode::create([
            'code' => $request->code ?? PromoCode::generateCode(),
            'type' => $request->type,
            'value' => $request->value,
            'expiry_date' => $request->expiry_date,
            'max_usages' => $request->max_usages,
            'max_usages_per_user' => $request->max_usages_per_user,
            'created_by' => $request->user()->id
        ]);

        // Attach specific users if provided
        if ($request->user_ids) {
            $promoCode->users()->attach($request->user_ids);
        }

        return response()->json([
            'message' => 'Promo code created successfully',
            'promo_code' => $promoCode->load('users')
        ], 201);
    }

    /**
     * Get all promo codes (Admin only) with caching
     */
    public function index(): JsonResponse
    {
        $promoCodes = Cache::remember(
            'all_promo_codes',
            now()->addMinutes(10),
            function () {
                return PromoCode::with(['users:id,name,email', 'creator:id,name'])
                              ->orderBy('created_at', 'desc')
                              ->get();
            }
        );

        return response()->json($promoCodes);
    }

    /**
     * Validate promo code and calculate discount (Rate Limited & Cached)
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price' => 'required|numeric|min:0',
            'promo_code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $price = $request->price;
        $code = $request->promo_code;
        $user = $request->user();

        // Use cached lookup
        $promoCode = PromoCode::findByCodeCached($code);

        // Check if promo code exists
        if (!$promoCode) {
            return response()->json([
                'message' => 'Promo code not found',
                'error' => 'PROMO_CODE_NOT_FOUND'
            ], 404);
        }

        // Check if promo code is active
        if (!$promoCode->is_active) {
            return response()->json([
                'message' => 'Promo code is inactive',
                'error' => 'PROMO_CODE_INACTIVE'
            ], 404);
        }

        // Check if promo code is expired
        if ($promoCode->expiry_date && $promoCode->expiry_date->isPast()) {
            return response()->json([
                'message' => 'Promo code has expired',
                'error' => 'PROMO_CODE_EXPIRED'
            ], 404);
        }

        // Check if max usages exceeded
        if ($promoCode->max_usages && $promoCode->current_usages >= $promoCode->max_usages) {
            return response()->json([
                'message' => 'Promo code usage limit exceeded',
                'error' => 'PROMO_CODE_USAGE_LIMIT_EXCEEDED'
            ], 404);
        }

        // Check if user can use this promo code (uses cached methods)
        if (!$promoCode->canBeUsedByUser($user)) {
            return response()->json([
                'message' => 'Promo code is not available for this user',
                'error' => 'PROMO_CODE_NOT_AVAILABLE_FOR_USER'
            ], 404);
        }

        // Check if user has already used this promo code
        $userUsages = $promoCode->getUserUsageCount($user->id);
        if ($promoCode->max_usages_per_user && $userUsages >= $promoCode->max_usages_per_user) {
            return response()->json([
                'message' => 'User has exceeded the maximum usage limit for this promo code',
                'error' => 'PROMO_CODE_USER_USAGE_LIMIT_EXCEEDED'
            ], 404);
        }

        // Calculate discount
        $discountAmount = 0;
        if ($promoCode->type === PromoCode::TYPE_PERCENTAGE) {
            $discountAmount = ($price * $promoCode->value) / 100;
        } else {
            $discountAmount = min($promoCode->value, $price);
        }

        $finalPrice = max(0, $price - $discountAmount);

        // Record usage with cache clearing
        $promoCode->recordUsage($user->id);

        return response()->json([
            'price' => $price,
            'promocode_discounted_amount' => round($discountAmount, 2),
            'final_price' => round($finalPrice, 2)
        ], 200);
    }

    /**
     * Use a promo code (without price calculation)
     */
    public function use(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $promoCode = PromoCode::findByCodeCached($request->code);

        if (!$promoCode) {
            return response()->json([
                'message' => 'Promo code not found',
                'error' => 'PROMO_CODE_NOT_FOUND'
            ], 404);
        }

        if (!$promoCode->canBeUsedByUser($request->user())) {
            return response()->json([
                'message' => 'Promo code cannot be used',
                'error' => 'PROMO_CODE_INVALID'
            ], 400);
        }

        // Check if user has already used this promo code
        $userUsages = $promoCode->getUserUsageCount($request->user()->id);
        if ($userUsages > 0) {
            return response()->json([
                'message' => 'Promo code already used by this user',
                'error' => 'PROMO_CODE_ALREADY_USED'
            ], 400);
        }

        // Record usage
        $promoCode->recordUsage($request->user()->id);

        return response()->json([
            'message' => 'Promo code applied successfully',
            'promo_code' => [
                'code' => $promoCode->code,
                'type' => $promoCode->type,
                'value' => $promoCode->value
            ]
        ]);
    }
}
