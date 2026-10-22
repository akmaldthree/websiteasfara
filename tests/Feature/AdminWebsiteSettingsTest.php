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
        foreach (['brand_name', 'brand_subtitle', 'tagline', 'hero_eyebrow', 'hero_title', 'hero_text', 'hero_button_label', 'hero_button_url', 'benefit_1', 'benefit_2', 'benefit_3', 'story_eyebrow', 'story_title', 'story_text', 'footer_text', 'store_location', 'contact_email', 'whatsapp', 'instagram_url', 'tiktok_url', 'facebook_url', 'default_seo_title', 'default_seo_description', 'shipping_mode', 'shipping_provider', 'shipping_api_url', 'tracking_mode', 'whatsapp_mode', 'whatsapp_provider', 'whatsapp_api_url', 'payment_hours', 'cod_hours', 'cod_min', 'cod_max', 'cod_fee', 'cod_percent', 'cod_fee_max', 'loyalty_spend_per_point', 'loyalty_point_value', 'loyalty_min_redeem', 'loyalty_expiry_days'] as $key) {
            $payload[$key] = Setting::get($key, '');
        }
        $payload += ['bank_text' => '', 'shipping_api_token_new' => 'shipping-secret', 'whatsapp_api_token_new' => 'whatsapp-secret'];
        $payload['brand_name'] = 'Dthree Baru';
        $payload['hero_title'] = 'Judul dari dashboard';

        $this->actingAs($admin)->post(route('admin.settings.save'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Dthree Baru', Setting::get('brand_name'));
        $this->assertNotSame('shipping-secret', Setting::get('shipping_api_token'));
        $this->get('/')->assertOk()->assertSee('Dthree Baru')->assertSee('Judul dari dashboard')->assertDontSee('shipping-secret');
    }
}
