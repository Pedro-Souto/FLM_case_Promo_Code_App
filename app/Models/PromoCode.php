<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class PromoCode extends Model // Main model for promotional discount codes
{
    use HasFactory; // Enables model factory for testing and seeding

    protected $fillable = [ // Fields that can be mass assigned during creation/update
        'code', // Unique promo code string (e.g., "SAVE10")
        'type', // Discount type: 'percentage' or 'value'
        'value', // Discount amount (percentage or fixed value)
        'expiry_date', // When the promo code expires (nullable)
        'max_usages', // Maximum total uses across all users (nullable)
        'max_usages_per_user', // Maximum uses per individual user (nullable)
        'current_usages', // Current count of total usages
        'is_active', // Whether the promo code is currently active
        'created_by' // Admin user ID who created this promo code
    ];

    protected $casts = [ // Define how attributes should be cast when accessed
        'expiry_date' => 'datetime', // Cast to Carbon datetime instance
        'is_active' => 'boolean', // Cast to boolean for active status
        'value' => 'decimal:2' // Cast to decimal with 2 decimal places for currency
    ];

    const TYPE_PERCENTAGE = 'percentage'; // Constant for percentage-based discounts
    const TYPE_VALUE = 'value'; // Constant for fixed-value discounts

    public static function getTypes(): array // Returns available discount types
    {
        return [
            self::TYPE_PERCENTAGE, // Percentage discount (e.g., 10% off)
            self::TYPE_VALUE // Fixed value discount (e.g., $5 off)
        ];
    }

    /**
     * Generate a unique promo code
     */
    public static function generateCode(): string // Creates a unique 8-character promo code
    {
        do {
            $code = strtoupper(Str::random(8)); // Generate uppercase random string
        } while (self::where('code', $code)->exists()); // Ensure uniqueness

        return $code; // Return the unique code
    }

    /**
     * Find promo code by code with caching
     */
    public static function findByCodeCached(string $code): ?self // Find promo code with 1-minute cache
    {
        return Cache::remember(
            "promo_code:{$code}", // Cache key for this specific code
            now()->addMinutes(1), // Cache for 1 minute
            function () use ($code) {
                return self::where('code', $code)->first(); // Database query fallback
            }
        );
    }

    /**
     * Get user usage count for this promo code with caching
     */
    public function getUserUsageCount(int $userId): int // Count how many times user used this code
    {
        return Cache::remember(
            "promo_usage:{$this->id}:user:{$userId}", // Unique cache key per promo-user pair
            now()->addMinutes(1), // Cache for 1 minute
            function () use ($userId) {
                return $this->usages()->where('user_id', $userId)->count(); // Count user's usages
            }
        );
    }

    /**
     * Check if promo code is valid
     */
    public function isValid(): bool // Validates promo code availability and limits
    {
        return $this->is_active && // Must be active
               (!$this->expiry_date || $this->expiry_date->isFuture()) && // Not expired
               (!$this->max_usages || $this->current_usages < $this->max_usages); // Under usage limit
    }

    /**
     * Check if user can use this promo code
     */
    public function canBeUsedByUser(User $user): bool // Comprehensive user eligibility check
    {
        if (!$this->isValid()) { // First check basic validity
            return false;
        }

        // Check if promo code is restricted to specific users (with caching)
        $isRestrictedToSpecificUsers = Cache::remember(
            "promo_restricted:{$this->id}", // Cache key for restriction status
            now()->addMinutes(1), // Cache for 1 minute
            function () {
                return $this->users()->exists(); // Check if any user restrictions exist
            }
        );

        if ($isRestrictedToSpecificUsers) { // If restricted to specific users
            $userHasAccess = Cache::remember(
                "promo_access:{$this->id}:user:{$user->id}", // Cache key for user access
                now()->addMinutes(1), // Cache for 1 minute
                function () use ($user) {
                    return $this->users()->where('user_id', $user->id)->exists(); // Check user access
                }
            );

            if (!$userHasAccess) { // User doesn't have access to restricted code
                return false;
            }
        }

        // Check max usages per user
        if ($this->max_usages_per_user) { // If per-user limit exists
            $userUsages = $this->getUserUsageCount($user->id); // Get user's current usage count
            return $userUsages < $this->max_usages_per_user; // Check if under limit
        }

        return true; // All checks passed
    }

    /**
     * Clear cache when model is updated
     */
    protected static function booted() // Laravel model event handlers
    {
        static::updated(function ($promoCode) { // When promo code is updated
            $promoCode->clearCache(); // Clear related cache entries
        });

        static::deleted(function ($promoCode) { // When promo code is deleted
            $promoCode->clearCache(); // Clear related cache entries
        });
    }

    /**
     * Clear related cache entries
     */
    public function clearCache(): void // Removes all cached data for this promo code
    {
        Cache::forget("promo_code:{$this->code}"); // Clear code lookup cache
        Cache::forget("promo_restricted:{$this->id}"); // Clear restriction status cache
        
        // Clear user access cache for all users
        $userIds = $this->users()->pluck('user_id'); // Get all users with access
        foreach ($userIds as $userId) {
            Cache::forget("promo_access:{$this->id}:user:{$userId}"); // Clear access cache
            Cache::forget("promo_usage:{$this->id}:user:{$userId}"); // Clear usage count cache
        }
    }

    /**
     * Clear user usage cache when usage is recorded
     */
    public function recordUsage(int $userId): void // Records promo code usage and updates counters
    {
        $this->usages()->attach($userId, ['used_at' => now()]); // Create usage record with timestamp
        $this->increment('current_usages'); // Increment total usage counter
        
        // Clear relevant cache
        Cache::forget("promo_usage:{$this->id}:user:{$userId}"); // Clear user usage cache
        Cache::forget("promo_code:{$this->code}"); // Clear code lookup cache
    }

    /**
     * Users who can use this promo code (if restricted)
     */
    public function users(): BelongsToMany // Many-to-many relationship with users who have access
    {
        return $this->belongsToMany(User::class, 'promo_code_users'); // Pivot table for user access control
    }

    /**
     * Promo code usages
     */
    public function usages(): BelongsToMany // Many-to-many relationship tracking actual usage
    {
        return $this->belongsToMany(User::class, 'promo_code_usages') // Pivot table for usage tracking
                    ->withPivot('used_at') // Include usage timestamp from pivot table
                    ->withTimestamps(); // Include created_at and updated_at from pivot
    }

    /**
     * Admin who created this promo code
     */
    public function creator() // Belongs-to relationship with the admin user who created this code
    {
        return $this->belongsTo(User::class, 'created_by'); // Foreign key to users table
    }
}
