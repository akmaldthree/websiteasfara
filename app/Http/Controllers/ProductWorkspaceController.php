<?php

namespace App\Http\Controllers;

use App\Models\BundleComponent;
use App\Models\Category;
use App\Models\MeasurementTemplate;
use App\Models\OrderItem;
use App\Models\Series;
use App\Models\StockMovement;
use App\Models\Variant;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $needsAction = fn ($query) => $query->where(function ($incomplete) {
            $incomplete->whereNull('description')->orWhere('description', '')->orWhereDoesntHave('variants')->orWhere(function ($photos) {
                $photos->whereNull('image')->whereDoesntHave('media');
            });
        });
        $query = Series::with(['category', 'media', 'variants:id,series_id,sku'])->withCount('variants')->withMin('variants', 'price')->withMax('variants', 'price')->withSum('variants', 'stock')->withSum('variants', 'reserved')->withSum('orderItems', 'quantity');
        if ($request->filled('q')) {
            $term = mb_substr(trim((string) $request->input('q')), 0, 100);
            $query->where(fn ($search) => $search->where('name', 'like', '%'.$term.'%')->orWhere('slug', 'like', '%'.$term.'%')->orWhereHas('variants', fn ($variants) => $variants->where('sku', 'like', '%'.$term.'%')));
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }
        if ($request->input('status') === 'live') {
            $query->where('active', true);
        } elseif ($request->input('status') === 'draft') {
            $query->where('active', false);
        } elseif ($request->input('status') === 'action') {
            $needsAction($query);
        } elseif ($request->input('status') === 'out') {
            $query->whereHas('variants')->whereDoesntHave('variants', fn ($variants) => $variants->whereRaw('stock - reserved > 0'));
        }
        if ($request->input('stock') === 'low') {
            $query->whereHas('variants', fn ($variants) => $variants->whereRaw('stock - reserved <= low_stock'));
        } elseif ($request->input('stock') === 'out') {
            $query->whereHas('variants', fn ($variants) => $variants->whereRaw('stock - reserved <= 0'));
        }

        return view('admin.products.index', [
            'products' => $query->latest()->paginate(20)->withQueryString(),
            'categories' => Category::orderBy('name')->get(),
            'statusCounts' => [
                'all' => Series::count(), 'live' => Series::where('active', true)->count(), 'action' => Series::where($needsAction)->count(),
                'out' => Series::whereHas('variants')->whereDoesntHave('variants', fn ($variants) => $variants->whereRaw('stock - reserved > 0'))->count(), 'draft' => Series::where('active', false)->count(),
            ],
        ]);
    }

    public function bulkAction(Request $request)
    {
        $data = $request->validate(['product_ids' => 'required|array|min:1|max:100', 'product_ids.*' => 'required|integer|distinct|exists:series,id', 'action' => 'required|in:activate,deactivate,feature,unfeature']);
        $changes = match ($data['action']) {
            'activate' => ['active' => true], 'deactivate' => ['active' => false], 'feature' => ['featured' => true], 'unfeature' => ['featured' => false],
        };
        $products = Series::whereKey($data['product_ids'])->get();
        DB::transaction(function () use ($products, $changes, $data) {
            foreach ($products as $product) {
                $before = $product->only(array_keys($changes));
                $product->update($changes);
                Audit::record('products.bulk_'.$data['action'], 'series:'.$product->id, $before, $changes);
            }
        });

        return back()->with('success', $products->count().' produk berhasil diperbarui.');
    }

    public function create()
    {
        return view('admin.products.create', ['categories' => Category::where('active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->productData($request);
        $product = Series::create($data);
        Audit::record('product.created', 'series:'.$product->id, null, $product->toArray());

        return redirect()->route('admin.products.edit', $product)->with('success', 'Produk dibuat. Lanjutkan dengan menambahkan varian dan foto.');
    }

    public function edit(Series $series)
    {
        $series->load(['category', 'variants.measurementTemplate', 'media.variant']);

        return view('admin.products.edit', [
            'product' => $series,
            'categories' => Category::orderBy('name')->get(),
            'measurementTemplates' => MeasurementTemplate::where('active', true)->orderBy('name')->get(),
            'movements' => StockMovement::whereIn('variant_id', $series->variants->pluck('id'))->latest()->take(15)->get(),
        ]);
    }

    public function update(Request $request, Series $series)
    {
        $data = $this->productData($request, $series);
        if ($series->variants()->exists() && (bool) $data['uses_product_type'] !== $series->uses_product_type) {
            throw ValidationException::withMessages(['uses_product_type' => 'Struktur jenis produk tidak dapat diubah setelah varian dibuat.']);
        }
        $before = $series->getAttributes();
        $series->update($data);
        Audit::record('product.updated', 'series:'.$series->id, $before, $series->getAttributes());

        return redirect()->route('admin.products.edit', ['series' => $series, 'section' => $request->input('section', 'basic')])->with('success', 'Informasi produk berhasil disimpan.');
    }

    public function saveVariants(Request $request, Series $series)
    {
        $data = $request->validate([
            'variants' => 'required|array|min:1|max:100',
            'variants.*.id' => 'required|integer|distinct',
            'variants.*.sku' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
            'variants.*.product_type' => 'nullable|string|max:100',
            'variants.*.color' => 'required|string|max:80',
            'variants.*.size' => 'required|string|max:40',
            'variants.*.price' => 'required|integer|min:1|max:100000000',
            'variants.*.weight' => 'required|integer|min:1|max:100000',
            'variants.*.low_stock' => 'required|integer|min:0|max:100000',
            'variants.*.fulfillment' => 'required|in:ready_stock,pre_order,custom_made,bundle',
            'variants.*.measurement_template_id' => 'nullable|integer|exists:measurement_templates,id',
            'variants.*.lead_time' => 'nullable|string|max:150',
            'variants.*.active' => 'required|boolean',
            'variants.*.stock_delta' => 'nullable|integer|between:-1000000,1000000',
            'variants.*.stock_reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $series) {
            foreach ($data['variants'] as $index => $row) {
                $variant = $series->variants()->lockForUpdate()->findOrFail($row['id']);
                $this->assertVariant($series, $variant, $row, 'variants.'.$index.'.');
                $before = $variant->getAttributes();
                $delta = (int) ($row['stock_delta'] ?? 0);
                if ($delta !== 0 && mb_strlen(trim((string) ($row['stock_reason'] ?? ''))) < 5) {
                    throw ValidationException::withMessages(['variants.'.$index.'.stock_reason' => 'Isi alasan perubahan stok minimal 5 karakter.']);
                }
                if ($variant->stock + $delta < $variant->reserved) {
                    throw ValidationException::withMessages(['variants.'.$index.'.stock_delta' => 'Stok tidak boleh kurang dari jumlah yang direservasi.']);
                }
                $variant->fill($this->variantAttributes($row));
                if ($delta !== 0) {
                    $variant->stock += $delta;
                }
                $variant->save();
                if ($delta !== 0) {
                    StockMovement::create(['variant_id' => $variant->id, 'user_id' => auth()->id(), 'stock_delta' => $delta, 'reserved_delta' => 0, 'reason' => trim($row['stock_reason'])]);
                }
                Audit::record('variant.workspace_updated', 'variant:'.$variant->id, $before, $variant->getAttributes());
            }
        }, 3);

        return redirect()->route('admin.products.edit', ['series' => $series, 'section' => 'variants'])->with('success', 'Semua perubahan varian dan stok berhasil disimpan.');
    }

    public function addVariant(Request $request, Series $series)
    {
        $row = $request->validate([
            'sku' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'], 'product_type' => 'nullable|string|max:100', 'color' => 'required|string|max:80', 'size' => 'required|string|max:40',
            'price' => 'required|integer|min:1|max:100000000', 'weight' => 'required|integer|min:1|max:100000', 'stock' => 'required|integer|min:0|max:1000000', 'low_stock' => 'required|integer|min:0|max:100000',
            'fulfillment' => 'required|in:ready_stock,pre_order,custom_made,bundle', 'measurement_template_id' => 'nullable|integer|exists:measurement_templates,id', 'lead_time' => 'nullable|string|max:150', 'active' => 'nullable|boolean',
        ]);
        $row['active'] = $request->boolean('active');
        $this->assertVariant($series, null, $row);
        $stock = (int) $row['stock'];
        unset($row['stock']);
        $variant = DB::transaction(function () use ($row, $stock, $series) {
            $variant = $series->variants()->create($this->variantAttributes($row) + ['stock' => $stock]);
            if ($stock > 0) {
                StockMovement::create(['variant_id' => $variant->id, 'user_id' => auth()->id(), 'stock_delta' => $stock, 'reserved_delta' => 0, 'reason' => 'Stok awal dari Product Workspace']);
            }
            Audit::record('variant.workspace_created', 'variant:'.$variant->id, null, $variant->toArray());

            return $variant;
        });

        return redirect()->route('admin.products.edit', ['series' => $series, 'section' => 'variants'])->with('success', 'Varian '.$variant->sku.' berhasil ditambahkan.');
    }

    public function generateVariants(Request $request, Series $series)
    {
        $data = $request->validate([
            'sku_prefix' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'], 'product_types' => 'nullable|string|max:1000', 'colors' => 'required|string|max:2000', 'sizes' => 'required|string|max:1000',
            'price' => 'required|integer|min:1|max:100000000', 'weight' => 'required|integer|min:1|max:100000', 'stock' => 'required|integer|min:0|max:1000000', 'low_stock' => 'required|integer|min:0|max:100000',
            'fulfillment' => 'required|in:ready_stock,pre_order', 'lead_time' => 'nullable|string|max:150',
        ]);
        $types = $series->uses_product_type ? $this->list($data['product_types'] ?? '') : [''];
        $colors = $this->list($data['colors']);
        $sizes = $this->list($data['sizes']);
        if (($series->uses_product_type && $types === []) || $colors === [] || $sizes === []) {
            throw ValidationException::withMessages(['colors' => 'Isi semua daftar kombinasi yang diwajibkan.']);
        }
        $combinations = [];
        foreach ($types as $type) {
            foreach ($colors as $color) {
                foreach ($sizes as $size) {
                    $sku = strtoupper(implode('-', array_filter([$data['sku_prefix'], Str::slug($type), Str::slug($color), Str::slug($size)])));
                    $row = $data + compact('sku', 'color', 'size') + ['product_type' => $type, 'active' => true];
                    $this->assertVariant($series, null, $row);
                    $combinations[] = $row;
                }
            }
        }
        if (count($combinations) > 100) {
            throw ValidationException::withMessages(['colors' => 'Maksimal 100 kombinasi dalam satu proses.']);
        }
        DB::transaction(function () use ($combinations, $series) {
            foreach ($combinations as $row) {
                $variant = $series->variants()->create($this->variantAttributes($row) + ['stock' => $row['stock']]);
                if ($row['stock'] > 0) {
                    StockMovement::create(['variant_id' => $variant->id, 'user_id' => auth()->id(), 'stock_delta' => $row['stock'], 'reserved_delta' => 0, 'reason' => 'Stok awal dari generator varian']);
                }
                Audit::record('variant.workspace_created', 'variant:'.$variant->id, null, $variant->toArray());
            }
        });

        return redirect()->route('admin.products.edit', ['series' => $series, 'section' => 'variants'])->with('success', count($combinations).' kombinasi varian berhasil dibuat.');
    }

    public function uploadMedia(Request $request, Series $series)
    {
        $data = $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'alt_text' => 'nullable|string|max:160',
            'color' => 'nullable|string|max:80',
            'variant_id' => 'nullable|integer',
            'set_first_as_main' => 'nullable|boolean',
        ]);
        $variantId = $data['variant_id'] ?? null;
        if ($variantId && ! $series->variants()->whereKey($variantId)->exists()) {
            throw ValidationException::withMessages(['variant_id' => 'Varian yang dipilih bukan milik produk ini.']);
        }
        $order = ((int) $series->media()->max('sort_order')) + 1;
        $setFirstAsMain = $request->boolean('set_first_as_main');
        DB::transaction(function () use ($data, $order, $series, $variantId, $setFirstAsMain) {
            $firstMedia = null;
            foreach ($data['images'] as $image) {
                $path = $image->store('product-gallery', 'public');
                $media = $series->media()->create([
                    'image' => $path,
                    'alt_text' => $data['alt_text'] ?: $series->name.' - '.$image->getClientOriginalName(),
                    'color' => filled($data['color'] ?? null) ? trim($data['color']) : null,
                    'variant_id' => $variantId,
                    'sort_order' => $order++,
                    'active' => true,
                ]);
                $firstMedia ??= $media;
                Audit::record('product_media.workspace_created', 'product-media:'.$media->id, null, $media->toArray());
            }

            if ($setFirstAsMain && $firstMedia) {
                $series->update(['image' => $firstMedia->image]);
            }

        });

        return redirect()->route('admin.products.edit', ['series' => $series, 'section' => 'media'])->with('success', count($data['images']).' foto berhasil diunggah.');
    }

    public function manageMedia(Request $request, Series $series)
    {
        $data = $request->validate([
            'media' => 'required|array|min:1|max:100',
            'media.*.id' => 'required|integer|distinct',
            'media.*.alt_text' => 'required|string|max:200',
            'media.*.color' => 'nullable|string|max:80',
            'media.*.variant_id' => 'nullable|integer',
            'media.*.sort_order' => 'required|integer|min:0|max:10000',
            'media.*.active' => 'nullable|boolean',
            'media.*.delete' => 'nullable|boolean',
            'main_media_id' => 'nullable|integer',
        ]);
        $submittedIds = collect($data['media'])->pluck('id')->map(fn ($id) => (int) $id);
        $mediaById = $series->media()->whereKey($submittedIds)->get()->keyBy('id');
        if ($mediaById->count() !== $submittedIds->count()) {
            throw ValidationException::withMessages(['media' => 'Ada foto yang bukan milik produk ini. Muat ulang halaman lalu coba kembali.']);
        }
        $variantIds = $series->variants()->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($data['media'] as $index => $row) {
            if (! empty($row['variant_id']) && ! in_array((int) $row['variant_id'], $variantIds, true)) {
                throw ValidationException::withMessages(['media.'.$index.'.variant_id' => 'Varian yang dipilih bukan milik produk ini.']);
            }
        }
        $mainId = isset($data['main_media_id']) ? (int) $data['main_media_id'] : null;
        if ($mainId && ! $mediaById->has($mainId)) {
            throw ValidationException::withMessages(['main_media_id' => 'Foto utama yang dipilih tidak valid.']);
        }

        DB::transaction(function () use ($data, $mainId, $mediaById, $series) {
            $mainMedia = null;
            foreach ($data['media'] as $row) {
                $media = $mediaById[(int) $row['id']];
                $before = $media->getAttributes();
                if (! empty($row['delete'])) {
                    if ($mainId === $media->id) {
                        throw ValidationException::withMessages(['main_media_id' => 'Foto utama tidak dapat sekaligus dihapus. Pilih foto utama lain.']);
                    }
                    $media->delete();
                    Audit::record('product_media.workspace_deleted', 'product-media:'.$media->id, $before, null);

                    continue;
                }
                $media->update([
                    'alt_text' => trim($row['alt_text']),
                    'color' => filled($row['color'] ?? null) ? trim($row['color']) : null,
                    'variant_id' => ! empty($row['variant_id']) ? (int) $row['variant_id'] : null,
                    'sort_order' => (int) $row['sort_order'],
                    'active' => (bool) ($row['active'] ?? false),
                ]);
                if ($mainId === $media->id) {
                    $mainMedia = $media;
                }
                Audit::record('product_media.workspace_updated', 'product-media:'.$media->id, $before, $media->getAttributes());
            }
            if ($mainMedia) {
                $series->update(['image' => $mainMedia->image]);
            } elseif ($series->image && ! $series->media()->where('image', $series->image)->exists()) {
                $series->update(['image' => $series->media()->where('active', true)->orderBy('sort_order')->value('image')]);
            }
        });

        return redirect()->route('admin.products.edit', ['series' => $series, 'section' => 'media'])->with('success', 'Semua pengaturan foto berhasil disimpan.');
    }

    public function duplicate(Series $series)
    {
        $series->load(['variants.prices', 'variants.bundleComponents', 'media']);
        $copy = DB::transaction(function () use ($series) {
            $copy = $series->replicate();
            $copy->name = $series->name.' - Salinan';
            $copy->slug = $this->uniqueSlug($series->slug.'-salinan');
            $copy->active = false;
            $copy->featured = false;
            $copy->save();
            $map = [];
            foreach ($series->variants as $original) {
                $variant = $original->replicate();
                $variant->series_id = $copy->id;
                $variant->sku = $this->uniqueSku($original->sku.'-COPY');
                $variant->stock = 0;
                $variant->reserved = 0;
                $variant->active = false;
                $variant->save();
                $map[$original->id] = $variant;
                foreach ($original->prices as $price) {
                    $variant->prices()->create($price->only(['price_group_id', 'minimum_quantity', 'price', 'active']));
                }
            }
            foreach ($series->media as $originalMedia) {
                $media = $originalMedia->replicate();
                $media->series_id = $copy->id;
                $media->variant_id = $originalMedia->variant_id ? ($map[$originalMedia->variant_id]->id ?? null) : null;
                $media->active = false;
                $media->save();
            }
            foreach ($series->variants as $original) {
                foreach ($original->bundleComponents as $component) {
                    BundleComponent::create(['bundle_variant_id' => $map[$original->id]->id, 'component_variant_id' => $map[$component->component_variant_id]->id ?? $component->component_variant_id, 'quantity' => $component->quantity]);
                }
            }
            Audit::record('product.duplicated', 'series:'.$copy->id, ['source_id' => $series->id], $copy->toArray());

            return $copy;
        });

        return redirect()->route('admin.products.edit', $copy)->with('success', 'Produk berhasil diduplikat sebagai draft. SKU dan stok salinan sudah dibuat aman.');
    }

    private function productData(Request $request, ?Series $series = null): array
    {
        $data = $request->validate([
            'category_id' => 'required|integer|exists:categories,id', 'name' => 'required|string|max:150',
            'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique('series', 'slug')->ignore($series?->id)],
            'description' => 'nullable|string|max:10000', 'material' => 'nullable|string|max:2000', 'care' => 'nullable|string|max:2000', 'size_guide' => 'nullable|string|max:5000',
            'seo_title' => 'nullable|string|max:200', 'seo_description' => 'nullable|string|max:300', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'uses_product_type' => 'nullable|boolean', 'featured' => 'nullable|boolean', 'active' => 'nullable|boolean',
        ]);
        $data['uses_product_type'] = $request->boolean('uses_product_type');
        $data['featured'] = $request->boolean('featured');
        $data['active'] = $request->boolean('active');
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']);
        }

        return $data;
    }

    private function assertVariant(Series $series, ?Variant $variant, array &$row, string $prefix = ''): void
    {
        $row['sku'] = strtoupper(trim($row['sku']));
        $row['product_type'] = trim($row['product_type'] ?? '');
        $row['color'] = trim($row['color']);
        $row['size'] = trim($row['size']);
        if ($series->uses_product_type !== ($row['product_type'] !== '')) {
            throw ValidationException::withMessages([$prefix.'product_type' => 'Jenis produk harus sesuai struktur produk.']);
        }
        if ($row['fulfillment'] === 'custom_made' && empty($row['measurement_template_id'])) {
            throw ValidationException::withMessages([$prefix.'measurement_template_id' => 'Pilih template ukuran untuk produk custom.']);
        }
        if ($variant && OrderItem::where('variant_id', $variant->id)->exists()) {
            foreach (['sku', 'product_type', 'color', 'size', 'fulfillment'] as $identity) {
                if ((string) $variant->{$identity} !== (string) $row[$identity]) {
                    throw ValidationException::withMessages([$prefix.$identity => 'Identitas varian yang pernah dipesan harus dipertahankan. Nonaktifkan varian lama dan buat varian baru.']);
                }
            }
        }
        if (Variant::where('sku', $row['sku'])->when($variant, fn ($query) => $query->whereKeyNot($variant->id))->exists()) {
            throw ValidationException::withMessages([$prefix.'sku' => 'SKU sudah digunakan.']);
        }
        $hash = hash('sha256', mb_strtolower(implode('|', [$series->id, $row['product_type'], $row['color'], $row['size']])));
        if (Variant::where('combination_key', $hash)->when($variant, fn ($query) => $query->whereKeyNot($variant->id))->exists()) {
            throw ValidationException::withMessages([$prefix.'sku' => 'Kombinasi jenis, warna, dan ukuran sudah tersedia.']);
        }
    }

    private function variantAttributes(array $row): array
    {
        return [
            'sku' => $row['sku'], 'product_type' => $row['product_type'] ?? '', 'color' => $row['color'], 'size' => $row['size'], 'price' => $row['price'], 'weight' => $row['weight'],
            'low_stock' => $row['low_stock'], 'fulfillment' => $row['fulfillment'], 'measurement_template_id' => $row['fulfillment'] === 'custom_made' ? ($row['measurement_template_id'] ?? null) : null,
            'lead_time' => $row['lead_time'] ?? null, 'active' => (bool) $row['active'],
        ];
    }

    private function list(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        $candidate = $slug;
        $index = 2;
        while (Series::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$index++;
        }

        return $candidate;
    }

    private function uniqueSku(string $base): string
    {
        $base = mb_substr(strtoupper(preg_replace('/[^A-Za-z0-9._-]/', '-', $base)), 0, 72);
        $candidate = $base;
        $index = 2;
        while (Variant::where('sku', $candidate)->exists()) {
            $candidate = mb_substr($base, 0, 72).'-'.$index++;
        }

        return $candidate;
    }
}
