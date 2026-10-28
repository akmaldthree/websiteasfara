<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\BusinessAccount;
use App\Models\BusinessOrderRule;
use App\Models\BundleComponent;
use App\Models\CustomQuote;
use App\Models\MediaAsset;
use App\Models\MeasurementTemplate;
use App\Models\NewsletterSubscriber;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\ProductMedia;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\Series;
use App\Models\ServiceCase;
use App\Models\Setting;
use App\Models\Variant;
use App\Models\VariantPrice;
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
        $this->assertSame(169, Variant::where('sku', 'like', 'DEMO-%')->count());
        $this->assertSame(30, ProductMedia::count());
        $this->assertSame(10, Review::where('status', 'approved')->count());
        $this->assertSame(13, ContentEntry::whereIn('slug', ['demo-banner', 'demo-cerita', 'demo-harmoni', 'demo-menu-faq', 'demo-menu-journal', 'pertanyaan-umum', 'akurasi-warna', 'waktu-proses', 'merawat-busana', 'memilih-sarimbit', 'memadukan-abaya', 'promo-demo', 'promo-family'])->count());
        $this->assertSame(1, Coupon::where('code', 'DEMO10')->count());
        $this->assertSame(3, Coupon::whereIn('code', ['DEMO10', 'FAMILY15', 'HEMAT50'])->count());
        $this->assertSame(2, MeasurementTemplate::count());
        $this->assertSame(4, BundleComponent::count());
        $this->assertSame(72, VariantPrice::count());
        $this->assertSame(3, BusinessOrderRule::count());
        $this->assertSame(1, BusinessAccount::where('business_name', 'Butik Sahabat Demo')->count());
        $this->assertSame(1, CustomQuote::where('number', 'DTH-CUSTOM-DEMO-001')->count());
        $this->assertSame(6, NewsletterSubscriber::where('email', 'like', 'newsletter%.demo@dthree.co.id')->count());
        $this->assertSame(4, ProductQuestion::where('status', 'answered')->count());
        $this->assertSame(16, MediaAsset::where('path', 'like', 'demo/%')->count());
        $this->assertSame(8, Order::where(fn ($query) => $query->where('number', 'like', 'DTH-DEMO-%')->orWhere('number', 'DEMO-REVIEW-001'))->count());
        $this->assertSame(7, NotificationDelivery::count());
        $this->assertSame(1, PaymentProof::where('status', 'pending')->count());
        $this->assertSame(1, ServiceCase::where('number', 'CASE-DEMO-001')->count());
        $this->assertTrue(Setting::get('checkout_enabled'));
        $this->assertTrue(Setting::get('cod_enabled'));
        $this->assertSame('BANK DEMO', Setting::get('bank_accounts')[0]['bank']);
        $this->assertSame('demo/hero-family-2x1.png', Setting::get('hero_image'));
        Storage::disk('public')->assertExists('demo/hero-family-2x1.png');
        Storage::disk('public')->assertExists('demo/product-olive-4x5.png');
        $this->assertImageRatio('hero-family-2x1.png', 2.0);
        $this->assertImageRatio('product-olive-4x5.png', 0.8);
        $this->assertImageRatio('promo-taupe-3x4.png', 0.75);
        $this->assertImageRatio('editorial-fabrics-3x2.png', 1.5);
        $this->assertImageRatio('look-olive-beige-4x5.png', 0.8);
        $this->assertImageRatio('payment-proof-demo-3x4.png', 0.75);
        $this->get('/')->assertOk()->assertSee('Tumbuh bersama dalam setiap cerita')->assertSee('Kebersamaan dalam satu warna')->assertSee('SAVE IDR')->assertSee('4.');
        $this->get('/koleksi')->assertOk()->assertSee('Raffah Signature')->assertSee('Hana Flow Khimar');
        $this->get('/koleksi/raffah-signature')->assertOk()->assertSee('Raffah Signature')->assertSee('Olive');
    }

    private function assertImageRatio(string $file, float $expected): void
    {
        [$width, $height] = getimagesize(database_path('seeders/assets/demo/'.$file));
        $this->assertEqualsWithDelta($expected, $width / $height, 0.002, $file.' memiliki rasio yang tidak sesuai.');
    }
}
