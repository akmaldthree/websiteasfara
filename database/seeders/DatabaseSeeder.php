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
        foreach (['brand_name' => 'Dthree', 'brand_subtitle' => 'MODEST WEAR', 'tagline' => 'Dari Tasikmalaya, untuk setiap cerita Anda.', 'hero_eyebrow' => 'DTHREE · MODEST IN EVERY MOMENT', 'hero_title' => 'Indah dalam setiap cerita.', 'hero_text' => 'Pilihan busana yang terasa dekat dengan diri Anda. Nyaman untuk keseharian, istimewa untuk kebersamaan.', 'hero_button_label' => 'Temukan koleksi Anda', 'hero_button_url' => '/koleksi', 'benefit_1' => 'Pilihan untuk seluruh keluarga', 'benefit_2' => 'Pengiriman POS & JNE', 'benefit_3' => 'Transfer bank & COD sesuai wilayah', 'story_eyebrow' => 'BERSAMA DTHREE', 'story_title' => 'Untuk Anda. Untuk kebersamaan.', 'story_text' => 'Dari hari yang sederhana hingga pertemuan yang dinanti. Temukan busana yang memberi ruang untuk bergerak, berbagi, dan menjadi diri sendiri.', 'hero_height_desktop' => 633, 'hero_autoplay_seconds' => 6.5, 'storefront_animation_speed' => 550, 'product_grid_columns' => 4, 'home_featured_enabled' => true, 'home_categories_enabled' => true, 'home_collections_enabled' => true, 'home_look_enabled' => true, 'home_sale_enabled' => true, 'product_related_enabled' => true, 'cart_recommendations_enabled' => true, 'home_editorial_sections_enabled' => false, 'home_story_enabled' => false, 'footer_text' => 'Busana yang menyatukan kenyamanan, kesederhanaan, dan cerita Anda.', 'store_location' => 'Tasikmalaya, Indonesia', 'default_seo_title' => 'Dthree — Dalam setiap cerita', 'default_seo_description' => 'Dthree — koleksi busana muslim dan muslimah.', 'shipping_mode' => 'manual', 'shipping_provider' => 'manual', 'shipping_api_url' => '', 'tracking_mode' => 'manual', 'whatsapp_mode' => 'manual', 'whatsapp_provider' => 'manual', 'whatsapp_api_url' => '', 'checkout_enabled' => false, 'cod_enabled' => false, 'cod_min' => 0, 'cod_max' => 1000000, 'cod_fee' => 0, 'cod_percent' => 0, 'cod_fee_max' => 100000, 'payment_hours' => 24, 'cod_hours' => 24, 'bank_accounts' => [], 'whatsapp' => '', 'contact_email' => '', 'instagram_url' => '', 'tiktok_url' => '', 'facebook_url' => '', 'demo_mode' => false, 'return_days' => 7, 'warranty_days' => 30, 'case_evidence_required' => true, 'return_reasons' => ['Barang rusak', 'Barang tidak sesuai', 'Ukuran tidak sesuai'], 'return_excluded_skus' => [], 'return_shipping_policy' => 'Biaya pengiriman dikonfirmasi CS setelah peninjauan.'] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        Setting::firstOrCreate(['key' => 'payment_review_sla_hours'], ['value' => 4]);
        Setting::firstOrCreate(['key' => 'free_shipping_threshold'], ['value' => 500000]);
        Setting::firstOrCreate(['key' => 'staff_two_factor_required'], ['value' => false]);
        foreach (['theme_palette_base' => '#F9F8F6', 'theme_palette_surface' => '#EFE9E3', 'theme_palette_structure' => '#D9CFC7', 'theme_palette_accent' => '#C9B59C', 'theme_text_color' => '#302D2A', 'theme_font' => 'poppins', 'theme_container_width' => 1520, 'theme_radius' => 0, 'theme_header_style' => 'standard'] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        foreach (['header_announcement_enabled' => true, 'header_categories_enabled' => true, 'header_tracking_enabled' => true, 'header_sticky' => true, 'footer_pages_enabled' => true, 'footer_social_enabled' => true, 'footer_copyright' => '', 'floating_whatsapp_enabled' => true, 'social_proof_enabled' => true, 'social_proof_interval' => 24, 'social_proof_cities' => 'Jakarta, Bandung, Surabaya, Yogyakarta, Makassar, Medan', 'hero_content_style' => 'editorial'] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        foreach (['home_featured_slug' => '', 'home_category_slugs' => '', 'home_section_order' => array_keys(config('storefront.home_sections')), 'storefront_copy_id' => [], 'storefront_copy_en' => []] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        foreach (['loyalty_enabled' => false, 'loyalty_spend_per_point' => 10000, 'loyalty_point_value' => 100, 'loyalty_min_redeem' => 100, 'loyalty_expiry_days' => 365, 'loyalty_tiers' => [['name' => 'Member', 'minimum' => 0, 'benefit' => 'Akses program poin', 'value_bonus_percent' => 0], ['name' => 'Silver', 'minimum' => 500, 'benefit' => 'Akses penawaran member', 'value_bonus_percent' => 0], ['name' => 'Gold', 'minimum' => 1500, 'benefit' => 'Prioritas kampanye member', 'value_bonus_percent' => 0]], 'loyalty_bonus_name' => '', 'loyalty_bonus_multiplier' => 1, 'loyalty_bonus_start' => '', 'loyalty_bonus_end' => '', 'loyalty_bonus_segment' => 'all', 'loyalty_bonus_tier' => '', 'loyalty_bonus_target_type' => 'all', 'loyalty_bonus_target_id' => null, 'analytics_enabled' => true, 'cod_allowed_segments' => ['guest', 'customer', 'business'], 'cod_blocked_phones' => [], 'cod_excluded_skus' => [], 'cod_excluded_categories' => []] as $key => $value) {
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
