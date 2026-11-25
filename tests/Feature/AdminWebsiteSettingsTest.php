<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Series;
use App\Models\Setting;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWebsiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_brand_and_future_integrations_without_exposing_tokens(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $payload = [];
        foreach (['brand_name', 'brand_subtitle', 'tagline', 'hero_eyebrow', 'hero_title', 'hero_text', 'hero_button_label', 'hero_button_url', 'benefit_1', 'benefit_2', 'benefit_3', 'story_eyebrow', 'story_title', 'story_text', 'footer_text', 'store_location', 'contact_email', 'whatsapp', 'instagram_url', 'tiktok_url', 'facebook_url', 'default_seo_title', 'default_seo_description', 'shipping_mode', 'shipping_provider', 'shipping_api_url', 'tracking_mode', 'whatsapp_mode', 'whatsapp_provider', 'whatsapp_api_url', 'payment_hours', 'cod_hours', 'cod_min', 'cod_max', 'cod_fee', 'cod_percent', 'cod_fee_max', 'loyalty_spend_per_point', 'loyalty_point_value', 'loyalty_min_redeem', 'loyalty_expiry_days', 'loyalty_bonus_name', 'loyalty_bonus_multiplier', 'loyalty_bonus_start', 'loyalty_bonus_end', 'loyalty_bonus_segment', 'loyalty_bonus_tier', 'loyalty_bonus_target_type', 'loyalty_bonus_target_id'] as $key) {
            $payload[$key] = Setting::get($key, '');
        }
        $payload += [
            'bank_text' => '',
            'shipping_api_token_new' => 'shipping-secret',
            'whatsapp_api_token_new' => 'whatsapp-secret',
            'cod_segment_guest' => '1',
            'cod_segment_customer' => '1',
            'cod_segment_business' => '1',
            'cod_blocked_phones_text' => "0812-3456\n0812 3456",
            'cod_excluded_skus_text' => "abc-1\nABC-1",
            'cod_excluded_categories_text' => 'Abaya',
            'loyalty_tiers_text' => "Member | 0 | Akses poin | 0\nSilver | 500 | Nilai poin lebih tinggi | 5\nGold | 1500 | Prioritas layanan | 10",
            'payment_review_sla_hours' => 4,
            'theme_primary_color' => '#112233',
            'theme_accent_color' => '#C49A6C',
            'theme_background_color' => '#F8F4EE',
            'theme_surface_color' => '#FFFFFF',
            'theme_text_color' => '#202020',
            'theme_palette_base' => '#F9F8F6',
            'theme_palette_surface' => '#EFE9E3',
            'theme_palette_structure' => '#D9CFC7',
            'theme_palette_accent' => '#C9B59C',
            'theme_font' => 'poppins',
            'theme_container_width' => 1400,
            'theme_radius' => 12,
            'theme_header_style' => 'compact',
            'hero_content_style' => 'compact',
            'hero_autoplay_seconds' => 5.5,
            'social_proof_interval' => 30,
            'social_proof_cities' => 'Jakarta, Bandung',
            'header_announcement_enabled' => '1',
            'announcement_interval_seconds' => 3.5,
            'header_categories_enabled' => '1',
            'header_tracking_enabled' => '1',
            'header_sticky' => '1',
            'footer_pages_enabled' => '1',
            'footer_social_enabled' => '1',
            'floating_whatsapp_enabled' => '1',
            'social_proof_enabled' => '1',
            'hero_video_enabled' => '1',
            'hero_campaign_enabled' => '0',
            'hero_video_has_audio' => '1',
            'welcome_voucher_enabled' => '1',
            'welcome_voucher_title' => 'Hadiah Asfara',
            'welcome_voucher_text' => 'Promo khusus pelanggan baru.',
            'welcome_voucher_code' => 'ASAFIRST',
            'welcome_voucher_cta_label' => 'Belanja pilihan',
            'welcome_voucher_cta_url' => '/koleksi?sort=newest',
            'welcome_voucher_delay_seconds' => 2,
            'welcome_voucher_frequency_days' => 14,
            'footer_copyright' => 'Hak cipta Asfara.',
            'home_featured_slug' => '',
            'home_category_slugs' => '',
            'home_collections_enabled' => '1',
            'home_section_order' => 'hero,campaign,new_in,categories,collections,featured,look,sale,editorial,story',
            'storefront_copy_id' => ['featured_collection' => 'KOLEKSI PILIHAN CMS', 'catalog_title' => 'BUSANA'],
            'storefront_copy_en' => ['featured_collection' => 'CMS CURATED COLLECTION', 'catalog_title' => 'APPAREL'],
        ];
        $payload['brand_name'] = 'Asfara Baru';
        $payload['hero_title'] = 'Judul dari dashboard';

        $this->actingAs($admin)->post(route('admin.settings.save'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Asfara Baru', Setting::get('brand_name'));
        $this->assertSame(['08123456'], Setting::get('cod_blocked_phones'));
        $this->assertSame(['ABC-1'], Setting::get('cod_excluded_skus'));
        $this->assertSame(['abaya'], Setting::get('cod_excluded_categories'));
        $this->assertSame('compact', Setting::get('hero_content_style'));
        $this->assertSame(5.5, (float) Setting::get('hero_autoplay_seconds'));
        $this->assertSame('poppins', Setting::get('theme_font'));
        $this->assertSame('#F9F8F6', Setting::get('theme_palette_base'));
        $this->assertSame('#C9B59C', Setting::get('theme_palette_accent'));
        $this->assertSame(30, Setting::get('social_proof_interval'));
        $this->assertSame(3.5, (float) Setting::get('announcement_interval_seconds'));
        $this->assertTrue(Setting::get('hero_video_enabled'));
        $this->assertFalse(Setting::get('hero_campaign_enabled'));
        $this->assertTrue(Setting::get('hero_video_has_audio'));
        $this->assertSame('/koleksi?sort=newest', Setting::get('welcome_voucher_cta_url'));
        $this->assertSame(14, Setting::get('welcome_voucher_frequency_days'));
        $this->assertSame('KOLEKSI PILIHAN CMS', Setting::get('storefront_copy_id')['featured_collection']);
        $this->assertSame(['hero', 'campaign', 'new_in', 'categories', 'collections', 'signature_banner', 'signature', 'studio_banner', 'studio'], Setting::get('home_section_order'));
        $this->assertSame(['guest', 'customer', 'business'], Setting::get('cod_allowed_segments'));
        $this->assertEquals(['name' => 'Silver', 'minimum' => 500, 'benefit' => 'Nilai poin lebih tinggi', 'value_bonus_percent' => 5], Setting::get('loyalty_tiers')[1]);
        $this->assertNotSame('shipping-secret', Setting::get('shipping_api_token'));
        $category = Category::create(['name' => 'Kampanye', 'slug' => 'kampanye-settings', 'active' => true]);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Produk kurasi', 'slug' => 'produk-kurasi', 'active' => true]);
        Variant::create(['series_id' => $series->id, 'sku' => 'KUR-01', 'color' => 'Black', 'size' => 'M', 'price' => 199000, 'stock' => 4, 'weight' => 500]);
        ContentEntry::create(['type' => 'campaign', 'title' => 'Kurasi CMS', 'slug' => 'kurasi-cms', 'active' => true, 'editorial_status' => 'approved',
            'blocks' => [['type' => 'campaign_product', 'series_id' => $series->id]],
        ]);
        $this->get('/')->assertOk()->assertSee('Asfara Baru')->assertSee('Judul dari dashboard')->assertSee('KOLEKSI PILIHAN CMS')->assertSee('data-duration="5500"', false)->assertSee('data-announcement-interval="3500"', false)->assertSee('data-notice-prev', false)->assertSee('data-notice-next', false)->assertSee('--palette-sage:#F9F8F6', false)->assertSee('--palette-gold:#C9B59C', false)->assertSee('--text:#202020', false)->assertSee("--body-font:'Poppins',Arial,sans-serif", false)->assertSee('fonts.googleapis.com/css2?family=Poppins', false)->assertSee('theme-header-compact')->assertDontSee('shipping-secret');
        $this->get('/koleksi')->assertOk()->assertSee('BUSANA');
        $this->withSession(['locale' => 'en'])->get('/')->assertOk()->assertSee('CMS CURATED COLLECTION');
    }
}
