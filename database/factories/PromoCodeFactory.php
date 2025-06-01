<?php

namespace Database\Factories;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PromoCode>
 */
class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('????####')),
            'type' => $this->faker->randomElement(['percentage', 'value']),
            'value' => $this->faker->numberBetween(5, 50),
            'is_active' => true,
            'expiry_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'max_usages' => $this->faker->optional(0.7)->numberBetween(10, 1000),
            'max_usages_per_user' => $this->faker->optional(0.5)->numberBetween(1, 5),
            'current_usages' => 0,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the promo code is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the promo code is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => $this->faker->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    /**
     * Indicate that the promo code is a percentage type.
     */
    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => $this->faker->numberBetween(5, 50),
        ]);
    }

    /**
     * Indicate that the promo code is a value type.
     */
    public function value(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'value',
            'value' => $this->faker->numberBetween(5, 100),
        ]);
    }
}
