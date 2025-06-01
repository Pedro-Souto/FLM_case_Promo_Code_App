<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_correct_fillable_attributes(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
    }

    public function test_user_password_is_hidden(): void
    {
        $user = User::factory()->create();
        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
    }

    public function test_user_is_admin_scope_works(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $admins = User::isAdmin()->get();

        $this->assertCount(1, $admins);
        $this->assertTrue($admins->contains($admin));
        $this->assertFalse($admins->contains($user));
    }

    public function test_user_can_have_promo_codes(): void
    {
        $user = User::factory()->create();
        
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsToMany::class,
            $user->promoCodes()
        );
    }
}