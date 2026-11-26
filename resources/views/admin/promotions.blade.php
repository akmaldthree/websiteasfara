@extends('layouts.admin')
@section('title', 'Kampanye dan voucher')
@section('content')
<div class="admin-heading"><div><h1>Kampanye dan voucher</h1><p>Periksa jadwal, hubungkan voucher, dan uji potongan sebelum kampanye tayang.</p></div></div>
@if($errors->any())
    <ul class="alert alert-danger ps-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
@endif
<section class="panel mb-4">
    <h2>Jadwal dan aset kampanye</h2>
    <form method="get" action="{{ route('admin.promotions') }}" class="d-flex flex-wrap align-items-end gap-2 mb-3">
        <label for="promo-campaign">Kampanye
            <select name="campaign" id="promo-campaign" class="form-select">
                @foreach($campaigns as $item)<option value="{{ $item->id }}" @selected($campaign?->id === $item->id)>{{ $item->title }}</option>@endforeach
            </select>
        </label>
        <button class="btn btn-outline-dark">Tampilkan</button>
    </form>
    @if($campaign)
        <p>{{ $campaign->active && $campaign->editorial_status === 'approved' ? 'Disetujui' : 'Belum siap tayang' }} · {{ $campaign->starts_at?->format('d M Y H:i') ?? 'Tanpa batas mulai' }} – {{ $campaign->ends_at?->format('d M Y H:i') ?? 'Tanpa batas akhir' }}</p>
        <p>{{ $campaign->campaignProductIds()->count() }} produk dikurasi. {{ $campaign->image ? 'Sampul desktop tersedia.' : 'Sampul desktop belum dipilih.' }} {{ $campaign->mobileCoverUrl() ? 'Sampul HP tersedia.' : '' }}</p>
        @if(auth()->user()->allowed('content.write'))
            <a href="{{ route('admin.edit', ['content', $campaign->id]) }}">Edit aset, produk, dan jadwal</a> · <a href="{{ route('admin.content.preview', $campaign) }}" target="_blank" rel="noopener">Pratinjau kampanye</a>
        @endif
        <form method="post" action="{{ route('admin.promotions.link', $campaign) }}" class="d-flex flex-wrap align-items-end gap-2 mt-3">
            @csrf
            <label for="promo-coupon-link">Voucher terkait
                <select class="form-select" name="coupon_id" id="promo-coupon-link">
                    <option value="">Tanpa voucher</option>
                    @foreach($coupons as $item)<option value="{{ $item->id }}" @selected($coupon?->id === $item->id)>{{ $item->code }}</option>@endforeach
                </select>
            </label>
            <button class="btn btn-dark">Simpan kaitan</button>
        </form>
        @if($coupon)
            <p class="mt-3">Voucher {{ $coupon->code }} · {{ $coupon->starts_at->format('d M Y H:i') }} – {{ $coupon->ends_at->format('d M Y H:i') }} · {{ $coupon->used }}/{{ $coupon->usage_limit }} terpakai. <a href="{{ route('admin.edit', ['coupons', $coupon->id]) }}">Edit aturan voucher</a></p>
            @if(!$coupon->active || !$campaign->starts_at || !$campaign->ends_at || $coupon->starts_at->gt($campaign->starts_at) || $coupon->ends_at->lt($campaign->ends_at))
                <p class="alert alert-warning mb-0">Voucher mungkin tidak berlaku sepanjang masa tayang kampanye.</p>
            @endif
        @endif
    @else
        <p>Belum ada kampanye.</p>
    @endif
</section>
<section class="panel">
    <h2>Simulasi keranjang</h2>
    <p>Simulasi untuk tamu dengan transfer bank. Tanggal simulasi memeriksa masa berlaku voucher. Stok dan kuota mengikuti data saat ini.</p>
    <form method="post" action="{{ route('admin.promotions.simulate') }}" class="form-grid">
        @csrf
        <input type="hidden" name="campaign" value="{{ $campaign?->id }}">
        <label>Kode voucher<input class="form-control" name="coupon" value="{{ old('coupon', $coupon?->code) }}" required></label>
        <label>Waktu simulasi (WIB)<input class="form-control" type="datetime-local" name="evaluate_at" value="{{ old('evaluate_at') }}"></label>
        <label class="span-2">Tarif pengiriman
            <select class="form-select" name="shipping_rate_id" required>
                <option value="">Pilih tarif</option>
                @foreach($rates as $rate)
                    <option value="{{ $rate->id }}" @selected((int) old('shipping_rate_id') === $rate->id)>{{ $rate->province }} · {{ $rate->city }} · {{ $rate->courier }} {{ $rate->service }}</option>
                @endforeach
            </select>
        </label>
        @for($i = 0; $i < 3; $i++)
            <label>SKU {{ $i + 1 }}<input class="form-control" name="items[{{ $i }}][sku]" value="{{ old('items.'.$i.'.sku') }}" @required($i === 0)></label>
            <label>Jumlah SKU {{ $i + 1 }}<input class="form-control" type="number" min="1" max="100" name="items[{{ $i }}][quantity]" value="{{ old('items.'.$i.'.quantity', 1) }}"></label>
        @endfor
        <div class="span-2"><button class="btn btn-dark">Hitung simulasi</button></div>
    </form>
    @if($quote = session('promotion_quote'))
        <dl class="mt-4">
            @foreach(['subtotal' => 'Subtotal', 'shipping_cost' => 'Ongkir', 'discount' => 'Potongan produk', 'shipping_discount' => 'Potongan ongkir', 'total' => 'Total'] as $key => $label)
                <div class="summary-row"><dt>{{ $label }}</dt><dd>@rupiah($quote[$key])</dd></div>
            @endforeach
        </dl>
    @endif
</section>
@endsection
