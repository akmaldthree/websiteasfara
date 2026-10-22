@extends('layouts.store')
@section('title','Keranjang — Dthree')
@section('content')
<section class="container-xxl section">
    <span class="eyebrow">PILIHAN ANDA</span>
    <h1 class="mt-3 mb-4">Keranjang belanja</h1>
    @if(empty($lines))
        <div class="empty-state"><h2>Cerita Anda dimulai di sini.</h2><p>Temukan koleksi yang Anda suka dan tambahkan ke keranjang.</p><a class="btn btn-dark" href="{{ route('catalog') }}">Jelajahi koleksi ↗</a></div>
    @else
        @if($lines[0]['price_group'])
            <div class="alert alert-success">Harga akun <strong>{{ $lines[0]['price_group']->name }}</strong> diterapkan. Tier dihitung per SKU berdasarkan jumlah di keranjang.</div>
        @endif
        <div class="row g-5">
            <div class="col-lg-8">
                @foreach($lines as $line)
                    @php($v=$line['variant'])
                    <div class="cart-item">
                        <img src="{{ $v->series->image_url }}" alt="{{ $v->series->name }}">
                        <div class="flex-grow-1">
                            <h2>{{ $v->series->name }}</h2>
                            <p>{{ $v->label }} · {{ $v->sku }}<br>@rupiah($line['unit_price']) / item @if($line['price_tier_minimum'])· Tier {{ $line['price_tier_minimum'] }}+@endif · Tersedia {{ $v->available }}</p>
                            <form method="post" action="{{ route('cart.update') }}">@csrf
                                <input type="hidden" name="variant_id" value="{{ $v->id }}">
                                <label class="visually-hidden" for="qty-{{ $v->id }}">Jumlah {{ $v->sku }}</label>
                                <input id="qty-{{ $v->id }}" class="form-control quantity" type="number" name="quantity" value="{{ $line['quantity'] }}" min="0" max="100">
                                <button class="btn btn-outline-dark btn-sm">Perbarui</button>
                                <button class="btn btn-link btn-sm" name="quantity" value="0">Hapus</button>
                            </form>
                        </div>
                        <strong>@rupiah($line['total'])</strong>
                    </div>
                @endforeach
            </div>
            <div class="col-lg-4"><aside class="panel">
                <h2>Ringkasan belanja</h2>
                <div class="summary-row"><span>Subtotal</span><strong>@rupiah(array_sum(array_column($lines,'total')))</strong></div>
                @if($lines[0]['price_group'])
                    <p class="small mt-3">Minimum {{ $lines[0]['price_group']->minimum_order_quantity }} item dan @rupiah($lines[0]['price_group']->minimum_order_amount) per pesanan.</p>
                @endif
                <p class="small mt-3">Ongkir, voucher, dan biaya COD dihitung pada langkah berikutnya. Harga dan stok diperiksa kembali sebelum pesanan dibuat.</p>
                <a class="btn btn-dark w-100" href="{{ route('checkout') }}">Lanjut ke checkout ↗</a>
                <a class="d-block text-center small mt-3" href="{{ route('catalog') }}">Lanjut belanja</a>
            </aside></div>
        </div>
    @endif
</section>
@endsection
