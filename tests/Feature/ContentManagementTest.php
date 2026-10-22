<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\ContentRevision;
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

    public function test_admin_can_review_and_restore_page_revisions_without_losing_current_version(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $page = Page::create(['title' => 'Kebijakan Awal', 'slug' => 'kebijakan-revisi', 'body' => 'Isi versi pertama.', 'active' => false]);

        $this->actingAs($admin)->post(route('admin.update', ['pages', $page->id]), [
            'title' => 'Kebijakan Baru', 'slug' => $page->slug, 'body' => 'Isi versi kedua.', 'seo_title' => '', 'seo_description' => '',
            'starts_at' => '', 'ends_at' => '', 'active' => '1',
        ])->assertRedirect(route('admin.resources', 'pages'))->assertSessionHasNoErrors();

        $revision = ContentRevision::firstOrFail();
        $this->assertSame('Kebijakan Awal', $revision->snapshot['title']);
        $this->actingAs($admin)->get(route('admin.content.history', ['pages', $page->id]))->assertOk()->assertSee('Kebijakan Awal');

        $this->actingAs($admin)->post(route('admin.content.restore', $revision))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Kebijakan Awal', $page->fresh()->title);
        $this->assertSame('Isi versi pertama.', $page->fresh()->body);
        $this->assertFalse($page->fresh()->active);
        $this->assertCount(2, ContentRevision::all());
        $this->assertDatabaseHas('content_revisions', ['content_type' => 'pages', 'content_id' => $page->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pages.restored', 'subject' => 'pages:'.$page->id]);
    }
}
