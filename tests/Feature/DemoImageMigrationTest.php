<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoImageMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_demo_image_references_are_migrated_to_webp(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('demo/hero-family-2x1.png', 'legacy image');
        DB::table('settings')->insert([
            'key' => 'hero_image',
            'value' => json_encode('demo/hero-family-2x1.png'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('content_entries')->insert([
            'type' => 'banner',
            'placement' => 'both',
            'title' => 'Banner lama',
            'slug' => 'banner-lama',
            'template' => 'standard',
            'image' => 'demo/hero-family-2x1.png',
            'sort_order' => 1,
            'active' => true,
            'editorial_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_30_140000_migrate_demo_images_to_webp.php');
        $migration->up();

        $this->assertDatabaseHas('settings', [
            'key' => 'hero_image',
            'value' => json_encode('demo/hero-family-2x1.webp'),
        ]);
        $this->assertDatabaseHas('content_entries', [
            'slug' => 'banner-lama',
            'image' => 'demo/hero-family-2x1.webp',
        ]);
        Storage::disk('public')->assertExists('demo/hero-family-2x1.webp');
        Storage::disk('public')->assertMissing('demo/hero-family-2x1.png');
    }
}
