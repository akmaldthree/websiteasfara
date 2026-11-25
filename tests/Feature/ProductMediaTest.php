<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProductMedia;
use App\Models\Series;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_uses_managed_gallery_seo_and_structured_data(): void
    {
        $this->seed();
        $category = Category::create(['name' => 'Dress', 'slug' => 'dress', 'active' => true]);
        $series = Series::create([
            'category_id' => $category->id, 'name' => 'Naira', 'slug' => 'naira', 'description' => 'Deskripsi produk.',
            'seo_title' => 'Naira Premium Asfara', 'seo_description' => 'Meta Naira dari dashboard.', 'active' => true,
        ]);
        $variant = Variant::create(['series_id' => $series->id, 'sku' => 'NAIRA-BLACK-M', 'color' => 'Black', 'size' => 'M', 'price' => 350000, 'stock' => 5, 'weight' => 500]);
        ProductMedia::create(['series_id' => $series->id, 'variant_id' => $variant->id, 'color' => 'Black', 'image' => 'product-gallery/naira.jpg', 'alt_text' => 'Naira warna hitam tampak depan', 'focal_x' => 41, 'focal_y' => 22, 'active' => true]);

        $this->get(route('product', $series->slug))
            ->assertOk()
            ->assertSee('Naira Premium Asfara')
            ->assertSee('Meta Naira dari dashboard.')
            ->assertSee('Naira warna hitam tampak depan')
            ->assertSee('object-position:41% 22%', false)
            ->assertSee('data-focal-x="41" data-focal-y="22"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('https://schema.org/InStock', false);
    }

    public function test_admin_can_upload_variant_gallery_image_and_variant_must_match_series(): void
    {
        Storage::fake('public');
        $this->seed();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $category = Category::create(['name' => 'Koleksi', 'slug' => 'koleksi-uji', 'active' => true]);
        $first = Series::create(['category_id' => $category->id, 'name' => 'Pertama', 'slug' => 'pertama', 'active' => true]);
        $second = Series::create(['category_id' => $category->id, 'name' => 'Kedua', 'slug' => 'kedua', 'active' => true]);
        $variant = Variant::create(['series_id' => $second->id, 'sku' => 'KEDUA-BEIGE-M', 'color' => 'Beige', 'size' => 'M', 'price' => 250000, 'stock' => 3, 'weight' => 400]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $payload = ['series_id' => $first->id, 'variant_id' => $variant->id, 'color' => '', 'alt_text' => 'Foto beige', 'sort_order' => 0, 'active' => '1', 'image' => UploadedFile::fake()->createWithContent('beige.png', $png)];

        $this->actingAs($admin)->post(route('admin.store', 'product-media'), $payload)
            ->assertSessionHasErrors('variant_id');

        $payload['series_id'] = $second->id;
        $this->actingAs($admin)->post(route('admin.store', 'product-media'), $payload)
            ->assertRedirect(route('admin.resources', 'product-media'))
            ->assertSessionHasNoErrors();

        $media = ProductMedia::firstOrFail();
        $this->assertSame('Beige', $media->color);
        Storage::disk('public')->assertExists($media->image);
    }

    public function test_category_metadata_and_shareable_variant_are_rendered(): void
    {
        $this->seed();
        $category = Category::create(['name' => 'Abaya', 'slug' => 'abaya-seo', 'description' => 'Koleksi abaya Asfara.', 'seo_title' => 'Abaya Asfara Premium', 'seo_description' => 'Temukan abaya Asfara pilihan.', 'active' => true]);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Rania', 'slug' => 'rania-share', 'active' => true]);
        $variant = Variant::create(['series_id' => $series->id, 'sku' => 'RANIA-OLIVE-L', 'color' => 'Olive', 'size' => 'L', 'price' => 320000, 'stock' => 4, 'weight' => 500]);
        $soldOut = Variant::create(['series_id' => $series->id, 'sku' => 'RANIA-SAND-M', 'color' => 'Sand', 'size' => 'M', 'price' => 360000, 'stock' => 0, 'weight' => 500]);
        ProductMedia::create(['series_id' => $series->id, 'variant_id' => $soldOut->id, 'color' => 'Sand', 'image' => 'product-gallery/rania-sand.jpg', 'alt_text' => 'Rania warna Sand', 'active' => true]);

        $this->get(route('catalog', ['category' => $category->slug]))
            ->assertOk()->assertSee('Abaya Asfara Premium')->assertSee('Temukan abaya Asfara pilihan.')
            ->assertSee('CollectionPage')->assertSee('category=abaya-seo', false);
        $this->get(route('product', ['slug' => $series->slug, 'variant' => $variant->id]))
            ->assertOk()->assertSee('Bagikan pilihan')->assertSee('history.replaceState', false)
            ->assertSee('selected', false);

        $response = $this->get(route('product', ['slug' => $series->slug, 'variant' => $soldOut->id]))
            ->assertOk()
            ->assertSee('id="display-product-price">Rp 360.000', false)
            ->assertSee('id="display-product-stock">Varian ini habis', false)
            ->assertSee('data-product-submit disabled', false);
        $this->assertMatchesRegularExpression('/<option value="'.$soldOut->id.'"[^>]*disabled[^>]*selected>/', $response->getContent());
        $this->assertMatchesRegularExpression('~id="product-main-image" src="[^"]+/rania-sand\.jpg"~', $response->getContent());
    }
}
