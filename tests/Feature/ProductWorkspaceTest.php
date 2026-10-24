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

    public function test_compare_at_price_is_managed_per_series_and_rendered_on_storefront(): void
    {
        $product = $this->product(['active' => true]);
        Variant::create(['series_id' => $product->id, 'sku' => 'SALE-SAGE-M', 'color' => 'Sage', 'size' => 'M', 'price' => 408000, 'stock' => 20, 'weight' => 500]);
        $payload = [
            'category_id' => $this->category->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'compare_at_price' => 480000,
            'active' => '1',
        ];

        $this->actingAs($this->admin)->post(route('admin.products.update', $product), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(480000, $product->fresh()->compare_at_price);
        $this->get(route('catalog'))->assertOk()->assertSee('Rp 408.000')->assertSee('Rp 480.000')->assertSee('sale-price');
        $this->get(route('product', $product->slug))->assertOk()->assertSee('Rp 408.000')->assertSee('Rp 480.000')->assertSee('display-compare-price');

        $this->actingAs($this->admin)->post(route('admin.products.update', $product), array_replace($payload, ['compare_at_price' => 400000]))
            ->assertSessionHasErrors('compare_at_price');
        $this->assertSame(480000, $product->fresh()->compare_at_price);
    }

    public function test_colors_sizes_and_photos_can_be_created_in_one_process(): void
    {
        Storage::fake('public');
        $product = $this->product();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($this->admin)->post(route('admin.products.variants-media.create', $product), [
            'sku_prefix' => 'NAIRA',
            'price' => 408000,
            'stock' => 5,
            'weight' => 500,
            'low_stock' => 2,
            'fulfillment' => 'ready_stock',
            'set_first_as_main' => '1',
            'groups' => [
                ['color' => 'Sage', 'sizes' => 'S, M', 'alt_text' => 'Naira warna sage', 'images' => [UploadedFile::fake()->createWithContent('sage-depan.png', $png), UploadedFile::fake()->createWithContent('sage-belakang.png', $png)]],
                ['color' => 'Mocca', 'sizes' => 'S, M', 'alt_text' => 'Naira warna mocca', 'images' => [UploadedFile::fake()->createWithContent('mocca-depan.png', $png)]],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(4, $product->variants()->count());
        $this->assertDatabaseHas('variants', ['series_id' => $product->id, 'sku' => 'NAIRA-SAGE-S', 'color' => 'Sage', 'size' => 'S', 'price' => 408000, 'stock' => 5]);
        $this->assertDatabaseHas('variants', ['series_id' => $product->id, 'sku' => 'NAIRA-MOCCA-M', 'color' => 'Mocca', 'size' => 'M']);
        $this->assertSame(4, StockMovement::whereIn('variant_id', $product->variants()->pluck('id'))->count());
        $this->assertSame(3, $product->media()->count());
        $this->assertSame(2, $product->media()->where('color', 'Sage')->count());
        $this->assertSame($product->media()->first()->image, $product->fresh()->image);

        $withoutPhotos = $this->product(['name' => 'Naira Tanpa Foto', 'slug' => 'naira-tanpa-foto']);
        $this->post(route('admin.products.variants-media.create', $withoutPhotos), [
            'sku_prefix' => 'NFP', 'price' => 300000, 'stock' => 0, 'weight' => 500, 'low_stock' => 2, 'fulfillment' => 'ready_stock',
            'groups' => [['color' => 'Black', 'sizes' => 'M, L', 'alt_text' => '']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $withoutPhotos->variants()->count());
        $this->assertSame(0, $withoutPhotos->media()->count());
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
            'color' => 'Sage',
            'variant_id' => $variant->id,
            'set_first_as_main' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, ProductMedia::where('series_id', $product->id)->count());
        $photos = ProductMedia::where('series_id', $product->id)->orderBy('id')->get();
        $this->assertSame('Sage', $photos[0]->color);
        $this->assertSame($variant->id, $photos[0]->variant_id);
        $this->assertSame($photos[0]->image, $product->fresh()->image);

        $this->post(route('admin.products.media.manage', $product), [
            'main_media_id' => $photos[1]->id,
            'media' => [
                ['id' => $photos[0]->id, 'alt_text' => 'Tampak depan', 'color' => 'Sage Muda', 'variant_id' => '', 'sort_order' => 2, 'active' => '1'],
                ['id' => $photos[1]->id, 'alt_text' => 'Tampak belakang', 'color' => 'Sage', 'variant_id' => $variant->id, 'sort_order' => 1, 'active' => '1'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_media', ['id' => $photos[0]->id, 'alt_text' => 'Tampak depan', 'color' => 'Sage Muda', 'variant_id' => null, 'sort_order' => 2]);
        $this->assertSame($photos[1]->image, $product->fresh()->image);
        $this->get(route('admin.products.edit', ['series' => $product, 'section' => 'media']))
            ->assertOk()->assertSee('Unggah semua foto')->assertSee('Atur galeri')->assertSee('Tampak depan');

        $this->post(route('admin.products.duplicate', $product))->assertRedirect()->assertSessionHasNoErrors();
        $copy = Series::where('name', 'Naira - Salinan')->firstOrFail();
        $this->assertFalse($copy->active);
        $this->assertSame(1, $copy->variants()->count());
        $this->assertSame(0, $copy->variants()->first()->stock);
        $this->assertNotSame($variant->sku, $copy->variants()->first()->sku);
        $this->assertSame(2, $copy->media()->count());

        $this->post(route('admin.products.media.manage', $product), [
            'main_media_id' => $photos[1]->id,
            'media' => [
                ['id' => $photos[0]->id, 'alt_text' => 'Tampak depan', 'sort_order' => 2, 'active' => '1', 'delete' => '1'],
                ['id' => $photos[1]->id, 'alt_text' => 'Tampak belakang', 'sort_order' => 1, 'active' => '1'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('product_media', ['id' => $photos[0]->id]);
        $this->assertSame(1, $product->media()->count());
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
