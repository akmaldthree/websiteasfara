@extends('layouts.store')
@section('title','Akun saya — Dthree')
@section('content')
<section class="container-xxl section">
    <div class="section-top">
        <div><span class="eyebrow">AKUN SAYA</span><h1 class="mt-3">Halo, {{ auth()->user()->name }}.</h1></div>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-dark">Keluar</button></form>
    </div>
    <div class="row g-4">
        <div class="col-lg-7"><div class="panel"><h2>Riwayat pesanan</h2>
            @forelse($orders as $order)
                <div class="summary-row"><span><a href="{{ $order->customerUrl() }}">{{ $order->number }}</a><br><small>{{ $order->created_at->format('d M Y') }} · {{ __('status.'.$order->status) }}</small></span><span class="text-end">@rupiah($order->total)<form method="post" action="{{ route('orders.reorder',$order) }}">@csrf<button class="btn btn-link btn-sm">Pesan ulang</button></form></span></div>
            @empty
                <p>Belum ada pesanan. <a href="{{ route('catalog') }}">Temukan koleksi Anda ↗</a></p>
            @endforelse
            {{ $orders->links() }}
        </div></div>
        <div class="col-lg-5"><div class="panel"><h2>Buku alamat</h2>
            @foreach($addresses as $address)
                <div class="border-bottom py-3">
                    <p><strong>{{ $address->name }}</strong> @if($address->is_default)<span class="badge">Utama</span>@endif<br>{{ $address->phone }}<br>{{ $address->detail }}, {{ $address->city }} {{ $address->postal_code }}</p>
                    <div class="d-flex gap-2">
                        @unless($address->is_default)<form action="{{ route('address.default',$address->id) }}" method="post">@csrf<button class="btn btn-outline-dark btn-sm">Jadikan utama</button></form>@endunless
                        <form action="{{ route('address.delete',$address->id) }}" method="post">@csrf @method('DELETE')<button class="btn btn-outline-dark btn-sm">Hapus</button></form>
                    </div>
                </div>
            @endforeach
            <details class="mt-4"><summary>+ Tambahkan alamat</summary>
                <form action="{{ route('address.store') }}" method="post" class="mt-4">@csrf
                    @foreach(['name'=>'Penerima','phone'=>'Telepon','province'=>'Provinsi','city'=>'Kota / kabupaten','district'=>'Kecamatan','postal_code'=>'Kode pos','detail'=>'Alamat lengkap'] as $key=>$label)
                        <label for="address-{{ $key }}">{{ $label }}</label><input id="address-{{ $key }}" class="form-control mb-3" name="{{ $key }}" required>
                    @endforeach
                    <label class="d-block mb-3"><input type="checkbox" name="is_default" value="1"> Jadikan alamat utama</label>
                    <button class="btn btn-dark">Simpan alamat</button>
                </form>
            </details>
        </div></div>
    </div>
    <div class="panel mt-4"><h2>Preferensi komunikasi</h2><p class="small">Persetujuan ini dapat diubah kapan saja dan terpisah dari email transaksi pesanan.</p><form method="post" action="{{ route('account.communication') }}">@csrf<label class="d-block mb-2"><input type="checkbox" name="marketing_email" value="1" @checked(auth()->user()->marketing_email)> Promo melalui email</label><label class="d-block mb-3"><input type="checkbox" name="marketing_whatsapp" value="1" @checked(auth()->user()->marketing_whatsapp)> Promo melalui WhatsApp</label><button class="btn btn-outline-dark btn-sm">Simpan preferensi</button></form></div>
    <div class="panel mt-4"><span class="eyebrow">DTHREE LOYALTY · {{ strtoupper($loyaltyTier['name']) }}</span><h2 class="mt-3">{{ number_format($loyaltyBalance,0,',','.') }} poin tersedia</h2><p>{{ number_format($loyaltyTier['lifetime_points'],0,',','.') }} poin terkumpul sepanjang masa.@if($loyaltyTier['benefit']) Benefit level: {{ $loyaltyTier['benefit'] }}.@endif @if($loyaltyTier['next']) Kumpulkan {{ number_format($loyaltyTier['next']['minimum']-$loyaltyTier['lifetime_points'],0,',','.') }} poin lagi untuk mencapai {{ $loyaltyTier['next']['name'] }}.@endif Poin tersedia dapat digunakan saat checkout setelah program diaktifkan oleh admin.</p>@if($loyaltyTransactions->isNotEmpty())<div class="table-responsive"><table class="table"><thead><tr><th>Tanggal</th><th>Aktivitas</th><th>Poin</th></tr></thead><tbody>@foreach($loyaltyTransactions as $transaction)<tr><td>{{ $transaction->created_at->format('d M Y') }}</td><td>{{ str_replace('_',' ',$transaction->type) }}@if(($transaction->metadata['multiplier'] ?? 1)>1) · {{ $transaction->metadata['campaign'] }} ×{{ $transaction->metadata['multiplier'] }}@endif</td><td>{{ $transaction->points>0?'+':'' }}{{ $transaction->points }}</td></tr>@endforeach</tbody></table></div>@endif</div>
    @if($customQuotes->isNotEmpty())
    <div class="panel mt-4"><span class="eyebrow">UKURAN CUSTOM</span><h2 class="mt-3">Penawaran saya</h2>
        @foreach($customQuotes as $quote)<div class="border-bottom py-3"><div class="summary-row"><span><strong>{{ $quote->number }}</strong><br>{{ $quote->variant->series->name }} · {{ $quote->variant->sku }}<br><small>Status {{ strtoupper($quote->status) }} @if($quote->expires_at)· berlaku sampai {{ $quote->expires_at->format('d M Y H:i') }}@endif</small></span><span>@if($quote->surcharge!==null)@rupiah($quote->surcharge)@endif</span></div>
            @if($quote->admin_notes)<p>{{ $quote->admin_notes }}</p>@endif
            @if($quote->status==='quoted' && $quote->expires_at?->isFuture())<form method="post" action="{{ route('custom.accept',$quote) }}">@csrf<button class="btn btn-dark btn-sm">Setujui dan masukkan ke keranjang</button></form>@endif
        </div>@endforeach
    </div>
    @endif
    <div class="row g-4 mt-1"><div class="col-lg-6"><div class="panel"><h2>Wishlist</h2>@forelse($wishlist as $wish)<div class="summary-row"><span><a href="{{ route('product',$wish->variant->series->slug) }}?variant={{ $wish->variant_id }}">{{ $wish->variant->series->name }} · {{ $wish->variant->label }}</a><br><small>{{ $wish->variant->sku }}</small></span><form method="post" action="{{ route('wishlist.toggle',$wish->variant) }}">@csrf<button class="btn btn-link btn-sm">Hapus</button></form></div>@empty<p>Wishlist masih kosong.</p>@endforelse</div></div>
    <div class="col-lg-6"><div class="panel"><h2>Ulasan pembelian</h2>@forelse($reviewableItems as $item)<details class="mb-3"><summary>{{ $item->name }} · {{ $item->sku }}</summary><form method="post" enctype="multipart/form-data" action="{{ route('review.store',$item) }}" class="mt-3">@csrf<label>Rating</label><select name="rating" class="form-select mb-2" required>@foreach(range(5,1) as $rating)<option value="{{ $rating }}">{{ $rating }} bintang</option>@endforeach</select><label>Ulasan</label><textarea name="body" class="form-control mb-2" minlength="10" maxlength="3000" required></textarea><label>Foto (maks. 3)</label><input type="file" name="photos[]" class="form-control mb-2" accept="image/png,image/jpeg,image/webp" multiple><button class="btn btn-dark btn-sm">Kirim ulasan</button></form></details>@empty<p>Tidak ada item yang menunggu ulasan.</p>@endforelse @foreach($myReviews as $review)<p class="small border-top pt-2">{{ $review->variant->series->name }} · {{ $review->rating }}/5 · {{ strtoupper($review->status) }}</p>@endforeach</div></div></div>
    <div class="panel mt-4">
        <span class="eyebrow">PROGRAM MITRA DTHREE</span>
        <h2 class="mt-3">Akun reseller, dropshipper, atau grosir</h2>
        @if($businessAccount)
            <p>Status pengajuan: <span class="badge">{{ $businessAccount->status==='approved' && $businessAccount->expires_at?->isPast() ? 'EXPIRED' : strtoupper($businessAccount->status) }}</span> @if($businessAccount->expires_at)· berlaku sampai {{ $businessAccount->expires_at->format('d M Y') }}@endif</p>
            <p><strong>{{ $businessAccount->business_name }}</strong> · {{ $businessAccount->business_type==='wholesale'?'Grosir':ucfirst($businessAccount->business_type) }}<br><small>Kanal penjualan: {{ $businessAccount->sales_channel }}</small></p>
            @if($businessAccount->status==='approved' && (!$businessAccount->expires_at || $businessAccount->expires_at->isFuture()))
                <p>Grup harga <strong>{{ $businessAccount->priceGroup?->name }}</strong> aktif untuk akun ini. Masuk ke halaman produk untuk melihat tier harga privat.</p>
            @elseif($businessAccount->status==='rejected')
                <p>{{ $businessAccount->review_notes }}</p>
            @else
                <p>Tim Dthree sedang meninjau data Anda. Harga privat baru aktif setelah pengajuan disetujui.</p>
            @endif
        @endif
        @if(!$businessAccount || $businessAccount->status!=='approved')
            <details @if(!$businessAccount) open @endif><summary>{{ $businessAccount?'Perbarui dan kirim ulang pengajuan':'Ajukan akun bisnis' }}</summary>
                <form method="post" enctype="multipart/form-data" action="{{ route('business.apply') }}" class="mt-4">@csrf
                    <div class="form-grid">
                        <div><label for="business-type">Jenis bisnis</label><select id="business-type" name="business_type" class="form-select" required><option value="reseller" @selected(old('business_type',$businessAccount?->business_type)==='reseller')>Reseller</option><option value="dropshipper" @selected(old('business_type',$businessAccount?->business_type)==='dropshipper')>Dropshipper</option><option value="wholesale" @selected(old('business_type',$businessAccount?->business_type)==='wholesale')>Grosir</option></select></div>
                        <div><label for="business-name">Nama toko / usaha</label><input id="business-name" name="business_name" class="form-control" value="{{ old('business_name',$businessAccount?->business_name) }}" required maxlength="150"></div>
                        <div><label for="sales-channel">Kanal penjualan</label><input id="sales-channel" name="sales_channel" class="form-control" value="{{ old('sales_channel',$businessAccount?->sales_channel) }}" placeholder="Contoh: toko fisik, Instagram, marketplace" required maxlength="150"></div>
                        <div><label for="business-notes">Catatan (opsional)</label><textarea id="business-notes" name="notes" class="form-control" maxlength="2000">{{ old('notes',$businessAccount?->notes) }}</textarea></div>
                        <div><label for="legal-document">Dokumen usaha (PDF/JPG/PNG, maks. 5 MB)</label><input id="legal-document" type="file" name="legal_document" class="form-control" accept="application/pdf,image/jpeg,image/png">@if($businessAccount?->legal_document_name)<small>Tersimpan: {{ $businessAccount->legal_document_name }}. Unggah file baru untuk mengganti.</small>@endif</div>
                    </div>
                    <button class="btn btn-dark mt-3">Kirim pengajuan</button>
                </form>
            </details>
        @endif
    </div>
</section>
@endsection
