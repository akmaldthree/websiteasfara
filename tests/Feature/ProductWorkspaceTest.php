<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProductMedia;
use App\Models\Series;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = Category::create(['name' => 'Abaya', 'slug' => 'abaya-workspace', 'active' => true]);
    }

    public function test_admin_can_create_and_find_a_product_in_the_workspace(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products'))->assertOk()->assertSee('Produk saya')->assertSee('Perlu diperbaiki')->assertSee('Pengaturan massal');
        $this->get(route('admin.products.create'))->assertOk()->assertSee('Buat produk baru');

        $response = $this->post(route('admin.products.store'), [
            'category_id' => $this->category->id, 'name' => 'Naira Workspace', 'slug' => 'naira-workspace',
            'description' => 'Produk yang dikelola dari satu halaman.', 'active' => '0',
        ]);
        $product = Series::where('slug', 'naira-workspace')->firstOrFail();
        $response->assertRedirect(route('admin.products.edit', $product));
        $this->get(route('admin.products', ['q' => 'Naira']))->assertOk()->assertSee('Naira Workspace');
    }

    public function test_variant_creation_and_batch_stock_adjustment_keep_an_audit_ledger(): void
    {
        $product = $this->product();
        $this->actingAs($this->admin)->post(route('admin.products.variants.add', $product), [
            'sku' => 'NAIRA-BLACK-M', 'product_type' => '', 'color' => 'Black', 'size' => 'M', 'price' => 350000,
            'stock' => 5, 'weight' => 500, 'low_stock' => 2, 'fulfillment' => 'ready_stock', 'active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $variant = Variant::where('sku', 'NAIRA-BLACK-M')->firstOrFail();
        $this->assertSame(5, $variant->stock);
        $this->assertDatabaseHas('stock_movements', ['variant_id' => $variant->id, 'stock_delta' => 5]);

        $this->post(route('admin.products.variants.save', $product), ['variants' => [[
            'id' => $variant->id, 'sku' => $variant->sku, 'product_type' => '', 'color' => 'Black', 'size' => 'M',
            'price' => 360000, 'weight' => 510, 'low_stock' => 3, 'fulfillment' => 'ready_stock', 'active' => '1',
            'stock_delta' => 4, 'stock_reason' => 'Stok masuk gudang',
        ]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(9, $variant->fresh()->stock);
        $this->assertSame(360000, $variant->fresh()->price);
        $this->assertDatabaseHas('stock_movements', ['variant_id' => $variant->id, 'stock_delta' => 4, 'reason' => 'Stok masuk gudang']);
    }

    public function test_generator_creates_combinations_atomically(): void
    {
        $product = $this->product(['uses_product_type' => true]);
        $this->actingAs($this->admin)->post(route('admin.products.variants.generate', $product), [
            'sku_prefix' => 'SAR', 'product_types' => 'Ayah, Ibu', 'colors' => 'Black, Sage', 'sizes' => 'M, L',
            'price' => 400000, 'stock' => 2, 'weight' => 600, 'low_stock' => 1, 'fulfillment' => 'ready_stock',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertCount(8, $product->variants);
        $this->assertDatabaseHas('variants', ['sku' => 'SAR-AYAH-BLACK-M', 'product_type' => 'Ayah']);
        $this->assertSame(8, StockMovement::count());
    }

    public function test_products_can_be_filtered_and_updated_in_bulk(): void
    {
        $first = $this->product();
        $second = Series::create(['category_id' => $this->category->id, 'name' => 'Produk Kedua', 'slug' => 'produk-kedua', 'description' => 'Lengkap', 'active' => false]);

        $this->actingAs($this->admin)->get(route('admin.products', ['status' => 'action']))
            ->assertOk()->assertSee('Naira')->assertSee('Produk Kedua');
        $this->post(route('admin.products.bulk'), ['product_ids' => [$first->id, $second->id], 'action' => 'activate'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($first->fresh()->active);
        $this->assertTrue($second->fresh()->active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'products.bulk_activate', 'subject' => 'series:'.$first->id]);
    }

    public function test_multiple_photos_can_be_uploaded_and_product_can_be_duplicated_safely(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $variant = Variant::create(['series_id' => $product->id, 'sku' => 'DUP-SAGE-M', 'color' => 'Sage', 'size' => 'M', 'price' => 300000, 'stock' => 4, 'weight' => 500]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($this->admin)->post(route('admin.products.media.upload', $product), [
            'images' => [UploadedFile::fake()->createWithContent('depan.png', $png), UploadedFile::fake()->createWithContent('belakang.png', $png)],
            'alt_text' => 'Produk tampak lengkap',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, ProductMedia::where('series_id', $product->id)->count());

        $this->post(route('admin.products.duplicate', $product))->assertRedirect()->assertSessionHasNoErrors();
        $copy = Series::where('name', 'Naira - Salinan')->firstOrFail();
        $this->assertFalse($copy->active);
        $this->assertSame(1, $copy->variants()->count());
        $this->assertSame(0, $copy->variants()->first()->stock);
        $this->assertNotSame($variant->sku, $copy->variants()->first()->sku);
        $this->assertSame(2, $copy->media()->count());
    }

    private function product(array $attributes = []): Series
    {
        return Series::create($attributes + [
            'category_id' => $this->category->id, 'name' => 'Naira', 'slug' => 'naira-product-workspace',
            'description' => 'Deskripsi produk.', 'active' => false,
        ]);
    }
}
