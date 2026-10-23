@extends('layouts.admin')
@section('title','Keamanan akun')
@section('content')
<div class="admin-heading"><div><span class="eyebrow">AKUN STAF</span><h1>Verifikasi dua langkah</h1><p>Kode enam digit dikirim melalui email setiap kali Anda masuk ke dashboard.</p></div></div>
<form method="post" action="{{ route('admin.security.save') }}" class="panel" style="max-width:720px">@csrf
    <label class="d-block mb-3"><input type="checkbox" name="two_factor_enabled" value="1" @checked(auth()->user()->two_factor_enabled)> Aktifkan verifikasi dua langkah untuk akun saya</label>
    <p class="small">Email tujuan: <strong>{{ auth()->user()->email }}</strong>. Pastikan konfigurasi email hosting sudah aktif sebelum mengaktifkan fitur ini.</p>
    <label for="current_password">Kata sandi saat ini</label><input id="current_password" type="password" name="current_password" class="form-control mb-3" autocomplete="current-password" required>
    <button class="btn btn-dark">Simpan pengaturan keamanan</button>
</form>
@endsection
