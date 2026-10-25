<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\CuratedLink;
use App\Models\NewsletterSubscriber;
use App\Models\Series;
use App\Models\Setting;
use App\Models\StoreLocation;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontEditorialTest extends TestCase
{
    use RefreshDatabase;

    private Series $series;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $category = Category::create(['name' => 'Gamis', 'slug' => 'gamis', 'active' => true]);
        $this->series = Series::create(['category_id' => $category->id, 'name' => 'Zayna Series', 'slug' => 'zayna-series', 'active' => true]);
        Variant::create(['series_id' => $this->series->id, 'sku' => 'ZAY-BLK-M', 'color' => 'Black', 'size' => 'M', 'price' => 408000, 'stock' => 12, 'weight' => 600, 'active' => true]);
    }

    public function test_shop_the_look_hotspot_is_managed_as_a_content_block(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $entry = ContentEntry::create(['type' => 'home_section', 'title' => 'Best Combination', 'slug' => 'best-combination', 'active' => false]);
        $payload = [
            'type' => 'home_section', 'title' => $entry->title, 'slug' => $entry->slug, 'summary' => 'Satu tampilan pilihan Dthree.', 'body' => '',
            'template' => 'standard', 'blocks' => json_encode([['type' => 'product_hotspot', 'series_id' => $this->series->id, 'x' => 42, 'y' => 63]]),
            'link_url' => '/koleksi', 'link_label' => 'Lihat produk', 'seo_title' => '', 'seo_description' => '', 'sort_order' => 1,
            'editorial_status' => 'approved', 'review_note' => '', 'starts_at' => '', 'ends_at' => '', 'active' => '1',
        ];

        $this->actingAs($admin)->post(route('admin.update', ['content', $entry->id]), $payload)->assertSessionHasNoErrors();
        $this->assertSame($this->series->id, $entry->fresh()->blocks[0]['series_id']);
        $this->get(route('home'))->assertOk()->assertSee('SHOP THE LOOK')->assertSee('data-look-product="look-product-'.$this->series->id.'"', false);

        $payload['blocks'] = json_encode([['type' => 'product_hotspot', 'series_id' => $this->series->id, 'x' => 120, 'y' => 50]]);
        $this->actingAs($admin)->post(route('admin.update', ['content', $entry->id]), $payload)->assertSessionHasErrors('blocks');
    }

    public function test_newsletter_subscription_is_idempotent_and_visible_to_admin(): void
    {
        $payload = ['email' => 'Customer@Example.com', 'consent' => '1'];
        $this->post(route('newsletter.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('newsletter.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        $this->assertSame('customer@example.com', NewsletterSubscriber::firstOrFail()->email);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get(route('admin.resources', 'newsletter'))->assertOk()->assertSee('customer@example.com');
    }

    public function test_editorial_indexes_and_catalog_facets_render(): void
    {
        ContentEntry::create(['type' => 'lookbook', 'title' => 'Eid Lookbook', 'slug' => 'eid-lookbook', 'summary' => 'Inspirasi tampilan.', 'active' => true]);

        $this->get(route('content.index', 'lookbook'))->assertOk()->assertSee('Eid Lookbook');
        $this->get(route('catalog', ['sort' => 'title_asc']))->assertOk()->assertSee('WARNA')->assertSee('Black')->assertSee('UKURAN')->assertSee('ZAYNA SERIES');
    }

    public function test_store_locations_and_language_switch_are_rendered_from_cms(): void
    {
        StoreLocation::create([
            'name' => 'Dthree Bandung',
            'address' => 'Jl. Buah Batu No. 121',
            'city' => 'Bandung',
            'hours' => '09.00–19.00',
            'sort_order' => 1,
            'active' => true,
        ]);

        $this->get(route('home'))->assertOk()->assertSee('Dthree Bandung')->assertSee('Jl. Buah Batu No. 121');
        $this->from(route('home'))->get(route('locale', 'en'))->assertRedirect(route('home'))->assertSessionHas('locale', 'en');
        $this->get(route('home'))->assertOk()->assertSee('All collections')->assertSee('Track order');

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get(route('admin.resources', 'store-locations'))->assertOk()->assertSee('Dthree Bandung');
    }

    public function test_hero_slide_options_are_saved_and_validated(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $entry = ContentEntry::create(['type' => 'banner', 'title' => 'Hero', 'slug' => 'hero-options', 'active' => false]);
        $payload = [
            'type' => 'banner', 'title' => 'Hero', 'slug' => 'hero-options', 'summary' => 'Hero yang dapat diatur.', 'body' => '',
            'template' => 'standard', 'blocks' => json_encode([['type' => 'hero_options', 'align' => 'right', 'tone' => 'dark', 'duration' => 7000, 'mobile_url' => 'https://example.com/mobile.webp']]),
            'link_url' => '/koleksi', 'link_label' => 'Belanja', 'seo_title' => '', 'seo_description' => '', 'sort_order' => 1,
            'editorial_status' => 'approved', 'review_note' => '', 'starts_at' => '', 'ends_at' => '', 'active' => '1',
        ];

        $this->actingAs($admin)->post(route('admin.update', ['content', $entry->id]), $payload)->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertSee('hero-align-right')->assertSee('data-duration="7000"', false);

        $payload['blocks'] = json_encode([['type' => 'hero_options', 'align' => 'outside', 'tone' => 'dark', 'duration' => 7000]]);
        $this->actingAs($admin)->post(route('admin.update', ['content', $entry->id]), $payload)->assertSessionHasErrors('blocks');
    }

    public function test_curated_navigation_and_structured_size_chart_render(): void
    {
        CuratedLink::create(['section' => 'fit', 'title' => 'Office Wear', 'url' => '/koleksi?category=gamis', 'active' => true]);
        $this->series->update(['size_chart' => [
            'columns' => ['Ukuran', 'Lingkar dada', 'Panjang'],
            'rows' => [['M', '100 cm', '138 cm'], ['L', '106 cm', '140 cm']],
            'notes' => 'Ukuran dalam sentimeter.',
        ]]);

        $this->get(route('home'))->assertOk()->assertSee('FIND YOUR FIT')->assertSee('OFFICE WEAR');
        $this->get(route('product', $this->series->slug))->assertOk()->assertSee('Lingkar dada')->assertSee('106 cm');
    }

    public function test_first_available_variant_is_selected_on_home_and_product_pages(): void
    {
        $variant = $this->series->variants()->firstOrFail();

        $home = $this->get(route('home'))->assertOk();
        $this->assertMatchesRegularExpression('/<option value="'.$variant->id.'"[^>]*selected>/', $home->getContent());
        $this->assertMatchesRegularExpression('/<button class="btn btn-dark"\s+data-featured-submit>ADD TO CART<\/button>/', $home->getContent());

        $product = $this->get(route('product', $this->series->slug))->assertOk();
        $this->assertMatchesRegularExpression('/<option value="'.$variant->id.'"[^>]*selected>/', $product->getContent());
        $product->assertSee('const initial=select.selectedOptions[0]', false);
    }

    public function test_empty_cart_summary_has_recommendations_and_shipping_progress(): void
    {
        Setting::put('free_shipping_threshold', 500000);

        $this->get(route('cart.summary'))->assertOk()->assertJson([
            'count' => 0,
            'free_shipping_target' => 500000,
            'free_shipping_remaining' => 500000,
            'free_shipping_progress' => 0,
        ])->assertJsonCount(1, 'recommendations');
    }

    public function test_admin_can_manage_curated_links_and_structured_size_chart(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->post(route('admin.store', 'curated-links'), [
            'section' => 'fit', 'title' => 'Weekend Edit', 'url' => '/koleksi?sort=newest',
            'eyebrow' => '', 'sort_order' => 3, 'active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('curated_links', ['title' => 'Weekend Edit', 'active' => true]);

        $chart = ['columns' => ['Ukuran', 'Dada'], 'rows' => [['M', '100 cm'], ['L', '106 cm']], 'notes' => 'Ukur badan tanpa menarik meteran.'];
        $this->actingAs($admin)->post(route('admin.update', ['series', $this->series->id]), [
            'category_id' => $this->series->category_id, 'name' => $this->series->name, 'slug' => $this->series->slug,
            'description' => '', 'material' => '', 'care' => '', 'seo_title' => '', 'seo_description' => '',
            'size_guide' => '', 'size_chart' => json_encode($chart), 'uses_product_type' => '0', 'featured' => '0', 'active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame($chart, $this->series->fresh()->size_chart);
    }
}
