<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_can_manage_reusable_media_assets(): void
    {
        $this->seed();
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nSIAAAAASUVORK5CYII=');

        $this->actingAs($admin)->post(route('admin.media.upload'), ['image' => UploadedFile::fake()->createWithContent('lookbook.png', $png), 'alt_text' => 'Model memakai koleksi Raffah'])->assertRedirect()->assertSessionHasNoErrors();
        $asset = MediaAsset::firstOrFail();
        Storage::disk('public')->assertExists($asset->path);
        $this->get(route('admin.media'))->assertOk()->assertSee('lookbook.png')->assertSee('Model memakai koleksi Raffah');
        $this->get(route('admin.create', 'pages'))->assertOk()->assertSee('lookbook.png')->assertSee('Model memakai koleksi Raffah');

        $this->post(route('admin.media.update', $asset), ['alt_text' => 'Alt text diperbarui'])->assertRedirect();
        $this->assertSame('Alt text diperbarui', $asset->fresh()->alt_text);
        $this->assertFalse($asset->fresh()->active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.uploaded']);
    }
}
