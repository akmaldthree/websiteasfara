@extends('layouts.store')
@section('title',$order->number.' — Dthree')
@section('content')
<section class="container-xxl section">
<div class="section-top"><div><span class="eyebrow">PESANAN ANDA</span><h1 class="my-4">{{ $order->number }}</h1></div><a class="btn btn-outline-dark" target="_blank" href="{{ URL::signedRoute('orders.invoice',['order'=>$order->id]) }}">Invoice / cetak ↗</a></div>
<div class="d-flex flex-wrap gap-2 mb-4"><span class="badge">Pesanan: {{ __('status.'.$order->status) }}</span><span class="badge">Pembayaran: {{ __('status.'.$order->payment_status) }}</span><span class="badge">Pengiriman: {{ __('status.'.$order->fulfillment_status) }}</span></div>
@auth @if(!$order->user_id && (($order->email && mb_strtolower($order->email)===mb_strtolower(auth()->user()->email)) || trim($order->phone)===trim((string)auth()->user()->phone)))<div class="alert alert-info"><form method="post" action="{{ URL::signedRoute('orders.claim',['order'=>$order->id]) }}">@csrf Pesanan tamu ini cocok dengan akun Anda. <button class="btn btn-dark btn-sm ms-2">Tambahkan ke riwayat akun</button></form></div>@endif @endauth
<div class="row g-4"><div class="col-lg-7">
<div class="panel">@include('partials.order-summary')</div>
<div class="panel mt-4"><h2>Alamat & pengiriman</h2><p>{{ $order->name }} · {{ $order->phone }}<br>{{ $order->address['detail'] }}<br>{{ $order->address['district'] }}, {{ $order->address['city'] }}, {{ $order->address['province'] }} {{ $order->address['postal_code'] }}</p><p>{{ $order->shipping['courier'] }} {{ $order->shipping['service'] }} · {{ $order->shipping['estimate'] }}</p>@if($order->tracking_number)<p><strong>Nomor resi: {{ $order->tracking_number }}</strong></p>@endif</div>
@include('partials.refunds')
@include('partials.order-events')
</div><div class="col-lg-5">
<div class="panel"><h2>{{ $order->payment_method==='cod'?'Pembayaran di tempat':'Instruksi pembayaran' }}</h2>
@if($order->status==='cancelled')<p>Pesanan dibatalkan. Silakan lihat rincian status dan pengembalian dana yang tercatat.</p>
@elseif($order->paid_at)<p>Pembayaran telah diterima. Terima kasih.</p>
@elseif($order->payment_method==='cod')<p>Siapkan pembayaran sebesar <strong>@rupiah($order->total)</strong> saat paket diterima. Customer Service akan mengonfirmasi pesanan Anda.</p>
@else
<p>Transfer tepat <strong>@rupiah($order->total)</strong> ke salah satu rekening berikut.</p>
@foreach($order->bank_accounts??[] as $bank)<p><strong>{{ $bank['bank'] }} — {{ $bank['number'] }}</strong><br>a.n. {{ $bank['owner'] }}</p>@endforeach
<p class="small">Batas upload bukti: {{ $order->expires_at->format('d M Y H:i') }} WIB.</p>
@if($order->payment_status==='review')<div class="alert alert-info">Bukti sedang diverifikasi. Reservasi stok tetap aktif selama peninjauan.</div>
@elseif(in_array($order->payment_status,['unpaid','rejected']))
@if($order->rejection_reason)<div class="alert alert-warning">{{ $order->rejection_reason }}</div>@endif
<form method="post" enctype="multipart/form-data" action="{{ URL::signedRoute('orders.proof',['order'=>$order->id]) }}">@csrf
<label for="proof">Bukti transfer (JPG, PNG, PDF; maksimal 5 MB)</label><input id="proof" class="form-control mb-3" type="file" name="proof" accept="image/jpeg,image/png,application/pdf" required><button class="btn btn-dark w-100">Kirim bukti pembayaran</button></form>
@endif @endif</div>
@include('partials.case-form')
</div></div></section>
@endsection
