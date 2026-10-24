<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Variant;
use App\Services\BulkImportService;
use App\Support\SimpleXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
    }

    public function test_every_safe_master_module_has_an_xlsx_template(): void
    {
        $service = app(BulkImportService::class);
        $this->actingAs($this->admin)->get(route('admin.import'))->assertOk()->assertSee('Kategori')->assertSee('Tarif pengiriman')->assertSee('Konten website');

        foreach (array_keys($service->definitions()) as $type) {
            $response = $this->get(route('admin.import.template', ['type' => $type]));
            $response->assertOk();
            $this->assertStringStartsWith('PK', $response->streamedContent(), $type);
        }
    }

    public function test_category_import_supports_preview_create_and_upsert(): void
    {
        $columns = app(BulkImportService::class)->definitions()['categories']['columns'];
        $create = implode(',', $columns)."\nAbaya,abaya,Koleksi abaya,Abaya Dthree,Koleksi abaya,1\n";

        $this->actingAs($this->admin)->post(route('admin.import.preview'), ['type' => 'categories', 'mode' => 'create', 'file' => UploadedFile::fake()->createWithContent('categories.csv', $create)])->assertOk()->assertSee('Valid');
        $this->post(route('admin.import.commit'))->assertRedirect();
        $this->assertDatabaseHas('categories', ['slug' => 'abaya', 'name' => 'Abaya', 'active' => 1]);

        $update = implode(',', $columns)."\nAbaya Premium,abaya,Koleksi baru,Abaya Premium,Koleksi baru,0\n";
        $this->post(route('admin.import.preview'), ['type' => 'categories', 'mode' => 'upsert', 'file' => UploadedFile::fake()->createWithContent('categories.csv', $update)])->assertOk();
        $this->post(route('admin.import.commit'))->assertRedirect();
        $this->assertDatabaseHas('categories', ['slug' => 'abaya', 'name' => 'Abaya Premium', 'active' => 0]);
    }

    public function test_invalid_row_keeps_the_whole_import_from_being_committed(): void
    {
        $columns = app(BulkImportService::class)->definitions()['categories']['columns'];
        $csv = implode(',', $columns)."\nValid,valid,,,,1\nInvalid,slug dengan spasi,,,,1\n";

        $this->actingAs($this->admin)->post(route('admin.import.preview'), ['type' => 'categories', 'file' => UploadedFile::fake()->createWithContent('categories.csv', $csv)])->assertOk()->assertSee('Perbaiki file');
        $this->post(route('admin.import.commit'))->assertSessionHasErrors();
        $this->assertDatabaseMissing('categories', ['slug' => 'valid']);
    }

    public function test_shipping_rates_can_be_imported_by_settings_admin(): void
    {
        Category::create(['name' => 'Produk', 'slug' => 'produk']);
        $columns = app(BulkImportService::class)->definitions()['shipping-rates']['columns'];
        $csv = implode(',', $columns)."\nJNE,REG,Jawa Barat,Bandung,15000,5000,2-3 hari,1,1\n";

        $this->actingAs($this->admin)->post(route('admin.import.preview'), ['type' => 'shipping-rates', 'file' => UploadedFile::fake()->createWithContent('rates.csv', $csv)])->assertOk();
        $this->post(route('admin.import.commit'))->assertRedirect();
        $this->assertTrue(ShippingRate::where(['courier' => 'JNE', 'city' => 'Bandung'])->exists());
    }

    public function test_generated_xlsx_can_be_read_back_without_losing_values(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dthree-xlsx-test-');
        $columns = ['name', 'slug', 'active'];
        app(SimpleXlsx::class)->create($path, 'Kategori', $columns, [['Abaya', 'abaya', 1]]);

        $this->assertSame([['name' => 'Abaya', 'slug' => 'abaya', 'active' => '1']], app(SimpleXlsx::class)->read($path, $columns));
        unlink($path);
    }

    public function test_bulk_stock_adjustment_cannot_reduce_stock_below_reservation(): void
    {
        $category = Category::create(['name' => 'Stok', 'slug' => 'stok']);
        $series = Series::create(['category_id' => $category->id, 'name' => 'Stok', 'slug' => 'stok', 'active' => true]);
        $variant = Variant::create(['series_id' => $series->id, 'sku' => 'STOCK-TEST', 'color' => 'Black', 'size' => 'M', 'price' => 100000, 'stock' => 5, 'reserved' => 4, 'weight' => 500, 'fulfillment' => 'ready_stock']);
        $columns = app(BulkImportService::class)->definitions()['stock-adjustments']['columns'];
        $csv = implode(',', $columns)."\n{$variant->sku},-2,Koreksi opname\n";

        $this->actingAs($this->admin)->post(route('admin.import.preview'), ['type' => 'stock-adjustments', 'file' => UploadedFile::fake()->createWithContent('stock.csv', $csv)])->assertOk()->assertSee('Stok akhir tidak boleh');
        $this->post(route('admin.import.commit'))->assertSessionHasErrors();
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_cod_exclusions_and_loyalty_tiers_can_be_managed_in_bulk(): void
    {
        $service = app(BulkImportService::class);
        $codColumns = $service->definitions()['cod-exclusions']['columns'];
        $cod = implode(',', $codColumns)."\nphone,+62 812-3456-7890\n";
        $this->actingAs($this->admin)->post(route('admin.import.preview'), ['type' => 'cod-exclusions', 'file' => UploadedFile::fake()->createWithContent('cod.csv', $cod)])->assertOk();
        $this->post(route('admin.import.commit'))->assertRedirect();
        $this->assertContains('6281234567890', Setting::get('cod_blocked_phones'));

        $tierColumns = $service->definitions()['loyalty-tiers']['columns'];
        $tiers = implode(',', $tierColumns)."\nPlatinum,5000,Prioritas layanan,10\n";
        $this->post(route('admin.import.preview'), ['type' => 'loyalty-tiers', 'mode' => 'upsert', 'file' => UploadedFile::fake()->createWithContent('tiers.csv', $tiers)])->assertOk();
        $this->post(route('admin.import.commit'))->assertRedirect();
        $this->assertSame('Platinum', collect(Setting::get('loyalty_tiers'))->firstWhere('minimum', 5000)['name']);
    }
}
