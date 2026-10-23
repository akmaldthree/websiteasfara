<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MeasurementTemplate;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use App\Services\CheckoutService;
use App\Services\CustomProductService;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomProductTest extends TestCase
{
    use RefreshDatabase;

    private Variant $variant;

    private MeasurementTemplate $template;

    private array $checkout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $category = Category::create(['name' => 'Custom', 'slug' => 'custom']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Custom Dress', 'slug' => 'custom-dress', 'active' => true]);
        $this->template = MeasurementTemplate::factory()->create();
        $this->variant = Variant::create(['series_id' => $series->id, 'measurement_template_id' => $this->template->id, 'sku' => 'CUSTOM-DRESS', 'color' => 'Black', 'size' => 'Custom', 'price' => 300000, 'stock' => 5, 'weight' => 600, 'fulfillment' => 'custom_made']);
        $this->variant->refresh();
        $rate = ShippingRate::create(['courier' => 'JNE', 'service' => 'REG', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2–3 hari']);
        Setting::put('bank_accounts', [['bank' => 'Bank Uji', 'number' => '000', 'owner' => 'Dthree']]);
        Setting::put('checkout_enabled', true);
        $this->checkout = ['name' => 'Pelanggan', 'phone' => '081234567890', 'email' => 'test@example.com', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung', 'postal_code' => '46111', 'detail' => 'Alamat uji', 'payment_method' => 'transfer', 'shipping_rate_id' => $rate->id, 'terms' => '1'];
    }

    public function test_fixed_custom_measurement_is_validated_priced_and_snapshotted(): void
    {
        $service = app(CustomProductService::class);
        $customization = $service->fixedCustomization($this->variant, ['chest' => 96]);
        $checkout = app(CheckoutService::class);
        try {
            $quote = $checkout->quote([$this->variant->id => 1], $this->checkout, false, null, [$this->variant->id => $customization]);
        } catch (ValidationException $exception) {
            $this->fail('Quote gagal: '.$exception->getMessage());
        }
        $this->assertSame(50000, $quote['custom_cost']);
        $this->assertSame(365000, $quote['total']);

        try {
            $order = $checkout->place([$this->variant->id => 1], $this->checkout, hash('sha256', 'custom-fixed'), null, $checkout->fingerprint($quote), [$this->variant->id => $customization]);
        } catch (ValidationException $exception) {
            $this->fail('Place gagal: '.$exception->getMessage());
        }
        $item = $order->items()->firstOrFail();
        $this->assertSame(96, $item->attributes['custom']['measurements']['chest']);
        $this->assertSame('reviewing', $item->production_status);
        $this->assertDatabaseHas('production_milestones', ['order_item_id' => $item->id, 'status' => 'reviewing']);
    }

    public function test_invalid_custom_measurement_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(CustomProductService::class)->fixedCustomization($this->variant, ['chest' => 500]);
    }

    public function test_quote_acceptance_and_production_gate(): void
    {
        $this->template->update(['pricing_mode' => 'quote']);
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $custom = app(CustomProductService::class);
        $request = $custom->requestQuote($this->variant->fresh(), $user, ['chest' => 100], fake()->uuid());
        $this->actingAs($admin);
        $custom->quote($request, ['status' => 'quoted', 'surcharge' => 75000, 'lead_time' => '10 hari kerja', 'admin_notes' => 'Ukuran dapat diproduksi.', 'valid_days' => 7]);
        $snapshot = $custom->accept($request->fresh(), $user);
        $checkout = app(CheckoutService::class);
        $quote = $checkout->quote([$this->variant->id => 1], $this->checkout, false, $user, [$this->variant->id => $snapshot]);
        $order = $checkout->place([$this->variant->id => 1], $this->checkout, hash('sha256', 'custom-quote'), $user->id, $checkout->fingerprint($quote), [$this->variant->id => $snapshot]);
        $item = $order->items()->firstOrFail();
        $this->assertSame('ordered', $request->fresh()->status);

        $order->update(['status' => 'confirmed', 'fulfillment_status' => 'processing']);
        try {
            app(OrderWorkflow::class)->act($order, 'pack');
            $this->fail('Produk custom belum siap tetapi dapat dikemas.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('belum selesai', $exception->getMessage());
        }
        $custom->updateProduction($item, 'ready_to_ship', 'QC lulus.');
        $this->assertDatabaseHas('notification_deliveries', ['channel' => 'email', 'status' => 'pending']);
        app(OrderWorkflow::class)->act($order->fresh(), 'pack');
        $this->assertSame('packed', $order->fresh()->fulfillment_status);
    }

    public function test_custom_reference_is_stored_privately_and_downloaded_through_authorized_admin(): void
    {
        Storage::fake('local');
        $this->template->update(['pricing_mode' => 'quote']);
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($user)->post(route('custom.submit', $this->variant), [
            'request_key' => fake()->uuid(), 'measurements' => ['chest' => 100], 'measurement_approval' => '1',
            'reference' => UploadedFile::fake()->create('referensi.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('account'))->assertSessionHasNoErrors();

        $quote = $user->customQuotes()->firstOrFail();
        $this->assertNotNull($quote->reference_path);
        Storage::disk('local')->assertExists($quote->reference_path);
        $this->actingAs($admin)->get(route('admin.custom-quotes.reference', $quote))->assertOk();
    }

    public function test_failed_or_unauthenticated_custom_submission_does_not_leave_private_orphan_file(): void
    {
        Storage::fake('local');
        $payload = [
            'request_key' => fake()->uuid(), 'measurements' => ['chest' => 500], 'measurement_approval' => '1',
            'reference' => UploadedFile::fake()->create('referensi.pdf', 100, 'application/pdf'),
        ];

        $this->post(route('custom.submit', $this->variant), $payload)->assertSessionHasErrors();
        $this->assertSame([], Storage::disk('local')->allFiles('custom-references'));

        $this->template->update(['pricing_mode' => 'quote']);
        $payload['measurements'] = ['chest' => 100];
        $payload['request_key'] = fake()->uuid();
        $payload['reference'] = UploadedFile::fake()->create('quote.pdf', 100, 'application/pdf');
        $this->post(route('custom.submit', $this->variant->fresh()), $payload)->assertRedirect(route('login'));
        $this->assertSame([], Storage::disk('local')->allFiles('custom-references'));
    }
}
