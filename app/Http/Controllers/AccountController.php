<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
        return view('store.account', ['orders' => $r->user()->orders()->latest()->paginate(10), 'addresses' => $r->user()->addresses]);
    }

    public function address(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'phone' => 'required|string|max:30', 'province' => 'required|string|max:100', 'city' => 'required|string|max:100', 'district' => 'required|string|max:100', 'postal_code' => 'required|digits:5', 'detail' => 'required|string|max:1000']);
        $r->user()->addresses()->create($d);

        return back()->with('success', 'Alamat disimpan.');
    }

    public function deleteAddress(Request $r, int $id)
    {
        $r->user()->addresses()->findOrFail($id)->delete();

        return back()->with('success', 'Alamat dihapus.');
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

        return redirect()->route('login')->with('success','Kata sandi diperbarui. Silakan masuk.');
    }
}
