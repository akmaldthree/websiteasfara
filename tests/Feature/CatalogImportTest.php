<?php

namespace Tests\Feature;

use App\Http\Controllers\ImportController;
use App\Models\Category;
use App\Models\Series;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_and_upsert_preserve_atomic_stock_ledger(): void
    {
        $this->seed();
        $category = Category::create(['name' => 'Import', 'slug' => 'import']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Import Series', 'slug' => 'import-series', 'active' => true]);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->post(route('admin.import.generate'), ['series_id' => $series->id, 'sku_prefix' => 'GEN', 'colors' => 'Black,White', 'sizes' => 'M,L', 'price' => 100000, 'stock' => 3, 'weight' => 400, 'fulfillment' => 'ready_stock', 'mode' => 'create'])->assertOk();
        $this->post(route('admin.import.commit'))->assertRedirect();
        $this->assertDatabaseCount('variants', 4);

        $variant = Variant::where('sku', 'GEN-BLACK-M')->firstOrFail();
        $csv = implode(',', ImportController::COLUMNS)."\nimport-series,GEN-BLACK-M,,Black,M,110000,7,450,ready_stock,\n";
        $this->post(route('admin.import.preview'), ['mode' => 'upsert', 'csv' => UploadedFile::fake()->createWithContent('upsert.csv', $csv)])->assertOk();
        $this->post(route('admin.import.commit'))->assertRedirect();
        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['variant_id' => $variant->id, 'stock_delta' => 4]);
    }
}
