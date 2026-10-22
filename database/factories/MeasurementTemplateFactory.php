<?php

namespace Database\Factories;

use App\Models\MeasurementTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeasurementTemplate>
 */
class MeasurementTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Ukuran Gamis', 'product_type' => null, 'pricing_mode' => 'fixed',
            'surcharge' => 50000, 'lead_time' => '7–10 hari kerja',
            'fields' => [['key' => 'chest', 'label' => 'Lingkar dada', 'unit' => 'cm', 'min' => 50, 'max' => 180, 'required' => true, 'help' => 'Ukur mengelilingi dada.', 'example' => 96]],
            'policy' => 'Produk dibuat sesuai ukuran yang disetujui.', 'active' => true,
        ];
    }
}
