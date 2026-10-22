<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NotificationDelivery;
use App\Models\Order;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use App\Services\AfterSalesService;
use App\Services\CheckoutService;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{order: Order, variant: Variant} */
    private function paidOrder(string $key = 'operations'): array
    {
        $this->seed();
        $category = Category::create(['name' => 'Abaya', 'slug' => 'ops-abaya']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Raffah', 'slug' => 'ops-raffah', 'active' => true]);
        $variant = Variant::create([
            'series_id' => $series->id, 'sku' => 'OPS-RAF-L', 'color' => 'Black', 'size' => 'L',
            'price' => 300000, 'stock' => 3, 'weight' => 600,
        ]);
        $rate = ShippingRate::create([
            'courier' => 'JNE', 'service' => 'REG', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya',
            'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2–3 hari', 'cod' => true,
        ]);
        Setting::put('bank_accounts', [['bank' => 'Bank Uji', 'number' => '000', 'owner' => 'Test']]);
        Setting::put('checkout_enabled', true);
        $input = [
            'name' => 'Pelanggan Uji', 'phone' => '081234567890', 'email' => 'test@example.com',
            'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung',
            'postal_code' => '46111', 'detail' => 'Alamat pengujian', 'payment_method' => 'transfer',
            'shipping_rate_id' => $rate->id, 'terms' => '1',
        ];
        $order = app(CheckoutService::class)->place([$variant->id => 1], $input, hash('sha256', $key));
        $order->proofs()->create(['path' => 'payment-proofs/test.pdf']);
        $order->update(['payment_status' => 'review']);
        $finance = User::factory()->create();
        $finance->forceFill(['role' => 'finance'])->save();
        $this->actingAs($finance);
        app(OrderWorkflow::class)->act($order, 'approve');

        return ['order' => $order->fresh(), 'variant' => $variant->fresh()];
    }

    public function test_transaction_email_outbox_is_deduplicated_and_sent_by_the_command(): void
    {
        $order = $this->paidOrder()['order'];

        $this->assertDatabaseCount('notification_deliveries', 2);
        $this->assertSame(2, NotificationDelivery::where('status', 'pending')->count());

        config(['commerce.email_notifications' => true, 'mail.default' => 'smtp']);
        Mail::fake();
        $this->artisan('notifications:send')->assertSuccessful();

        $this->assertSame(2, NotificationDelivery::where('status', 'sent')->count());
        app(OrderWorkflow::class)->act($order, 'approve');
        $this->assertDatabaseCount('notification_deliveries', 2);
    }

    public function test_return_request_requires_received_order_and_private_evidence(): void
    {
        Storage::fake('local');
        $order = $this->paidOrder()['order'];
        $item = $order->items()->first();

        $url = URL::signedRoute('orders.case', ['order' => $order->id]);
        $response = $this->post($url, [
            'request_key' => (string) Str::uuid(), 'type' => 'return', 'reason_code' => 'Barang rusak',
            'reason' => 'Barang sobek ketika paket dibuka.', 'items' => [$item->id => 1],
        ]);

        $response->assertSessionHasErrors('service');
        $this->assertDatabaseCount('service_cases', 0);
    }

    public function test_approved_return_restock_is_recorded_once_after_warehouse_inspection(): void
    {
        $fixture = $this->paidOrder('return');
        $order = $fixture['order'];
        $variant = $fixture['variant'];
        $order->update(['fulfillment_status' => 'received', 'received_at' => now(), 'status' => 'completed', 'completed_at' => now()]);
        $item = $order->items()->first();
        $service = app(AfterSalesService::class);
        $case = $service->open($order, [
            'request_key' => (string) Str::uuid(), 'type' => 'return', 'reason_code' => 'Barang rusak',
            'reason' => 'Barang sobek ketika paket dibuka.', 'items' => [$item->id => 1],
        ], 'case-evidence/test.jpg');
        $service->update($case, ['status' => 'approved', 'resolution' => 'Retur disetujui setelah bukti diperiksa.']);

        $warehouse = User::factory()->create();
        $warehouse->forceFill(['role' => 'warehouse'])->save();
        $this->actingAs($warehouse);
        $line = $case->items()->first();
        $service->receive($line, 1, 'Barang utuh dan layak dijual kembali.');
        $service->receive($line, 1, 'Barang utuh dan layak dijual kembali.');

        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertSame(1, $line->fresh()->restocked_quantity);
        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_full_refund_before_shipping_cancels_order_and_restores_stock_once(): void
    {
        $fixture = $this->paidOrder('refund');
        $order = $fixture['order'];
        $variant = $fixture['variant'];
        $requestKey = (string) Str::uuid();
        $data = [
            'request_key' => $requestKey, 'amount' => $order->total, 'method' => 'Transfer bank',
            'reference' => 'RF-001', 'reason' => 'Pembatalan sebelum pengiriman', 'paid_at' => now()->toDateString(),
        ];
        $service = app(AfterSalesService::class);
        $refund = $service->refund($order, $data, 'refund-proofs/test.pdf');
        $again = $service->refund($order, $data, 'refund-proofs/duplicate.pdf');

        $this->assertSame($refund->id, $again->id);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('released', $order->fresh()->inventory_state);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_refund_and_notification_actions_enforce_role_permissions(): void
    {
        $order = $this->paidOrder('roles')['order'];
        $warehouse = User::factory()->create();
        $warehouse->forceFill(['role' => 'warehouse'])->save();
        $this->actingAs($warehouse)->post(route('admin.refund', $order), [])->assertForbidden();

        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.notifications'))->assertForbidden();
    }

    public function test_customer_can_choose_one_default_address_without_accessing_another_customer_address(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $payload = [
            'name' => 'Penerima', 'phone' => '081234567890', 'province' => 'Jawa Barat',
            'city' => 'Tasikmalaya', 'district' => 'Cihideung', 'postal_code' => '46111',
            'detail' => 'Alamat pertama',
        ];
        $this->actingAs($customer)->post(route('address.store'), $payload)->assertRedirect();
        $first = $customer->addresses()->first();
        $this->assertTrue($first->is_default);
        $this->post(route('address.store'), array_merge($payload, ['detail' => 'Alamat kedua']))->assertRedirect();
        $second = $customer->addresses()->where('detail', 'Alamat kedua')->first();

        $this->post(route('address.default', $second->id))->assertRedirect();
        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);

        $foreign = $other->addresses()->create($payload);
        $this->post(route('address.default', $foreign->id))->assertNotFound();
    }

    public function test_admin_can_disable_staff_but_cannot_disable_the_current_or_last_admin(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $staff = User::factory()->create();
        $staff->forceFill(['role' => 'cs'])->save();
        $this->actingAs($admin);

        $this->post(route('admin.team.update', $staff), ['role' => 'cs'])->assertRedirect();
        $this->assertFalse($staff->fresh()->active);

        $this->post(route('admin.team.update', $admin), ['role' => 'admin'])->assertSessionHasErrors('active');
        $this->assertTrue($admin->fresh()->active);
    }

    public function test_new_customer_and_admin_operation_pages_render(): void
    {
        $order = $this->paidOrder('render')['order'];
        $case = app(AfterSalesService::class)->open($order, [
            'request_key' => (string) Str::uuid(), 'type' => 'complaint',
            'reason' => 'Saya membutuhkan bantuan untuk pesanan ini.',
        ], null);
        $this->get(URL::signedRoute('orders.show', ['order' => $order->id]))->assertOk()->assertSee($case->number);
        $this->get(URL::signedRoute('orders.invoice', ['order' => $order->id]))->assertOk()->assertSee($order->number);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin);
        foreach ([
            route('admin.cases'), route('admin.case', $case), route('admin.notifications'),
            route('admin.service.policy'), route('admin.document', [$order, 'invoice']),
            route('admin.document', [$order, 'picking']), route('admin.reports'), route('admin.team'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
