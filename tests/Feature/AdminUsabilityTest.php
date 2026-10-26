<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_resource_search_only_shows_matching_records_and_keeps_the_query(): void
    {
        Category::create(['name' => 'Abaya Khusus', 'slug' => 'abaya-khusus', 'active' => true]);
        Category::create(['name' => 'Sarimbit Keluarga', 'slug' => 'sarimbit-keluarga', 'active' => true]);

        $this->actingAs($this->admin)
            ->get(route('admin.resources', ['categories', 'q' => 'Abaya']))
            ->assertOk()
            ->assertSee('Abaya Khusus')
            ->assertDontSee('Sarimbit Keluarga')
            ->assertSee('value="Abaya"', false);
    }

    public function test_admin_can_choose_where_to_continue_after_saving(): void
    {
        $payload = ['name' => 'Kategori Cepat', 'slug' => 'kategori-cepat', 'description' => '', 'seo_title' => '', 'seo_description' => '', 'active' => '1'];

        $this->actingAs($this->admin)
            ->post(route('admin.store', 'categories'), $payload + ['after_save' => 'edit'])
            ->assertRedirect(route('admin.edit', ['categories', Category::where('slug', 'kategori-cepat')->value('id')]));

        $this->actingAs($this->admin)
            ->post(route('admin.store', 'categories'), array_merge($payload, ['name' => 'Kategori Berikutnya', 'slug' => 'kategori-berikutnya', 'after_save' => 'new']))
            ->assertRedirect(route('admin.create', 'categories'));
    }

    public function test_key_cms_pages_render_with_guided_workflows(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Apa yang ingin Anda kelola?')
            ->assertDontSee('Varian &amp; SKU', false)
            ->assertDontSee('Galeri produk')
            ->assertSee('css/dthree.css?v=', false)
            ->assertSee('class="admin-sidebar-backdrop" data-sidebar-close hidden', false);
        $this->get(route('admin.settings'))->assertOk()->assertSee('Lompat ke:')->assertSee('Banner & konten website')->assertSee('Sage — latar utama')->assertSee('#EBF2ED');
        $this->get(route('admin.media'))->assertOk()->assertSee('Cara menggunakan gambar');
        $this->get(route('admin.import'))->assertOk()->assertSee('Unduh dan isi template');
    }

    public function test_image_upload_fields_show_contextual_size_recommendations(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('600 × 200 px')
            ->assertSee('512 × 512 px')
            ->assertSee('1920 × 960 px');

        $this->get(route('admin.media'))
            ->assertOk()
            ->assertSee('Minimal 1600 px pada sisi terpanjang');

        $this->get(route('admin.create', 'series'))
            ->assertOk()
            ->assertSee('1200 × 1500 px');

        $this->get(route('admin.create', 'content'))
            ->assertOk()
            ->assertSee('Shop the Look 1600 × 2000 px');

        $this->get(route('admin.create', 'curated-links'))
            ->assertOk()
            ->assertSee('900 × 1200 px');
    }
}
