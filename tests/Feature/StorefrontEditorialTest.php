<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\NewsletterSubscriber;
use App\Models\Series;
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
}
