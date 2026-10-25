@extends('layouts.store')
@section('title','Keranjang — Dthree')
@section('content')
<section class="container-xxl section cart-page">
    @php($cartSubtotal=array_sum(array_column($lines,'total')))
    @php($shippingTarget=max(0,(int)($shop['free_shipping_threshold'] ?? 500000)))
    @php($shippingRemaining=max(0,$shippingTarget-$cartSubtotal))
    @if(empty($lines))
        <div class="empty-state cart-empty"><h1>{{ strtoupper(__('store.cart')) }}</h1><p>{{ __('store.empty_cart') }}</p><a class="btn btn-dark" href="{{ route('catalog') }}">{{ strtoupper(__('store.start_shopping')) }}</a></div>
    @else
        <span class="eyebrow">PILIHAN ANDA</span><h1 class="mt-3 mb-4">{{ __('store.cart') }}</h1>
        @if($lines[0]['price_group'])
            <div class="alert alert-success">Harga akun <strong>{{ $lines[0]['price_group']->name }}</strong> diterapkan. Tier dihitung per SKU berdasarkan jumlah di keranjang.</div>
        @endif
        <div class="row g-5 cart-layout">
            <div class="col-lg-8">
                @foreach($lines as $line)
                    @php($v=$line['variant'])
                    <div class="cart-item">
                        <img src="{{ $v->series->image_url }}" alt="{{ $v->series->name }}">
                        <div class="flex-grow-1">
                            <h2>{{ $v->series->name }}</h2>
                            <p>{{ $v->label }} · {{ $v->sku }}<br>@rupiah($line['unit_price']) / item @if($line['price_tier_minimum'])· Tier {{ $line['price_tier_minimum'] }}+@endif · Tersedia {{ $v->available }}</p>
                            @if($line['customization'])<p class="small"><strong>Ukuran custom:</strong> @foreach($line['customization']['measurements'] as $key=>$value){{ $line['customization']['template']['fields'][$loop->index]['label'] ?? $key }} {{ $value }}{{ $line['customization']['template']['fields'][$loop->index]['unit'] ?? '' }}{{ !$loop->last?' · ':'' }}@endforeach<br>Biaya custom @rupiah($line['custom_cost']) · {{ $line['customization']['lead_time'] }}</p>@endif
                            @if($line['bundle'])<p class="small"><strong>Isi bundle:</strong> @foreach($line['bundle'] as $component){{ $component['sku'] }} × {{ $component['quantity'] }}{{ !$loop->last?' · ':'' }}@endforeach</p>@endif
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
            <div class="col-lg-4"><aside class="panel cart-summary">
                <h2>{{ __('store.order_summary') }}</h2>
                @if($shippingTarget>0)<div class="shipping-progress"><p>{{ $shippingRemaining>0?'Tambah Rp '.number_format($shippingRemaining,0,',','.').' lagi untuk mencapai promo gratis ongkir.':'Target promo gratis ongkir sudah tercapai.' }}</p><div><i style="width:{{ min(100,(int)round($cartSubtotal/$shippingTarget*100)) }}%"></i></div></div>@endif
                <div class="summary-row"><span>{{ __('store.subtotal') }}</span><strong>@rupiah($cartSubtotal)</strong></div>
                @if($lines[0]['price_group'])
                    <p class="small mt-3">Minimum {{ $lines[0]['price_group']->minimum_order_quantity }} item dan @rupiah($lines[0]['price_group']->minimum_order_amount) per pesanan.</p>
                @endif
                <p class="small mt-3">Ongkir, voucher, dan biaya COD dihitung pada langkah berikutnya. Harga dan stok diperiksa kembali sebelum pesanan dibuat.</p>
                <a class="btn btn-dark w-100" href="{{ route('checkout') }}">{{ __('store.continue_checkout') }} ↗</a>
                <a class="d-block text-center small mt-3" href="{{ route('catalog') }}">Lanjut belanja</a>
            </aside></div>
        </div>
    @endif
</section>
@if(!empty($lines) && ($shop['cart_recommendations_enabled'] ?? true) && $recommendations->isNotEmpty())<section class="recently-viewed container-xxl"><span class="eyebrow">LENGKAPI PILIHAN ANDA</span><h2>YOU MAY ALSO LIKE</h2><div class="product-carousel" data-drag-scroll>@foreach($recommendations as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div></section>@endif
@if($recentlyViewed->isNotEmpty())<section class="recently-viewed container-xxl"><h2>RECENTLY VIEWED PRODUCTS</h2><div class="product-carousel" data-drag-scroll>@foreach($recentlyViewed as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div></section>@endif
@endsection
