<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProof;
use App\Models\Role;
use App\Models\ServiceCase;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use App\Services\Audit;
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
            'revenue' => Order::where('payment_status', 'paid')->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('subtotal') - Order::where('payment_status', 'paid')->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('discount'),
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
        $order->load('items', 'proofs');

        return view('admin.order', compact('order'));
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

        return view('admin.resources', ['resource' => $resource, 'schema' => $schema, 'rows' => $schema['model']::latest()->paginate(20)]);
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
        foreach (['slug', 'sku', 'code'] as $key) {
            if (isset($rules[$key])) {
                $rules[$key] = array_merge(explode('|', $rules[$key]), [Rule::unique($record->getTable(), $key)->ignore($record->id)]);
            }
        }
        $data = $r->validate($rules);
        if ($resource === 'coupons' && $data['type'] === 'percent' && $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'Persentase maksimal 100.']);
        }
        if ($resource === 'series' && $record->exists && $record->variants()->exists() && (bool) $data['uses_product_type'] !== $record->uses_product_type) {
            throw ValidationException::withMessages(['uses_product_type' => 'Struktur series dengan varian tidak dapat diubah.']);
        }
        if ($resource === 'variants') {
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
        $rules = ['brand_name' => 'required|string|max:80', 'tagline' => 'required|string|max:160', 'hero_title' => 'required|string|max:150', 'hero_text' => 'required|string|max:500', 'whatsapp' => ['nullable', 'regex:/^62[0-9]{8,13}$/'], 'bank_text' => 'nullable|string|max:3000', 'payment_hours' => 'required|integer|between:1,168', 'cod_hours' => 'required|integer|between:1,168', 'cod_min' => 'required|integer|min:0', 'cod_max' => 'required|integer|min:0|gte:cod_min', 'cod_fee' => 'required|integer|min:0', 'cod_percent' => 'required|integer|between:0,100', 'cod_fee_max' => 'required|integer|min:0'];
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
        return view('admin.customers', ['users' => User::where('role', 'customer')->withCount('orders')->latest()->paginate(20)]);
    }

    public function cases()
    {
        return view('admin.cases', ['cases' => ServiceCase::with('order')->latest()->paginate(20)]);
    }

    public function caseUpdate(Request $r, ServiceCase $case)
    {
        $d = $r->validate(['status' => 'required|in:open,reviewing,resolved,rejected', 'resolution' => 'required|string|max:2000']);
        $before = $case->toArray();
        $case->update($d);
        Audit::record('case.updated', 'case:'.$case->id, $before, $d);

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
        $q = Order::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        if ($r->input('format') === 'csv') {
            return response()->streamDownload(function () use ($q) {
                $f = fopen('php://output', 'w');
                fputcsv($f, ['Nomor', 'Tanggal', 'Status', 'Pembayaran', 'Subtotal', 'Diskon', 'Ongkir', 'Subsidi ongkir', 'Biaya COD', 'Total'], ',', '"', '');
                foreach ($q->orderBy('id')->cursor() as $o) {
                    fputcsv($f, [$o->number, $o->created_at, $o->status, $o->payment_status, $o->subtotal, $o->discount, $o->shipping_cost, $o->shipping_discount, $o->cod_fee, $o->total], ',', '"', '');
                } fclose($f);
            }, 'penjualan.csv', ['Content-Type' => 'text/csv']);
        }

        return view('admin.reports',['from' => $from, 'to' => $to, 'count' => (clone $q)->count(), 'paid' => (clone $q)->where('payment_status','paid')->sum('total'), 'orders' => $q->latest()->paginate(20)->withQueryString()]);
    }
}
