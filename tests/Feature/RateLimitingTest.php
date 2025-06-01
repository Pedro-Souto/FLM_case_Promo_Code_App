<?php


namespace Tests\Feature;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiters before each test
        RateLimiter::clear('promo-validation');
    }

    public function test_promo_validation_is_rate_limited(): void
    {
         /** @var User $user */
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create([
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10
        ]);

        // Make 10 requests (should all succeed)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/auth/promo-codes/validate', [
                    'price' => 100,
                    'promo_code' => 'TEST'
                ]);
            
            $response->assertStatus(200);
        }

        // 11th request should be rate limited
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/promo-codes/validate', [
                'price' => 100,
                'promo_code' => 'TEST'
            ]);

        $response->assertStatus(429); // Too Many Requests
    }

    public function test_different_users_have_separate_rate_limits(): void
    {    /** @var User $user1 */
        $user1 = User::factory()->create();
         /** @var User $user2 */
        $user2 = User::factory()->create();
        $promoCode = PromoCode::factory()->create([
            'code' => 'TEST',
            'type' => 'percentage',
            'value' => 10
        ]);

        // User 1 makes 10 requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($user1, 'sanctum')
                ->postJson('/api/auth/promo-codes/validate', [
                    'price' => 100,
                    'promo_code' => 'TEST'
                ]);
            
            $response->assertStatus(200);
        }

        // User 2 should still be able to make requests
        $response = $this->actingAs($user2, 'sanctum')
            ->postJson('/api/auth/promo-codes/validate', [
                'price' => 100,
                'promo_code' => 'TEST'
            ]);

        $response->assertStatus(200);
    }
}