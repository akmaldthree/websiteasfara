@extends('layouts.store')
@section('title','Lacak pesanan — Asfara')
@section('body_class','track-body')
@section('content')
<section class="container-xxl section support-page">
    <header class="support-page-header"><span class="eyebrow">DARI KAMI, MENUJU ANDA</span><h1>Lacak pesanan</h1><p>Lihat perkembangan pesanan menggunakan informasi yang sama seperti saat checkout.</p></header>
    <div class="support-page-grid">
        <form class="panel tracking-panel" method="post" action="{{ route('track.find') }}">@csrf
            <span class="eyebrow">STATUS PESANAN</span><h2>Masukkan detail pesanan</h2><p>Nomor pesanan tersedia pada halaman konfirmasi dan pesan transaksi Asfara.</p>
            <label for="number">Nomor pesanan</label><input class="form-control mb-4" id="number" name="number" placeholder="ASF-261124-XXXXXXXX" value="{{ old('number') }}" autocomplete="off" required>
            <label for="phone">Nomor WhatsApp / telepon</label><input class="form-control mb-4" type="tel" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" required>
            <button class="btn btn-dark w-100">Lihat status pesanan</button>
            <small>Data ini hanya digunakan untuk mencocokkan pesanan Anda.</small>
        </form>
        <aside class="tracking-help"><span class="eyebrow">PERLU BANTUAN?</span><h2>Perjalanan pesanan yang transparan.</h2><ol><li><b>Pesanan diterima</b><span>Detail pesanan dan pembayaran diperiksa.</span></li><li><b>Pesanan diproses</b><span>Produk disiapkan dan melewati pemeriksaan kualitas.</span></li><li><b>Pesanan dikirim</b><span>Nomor resi tampil setelah paket diserahkan ke kurir.</span></li></ol><p>Belum menemukan pesanan? Pastikan nomor telepon ditulis sama seperti saat checkout atau hubungi tim Asfara.</p></aside>
    </div>
</section>
@endsection
