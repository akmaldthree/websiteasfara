<?php

namespace Tests\Feature;

use App\Models\BusinessAccount;
use App\Models\BusinessOrderRule;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\PriceGroup;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use App\Models\VariantPrice;
use App\Services\CheckoutService;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessCommerceTest extends TestCase
{
    use RefreshDatabase;

    private Variant $variant;

    private ShippingRate $rate;

    private PriceGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $category = Category::create(['name' => 'Bisnis', 'slug' => 'bisnis']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Mitra', 'slug' => 'mitra', 'active' => true]);
        $this->variant = Variant::create([
            'series_id' => $series->id, 'sku' => 'B2B-MITRA-L', 'color' => 'Black', 'size' => 'L',
            'price' => 300000, 'stock' => 30, 'weight' => 500,
        ]);
        $this->rate = ShippingRate::create([
            'courier' => 'JNE', 'service' => 'REG', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya',
            'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2–3 hari',
        ]);
        $this->group = PriceGroup::where('slug', 'reseller')->firstOrFail();
        $this->group->update(['minimum_order_quantity' => 3, 'minimum_order_amount' => 0]);
        VariantPrice::create([
            'variant_id' => $this->variant->id, 'price_group_id' => $this->group->id,
            'minimum_quantity' => 3, 'price' => 250000,
        ]);
        Setting::put('bank_accounts', [['bank' => 'Bank Uji', 'number' => '000', 'owner' => 'Dthree']]);
        Setting::put('checkout_enabled', true);
    }

    public function test_customer_can_apply_and_admin_can_assign_a_matching_price_group(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('business.apply'), [
            'business_type' => 'reseller', 'business_name' => 'Toko Mitra',
            'sales_channel' => 'Instagram', 'notes' => 'Berjualan dari Tasikmalaya.',
            'legal_document' => UploadedFile::fake()->create('nib.pdf', 100, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $account = $customer->businessAccount()->firstOrFail();
        $this->assertSame('pending', $account->status);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->post(route('admin.business.review', $account), [
            'status' => 'approved', 'price_group_id' => $this->group->id,
            'sales_user_id' => $admin->id, 'credit_terms_days' => 30,
            'credit_limit' => 5000000, 'commission_percent' => 2.5,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('approved', $account->fresh()->status);
        $this->assertSame($this->group->id, $account->fresh()->price_group_id);
        $this->assertSame(30, $account->fresh()->credit_terms_days);
        $this->assertSame(5000000, $account->fresh()->credit_limit);
        $this->assertSame($admin->id, $account->fresh()->sales_user_id);
        Storage::disk('local')->assertExists($account->fresh()->legal_document_path);
        $this->get(route('admin.business.document', $account))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'business.application_reviewed']);
    }

    public function test_admin_cannot_assign_a_group_for_another_business_type(): void
    {
        $customer = User::factory()->create();
        $account = BusinessAccount::factory()->create(['user_id' => $customer->id, 'business_type' => 'reseller']);
        $wholesale = PriceGroup::where('slug', 'grosir')->firstOrFail();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->post(route('admin.business.review', $account), [
            'status' => 'approved', 'price_group_id' => $wholesale->id,
            'credit_terms_days' => 0, 'credit_limit' => 0, 'commission_percent' => 0,
        ])->assertSessionHasErrors('price_group_id');

        $this->assertSame('pending', $account->fresh()->status);
    }

    public function test_business_tier_must_not_get_more_expensive_at_a_higher_quantity(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->post(route('admin.store', 'variant-prices'), [
            'variant_id' => $this->variant->id,
            'price_group_id' => $this->group->id,
            'minimum_quantity' => 5,
            'price' => 260000,
            'active' => 1,
        ])->assertSessionHasErrors('price');

        $this->assertDatabaseMissing('variant_prices', ['minimum_quantity' => 5, 'price' => 260000]);
    }

    public function test_private_tier_price_is_not_exposed_to_guests_and_is_snapshotted_for_approved_customer(): void
    {
        $this->get(route('product', 'mitra'))->assertOk()->assertDontSee('Harga privat')->assertDontSee('250.000');

        $customer = User::factory()->create();
        BusinessAccount::factory()->approved($this->group)->create(['user_id' => $customer->id, 'business_type' => 'reseller']);
        $this->actingAs($customer)->get(route('product', 'mitra'))->assertOk()->assertSee('Harga privat')->assertSee('250.000');

        $service = app(CheckoutService::class);
        $quote = $service->quote([$this->variant->id => 3], $this->checkoutInput(), false, $customer);
        $this->assertSame(750000, $quote['subtotal']);
        $this->assertSame(250000, $quote['lines'][0]['unit_price']);

        $order = $service->place([$this->variant->id => 3], $this->checkoutInput(), hash('sha256', 'business-order'), $customer->id, $service->fingerprint($quote));
        $this->assertSame($this->group->id, $order->price_group_id);
        $this->assertSame('Reseller', $order->business_snapshot['name']);
        $this->assertSame(250000, $order->items()->first()->price);
        $this->assertSame('Reseller', $order->items()->first()->attributes['price_group']);
    }

    public function test_group_minimum_and_changed_tier_are_revalidated_before_order_creation(): void
    {
        $customer = User::factory()->create();
        BusinessAccount::factory()->approved($this->group)->create(['user_id' => $customer->id, 'business_type' => 'reseller']);
        $service = app(CheckoutService::class);

        try {
            $service->quote([$this->variant->id => 2], $this->checkoutInput(), false, $customer);
            $this->fail('Minimum grup harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Minimum grup Reseller', $exception->getMessage());
        }

        $quote = $service->quote([$this->variant->id => 3], $this->checkoutInput(), false, $customer);
        VariantPrice::where('price_group_id', $this->group->id)->update(['price' => 240000]);

        $this->expectException(ValidationException::class);
        $service->place([$this->variant->id => 3], $this->checkoutInput(), hash('sha256', 'changed-tier'), $customer->id, $service->fingerprint($quote));
    }

    public function test_group_discount_targeted_minimum_and_expiry_are_enforced(): void
    {
        $customer = User::factory()->create();
        $account = BusinessAccount::factory()->approved($this->group)->create(['user_id' => $customer->id, 'business_type' => 'reseller']);
        $this->group->update(['minimum_order_quantity' => 1, 'discount_percent' => 10]);
        VariantPrice::where('price_group_id', $this->group->id)->delete();
        BusinessOrderRule::create(['price_group_id' => $this->group->id, 'target_type' => 'variant', 'target_id' => $this->variant->id, 'minimum_quantity' => 2]);
        $service = app(CheckoutService::class);
        $quote = $service->quote([$this->variant->id => 2], $this->checkoutInput(), false, $customer);
        $this->assertSame(270000, $quote['lines'][0]['unit_price']);

        $this->expectException(ValidationException::class);
        $service->quote([$this->variant->id => 1], $this->checkoutInput(), false, $customer);
    }

    public function test_expired_business_account_loses_private_price_and_dropship_sender_is_snapshotted(): void
    {
        $customer = User::factory()->create();
        $account = BusinessAccount::factory()->approved($this->group)->create(['user_id' => $customer->id, 'business_type' => 'reseller', 'expires_at' => now()->subDay()]);
        $service = app(CheckoutService::class);
        $quote = $service->quote([$this->variant->id => 1], $this->checkoutInput(), false, $customer);
        $this->assertNull($quote['price_group']);
        $this->assertSame(300000, $quote['lines'][0]['unit_price']);

        $dropship = PriceGroup::where('business_type', 'dropshipper')->firstOrFail();
        $account->update(['business_type' => 'dropshipper', 'price_group_id' => $dropship->id, 'expires_at' => now()->addMonth()]);
        $input = $this->checkoutInput() + ['sender_name' => 'Toko Pelanggan', 'sender_phone' => '081234567899'];
        $quote = $service->quote([$this->variant->id => 1], $input, false, $customer);
        $order = $service->place([$this->variant->id => 1], $input, hash('sha256', 'dropship-order'), $customer->id, $service->fingerprint($quote));
        $this->assertSame('Toko Pelanggan', $order->dropship_snapshot['name']);
        $this->assertSame('081234567899', $order->dropship_snapshot['phone']);
    }

    public function test_credit_terms_enforce_available_limit_and_completed_order_accrues_sales_commission(): void
    {
        $customer = User::factory()->create();
        $sales = User::factory()->create();
        $sales->forceFill(['role' => 'admin'])->save();
        BusinessAccount::factory()->approved($this->group)->create([
            'user_id' => $customer->id, 'business_type' => 'reseller', 'sales_user_id' => $sales->id,
            'credit_terms_days' => 30, 'credit_limit' => 1000000, 'commission_percent' => 2.5,
        ]);
        $input = array_replace($this->checkoutInput(), ['payment_method' => 'credit']);
        $service = app(CheckoutService::class);
        $quote = $service->quote([$this->variant->id => 3], $input, false, $customer);
        $order = $service->place([$this->variant->id => 3], $input, hash('sha256', 'business-credit'), $customer->id, $service->fingerprint($quote));

        $this->assertNotNull($order->credit_due_at);
        $this->assertSame(30, $order->business_snapshot['credit_terms_days']);
        try {
            $service->quote([$this->variant->id => 3], $input, false, $customer);
            $this->fail('Limit kredit yang sudah terpakai harus diperhitungkan.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Limit kredit', $exception->getMessage());
        }

        $this->actingAs($sales);
        $workflow = app(OrderWorkflow::class);
        $workflow->act($order, 'credit_confirm');
        $workflow->act($order->fresh(), 'pack');
        $workflow->act($order->fresh(), 'ship', tracking: 'JNE-CREDIT-1');
        $workflow->act($order->fresh(), 'receive');
        $workflow->act($order->fresh(), 'credit_paid');

        $this->assertSame('completed', $order->fresh()->status);
        $entry = CommissionEntry::where('order_id', $order->id)->where('type', 'earned')->firstOrFail();
        $this->assertSame(18750, $entry->amount);
        $this->assertSame($sales->id, $entry->sales_user_id);

        $order->update(['payment_status' => 'refunded']);
        $nextQuote = $service->quote([$this->variant->id => 3], $input, false, $customer);
        $this->assertSame(775000, $nextQuote['total']);
    }

    public function test_approved_business_can_build_cart_from_bulk_sku_list(): void
    {
        $customer = User::factory()->create();
        BusinessAccount::factory()->approved($this->group)->create(['user_id' => $customer->id, 'business_type' => 'reseller']);

        $this->actingAs($customer)->post(route('business.bulk-order'), [
            'items' => "b2b-mitra-l | 2\nB2B-MITRA-L | 1",
        ])->assertRedirect(route('cart'))->assertSessionHasNoErrors();

        $this->assertSame(3, session('cart')[$this->variant->id]);
        $this->assertDatabaseHas('user_cart_items', ['user_id' => $customer->id, 'variant_id' => $this->variant->id, 'quantity' => 3]);
    }

    /** @return array<string, mixed> */
    private function checkoutInput(): array
    {
        return [
            'name' => 'Mitra Dthree', 'phone' => '081234567890', 'email' => 'mitra@example.com',
            'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung',
            'postal_code' => '46111', 'detail' => 'Alamat pengujian', 'payment_method' => 'transfer',
            'shipping_rate_id' => $this->rate->id, 'terms' => '1',
        ];
    }
}
