<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProductQuestion;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use App\Notifications\ProductQuestionAnswered;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerEngagementTest extends TestCase
{
    use RefreshDatabase;

    private Variant $variant;

    private array $checkout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $category = Category::create(['name' => 'Review', 'slug' => 'review']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Dress Review', 'slug' => 'dress-review', 'active' => true]);
        $this->variant = Variant::create(['series_id' => $series->id, 'sku' => 'REVIEW-DRESS', 'color' => 'Black', 'size' => 'L', 'price' => 300000, 'stock' => 5, 'weight' => 500]);
        $rate = ShippingRate::create(['courier' => 'JNE', 'service' => 'REG', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2–3 hari']);
        Setting::put('bank_accounts', [['bank' => 'Bank Uji', 'number' => '000', 'owner' => 'Dthree']]);
        Setting::put('checkout_enabled', true);
        $this->checkout = ['name' => 'Pelanggan', 'phone' => '081234567890', 'email' => 'test@example.com', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya', 'district' => 'Cihideung', 'postal_code' => '46111', 'detail' => 'Alamat uji', 'payment_method' => 'transfer', 'shipping_rate_id' => $rate->id, 'terms' => '1'];
    }

    public function test_customer_can_toggle_one_wishlist_entry_per_product_series(): void
    {
        $user = User::factory()->create();
        $series = $this->variant->series;
        Variant::create(['series_id' => $series->id, 'sku' => 'REVIEW-DRESS-M', 'color' => 'Sage', 'size' => 'M', 'price' => 300000, 'stock' => 5, 'weight' => 500]);

        $productPage = $this->actingAs($user)->get(route('product', $series->slug))->assertOk();
        $this->assertSame(1, substr_count($productPage->getContent(), route('wishlist.toggle', $series)));
        $productPage->assertSee('Simpan ke wishlist')->assertDontSee('Simpan varian ke wishlist');

        $this->post(route('wishlist.toggle', $series))->assertRedirect();
        $this->assertDatabaseHas('wishlist_items', ['user_id' => $user->id, 'series_id' => $series->id]);
        $this->post(route('wishlist.toggle', $series))->assertRedirect();
        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $user->id, 'series_id' => $series->id]);
    }

    public function test_verified_review_requires_received_purchase_and_admin_moderation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $order = app(CheckoutService::class)->place([$this->variant->id => 1], $this->checkout, hash('sha256', 'review'), $user->id);
        $order->update(['fulfillment_status' => 'received']);
        $item = $order->items()->firstOrFail();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nSIAAAAASUVORK5CYII=');
        $payload = ['rating' => 5, 'body' => 'Produk ini sangat nyaman dipakai.', 'photos' => [UploadedFile::fake()->createWithContent('review.png', $png)]];

        $this->actingAs($other)->post(route('review.store', $item), $payload)->assertForbidden();
        $this->actingAs($user)->post(route('review.store', $item), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $review = $user->reviews()->firstOrFail();
        $this->assertSame('pending', $review->status);
        Storage::disk('public')->assertExists($review->photos[0]);
        $this->get(route('product', 'dress-review'))->assertDontSee('Produk ini sangat nyaman');

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->post(route('admin.reviews.update', $review), ['status' => 'approved'])->assertRedirect();
        $this->get(route('product', 'dress-review'))->assertSee('Produk ini sangat nyaman')->assertSee('Terverifikasi');
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.moderated']);
    }

    public function test_product_questions_are_moderated_and_helpful_votes_toggle(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($customer)->post(route('product.question', $this->variant->series), ['question' => 'Apakah bahan produk ini cocok untuk cuaca panas?'])->assertRedirect()->assertSessionHasNoErrors();
        $question = ProductQuestion::firstOrFail();
        $this->get(route('product', 'dress-review'))->assertDontSee('Apakah bahan produk ini');
        Notification::fake();
        $this->actingAs($admin)->post(route('admin.questions.update', $question), ['status' => 'answered', 'answer' => 'Bahannya ringan dan nyaman untuk cuaca panas.'])->assertRedirect()->assertSessionHasNoErrors();
        Notification::assertSentTo($customer, ProductQuestionAnswered::class);
        $this->get(route('product', 'dress-review'))->assertSee('Apakah bahan produk ini')->assertSee('Bahannya ringan');

        $reviewOwner = User::factory()->create();
        $order = app(CheckoutService::class)->place([$this->variant->id => 1], $this->checkout, hash('sha256', 'helpful'), $reviewOwner->id);
        $order->update(['fulfillment_status' => 'received']);
        $this->actingAs($reviewOwner)->post(route('review.store', $order->items()->first()), ['rating' => 5, 'body' => 'Ulasan produk yang sangat membantu pembeli lain.'])->assertRedirect();
        $review = $reviewOwner->reviews()->firstOrFail();
        $review->update(['status' => 'approved']);

        $this->actingAs($customer)->post(route('review.helpful', $review))->assertRedirect();
        $this->assertDatabaseHas('review_helpful_votes', ['review_id' => $review->id, 'user_id' => $customer->id]);
        $this->post(route('review.helpful', $review))->assertRedirect();
        $this->assertDatabaseMissing('review_helpful_votes', ['review_id' => $review->id, 'user_id' => $customer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product_question.moderated']);
    }
}
