<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\CuratedLink;
use App\Models\MediaAsset;
use App\Models\Series;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemoAbayaSeeder extends Seeder
{
    private const STYLES = [
        'raffah-signature' => ['olive', 'Olive'],
        'naura-pleated' => ['rose', 'Dusty Rose'],
        'mahira-luxe' => ['black', 'Black'],
        'alesha-everyday' => ['sage', 'Sage'],
        'zayna-essential' => ['espresso', 'Espresso'],
        'kamila-embroidery' => ['rosewood', 'Rosewood'],
        'samira-flare' => ['moss', 'Moss'],
        'nayla-travel' => ['stone', 'Oat'],
    ];

    private const LEGACY_SERIES = [
        'arcelia-family', 'sakina-family', 'harmoni-raya', 'zahara-kids', 'livia-layer-outer',
        'hana-flow-khimar', 'amara-family', 'nadhira-family', 'aira-kids', 'maryam-kids',
        'inara-long-outer', 'safa-daily-khimar',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->registerAssets();
            $series = Series::whereIn('slug', [...array_keys(self::STYLES), ...self::LEGACY_SERIES])
                ->whereHas('variants', fn ($query) => $query->where('sku', 'like', 'DEMO-%'))
                ->whereDoesntHave('variants', fn ($query) => $query->where('sku', 'not like', 'DEMO-%'))
                ->get()->toBase()->keyBy('slug');
            if ($series->isEmpty()) {
                $this->command?->warn('Katalog demo tidak ditemukan. Foto tersedia di pustaka media.');

                return;
            }

            foreach (self::STYLES as $slug => [$style, $color]) {
                $product = $series->get($slug);
                if (! $product || ($product->image && ! str_starts_with($product->image, 'demo/'))) {
                    continue;
                }

                $this->updateGallery($product, $style, $color);
            }

            foreach ($series->only(self::LEGACY_SERIES) as $product) {
                if (str_starts_with((string) $product->image, 'demo/')
                    && ! $product->media()->where('image', 'not like', 'demo/%')->exists()) {
                    $product->update(['active' => false]);
                }
            }
            Category::whereIn('slug', ['sarimbit-keluarga', 'busana-anak', 'outer-khimar'])
                ->whereIn('id', $series->only(self::LEGACY_SERIES)->pluck('category_id'))
                ->whereDoesntHave('series', fn ($query) => $query->where('active', true))
                ->update(['active' => false]);

            $this->updateCampaigns($series->only(array_keys(self::STYLES))->filter->active, $series->pluck('id'));
            $this->updateEditorial($series);
        });
    }

    private function registerAssets(): void
    {
        foreach (glob(database_path('seeders/assets/demo/abaya-*.webp')) ?: [] as $file) {
            $filename = basename($file);
            $name = str(pathinfo($filename, PATHINFO_FILENAME))->replace('-', ' ')->title()->toString();
            MediaAsset::firstOrCreate(['path' => 'demo/'.$filename], [
                'name' => $name, 'mime' => 'image/webp', 'size' => filesize($file), 'alt_text' => $name, 'active' => true,
            ]);
        }
    }

    private function updateGallery(Series $series, string $style, string $color): void
    {
        $prefix = 'demo/abaya-'.$style.'-';
        $photos = collect(['front' => 'tampak depan', 'back' => 'tampak belakang', 'detail' => 'detail bahan'])
            ->mapWithKeys(fn ($label, $view) => [$prefix.$view.'.webp' => [$color, $label]]);
        foreach ($series->variants()->pluck('color')->unique() as $variantColor) {
            $filename = 'abaya-'.$style.'-'.str($variantColor)->slug().'-front.webp';
            if (is_file(database_path('seeders/assets/demo/'.$filename))) {
                $photos->put('demo/'.$filename, [$variantColor, 'tampak depan']);
            }
        }
        $series->update(['image' => $prefix.'front.webp']);
        $series->media()->where('image', 'like', 'demo/%')->whereNotIn('image', $photos->keys())->update(['active' => false]);
        foreach ($photos->keys() as $index => $image) {
            [$photoColor, $label] = $photos->get($image);
            $series->media()->updateOrCreate(['image' => $image], [
                'variant_id' => null, 'color' => $photoColor, 'alt_text' => $series->name.' — '.$photoColor.' — '.$label,
                'sort_order' => $index, 'focal_x' => 50, 'focal_y' => 50, 'active' => true,
            ]);
        }
    }

    private function updateCampaigns(Collection $products, Collection $demoIds): void
    {
        $campaigns = [
            'demo-seri-ruang-raya' => ['Abaya dalam palet teduh untuk silaturahmi dan momen istimewa.', 'hero-wide', ['raffah-signature', 'naura-pleated', 'kamila-embroidery', 'samira-flare']],
            'demo-seri-siluet-senja' => ['Abaya lembut dengan lipit dan detail bordir untuk acara istimewa.', 'senja-wide', ['naura-pleated', 'kamila-embroidery', 'mahira-luxe']],
            'demo-seri-langkah-ringan' => ['Abaya ringan untuk bepergian dan bergerak nyaman sepanjang hari.', 'harian-wide', ['nayla-travel', 'alesha-everyday', 'zayna-essential']],
            'demo-signature-rona-malam' => ['Abaya dengan tekstur dan bordir tonal untuk acara malam.', 'malam-wide', ['mahira-luxe', 'kamila-embroidery', 'raffah-signature']],
            'demo-studio-lapis-kota' => ['Pilihan abaya berpotongan praktis untuk hari yang aktif.', 'harian-wide', ['alesha-everyday', 'nayla-travel', 'zayna-essential']],
        ];
        foreach ($campaigns as $slug => [$summary, $cover, $slugs]) {
            $entry = $this->demoContent($slug);
            $items = collect($slugs)->map(fn ($slug) => $products->get($slug))->filter();
            if (! $entry || $items->isEmpty() || $entry->campaignProductIds()->diff($demoIds)->isNotEmpty()) {
                continue;
            }

            $blocks = collect($entry->blocks)->reject(fn ($block) => in_array($block['type'] ?? '', ['campaign_product', 'hero_options'], true));
            $options = collect($entry->blocks)->firstWhere('type', 'hero_options') ?? ['type' => 'hero_options'];
            $options['mobile_url'] = '/storage/demo/abaya-'.str_replace('-wide', '-mobile', $cover).'.webp';
            $entry->update([
                'image' => 'demo/abaya-'.$cover.'.webp', 'summary' => $summary, 'seo_description' => $summary,
                'blocks' => $blocks->push($options)->concat($items->map(fn (Series $product) => ['type' => 'campaign_product', 'series_id' => $product->id]))->values()->all(),
            ]);
        }
    }

    private function updateEditorial(Collection $series): void
    {
        $catalog = '/koleksi?category=abaya-dewasa';
        $sections = [
            'hero-wide' => ['demo-banner', 'demo-banner-new', 'warm-gathering', 'demo-harmoni'],
            'senja-wide' => ['demo-banner-abaya', 'demo-banner-kids', 'little-celebration'],
            'harian-wide' => ['demo-banner-travel', 'everyday-modest-edit', 'soft-earth-journey', 'demo-look-natural'],
            'journal-wide' => ['demo-cerita', 'merawat-busana', 'memilih-sarimbit', 'memadukan-abaya', 'bahan-untuk-perjalanan', 'palet-warna-keluarga', 'mencuci-busana-anak'],
            'promo-portrait' => ['promo-demo', 'promo-family', 'promo-kids', 'promo-travel'],
        ];
        $outdated = ['demo-banner', 'demo-banner-kids', 'demo-harmoni', 'memilih-sarimbit', 'palet-warna-keluarga', 'mencuci-busana-anak', 'warm-gathering', 'little-celebration', 'promo-family', 'promo-kids'];
        $archived = $series->only(self::LEGACY_SERIES)->where('active', false)->pluck('id')->all();
        foreach ($sections as $cover => $slugs) {
            foreach ($slugs as $slug) {
                $entry = $this->demoContent($slug);
                if (! $entry) {
                    continue;
                }
                $update = ['image' => 'demo/abaya-'.$cover.'.webp'];
                if (in_array($slug, $outdated, true) && $entry->summary !== 'Pilihan abaya Asfara dalam palet lembut untuk berbagai suasana.') {
                    $title = match ($slug) {
                        'memilih-sarimbit' => 'Panduan memilih potongan abaya',
                        'palet-warna-keluarga' => 'Palet warna untuk abaya',
                        'mencuci-busana-anak' => 'Cara mencuci abaya',
                        'promo-family', 'promo-kids' => 'Jelajahi pilihan abaya',
                        'demo-banner-kids', 'little-celebration' => 'Abaya untuk perayaan',
                        default => $entry->type === 'lookbook' ? $entry->title : 'Pilihan abaya Asfara',
                    };
                    $summary = 'Pilihan abaya Asfara dalam palet lembut untuk berbagai suasana.';
                    $update += ['title' => $title, 'summary' => $summary, 'body' => $summary, 'seo_title' => $title.' — Asfara', 'seo_description' => $summary, 'link_url' => $catalog, 'link_label' => 'Lihat abaya'];
                }
                $blocks = collect($entry->blocks)->reject(fn ($block) => in_array($block['series_id'] ?? null, $archived, false));
                if ($entry->type === 'banner') {
                    $options = $blocks->firstWhere('type', 'hero_options') ?? ['type' => 'hero_options'];
                    $options['mobile_url'] = '/storage/demo/abaya-'.str_replace('-wide', '-mobile', $cover).'.webp';
                    $blocks = $blocks->reject(fn ($block) => ($block['type'] ?? '') === 'hero_options')->push($options);
                }
                $entry->update($update + ['blocks' => $blocks->values()->all()]);
            }
        }

        CuratedLink::where('section', 'promo')->where('image', 'like', 'demo/%')->update(['image' => 'demo/abaya-promo-portrait.webp']);
        foreach (['Family Edit' => 'Abaya Pilihan', 'Mom & Kids' => 'Abaya Berlapis', 'Special Occasion' => 'Abaya Momen Istimewa'] as $title => $label) {
            CuratedLink::where('title', $title)->where('url', '/koleksi?category=sarimbit-keluarga')
                ->update(['url' => $catalog, 'title' => $label, 'eyebrow' => 'ABAYA']);
        }
        if (str_starts_with((string) Setting::get('hero_image'), 'demo/')) {
            Setting::put('hero_image', 'demo/abaya-hero-wide.webp');
            if (! Setting::get('hero_image_mobile')) {
                Setting::put('hero_image_mobile', 'demo/abaya-hero-mobile.webp');
            }
        }
        if (str_starts_with((string) Setting::get('welcome_voucher_image'), 'demo/')) {
            Setting::put('welcome_voucher_image', 'demo/abaya-promo-portrait.webp');
        }
        $new = collect(preg_split('/[\s,]+/', (string) Setting::get('home_new_slugs')))->filter();
        if ($new->diff($series->keys())->isEmpty()) {
            Setting::put('home_new_slugs', 'zayna-essential,kamila-embroidery,nayla-travel,alesha-everyday');
        }
    }

    private function demoContent(string $slug): ?ContentEntry
    {
        return ContentEntry::where('slug', $slug)->where('image', 'like', 'demo/%')->first();
    }
}
