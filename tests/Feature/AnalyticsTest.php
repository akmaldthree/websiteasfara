<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Category;
use App\Models\Series;
use App\Models\Setting;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_records_anonymous_funnel_events_without_search_text(): void
    {
        $this->seed();
        $category = Category::create(['name' => 'Abaya', 'slug' => 'abaya-analytics', 'active' => true]);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Alya', 'slug' => 'alya-analytics', 'active' => true]);
        $variant = Variant::create(['series_id' => $series->id, 'sku' => 'ALYA-BLACK-M', 'color' => 'Black', 'size' => 'M', 'price' => 275000, 'stock' => 4, 'weight' => 450]);

        $this->get('/')->assertOk();
        $this->get(route('catalog', ['q' => 'rahasia pelanggan']))->assertOk();
        $this->get(route('product', ['slug' => $series->slug, 'variant' => $variant->id]))->assertOk();
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect(route('cart'));

        $this->assertDatabaseCount('analytics_events', 4);
        $this->assertSame(['add_to_cart', 'search', 'view_home', 'view_item'], AnalyticsEvent::pluck('event')->sort()->values()->all());
        $this->assertStringNotContainsString('rahasia pelanggan', AnalyticsEvent::get()->toJson());
        $this->assertSame(64, strlen(AnalyticsEvent::firstOrFail()->visitor_hash));
    }

    public function test_admin_can_disable_internal_analytics(): void
    {
        $this->seed();
        Setting::put('analytics_enabled', false);
        $this->get('/')->assertOk();
        $this->assertDatabaseCount('analytics_events', 0);
    }
}
