<?php

namespace Database\Seeders;

use App\Models\ProductMedia;
use App\Models\Series;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoProductGallerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $series = Series::where('slug', 'raffah-signature')
            ->whereHas('variants', fn ($variants) => $variants->where('sku', 'like', 'DEMO-%'))->first();
        if (! $series) {
            $this->command?->warn('Raffah Signature demo belum tersedia.');

            return;
        }

        $assets = [
            'demo/raffah-olive-back-4x5.webp' => 'Raffah Signature warna olive tampak belakang',
            'demo/raffah-olive-detail-4x5.webp' => 'Detail jahitan manset dan tekstur kain Raffah Signature warna olive',
        ];
        $nextPosition = (int) $series->media()->max('sort_order') + 1;
        foreach ($assets as $path => $alt) {
            $contents = file_get_contents(database_path('seeders/assets/'.$path));
            if ($contents === false || ! Storage::disk('public')->put($path, $contents)) {
                throw new RuntimeException('Gagal memasang foto demo: '.$path);
            }
            ProductMedia::firstOrCreate(['series_id' => $series->id, 'image' => $path], [
                'color' => 'Olive',
                'alt_text' => $alt,
                'sort_order' => $nextPosition++,
                'active' => true,
            ]);
        }
    }
}
