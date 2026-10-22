<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_scheduled_content_is_only_public_in_its_window_with_seo_metadata(): void
    {
        $future = ContentEntry::create(['type' => 'journal', 'title' => 'Artikel Masa Depan', 'slug' => 'masa-depan', 'body' => 'Belum boleh terlihat.', 'active' => true, 'starts_at' => now()->addDay()]);
        $this->get(route('content.show', ['journal', $future->slug]))->assertNotFound();
        $future->update(['starts_at' => now()->subMinute(), 'seo_title' => 'SEO Journal', 'seo_description' => 'Deskripsi pencarian journal.']);
        $this->get(route('content.show', ['journal', $future->slug]))->assertOk()->assertSee('SEO Journal')->assertSee('Deskripsi pencarian journal.');
        $future->update(['ends_at' => now()->subMinute()]);
        $this->get(route('content.show', ['journal', $future->slug]))->assertNotFound();
    }

    public function test_home_sections_and_menu_follow_publish_schedule(): void
    {
        ContentEntry::create(['type' => 'banner', 'title' => 'Banner Aktif', 'slug' => 'banner-aktif', 'summary' => 'Pesan banner.', 'sort_order' => 2, 'active' => true]);
        ContentEntry::create(['type' => 'menu', 'title' => 'Journal Kami', 'slug' => 'menu-journal', 'link_url' => '/konten/journal/cerita', 'sort_order' => 1, 'active' => true]);
        ContentEntry::create(['type' => 'home_section', 'title' => 'Section Kedaluwarsa', 'slug' => 'section-lama', 'active' => true, 'ends_at' => now()->subMinute()]);
        $this->get('/')->assertOk()->assertSee('Banner Aktif')->assertSee('Journal Kami')->assertDontSee('Section Kedaluwarsa');
    }

    public function test_admin_can_preview_unpublished_content_and_pages_are_scheduled(): void
    {
        $entry = ContentEntry::create(['type' => 'campaign', 'title' => 'Preview Campaign', 'slug' => 'preview-campaign', 'body' => 'Konten pratinjau.', 'active' => false]);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get(route('admin.content.preview', $entry))->assertOk()->assertSee('Pratinjau admin')->assertSee('Konten pratinjau.');

        $page = Page::create(['title' => 'Terjadwal', 'slug' => 'terjadwal', 'body' => 'Isi halaman.', 'active' => true, 'starts_at' => now()->addDay()]);
        $this->get(route('page', $page->slug))->assertNotFound();
    }
}
