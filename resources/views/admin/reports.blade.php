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
<div class="panel mt-4"><div class="section-top"><div><h2>Funnel storefront</h2><small>{{ $visitors }} pengunjung anonim pada periode ini</small></div></div><div class="row g-3">
    @foreach(['view_home'=>'Beranda','view_category'=>'Katalog','search'=>'Pencarian','view_item'=>'Detail produk','add_to_cart'=>'Tambah keranjang','begin_checkout'=>'Mulai checkout','shipping_selected'=>'Pilih ongkir','payment_selected'=>'Pilih pembayaran','purchase'=>'Pesanan dibuat'] as $event=>$label)
        <div class="col-6 col-lg-3"><div class="stat"><span>{{ $label }}</span><strong>{{ $analytics[$event] ?? 0 }}</strong></div></div>
    @endforeach
</div><p class="small mt-3 mb-0">Pengunjung disamarkan menggunakan hash sesi. Event tidak menyimpan nama, email, telepon, alamat, atau kata pencarian.</p></div>
<div class="row g-4 mt-1">
    <div class="col-lg-7"><div class="panel"><h2>Produk terjual</h2><div class="table-responsive"><table class="table">
        <thead><tr><th>SKU / produk</th><th>Unit</th><th>Nilai item</th></tr></thead>
        <tbody>@forelse($topProducts as $product)<tr><td>{{ $product->sku }}<br><small>{{ $product->name }}</small></td><td>{{ $product->quantity }}</td><td>@rupiah($product->revenue)</td></tr>@empty<tr><td colspan="3">Belum ada pembayaran pada periode ini.</td></tr>@endforelse</tbody>
    </table></div></div></div>
    <div class="col-lg-5"><div class="panel"><h2>Pengiriman</h2>
        @forelse($shipping as $service=>$summary)<div class="summary-row"><span>{{ $service }}<br><small>{{ $summary['orders'] }} pesanan</small></span><strong>@rupiah($summary['value'])</strong></div>@empty<p>Belum ada pengiriman pada periode ini.</p>@endforelse
    </div></div>
</div>
<div class="panel mt-4"><div class="section-top"><div><h2>Ledger komisi sales</h2><small>Saldo periode: @rupiah($commissionTotal)</small></div></div><div class="table-responsive"><table class="table"><thead><tr><th>Tanggal</th><th>Sales</th><th>Pesanan</th><th>Jenis</th><th>Basis / rate</th><th>Komisi</th></tr></thead><tbody>@forelse($commissionEntries as $entry)<tr><td>{{ $entry->created_at->format('d M Y') }}</td><td>{{ $entry->salesUser?->name }}</td><td><a href="{{ route('admin.order',$entry->order_id) }}">{{ $entry->order?->number }}</a></td><td>{{ $entry->type==='earned'?'Diperoleh':'Penyesuaian refund' }}</td><td>@rupiah($entry->base_amount) · {{ $entry->commission_percent }}%</td><td>@rupiah($entry->amount)</td></tr>@empty<tr><td colspan="6">Belum ada komisi pada periode ini.</td></tr>@endforelse</tbody></table></div></div>
<div class="table-panel mt-4"><h2>Pesanan dibuat pada periode</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>Nomor</th><th>Status</th><th>Total</th><th>Refund</th></tr></thead><tbody>
    @foreach($orders as $order)<tr><td><a href="{{ route('admin.order',$order) }}">{{ $order->number }}</a><br><small>{{ $order->created_at->format('d M Y H:i') }}</small></td><td>{{ __('status.'.$order->status) }}<br><small>{{ __('status.'.$order->payment_status) }}</small></td><td>@rupiah($order->total)</td><td>@rupiah($order->refunds_sum_amount ?? 0)</td></tr>@endforeach
    </tbody></table></div>{{ $orders->links() }}
</div>
<p class="small mt-3">Omzet memakai tanggal pembayaran diterima. Refund memakai tanggal pengembalian dana selesai. CSV memuat komponen biaya dan nilai bersih per pesanan.</p>
@endsection
