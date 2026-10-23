<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\Setting;
use App\Models\Variant;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_store_data_is_complete_and_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(54, Variant::where('sku', 'like', 'DEMO-%')->count());
        $this->assertSame(7, ContentEntry::whereIn('slug', ['demo-banner', 'demo-cerita', 'demo-menu-faq', 'demo-menu-journal', 'pertanyaan-umum', 'merawat-busana', 'promo-demo'])->count());
        $this->assertSame(1, Coupon::where('code', 'DEMO10')->count());
        $this->assertTrue(Setting::get('checkout_enabled'));
        $this->assertTrue(Setting::get('cod_enabled'));
        $this->assertSame('BANK DEMO', Setting::get('bank_accounts')[0]['bank']);
        $this->get('/')->assertOk()->assertSee('Selamat datang di website demo Dthree');
        $this->get('/koleksi')->assertOk()->assertSee('Raffah');
    }
}
