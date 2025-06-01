<?php

namespace Tests\Feature;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_percentage_promo_code(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/auth/promo-codes', [
                'code' => 'SAVE20',
                'type' => 'percentage',
                'value' => 20,
                'expiry_date' => now()->addDays(30)->toDateString(),
                'max_usages' => 100,
                'max_usages_per_user' => 1
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'promo_code' => [
                    'id', 'code', 'type', 'value', 'expiry_date', 
                    'max_usages', 'max_usages_per_user'
                ]
            ]);

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20
        ]);
    }

    public function test_admin_can_create_value_promo_code(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/auth/promo-codes', [
                'type' => 'value',
                'value' => 50,
                'max_usages' => 50
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('promo_code.type', 'value')
            ->assertJsonPath('promo_code.value', '50.00');
    }

    public function test_non_admin_cannot_create_promo_code(): void
    {
         /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/promo-codes', [
                'type' => 'percentage',
                'value' => 20
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_list_promo_codes(): void
    {
        $admin = User::factory()->admin()->create();
        PromoCode::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/promo-codes');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_user_can_validate_active_percentage_promo_code(): void
    {
         /** @var User $user */
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
            'expiry_date' => now()->addDays(30)
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/promo-codes/validate', [
                'price' => 100,
                'promo_code' => 'SAVE20'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('final_price', 80);
    }

    public function test_user_can_validate_active_value_promo_code(): void
    {
         /** @var User $user */
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create([
            'code' => 'SAVE50',
            'type' => 'value',
            'value' => 50,
            'is_active' => true,
            'expiry_date' => now()->addDays(30)
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/promo-codes/validate', [
                'price' => 100,
                'promo_code' => 'SAVE50'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('final_price', 50);
    }

    public function test_user_cannot_validate_inactive_promo_code(): void
    {
         /** @var User $user */
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create([
            'code' => 'INACTIVE',
            'is_active' => false
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/promo-codes/validate', [
                'price' => 100,
                'promo_code' => 'INACTIVE'
            ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Promo code is inactive']);
    }

    public function test_user_cannot_validate_expired_promo_code(): void
    {
         /** @var User $user */
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create([
            'code' => 'EXPIRED',
            'expiry_date' => now()->subDay()
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/promo-codes/validate', [
                'price' => 100,
                'promo_code' => 'EXPIRED'
            ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Promo code has expired']);
    }

    public function test_user_cannot_validate_nonexistent_promo_code(): void
    {
         /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/promo-codes/validate', [
                'price' => 100,
                'promo_code' => 'NONEXISTENT'
            ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Promo code not found']);
    }

    public function test_validation_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/promo-codes/validate', [
            'price' => 100,
            'promo_code' => 'TEST'
        ]);

        $response->assertStatus(401);
    }

    public function test_percentage_promo_code_cannot_exceed_100_percent(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/auth/promo-codes', [
                'type' => 'percentage',
                'value' => 150 // Invalid: over 100%
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Percentage value cannot exceed 100']);
    }

    public function test_promo_code_creation_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/auth/promo-codes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'value']);
    }
}