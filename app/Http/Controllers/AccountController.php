<?php

namespace App\Http\Controllers;

use App\Models\BusinessAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Series;
use App\Models\Setting;
use App\Models\User;
use App\Models\Variant;
use App\Notifications\StaffLoginCode;
use App\Services\Audit;
use App\Services\CartService;
use App\Services\LoyaltyService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function login(Request $r, CartService $cartService)
    {
        $r->merge(['login' => strtolower(trim($r->input('login', '')))]);
        $d = $r->validate(['login' => 'required|string|max:150', 'password' => 'required|string']);
        $field = filter_var($d['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        if (! Auth::attempt([$field => $d['login'], 'password' => $d['password'], 'active' => true], $r->boolean('remember'))) {
            throw ValidationException::withMessages(['login' => 'Email, nomor telepon, atau kata sandi tidak sesuai.']);
        }
        $user = $r->user();
        if ($user->role !== 'customer' && ($user->two_factor_enabled || Setting::get('staff_two_factor_required', false))) {
            $code = (string) random_int(100000, 999999);
            Auth::logout();
            $r->session()->regenerate();
            $r->session()->put('staff_two_factor', [
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10)->timestamp,
                'remember' => $r->boolean('remember'),
            ]);
            $user->notify(new StaffLoginCode($code));

            return redirect()->route('two-factor.challenge');
        }
        $r->session()->regenerate();
        $merged = $cartService->merge($r->user(), $r->session()->get('cart', []), $r->session()->get('cart_customizations', []));
        $r->session()->put(['cart' => $merged['cart'], 'cart_customizations' => $merged['customizations']]);

        return redirect()->intended($r->user()->role === 'customer' ? route('account') : route('admin.dashboard'));
    }

    public function twoFactorChallenge(Request $request)
    {
        if (! $request->session()->has('staff_two_factor')) {
            return redirect()->route('login');
        }

        return view('store.two-factor');
    }

    public function twoFactorVerify(Request $request, CartService $cartService)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $challenge = $request->session()->get('staff_two_factor');
        if (! is_array($challenge) || ($challenge['expires_at'] ?? 0) < now()->timestamp || ! Hash::check($data['code'], $challenge['code_hash'] ?? '')) {
            throw ValidationException::withMessages(['code' => 'Kode verifikasi tidak valid atau sudah kedaluwarsa. Silakan masuk kembali.']);
        }
        $user = User::whereKey($challenge['user_id'])->where('active', true)->where('role', '!=', 'customer')->firstOrFail();
        Auth::login($user, (bool) ($challenge['remember'] ?? false));
        $request->session()->forget('staff_two_factor');
        $request->session()->regenerate();
        $merged = $cartService->merge($user, $request->session()->get('cart', []), $request->session()->get('cart_customizations', []));
        $request->session()->put(['cart' => $merged['cart'], 'cart_customizations' => $merged['customizations']]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function register(Request $r, CartService $cartService)
    {
        $r->merge(['email' => strtolower(trim($r->input('email', '')))]);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:150|unique:users', 'phone' => ['required', 'regex:/^\+?[0-9]{9,15}$/', 'unique:users'], 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()], 'terms' => 'accepted', 'marketing_email' => 'nullable|boolean', 'marketing_whatsapp' => 'nullable|boolean']);
        $u = User::create($d + ['marketing_email' => $r->boolean('marketing_email'), 'marketing_whatsapp' => $r->boolean('marketing_whatsapp'), 'consent_updated_at' => now()]);
        Auth::login($u);
        $r->session()->regenerate();
        $cartService->sync($u, $r->session()->get('cart', []), $r->session()->get('cart_customizations', []));

        return redirect()->route('account');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function account(Request $r)
    {
        $loyalty = app(LoyaltyService::class);

        return view('store.account', [
            'orders' => $r->user()->orders()->latest()->paginate(10),
            'addresses' => $r->user()->addresses,
            'businessAccount' => $r->user()->businessAccount()->with('priceGroup')->first(),
            'customQuotes' => $r->user()->customQuotes()->with('variant.series')->latest()->get(),
            'wishlist' => $r->user()->wishlistItems()->with('series.category')->latest()->get(),
            'reviewableItems' => OrderItem::with('order')->whereHas('order', fn ($query) => $query->where('user_id', $r->user()->id)->whereIn('fulfillment_status', ['received']))->doesntHave('review')->latest()->get(),
            'myReviews' => $r->user()->reviews()->with('variant.series')->latest()->get(),
            'loyaltyBalance' => $loyalty->balance($r->user()),
            'loyaltyTier' => $loyalty->tier($r->user()),
            'loyaltyTransactions' => $r->user()->loyaltyTransactions()->latest()->take(20)->get(),
        ]);
    }

    public function bulkOrder(Request $request, CartService $cartService)
    {
        $data = $request->validate(['items' => 'required|string|max:10000']);
        $account = $request->user()->businessAccount;
        if (! $account || $account->status !== 'approved' || ($account->expires_at && $account->expires_at->isPast())) {
            throw ValidationException::withMessages(['items' => 'Portal pesanan massal hanya tersedia untuk akun bisnis aktif.']);
        }
        $rows = collect(preg_split('/\r\n|\r|\n/', trim($data['items'])))->filter();
        if ($rows->count() > 100) {
            throw ValidationException::withMessages(['items' => 'Maksimal 100 baris SKU per unggahan.']);
        }
        $quantities = [];
        foreach ($rows as $index => $row) {
            $parts = array_map('trim', preg_split('/[|,;]/', $row));
            if (count($parts) !== 2 || ! preg_match('/^[A-Za-z0-9._-]+$/', $parts[0]) || filter_var($parts[1], FILTER_VALIDATE_INT) === false || (int) $parts[1] < 1) {
                throw ValidationException::withMessages(['items' => 'Baris '.($index + 1).' harus memakai format SKU | jumlah.']);
            }
            $sku = strtoupper($parts[0]);
            $quantities[$sku] = ($quantities[$sku] ?? 0) + (int) $parts[1];
            if ($quantities[$sku] > 100) {
                throw ValidationException::withMessages(['items' => "Jumlah {$sku} maksimal 100 item."]);
            }
        }
        $variants = Variant::with('series.category')->whereIn('sku', array_keys($quantities))->get()->keyBy(fn ($variant) => strtoupper($variant->sku));
        if ($variants->count() !== count($quantities)) {
            $missing = collect(array_keys($quantities))->diff($variants->keys())->implode(', ');
            throw ValidationException::withMessages(['items' => 'SKU tidak ditemukan: '.$missing]);
        }
        $cart = [];
        foreach ($quantities as $sku => $quantity) {
            $variant = $variants[$sku];
            if (! $variant->active || ! $variant->series->active || ! $variant->series->category->active || $variant->fulfillment === 'custom_made') {
                throw ValidationException::withMessages(['items' => "SKU {$sku} tidak tersedia untuk pesanan massal."]);
            }
            $cart[$variant->id] = $quantity;
        }
        $request->session()->put(['cart' => $cart, 'cart_customizations' => []]);
        $cartService->sync($request->user(), $cart);

        return redirect()->route('cart')->with('success', count($cart).' SKU dimasukkan ke keranjang. Tinjau harga, minimum, dan stok sebelum checkout.');
    }

    public function wishlist(Request $request, Series $series)
    {
        $item = $request->user()->wishlistItems()->where('series_id', $series->id)->first();
        if ($item) {
            $item->delete();
            $message = 'Produk dihapus dari wishlist.';
        } else {
            $request->user()->wishlistItems()->create(['series_id' => $series->id]);
            $message = 'Produk disimpan ke wishlist.';
        }

        return back()->with('success', $message);
    }

    public function communicationPreferences(Request $request)
    {
        $request->user()->update(['marketing_email' => $request->boolean('marketing_email'), 'marketing_whatsapp' => $request->boolean('marketing_whatsapp'), 'consent_updated_at' => now()]);
        Audit::record('customer.communication_preferences', 'user:'.$request->user()->id, null, $request->user()->only('marketing_email', 'marketing_whatsapp'));

        return back()->with('success', 'Preferensi komunikasi diperbarui.');
    }

    public function claimOrder(Request $request, Order $order)
    {
        abort_unless($order->user_id === null, 422);
        $matches = ($order->email && mb_strtolower($order->email) === mb_strtolower($request->user()->email)) || trim($order->phone) === trim((string) $request->user()->phone);
        abort_unless($matches, 403);
        $order->update(['user_id' => $request->user()->id, 'claimed_at' => now()]);
        Audit::record('order.claimed', 'order:'.$order->id, ['user_id' => null], ['user_id' => $request->user()->id]);

        return redirect($order->customerUrl())->with('success', 'Pesanan telah ditambahkan ke riwayat akun.');
    }

    public function reorder(Request $request, Order $order, CartService $cartService)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        $order->load('items');
        $available = Variant::whereIn('id', $order->items->pluck('variant_id'))->where('active', true)->where('fulfillment', '!=', 'custom_made')->pluck('id')->all();
        $cart = $request->session()->get('cart', []);
        foreach ($order->items->whereIn('variant_id', $available) as $item) {
            $cart[$item->variant_id] = min(100, (int) ($cart[$item->variant_id] ?? 0) + $item->quantity);
        }
        $cart = array_slice($cart, 0, 50, true);
        $request->session()->put('cart', $cart);
        $request->session()->forget('checkout_key');
        $cartService->sync($request->user(), $cart, $request->session()->get('cart_customizations', []));
        $added = $order->items->whereIn('variant_id', $available)->count();

        return redirect()->route('cart')->with('success', $added.' item tersedia ditambahkan kembali. Produk custom perlu diukur ulang.');
    }

    public function review(Request $request, OrderItem $item)
    {
        $item->load('order');
        abort_unless($item->order->user_id === $request->user()->id && $item->order->fulfillment_status === 'received', 403);
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'body' => 'required|string|min:10|max:3000', 'photos' => 'nullable|array|max:3', 'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:3072']);
        if (Review::where('order_item_id', $item->id)->exists()) {
            throw ValidationException::withMessages(['body' => 'Item pesanan ini sudah memiliki ulasan.']);
        }
        $photos = [];
        foreach ($request->file('photos', []) as $photo) {
            $photos[] = $photo->store('reviews', 'public');
        }
        try {
            $review = Review::create(['user_id' => $request->user()->id, 'order_item_id' => $item->id, 'variant_id' => $item->variant_id, 'rating' => $data['rating'], 'body' => $data['body'], 'photos' => $photos]);
            Audit::record('review.submitted', 'review:'.$review->id, null, $review->only('rating', 'variant_id'));
        } catch (\Throwable $exception) {
            foreach ($photos as $photo) {
                Storage::disk('public')->delete($photo);
            }
            throw $exception;
        }

        return back()->with('success', 'Ulasan dikirim dan menunggu moderasi.');
    }

    public function businessApplication(Request $r)
    {
        abort_unless($r->user()->role === 'customer', 403);
        $data = $r->validate([
            'business_type' => 'required|in:reseller,dropshipper,wholesale',
            'business_name' => 'required|string|max:150',
            'sales_channel' => 'required|string|max:150',
            'notes' => 'nullable|string|max:2000',
            'legal_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        $existing = $r->user()->businessAccount;
        if ($existing?->status === 'approved') {
            throw ValidationException::withMessages(['business_type' => 'Akun bisnis yang sudah disetujui tidak dapat diajukan ulang. Hubungi Dthree untuk perubahan grup.']);
        }

        $document = $r->file('legal_document');
        $documentPath = $document?->store('business-documents', 'local');
        unset($data['legal_document']);
        DB::transaction(function () use ($r, $data, $existing, $document, $documentPath) {
            $before = $existing?->toArray();
            $documentData = $document ? ['legal_document_path' => $documentPath, 'legal_document_name' => $document->getClientOriginalName()] : [];
            $account = BusinessAccount::updateOrCreate(['user_id' => $r->user()->id], $data + $documentData + [
                'status' => 'pending', 'price_group_id' => null, 'reviewed_by' => null,
                'review_notes' => null, 'submitted_at' => now(), 'reviewed_at' => null,
            ]);
            Audit::record('business.application_submitted', 'business_account:'.$account->id, $before, $account->toArray());
        });

        return back()->with('success', 'Pengajuan akun bisnis dikirim dan menunggu peninjauan Dthree.');
    }

    public function address(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'phone' => 'required|string|max:30', 'province' => 'required|string|max:100', 'city' => 'required|string|max:100', 'district' => 'required|string|max:100', 'postal_code' => 'required|digits:5', 'detail' => 'required|string|max:1000']);
        DB::transaction(function () use ($r, $d) {
            $makeDefault = $r->boolean('is_default') || ! $r->user()->addresses()->exists();
            if ($makeDefault) {
                $r->user()->addresses()->update(['is_default' => false]);
            }
            $r->user()->addresses()->create($d + ['is_default' => $makeDefault]);
        });

        return back()->with('success', 'Alamat disimpan.');
    }

    public function deleteAddress(Request $r, int $id)
    {
        DB::transaction(function () use ($r, $id) {
            $address = $r->user()->addresses()->findOrFail($id);
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault) {
                $r->user()->addresses()->oldest('id')->first()?->update(['is_default' => true]);
            }
        });

        return back()->with('success', 'Alamat dihapus.');
    }

    public function defaultAddress(Request $r, int $id)
    {
        DB::transaction(function () use ($r, $id) {
            $address = $r->user()->addresses()->findOrFail($id);
            $r->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return back()->with('success', 'Alamat utama diperbarui.');
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));

        return back()->with('success', 'Jika email terdaftar, tautan penggantian kata sandi akan dikirim.');
    }

    public function reset(Request $r)
    {
        $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function (User $u, string $password) {
            $u->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($u));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('success', 'Kata sandi diperbarui. Silakan masuk.');
    }
}
