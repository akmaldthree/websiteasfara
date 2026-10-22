<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdvancedPromotionTest extends TestCase
{
    use RefreshDatabase;

    private Variant $first;

    private Variant $second;

    private User $user;

    private array $checkout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $category = Category::create(['name' => 'Promo', 'slug' => 'promo']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Promo Series', 'slug' => 'promo-series', 'active' => true]);
        $this->first = Variant::create(['series_id' => $series->id, 'sku' => 'PROMO-A', 'color' => 'Black', 'size' => 'L', 'price' => 300000, 'stock' => 10, 'weight' => 400]);
        $this->second = Variant::create(['series_id' => $series->id, 'sku' => 'PROMO-B', 'color' => 'White', 'size' => 'M', 'price' => 200000, 'stock' => 10, 'weight' => 300]);
        $rate = ShippingRate::create(['courier' => 'JNE', 'service' => 'REG', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2–3 hari']);
        Setting::put('bank_accounts', [['bank' => 'Bank Uji', 'number' => '000', 'owner' => 'Dthree']]);
        Setting::put('checkout_enabled', true);
        $this->user = User::factory()->create();
        $this->checkout = ['name' => 'Pelanggan', 'phone' => '081234567890', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung', 'postal_code' => '46111', 'detail' => 'Alamat', 'payment_method' => 'transfer', 'shipping_rate_id' => $rate->id, 'terms' => '1'];
    }

    public function test_targeted_combinable_promotions_only_discount_eligible_items_and_snapshot_rules(): void
    {
        Coupon::create(['code' => 'SKU10', 'type' => 'percent', 'value' => 10, 'minimum' => 0, 'usage_limit' => 10, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'segment' => 'customer', 'target_type' => 'variant', 'target_id' => $this->first->id, 'combinable' => true, 'priority' => 10, 'per_user_limit' => 1]);
        Coupon::create(['code' => 'EXTRA10', 'type' => 'fixed', 'value' => 10000, 'minimum' => 0, 'usage_limit' => 10, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'target_type' => 'all', 'combinable' => true]);
        $this->checkout['coupon'] = 'extra10, sku10';
        $service = app(CheckoutService::class);
        $cart = [$this->first->id => 1, $this->second->id => 1];
        $quote = $service->quote($cart, $this->checkout, false, $this->user);
        $this->assertSame(40000, $quote['discount']);
        $this->assertSame(475000, $quote['total']);
        $order = $service->place($cart, $this->checkout, hash('sha256', 'advanced-promo'), $this->user->id, $service->fingerprint($quote));
        $this->assertCount(2, $order->coupon_snapshot);
        $this->assertDatabaseCount('coupon_redemptions', 2);

        $this->expectException(ValidationException::class);
        $service->quote($cart, $this->checkout, false, $this->user);
    }

    public function test_non_combinable_or_wrong_segment_coupon_is_rejected(): void
    {
        Coupon::create(['code' => 'SOLO', 'type' => 'fixed', 'value' => 10000, 'minimum' => 0, 'usage_limit' => 10, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'target_type' => 'all']);
        Coupon::create(['code' => 'GUEST', 'type' => 'fixed', 'value' => 10000, 'minimum' => 0, 'usage_limit' => 10, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'segment' => 'guest', 'target_type' => 'all', 'combinable' => true]);
        $this->checkout['coupon'] = 'SOLO,GUEST';
        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->quote([$this->first->id => 1], $this->checkout, false, $this->user);
    }
}
