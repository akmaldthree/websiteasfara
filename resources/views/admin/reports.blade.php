@extends('layouts.admin')
@section('title','Laporan')
@section('content')
<div class="admin-heading">
    <div><span class="eyebrow">PENJUALAN, PRODUK & OPERASIONAL</span><h1>Laporan dasar</h1></div>
    <a class="btn btn-outline-dark" href="{{ route('admin.reports',['from'=>$from,'to'=>$to,'format'=>'csv']) }}">Export transaksi CSV</a>
</div>
<form class="row g-3 mb-4">
    <div class="col-md-4"><label for="from">Dari</label><input id="from" class="form-control" type="date" name="from" value="{{ $from }}"></div>
    <div class="col-md-4"><label for="to">Sampai</label><input id="to" class="form-control" type="date" name="to" value="{{ $to }}"></div>
    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-dark">Tampilkan</button></div>
</form>
<div class="stats-grid">
    <div class="stat"><span>Pesanan dibuat</span><strong>{{ $count }}</strong></div>
    <div class="stat"><span>Pembayaran diterima</span><strong>{{ $paidCount }}</strong></div>
    <div class="stat"><span>Omzet bruto</span><strong>@rupiah($gross)</strong></div>
    <div class="stat"><span>Refund diselesaikan</span><strong>@rupiah($refunds)</strong></div>
    <div class="stat"><span>Omzet bersih</span><strong>@rupiah($net)</strong></div>
    <div class="stat"><span>Nilai pesanan rata-rata</span><strong>@rupiah($aov)</strong></div>
    <div class="stat"><span>Pelanggan / kontak membeli</span><strong>{{ $customerCount }}</strong></div>
    <div class="stat"><span>Pelanggan membeli berulang</span><strong>{{ $repeatCustomers }}</strong></div>
    <div class="stat"><span>SKU stok rendah</span><strong>{{ $lowStock }}</strong></div>
</div>
<div class="row g-4 mt-1">
    <div class="col-lg-7"><div class="panel"><h2>Produk terjual</h2><div class="table-responsive"><table class="table">
        <thead><tr><th>SKU / produk</th><th>Unit</th><th>Nilai item</th></tr></thead>
        <tbody>@forelse($topProducts as $product)<tr><td>{{ $product->sku }}<br><small>{{ $product->name }}</small></td><td>{{ $product->quantity }}</td><td>@rupiah($product->revenue)</td></tr>@empty<tr><td colspan="3">Belum ada pembayaran pada periode ini.</td></tr>@endforelse</tbody>
    </table></div></div></div>
    <div class="col-lg-5"><div class="panel"><h2>Pengiriman</h2>
        @forelse($shipping as $service=>$summary)<div class="summary-row"><span>{{ $service }}<br><small>{{ $summary['orders'] }} pesanan</small></span><strong>@rupiah($summary['value'])</strong></div>@empty<p>Belum ada pengiriman pada periode ini.</p>@endforelse
    </div></div>
</div>
<div class="table-panel mt-4"><h2>Pesanan dibuat pada periode</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>Nomor</th><th>Status</th><th>Total</th><th>Refund</th></tr></thead><tbody>
    @foreach($orders as $order)<tr><td><a href="{{ route('admin.order',$order) }}">{{ $order->number }}</a><br><small>{{ $order->created_at->format('d M Y H:i') }}</small></td><td>{{ __('status.'.$order->status) }}<br><small>{{ __('status.'.$order->payment_status) }}</small></td><td>@rupiah($order->total)</td><td>@rupiah($order->refunds_sum_amount ?? 0)</td></tr>@endforeach
    </tbody></table></div>{{ $orders->links() }}
</div>
<p class="small mt-3">Omzet memakai tanggal pembayaran diterima. Refund memakai tanggal pengembalian dana selesai. CSV memuat komponen biaya dan nilai bersih per pesanan.</p>
@endsection
