@extends('layouts.store')
@section('title','Keranjang — Asfara')
@section('body_class','cart-body')
@section('content')
<section class="container-xxl section cart-page">
    @php($cartSubtotal=array_sum(array_column($lines,'total')))
    @php($cartItemCount=array_sum(array_column($lines,'quantity')))
    @php($shippingTarget=max(0,(int)($shop['free_shipping_threshold'] ?? 500000)))
    @php($shippingRemaining=max(0,$shippingTarget-$cartSubtotal))
    @if(empty($lines))
        <div class="empty-state cart-empty"><h1>{{ strtoupper(__('store.cart')) }}</h1><p>{{ __('store.empty_cart') }}</p><a class="btn btn-dark" href="{{ route('catalog') }}">{{ strtoupper(__('store.start_shopping')) }}</a></div>
    @else
        <header class="cart-page-header">
            <div><span class="eyebrow">PILIHAN ANDA</span><h1>{{ $storeCopy['cart_title'] }}</h1></div>
            <p><strong>{{ $cartItemCount }}</strong> item di keranjang</p>
        </header>
        @if($lines[0]['price_group'])
            <div class="alert alert-success">Harga akun <strong>{{ $lines[0]['price_group']->name }}</strong> diterapkan. Tier dihitung per SKU berdasarkan jumlah di keranjang.</div>
        @endif
        <div class="row g-5 cart-layout">
            <div class="col-lg-8">
                @foreach($lines as $line)
                    @php($v=$line['variant'])
                    <div class="cart-item">
                        <img src="{{ $v->series->image_url }}" alt="{{ $v->series->name }}" width="240" height="300" loading="lazy" decoding="async">
                        <div class="flex-grow-1">
                            <h2>{{ $v->series->name }}</h2>
                            <p class="cart-line-meta">{{ $v->label }} · {{ $v->sku }}<br>@rupiah($line['unit_price']) / item @if($line['price_tier_minimum'])· Tier {{ $line['price_tier_minimum'] }}+@endif</p>
                            <span class="cart-stock-status">READY STOCK · {{ $v->available }} TERSEDIA</span>
                            @if($line['customization'])<p class="small"><strong>Ukuran custom:</strong> @foreach($line['customization']['measurements'] as $key=>$value){{ $line['customization']['template']['fields'][$loop->index]['label'] ?? $key }} {{ $value }}{{ $line['customization']['template']['fields'][$loop->index]['unit'] ?? '' }}{{ !$loop->last?' · ':'' }}@endforeach<br>Biaya custom @rupiah($line['custom_cost']) · {{ $line['customization']['lead_time'] }}</p>@endif
                            @if($line['bundle'])<p class="small"><strong>Isi bundle:</strong> @foreach($line['bundle'] as $component){{ $component['sku'] }} × {{ $component['quantity'] }}{{ !$loop->last?' · ':'' }}@endforeach</p>@endif
                            <form method="post" action="{{ route('cart.update') }}" class="cart-line-actions" data-cart-line-form>@csrf
                                <input type="hidden" name="variant_id" value="{{ $v->id }}">
                                <label class="visually-hidden" for="qty-{{ $v->id }}">Jumlah {{ $v->sku }}</label>
                                <div class="cart-line-quantity" aria-label="Atur jumlah {{ $v->series->name }}">
                                    <button type="button" data-cart-quantity="-1" aria-label="Kurangi jumlah">−</button>
                                    <input id="qty-{{ $v->id }}" class="quantity" type="number" name="quantity" value="{{ $line['quantity'] }}" min="1" max="{{ min(100,$v->available) }}">
                                    <button type="button" data-cart-quantity="1" aria-label="Tambah jumlah">+</button>
                                </div>
                                <button class="cart-line-update">PERBARUI</button>
                                <button class="cart-line-remove" name="quantity" value="0">HAPUS</button>
                            </form>
                        </div>
                        <strong class="cart-line-total">@rupiah($line['total'])</strong>
                    </div>
                @endforeach
            </div>
            <div class="col-lg-4"><aside class="panel cart-summary">
                <h2>{{ __('store.order_summary') }}</h2>
                @if($shippingTarget>0)<div class="shipping-progress"><p>{{ $shippingRemaining>0?'Tambah Rp '.number_format($shippingRemaining,0,',','.').' lagi untuk mencapai promo gratis ongkir.':'Target promo gratis ongkir sudah tercapai.' }}</p><div><i style="width:{{ min(100,(int)round($cartSubtotal/$shippingTarget*100)) }}%"></i></div></div>@endif
                <div class="summary-row"><span>{{ __('store.subtotal') }} · {{ $cartItemCount }} item</span><strong>@rupiah($cartSubtotal)</strong></div>
                @if($lines[0]['price_group'])
                    <p class="small mt-3">Minimum {{ $lines[0]['price_group']->minimum_order_quantity }} item dan @rupiah($lines[0]['price_group']->minimum_order_amount) per pesanan.</p>
                @endif
                <p class="small mt-3">Ongkir, voucher, dan biaya COD dihitung pada langkah berikutnya. Harga dan stok diperiksa kembali sebelum pesanan dibuat.</p>
                <ul class="cart-assurances"><li>Transaksi dan data pelanggan terlindungi</li><li>Total akhir ditampilkan sebelum konfirmasi</li><li>Bantuan pelanggan tersedia via WhatsApp</li></ul>
                <a class="btn btn-dark w-100" data-cart-page-checkout href="{{ route('checkout') }}">{{ __('store.continue_checkout') }}</a>
                <a class="d-block text-center small mt-3" href="{{ route('catalog') }}">Lanjut belanja</a>
            </aside></div>
        </div>
    @endif
</section>
@if(!empty($lines) && ($shop['cart_recommendations_enabled'] ?? true) && $recommendations->isNotEmpty())<section class="recently-viewed container-xxl"><span class="eyebrow">{{ $storeCopy['cart_recommendations'] }}</span><h2>{{ $storeCopy['related_products'] }}</h2><div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@foreach($recommendations as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div></section>@endif
@if($recentlyViewed->isNotEmpty())<section class="recently-viewed container-xxl"><h2>{{ $storeCopy['recently_viewed'] }}</h2><div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@foreach($recentlyViewed as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div></section>@endif
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-cart-line-form]').forEach(form => {
    const input = form.querySelector('.quantity');
    form.querySelectorAll('[data-cart-quantity]').forEach(button => button.addEventListener('click', () => {
        const minimum = Number(input.min) || 1;
        const maximum = Number(input.max) || 100;
        input.value = Math.min(maximum, Math.max(minimum, Number(input.value || minimum) + Number(button.dataset.cartQuantity)));
        form.classList.add('is-dirty');
    }));
    input.addEventListener('input', () => form.classList.add('is-dirty'));
});
</script>
@endpush
