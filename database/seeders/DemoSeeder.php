<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\Variant;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DemoSeeder hanya untuk local/testing.');
        }
        $this->call(DatabaseSeeder::class);
        $abaya = Category::updateOrCreate(['slug' => 'abaya-dewasa'], ['name' => 'Abaya Dewasa', 'description' => 'Koleksi abaya contoh untuk pengujian katalog Dthree.', 'seo_title' => 'Abaya Dewasa Dthree', 'seo_description' => 'Data demonstrasi koleksi abaya Dthree.', 'active' => true]);
        $family = Category::updateOrCreate(['slug' => 'sarimbit-keluarga'], ['name' => 'Sarimbit Keluarga', 'description' => 'Koleksi pakaian senada keluarga untuk pengujian.', 'seo_title' => 'Sarimbit Keluarga Dthree', 'seo_description' => 'Data demonstrasi sarimbit keluarga Dthree.', 'active' => true]);
        foreach ([['Raffah', $abaya, 'Ketenangan dalam siluet yang sederhana.', 389000, false], ['Arcelia', $family, 'Warna senada, cerita yang berbeda.', 249000, true], ['Naura', $abaya, 'Sentuhan lembut untuk keseharian Anda.', 429000, false], ['Sakina', $family, 'Melengkapi hangatnya kebersamaan.', 279000, true]] as [$name,$category,$description,$price,$types]) {
            $s = Series::updateOrCreate(['slug' => strtolower($name)], ['name' => $name, 'category_id' => $category->id, 'description' => $description, 'material' => 'Contoh: bahan lembut dan ringan. Konfirmasi spesifikasi sebelum penjualan.', 'care' => 'Cuci lembut dan pisahkan warna gelap.', 'size_guide' => "Contoh panduan ukuran\nM: lingkar dada 100 cm, panjang 138 cm\nL: lingkar dada 106 cm, panjang 140 cm\nXL: lingkar dada 112 cm, panjang 142 cm", 'seo_title' => $name.' — Koleksi Dthree', 'seo_description' => $description.' Data produk demonstrasi.', 'active' => true, 'featured' => true, 'uses_product_type' => $types]);
            foreach (($types ? ['Kemeja Anak', 'Gamis Anak'] : ['']) as $type) {
                foreach (['Olive', 'Black', 'Plum'] as $color) {
                    foreach (['M', 'L', 'XL'] as $size) {
                        // Explicit sample SKU values are demo data, not the admin's SKU generator.
                        $sku = 'DEMO-'.strtoupper(substr($name, 0, 3)).'-'.($type === 'Kemeja Anak' ? 'KA' : ($type === 'Gamis Anak' ? 'GA' : 'AB')).'-'.strtoupper(substr($color, 0, 2)).'-'.$size;
                        Variant::firstOrCreate(['sku' => $sku], ['series_id' => $s->id, 'product_type' => $type, 'color' => $color, 'size' => $size, 'price' => $price, 'stock' => 10, 'weight' => 500, 'active' => true]);
                    }
                }
            }
        }
        foreach (['POS', 'JNE'] as $courier) {
            ShippingRate::firstOrCreate(['courier' => $courier, 'service' => 'DEMO REG'], ['province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2–4 hari (tarif uji)', 'cod' => true]);
        }
        foreach ([
            ['banner', 'Selamat datang di website demo Dthree', 'demo-banner', 'Katalog, checkout, loyalty, dan CMS siap diuji menggunakan data demonstrasi.', '/', 'Lihat beranda', 1],
            ['home_section', 'Nyaman untuk setiap cerita', 'demo-cerita', 'Contoh section beranda yang dapat diubah, dijadwalkan, atau disusun ulang oleh admin.', '/koleksi', 'Lihat koleksi', 2],
            ['menu', 'FAQ', 'demo-menu-faq', null, '/konten/faq/pertanyaan-umum', 'FAQ', 1],
            ['menu', 'Journal', 'demo-menu-journal', null, '/konten/journal/merawat-busana', 'Journal', 2],
            ['faq', 'Pertanyaan umum pemesanan', 'pertanyaan-umum', 'Bagaimana cara memesan dan membayar?', null, null, 1],
            ['journal', 'Cara merawat busana agar tetap nyaman', 'merawat-busana', 'Gunakan pencucian lembut, pisahkan warna, dan ikuti petunjuk bahan pada produk.', null, null, 1],
            ['campaign', 'Promo demo pelanggan baru', 'promo-demo', 'Gunakan kode DEMO10 saat menguji checkout. Promo ini hanya untuk lingkungan demonstrasi.', '/koleksi', 'Pilih produk', 1],
        ] as [$type, $title, $slug, $summary, $url, $label, $sort]) {
            ContentEntry::updateOrCreate(['slug' => $slug], ['type' => $type, 'title' => $title, 'summary' => $summary, 'body' => $summary, 'link_url' => $url, 'link_label' => $label, 'sort_order' => $sort, 'active' => true]);
        }
        Coupon::updateOrCreate(['code' => 'DEMO10'], ['type' => 'percent', 'value' => 10, 'minimum' => 200000, 'maximum' => 50000, 'usage_limit' => 1000, 'starts_at' => now()->subYear(), 'ends_at' => now()->addYear(), 'active' => true, 'segment' => 'all', 'target_type' => 'all', 'per_user_limit' => 3]);
        Setting::put('demo_mode', true);
        Setting::put('checkout_enabled', true);
        Setting::put('cod_enabled', true);
        Setting::put('loyalty_enabled', true);
        Setting::put('bank_accounts', [['bank' => 'BANK DEMO', 'number' => '0000000000', 'owner' => 'DTHREE DEMO']]);
        foreach (['syarat-ketentuan', 'privasi', 'pengiriman', 'retur-garansi', 'tentang'] as $slug) {
            Page::where('slug', $slug)->update(['active' => true, 'body' => 'Halaman contoh untuk pengujian internal. Konten resmi Dthree wajib menggantikan teks ini sebelum website dibuka untuk transaksi publik.', 'seo_title' => Page::where('slug', $slug)->value('title').' — Demo Dthree']);
        }
    }
}
