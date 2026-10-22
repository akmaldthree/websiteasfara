<?php

namespace Database\Factories;

use App\Models\PriceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceGroup>
 */
class PriceGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['reseller', 'dropshipper', 'wholesale']);

        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'business_type' => $type,
            'minimum_order_quantity' => fake()->numberBetween(1, 12),
            'minimum_order_amount' => 0,
            'active' => true,
        ];
    }
}
