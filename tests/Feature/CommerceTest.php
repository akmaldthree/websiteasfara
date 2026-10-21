<?php

namespace Tests\Feature;

use App\Http\Controllers\ImportController;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use App\Services\CheckoutService;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    use RefreshDatabase;

    private Variant $variant;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $c = Category::create(['name' => 'Abaya', 'slug' => 'abaya']);
        $s = Series::create(['category_id' => $c->id, 'name' => 'Raffah', 'slug' => 'raffah', 'active' => true]);
        $this->variant = Variant::create(['series_id' => $s->id, 'sku' => 'raf-black-l', 'color' => 'Black', 'size' => 'L', 'price' => 300000, 'stock' => 3, 'weight' => 600]);
        $rate = ShippingRate::create(['courier' => 'JNE', 'service' => 'REG', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2–3 hari', 'cod' => true]);
        Setting::put('bank_accounts', [['bank' => 'Bank Uji', 'number' => '000', 'owner' => 'Test']]);
        Setting::put('checkout_enabled', true);
        Setting::put('cod_enabled', true);
        $this->input = ['name' => 'Pelanggan Uji', 'phone' => '081234567890', 'email' => 'test@example.com', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung', 'postal_code' => '46111', 'detail' => 'Alamat pengujian', 'payment_method' => 'transfer', 'shipping_rate_id' => $rate->id, 'terms' => '1'];
    }

    private function order(string $key = 'first', int $quantity = 1): Order
    {
        return app(CheckoutService::class)->place([$this->variant->id => $quantity], $this->input, hash('sha256', $key));
    }

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();

        return $u;
    }

    public function test_storefront_and_signed_tracking_render(): void
    {
        foreach (['/', '/koleksi', '/koleksi/raffah', '/keranjang', '/masuk', '/daftar', '/lacak-pesanan'] as $url) {
            $this->get($url)->assertOk();
        }
        $order = $this->order();
        $this->get($order->customerUrl())->assertOk()->assertSee($order->number);
        $this->get('/pesanan/'.$order->id)->assertForbidden();
    }

    public function test_server_prices_snapshot_and_idempotency(): void
    {
        $this->input['total'] = 1;
        $this->input['price'] = 1;
        $order = $this->order('key', 2);
        $again = $this->order('key', 2);
        $this->assertSame($order->id, $again->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(625000, $order->total);
        $this->assertEquals(2, $this->variant->fresh()->reserved);
        $this->variant->update(['price' => 999999]);
        $this->assertEquals(300000, $order->items->first()->price);
    }

    public function test_last_stock_cannot_be_reserved_again(): void
    {
        $this->variant->update(['stock' => 1]);
        $this->order();
        try {
            $this->order('second');
            $this->fail('Overselling accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->getMessage());
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(1, $this->variant->fresh()->reserved);
    }

    public function test_payment_approval_is_idempotent_and_commits_stock_once(): void
    {
        $o = $this->order();
        $o->proofs()->create(['path' => 'payment-proofs/test.pdf']);
        $o->update(['payment_status' => 'review']);
        $this->actingAs($this->user('finance'));
        app(OrderWorkflow::class)->act($o, 'approve');
        app(OrderWorkflow::class)->act($o, 'approve');
        $this->assertEquals(2, $this->variant->fresh()->stock);
        $this->assertEquals(0, $this->variant->fresh()->reserved);
        $this->assertEquals('paid', $o->fresh()->payment_status);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_expiry_releases_stock_but_preserves_pending_proof(): void
    {
        $o = $this->order();
        $o->update(['expires_at' => now()->subHour()]);
        $this->artisan('orders:expire')->assertSuccessful();
        $this->assertEquals('cancelled', $o->fresh()->status);
        $this->assertEquals(0, $this->variant->fresh()->reserved);
        $o = $this->order('proof');
        $o->update(['expires_at' => now()->subHour(), 'payment_status' => 'review']);
        $this->artisan('orders:expire')->assertSuccessful();
        $this->assertEquals('waiting', $o->fresh()->status);
        $this->assertEquals(1, $this->variant->fresh()->reserved);
    }

    public function test_upload_rejection_and_reupload_are_private_and_audited(): void
    {
        Storage::fake('local');
        $o = $this->order();
        $url = URL::signedRoute('orders.proof', ['order' => $o->id]);
        $this->post($url, ['proof' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf')])->assertRedirect();
        $this->assertEquals('review', $o->fresh()->payment_status);
        Storage::disk('local')->assertExists($o->proofs()->first()->path);
        $this->actingAs($this->user('finance'));
        app(OrderWorkflow::class)->act($o, 'reject', 'Nominal tidak sesuai');
        $this->assertEquals('rejected', $o->fresh()->payment_status);
        $this->post($url, ['proof' => UploadedFile::fake()->create('retry.pdf', 50, 'application/pdf')])->assertRedirect();
        $this->assertDatabaseCount('payment_proofs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.reject']);
    }

    public function test_cod_checks_destination_value_and_fulfillment(): void
    {
        $this->input['payment_method'] = 'cod';
        $this->input['city'] = 'Bandung';
        try {
            $this->order();
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertStringContainsString('wilayah', $e->getMessage());
        }
        $this->input['city'] = 'Tasikmalaya';
        $this->variant->update(['fulfillment' => 'pre_order']);
        try {
            $this->order();
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ready stock', $e->getMessage());
        }
        $this->variant->update(['fulfillment' => 'ready_stock']);
        Setting::put('cod_max', 100000);
        $this->expectException(ValidationException::class);
        $this->order();
    }

    public function test_shipping_discount_cap_and_voucher_quota(): void
    {
        Coupon::create(['code' => 'ONGKIR', 'type' => 'shipping', 'value' => 100000, 'maximum' => 10000, 'minimum' => 200000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'usage_limit' => 1]);
        $this->input['coupon'] = 'ongkir';
        $o = $this->order();
        $this->assertEquals(305000, $o->total);
        $this->expectException(ValidationException::class);
        $this->order('another');
    }

    public function test_role_boundaries_and_private_proofs(): void
    {
        $o = $this->order();
        $p = $o->proofs()->create(['path' => 'x.pdf']);
        $this->actingAs($this->user('warehouse'));
        $this->post(route('admin.order.action', $o), ['action' => 'approve'])->assertForbidden();
        $this->get(route('admin.proof', $p))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($this->user('finance'));
        $this->post(route('admin.inventory.adjust', $this->variant), ['delta' => 1, 'reason' => 'Test adjustment'])->assertForbidden();
        $this->actingAs($this->user('customer'))->get('/admin')->assertForbidden();
    }

    public function test_catalog_unique_manual_skus_and_sarimbit_structure(): void
    {
        $this->assertEquals('RAF-BLACK-L', $this->variant->sku);
        $admin = $this->user('admin');
        $this->actingAs($admin);
        $fields = ['series_id' => $this->variant->series_id, 'sku' => 'raf-black-l', 'color' => 'Olive', 'size' => 'M', 'price' => 100000, 'weight' => 500, 'low_stock' => 5, 'fulfillment' => 'ready_stock', 'active' => 1];
        $this->post(route('admin.store', 'variants'), $fields)->assertSessionHasErrors('sku');
        $fields['sku'] = 'NEW-SKU';
        $this->post(route('admin.store', 'variants'), $fields)->assertSessionHasNoErrors();
        $s = Series::create(['name' => 'Arcelia', 'slug' => 'arcelia', 'category_id' => 1, 'uses_product_type' => true]);
        $fields['series_id'] = $s->id;
        $fields['sku'] = 'ARC-1';
        $this->post(route('admin.store', 'variants'), $fields)->assertSessionHasErrors('product_type');
        $fields['product_type'] = 'Kemeja Anak';
        $this->post(route('admin.store', 'variants'), $fields)->assertSessionHasNoErrors();
    }

    public function test_all_admin_pages_render(): void
    {
        $this->actingAs($this->user('admin'));
        $o = $this->order();
        foreach (['/admin', '/admin/pesanan', '/admin/pesanan/'.$o->id, '/admin/inventori', '/admin/pelanggan', '/admin/layanan', '/admin/tim', '/admin/pengaturan', '/admin/audit', '/admin/import', '/admin/laporan'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (array_keys(config('admin_resources')) as $r) {
            $this->get('/admin/master/'.$r)->assertOk();
            $this->get('/admin/master/'.$r.'/baru')->assertOk();
        }
    }

    public function test_guest_checkout_http_flow_and_retry(): void
    {
        $this->post('/keranjang', ['variant_id' => $this->variant->id, 'quantity' => 1])->assertRedirect(route('cart'));
        $this->get('/checkout')->assertOk();
        $key = session('checkout_key');
        $this->post('/checkout/tinjau', $this->input)->assertOk()->assertSee('315.000');
        $this->post('/checkout', ['checkout_key' => $key])->assertRedirect();
        $this->post('/checkout', ['checkout_key' => $key])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_import_requires_preview_and_rejects_duplicates(): void
    {
        $this->actingAs($this->user('admin'));
        $this->post('/admin/import/commit')->assertSessionHasErrors();
        $csv = implode(',', ImportController::COLUMNS)."\nraffah,IMPORT-1,,Olive,M,250000,5,500,ready_stock,\n";
        $this->post('/admin/import/preview', ['csv' => UploadedFile::fake()->createWithContent('test.csv', $csv)])->assertOk()->assertSee('Konfirmasi import');
        $this->post('/admin/import/commit')->assertRedirect();
        $this->assertDatabaseHas('variants', ['sku' => 'IMPORT-1', 'stock' => 5]);
        $this->post('/admin/import/preview', ['csv' => UploadedFile::fake()->createWithContent('test.csv', $csv)])->assertOk()->assertSee('Perbaiki file');
    }

    public function test_stock_cannot_be_adjusted_below_reservations(): void
    {
        $this->order();
        $this->actingAs($this->user('warehouse'));
        $this->post(route('admin.inventory.adjust', $this->variant), ['delta' => -3, 'reason' => 'Perhitungan stok'])->assertSessionHasErrors('delta');
        $this->assertEquals(3, $this->variant->fresh()->stock);
    }

    public function test_customer_registration_does_not_accept_admin_role(): void
    {
        $this->post('/daftar', ['name' => 'Customer', 'email' => 'customer@example.com', 'phone' => '081234567899', 'password' => 'TestingPassword123', 'password_confirmation' => 'TestingPassword123', 'terms' => 1, 'role' => 'admin'])->assertRedirect('/akun');
        $this->assertEquals('customer', User::where('email', 'customer@example.com')->first()->role);
    }

    public function test_customer_cannot_remove_someone_elses_address(): void
    {
        $a = $this->user('customer');
        $b = $this->user('customer');
        $address = $a->addresses()->create(['name' => 'A', 'phone' => '08123456789', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung', 'postal_code' => '46111', 'detail' => 'Test']);
        $this->actingAs($b)->delete(route('address.delete', $address->id))->assertNotFound();
    }

    public function test_changed_price_requires_review_before_checkout_is_created(): void
    {
        $this->post('/keranjang', ['variant_id' => $this->variant->id, 'quantity' => 1]);
        $this->get('/checkout');
        $key = session('checkout_key');
        $this->post('/checkout/tinjau', $this->input)->assertOk();
        $this->variant->update(['price' => 400000]);

        $this->post('/checkout',['checkout_key' => $key])->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('orders',0);
        $this->assertEquals(0,$this->variant->fresh()->reserved);
    }
}
