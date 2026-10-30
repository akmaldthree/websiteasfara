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
            'app.url' => 'https://store.dthree.co.id',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.secure' => true,
            'commerce.email_notifications' => false,
        ]);

        $this->artisan('system:production-check')->assertSuccessful();
    }

    public function test_successful_backup_can_be_recorded_for_monitoring(): void
    {
        $this->artisan('system:backup-record', ['file' => 'dthree-20261030T120000Z'])->assertSuccessful();

        $backup = Setting::get('system_backup_last_seen');
        $this->assertSame('dthree-20261030T120000Z', $backup['file']);
        $this->assertNotEmpty($backup['at']);
    }

    public function test_production_https_responses_include_transport_security_headers(): void
    {
        $this->seed();
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->withServerVariables(['HTTPS' => 'on'])
            ->get('https://store.dthree.co.id/')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
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
}
