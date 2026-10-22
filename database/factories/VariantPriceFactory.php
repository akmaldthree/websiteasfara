<?php

namespace Database\Factories;

use App\Models\PriceGroup;
use App\Models\Variant;
use App\Models\VariantPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariantPrice>
 */
class VariantPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variant_id' => fn () => Variant::query()->value('id') ?? throw new \LogicException('Buat varian sebelum memakai VariantPriceFactory.'),
            'price_group_id' => PriceGroup::factory(),
            'minimum_quantity' => 1,
            'price' => fake()->numberBetween(50000, 500000),
            'active' => true,
        ];
    }
}
