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
            'type' => 'home_section', 'title' => $entry->title, 'slug' => $entry->slug, 'summary' => 'Satu tampilan pilihan Asfara.', 'body' => '',
            'template' => 'standard', 'blocks' => json_encode([['type' => 'product_hotspot', 'series_id' => $this->series->id, 'x' => 42, 'y' => 63]]),
            'link_url' => '/koleksi', 'link_label' => 'Lihat produk', 'seo_title' => '', 'seo_description' => '', 'sort_order' => 1,
            'editorial_status' => 'approved', 'review_note' => '', 'starts_at' => '', 'ends_at' => '', 'active' => '1',
        ];

        $this->actingAs($admin)->post(route('admin.update', ['content', $entry->id]), $payload)->assertSessionHasNoErrors();
        $this->assertSame($this->series->id, $entry->fresh()->blocks[0]['series_id']);
        $entry->update(['image' => 'demo/look-olive-beige-4x5.webp']);
        $this->get(route('home'))->assertOk()->assertDontSee('data-look-carousel', false);
        $this->get(route('content.index', 'lookbook'))->assertOk()->assertSee('Best Combination');
        $this->get(route('content.show', ['lookbook', $entry->slug]))->assertOk()
            ->assertSee('class="look-hotspot-marker"', false)
            ->assertSee('aria-label="Lihat Zayna Series"', false);

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

    public function test_campaign_products_publish_in_editor_order_across_home_navigation_and_landing_page(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $second = Series::create(['category_id' => $this->series->category_id, 'name' => 'Aster Edition', 'slug' => 'aster-edition', 'active' => true]);
        Variant::create(['series_id' => $second->id, 'sku' => 'ASTER-OLV-M', 'color' => 'Olive', 'size' => 'M', 'price' => 399000, 'stock' => 8, 'weight' => 500, 'active' => true]);
        $entry = ContentEntry::create(['type' => 'campaign', 'title' => 'Kampanye Aster', 'slug' => 'kampanye-aster', 'active' => false]);
        $products = [
            ['type' => 'campaign_options', 'group' => 'signature'],
            ['type' => 'campaign_product', 'series_id' => $second->id],
            ['type' => 'campaign_product', 'series_id' => $this->series->id],
            ['type' => 'hero_options', 'mobile_url' => '/storage/demo/hero.webp'],
        ];
        $payload = [
            'type' => 'campaign', 'title' => $entry->title, 'slug' => $entry->slug, 'summary' => 'Koleksi pilihan.',
            'template' => 'standard', 'blocks' => json_encode($products), 'sort_order' => 1,
            'editorial_status' => 'approved', 'active' => '1',
        ];

        $this->actingAs($admin)->get(route('admin.edit', ['content', $entry->id]))->assertOk()->assertSee('Produk kampanye');
        $this->post(route('admin.update', ['content', $entry->id]), $payload)->assertSessionHasNoErrors();
        $url = route('content.show', ['campaign', $entry->slug]);
        $this->get(route('home'))->assertOk()
            ->assertSee('class="campaign-nav"', false)
            ->assertSee('data-campaign-tabs="signature"', false)
            ->assertDontSee('class="campaign-tab-banner"', false)
            ->assertSee($url, false)
            ->assertSee('id="pane-signature-campaign-'.$entry->id.'"', false)
            ->assertDontSee('data-collection-tab="campaign-'.$entry->id.'"', false);
        $this->get($url)->assertOk()->assertSeeInOrder(['Aster Edition', 'Zayna Series'])
            ->assertSee('srcset="/storage/demo/hero.webp"', false)
            ->assertSee('href="#campaign-products-heading"', false);
        $this->get(route('admin.content.preview', $entry))->assertOk()
            ->assertSee('Pratinjau admin')->assertSee('srcset="/storage/demo/hero.webp"', false);

        $payload['blocks'] = json_encode([$products[1], $products[1]]);
        $this->post(route('admin.update', ['content', $entry->id]), $payload)->assertSessionHasErrors('blocks');
        $this->assertCount(2, $entry->fresh()->campaignProductIds());

        $second->update(['active' => false]);
        $this->get($url)->assertOk()->assertSee('Zayna Series')->assertDontSee('Aster Edition');
        $entry->update(['starts_at' => now()->addDay()]);
        $this->get($url)->assertNotFound();
        $this->get(route('home'))->assertOk()->assertDontSee('id="pane-signature-campaign-'.$entry->id.'"', false)->assertDontSee($url, false);
    }

    public function test_home_banner_showcases_products_from_its_catalog_category(): void
    {
        Setting::put('hero_video_enabled', true);
        Setting::put('hero_video', 'demo/hero-desktop.webm');
        $this->series->update(['compare_at_price' => 480000]);
        $category = Category::create(['name' => 'Aksesori', 'slug' => 'aksesori', 'active' => true]);
        $otherSeries = Series::create(['category_id' => $category->id, 'name' => 'Aksesori Asfara', 'slug' => 'aksesori-asfara', 'active' => true]);
        Variant::create(['series_id' => $otherSeries->id, 'sku' => 'AKS-BLK', 'color' => 'Black', 'size' => 'All Size', 'price' => 125000, 'stock' => 4, 'weight' => 100, 'active' => true]);
        $banner = ContentEntry::create([
            'type' => 'banner', 'title' => 'Gamis Asfara', 'slug' => 'gamis-asfara', 'image' => 'demo/hero.webp',
            'link_url' => route('catalog', ['category' => 'gamis'], false), 'editorial_status' => 'approved', 'active' => true,
        ]);

        $this->get(route('home'))->assertOk()
            ->assertSeeInOrder(['id="home-campaign-title"', 'id="home-campaign-products"', 'id="direct-add-home-campaign-'.$this->series->id.'"', 'class="site-footer"'], false)
            ->assertDontSee('id="direct-add-home-campaign-'.$otherSeries->id.'"', false)
            ->assertDontSee('product-tag-sale', false);
        $this->get(route('catalog'))->assertOk()->assertSee('Hemat Rp 72.000');

        $banner->update(['link_url' => 'https://example.com'.$banner->link_url]);
        $this->get(route('home'))->assertOk()->assertSee('id="home-campaign-title"', false)
            ->assertDontSee('id="home-campaign-products"', false);
    }

    public function test_editorial_indexes_and_catalog_facets_render(): void
    {
        ContentEntry::create(['type' => 'lookbook', 'title' => 'Eid Lookbook', 'slug' => 'eid-lookbook', 'summary' => 'Inspirasi tampilan.', 'active' => true]);

        $this->get(route('content.index', 'lookbook'))->assertOk()->assertSee('Eid Lookbook');
        $this->get(route('catalog', ['sort' => 'title_asc', 'color' => 'Black']))->assertOk()->assertSee('WARNA')->assertSee('Black')->assertSee('UKURAN')->assertSee('Zayna Series')
            ->assertSee('FILTER AKTIF')->assertSee('Warna: Black')->assertSee('Filter lanjutan')->assertSee('data-filter-submit', false);
    }

    public function test_store_locations_and_language_switch_are_rendered_from_cms(): void
    {
        StoreLocation::create([
            'name' => 'Asfara Bandung',
            'address' => 'Jl. Buah Batu No. 121',
            'city' => 'Bandung',
            'hours' => '09.00–19.00',
            'map_url' => 'https://example.com/lokasi-bandung',
            'sort_order' => 1,
            'active' => true,
        ]);

        $this->get(route('home'))->assertOk()->assertSee('Asfara Bandung')->assertSee('Jl. Buah Batu No. 121');
        $this->from(route('home'))->get(route('locale', 'en'))->assertRedirect(route('home'))->assertSessionHas('locale', 'en');
        $this->get(route('home'))->assertOk()->assertSee('All collections')->assertSee('Track order');

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->get(route('store.locations'))->assertOk()->assertSee('Asfara Bandung')->assertSee('Lihat di peta');
        $this->actingAs($admin)->get(route('admin.resources', 'store-locations'))->assertOk()->assertSee('Asfara Bandung');
    }

    public function test_menu_entries_can_target_desktop_or_mobile_navigation(): void
    {
        ContentEntry::create(['type' => 'menu', 'placement' => 'mobile', 'title' => 'Mobile Only', 'slug' => 'mobile-only', 'link_url' => '/mobile', 'active' => true]);
        ContentEntry::create(['type' => 'menu', 'placement' => 'desktop', 'title' => 'Desktop Only', 'slug' => 'desktop-only', 'link_url' => '/desktop', 'active' => true]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Mobile Only'));
        $this->assertSame(1, substr_count($html, 'Desktop Only'));
    }

    public function test_storefront_keeps_language_picker_and_hides_legacy_simulated_activity(): void
    {
        Setting::put('social_proof_enabled', true);
        Setting::put('social_proof_cities', 'Jakarta, Bandung');
        Setting::put('hero_content_style', 'compact');

        $this->get(route('home'))->assertOk()
            ->assertSee('language-picker', false)
            ->assertSee('id="home-new-in-title">New In', false)
            ->assertSee('SPECIAL OFFER')
            ->assertSee('aria-controls="catalog-mega-menu"', false)
            ->assertSee('id="catalog-mega-menu"', false)
            ->assertSee('class="mobile-nav-links"', false)
            ->assertSee('id="mobile-open-search"', false)
            ->assertSee('is-catalog-open', false)
            ->assertDontSee('id="social-proof"', false)
            ->assertSee('hero-content-compact', false);

        Setting::put('social_proof_enabled', false);
        $this->get(route('home'))->assertOk()->assertDontSee('id="social-proof"', false);
    }

    public function test_hero_slide_options_are_saved_and_validated(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $entry = ContentEntry::create(['type' => 'banner', 'title' => 'Hero', 'slug' => 'hero-options', 'active' => false]);
        $payload = [
            'type' => 'banner', 'title' => 'Hero', 'slug' => 'hero-options', 'summary' => 'Hero yang dapat diatur.', 'body' => '',
            'template' => 'standard', 'blocks' => json_encode([['type' => 'hero_options', 'align' => 'right', 'tone' => 'dark', 'duration' => 7000, 'mobile_url' => 'https://example.com/mobile.webp', 'video_url' => 'https://example.com/hero.mp4', 'mobile_video_url' => 'https://example.com/hero-mobile.mp4', 'has_audio' => true]]),
            'link_url' => '/koleksi', 'link_label' => 'Belanja', 'seo_title' => '', 'seo_description' => '', 'sort_order' => 1,
            'editorial_status' => 'approved', 'review_note' => '', 'starts_at' => '', 'ends_at' => '', 'active' => '1',
        ];

        $this->actingAs($admin)->post(route('admin.update', ['content', $entry->id]), $payload)->assertSessionHasNoErrors();
        Setting::put('hero_autoplay_seconds', 7);
        $this->get(route('home'))->assertOk()->assertSee('hero-align-right')->assertSee('data-duration="7000"', false)->assertSee('https://example.com/hero.mp4', false)->assertSee('<button type="button" data-hero-sound', false)->assertDontSee('data-hero-prev', false)->assertDontSee('data-hero-next', false);

        $payload['blocks'] = json_encode([['type' => 'hero_options', 'align' => 'outside', 'tone' => 'dark', 'duration' => 7000]]);
        $this->actingAs($admin)->post(route('admin.update', ['content', $entry->id]), $payload)->assertSessionHasErrors('blocks');
    }

    public function test_home_hero_uses_the_published_main_campaign_media_until_disabled_or_unpublished(): void
    {
        Setting::put('hero_video_enabled', true);
        $campaign = ContentEntry::create([
            'type' => 'campaign',
            'title' => 'Kampanye Musim',
            'slug' => 'kampanye-musim',
            'active' => true,
            'editorial_status' => 'approved',
            'blocks' => [
                ['type' => 'campaign_product', 'series_id' => $this->series->id],
                ['type' => 'hero_options', 'video_url' => '/media/musim.mp4', 'mobile_video_url' => '/media/musim-hp.mp4', 'has_audio' => true],
            ],
        ]);

        $this->get(route('home'))->assertOk()
            ->assertSee('<h1>Kampanye Musim</h1>', false)
            ->assertSee('src="/media/musim.mp4"', false)
            ->assertSee('src="/media/musim-hp.mp4"', false)
            ->assertSee('<button type="button" data-hero-sound', false)
            ->assertDontSee('home-spotlight-', false);

        Setting::put('hero_campaign_enabled', false);
        $this->get(route('home'))->assertOk()->assertDontSee('<h1>Kampanye Musim</h1>', false);

        Setting::put('hero_campaign_enabled', true);
        $pinned = ContentEntry::create([
            'type' => 'campaign', 'title' => 'Kampanye Pilihan', 'slug' => 'kampanye-pilihan',
            'active' => true, 'editorial_status' => 'approved', 'sort_order' => 99,
            'blocks' => [
                ['type' => 'campaign_options', 'group' => 'signature'],
                ['type' => 'campaign_product', 'series_id' => $this->series->id],
            ],
        ]);
        Setting::put('hero_campaign_id', $pinned->id);
        $this->get(route('home'))->assertOk()->assertSee('<h1>Kampanye Pilihan</h1>', false);
        $pinned->update(['starts_at' => now()->addDay()]);
        $this->get(route('home'))->assertOk()->assertSee('<h1>Kampanye Musim</h1>', false);
        $campaign->update(['starts_at' => now()->addDay()]);
        $this->get(route('home'))->assertOk()->assertDontSee('<h1>Kampanye Musim</h1>', false)->assertDontSee('/media/musim.mp4', false);
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

    public function test_first_available_variant_is_selected_on_product_page(): void
    {
        $variant = $this->series->variants()->firstOrFail();

        $home = $this->get(route('home'))->assertOk();
        $home->assertDontSee('data-featured-submit', false);

        $product = $this->get(route('product', $this->series->slug))->assertOk();
        $this->assertMatchesRegularExpression('/<option value="'.$variant->id.'"[^>]*selected>/', $product->getContent());
        $product->assertSee('data-cart-add', false)
            ->assertSee('data-product-gallery-next', false)
            ->assertDontSee('product-stock-bar', false)
            ->assertSee('S&amp;K PENGEMBALIAN', false);
    }

    public function test_single_available_variant_can_be_added_directly_from_product_card(): void
    {
        $variant = $this->series->variants()->firstOrFail();
        $formId = 'direct-add-home-new-'.$this->series->id;

        $this->get(route('home'))->assertOk()
            ->assertSee('id="'.$formId.'"', false)
            ->assertSee('name="variant_id" value="'.$variant->id.'"', false)
            ->assertSee('form="'.$formId.'" class="product-quick">ADD TO CART', false);
    }

    public function test_sold_out_product_has_no_quick_purchase_action(): void
    {
        $variant = $this->series->variants()->firstOrFail();
        $variant->update(['stock' => 0]);

        $this->get(route('catalog'))->assertOk()
            ->assertSee('SOLD OUT')
            ->assertDontSee('data-quick-open="quick-product-'.$this->series->id.'"', false)
            ->assertDontSee('form="direct-add-'.$this->series->id.'"', false);
        $this->get(route('product', ['slug' => $this->series->slug, 'variant' => $variant->id]))
            ->assertOk()->assertSee('Stok habis')->assertSee('data-product-submit disabled', false);
    }

    public function test_scheduled_visual_voucher_popup_renders_from_cms_settings(): void
    {
        Setting::put('welcome_voucher_enabled', true);
        Setting::put('welcome_voucher_title', 'Hadiah Pertama Asfara');
        Setting::put('welcome_voucher_text', 'Nikmati penawaran editorial pilihan.');
        Setting::put('welcome_voucher_code', 'ASAFIRST');
        Setting::put('welcome_voucher_image', 'branding/welcome.webp');
        Setting::put('welcome_voucher_cta_label', 'Lihat koleksi');
        Setting::put('welcome_voucher_cta_url', '/koleksi?sort=newest');
        Setting::put('welcome_voucher_delay_seconds', 3);
        Setting::put('welcome_voucher_frequency_days', 7);
        Setting::put('welcome_voucher_start', now()->subMinute()->toDateTimeString());
        Setting::put('welcome_voucher_end', now()->addMinute()->toDateTimeString());
        $this->get(route('home'))->assertOk()->assertDontSee('id="welcome-voucher"', false);
        $campaign = ContentEntry::create(['type' => 'campaign', 'title' => 'Promo Asfara', 'slug' => 'promo-asfara', 'editorial_status' => 'approved', 'active' => true]);
        Setting::put('welcome_voucher_campaign', $campaign->id);

        $this->get(route('home'))->assertOk()
            ->assertSee('class="welcome-voucher has-image"', false)
            ->assertSee('data-delay="3"', false)
            ->assertSee('data-frequency-days="7"', false)
            ->assertSee('branding/welcome.webp', false)
            ->assertSee('/koleksi?sort=newest', false)
            ->assertSee('LIHAT KOLEKSI');
        $this->get(route('catalog'))->assertOk()->assertDontSee('id="welcome-voucher"', false);

        $campaign->update(['active' => false]);
        $this->get(route('home'))->assertOk()->assertDontSee('id="welcome-voucher"', false);
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
        $this->assertEquals($chart, $this->series->fresh()->size_chart);
    }
}
