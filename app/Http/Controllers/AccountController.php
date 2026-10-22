<?php

namespace App\Http\Controllers;

use App\Models\BusinessAccount;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use App\Models\Variant;
use App\Services\Audit;
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
    public function login(Request $r)
    {
        $r->merge(['email' => strtolower(trim($r->input('email', '')))]);
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($d + ['active' => true], $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }
        $r->session()->regenerate();

        return redirect()->intended($r->user()->role === 'customer' ? route('account') : route('admin.dashboard'));
    }

    public function register(Request $r)
    {
        $r->merge(['email' => strtolower(trim($r->input('email', '')))]);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:150|unique:users', 'phone' => ['required', 'regex:/^\+?[0-9]{9,15}$/'], 'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()], 'terms' => 'accepted']);
        $u = User::create($d);
        Auth::login($u);
        $r->session()->regenerate();

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
        return view('store.account', [
            'orders' => $r->user()->orders()->latest()->paginate(10),
            'addresses' => $r->user()->addresses,
            'businessAccount' => $r->user()->businessAccount()->with('priceGroup')->first(),
            'customQuotes' => $r->user()->customQuotes()->with('variant.series')->latest()->get(),
            'wishlist' => $r->user()->wishlistItems()->with('variant.series')->latest()->get(),
            'reviewableItems' => OrderItem::with('order')->whereHas('order', fn ($query) => $query->where('user_id', $r->user()->id)->whereIn('fulfillment_status', ['received']))->doesntHave('review')->latest()->get(),
            'myReviews' => $r->user()->reviews()->with('variant.series')->latest()->get(),
            'loyaltyBalance' => app(LoyaltyService::class)->balance($r->user()),
            'loyaltyTransactions' => $r->user()->loyaltyTransactions()->latest()->take(20)->get(),
        ]);
    }

    public function wishlist(Request $request, Variant $variant)
    {
        $item = $request->user()->wishlistItems()->where('variant_id', $variant->id)->first();
        if ($item) {
            $item->delete();
            $message = 'Produk dihapus dari wishlist.';
        } else {
            $request->user()->wishlistItems()->create(['variant_id' => $variant->id]);
            $message = 'Produk disimpan ke wishlist.';
        }

        return back()->with('success', $message);
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
        ]);
        $existing = $r->user()->businessAccount;
        if ($existing?->status === 'approved') {
            throw ValidationException::withMessages(['business_type' => 'Akun bisnis yang sudah disetujui tidak dapat diajukan ulang. Hubungi Dthree untuk perubahan grup.']);
        }

        DB::transaction(function () use ($r, $data, $existing) {
            $before = $existing?->toArray();
            $account = BusinessAccount::updateOrCreate(['user_id' => $r->user()->id], $data + [
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
