<?php

namespace Tests\Feature;

use App\Models\BundleComponent;
use App\Models\Category;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use App\Services\CheckoutService;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductBundleTest extends TestCase
{
    use RefreshDatabase;

    private Variant $bundle;

    private Variant $first;

    private Variant $second;

    private array $checkout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $category = Category::create(['name' => 'Bundle', 'slug' => 'bundle']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Paket Keluarga', 'slug' => 'paket-keluarga', 'active' => true]);
        $this->first = Variant::create(['series_id' => $series->id, 'sku' => 'BUNDLE-COMP-A', 'color' => 'Black', 'size' => 'L', 'price' => 200000, 'stock' => 5, 'weight' => 400]);
        $this->second = Variant::create(['series_id' => $series->id, 'sku' => 'BUNDLE-COMP-B', 'color' => 'White', 'size' => 'M', 'price' => 150000, 'stock' => 3, 'weight' => 300]);
        $this->bundle = Variant::create(['series_id' => $series->id, 'sku' => 'BUNDLE-FAMILY', 'color' => 'Mix', 'size' => 'Set', 'price' => 500000, 'stock' => 0, 'weight' => 1, 'fulfillment' => 'bundle']);
        BundleComponent::create(['bundle_variant_id' => $this->bundle->id, 'component_variant_id' => $this->first->id, 'quantity' => 2]);
        BundleComponent::create(['bundle_variant_id' => $this->bundle->id, 'component_variant_id' => $this->second->id, 'quantity' => 1]);
        $rate = ShippingRate::create(['courier' => 'JNE', 'service' => 'REG', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2–3 hari']);
        Setting::put('bank_accounts', [['bank' => 'Bank Uji', 'number' => '000', 'owner' => 'Dthree']]);
        Setting::put('checkout_enabled', true);
        $this->checkout = ['name' => 'Pelanggan', 'phone' => '081234567890', 'email' => 'test@example.com', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung', 'postal_code' => '46111', 'detail' => 'Alamat uji', 'payment_method' => 'transfer', 'shipping_rate_id' => $rate->id, 'terms' => '1'];
    }

    public function test_bundle_availability_weight_and_component_reservation_are_derived(): void
    {
        $service = app(CheckoutService::class);
        $quote = $service->quote([$this->bundle->id => 2], $this->checkout);
        $this->assertSame(2200, $quote['weight']);
        $order = $service->place([$this->bundle->id => 2], $this->checkout, hash('sha256', 'bundle'), null, $service->fingerprint($quote));

        $this->assertSame(0, $this->bundle->fresh()->reserved);
        $this->assertSame(4, $this->first->fresh()->reserved);
        $this->assertSame(2, $this->second->fresh()->reserved);
        $this->assertDatabaseHas('order_item_components', ['order_item_id' => $order->items()->first()->id, 'variant_id' => $this->first->id, 'quantity' => 4]);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $order->proofs()->create(['path' => 'payment-proofs/bundle.pdf']);
        $order->update(['payment_status' => 'review']);
        $this->actingAs($admin);
        app(OrderWorkflow::class)->act($order, 'approve');
        $this->assertSame(1, $this->first->fresh()->stock);
        $this->assertSame(1, $this->second->fresh()->stock);
    }

    public function test_bundle_cannot_exceed_the_scarcest_component_and_cancel_releases_components(): void
    {
        $service = app(CheckoutService::class);
        try {
            $service->quote([$this->bundle->id => 3], $this->checkout);
            $this->fail('Bundle melebihi stok komponen diterima.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('tidak mencukupi', $exception->getMessage());
        }

        $order = $service->place([$this->bundle->id => 1], $this->checkout, hash('sha256', 'bundle-cancel'));
        app(OrderWorkflow::class)->act($order, 'cancel', 'Pelanggan membatalkan pesanan.');
        $this->assertSame(0, $this->first->fresh()->reserved);
        $this->assertSame(0, $this->second->fresh()->reserved);
    }
}
