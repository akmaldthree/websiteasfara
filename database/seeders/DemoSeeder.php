<?php

namespace Database\Seeders;

use App\Models\Category;
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
        $abaya = Category::firstOrCreate(['slug' => 'abaya-dewasa'], ['name' => 'Abaya Dewasa']);
        $family = Category::firstOrCreate(['slug' => 'sarimbit-keluarga'], ['name' => 'Sarimbit Keluarga']);
        foreach ([['Raffah', $abaya, 'Ketenangan dalam siluet yang sederhana.', 389000, false], ['Arcelia', $family, 'Warna senada, cerita yang berbeda.', 249000, true], ['Naura', $abaya, 'Sentuhan lembut untuk keseharian Anda.', 429000, false], ['Sakina', $family, 'Melengkapi hangatnya kebersamaan.', 279000, true]] as [$name,$category,$description,$price,$types]) {
            $s = Series::firstOrCreate(['slug' => strtolower($name)], ['name' => $name, 'category_id' => $category->id, 'description' => $description, 'material' => 'Contoh: bahan lembut dan ringan. Konfirmasi spesifikasi sebelum penjualan.', 'care' => 'Cuci lembut dan pisahkan warna gelap.', 'size_guide' => "Contoh panduan ukuran\nM: lingkar dada 100 cm, panjang 138 cm\nL: lingkar dada 106 cm, panjang 140 cm\nXL: lingkar dada 112 cm, panjang 142 cm", 'active' => true, 'featured' => true, 'uses_product_type' => $types]);
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
        Setting::put('demo_mode', true);
        foreach (['syarat-ketentuan', 'privasi'] as $slug) {
            Page::where('slug', $slug)->update(['active' => true, 'body' => 'Halaman contoh untuk pengujian internal. Kebijakan resmi Dthree belum diterbitkan.']);
        }
    }
}
