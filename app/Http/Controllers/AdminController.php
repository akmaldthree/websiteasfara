<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BundleComponent;
use App\Models\BusinessAccount;
use App\Models\ContentEntry;
use App\Models\CustomQuote;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProof;
use App\Models\PriceGroup;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Role;
use App\Models\ServiceCase;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use App\Models\VariantPrice;
use App\Services\AfterSalesService;
use App\Services\Audit;
use App\Services\CustomProductService;
use App\Services\OrderWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'revenue' => max(0, Order::whereNotNull('paid_at')->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('total')
                - Refund::whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount')),
            'waiting' => Order::where('payment_status', 'review')->count(), 'cod' => Order::where('payment_method', 'cod')->where('status', 'waiting')->count(),
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

    public function reviews(Request $request)
    {
        $query = Review::with(['user', 'variant.series', 'item.order']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('admin.reviews', ['reviews' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function reviewAction(Request $request, Review $review)
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'moderation_notes' => 'nullable|required_if:status,rejected|string|max:1000']);
        $before = $review->only('status', 'moderation_notes');
        $review->update($data + ['moderated_by' => $request->user()->id, 'moderated_at' => now()]);
        Audit::record('review.moderated', 'review:'.$review->id, $before, $review->only('status', 'moderation_notes'));

        return back()->with('success', 'Moderasi ulasan disimpan.');
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

    public function production(Request $request, OrderItem $item, CustomProductService $service)
    {
        $data = $request->validate(['status' => 'required|in:reviewing,approved,production,quality_check,ready_to_ship', 'note' => 'nullable|string|max:1000']);
        $service->updateProduction($item, $data['status'], $data['note'] ?? null);

        return back()->with('success', 'Status produksi diperbarui.');
    }

    public function action(Request $r, Order $order, OrderWorkflow $workflow)
    {
        $d = $r->validate(['action' => 'required|in:approve,reject,cod_confirm,cancel,pack,ship,receive,cod_paid', 'reason' => 'nullable|string|max:1000', 'tracking' => 'nullable|string|max:100']);
        $permission = match ($d['action']) {
            'approve','reject','cod_paid' => 'payments.verify','cod_confirm' => 'cod.confirm','cancel' => 'orders.cancel',default => 'shipping.write'
        };
        abort_unless($r->user()->allowed($permission), 403);
        $workflow->act($order, $d['action'], $d['reason'] ?? null, $d['tracking'] ?? null);

        return back()->with('success', 'Status pesanan diperbarui.');
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
            $data['image'] = $r->file('image')->store('products', 'public');
        } else {
            unset($data['image']);
        }
        DB::transaction(function () use ($record, $data, $resource) {
            $before = $record->exists ? $record->toArray() : null;
            $record->fill($data)->save();
            Audit::record($resource.'.saved', $resource.':'.$record->id, $before, $record->toArray());
        });

        return redirect()->route('admin.resources', $resource)->with('success', 'Data berhasil disimpan.');
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

    public function saveSettings(Request $r)
    {
        $rules = ['brand_name' => 'required|string|max:80', 'tagline' => 'required|string|max:160', 'hero_title' => 'required|string|max:150', 'hero_text' => 'required|string|max:500', 'whatsapp' => ['nullable', 'regex:/^62[0-9]{8,13}$/'], 'bank_text' => 'nullable|string|max:3000', 'payment_hours' => 'required|integer|between:1,168', 'cod_hours' => 'required|integer|between:1,168', 'cod_min' => 'required|integer|min:0', 'cod_max' => 'required|integer|min:0|gte:cod_min', 'cod_fee' => 'required|integer|min:0', 'cod_percent' => 'required|integer|between:0,100', 'cod_fee_max' => 'required|integer|min:0', 'loyalty_spend_per_point' => 'required|integer|min:1', 'loyalty_point_value' => 'required|integer|min:1', 'loyalty_min_redeem' => 'required|integer|min:1', 'loyalty_expiry_days' => 'required|integer|between:1,3650'];
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
        $data['bank_accounts'] = $banks;
        $data['cod_enabled'] = $r->boolean('cod_enabled');
        $data['loyalty_enabled'] = $r->boolean('loyalty_enabled');
        $data['checkout_enabled'] = $r->boolean('checkout_enabled');
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
            'users' => User::where('role', 'customer')->with(['businessAccount.priceGroup'])->withCount('orders')->latest()->paginate(20),
            'priceGroups' => PriceGroup::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function reviewBusinessAccount(Request $r, BusinessAccount $businessAccount)
    {
        $data = $r->validate([
            'status' => 'required|in:approved,rejected',
            'price_group_id' => 'nullable|required_if:status,approved|exists:price_groups,id',
            'review_notes' => 'nullable|required_if:status,rejected|string|max:2000',
        ]);
        $group = isset($data['price_group_id']) ? PriceGroup::findOrFail($data['price_group_id']) : null;
        if ($data['status'] === 'approved' && (! $group?->active || $group->business_type !== $businessAccount->business_type)) {
            throw ValidationException::withMessages(['price_group_id' => 'Pilih grup aktif yang sesuai dengan jenis bisnis pelanggan.']);
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
            ]);
            Audit::record('business.application_reviewed', 'business_account:'.$account->id, $before, $account->toArray());
        });

        return back()->with('success', 'Pengajuan akun bisnis diperbarui.');
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

        return view('admin.reports', [
            'from' => $from, 'to' => $to, 'count' => (clone $q)->count(), 'paidCount' => (clone $paid)->count(),
            'gross' => $gross, 'refunds' => $refunds, 'net' => max(0, $gross - $refunds),
            'aov' => (clone $paid)->count() ? (int) round($gross / (clone $paid)->count()) : 0,
            'customerCount' => $paidCustomers->count(), 'repeatCustomers' => $paidCustomers->filter(fn ($orders) => $orders->count() > 1)->count(),
            'topProducts' => $topProducts, 'shipping' => $shipping,
            'lowStock' => Variant::whereRaw('stock - reserved <= low_stock')->count(),
            'orders' => $q->withSum('refunds', 'amount')->latest()->paginate(20)->withQueryString(),
        ]);
    }
}
