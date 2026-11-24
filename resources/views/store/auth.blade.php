@extends('layouts.store')
@section('title','Akun — Asfara')
@section('body_class','auth-body')
@php
    $heading = $mode==='register' ? 'Buat akun Asfara' : ($mode==='forgot' ? 'Pulihkan akun Anda' : ($mode==='reset' ? 'Buat kata sandi baru' : 'Senang bertemu Anda.'));
    $intro = $mode==='register' ? 'Simpan alamat, pantau pesanan, dan kumpulkan benefit dalam satu akun.' : ($mode==='forgot' ? 'Masukkan email akun dan kami akan mengirim tautan pemulihan.' : ($mode==='reset' ? 'Gunakan kata sandi yang kuat agar akun Anda tetap terlindungi.' : 'Masuk untuk melanjutkan pengalaman belanja dan melihat pesanan Anda.'));
@endphp
@section('content')
<section class="auth-shell">
    <aside class="auth-story">
        <span class="eyebrow">ASFARA MEMBERSHIP</span>
        <h2>Lebih dekat dengan setiap pilihan Anda.</h2>
        <p>Akun Asfara membantu Anda menyimpan detail penting dan mengikuti perjalanan pesanan dengan lebih mudah.</p>
        <ul><li>Riwayat dan status pesanan tersimpan</li><li>Alamat checkout siap digunakan kembali</li><li>Wishlist dan benefit loyalitas dalam satu tempat</li></ul>
        <small>Privasi Anda dijaga. Preferensi promosi dapat diubah kapan saja.</small>
    </aside>
    <div class="auth-panel panel">
        <span class="eyebrow">{{ $mode==='login'?'SELAMAT DATANG KEMBALI':'MULAI BERSAMA ASFARA' }}</span>
        <h1>{{ $heading }}</h1>
        <p class="auth-intro">{{ $intro }}</p>
        <form method="post" action="{{ $mode==='login'?route('login.submit'):($mode==='register'?route('register.submit'):($mode==='forgot'?route('password.email'):route('password.update'))) }}">@csrf
            @if($mode==='reset')<input type="hidden" name="token" value="{{ request()->route('token') }}">@endif
            @if($mode==='register')
                <label for="name">Nama lengkap</label><input id="name" class="form-control mb-3" name="name" value="{{ old('name') }}" autocomplete="name" required>
                <label for="phone">Nomor WhatsApp / telepon</label><input id="phone" class="form-control mb-3" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" required>
            @endif
            @if($mode==='login')
                <label for="login">Email atau nomor telepon</label><input id="login" class="form-control mb-3" name="login" value="{{ old('login') }}" autocomplete="username" required>
            @else
                <label for="email">Email</label><input type="email" id="email" class="form-control mb-3" name="email" value="{{ old('email',request('email')) }}" autocomplete="email" required>
            @endif
            @if($mode!=='forgot')
                <label for="password">Kata sandi</label><input type="password" id="password" class="form-control mb-2" name="password" autocomplete="{{ $mode==='login'?'current-password':'new-password' }}" required>
                @if($mode!=='login')<small class="auth-field-help">Minimal 12 karakter dengan kombinasi huruf dan angka.</small>@endif
            @endif
            @if(in_array($mode,['register','reset']))
                <label for="password_confirmation" class="mt-3">Ulangi kata sandi</label><input type="password" id="password_confirmation" class="form-control mb-3" name="password_confirmation" autocomplete="new-password" required>
            @endif
            @if($mode==='register')
                <label class="auth-check"><input type="checkbox" name="terms" value="1" required><span>Saya menyetujui <a href="{{ route('page','syarat-ketentuan') }}">syarat dan ketentuan</a> serta <a href="{{ route('page','privasi') }}">kebijakan privasi</a>.</span></label>
                <label class="auth-check"><input type="checkbox" name="marketing_email" value="1"><span>Saya bersedia menerima informasi promo melalui email.</span></label>
                <label class="auth-check"><input type="checkbox" name="marketing_whatsapp" value="1"><span>Saya bersedia menerima informasi promo melalui WhatsApp.</span></label>
            @endif
            <button class="btn btn-dark w-100 mt-3">{{ $mode==='login'?'Masuk':($mode==='register'?'Daftar':($mode==='forgot'?'Kirim tautan pemulihan':'Simpan kata sandi')) }}</button>
        </form>
        @if($mode==='login')<div class="auth-links"><a href="{{ route('register') }}">Buat akun baru</a><a href="{{ route('password.request') }}">Lupa kata sandi?</a></div>@else<a class="auth-back" href="{{ route('login') }}">Kembali ke halaman masuk</a>@endif
    </div>
</section>
@endsection
