<?php

namespace Tests\Unit;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCodeModelTest extends TestCase
{
    use RefreshDatabase;


    public function test_promo_code_uses_provided_code(): void
    {
        $promoCode = PromoCode::factory()->create(['code' => 'CUSTOM123']);
        
        $this->assertEquals('CUSTOM123', $promoCode->code);
    }

    public function test_promo_code_has_correct_default_values(): void
    {
        $promoCode = PromoCode::factory()->create();
        
        $this->assertTrue($promoCode->is_active);
        $this->assertEquals(0, $promoCode->current_usages);
    }

    public function test_can_be_used_by_user_returns_true_for_global_promo(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create();
        
        $this->assertTrue($promoCode->canBeUsedByUser($user));
    }

    public function test_can_be_used_by_user_returns_true_for_specific_user(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create();
        $promoCode->users()->attach($user->id);
        
        $this->assertTrue($promoCode->canBeUsedByUser($user));
    }

    public function test_can_be_used_by_user_returns_false_for_restricted_promo(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $promoCode = PromoCode::factory()->create();
        $promoCode->users()->attach($user1->id);
        
        $this->assertFalse($promoCode->canBeUsedByUser($user2));
    }

    public function test_find_by_code_cached_returns_correct_promo(): void
    {
        $promoCode = PromoCode::factory()->create(['code' => 'FINDME']);
        
        $found = PromoCode::findByCodeCached('FINDME');
        
        $this->assertInstanceOf(PromoCode::class, $found);
        $this->assertEquals('FINDME', $found->code);
    }

    public function test_find_by_code_cached_returns_null_for_nonexistent(): void
    {
        $found = PromoCode::findByCodeCached('NONEXISTENT');
        
        $this->assertNull($found);
    }


    public function test_record_usage_increments_counters(): void
    {
        $user = User::factory()->create();
        $promoCode = PromoCode::factory()->create(['current_usages' => 5]);
        
        $promoCode->recordUsage($user->id);
        
        $this->assertEquals(6, $promoCode->fresh()->current_usages);
        $this->assertEquals(1, $promoCode->getUserUsageCount($user->id));
    }
}