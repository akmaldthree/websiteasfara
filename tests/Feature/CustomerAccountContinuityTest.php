<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Series;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerAccountContinuityTest extends TestCase
{
    use RefreshDatabase;

    private Variant $first;

    private Variant $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $category = Category::create(['name' => 'Akun', 'slug' => 'akun']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Akun Series', 'slug' => 'akun-series', 'active' => true]);
        $this->first = Variant::create(['series_id' => $series->id, 'sku' => 'ACCOUNT-A', 'color' => 'Black', 'price' => 100000, 'stock' => 10, 'weight' => 300]);
        $this->second = Variant::create(['series_id' => $series->id, 'sku' => 'ACCOUNT-B', 'color' => 'White', 'price' => 120000, 'stock' => 10, 'weight' => 300]);
    }

    public function test_phone_login_merges_guest_and_saved_cart(): void
    {
        $user = User::factory()->create(['phone' => '081234567891', 'password' => Hash::make('TestingPassword123')]);
        $user->cartItems()->create(['variant_id' => $this->first->id, 'quantity' => 2]);

        $this->withSession(['cart' => [$this->second->id => 1]])
            ->post('/masuk', ['login' => '081234567891', 'password' => 'TestingPassword123'])
            ->assertRedirect('/akun');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(2, session('cart')[$this->first->id]);
        $this->assertSame(1, session('cart')[$this->second->id]);
        $this->assertDatabaseCount('user_cart_items', 2);
    }

    public function test_signed_guest_order_can_be_claimed_then_reordered(): void
    {
        $user = User::factory()->create(['phone' => '081234567892']);
        $order = Order::create([
            'number' => 'D3-GUEST-1', 'checkout_key' => hash('sha256', 'guest-order'), 'name' => 'Guest', 'phone' => $user->phone, 'email' => 'guest@example.com',
            'address' => ['province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung', 'postal_code' => '46111', 'detail' => 'Alamat'],
            'payment_method' => 'transfer', 'subtotal' => 100000, 'shipping_cost' => 15000, 'total' => 115000,
            'shipping' => ['courier' => 'JNE', 'service' => 'REG', 'estimate' => '2 hari'], 'expires_at' => now()->addDay(),
        ]);
        $order->items()->create(['variant_id' => $this->first->id, 'sku' => $this->first->sku, 'name' => 'Akun Series', 'attributes' => [], 'fulfillment' => 'ready_stock', 'weight' => 300, 'price' => 100000, 'quantity' => 1, 'total' => 100000]);

        $this->actingAs($user)->post(URL::signedRoute('orders.claim', ['order' => $order->id]))->assertRedirect();
        $this->assertSame($user->id, $order->fresh()->user_id);
        $this->assertNotNull($order->fresh()->claimed_at);

        $this->post(route('orders.reorder', $order))->assertRedirect(route('cart'));
        $this->assertSame(1, session('cart')[$this->first->id]);
        $this->assertDatabaseHas('user_cart_items', ['user_id' => $user->id, 'variant_id' => $this->first->id, 'quantity' => 1]);
    }

    public function test_customer_can_change_optional_marketing_consent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.communication'), ['marketing_email' => 1])->assertRedirect();
        $user->refresh();
        $this->assertTrue($user->marketing_email);
        $this->assertFalse($user->marketing_whatsapp);
        $this->assertNotNull($user->consent_updated_at);
    }
}
