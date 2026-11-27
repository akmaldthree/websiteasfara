<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\ContentRevision;
use App\Models\Coupon;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Page;
use App\Models\Role;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontCmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_editor_cannot_replace_a_live_page_until_approval(): void
    {
        Role::create(['name' => 'editor', 'permissions' => ['content.write']]);
        $editor = User::factory()->create(['role' => 'editor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $page = Page::create(['title' => 'Versi publik', 'slug' => 'audit-publik', 'body' => 'Teks tayang', 'editorial_status' => 'approved', 'active' => true]);
        $approved = ContentRevision::create(['content_type' => 'pages', 'content_id' => $page->id, 'snapshot' => $page->getAttributes()]);

        $this->actingAs($editor)->post(route('admin.content.restore', $approved))->assertRedirect();
        $this->assertSame('draft', $page->fresh()->editorial_draft['editorial_status']);
        $payload = ['title' => 'Revisi baru', 'slug' => $page->slug, 'body' => 'Teks revisi belum tayang', 'editorial_status' => 'review', 'active' => '1'];
        $this->post(route('admin.update', ['pages', $page->id]), $payload)->assertSessionHasNoErrors();
        $this->get(route('page', $page->slug))->assertOk()->assertSee('Teks tayang')->assertDontSee('Teks revisi belum tayang');
        $this->get(route('admin.page.preview', $page))->assertOk()->assertSee('Teks revisi belum tayang')->assertSee('noindex,nofollow');
        $this->delete(route('admin.destroy', ['pages', $page->id]))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.update', ['pages', $page->id]), array_replace($payload, ['editorial_status' => 'approved']))->assertSessionHasNoErrors();
        $this->get(route('page', $page->slug))->assertOk()->assertSee('Teks revisi belum tayang');
        $this->assertNull($page->fresh()->editorial_draft);
    }

    public function test_home_layout_draft_previews_then_publishes_and_protects_selected_media(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cms-media/card.webp', 'visual test');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = MediaAsset::create(['name' => 'Abaya editorial', 'path' => 'cms-media/card.webp', 'mime' => 'image/webp', 'size' => 11, 'alt_text' => 'Abaya']);
        $section = ContentEntry::create(['type' => 'home_section', 'title' => 'Padanan Baru', 'slug' => 'padanan-baru', 'editorial_status' => 'approved', 'active' => true]);
        $this->get(route('admin.home-layout.preview'))->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('admin.home-layout'))->assertOk()->assertSee('Simpan draft');
        $this->post(route('admin.home-layout.save'), [
            'section_order' => 'hero,categories,editorial,new_in',
            'home_section_hidden' => ['new_in'],
            'home_visual_cards' => [['label' => 'Abaya Perjalanan', 'url' => '/koleksi', 'desktop_id' => $asset->id]],
            'home_editorial_sections' => [$section->id],
        ])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertDontSee('Abaya Perjalanan')->assertDontSee('Padanan Baru');
        $this->get(route('admin.home-layout.preview'))->assertOk()->assertSee('Abaya Perjalanan')->assertSee('Padanan Baru')->assertDontSee('id="new-in"', false);
        $this->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('delete');
        Storage::disk('public')->assertExists($asset->path);
        $revision = Setting::get('home_layout_draft')['revision'];
        $this->post(route('admin.home-layout.publish'), ['revision' => $revision])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertSee('Abaya Perjalanan')->assertSee('Padanan Baru')->assertDontSee('id="new-in"', false);
        $this->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('delete');
    }

    public function test_curated_products_outside_recent_results_and_draft_previews_work(): void
    {
        $category = Category::create(['name' => 'Abaya', 'slug' => 'abaya', 'active' => true]);
        $old = Series::create(['category_id' => $category->id, 'name' => 'Abaya Kurasi Arsip', 'slug' => 'kurasi-arsip', 'active' => true, 'created_at' => now()->subYear()]);
        foreach (range(1, 25) as $index) {
            Series::create(['category_id' => $category->id, 'name' => 'Model '.$index, 'slug' => 'model-'.$index, 'active' => true]);
        }
        Setting::put('home_new_slugs', $old->slug);
        $this->get(route('home'))->assertOk()->assertSee($old->name);

        $old->update(['active' => false]);
        $this->get(route('product', $old->slug))->assertNotFound();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.products.preview', $old->slug))->assertOk()->assertSee('Pembelian dinonaktifkan');
    }

    public function test_curated_new_in_displays_all_twelve_selected_products(): void
    {
        $category = Category::create(['name' => 'Abaya Kurasi', 'slug' => 'abaya-kurasi', 'active' => true]);
        $slugs = collect(range(1, 12))->map(function (int $number) use ($category): string {
            return Series::create([
                'name' => 'Kurasi beranda produk nomor '.$number,
                'slug' => 'kurasi-beranda-'.$number,
                'category_id' => $category->id,
                'active' => true,
            ])->slug;
        });
        Setting::put('home_new_slugs', $slugs->implode(', '));

        $page = $this->get(route('home'))->assertOk();

        $page->assertSeeInOrder(['id="new-in"', 'Kurasi beranda produk nomor 9', 'Kurasi beranda produk nomor 10', 'Kurasi beranda produk nomor 11', 'Kurasi beranda produk nomor 12'], false);
    }

    public function test_closing_products_have_their_own_selection_order_and_visibility_in_the_home_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Abaya Pilihan', 'slug' => 'abaya-pilihan', 'active' => true]);
        $first = Series::create(['name' => 'Abaya Awal', 'slug' => 'abaya-awal', 'category_id' => $category->id, 'active' => true]);
        $second = Series::create(['name' => 'Abaya Penutup', 'slug' => 'abaya-penutup', 'category_id' => $category->id, 'active' => true]);
        foreach ([$first, $second] as $product) {
            Variant::create(['series_id' => $product->id, 'sku' => strtoupper($product->slug), 'color' => 'Hitam', 'size' => 'M', 'price' => 299000, 'stock' => 5, 'weight' => 600]);
        }
        $data = [
            'section_order' => 'hero,campaign_products,new_in',
            'home_section_hidden' => ['campaign'],
            'campaign_products' => [
                'title_id' => 'Pilihan butik Asfara',
                'title_en' => 'Asfara boutique picks',
                'url' => '/koleksi?category=abaya-pilihan',
                'products' => [$second->id, null, $first->id, null],
            ],
        ];

        $this->actingAs($admin)->post(route('admin.home-layout.save'), $data)->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertDontSee('Pilihan butik Asfara');
        $this->get(route('admin.home-layout.preview'))->assertOk()
            ->assertSeeInOrder(['Pilihan butik Asfara', 'direct-add-home-campaign-'.$second->id, 'direct-add-home-campaign-'.$first->id, 'id="new-in"'], false)
            ->assertSee('href="/koleksi?category=abaya-pilihan"', false);

        $this->post(route('admin.home-layout.publish'), ['revision' => Setting::get('home_layout_draft')['revision']])->assertSessionHasNoErrors();
        $this->withSession(['locale' => 'en'])->get(route('home'))->assertOk()->assertSee('Asfara boutique picks');
        $first->update(['active' => false]);
        $this->get(route('home'))->assertOk()->assertDontSee('direct-add-home-campaign-'.$first->id, false);

        $data['home_section_hidden'] = ['campaign_products'];
        $this->post(route('admin.home-layout.save'), $data)->assertSessionHasNoErrors();
        $this->get(route('admin.home-layout.preview'))->assertOk()->assertDontSee('id="home-campaign-products"', false);
        $this->get(route('home'))->assertOk()->assertSee('id="home-campaign-products"', false);
    }

    public function test_media_used_as_a_direct_cover_cannot_be_deleted(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cms-media/hero.webp', 'visual test');
        $asset = MediaAsset::create(['name' => 'Hero', 'path' => 'cms-media/hero.webp', 'mime' => 'image/webp', 'size' => 11, 'alt_text' => 'Foto hero']);
        Setting::put('hero_image', $asset->path);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))->assertSessionHasErrors('delete');
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_media_library_uploads_and_finds_multiple_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $data = file_get_contents(database_path('seeders/assets/demo/abaya-editorial-tile-harian.webp'));
        $this->actingAs($admin)->post(route('admin.media.upload'), ['images' => [
            UploadedFile::fake()->createWithContent('abaya-harian-uji.webp', $data),
            UploadedFile::fake()->createWithContent('abaya-formal-uji.webp', $data),
        ]])->assertSessionHasNoErrors();
        $asset = MediaAsset::where('name', 'abaya-harian-uji.webp')->firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
        Setting::put('hero_image', $asset->path);
        $this->get(route('admin.media', ['q' => 'harian uji', 'usage' => $asset->id]))
            ->assertOk()->assertSee('Pengaturan website')->assertDontSee('abaya-formal-uji.webp');
    }

    public function test_promotion_workspace_evaluates_a_future_voucher_without_spending_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = ContentEntry::create(['type' => 'campaign', 'title' => 'Kampanye mendatang', 'slug' => 'kampanye-mendatang']);
        $coupon = Coupon::create(['code' => 'TEST20', 'type' => 'percent', 'value' => 20, 'minimum' => 0, 'active' => true,
            'usage_limit' => 10, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(3)]);
        $category = Category::create(['name' => 'Abaya Promo', 'slug' => 'abaya-promo', 'active' => true]);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Model Promo', 'slug' => 'model-promo', 'active' => true]);
        $variant = Variant::create(['series_id' => $series->id, 'sku' => 'PROMO-SKU', 'size' => 'S', 'color' => 'Black', 'price' => 200000, 'weight' => 500, 'stock' => 2]);
        $rate = ShippingRate::create(['courier' => 'JNE', 'service' => 'REG', 'province' => 'Jawa Barat', 'city' => 'Tasikmalaya',
            'first_kg' => 15000, 'extra_kg' => 10000, 'estimate' => '2 hari', 'active' => true]);
        Setting::put('bank_accounts', [['bank' => 'Bank Uji']]);

        $this->actingAs($admin)->post(route('admin.promotions.link', $campaign), ['coupon_id' => $coupon->id])->assertSessionHasNoErrors();
        $this->get(route('admin.promotions', ['campaign' => $campaign->id]))->assertOk()->assertSee($coupon->code);
        $this->post(route('admin.promotions.simulate'), [
            'campaign' => $campaign->id, 'coupon' => $coupon->code, 'shipping_rate_id' => $rate->id,
            'items' => [['sku' => $variant->sku, 'quantity' => 1]], 'evaluate_at' => now()->addDays(2)->format('Y-m-d H:i'),
        ])->assertSessionHas('promotion_quote.discount', 40000)->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $coupon->fresh()->used);
        $this->assertSame(0, $variant->fresh()->reserved);
        $this->assertSame(0, Order::count());
    }
}
