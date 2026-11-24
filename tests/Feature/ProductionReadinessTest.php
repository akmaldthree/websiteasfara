<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_check_rejects_unsafe_runtime_and_accepts_safe_configuration(): void
    {
        $this->artisan('system:production-check')->assertFailed();

        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'app.debug' => false,
            'app.url' => 'https://store.asfara.co.id',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.secure' => true,
            'session.encrypt' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'database',
            'cache.default' => 'database',
            'queue.default' => 'database',
            'commerce.email_notifications' => false,
        ]);

        $this->artisan('system:production-check')->assertSuccessful();
    }

    public function test_successful_backup_can_be_recorded_for_monitoring(): void
    {
        $this->artisan('system:backup-record', ['file' => 'asfara-20261030T120000Z'])->assertSuccessful();

        $backup = Setting::get('system_backup_last_seen');
        $this->assertSame('asfara-20261030T120000Z', $backup['file']);
        $this->assertNotEmpty($backup['at']);
    }

    public function test_production_https_responses_include_transport_security_headers(): void
    {
        $this->seed();
        $this->app->detectEnvironment(fn (): string => 'production');

        $response = $this->withServerVariables(['HTTPS' => 'on'])
            ->get('https://store.asfara.co.id/')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');

        $contentSecurityPolicy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $contentSecurityPolicy);
        $this->assertStringContainsString('upgrade-insecure-requests', $contentSecurityPolicy);
    }

    public function test_not_found_page_is_branded_and_not_indexable(): void
    {
        $this->get('/halaman-yang-tidak-tersedia')
            ->assertNotFound()
            ->assertSee('ASFARA')
            ->assertSee('Halaman ini tidak ditemukan.')
            ->assertSee('name="robots" content="noindex,nofollow"', false)
            ->assertDontSee('Laravel');
    }

    public function test_storefront_and_cms_expose_keyboard_and_screen_reader_context(): void
    {
        $this->seed();
        $this->get('/')
            ->assertOk()
            ->assertSee('href="#main"', false)
            ->assertSee('aria-labelledby="search-drawer-title"', false)
            ->assertSee('aria-labelledby="cart-drawer-title"', false)
            ->assertSee('aria-live="polite"', false);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_storefront_exposes_complete_seo_discovery_metadata(): void
    {
        $this->seed();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('SearchAction')
            ->assertSee('name="robots" content="index,follow"', false);

        $this->get(route('cart'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,nofollow"', false);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'))
            ->assertSee(route('catalog'));
    }

    public function test_support_and_empty_search_surfaces_use_asfara_identity(): void
    {
        $this->seed();

        $this->get(route('track'))
            ->assertOk()
            ->assertSee('support-page-grid', false)
            ->assertSee('ASF-261124-XXXXXXXX')
            ->assertDontSee('D3-');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('auth-shell', false)
            ->assertSee('ASFARA MEMBERSHIP');

        $this->get(route('catalog', ['q' => 'produk-yang-tidak-ada']))
            ->assertOk()
            ->assertSee('catalog-empty-state', false)
            ->assertSee('Lihat produk terlaris');
    }
}
