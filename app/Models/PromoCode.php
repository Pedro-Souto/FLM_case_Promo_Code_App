<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'expiry_date',
        'max_usages',
        'max_usages_per_user',
        'current_usages',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
        'is_active' => 'boolean',
        'value' => 'decimal:2'
    ];

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_VALUE = 'value';

    public static function getTypes(): array
    {
        return [
            self::TYPE_PERCENTAGE,
            self::TYPE_VALUE
        ];
    }

    /**
     * Generate a unique promo code
     */
    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Find promo code by code with caching
     */
    public static function findByCodeCached(string $code): ?self
    {
        return Cache::remember(
            "promo_code:{$code}",
            now()->addMinutes(1),
            function () use ($code) {
                return self::where('code', $code)->first();
            }
        );
    }

    /**
     * Get user usage count for this promo code with caching
     */
    public function getUserUsageCount(int $userId): int
    {
        return Cache::remember(
            "promo_usage:{$this->id}:user:{$userId}",
            now()->addMinutes(1),
            function () use ($userId) {
                return $this->usages()->where('user_id', $userId)->count();
            }
        );
    }

    /**
     * Check if promo code is valid
     */
    public function isValid(): bool
    {
        return $this->is_active &&
               (!$this->expiry_date || $this->expiry_date->isFuture()) &&
               (!$this->max_usages || $this->current_usages < $this->max_usages);
    }

    /**
     * Check if user can use this promo code
     */
    public function canBeUsedByUser(User $user): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        // Check if promo code is restricted to specific users (with caching)
        $isRestrictedToSpecificUsers = Cache::remember(
            "promo_restricted:{$this->id}",
            now()->addMinutes(1),
            function () {
                return $this->users()->exists();
            }
        );

        if ($isRestrictedToSpecificUsers) {
            $userHasAccess = Cache::remember(
                "promo_access:{$this->id}:user:{$user->id}",
                now()->addMinutes(1),
                function () use ($user) {
                    return $this->users()->where('user_id', $user->id)->exists();
                }
            );

            if (!$userHasAccess) {
                return false;
            }
        }

        // Check max usages per user
        if ($this->max_usages_per_user) {
            $userUsages = $this->getUserUsageCount($user->id);
            return $userUsages < $this->max_usages_per_user;
        }

        return true;
    }

    /**
     * Clear cache when model is updated
     */
    protected static function booted()
    {
        static::updated(function ($promoCode) {
            $promoCode->clearCache();
        });

        static::deleted(function ($promoCode) {
            $promoCode->clearCache();
        });
    }

    /**
     * Clear related cache entries
     */
    public function clearCache(): void
    {
        Cache::forget("promo_code:{$this->code}");
        Cache::forget("promo_restricted:{$this->id}");
        
        // Clear user access cache for all users
        $userIds = $this->users()->pluck('user_id');
        foreach ($userIds as $userId) {
            Cache::forget("promo_access:{$this->id}:user:{$userId}");
            Cache::forget("promo_usage:{$this->id}:user:{$userId}");
        }
    }

    /**
     * Clear user usage cache when usage is recorded
     */
    public function recordUsage(int $userId): void
    {
        $this->usages()->attach($userId, ['used_at' => now()]);
        $this->increment('current_usages');
        
        // Clear relevant cache
        Cache::forget("promo_usage:{$this->id}:user:{$userId}");
        Cache::forget("promo_code:{$this->code}");
    }

    /**
     * Users who can use this promo code (if restricted)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'promo_code_users');
    }

    /**
     * Promo code usages
     */
    public function usages(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'promo_code_usages')
                    ->withPivot('used_at')
                    ->withTimestamps();
    }

    /**
     * Admin who created this promo code
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
