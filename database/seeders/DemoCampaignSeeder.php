<?php

namespace Database\Seeders;

use App\Models\ContentEntry;
use App\Models\Series;
use Illuminate\Database\Seeder;

class DemoCampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $campaigns = [
            ['demo-seri-ruang-raya', 'Ruang Raya', 'Sarimbit untuk perayaan dan pertemuan keluarga.', 'demo/hero-family-sage-2x1.webp', ['arcelia-family', 'sakina-family', 'harmoni-raya', 'amara-family']],
            ['demo-seri-siluet-senja', 'Siluet Senja', 'Abaya berpotongan lembut untuk agenda istimewa.', 'demo/hero-abaya-rose-2x1.webp', ['naura-pleated', 'kamila-embroidery', 'raffah-signature', 'mahira-luxe']],
            ['demo-seri-langkah-ringan', 'Langkah Ringan', 'Busana berlapis dan ringan untuk kegiatan sehari-hari.', 'demo/hero-travel-2x1.webp', ['nayla-travel', 'livia-layer-outer', 'inara-long-outer', 'safa-daily-khimar']],
            ['demo-signature-rona-malam', 'Rona Malam', 'Potongan abaya rapi dan detail bordir untuk acara malam.', 'demo/hero-abaya-rose-2x1.webp', ['raffah-signature', 'mahira-luxe', 'kamila-embroidery'], 'signature'],
            ['demo-studio-lapis-kota', 'Lapis Kota', 'Outer dan khimar ringan untuk beraktivitas di kota.', 'demo/hero-travel-2x1.webp', ['livia-layer-outer', 'inara-long-outer', 'hana-flow-khimar'], 'studio'],
        ];
        $products = Series::published()->whereIn('slug', collect($campaigns)->pluck(4)->flatten())
            ->whereHas('variants', fn ($variants) => $variants->where('active', true)->where('sku', 'like', 'DEMO-%'))
            ->pluck('id', 'slug');

        foreach ($campaigns as $position => $campaign) {
            [$slug, $title, $summary, $image, $seriesSlugs] = $campaign;
            $blocks = collect($seriesSlugs)->map(fn (string $seriesSlug) => $products->get($seriesSlug))
                ->filter()->map(fn (int $id) => ['type' => 'campaign_product', 'series_id' => $id])->values()->all();
            if ($blocks === []) {
                $this->command?->warn('Produk demo belum tersedia untuk '.$title.'.');

                continue;
            }
            if (isset($campaign[5])) {
                array_unshift($blocks, ['type' => 'campaign_options', 'group' => $campaign[5]]);
            }

            ContentEntry::firstOrCreate(['type' => 'campaign', 'slug' => $slug], [
                'title' => $title,
                'summary' => $summary,
                'image' => $image,
                'blocks' => $blocks,
                'sort_order' => $position + 1,
                'editorial_status' => 'approved',
                'active' => true,
            ]);
        }
    }
}
