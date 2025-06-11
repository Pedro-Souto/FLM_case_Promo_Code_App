<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable // Extends Laravel's base Authenticatable model for user authentication
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens; // Traits for model factories, notifications, and API token authentication

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [ // Fields that can be mass assigned during creation/update
        'name', // User's display name
        'email', // User's email address (unique identifier)
        'password', // User's hashed password
        'is_admin', // Boolean flag indicating admin privileges
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [ // Fields to hide when converting model to JSON/array
        'password', // Never expose password in API responses
        'remember_token', // Hide remember token for security
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array // Define how attributes should be cast when accessed
    {
        return [
            'email_verified_at' => 'datetime', // Cast to Carbon datetime instance
            'password' => 'hashed', // Automatically hash when setting password
            'is_admin' => 'boolean', // Cast to boolean for admin flag
        ];
    }

    /**
     * Promo codes this user can access
     */
    public function availablePromoCodes() // Many-to-many relationship with available promo codes
    {
        return $this->belongsToMany(PromoCode::class, 'promo_code_users'); // Pivot table for user-promo code access
    }

    /**
     * Promo codes this user has used
     */
    public function usedPromoCodes() // Many-to-many relationship with used promo codes
    {
        return $this->belongsToMany(PromoCode::class, 'promo_code_usages') // Pivot table for usage tracking
                    ->withPivot('used_at') // Include usage timestamp from pivot table
                    ->withTimestamps(); // Include created_at and updated_at from pivot
    }
}
