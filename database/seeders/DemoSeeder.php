<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\ProductMedia;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $demoSeedingEnabled = config('app.demo_seeding_enabled') || filter_var(env('ALLOW_DEMO_SEEDING', false), FILTER_VALIDATE_BOOL);
        if (! app()->environment(['local', 'testing']) && ! $demoSeedingEnabled) {
            throw new \RuntimeException('Aktifkan ALLOW_DEMO_SEEDING=true untuk memasang data demonstrasi di luar local/testing.');
        }

        $this->call(DatabaseSeeder::class);
        $this->publishImages();

        $categories = collect([
            ['name' => 'Abaya Dewasa', 'slug' => 'abaya-dewasa', 'description' => 'Abaya modern dengan siluet anggun untuk kegiatan harian dan acara istimewa.'],
            ['name' => 'Sarimbit Keluarga', 'slug' => 'sarimbit-keluarga', 'description' => 'Busana keluarga senada untuk momen hangat dan perayaan bersama.'],
            ['name' => 'Busana Anak', 'slug' => 'busana-anak', 'description' => 'Busana santun anak yang ringan, rapi, dan nyaman bergerak.'],
            ['name' => 'Outer & Khimar', 'slug' => 'outer-khimar', 'description' => 'Pelengkap tampilan modest yang mudah dipadukan dengan koleksi Dthree.'],
        ])->mapWithKeys(function (array $data) {
            $category = Category::updateOrCreate(['slug' => $data['slug']], $data + [
                'seo_title' => $data['name'].' Dthree',
                'seo_description' => $data['description'],
                'active' => true,
            ]);

            return [$data['slug'] => $category];
        });

        foreach ($this->products() as $index => $product) {
            $series = Series::updateOrCreate(['slug' => $product['slug']], [
                'name' => $product['name'],
                'category_id' => $categories[$product['category']]->id,
                'description' => $product['description'],
                'material' => $product['material'],
                'care' => 'Cuci lembut dengan warna serupa, hindari pemutih, dan setrika dengan suhu rendah.',
                'size_guide' => "S: LD 96 cm · panjang 136 cm\nM: LD 102 cm · panjang 138 cm\nL: LD 108 cm · panjang 140 cm\nXL: LD 114 cm · panjang 142 cm",
                'image' => 'demo/'.$product['image'],
                'seo_title' => $product['name'].' — Koleksi Dthree',
                'seo_description' => $product['description'],
                'active' => true,
                'featured' => $index < 6,
                'uses_product_type' => $product['types'] !== [''],
            ]);

            $this->seedVariants($series, $product);
            $this->seedMedia($series, $product);
        }

        $this->seedShippingRates();
        $this->seedContent();
        $this->seedCoupons();
        $this->seedSettings();
        $this->seedPages();
    }

    private function products(): array
    {
        return [
            ['name' => 'Raffah Signature', 'slug' => 'raffah-signature', 'code' => 'RAF', 'category' => 'abaya-dewasa', 'description' => 'Abaya berpotongan A-line dengan detail minimal yang memberi kesan tenang dan berkelas.', 'material' => 'Nidha premium yang jatuh, ringan, dan tidak menerawang.', 'price' => 389000, 'image' => 'raffah-olive.webp', 'gallery' => ['raffah-olive.webp', 'naura-rose.webp', 'mahira-black.webp'], 'colors' => ['Olive', 'Black', 'Sand'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Naura Pleated', 'slug' => 'naura-pleated', 'code' => 'NAU', 'category' => 'abaya-dewasa', 'description' => 'Abaya feminin dengan aksen lipit halus untuk tampilan lembut sepanjang hari.', 'material' => 'Ceruty baby doll berlapis furing premium.', 'price' => 429000, 'image' => 'naura-rose.webp', 'gallery' => ['naura-rose.webp', 'raffah-olive.webp', 'mahira-black.webp'], 'colors' => ['Dusty Rose', 'Mauve', 'Ivory'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Mahira Luxe', 'slug' => 'mahira-luxe', 'code' => 'MAH', 'category' => 'abaya-dewasa', 'description' => 'Abaya hitam premium dengan bordir tonal yang elegan untuk acara formal.', 'material' => 'Nidha Arab premium dengan bordir eksklusif.', 'price' => 549000, 'image' => 'mahira-black.webp', 'gallery' => ['mahira-black.webp', 'raffah-olive.webp', 'naura-rose.webp'], 'colors' => ['Black', 'Midnight Navy', 'Mocha'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Alesha Everyday', 'slug' => 'alesha-everyday', 'code' => 'ALE', 'category' => 'abaya-dewasa', 'description' => 'Abaya praktis dengan potongan bersih untuk menemani aktivitas sehari-hari.', 'material' => 'Madina silk yang adem dan mudah dirawat.', 'price' => 359000, 'image' => 'hero.webp', 'gallery' => ['hero.webp', 'raffah-olive.webp', 'naura-rose.webp'], 'colors' => ['Sage', 'Taupe', 'Black'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Arcelia Family', 'slug' => 'arcelia-family', 'code' => 'ARC', 'category' => 'sarimbit-keluarga', 'description' => 'Koleksi keluarga bernuansa sage yang segar, hangat, dan mudah dipadukan.', 'material' => 'Katun dobby premium dengan aksen bordir lembut.', 'price' => 249000, 'image' => 'arcelia-sage.webp', 'gallery' => ['arcelia-sage.webp', 'campaign-family.webp', 'sakina-plum.webp'], 'colors' => ['Sage', 'Warm Beige'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['Gamis Ibu', 'Koko Ayah', 'Koko Anak', 'Gamis Anak']],
            ['name' => 'Sakina Family', 'slug' => 'sakina-family', 'code' => 'SAK', 'category' => 'sarimbit-keluarga', 'description' => 'Sarimbit plum dan taupe yang menghadirkan kesan hangat dalam setiap kebersamaan.', 'material' => 'Katun jacquard lembut dengan detail bordir senada.', 'price' => 279000, 'image' => 'sakina-plum.webp', 'gallery' => ['sakina-plum.webp', 'campaign-family.webp', 'arcelia-sage.webp'], 'colors' => ['Plum', 'Warm Taupe'], 'sizes' => ['M', 'L', 'XL'], 'types' => ['Gamis Ibu', 'Koko Ayah', 'Koko Anak', 'Gamis Anak']],
            ['name' => 'Harmoni Raya', 'slug' => 'harmoni-raya', 'code' => 'HAR', 'category' => 'sarimbit-keluarga', 'description' => 'Set keluarga bernuansa netral untuk silaturahmi dan perayaan yang berkesan.', 'material' => 'Katun linen premium yang breathable dan bertekstur rapi.', 'price' => 299000, 'image' => 'campaign-family.webp', 'gallery' => ['campaign-family.webp', 'arcelia-sage.webp', 'sakina-plum.webp'], 'colors' => ['Soft Sage', 'Champagne'], 'sizes' => ['M', 'L', 'XL'], 'types' => ['Gamis Ibu', 'Koko Ayah', 'Koko Anak', 'Gamis Anak']],
            ['name' => 'Zahara Kids', 'slug' => 'zahara-kids', 'code' => 'ZAH', 'category' => 'busana-anak', 'description' => 'Gamis anak ringan dengan detail manis dan ruang gerak yang nyaman.', 'material' => 'Katun rayon lembut dan menyerap keringat.', 'price' => 229000, 'image' => 'sakina-plum.webp', 'gallery' => ['sakina-plum.webp', 'arcelia-sage.webp', 'naura-rose.webp'], 'colors' => ['Plum', 'Sage', 'Dusty Rose'], 'sizes' => ['4', '6', '8', '10', '12'], 'types' => ['']],
            ['name' => 'Livia Layer Outer', 'slug' => 'livia-layer-outer', 'code' => 'LIV', 'category' => 'outer-khimar', 'description' => 'Outer panjang berpotongan ringan yang memberi lapisan elegan tanpa terasa berat.', 'material' => 'Woolpeach premium yang ringan dan tidak mudah kusut.', 'price' => 319000, 'image' => 'raffah-olive.webp', 'gallery' => ['raffah-olive.webp', 'hero.webp', 'mahira-black.webp'], 'colors' => ['Olive', 'Black', 'Beige'], 'sizes' => ['S', 'M', 'L', 'XL'], 'types' => ['']],
            ['name' => 'Hana Flow Khimar', 'slug' => 'hana-flow-khimar', 'code' => 'HAN', 'category' => 'outer-khimar', 'description' => 'Khimar instan berpotongan flowy dengan coverage nyaman dan tampilan rapi.', 'material' => 'Diamond crepe ringan, tidak licin, dan mudah dibentuk.', 'price' => 169000, 'image' => 'naura-rose.webp', 'gallery' => ['naura-rose.webp', 'mahira-black.webp', 'raffah-olive.webp'], 'colors' => ['Mauve', 'Black', 'Olive', 'Ivory'], 'sizes' => ['M', 'L', 'XL'], 'types' => ['']],
        ];
    }

    private function seedVariants(Series $series, array $product): void
    {
        foreach ($product['types'] as $typeIndex => $type) {
            foreach ($product['colors'] as $colorIndex => $color) {
                foreach ($product['sizes'] as $sizeIndex => $size) {
                    $typeCode = match ($type) {
                        'Gamis Ibu' => 'GIB',
                        'Koko Ayah' => 'KAY',
                        'Koko Anak' => 'KAN',
                        'Gamis Anak' => 'GAN',
                        default => 'STD',
                    };
                    $colorCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', mb_substr($color, 0, 3)));
                    $sku = 'DEMO-'.$product['code'].'-'.$typeCode.'-'.$colorCode.'-'.$size;
                    $price = $product['price'] + ($typeIndex * 20000) + ($sizeIndex >= 2 ? 10000 : 0);

                    Variant::updateOrCreate(['sku' => $sku], [
                        'series_id' => $series->id,
                        'product_type' => $type,
                        'color' => $color,
                        'size' => $size,
                        'price' => $price,
                        'stock' => 6 + (abs(crc32($sku)) % 25),
                        'reserved' => 0,
                        'low_stock' => 5,
                        'weight' => in_array($type, ['Koko Anak', 'Gamis Anak'], true) ? 350 : 650,
                        'fulfillment' => 'ready_stock',
                        'lead_time' => null,
                        'active' => true,
                    ]);
                }
            }
        }
    }

    private function seedMedia(Series $series, array $product): void
    {
        foreach ($product['gallery'] as $sort => $image) {
            ProductMedia::updateOrCreate(
                ['series_id' => $series->id, 'image' => 'demo/'.$image],
                ['variant_id' => null, 'color' => null, 'alt_text' => $product['name'].' — foto '.($sort + 1), 'sort_order' => $sort, 'active' => true]
            );
        }
    }

    private function publishImages(): void
    {
        foreach (glob(database_path('seeders/assets/demo/*.webp')) ?: [] as $source) {
            Storage::disk('public')->put('demo/'.basename($source), file_get_contents($source));
        }
    }

    private function seedShippingRates(): void
    {
        foreach ([
            ['JNE', 'REG', 'DKI Jakarta', 'Jakarta', 18000, 10000, '2–3 hari', true],
            ['JNE', 'YES', 'DKI Jakarta', 'Jakarta', 30000, 18000, '1 hari', false],
            ['JNE', 'REG', 'Jawa Barat', 'Bandung', 16000, 9000, '2–3 hari', true],
            ['JNE', 'REG', 'Jawa Barat', 'Tasikmalaya', 15000, 9000, '1–2 hari', true],
            ['POS', 'Kilat Khusus', 'Jawa Tengah', 'Semarang', 22000, 12000, '3–5 hari', true],
            ['POS', 'Kilat Khusus', 'Jawa Timur', 'Surabaya', 25000, 14000, '3–5 hari', true],
        ] as [$courier, $service, $province, $city, $firstKg, $extraKg, $estimate, $cod]) {
            ShippingRate::updateOrCreate(
                compact('courier', 'service', 'province', 'city'),
                ['first_kg' => $firstKg, 'extra_kg' => $extraKg, 'estimate' => $estimate, 'cod' => $cod, 'active' => true]
            );
        }
    }

    private function seedContent(): void
    {
        $entries = [
            ['banner', 'Kebersamaan dalam satu warna', 'demo-banner', 'Temukan koleksi sarimbit keluarga dengan bahan nyaman dan warna yang dirancang untuk saling melengkapi.', 'campaign-family.webp', '/koleksi?category=sarimbit-keluarga', 'Lihat sarimbit', 1],
            ['home_section', 'Dirancang untuk bergerak bersama Anda', 'demo-cerita', 'Material pilihan, potongan nyaman, dan detail yang tetap rapi dari pagi hingga malam.', 'raffah-olive.webp', '/koleksi?category=abaya-dewasa', 'Jelajahi abaya', 2],
            ['home_section', 'Warna hangat untuk momen istimewa', 'demo-harmoni', 'Padukan koleksi dewasa dan anak untuk menciptakan tampilan keluarga yang selaras.', 'sakina-plum.webp', '/koleksi?category=sarimbit-keluarga', 'Pilih koleksi', 3],
            ['menu', 'FAQ', 'demo-menu-faq', null, null, '/konten/faq/pertanyaan-umum', 'FAQ', 1],
            ['menu', 'Journal', 'demo-menu-journal', null, null, '/konten/journal/merawat-busana', 'Journal', 2],
            ['faq', 'Bagaimana memilih ukuran yang tepat?', 'pertanyaan-umum', 'Gunakan panduan ukuran pada halaman produk. Ukur lingkar dada pada bagian terlebar dan pilih ukuran dengan ruang longgar 4–8 cm sesuai preferensi.', null, null, null, 1],
            ['faq', 'Apakah warna produk sama dengan foto?', 'akurasi-warna', 'Kami berusaha menampilkan warna seakurat mungkin. Sedikit perbedaan dapat terjadi karena pencahayaan foto dan pengaturan layar.', null, null, null, 2],
            ['faq', 'Berapa lama pesanan diproses?', 'waktu-proses', 'Produk ready stock diproses 1–2 hari kerja setelah pembayaran terverifikasi. Estimasi pengiriman mengikuti layanan kurir.', null, null, null, 3],
            ['journal', 'Cara merawat busana agar tetap nyaman', 'merawat-busana', 'Pisahkan warna gelap dan terang, gunakan deterjen lembut, hindari pemutih, dan keringkan di tempat teduh agar serat serta warna lebih terjaga.', 'naura-rose.webp', null, null, 1],
            ['journal', 'Panduan memilih sarimbit keluarga', 'memilih-sarimbit', 'Mulai dari palet warna yang disukai, tentukan kebutuhan tiap anggota keluarga, lalu cocokkan ukuran melalui panduan pada setiap produk.', 'arcelia-sage.webp', null, null, 2],
            ['journal', 'Tiga cara memadukan abaya', 'memadukan-abaya', 'Gunakan aksesori bertekstur lembut, pilih alas kaki bernuansa netral, dan tambahkan outer ringan untuk menciptakan tampilan berbeda.', 'mahira-black.webp', null, null, 3],
            ['campaign', 'Hadiah pelanggan baru', 'promo-demo', 'Gunakan kode DEMO10 untuk potongan 10% pada transaksi demonstrasi pertama Anda.', 'campaign-family.webp', '/koleksi', 'Mulai belanja', 1],
            ['campaign', 'Lengkapi gaya keluarga', 'promo-family', 'Gunakan FAMILY15 untuk potongan 15% pada pembelian koleksi sarimbit dengan minimum transaksi yang berlaku.', 'sakina-plum.webp', '/koleksi?category=sarimbit-keluarga', 'Lihat koleksi', 2],
        ];

        foreach ($entries as [$type, $title, $slug, $summary, $image, $url, $label, $sort]) {
            ContentEntry::updateOrCreate(['slug' => $slug], [
                'type' => $type,
                'title' => $title,
                'summary' => $summary,
                'body' => $summary,
                'image' => $image ? 'demo/'.$image : null,
                'link_url' => $url,
                'link_label' => $label,
                'seo_title' => $title.' — Dthree',
                'seo_description' => $summary,
                'sort_order' => $sort,
                'editorial_status' => 'approved',
                'active' => true,
            ]);
        }
    }

    private function seedCoupons(): void
    {
        foreach ([
            ['DEMO10', 'percent', 10, 200000, 50000, 'all', 'all'],
            ['FAMILY15', 'percent', 15, 750000, 150000, 'all', 'category'],
            ['HEMAT50', 'fixed', 50000, 500000, 50000, 'all', 'all'],
        ] as [$code, $type, $value, $minimum, $maximum, $segment, $targetType]) {
            Coupon::updateOrCreate(['code' => $code], [
                'type' => $type,
                'value' => $value,
                'minimum' => $minimum,
                'maximum' => $maximum,
                'usage_limit' => 1000,
                'starts_at' => now()->subYear(),
                'ends_at' => now()->addYears(2),
                'active' => true,
                'segment' => $segment,
                'target_type' => $targetType,
                'per_user_limit' => 3,
            ]);
        }
    }

    private function seedSettings(): void
    {
        foreach ([
            'demo_mode' => true,
            'checkout_enabled' => true,
            'cod_enabled' => true,
            'loyalty_enabled' => true,
            'analytics_enabled' => true,
            'brand_name' => 'Dthree',
            'hero_eyebrow' => 'Koleksi terbaru Dthree',
            'hero_title' => 'Tumbuh bersama dalam setiap cerita',
            'hero_text' => 'Busana modest yang nyaman untuk perempuan dan keluarga Indonesia, dirancang dengan warna hangat serta detail yang berkesan.',
            'hero_button_label' => 'Belanja koleksi',
            'hero_button_url' => '/koleksi',
            'hero_image' => 'demo/hero.webp',
            'benefit_1' => 'Bahan nyaman pilihan',
            'benefit_2' => 'Pengiriman ke seluruh Indonesia',
            'benefit_3' => 'Retur mudah sesuai ketentuan',
            'story_eyebrow' => 'Tentang Dthree',
            'story_title' => 'Busana yang menyertai momen keluarga',
            'story_text' => 'Kami menghadirkan koleksi dengan siluet santun, warna mudah dipadukan, dan kenyamanan yang mendukung aktivitas Anda.',
            'store_location' => 'Tasikmalaya · Indonesia',
            'contact_phone' => '0812-0000-0000',
            'contact_email' => 'hello@dthree.co.id',
            'bank_accounts' => [['bank' => 'BANK DEMO', 'number' => '0000000000', 'owner' => 'DTHREE DEMO']],
            'loyalty_tiers' => [
                ['name' => 'Member', 'minimum' => 0, 'benefit' => 'Kumpulkan poin di setiap transaksi', 'value_bonus_percent' => 0],
                ['name' => 'Sahabat', 'minimum' => 500, 'benefit' => 'Nilai penukaran poin lebih besar', 'value_bonus_percent' => 10],
                ['name' => 'Keluarga', 'minimum' => 1500, 'benefit' => 'Prioritas promo dan bonus poin', 'value_bonus_percent' => 20],
            ],
        ] as $key => $value) {
            Setting::put($key, $value);
        }
    }

    private function seedPages(): void
    {
        $copy = [
            'syarat-ketentuan' => 'Dengan melakukan pemesanan, pelanggan menyetujui detail produk, harga, metode pembayaran, serta kebijakan pengiriman yang ditampilkan saat checkout.',
            'privasi' => 'Dthree menggunakan data pelanggan hanya untuk memproses pesanan, memberi layanan pelanggan, dan menjalankan fitur akun sesuai persetujuan.',
            'pengiriman' => 'Pesanan ready stock diproses 1–2 hari kerja. Estimasi tiba mengikuti layanan kurir dan lokasi tujuan yang dipilih saat checkout.',
            'retur-garansi' => 'Pengajuan retur dapat dilakukan sesuai batas waktu dan kondisi produk pada kebijakan toko. Simpan bukti pembelian dan dokumentasi saat paket diterima.',
            'tentang' => 'Dthree adalah brand modest fashion dari Tasikmalaya yang menghadirkan busana perempuan dan keluarga dengan fokus pada kenyamanan, detail, dan kebersamaan.',
        ];

        foreach ($copy as $slug => $body) {
            $page = Page::where('slug', $slug)->first();
            if ($page) {
                $page->update(['active' => true, 'body' => $body, 'editorial_status' => 'approved', 'seo_title' => $page->title.' — Dthree', 'seo_description' => $body]);
            }
        }
    }
}
