<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CustomQuote;
use App\Models\MeasurementTemplate;
use App\Models\Series;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomQuote>
 */
class CustomQuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_key' => Str::uuid(), 'number' => 'CQ-'.strtoupper(Str::random(10)),
            'user_id' => User::factory(), 'measurement_template_id' => MeasurementTemplate::factory(),
            'variant_id' => function (array $attributes) {
                $category = Category::create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug()]);
                $series = Series::create(['category_id' => $category->id, 'name' => fake()->word(), 'slug' => fake()->unique()->slug(), 'active' => true]);

                return Variant::create(['series_id' => $series->id, 'measurement_template_id' => $attributes['measurement_template_id'], 'sku' => strtoupper(Str::random(10)), 'color' => 'Black', 'size' => 'Custom', 'price' => 300000, 'stock' => 5, 'weight' => 500, 'fulfillment' => 'custom_made'])->id;
            },
            'template_snapshot' => [], 'measurements' => ['chest' => 96], 'status' => 'submitted',
        ];
    }
}
