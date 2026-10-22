<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LoyaltyTransaction;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use App\Services\AfterSalesService;
use App\Services\CheckoutService;
use App\Services\LoyaltyService;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoyaltyProgramTest extends TestCase
{
    use RefreshDatabase;

    private Variant $variant;

    private User $user;

    private array $checkout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        foreach (['loyalty_enabled' => true, 'loyalty_spend_per_point' => 10000, 'loyalty_point_value' => 100, 'loyalty_min_redeem' => 100] as $key => $value) {
            Setting::put($key, $value);
        }
        $category = Category::create(['name' => 'Loyalty', 'slug' => 'loyalty']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Loyalty Dress', 'slug' => 'loyalty-dress', 'active' => true]);
        $this->variant = Variant::create(['series_id' => $series->id, 'sku' => 'LOYALTY-DRESS', 'color' => 'Black', 'size' => 'L', 'price' => 300000, 'stock' => 10, 'weight' => 500]);
        $rate = ShippingRate::create(['courier' => 'JNE', 'service' => 'REG', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2–3 hari']);
        Setting::put('bank_accounts', [['bank' => 'Bank Uji', 'number' => '000', 'owner' => 'Dthree']]);
        Setting::put('checkout_enabled', true);
        $this->user = User::factory()->create();
        LoyaltyTransaction::create(['user_id' => $this->user->id, 'type' => 'earn', 'points' => 1000, 'reference' => 'opening', 'expires_at' => now()->addYear()]);
        $this->checkout = ['name' => 'Pelanggan', 'phone' => '081234567890', 'email' => 'test@example.com', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung', 'postal_code' => '46111', 'detail' => 'Alamat uji', 'payment_method' => 'transfer', 'shipping_rate_id' => $rate->id, 'loyalty_points' => 200, 'terms' => '1'];
    }

    public function test_points_are_redeemed_atomically_and_restored_on_cancellation(): void
    {
        $service = app(CheckoutService::class);
        $quote = $service->quote([$this->variant->id => 1], $this->checkout, false, $this->user);
        $this->assertSame(20000, $quote['loyalty_discount']);
        $this->assertSame(295000, $quote['total']);
        $order = $service->place([$this->variant->id => 1], $this->checkout, hash('sha256', 'loyalty-redeem'), $this->user->id, $service->fingerprint($quote));
        $this->assertSame(800, app(LoyaltyService::class)->balance($this->user));

        app(OrderWorkflow::class)->act($order, 'cancel', 'Pelanggan membatalkan pesanan.');
        $this->assertSame(1000, app(LoyaltyService::class)->balance($this->user));
        $this->assertDatabaseHas('loyalty_transactions', ['reference' => 'order:'.$order->id.':cancel-restore', 'points' => 200]);
    }

    public function test_completed_order_earns_expiring_points_once(): void
    {
        unset($this->checkout['loyalty_points']);
        $order = app(CheckoutService::class)->place([$this->variant->id => 1], $this->checkout, hash('sha256', 'loyalty-earn'), $this->user->id);
        $order->update(['status' => 'confirmed', 'payment_status' => 'paid', 'fulfillment_status' => 'shipped', 'paid_at' => now()]);
        app(OrderWorkflow::class)->act($order, 'receive');
        app(LoyaltyService::class)->earn($order->fresh());

        $earned = LoyaltyTransaction::where('reference', 'order:'.$order->id.':earn')->firstOrFail();
        $this->assertSame(30, $earned->points);
        $this->assertNotNull($earned->expires_at);
        $this->assertDatabaseCount('loyalty_transactions', 2);
    }

    public function test_expired_points_are_not_available(): void
    {
        LoyaltyTransaction::where('reference', 'opening')->update(['expires_at' => now()->subDay()]);
        $this->assertSame(0, app(LoyaltyService::class)->balance($this->user));
    }

    public function test_partial_refund_reverses_earned_points_and_restores_redeemed_points_proportionally(): void
    {
        $service = app(CheckoutService::class);
        $quote = $service->quote([$this->variant->id => 1], $this->checkout, false, $this->user);
        $order = $service->place([$this->variant->id => 1], $this->checkout, hash('sha256', 'loyalty-refund'), $this->user->id, $service->fingerprint($quote));
        $order->update(['status' => 'completed', 'payment_status' => 'paid', 'fulfillment_status' => 'received', 'paid_at' => now(), 'received_at' => now(), 'completed_at' => now()]);
        app(LoyaltyService::class)->earn($order->fresh());
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin);
        $amount = intdiv($order->total, 2);
        app(AfterSalesService::class)->refund($order, ['request_key' => (string) Str::uuid(), 'amount' => $amount, 'method' => 'transfer', 'reference' => 'RF-LOYALTY', 'reason' => 'Refund sebagian untuk pengujian.', 'paid_at' => now()->toDateString()], 'refund-proofs/test.pdf');

        $this->assertDatabaseHas('loyalty_transactions', ['order_id' => $order->id, 'type' => 'refund_reversal', 'points' => -14]);
        $this->assertDatabaseHas('loyalty_transactions', ['order_id' => $order->id, 'type' => 'redemption_restore', 'points' => 100]);
    }
}
