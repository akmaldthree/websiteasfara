<?php

namespace App\Http\Controllers;

use App\Models\AnalyticsEvent;
use App\Models\AuditLog;
use App\Models\BundleComponent;
use App\Models\BusinessAccount;
use App\Models\BusinessOrderRule;
use App\Models\Category;
use App\Models\CommissionEntry;
use App\Models\CommissionPayout;
use App\Models\ContentEntry;
use App\Models\ContentRevision;
use App\Models\CustomQuote;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProof;
use App\Models\PriceGroup;
use App\Models\ProductQuestion;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Role;
use App\Models\Series;
use App\Models\ServiceCase;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use App\Models\VariantPrice;
use App\Notifications\ProductQuestionAnswered;
use App\Services\AfterSalesService;
use App\Services\Audit;
use App\Services\CommissionPayoutService;
use App\Services\CustomProductService;
use App\Services\OrderWorkflow;
use App\Services\SystemHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function dashboard()
    {
        $paymentSlaHours = max(1, (int) Setting::get('payment_review_sla_hours', 4));

        return view('admin.dashboard', [
            'revenue' => max(0, Order::whereNotNull('paid_at')->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('total')
                - Refund::whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount')),
            'waiting' => Order::where('payment_status', 'review')->count(), 'cod' => Order::where('payment_method', 'cod')->where('status', 'waiting')->count(),
            'paymentOverdue' => Order::where('payment_status', 'review')->whereHas('proofs', fn ($query) => $query->where('created_at', '<=', now()->subHours($paymentSlaHours)))->count(),
            'paymentSlaHours' => $paymentSlaHours,
            'packing' => Order::where('status', 'confirmed')->where('fulfillment_status', 'processing')->count(),
            'low' => Variant::whereRaw('stock - reserved <= low_stock')->count(), 'recent' => Order::latest()->take(8)->get(),
        ]);
    }

    public function orders(Request $r)
    {
        $q = Order::query();
        if ($r->filled('q')) {
            $q->where('number', 'like', '%'.mb_substr($r->string('q'), 0, 50).'%');
        }
        foreach (['status', 'payment_status', 'payment_method', 'fulfillment_status'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->input($f));
            }
        }

        return view('admin.orders', ['orders' => $q->latest()->paginate(20)->withQueryString()]);
    }

    public function order(Order $order)
    {
        $order->load('items.productionMilestones', 'items.components', 'proofs', 'refunds', 'cases', 'events');

        return view('admin.order', compact('order'));
    }

    public function customQuotes(Request $request)
    {
        $query = CustomQuote::with(['user', 'variant.series']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('admin.custom-quotes', ['quotes' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function contentPreview(ContentEntry $contentEntry)
    {
        return view('store.content', ['entry' => $contentEntry, 'preview' => true]);
    }

    public function mediaLibrary()
    {
        return view('admin.media', ['assets' => MediaAsset::with('user')->latest()->paginate(30)]);
    }

    public function mediaUpload(Request $request)
    {
        $data = $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120', 'alt_text' => 'required|string|max:200']);
        $file = $request->file('image');
        $path = $file->store('cms-media/'.now()->format('Y/m'), 'public');
        $asset = MediaAsset::create(['user_id' => $request->user()->id, 'name' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'alt_text' => $data['alt_text']]);
        Audit::record('media.uploaded', 'media_asset:'.$asset->id, null, $asset->only('name', 'path', 'alt_text'));

        return back()->with('success', 'Gambar ditambahkan ke media library.');
    }

    public function mediaUpdate(Request $request, MediaAsset $asset)
    {
        $data = $request->validate(['alt_text' => 'required|string|max:200', 'active' => 'nullable|boolean']);
        $before = $asset->only('alt_text', 'active');
        $asset->update(['alt_text' => $data['alt_text'], 'active' => $request->boolean('active')]);
        Audit::record('media.updated', 'media_asset:'.$asset->id, $before, $asset->only('alt_text', 'active'));

        return back()->with('success', 'Media diperbarui.');
    }

    public function reviews(Request $request)
    {
        $query = Review::with(['user', 'variant.series', 'item.order']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $questions = ProductQuestion::with(['user', 'series', 'answeredBy'])->latest()->paginate(20, ['*'], 'questions_page')->withQueryString();

        return view('admin.reviews', ['reviews' => $query->latest()->paginate(20)->withQueryString(), 'questions' => $questions]);
    }

    public function reviewAction(Request $request, Review $review)
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'moderation_notes' => 'nullable|required_if:status,rejected|string|max:1000']);
        $before = $review->only('status', 'moderation_notes');
        $review->update($data + ['moderated_by' => $request->user()->id, 'moderated_at' => now()]);
        Audit::record('review.moderated', 'review:'.$review->id, $before, $review->only('status', 'moderation_notes'));

        return back()->with('success', 'Moderasi ulasan disimpan.');
    }

    public function questionAction(Request $request, ProductQuestion $question)
    {
        $data = $request->validate(['status' => 'required|in:answered,rejected', 'answer' => 'nullable|required_if:status,answered|string|min:3|max:3000']);
        $before = $question->only('status', 'answer');
        $question->update($data + [
            'answer' => $data['status'] === 'answered' ? $data['answer'] : null,
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
        ]);
        Audit::record('product_question.moderated', 'product_question:'.$question->id, $before, $question->only('status', 'answer'));
        if ($data['status'] === 'answered' && $before['status'] !== 'answered') {
            $question->loadMissing(['user', 'series']);
            $question->user->notify(new ProductQuestionAnswered($question));
        }

        return back()->with('success', 'Pertanyaan produk diperbarui.');
    }

    public function customQuoteAction(Request $request, CustomQuote $customQuote, CustomProductService $service)
    {
        $data = $request->validate([
            'status' => 'required|in:quoted,rejected',
            'surcharge' => 'nullable|required_if:status,quoted|integer|min:0|max:100000000',
            'lead_time' => 'nullable|required_if:status,quoted|string|max:150',
            'admin_notes' => 'required|string|min:3|max:2000',
            'valid_days' => 'required_if:status,quoted|nullable|integer|between:1,60',
        ]);
        $service->quote($customQuote, $data + ['valid_days' => 7]);

        return back()->with('success', 'Penawaran ukuran custom diperbarui.');
    }

    public function customQuoteReference(CustomQuote $customQuote)
    {
        abort_unless($customQuote->reference_path && Storage::disk('local')->exists($customQuote->reference_path), 404);

        return Storage::disk('local')->download($customQuote->reference_path, 'referensi-'.$customQuote->number.'.'.pathinfo($customQuote->reference_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store']);
    }

    public function orderItemReference(OrderItem $item)
    {
        $path = $item->attributes['custom']['reference_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'referensi-'.$item->sku.'.'.pathinfo($path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store']);
    }

    public function production(Request $request, OrderItem $item, CustomProductService $service)
    {
        $data = $request->validate(['status' => 'required|in:reviewing,approved,production,quality_check,ready_to_ship', 'note' => 'nullable|string|max:1000']);
        $service->updateProduction($item, $data['status'], $data['note'] ?? null);

        return back()->with('success', 'Status produksi diperbarui.');
    }

    public function action(Request $r, Order $order, OrderWorkflow $workflow)
    {
        $d = $r->validate(['action' => 'required|in:approve,reject,cod_confirm,credit_confirm,cancel,pack,ship,receive,cod_paid,credit_paid', 'reason' => 'nullable|string|max:1000', 'tracking' => 'nullable|string|max:100']);
        $permission = match ($d['action']) {
            'approve','reject','cod_paid','credit_paid','credit_confirm' => 'payments.verify','cod_confirm' => 'cod.confirm','cancel' => 'orders.cancel',default => 'shipping.write'
        };
        abort_unless($r->user()->allowed($permission), 403);
        $workflow->act($order, $d['action'], $d['reason'] ?? null, $d['tracking'] ?? null);

        return back()->with('success', 'Status pesanan diperbarui.');
    }

    public function bulkOrderAction(Request $request, OrderWorkflow $workflow)
    {
        $data = $request->validate([
            'order_ids' => 'required|array|min:1|max:100',
            'order_ids.*' => 'required|integer|distinct|exists:orders,id',
            'action' => 'required|in:pack,receive,cancel',
            'reason' => 'nullable|required_if:action,cancel|string|min:5|max:1000',
        ]);
        $permission = $data['action'] === 'cancel' ? 'orders.cancel' : 'shipping.write';
        abort_unless($request->user()->allowed($permission), 403);
        $completed = 0;
        $failures = [];
        foreach (Order::whereKey($data['order_ids'])->get() as $order) {
            try {
                $workflow->act($order, $data['action'], $data['reason'] ?? null);
                $completed++;
            } catch (ValidationException $exception) {
                $failures[] = $order->number.': '.$exception->getMessage();
            }
        }
        Audit::record('orders.bulk_action', 'orders:bulk', null, [
            'order_ids' => $data['order_ids'], 'action' => $data['action'], 'completed' => $completed, 'failed' => count($failures),
        ]);

        return back()->with('success', "{$completed} pesanan berhasil diperbarui.")
            ->with('bulk_failures', $failures);
    }

    public function overrideShipping(Request $request, Order $order)
    {
        $data = $request->validate(['shipping_cost' => 'required|integer|min:0|max:100000000', 'reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($request, $order, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'waiting' || $order->payment_status !== 'unpaid' || $order->proofs()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['shipping_cost' => 'Ongkir hanya dapat diubah sebelum bukti pembayaran atau konfirmasi pesanan.']);
            }
            $before = $order->only(['shipping_cost', 'total', 'shipping', 'shipping_override_reason']);
            $original = $order->shipping_original_cost ?? $order->shipping_cost;
            $shipping = $order->shipping;
            $shipping['source'] = 'manual_override';
            $shipping['override_reason'] = $data['reason'];
            $order->update([
                'shipping_original_cost' => $original,
                'shipping_cost' => $data['shipping_cost'],
                'total' => max(0, $order->total - $order->shipping_cost + $data['shipping_cost']),
                'shipping' => $shipping,
                'shipping_override_reason' => $data['reason'],
                'shipping_overridden_by' => $request->user()->id,
                'shipping_overridden_at' => now(),
            ]);
            $order->events()->create(['event_key' => 'shipping-override-'.Str::uuid(), 'type' => 'shipping_override', 'message' => 'Ongkir diubah dari Rp '.number_format($before['shipping_cost'], 0, ',', '.').' menjadi Rp '.number_format($data['shipping_cost'], 0, ',', '.').'. '.$data['reason']]);
            Audit::record('order.shipping_overridden', 'order:'.$order->id, $before, $order->only(['shipping_cost', 'total', 'shipping', 'shipping_override_reason']));
        }, 3);

        return back()->with('success', 'Ongkir pesanan diperbarui dan dicatat di audit log.');
    }

    public function proof(PaymentProof $proof)
    {
        return Storage::disk('local')->download($proof->path, 'bukti-'.$proof->id.'.'.pathinfo($proof->path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store']);
    }

    private function resource(string $resource): array
    {
        $s = config('admin_resources.'.$resource);
        abort_unless($s, 404);
        abort_unless(auth()->user()->allowed($s['permission']), 403);

        return $s;
    }

    public function resources(Request $r, string $resource)
    {
        $schema = $this->resource($resource);
        $query = $schema['model']::latest();
        if ($resource === 'variant-prices') {
            $query->with('variant', 'priceGroup');
        }
        if ($resource === 'product-media') {
            $query->with('series', 'variant');
        }

        return view('admin.resources', ['resource' => $resource, 'schema' => $schema, 'rows' => $query->paginate(20)]);
    }

    public function form(string $resource, ?int $id = null)
    {
        $schema = $this->resource($resource);
        $record = $id ? $schema['model']::findOrFail($id) : new $schema['model'];

        return view('admin.form', compact('resource', 'schema', 'record'));
    }

    public function save(Request $r, string $resource, ?int $id = null)
    {
        $schema = $this->resource($resource);
        $record = $id ? $schema['model']::findOrFail($id) : new $schema['model'];
        $rules = [];
        foreach ($schema['fields'] as $name => $field) {
            if ($field[1] === 'checkbox') {
                $r->merge([$name => $r->boolean($name)]);
            } $rules[$name] = $field[2];
        }
        if ($resource === 'variants') {
            $r->merge(['sku' => strtoupper(trim($r->input('sku', ''))), 'product_type' => trim($r->input('product_type', ''))]);
        }
        if ($resource === 'coupons') {
            $r->merge(['code' => strtoupper(trim($r->input('code', '')))]);
        }
        if ($resource === 'price-groups') {
            $r->merge(['slug' => strtolower(trim($r->input('slug', '')))]);
        }
        foreach (['slug', 'sku', 'code'] as $key) {
            if (isset($rules[$key])) {
                $rules[$key] = array_merge(explode('|', $rules[$key]), [Rule::unique($record->getTable(), $key)->ignore($record->id)]);
            }
        }
        $data = $r->validate($rules);
        if ($resource === 'bundle-components') {
            $bundle = Variant::findOrFail($data['bundle_variant_id']);
            $component = Variant::findOrFail($data['component_variant_id']);
            if ($bundle->id === $component->id || $bundle->fulfillment !== 'bundle' || $component->fulfillment === 'bundle') {
                throw ValidationException::withMessages(['component_variant_id' => 'Pilih SKU bundle dan SKU komponen non-bundle yang berbeda.']);
            }
            if (BundleComponent::where('bundle_variant_id', $bundle->id)->where('component_variant_id', $component->id)->when($id, fn ($query) => $query->whereKeyNot($id))->exists()) {
                throw ValidationException::withMessages(['component_variant_id' => 'Komponen ini sudah ada pada bundle.']);
            }
        }
        if ($resource === 'business-order-rules') {
            $targetModel = match ($data['target_type']) {
                'category' => Category::class, 'series' => Series::class, 'variant' => Variant::class,
            };
            if (! $targetModel::whereKey($data['target_id'])->exists()) {
                throw ValidationException::withMessages(['target_id' => 'Target minimum B2B tidak ditemukan.']);
            }
            if (BusinessOrderRule::where('price_group_id', $data['price_group_id'])->where('target_type', $data['target_type'])->where('target_id', $data['target_id'])->when($id, fn ($query) => $query->whereKeyNot($id))->exists()) {
                throw ValidationException::withMessages(['target_id' => 'Aturan untuk grup dan target ini sudah tersedia.']);
            }
            if ($data['minimum_quantity'] === 0 && $data['minimum_amount'] === 0) {
                throw ValidationException::withMessages(['minimum_quantity' => 'Isi minimum item atau minimum nilai.']);
            }
        }
        if ($resource === 'product-media') {
            $series = Series::findOrFail($data['series_id']);
            if (! $record->exists && ! $r->hasFile('image')) {
                throw ValidationException::withMessages(['image' => 'Gambar wajib diunggah untuk foto galeri baru.']);
            }
            if (! empty($data['variant_id'])) {
                $variant = Variant::findOrFail($data['variant_id']);
                if ($variant->series_id !== $series->id) {
                    throw ValidationException::withMessages(['variant_id' => 'SKU harus berasal dari series yang dipilih.']);
                }
                $data['color'] = $data['color'] ?: $variant->color;
            }
        }
        if (in_array($resource, ['pages', 'content'], true)) {
            $data['blocks'] = $this->validatedContentBlocks($data['blocks'] ?? null);
            $status = $data['editorial_status'];
            if ($status === 'approved' && ! $r->user()->allowed('content.approve')) {
                throw ValidationException::withMessages(['editorial_status' => 'Akun Anda dapat menulis dan mengajukan konten, tetapi tidak dapat menyetujuinya.']);
            }
            if ($status === 'draft') {
                $data += ['submitted_by' => null, 'submitted_at' => null, 'reviewed_by' => null, 'reviewed_at' => null];
            } elseif ($status === 'review') {
                $data += [
                    'submitted_by' => $record->editorial_status === 'review' ? $record->submitted_by : $r->user()->id,
                    'submitted_at' => $record->editorial_status === 'review' ? $record->submitted_at : now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];
            } else {
                $data += [
                    'submitted_by' => $record->submitted_by ?: $r->user()->id,
                    'submitted_at' => $record->submitted_at ?: now(),
                    'reviewed_by' => $r->user()->id,
                    'reviewed_at' => now(),
                ];
            }
        }
        if ($resource === 'measurement-templates') {
            try {
                $fields = json_decode($data['fields'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['fields' => 'Field ukuran harus berupa JSON yang valid.']);
            }
            if (! is_array($fields) || $fields === []) {
                throw ValidationException::withMessages(['fields' => 'Tambahkan minimal satu field ukuran.']);
            }
            foreach ($fields as $field) {
                if (! is_array($field) || ! preg_match('/^[a-z][a-z0-9_]{1,49}$/', $field['key'] ?? '') || empty($field['label']) || empty($field['unit'])) {
                    throw ValidationException::withMessages(['fields' => 'Setiap field wajib memiliki key, label, dan unit. Key memakai huruf kecil/angka/underscore.']);
                }
                if (isset($field['min'], $field['max']) && $field['min'] > $field['max']) {
                    throw ValidationException::withMessages(['fields' => 'Nilai minimum field tidak boleh melebihi maksimum.']);
                }
            }
            $data['fields'] = $fields;
            if ($data['pricing_mode'] === 'fixed' && $data['surcharge'] === null) {
                throw ValidationException::withMessages(['surcharge' => 'Biaya tetap wajib diisi untuk mekanisme ini.']);
            }
        }
        if ($resource === 'coupons' && $data['type'] === 'percent' && $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'Persentase maksimal 100.']);
        }
        if ($resource === 'coupons') {
            if ($data['target_type'] !== 'all' && empty($data['target_id'])) {
                throw ValidationException::withMessages(['target_id' => 'ID target wajib diisi untuk aturan item ini.']);
            }
            $targetModel = match ($data['target_type']) {
                'category' => Category::class, 'series' => Series::class, 'variant' => Variant::class, default => null,
            };
            if ($targetModel && ! $targetModel::whereKey($data['target_id'])->exists()) {
                throw ValidationException::withMessages(['target_id' => 'Target voucher tidak ditemukan.']);
            }
            if ($data['target_type'] === 'all') {
                $data['target_id'] = null;
            }
        }
        if ($resource === 'series' && $record->exists && $record->variants()->exists() && (bool) $data['uses_product_type'] !== $record->uses_product_type) {
            throw ValidationException::withMessages(['uses_product_type' => 'Struktur series dengan varian tidak dapat diubah.']);
        }
        if ($resource === 'variants') {
            if ($data['fulfillment'] === 'custom_made' && empty($data['measurement_template_id'])) {
                throw ValidationException::withMessages(['measurement_template_id' => 'Pilih template ukuran untuk varian custom.']);
            }
            if ($data['fulfillment'] !== 'custom_made') {
                $data['measurement_template_id'] = null;
            }
            if ($record->exists && OrderItem::where('variant_id', $record->id)->exists()) {
                foreach (['sku', 'series_id', 'product_type', 'color', 'size', 'fulfillment'] as $key) {
                    if ((string) $record->$key !== (string) $data[$key]) {
                        throw ValidationException::withMessages([$key => 'Identitas varian yang pernah dipesan harus dipertahankan. Arsipkan dan buat varian baru.']);
                    }
                }
            }
            $hash = hash('sha256', mb_strtolower(implode('|', [$data['series_id'], trim($data['product_type']), trim($data['color']), trim($data['size'])])));
            if (Variant::where('combination_key', $hash)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                throw ValidationException::withMessages(['sku' => 'Kombinasi atribut ini sudah ada.']);
            }
        }
        if ($resource === 'variant-prices') {
            $duplicate = VariantPrice::where('variant_id', $data['variant_id'])
                ->where('price_group_id', $data['price_group_id'])
                ->where('minimum_quantity', $data['minimum_quantity'])
                ->when($id, fn ($query) => $query->whereKeyNot($id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['minimum_quantity' => 'Tier untuk SKU, grup, dan jumlah minimum ini sudah ada.']);
            }
            $retailPrice = Variant::findOrFail($data['variant_id'])->price;
            if ($data['price'] > $retailPrice) {
                throw ValidationException::withMessages(['price' => 'Harga grup tidak boleh lebih tinggi dari harga retail SKU.']);
            }
            $otherTiers = VariantPrice::where('variant_id', $data['variant_id'])
                ->where('price_group_id', $data['price_group_id'])
                ->when($id, fn ($query) => $query->whereKeyNot($id));
            if ((clone $otherTiers)->where('minimum_quantity', '<', $data['minimum_quantity'])->where('price', '<', $data['price'])->exists()
                || (clone $otherTiers)->where('minimum_quantity', '>', $data['minimum_quantity'])->where('price', '>', $data['price'])->exists()) {
                throw ValidationException::withMessages(['price' => 'Harga per item harus tetap atau turun ketika jumlah minimum bertambah.']);
            }
        }
        if ($r->hasFile('image')) {
            $directory = $resource === 'product-media' ? 'product-gallery' : 'products';
            $data['image'] = $r->file('image')->store($directory, 'public');
        } else {
            unset($data['image']);
        }
        DB::transaction(function () use ($record, $data, $resource) {
            $before = $record->exists ? $record->getAttributes() : null;
            if ($before && in_array($resource, ['pages', 'content'], true)) {
                ContentRevision::create(['content_type' => $resource, 'content_id' => $record->id, 'user_id' => auth()->id(), 'snapshot' => $before]);
            }
            $record->fill($data)->save();
            Audit::record($resource.'.saved', $resource.':'.$record->id, $before, $record->toArray());
        });

        return redirect()->route('admin.resources', $resource)->with('success', 'Data berhasil disimpan.');
    }

    private function validatedContentBlocks(?string $json): ?array
    {
        if (! $json) {
            return null;
        }
        try {
            $blocks = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['blocks' => 'Susunan blok konten tidak valid. Muat ulang halaman dan coba kembali.']);
        }
        if (! is_array($blocks) || count($blocks) > 50) {
            throw ValidationException::withMessages(['blocks' => 'Konten dibatasi maksimal 50 blok.']);
        }
        $allowed = ['heading', 'paragraph', 'quote', 'button', 'image', 'columns'];
        foreach ($blocks as $index => &$block) {
            if (! is_array($block) || ! in_array($block['type'] ?? '', $allowed, true)) {
                throw ValidationException::withMessages(['blocks' => 'Tipe blok ke-'.($index + 1).' tidak didukung.']);
            }
            $block = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $block);
            if (in_array($block['type'], ['heading', 'paragraph', 'quote'], true) && empty($block['text'])) {
                throw ValidationException::withMessages(['blocks' => 'Isi teks blok ke-'.($index + 1).' wajib diisi.']);
            }
            if (isset($block['text']) && mb_strlen($block['text']) > 10000) {
                throw ValidationException::withMessages(['blocks' => 'Isi salah satu blok terlalu panjang.']);
            }
            if ($block['type'] === 'columns') {
                $items = $block['items'] ?? [];
                if (! is_array($items) || count($items) < 2 || count($items) > 4) {
                    throw ValidationException::withMessages(['blocks' => 'Blok kolom harus memiliki dua sampai empat kolom.']);
                }
                foreach ($items as &$item) {
                    if (! is_array($item) || empty(trim($item['heading'] ?? '')) || empty(trim($item['text'] ?? '')) || mb_strlen($item['heading']) > 200 || mb_strlen($item['text']) > 3000) {
                        throw ValidationException::withMessages(['blocks' => 'Setiap kolom wajib memiliki judul dan isi.']);
                    }
                    $item = ['heading' => trim($item['heading']), 'text' => trim($item['text'])];
                }
                $block['items'] = array_values($items);
            }
            if (in_array($block['type'], ['button', 'image'], true)) {
                if (empty($block['url']) || ! $this->safeContentUrl($block['url'])) {
                    throw ValidationException::withMessages(['blocks' => 'Tautan blok ke-'.($index + 1).' harus berupa URL https atau path internal.']);
                }
                if (empty($block['label']) || mb_strlen($block['label']) > 200) {
                    throw ValidationException::withMessages(['blocks' => 'Label atau alt text blok ke-'.($index + 1).' wajib diisi dan maksimal 200 karakter.']);
                }
            }
            $block = array_intersect_key($block, array_flip(['type', 'text', 'url', 'label', 'items']));
        }

        return array_values($blocks);
    }

    private function safeContentUrl(string $url): bool
    {
        return str_starts_with($url, '/') || str_starts_with($url, '#') || filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    public function contentHistory(string $resource, int $id)
    {
        abort_unless(in_array($resource, ['pages', 'content'], true), 404);
        $schema = $this->resource($resource);
        $record = $schema['model']::findOrFail($id);
        $revisions = ContentRevision::where('content_type', $resource)->where('content_id', $id)->with('user')->latest()->paginate(20);

        return view('admin.content-history', compact('resource', 'schema', 'record', 'revisions'));
    }

    public function restoreContent(ContentRevision $revision)
    {
        abort_unless(in_array($revision->content_type, ['pages', 'content'], true), 404);
        $schema = $this->resource($revision->content_type);
        $record = $schema['model']::findOrFail($revision->content_id);

        DB::transaction(function () use ($record, $revision) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            $before = $record->getAttributes();
            ContentRevision::create(['content_type' => $revision->content_type, 'content_id' => $record->id, 'user_id' => auth()->id(), 'snapshot' => $before]);
            $snapshot = $revision->snapshot;
            unset($snapshot['id'], $snapshot['created_at'], $snapshot['updated_at']);
            $record->fill($snapshot)->save();
            Audit::record($revision->content_type.'.restored', $revision->content_type.':'.$record->id, $before, $record->getAttributes());
        });

        return redirect()->route('admin.content.history', [$revision->content_type, $revision->content_id])->with('success', 'Versi konten berhasil dipulihkan. Versi sebelumnya tetap tersimpan.');
    }

    public function inventory(Request $r)
    {
        $q = Variant::with('series');
        if ($r->filled('q')) {
            $q->where('sku', 'like', '%'.mb_substr($r->string('q'), 0, 80).'%');
        } if ($r->boolean('low')) {
            $q->whereRaw('stock - reserved <= low_stock');
        }

        return view('admin.inventory', ['variants' => $q->paginate(20)->withQueryString(), 'movements' => StockMovement::latest()->take(20)->get()]);
    }

    public function adjust(Request $r, Variant $variant)
    {
        $d = $r->validate(['delta' => 'required|integer|not_in:0|between:-1000000,1000000', 'reason' => 'required|string|min:5|max:255']);
        DB::transaction(function () use ($d, $variant) {
            $v = Variant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            if ($v->stock + $d['delta'] < $v->reserved) {
                throw ValidationException::withMessages(['delta' => 'Stok tidak boleh kurang dari jumlah yang direservasi.']);
            } $before = $v->stock;
            $v->update(['stock' => $v->stock + $d['delta']]);
            StockMovement::create(['variant_id' => $v->id, 'user_id' => auth()->id(), 'stock_delta' => $d['delta'], 'reserved_delta' => 0, 'reason' => $d['reason']]);
            Audit::record('inventory.adjusted', 'variant:'.$v->id, ['stock' => $before], ['stock' => $v->stock, 'reason' => $d['reason']]);
        }, 3);

        return back()->with('success', 'Penyesuaian dicatat.');
    }

    public function settings()
    {
        return view('admin.settings');
    }

    public function security()
    {
        return view('admin.security');
    }

    public function saveSecurity(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'two_factor_enabled' => 'nullable|boolean']);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
        }
        $required = (bool) Setting::get('staff_two_factor_required', false);
        $enabled = $required || $request->boolean('two_factor_enabled');
        $before = $request->user()->only(['two_factor_enabled', 'two_factor_confirmed_at']);
        $request->user()->forceFill([
            'two_factor_enabled' => $enabled,
            'two_factor_confirmed_at' => $enabled ? now() : null,
        ])->save();
        Audit::record('staff.two_factor_updated', 'user:'.$request->user()->id, $before, $request->user()->only(['two_factor_enabled', 'two_factor_confirmed_at']));

        return back()->with('success', $required ? 'Verifikasi dua langkah diwajibkan oleh organisasi dan tetap aktif.' : ($enabled ? 'Verifikasi dua langkah sudah aktif.' : 'Verifikasi dua langkah dinonaktifkan.'));
    }

    public function saveSettings(Request $r)
    {
        $rules = [
            'brand_name' => 'required|string|max:80', 'brand_subtitle' => 'nullable|string|max:80', 'tagline' => 'required|string|max:160',
            'hero_eyebrow' => 'nullable|string|max:120', 'hero_title' => 'required|string|max:150', 'hero_text' => 'required|string|max:500', 'hero_button_label' => 'nullable|string|max:80', 'hero_button_url' => 'nullable|string|max:500',
            'benefit_1' => 'nullable|string|max:150', 'benefit_2' => 'nullable|string|max:150', 'benefit_3' => 'nullable|string|max:150',
            'story_eyebrow' => 'nullable|string|max:120', 'story_title' => 'nullable|string|max:200', 'story_text' => 'nullable|string|max:1000',
            'footer_text' => 'nullable|string|max:500', 'store_location' => 'nullable|string|max:150', 'contact_email' => 'nullable|email|max:150',
            'whatsapp' => ['nullable', 'regex:/^62[0-9]{8,13}$/'], 'instagram_url' => 'nullable|url|max:500', 'tiktok_url' => 'nullable|url|max:500', 'facebook_url' => 'nullable|url|max:500',
            'default_seo_title' => 'nullable|string|max:200', 'default_seo_description' => 'nullable|string|max:300',
            'theme_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'theme_accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'theme_background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'theme_surface_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'theme_text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_font' => 'required|in:classic,modern,system', 'theme_container_width' => 'required|integer|between:960,1800', 'theme_radius' => 'required|integer|between:0,32', 'theme_header_style' => 'required|in:standard,compact,centered',
            'footer_copyright' => 'nullable|string|max:200',
            'shipping_mode' => 'required|in:manual,api', 'shipping_provider' => 'required|string|max:80', 'shipping_api_url' => 'nullable|url|max:500', 'shipping_api_token_new' => 'nullable|string|max:2000',
            'tracking_mode' => 'required|in:manual,api', 'whatsapp_mode' => 'required|in:manual,api', 'whatsapp_provider' => 'required|string|max:80', 'whatsapp_api_url' => 'nullable|url|max:500', 'whatsapp_api_token_new' => 'nullable|string|max:2000',
            'bank_text' => 'nullable|string|max:3000', 'payment_hours' => 'required|integer|between:1,168', 'cod_hours' => 'required|integer|between:1,168', 'cod_min' => 'required|integer|min:0', 'cod_max' => 'required|integer|min:0|gte:cod_min', 'cod_fee' => 'required|integer|min:0', 'cod_percent' => 'required|integer|between:0,100', 'cod_fee_max' => 'required|integer|min:0',
            'payment_review_sla_hours' => 'required|integer|between:1,168',
            'cod_blocked_phones_text' => 'nullable|string|max:10000', 'cod_excluded_skus_text' => 'nullable|string|max:10000', 'cod_excluded_categories_text' => 'nullable|string|max:10000',
            'loyalty_spend_per_point' => 'required|integer|min:1', 'loyalty_point_value' => 'required|integer|min:1', 'loyalty_min_redeem' => 'required|integer|min:1', 'loyalty_expiry_days' => 'required|integer|between:1,3650',
            'loyalty_tiers_text' => 'nullable|string|max:5000', 'loyalty_bonus_name' => 'nullable|string|max:100', 'loyalty_bonus_multiplier' => 'required|integer|between:1,20', 'loyalty_bonus_start' => 'nullable|date', 'loyalty_bonus_end' => 'nullable|date|after_or_equal:loyalty_bonus_start',
            'loyalty_bonus_segment' => 'required|in:all,customer,business', 'loyalty_bonus_tier' => 'nullable|string|max:100', 'loyalty_bonus_target_type' => 'required|in:all,category,series,variant', 'loyalty_bonus_target_id' => 'nullable|integer|min:1',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072', 'favicon' => 'nullable|image|mimes:png|max:512', 'hero_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
        $data = $r->validate($rules);
        $banks = [];
        foreach (explode("\n", $data['bank_text'] ?? '') as $line) {
            if (trim($line) !== '') {
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) !== 3) {
                    throw ValidationException::withMessages(['bank_text' => 'Format setiap baris: Bank | Nomor rekening | Pemilik']);
                } $banks[] = ['bank' => $parts[0], 'number' => $parts[1], 'owner' => $parts[2]];
            }
        }
        unset($data['bank_text']);
        foreach (['cod_blocked_phones', 'cod_excluded_skus', 'cod_excluded_categories'] as $list) {
            $input = $list.'_text';
            $values = preg_split('/[,\r\n]+/', $data[$input] ?? '');
            $data[$list] = collect($values)->map(fn ($value) => trim($value))->filter()->map(fn ($value) => $list === 'cod_blocked_phones' ? preg_replace('/\D+/', '', $value) : ($list === 'cod_excluded_skus' ? strtoupper($value) : strtolower($value)))->filter()->unique()->values()->all();
            unset($data[$input]);
        }
        $data['cod_allowed_segments'] = collect(['guest', 'customer', 'business'])->filter(fn ($segment) => $r->boolean('cod_segment_'.$segment))->values()->all();
        $tiers = [];
        foreach (explode("\n", $data['loyalty_tiers_text'] ?? '') as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2 || count($parts) > 4 || $parts[0] === '' || filter_var($parts[1], FILTER_VALIDATE_INT) === false || (int) $parts[1] < 0 || (isset($parts[3]) && (filter_var($parts[3], FILTER_VALIDATE_INT) === false || (int) $parts[3] < 0 || (int) $parts[3] > 500))) {
                throw ValidationException::withMessages(['loyalty_tiers_text' => 'Format setiap level: Nama | Minimum poin | Benefit | Bonus nilai poin (%).']);
            }
            $tiers[] = ['name' => $parts[0], 'minimum' => (int) $parts[1], 'benefit' => $parts[2] ?? '', 'value_bonus_percent' => (int) ($parts[3] ?? 0)];
        }
        $data['loyalty_tiers'] = collect($tiers ?: [['name' => 'Member', 'minimum' => 0, 'benefit' => '', 'value_bonus_percent' => 0]])->sortBy('minimum')->unique('minimum')->values()->all();
        unset($data['loyalty_tiers_text']);
        if ($data['loyalty_bonus_target_type'] === 'all') {
            $data['loyalty_bonus_target_id'] = null;
        } else {
            if (empty($data['loyalty_bonus_target_id'])) {
                throw ValidationException::withMessages(['loyalty_bonus_target_id' => 'ID target wajib diisi untuk kampanye bertarget.']);
            }
            $targetModel = match ($data['loyalty_bonus_target_type']) {
                'category' => Category::class, 'series' => Series::class, 'variant' => Variant::class,
            };
            if (! $targetModel::whereKey($data['loyalty_bonus_target_id'])->exists()) {
                throw ValidationException::withMessages(['loyalty_bonus_target_id' => 'Target kampanye loyalty tidak ditemukan.']);
            }
        }
        foreach (['logo', 'favicon', 'hero_image'] as $image) {
            unset($data[$image]);
            if ($r->hasFile($image)) {
                $data[$image] = $r->file($image)->store('branding', 'public');
            }
        }
        foreach (['shipping_api_token', 'whatsapp_api_token'] as $secret) {
            $input = $secret.'_new';
            if (! empty($data[$input])) {
                $data[$secret] = Crypt::encryptString($data[$input]);
            }
            unset($data[$input]);
        }
        $data['bank_accounts'] = $banks;
        $data['cod_enabled'] = $r->boolean('cod_enabled');
        $data['loyalty_enabled'] = $r->boolean('loyalty_enabled');
        $data['analytics_enabled'] = $r->boolean('analytics_enabled');
        $data['checkout_enabled'] = $r->boolean('checkout_enabled');
        $data['staff_two_factor_required'] = $r->boolean('staff_two_factor_required');
        foreach (['header_announcement_enabled', 'header_categories_enabled', 'header_tracking_enabled', 'header_sticky', 'footer_pages_enabled', 'footer_social_enabled'] as $toggle) {
            $data[$toggle] = $r->boolean($toggle);
        }
        if ($data['checkout_enabled'] && ! count($banks)) {
            throw ValidationException::withMessages(['bank_text' => 'Isi rekening sebelum membuka checkout.']);
        }
        DB::transaction(function () use ($data) {
            foreach ($data as $k => $v) {
                $old = Setting::get($k);
                Setting::put($k, $v);
                Audit::record('setting.updated', $k, $old, $v);
            }
        });

        return back()->with('success', 'Pengaturan disimpan.');
    }

    public function audit()
    {
        return view('admin.audit', ['logs' => AuditLog::latest()->paginate(40)]);
    }

    public function customers()
    {
        return view('admin.customers', [
            'users' => User::where('role', 'customer')->with(['businessAccount.priceGroup', 'businessAccount.salesUser'])->withCount('orders')->latest()->paginate(20),
            'priceGroups' => PriceGroup::where('active', true)->orderBy('name')->get(),
            'salesUsers' => User::where('role', '!=', 'customer')->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function reviewBusinessAccount(Request $r, BusinessAccount $businessAccount)
    {
        $data = $r->validate([
            'status' => 'required|in:approved,rejected,suspended',
            'price_group_id' => 'nullable|required_if:status,approved|exists:price_groups,id',
            'review_notes' => 'nullable|required_if:status,rejected|string|max:2000',
            'expires_at' => 'nullable|date|after:today',
            'sales_user_id' => 'nullable|exists:users,id',
            'credit_terms_days' => 'required|integer|between:0,365',
            'credit_limit' => 'required|integer|min:0|max:100000000000',
            'commission_percent' => 'required|numeric|between:0,100',
        ]);
        $group = isset($data['price_group_id']) ? PriceGroup::findOrFail($data['price_group_id']) : null;
        if ($data['status'] === 'approved' && (! $group?->active || $group->business_type !== $businessAccount->business_type)) {
            throw ValidationException::withMessages(['price_group_id' => 'Pilih grup aktif yang sesuai dengan jenis bisnis pelanggan.']);
        }
        if (! empty($data['sales_user_id']) && ! User::whereKey($data['sales_user_id'])->where('role', '!=', 'customer')->where('active', true)->exists()) {
            throw ValidationException::withMessages(['sales_user_id' => 'Pilih anggota tim aktif sebagai penanggung jawab sales.']);
        }

        DB::transaction(function () use ($r, $data, $group, $businessAccount) {
            $account = BusinessAccount::whereKey($businessAccount->id)->lockForUpdate()->firstOrFail();
            $before = $account->toArray();
            $account->update([
                'status' => $data['status'],
                'price_group_id' => $data['status'] === 'approved' ? $group->id : null,
                'reviewed_by' => $r->user()->id,
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_at' => now(),
                'expires_at' => $data['status'] === 'approved' ? ($data['expires_at'] ?? null) : $account->expires_at,
                'sales_user_id' => $data['sales_user_id'] ?? null,
                'credit_terms_days' => $data['credit_terms_days'],
                'credit_limit' => $data['credit_limit'],
                'commission_percent' => $data['commission_percent'],
            ]);
            Audit::record('business.application_reviewed', 'business_account:'.$account->id, $before, $account->toArray());
        });

        return back()->with('success', 'Pengajuan akun bisnis diperbarui.');
    }

    public function businessDocument(BusinessAccount $businessAccount)
    {
        abort_unless($businessAccount->legal_document_path && Storage::disk('local')->exists($businessAccount->legal_document_path), 404);

        return Storage::disk('local')->download($businessAccount->legal_document_path, $businessAccount->legal_document_name ?: 'dokumen-usaha');
    }

    public function cases()
    {
        return view('admin.cases', ['cases' => ServiceCase::with('order')->latest()->paginate(20)]);
    }

    public function caseUpdate(Request $r, ServiceCase $case, AfterSalesService $service)
    {
        $d = $r->validate(['status' => 'required|in:reviewing,approved,resolved,rejected', 'resolution' => 'required|string|min:5|max:2000']);
        $service->update($case, $d);

        return back()->with('success', 'Kasus diperbarui.');
    }

    public function team()
    {
        return view('admin.team', ['users' => User::where('role', '!=', 'customer')->get(), 'roles' => Role::all()]);
    }

    public function teamSave(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|unique:users|max:150', 'password' => 'required|string|min:12|max:128', 'role' => ['required', Rule::in(array_keys(config('commerce.roles')))]]);
        $u = User::create($d);
        $u->forceFill(['role' => $d['role']])->save();
        Audit::record('user.created', 'user:'.$u->id, null, $u->only('name', 'role'));

        return back()->with('success', 'Pengguna ditambahkan.');
    }

    public function teamUpdate(Request $r, User $user)
    {
        abort_if($user->role === 'customer', 404);
        $d = $r->validate(['role' => ['required', Rule::in(array_keys(config('commerce.roles')))], 'active' => 'nullable|boolean']);
        $active = $r->boolean('active');
        if ($user->is($r->user()) && (! $active || $d['role'] !== 'admin')) {
            throw ValidationException::withMessages(['active' => 'Admin yang sedang masuk tidak dapat menurunkan akses atau menonaktifkan dirinya sendiri.']);
        }
        if ($user->role === 'admin' && ($d['role'] !== 'admin' || ! $active)
            && User::where('role', 'admin')->where('active', true)->count() <= 1) {
            throw ValidationException::withMessages(['active' => 'Minimal satu admin aktif harus dipertahankan.']);
        }
        $before = $user->only('role', 'active');
        $user->forceFill(['role' => $d['role'], 'active' => $active])->save();
        Audit::record('user.updated', 'user:'.$user->id, $before, $user->only('role', 'active'));

        return back()->with('success', 'Akses pengguna diperbarui.');
    }

    public function roleSave(Request $r, Role $role)
    {
        abort_if($role->name === 'admin', 422, 'Peran admin utama dipertahankan.');
        $d = $r->validate(['permissions' => 'sometimes|array', 'permissions.*' => [Rule::in(config('commerce.permissions'))]]);
        $before = $role->permissions;
        $role->update(['permissions' => $d['permissions'] ?? []]);
        Audit::record('role.updated', 'role:'.$role->id, $before, $role->permissions);

        return back()->with('success', 'Hak akses diperbarui.');
    }

    public function reports(Request $r)
    {
        $d = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = $d['from'] ?? now()->startOfMonth()->toDateString();
        $to = $d['to'] ?? now()->toDateString();
        $range = [$from.' 00:00:00', $to.' 23:59:59'];
        $q = Order::whereBetween('created_at', $range);
        if ($r->input('format') === 'csv') {
            return response()->streamDownload(function () use ($q) {
                $f = fopen('php://output', 'w');
                fputcsv($f, ['Nomor', 'Tanggal', 'Status', 'Pembayaran', 'Metode', 'Subtotal', 'Diskon', 'Ongkir', 'Subsidi ongkir', 'Biaya COD', 'Total', 'Refund', 'Nilai bersih'], ',', '"', '');
                foreach ($q->withSum('refunds', 'amount')->orderBy('id')->cursor() as $o) {
                    fputcsv($f, [$o->number, $o->created_at, $o->status, $o->payment_status, $o->payment_method, $o->subtotal, $o->discount, $o->shipping_cost, $o->shipping_discount, $o->cod_fee, $o->total, $o->refunds_sum_amount ?? 0, $o->total - ($o->refunds_sum_amount ?? 0)], ',', '"', '');
                }
                fclose($f);
            }, 'penjualan.csv', ['Content-Type' => 'text/csv']);
        }

        $paid = Order::whereBetween('paid_at', $range);
        $gross = (clone $paid)->sum('total');
        $refunds = Refund::whereBetween('paid_at', $range)->sum('amount');
        $paidCustomers = (clone $paid)->get(['user_id', 'phone'])->groupBy(fn (Order $order) => $order->user_id ? 'user:'.$order->user_id : 'phone:'.$order->phone);
        $topProducts = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.paid_at', $range)
            ->select('order_items.sku', 'order_items.name', DB::raw('SUM(order_items.quantity) AS quantity'), DB::raw('SUM(order_items.total) AS revenue'))
            ->groupBy('order_items.sku', 'order_items.name')->orderByDesc('quantity')->limit(8)->get();
        $shipping = (clone $paid)->get()->groupBy(fn (Order $order) => ($order->shipping['courier'] ?? 'Tanpa kurir').' '.($order->shipping['service'] ?? ''))
            ->map(fn ($orders) => ['orders' => $orders->count(), 'value' => $orders->sum('shipping_cost')]);
        $analytics = AnalyticsEvent::whereBetween('created_at', $range)->select('event', DB::raw('COUNT(*) AS total'))->groupBy('event')->pluck('total', 'event');
        $visitors = AnalyticsEvent::whereBetween('created_at', $range)->distinct()->count('visitor_hash');
        $commissionEntries = CommissionEntry::with(['salesUser', 'order', 'payout'])->whereBetween('created_at', $range)->latest()->get();
        $commissionPayouts = CommissionPayout::with(['salesUser', 'creator'])->whereBetween('paid_at', $range)->latest('paid_at')->get();
        $unpaidCommissions = CommissionEntry::with('salesUser')->whereNull('commission_payout_id')->get()->groupBy('sales_user_id')
            ->map(fn ($entries) => ['sales_user' => $entries->first()->salesUser, 'amount' => (int) $entries->sum('amount')])
            ->filter(fn ($summary) => $summary['amount'] > 0)->values();

        return view('admin.reports', [
            'from' => $from, 'to' => $to, 'count' => (clone $q)->count(), 'paidCount' => (clone $paid)->count(),
            'gross' => $gross, 'refunds' => $refunds, 'net' => max(0, $gross - $refunds),
            'aov' => (clone $paid)->count() ? (int) round($gross / (clone $paid)->count()) : 0,
            'customerCount' => $paidCustomers->count(), 'repeatCustomers' => $paidCustomers->filter(fn ($orders) => $orders->count() > 1)->count(),
            'topProducts' => $topProducts, 'shipping' => $shipping, 'analytics' => $analytics, 'visitors' => $visitors,
            'commissionEntries' => $commissionEntries, 'commissionTotal' => $commissionEntries->sum('amount'),
            'commissionPayouts' => $commissionPayouts, 'unpaidCommissions' => $unpaidCommissions,
            'lowStock' => Variant::whereRaw('stock - reserved <= low_stock')->count(),
            'orders' => $q->withSum('refunds', 'amount')->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function commissionPayout(Request $request, CommissionPayoutService $service)
    {
        $data = $request->validate([
            'sales_user_id' => 'required|integer|exists:users,id',
            'recipient_name' => 'required|string|max:150',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:100',
            'payment_reference' => 'required|string|max:150|unique:commission_payouts,payment_reference',
            'paid_at' => 'required|date|before_or_equal:now',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'notes' => 'nullable|string|max:1000',
        ]);
        $salesUser = User::whereKey($data['sales_user_id'])->where('role', '!=', 'customer')->firstOrFail();
        $proofPath = $request->hasFile('proof') ? $request->file('proof')->store('commission-payouts', 'local') : null;
        unset($data['sales_user_id'], $data['proof']);
        try {
            $payout = $service->create($salesUser, $request->user(), $data + ['proof_path' => $proofPath]);
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }

        return back()->with('success', 'Payout komisi '.$payout->number.' senilai Rp '.number_format($payout->amount, 0, ',', '.').' dicatat.');
    }

    public function commissionPayoutProof(CommissionPayout $payout)
    {
        abort_unless($payout->proof_path && Storage::disk('local')->exists($payout->proof_path), 404);

        return Storage::disk('local')->download($payout->proof_path, 'bukti-'.$payout->number.'.'.pathinfo($payout->proof_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store']);
    }

    public function monitoring(SystemHealthService $health)
    {
        return view('admin.monitoring', [
            'checks' => $health->checks(),
            'recentErrors' => AuditLog::whereIn('action', ['notification.failed', 'notification.uncertain'])->latest()->take(20)->get(),
        ]);
    }
}
