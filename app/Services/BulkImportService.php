<?php

namespace App\Services;

use App\Models\BundleComponent;
use App\Models\BusinessAccount;
use App\Models\BusinessOrderRule;
use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\CuratedLink;
use App\Models\MeasurementTemplate;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\PriceGroup;
use App\Models\ProductMedia;
use App\Models\Series;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\StockMovement;
use App\Models\StoreLocation;
use App\Models\User;
use App\Models\Variant;
use App\Models\VariantPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BulkImportService
{
    public const MAXIMUM_ROWS = 1000;

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return [
            'curated-links' => $this->definition('Menu koleksi kurasi', 'content.write', CuratedLink::class,
                ['section', 'title', 'url', 'eyebrow', 'image', 'sort_order', 'active'],
                ['section' => 'required|in:fit,promo', 'title' => 'required|string|max:150', 'url' => 'required|string|max:500', 'eyebrow' => 'nullable|string|max:100', 'image' => 'nullable|string|max:255', 'sort_order' => 'required|integer|between:0,10000', 'active' => 'required|in:0,1'], ['section', 'title']),
            'categories' => $this->definition('Kategori', 'catalog.write', Category::class,
                ['name', 'slug', 'description', 'seo_title', 'seo_description', 'active'],
                ['name' => 'required|string|max:100', 'slug' => 'required|alpha_dash|max:100', 'description' => 'nullable|string|max:5000', 'seo_title' => 'nullable|string|max:200', 'seo_description' => 'nullable|string|max:300', 'active' => 'required|in:0,1'], ['slug']),
            'series' => $this->definition('Series / produk', 'catalog.write', Series::class,
                ['category_slug', 'name', 'slug', 'description', 'material', 'care', 'size_guide', 'image', 'compare_at_price', 'seo_title', 'seo_description', 'uses_product_type', 'featured', 'active'],
                ['category_slug' => 'required|exists:categories,slug', 'name' => 'required|string|max:150', 'slug' => 'required|alpha_dash|max:150', 'description' => 'nullable|string|max:10000', 'material' => 'nullable|string|max:2000', 'care' => 'nullable|string|max:2000', 'size_guide' => 'nullable|string|max:5000', 'image' => 'nullable|string|max:255', 'compare_at_price' => 'nullable|integer|between:1,100000000', 'seo_title' => 'nullable|string|max:200', 'seo_description' => 'nullable|string|max:300', 'uses_product_type' => 'required|in:0,1', 'featured' => 'required|in:0,1', 'active' => 'required|in:0,1'], ['slug']),
            'variants' => $this->definition('SKU & varian', 'catalog.write', Variant::class,
                ['series_slug', 'sku', 'product_type', 'color', 'size', 'price', 'stock', 'low_stock', 'weight', 'fulfillment', 'measurement_template_name', 'lead_time', 'active'],
                ['series_slug' => 'required|exists:series,slug', 'sku' => 'required|regex:/^[A-Za-z0-9._-]+$/|max:80', 'product_type' => 'nullable|string|max:100', 'color' => 'required|string|max:80', 'size' => 'required|string|max:40', 'price' => 'required|integer|between:1,100000000', 'stock' => 'required|integer|between:0,1000000', 'low_stock' => 'required|integer|between:0,100000', 'weight' => 'required|integer|between:1,100000', 'fulfillment' => 'required|in:ready_stock,pre_order,custom_made,bundle', 'measurement_template_name' => 'nullable|string|max:120', 'lead_time' => 'nullable|string|max:150', 'active' => 'required|in:0,1'], ['sku']),
            'stock-adjustments' => $this->definition('Penyesuaian stok', 'inventory.write', null,
                ['sku', 'stock_delta', 'reason'], ['sku' => 'required|exists:variants,sku', 'stock_delta' => 'required|integer|between:-1000000,1000000|not_in:0', 'reason' => 'required|string|max:255'], ['variant_id']),
            'product-media' => $this->definition('Galeri produk', 'catalog.write', ProductMedia::class,
                ['series_slug', 'sku', 'color', 'image', 'alt_text', 'focal_x', 'focal_y', 'sort_order', 'active'],
                ['series_slug' => 'required|exists:series,slug', 'sku' => 'nullable|exists:variants,sku', 'color' => 'nullable|string|max:80', 'image' => 'required|string|max:255', 'alt_text' => 'required|string|max:200', 'focal_x' => 'nullable|integer|between:0,100', 'focal_y' => 'nullable|integer|between:0,100', 'sort_order' => 'required|integer|between:0,10000', 'active' => 'required|in:0,1'], ['image']),
            'bundle-components' => $this->definition('Komponen bundle', 'catalog.write', BundleComponent::class,
                ['bundle_sku', 'component_sku', 'quantity'], ['bundle_sku' => 'required|exists:variants,sku', 'component_sku' => 'required|different:bundle_sku|exists:variants,sku', 'quantity' => 'required|integer|between:1,1000'], ['bundle_variant_id', 'component_variant_id']),
            'measurement-templates' => $this->definition('Template ukuran custom', 'catalog.write', MeasurementTemplate::class,
                ['name', 'product_type', 'pricing_mode', 'surcharge', 'lead_time', 'fields_json', 'policy', 'active'],
                ['name' => 'required|string|max:120', 'product_type' => 'nullable|string|max:100', 'pricing_mode' => 'required|in:fixed,quote', 'surcharge' => 'required|integer|between:0,100000000', 'lead_time' => 'required|string|max:150', 'fields_json' => 'required|json|max:20000', 'policy' => 'nullable|string|max:5000', 'active' => 'required|in:0,1'], ['name']),
            'price-groups' => $this->definition('Grup harga bisnis', 'catalog.write', PriceGroup::class,
                ['name', 'slug', 'business_type', 'minimum_order_quantity', 'minimum_order_amount', 'discount_percent', 'active'],
                ['name' => 'required|string|max:100', 'slug' => 'required|alpha_dash|max:100', 'business_type' => 'required|in:reseller,dropshipper,wholesale', 'minimum_order_quantity' => 'required|integer|between:1,10000', 'minimum_order_amount' => 'required|integer|between:0,1000000000', 'discount_percent' => 'required|integer|between:0,100', 'active' => 'required|in:0,1'], ['slug']),
            'business-order-rules' => $this->definition('Minimum pembelian B2B', 'catalog.write', BusinessOrderRule::class,
                ['price_group_slug', 'target_type', 'target_key', 'minimum_quantity', 'minimum_amount', 'active'],
                ['price_group_slug' => 'required|exists:price_groups,slug', 'target_type' => 'required|in:category,series,variant', 'target_key' => 'required|string|max:150', 'minimum_quantity' => 'required|integer|between:0,10000', 'minimum_amount' => 'required|integer|between:0,1000000000', 'active' => 'required|in:0,1'], ['price_group_id', 'target_type', 'target_id']),
            'variant-prices' => $this->definition('Harga bertingkat B2B', 'catalog.write', VariantPrice::class,
                ['sku', 'price_group_slug', 'minimum_quantity', 'price', 'active'],
                ['sku' => 'required|exists:variants,sku', 'price_group_slug' => 'required|exists:price_groups,slug', 'minimum_quantity' => 'required|integer|between:1,10000', 'price' => 'required|integer|between:1,100000000', 'active' => 'required|in:0,1'], ['variant_id', 'price_group_id', 'minimum_quantity']),
            'business-accounts' => $this->definition('Penempatan akun bisnis', 'users.write', BusinessAccount::class,
                ['user_email', 'price_group_slug', 'sales_email', 'status', 'expires_at', 'credit_terms_days', 'credit_limit', 'commission_percent', 'review_notes'],
                ['user_email' => 'required|email|exists:users,email', 'price_group_slug' => 'required|exists:price_groups,slug', 'sales_email' => 'nullable|email|exists:users,email', 'status' => 'required|in:approved,suspended,rejected', 'expires_at' => 'nullable|date', 'credit_terms_days' => 'required|integer|between:0,365', 'credit_limit' => 'required|integer|between:0,100000000000', 'commission_percent' => 'required|numeric|between:0,100', 'review_notes' => 'nullable|string|max:2000'], ['user_id']),
            'shipping-rates' => $this->definition('Tarif pengiriman', 'settings.write', ShippingRate::class,
                ['courier', 'service', 'province', 'city', 'first_kg', 'extra_kg', 'estimate', 'cod', 'active'],
                ['courier' => 'required|in:POS,JNE', 'service' => 'required|string|max:80', 'province' => 'required|string|max:100', 'city' => 'required|string|max:100', 'first_kg' => 'required|integer|between:0,10000000', 'extra_kg' => 'required|integer|between:0,10000000', 'estimate' => 'required|string|max:100', 'cod' => 'required|in:0,1', 'active' => 'required|in:0,1'], ['courier', 'service', 'province', 'city']),
            'coupons' => $this->definition('Voucher & promosi', 'promotions.write', Coupon::class,
                ['code', 'type', 'value', 'minimum', 'maximum', 'usage_limit', 'segment', 'price_group_slug', 'target_type', 'target_key', 'combinable', 'priority', 'per_user_limit', 'starts_at', 'ends_at', 'active'],
                ['code' => 'required|alpha_dash|max:40', 'type' => 'required|in:fixed,percent,shipping', 'value' => 'required|integer|between:1,100000000', 'minimum' => 'required|integer|between:0,100000000', 'maximum' => 'nullable|integer|between:0,100000000', 'usage_limit' => 'required|integer|between:1,1000000', 'segment' => 'required|in:all,guest,customer,business', 'price_group_slug' => 'nullable|exists:price_groups,slug', 'target_type' => 'required|in:all,category,series,variant', 'target_key' => 'nullable|string|max:150', 'combinable' => 'required|in:0,1', 'priority' => 'required|integer|between:0,10000', 'per_user_limit' => 'nullable|integer|between:1,10000', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'active' => 'required|in:0,1'], ['code']),
            'cod-exclusions' => $this->definition('Pengecualian COD', 'settings.write', null,
                ['kind', 'value'], ['kind' => 'required|in:phone,sku,category', 'value' => 'required|string|max:150'], ['kind', 'value']),
            'loyalty-tiers' => $this->definition('Level loyalty', 'settings.write', null,
                ['name', 'minimum_points', 'benefit', 'value_bonus_percent'], ['name' => 'required|string|max:100', 'minimum_points' => 'required|integer|between:0,100000000', 'benefit' => 'nullable|string|max:500', 'value_bonus_percent' => 'required|integer|between:0,100'], ['minimum_points']),
            'content' => $this->definition('Konten website', 'content.write', ContentEntry::class,
                ['type', 'placement', 'title', 'slug', 'summary', 'body', 'template', 'blocks_json', 'image', 'link_url', 'link_label', 'seo_title', 'seo_description', 'sort_order', 'editorial_status', 'review_note', 'starts_at', 'ends_at', 'active'],
                ['type' => 'required|in:banner,home_section,menu,faq,journal,lookbook,campaign', 'placement' => 'required|in:both,desktop,mobile', 'title' => 'required|string|max:200', 'slug' => 'required|alpha_dash|max:180', 'summary' => 'nullable|string|max:2000', 'body' => 'nullable|string|max:50000', 'template' => 'required|in:standard,wide,landing', 'blocks_json' => 'nullable|json|max:50000', 'image' => 'nullable|string|max:255', 'link_url' => 'nullable|string|max:500', 'link_label' => 'nullable|string|max:100', 'seo_title' => 'nullable|string|max:200', 'seo_description' => 'nullable|string|max:300', 'sort_order' => 'required|integer|between:0,10000', 'editorial_status' => 'required|in:draft,review,approved', 'review_note' => 'nullable|string|max:1000', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at', 'active' => 'required|in:0,1'], ['slug']),
            'pages' => $this->definition('Halaman & kebijakan', 'content.write', Page::class,
                ['title', 'slug', 'body', 'template', 'blocks_json', 'seo_title', 'seo_description', 'editorial_status', 'review_note', 'starts_at', 'ends_at', 'active'],
                ['title' => 'required|string|max:150', 'slug' => 'required|alpha_dash|max:150', 'body' => 'required|string|max:50000', 'template' => 'required|in:standard,wide,landing', 'blocks_json' => 'nullable|json|max:50000', 'seo_title' => 'nullable|string|max:200', 'seo_description' => 'nullable|string|max:300', 'editorial_status' => 'required|in:draft,review,approved', 'review_note' => 'nullable|string|max:1000', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at', 'active' => 'required|in:0,1'], ['slug']),
            'newsletter' => $this->definition('Pelanggan newsletter', 'content.write', NewsletterSubscriber::class,
                ['email', 'name', 'source', 'subscribed_at', 'active'],
                ['email' => 'required|email|max:150', 'name' => 'nullable|string|max:100', 'source' => 'required|string|max:80', 'subscribed_at' => 'required|date', 'active' => 'required|in:0,1'], ['email']),
            'store-locations' => $this->definition('Lokasi toko', 'content.write', StoreLocation::class,
                ['name', 'address', 'city', 'map_url', 'hours', 'sort_order', 'active'],
                ['name' => 'required|string|max:150', 'address' => 'required|string|max:1000', 'city' => 'nullable|string|max:100', 'map_url' => 'nullable|url|max:500', 'hours' => 'nullable|string|max:150', 'sort_order' => 'required|integer|between:0,10000', 'active' => 'required|in:0,1'], ['name', 'city']),
        ];
    }

    /** @return array<string, mixed> */
    private function definition(string $label, string $permission, ?string $model, array $columns, array $rules, array $keys): array
    {
        return compact('label', 'permission', 'model', 'columns', 'rules', 'keys');
    }

    /** @return array{0: array<int, array<string, mixed>>, 1: array<int, array<int, string>>} */
    public function validateRows(string $type, array $rows, string $mode): array
    {
        $definition = $this->definitions()[$type];
        $clean = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $index => $input) {
            $input = array_map(fn ($value) => trim((string) $value), $input);
            foreach (['sku', 'bundle_sku', 'component_sku', 'code'] as $uppercase) {
                if (array_key_exists($uppercase, $input)) {
                    $input[$uppercase] = strtoupper($input[$uppercase]);
                }
            }
            $messages = Validator::make($input, $definition['rules'])->errors()->all();
            $row = $this->transform($type, $input, $messages);
            $identity = $this->identity($definition, $row);
            if (isset($seen[$identity])) {
                $messages[] = 'Kunci data duplikat di dalam file.';
            }
            $seen[$identity] = true;
            $existing = $this->existing($definition, $row);
            if ($mode === 'create' && $existing) {
                $messages[] = 'Data dengan kunci yang sama sudah tersedia.';
            }
            if ($type === 'business-accounts' && ! $existing) {
                $messages[] = 'Akun bisnis belum pernah diajukan dan tidak dapat dibuat melalui import.';
            }
            if ($type === 'variants') {
                $this->validateVariant($row, $existing, $messages);
            }
            if ($messages) {
                $errors[$index + 2] = array_values(array_unique($messages));
            } else {
                $clean[] = $row;
            }
        }
        if (! $rows) {
            $errors[1] = ['File tidak berisi data.'];
        }

        return [$clean, $errors];
    }

    public function commit(string $type, array $rows, string $mode, int $userId): int
    {
        [$clean, $errors] = $this->validateRows($type, $rows, $mode);
        if ($errors) {
            throw ValidationException::withMessages(['file' => 'Data berubah. Buat pratinjau ulang.']);
        }
        $definition = $this->definitions()[$type];

        return DB::transaction(function () use ($clean, $definition, $mode, $type, $userId) {
            foreach ($clean as $row) {
                if ($type === 'stock-adjustments') {
                    $variant = Variant::whereKey($row['variant_id'])->lockForUpdate()->firstOrFail();
                    $finalStock = $variant->stock + (int) $row['stock_delta'];
                    if ($finalStock < $variant->reserved) {
                        throw ValidationException::withMessages(['file' => 'Stok berubah setelah pratinjau. Buat pratinjau ulang.']);
                    }
                    $variant->update(['stock' => $finalStock]);
                    StockMovement::create(['variant_id' => $variant->id, 'user_id' => $userId, 'stock_delta' => $row['stock_delta'], 'reserved_delta' => 0, 'reason' => $row['reason']]);

                    continue;
                }
                if ($type === 'cod-exclusions') {
                    $setting = match ($row['kind']) {
                        'phone' => 'cod_blocked_phones',
                        'sku' => 'cod_excluded_skus',
                        'category' => 'cod_excluded_categories',
                    };
                    Setting::put($setting, collect(Setting::get($setting, []))->push($row['value'])->unique()->values()->all());

                    continue;
                }
                if ($type === 'loyalty-tiers') {
                    $tiers = collect(Setting::get('loyalty_tiers', []))->reject(fn (array $tier) => (int) ($tier['minimum'] ?? -1) === (int) $row['minimum_points']);
                    $tiers->push(['name' => $row['name'], 'minimum' => (int) $row['minimum_points'], 'benefit' => $row['benefit'], 'value_bonus_percent' => (int) $row['value_bonus_percent']]);
                    Setting::put('loyalty_tiers', $tiers->sortBy('minimum')->values()->all());

                    continue;
                }

                $existing = $this->existing($definition, $row, true);
                if ($type === 'business-accounts') {
                    $row['reviewed_by'] = $userId;
                    $row['reviewed_at'] = now();
                }
                $oldStock = $type === 'variants' ? ($existing?->stock ?? 0) : 0;
                $record = $existing ?: new $definition['model'];
                $record->fill($row)->save();
                if ($type === 'variants' && ($delta = $record->stock - $oldStock)) {
                    StockMovement::create(['variant_id' => $record->id, 'user_id' => $userId, 'stock_delta' => $delta, 'reserved_delta' => 0, 'reason' => $existing ? 'Penyesuaian stok import upsert' : 'Stok awal import']);
                }
            }
            Audit::record('bulk-import.completed', $type.':'.$mode, null, ['rows' => count($clean)]);

            return count($clean);
        });
    }

    /** @return array<int, array<int, int|string|null>> */
    public function exportRows(string $type): array
    {
        $definition = $this->definitions()[$type];
        if ($type === 'stock-adjustments') {
            return [];
        }
        if ($type === 'cod-exclusions') {
            return collect([
                'phone' => Setting::get('cod_blocked_phones', []),
                'sku' => Setting::get('cod_excluded_skus', []),
                'category' => Setting::get('cod_excluded_categories', []),
            ])->flatMap(fn (array $values, string $kind) => collect($values)->map(fn (string $value) => [$kind, $value]))->values()->all();
        }
        if ($type === 'loyalty-tiers') {
            return collect(Setting::get('loyalty_tiers', []))->map(fn (array $tier) => [$tier['name'], $tier['minimum'], $tier['benefit'] ?? '', $tier['value_bonus_percent'] ?? 0])->values()->all();
        }

        return $definition['model']::query()->orderBy('id')->get()->map(fn (Model $model) => array_values($this->exportRow($type, $model)))->all();
    }

    /** @param array<int, string> $messages */
    private function transform(string $type, array $input, array &$messages): array
    {
        $row = $this->nullable($input);
        foreach (['active', 'featured', 'uses_product_type', 'cod', 'combinable'] as $boolean) {
            if (array_key_exists($boolean, $row)) {
                $row[$boolean] = (bool) (int) $row[$boolean];
            }
        }
        if (isset($row['series_slug'])) {
            $row['series_id'] = Series::where('slug', $row['series_slug'])->value('id');
            unset($row['series_slug']);
        }
        if (isset($input['category_slug'])) {
            $row['category_id'] = Category::where('slug', $input['category_slug'])->value('id');
            unset($row['category_slug']);
        }
        if (array_key_exists('measurement_template_name', $input)) {
            $row['measurement_template_id'] = $input['measurement_template_name'] === '' ? null : MeasurementTemplate::where('name', $input['measurement_template_name'])->value('id');
            if ($input['measurement_template_name'] !== '' && ! $row['measurement_template_id']) {
                $messages[] = 'Template ukuran tidak ditemukan.';
            }
            unset($row['measurement_template_name']);
        }
        if (isset($input['sku']) && in_array($type, ['product-media', 'variant-prices', 'stock-adjustments'], true)) {
            $row['variant_id'] = Variant::where('sku', strtoupper($input['sku']))->value('id');
            unset($row['sku']);
        }
        if ($type === 'stock-adjustments' && $variant = Variant::find($row['variant_id'] ?? null)) {
            if ($variant->stock + (int) $row['stock_delta'] < $variant->reserved) {
                $messages[] = 'Stok akhir tidak boleh lebih kecil dari jumlah yang sudah direservasi.';
            }
        }
        if ($type === 'product-media' && ! empty($row['variant_id']) && Variant::whereKey($row['variant_id'])->value('series_id') !== $row['series_id']) {
            $messages[] = 'SKU galeri harus berasal dari series yang dipilih.';
        }
        if ($type === 'product-media') {
            $row['focal_x'] = (int) ($row['focal_x'] ?? 50);
            $row['focal_y'] = (int) ($row['focal_y'] ?? 30);
        }
        if ($type === 'series' && ! empty($row['compare_at_price'])) {
            $minimumPrice = Series::where('slug', $input['slug'])->first()?->variants()->min('price');
            if ($minimumPrice && (int) $row['compare_at_price'] <= $minimumPrice) {
                $messages[] = 'Harga coret harus lebih besar dari harga jual terendah produk.';
            }
        }
        if ($type === 'variants' && ($series = Series::find($row['series_id'] ?? null))) {
            $minimumPrice = min(array_filter([$series->variants()->min('price'), (int) $row['price']]) ?: [(int) $row['price']]);
            if ($series->compare_at_price && $series->compare_at_price <= $minimumPrice) {
                $messages[] = 'Harga coret series harus lebih besar dari harga jual terendah produk.';
            }
        }
        if ($type === 'curated-links') {
            $url = (string) $row['url'];
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (! str_starts_with($url, '/') && ! str_starts_with($url, '#') && ! in_array($scheme, ['http', 'https'], true)) {
                $messages[] = 'Tautan kurasi harus berupa path internal atau URL http/https.';
            }
        }
        if (in_array($type, ['series', 'product-media', 'content', 'curated-links'], true) && ! empty($row['image'])) {
            if (str_contains($row['image'], '..') || str_starts_with($row['image'], '/') || ! Storage::disk('public')->exists($row['image'])) {
                $messages[] = 'Path gambar tidak ditemukan di media publik.';
            }
        }
        if ($type === 'bundle-components') {
            $row['bundle_variant_id'] = Variant::where('sku', $input['bundle_sku'])->value('id');
            $row['component_variant_id'] = Variant::where('sku', $input['component_sku'])->value('id');
            unset($row['bundle_sku'], $row['component_sku']);
            if (Variant::whereKey($row['bundle_variant_id'])->value('fulfillment') !== 'bundle') {
                $messages[] = 'SKU bundle harus menggunakan fulfillment bundle.';
            }
        }
        if (isset($input['price_group_slug'])) {
            $row['price_group_id'] = $input['price_group_slug'] === '' ? null : PriceGroup::where('slug', $input['price_group_slug'])->value('id');
            unset($row['price_group_slug']);
        }
        if ($type === 'business-accounts') {
            $row['user_id'] = User::where('email', $input['user_email'])->value('id');
            $row['sales_user_id'] = $input['sales_email'] === '' ? null : User::where('email', $input['sales_email'])->value('id');
            unset($row['user_email'], $row['sales_email']);
            if ($row['sales_user_id'] && User::whereKey($row['sales_user_id'])->value('role') === 'customer') {
                $messages[] = 'Sales penanggung jawab harus merupakan akun staf.';
            }
        }
        if ($type === 'business-order-rules') {
            $row['target_id'] = $this->targetId($input['target_type'], $input['target_key']);
            unset($row['target_key']);
            if (! $row['target_id']) {
                $messages[] = 'Target kategori, series, atau SKU tidak ditemukan.';
            }
        }
        if ($type === 'coupons') {
            $row['target_id'] = $input['target_type'] === 'all' ? null : $this->targetId($input['target_type'], (string) ($input['target_key'] ?? ''));
            unset($row['target_key']);
            if ($input['target_type'] !== 'all' && ! $row['target_id']) {
                $messages[] = 'Target voucher tidak ditemukan.';
            }
        }
        if ($type === 'cod-exclusions') {
            $row['value'] = match ($input['kind']) {
                'phone' => preg_replace('/\D+/', '', $input['value']),
                'sku' => strtoupper($input['value']),
                default => strtolower($input['value']),
            };
            if ($input['kind'] === 'phone' && ! preg_match('/^\d{8,20}$/', $row['value'])) {
                $messages[] = 'Nomor telepon COD harus berisi 8 sampai 20 digit.';
            }
            if ($input['kind'] === 'sku' && ! Variant::where('sku', $row['value'])->exists()) {
                $messages[] = 'SKU pengecualian COD tidak ditemukan.';
            }
            if ($input['kind'] === 'category' && ! Category::where('slug', $row['value'])->exists()) {
                $messages[] = 'Kategori pengecualian COD tidak ditemukan.';
            }
        }
        foreach (['fields_json' => 'fields', 'blocks_json' => 'blocks'] as $source => $target) {
            if (array_key_exists($source, $input)) {
                $row[$target] = $input[$source] === '' ? null : json_decode($input[$source], true);
                unset($row[$source]);
            }
        }

        return $row;
    }

    private function targetId(string $type, string $key): ?int
    {
        return match ($type) {
            'category' => Category::where('slug', $key)->value('id'),
            'series' => Series::where('slug', $key)->value('id'),
            'variant' => Variant::where('sku', strtoupper($key))->value('id'),
            default => null,
        };
    }

    private function validateVariant(array $row, ?Model $existing, array &$messages): void
    {
        $series = Series::find($row['series_id'] ?? null);
        if (! $series) {
            return;
        }
        if ($series->uses_product_type !== ! empty($row['product_type'])) {
            $messages[] = 'Jenis produk tidak sesuai struktur series.';
        }
        if ($row['fulfillment'] === 'custom_made' && empty($row['measurement_template_id'])) {
            $messages[] = 'Varian custom wajib memiliki template ukuran.';
        }
        $combination = hash('sha256', mb_strtolower(implode('|', [$series->id, $row['product_type'] ?? '', $row['color'] ?? '', $row['size'] ?? ''])));
        if (Variant::where('combination_key', $combination)->when($existing, fn (Builder $query) => $query->whereKeyNot($existing->id))->exists()) {
            $messages[] = 'Kombinasi varian sudah tersedia.';
        }
    }

    private function existing(array $definition, array $row, bool $lock = false): ?Model
    {
        if (! $definition['model']) {
            return null;
        }
        $query = $definition['model']::query();
        foreach ($definition['keys'] as $key) {
            $query->where($key, $row[$key] ?? null);
        }
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function identity(array $definition, array $row): string
    {
        return implode('|', array_map(fn (string $key) => mb_strtolower((string) ($row[$key] ?? '')), $definition['keys']));
    }

    private function nullable(array $row): array
    {
        return array_map(fn ($value) => $value === '' ? null : $value, $row);
    }

    /** @return array<string, int|string|null> */
    private function exportRow(string $type, Model $model): array
    {
        $row = $model->toArray();
        $definition = $this->definitions()[$type];
        $mapped = [];
        foreach ($definition['columns'] as $column) {
            $mapped[$column] = match ($column) {
                'category_slug' => Category::whereKey($model->category_id)->value('slug'),
                'series_slug' => Series::whereKey($model->series_id)->value('slug'),
                'sku' => Variant::whereKey($model->variant_id)->value('sku'),
                'bundle_sku' => Variant::whereKey($model->bundle_variant_id)->value('sku'),
                'component_sku' => Variant::whereKey($model->component_variant_id)->value('sku'),
                'measurement_template_name' => MeasurementTemplate::whereKey($model->measurement_template_id)->value('name'),
                'price_group_slug' => PriceGroup::whereKey($model->price_group_id)->value('slug'),
                'user_email' => User::whereKey($model->user_id)->value('email'),
                'sales_email' => User::whereKey($model->sales_user_id)->value('email'),
                'target_key' => $this->targetKey($model->target_type, $model->target_id),
                'fields_json' => json_encode($model->fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'blocks_json' => $model->blocks ? json_encode($model->blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                default => $row[$column] ?? null,
            };
            if (is_bool($mapped[$column])) {
                $mapped[$column] = (int) $mapped[$column];
            }
        }

        return $mapped;
    }

    private function targetKey(string $type, ?int $id): ?string
    {
        if (! $id) {
            return null;
        }

        return match ($type) {
            'category' => Category::whereKey($id)->value('slug'),
            'series' => Series::whereKey($id)->value('slug'),
            'variant' => Variant::whereKey($id)->value('sku'),
            default => null,
        };
    }
}
