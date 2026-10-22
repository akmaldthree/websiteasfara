<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PriceGroup;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (config('commerce.roles') as $name => $permissions) {
            Role::firstOrCreate(['name' => $name], ['permissions' => $permissions]);
        }
        foreach (['brand_name' => 'Dthree', 'tagline' => 'Dari Tasikmalaya, untuk setiap cerita Anda.', 'hero_title' => 'Indah dalam setiap cerita.', 'hero_text' => 'Pilihan busana yang terasa dekat dengan diri Anda. Nyaman untuk keseharian, istimewa untuk kebersamaan.', 'checkout_enabled' => false, 'cod_enabled' => false, 'cod_min' => 0, 'cod_max' => 1000000, 'cod_fee' => 0, 'cod_percent' => 0, 'cod_fee_max' => 100000, 'payment_hours' => 24, 'cod_hours' => 24, 'bank_accounts' => [], 'whatsapp' => '', 'demo_mode' => false, 'return_days' => 7, 'warranty_days' => 30, 'case_evidence_required' => true, 'return_reasons' => ['Barang rusak', 'Barang tidak sesuai', 'Ukuran tidak sesuai'], 'return_excluded_skus' => [], 'return_shipping_policy' => 'Biaya pengiriman dikonfirmasi CS setelah peninjauan.'] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        foreach (['loyalty_enabled' => false, 'loyalty_spend_per_point' => 10000, 'loyalty_point_value' => 100, 'loyalty_min_redeem' => 100, 'loyalty_expiry_days' => 365] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        foreach (['syarat-ketentuan' => 'Syarat dan ketentuan', 'privasi' => 'Kebijakan privasi', 'pengiriman' => 'Pengiriman', 'retur-garansi' => 'Retur dan garansi', 'tentang' => 'Tentang Dthree'] as $slug => $title) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => 'Isi kebijakan resmi Dthree melalui dashboard sebelum membuka checkout.', 'active' => false]);
        }
        foreach ([
            ['name' => 'Reseller', 'slug' => 'reseller', 'business_type' => 'reseller', 'minimum_order_quantity' => 3],
            ['name' => 'Dropshipper', 'slug' => 'dropshipper', 'business_type' => 'dropshipper', 'minimum_order_quantity' => 1],
            ['name' => 'Grosir', 'slug' => 'grosir', 'business_type' => 'wholesale', 'minimum_order_quantity' => 12],
        ] as $group) {
            PriceGroup::firstOrCreate(['slug' => $group['slug']], $group + ['minimum_order_amount' => 0, 'active' => true]);
        }
    }
}
