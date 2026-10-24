@extends('layouts.store')
@section('title','Tinjau pesanan — Dthree')
@section('content')
<section class="container-xxl section page-narrow checkout-review-page">
    <span class="eyebrow">02 TINJAU PESANAN</span><h1 class="my-4">Satu langkah lagi.</h1>
    @if($quote['price_group'])<div class="alert alert-success">Harga akun <strong>{{ $quote['price_group']->name }}</strong> sudah diterapkan dan akan disimpan pada pesanan.</div>@endif
    <div class="panel"><h2>Pastikan pilihan Anda</h2>
        @foreach($quote['lines'] as $line)
            <div class="summary-row"><span>{{ $line['variant']->series->name }}<br><small>{{ $line['variant']->label }} × {{ $line['quantity'] }} · @rupiah($line['unit_price'])/item @if($line['price_tier_minimum'])· tier {{ $line['price_tier_minimum'] }}+@endif @if($line['bundle'])<br>Isi: @foreach($line['bundle'] as $component){{ $component['sku'] }} × {{ $component['quantity'] }}{{ !$loop->last?' · ':'' }}@endforeach @endif</small></span><span>@rupiah($line['total'])</span></div>
        @endforeach
        <p class="mt-4"><strong>{{ $data['name'] }}</strong> · {{ $data['phone'] }}<br>{{ $data['detail'] }}<br>{{ $data['district'] }}, {{ $data['city'] }}, {{ $data['province'] }} {{ $data['postal_code'] }}</p>
        <p>{{ $quote['rate']->courier }} {{ $quote['rate']->service }} · {{ $quote['rate']->estimate }}<br>Pembayaran: {{ ['cod'=>'COD','credit'=>'Termin kredit','transfer'=>'Transfer bank'][$data['payment_method']] }}</p>
        @if(!empty($data['sender_name']))<p><strong>Pengirim dropship:</strong> {{ $data['sender_name'] }} @if(!empty($data['sender_phone']))· {{ $data['sender_phone'] }}@endif</p>@endif
        @foreach(['subtotal'=>'Subtotal produk','custom_cost'=>'Biaya custom','shipping_cost'=>'Ongkir','discount'=>'Diskon voucher','loyalty_discount'=>'Potongan loyalty','shipping_discount'=>'Subsidi ongkir','cod_fee'=>'Biaya COD'] as $key=>$label)<div class="summary-row"><span>{{ $label }}</span><span>{{ in_array($key,['discount','loyalty_discount','shipping_discount'])?'− ':'' }}@rupiah($quote[$key])</span></div>@endforeach
        <div class="summary-row total"><span>Total</span><span>@rupiah($quote['total'])</span></div>
        <form action="{{ route('checkout.place') }}" method="post">@csrf<input type="hidden" name="checkout_key" value="{{ session('checkout_key') }}"><button class="btn btn-dark w-100">Buat pesanan · @rupiah($quote['total'])</button></form>
        <a class="d-block text-center mt-3 small" href="{{ route('checkout') }}">Ubah alamat atau pengiriman</a>
    </div>
</section>
@endsection
