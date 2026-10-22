<?php

namespace Database\Factories;

use App\Models\BusinessAccount;
use App\Models\PriceGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessAccount>
 */
class BusinessAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_type' => 'reseller',
            'business_name' => fake()->company(),
            'sales_channel' => fake()->randomElement(['Toko fisik', 'Instagram', 'Marketplace']),
            'status' => 'pending',
            'submitted_at' => now(),
        ];
    }

    public function approved(?PriceGroup $group = null): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'price_group_id' => $group?->id ?? PriceGroup::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
