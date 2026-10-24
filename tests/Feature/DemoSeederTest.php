<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\ProductMedia;
use App\Models\Series;
use App\Models\Setting;
use App\Models\Variant;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_store_data_is_complete_and_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(10, Series::whereIn('slug', ['raffah-signature', 'naura-pleated', 'mahira-luxe', 'alesha-everyday', 'arcelia-family', 'sakina-family', 'harmoni-raya', 'zahara-kids', 'livia-layer-outer', 'hana-flow-khimar'])->count());
        $this->assertSame(167, Variant::where('sku', 'like', 'DEMO-%')->count());
        $this->assertSame(30, ProductMedia::count());
        $this->assertSame(13, ContentEntry::whereIn('slug', ['demo-banner', 'demo-cerita', 'demo-harmoni', 'demo-menu-faq', 'demo-menu-journal', 'pertanyaan-umum', 'akurasi-warna', 'waktu-proses', 'merawat-busana', 'memilih-sarimbit', 'memadukan-abaya', 'promo-demo', 'promo-family'])->count());
        $this->assertSame(1, Coupon::where('code', 'DEMO10')->count());
        $this->assertSame(3, Coupon::whereIn('code', ['DEMO10', 'FAMILY15', 'HEMAT50'])->count());
        $this->assertTrue(Setting::get('checkout_enabled'));
        $this->assertTrue(Setting::get('cod_enabled'));
        $this->assertSame('BANK DEMO', Setting::get('bank_accounts')[0]['bank']);
        $this->assertSame('demo/hero.webp', Setting::get('hero_image'));
        Storage::disk('public')->assertExists('demo/hero.webp');
        Storage::disk('public')->assertExists('demo/raffah-olive.webp');
        $this->get('/')->assertOk()->assertSee('Tumbuh bersama dalam setiap cerita')->assertSee('Kebersamaan dalam satu warna');
        $this->get('/koleksi')->assertOk()->assertSee('Raffah Signature')->assertSee('Hana Flow Khimar');
        $this->get('/koleksi/raffah-signature')->assertOk()->assertSee('Raffah Signature')->assertSee('Olive');
    }
}
