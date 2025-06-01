<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'accessToken'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);
    }

    public function test_user_can_login(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'accessToken',
                'token_type'
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully logged out']);
    }

    public function test_admin_can_get_profile(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'is_admin' => true
        ]);
        
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/user');

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Admin User',
                'email' => 'admin@example.com'
            ]);
    }

    public function test_non_admin_cannot_get_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_admin' => false
        ]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/user');

        $response->assertStatus(403);
    }

    public function test_admin_can_get_all_users(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->count(3)->create();
        
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/users');

        $response->assertStatus(200)
            ->assertJsonCount(4); // 3 users + 1 admin
    }

    public function test_non_admin_cannot_get_all_users(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_admin' => false]);
        
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/users');

        $response->assertStatus(403);
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123', // too short
            'password_confirmation' => 'different'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_prevents_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_user_can_be_created(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_admin' => true
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'is_admin' => true
        ]);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/auth/user');
        $response->assertStatus(401);

        $response = $this->getJson('/api/auth/users');
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }
}