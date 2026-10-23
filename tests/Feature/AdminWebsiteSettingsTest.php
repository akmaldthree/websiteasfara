<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
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
            'theme_font' => 'modern',
            'theme_container_width' => 1400,
            'theme_radius' => 12,
            'theme_header_style' => 'compact',
            'header_announcement_enabled' => '1',
            'header_categories_enabled' => '1',
            'header_tracking_enabled' => '1',
            'header_sticky' => '1',
            'footer_pages_enabled' => '1',
            'footer_social_enabled' => '1',
            'footer_copyright' => 'Hak cipta Dthree.',
        ];
        $payload['brand_name'] = 'Dthree Baru';
        $payload['hero_title'] = 'Judul dari dashboard';

        $this->actingAs($admin)->post(route('admin.settings.save'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Dthree Baru', Setting::get('brand_name'));
        $this->assertSame(['08123456'], Setting::get('cod_blocked_phones'));
        $this->assertSame(['ABC-1'], Setting::get('cod_excluded_skus'));
        $this->assertSame(['abaya'], Setting::get('cod_excluded_categories'));
        $this->assertSame(['guest', 'customer', 'business'], Setting::get('cod_allowed_segments'));
        $this->assertSame(['name' => 'Silver', 'minimum' => 500, 'benefit' => 'Nilai poin lebih tinggi', 'value_bonus_percent' => 5], Setting::get('loyalty_tiers')[1]);
        $this->assertNotSame('shipping-secret', Setting::get('shipping_api_token'));
        $this->get('/')->assertOk()->assertSee('Dthree Baru')->assertSee('Judul dari dashboard')->assertSee('--ink:#112233', false)->assertSee('theme-header-compact')->assertDontSee('shipping-secret');
    }
}
