@extends('layouts.store')
@section('title','Verifikasi dua langkah')
@section('content')
<section class="container"><div class="auth-panel panel"><span class="eyebrow">KEAMANAN AKUN</span><h1 class="my-4">Masukkan kode verifikasi</h1><p>Kami mengirim kode enam digit ke email akun staf Anda. Kode berlaku selama 10 menit.</p><form method="post" action="{{ route('two-factor.verify') }}">@csrf<label for="code">Kode verifikasi</label><input id="code" class="form-control mb-3" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus><button class="btn btn-dark w-100">Verifikasi dan masuk</button></form><a class="d-block text-center small mt-4" href="{{ route('login') }}">Kembali ke halaman masuk</a></div></section>
@endsection
