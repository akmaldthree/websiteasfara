<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProductMedia;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\Role;
use App\Models\Series;
use App\Models\User;
use App\Models\Variant;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_editable_master_data_has_delete_controls_and_can_be_deleted(): void
    {
        $category = Category::create(['name' => 'Kategori Sementara', 'slug' => 'kategori-sementara', 'active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.resources', 'categories'))
            ->assertOk()
            ->assertSee(route('admin.destroy', ['categories', $category->id]), false)
            ->assertSee('Hapus');
        $this->get(route('admin.edit', ['categories', $category->id]))
            ->assertOk()
            ->assertSee('Hapus permanen');

        $this->delete(route('admin.destroy', ['categories', $category->id]))
            ->assertRedirect(route('admin.resources', 'categories'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'categories.deleted', 'subject' => 'categories:'.$category->id]);
    }

    public function test_referenced_master_data_is_not_deleted_and_explains_what_to_do(): void
    {
        $category = Category::create(['name' => 'Kategori Terpakai', 'slug' => 'kategori-terpakai', 'active' => true]);
        Series::create(['category_id' => $category->id, 'name' => 'Produk Aktif', 'slug' => 'produk-aktif', 'active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('admin.destroy', ['categories', $category->id]))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_unused_product_deletion_removes_variants_gallery_files_and_records(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/delete-main.webp', 'main');
        Storage::disk('public')->put('product-gallery/delete-gallery.webp', 'gallery');
        $category = Category::create(['name' => 'Hapus Produk', 'slug' => 'hapus-produk', 'active' => true]);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Produk Sementara', 'slug' => 'produk-sementara', 'image' => 'products/delete-main.webp', 'active' => false]);
        $variant = Variant::create([
            'series_id' => $series->id, 'sku' => 'DELETE-ONE', 'product_type' => '', 'color' => 'Ivory', 'size' => 'M',
            'price' => 100000, 'weight' => 500, 'stock' => 0, 'reserved' => 0, 'low_stock' => 0, 'fulfillment' => 'ready_stock', 'active' => false,
        ]);
        $media = ProductMedia::create(['series_id' => $series->id, 'variant_id' => $variant->id, 'image' => 'product-gallery/delete-gallery.webp', 'alt_text' => 'Foto sementara', 'sort_order' => 0, 'active' => true]);

        $this->actingAs($this->admin)
            ->delete(route('admin.destroy', ['series', $series->id]))
            ->assertRedirect(route('admin.resources', 'series'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('series', ['id' => $series->id]);
        $this->assertDatabaseMissing('variants', ['id' => $variant->id]);
        $this->assertDatabaseMissing('product_media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing('products/delete-main.webp');
        Storage::disk('public')->assertMissing('product-gallery/delete-gallery.webp');
    }

    public function test_admin_can_delete_moderated_content_unused_staff_and_unused_role(): void
    {
        $this->seed(DemoSeeder::class);
        $review = Review::firstOrFail();
        $question = ProductQuestion::firstOrFail();
        $staff = User::factory()->create(['role' => 'editor']);
        $role = Role::create(['name' => 'temporary-role', 'permissions' => []]);

        $this->actingAs($this->admin)->delete(route('admin.reviews.destroy', $review))->assertSessionHasNoErrors();
        $this->delete(route('admin.questions.destroy', $question))->assertSessionHasNoErrors();
        $this->delete(route('admin.team.destroy', $staff))->assertSessionHasNoErrors();
        $this->delete(route('admin.role.destroy', $role))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('product_questions', ['id' => $question->id]);
        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
