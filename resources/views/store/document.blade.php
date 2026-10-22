<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ ucfirst($kind) }} {{ $order->number }}</title>
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
<style>body{font:14px system-ui;color:#202620;background:#eee}.document{max-width:850px;margin:30px auto;padding:40px;background:#fff}h1{font-size:28px}.summary-row{display:flex;justify-content:space-between;gap:20px;padding:8px 0}.total{font-weight:bold;border-top:1px solid #999}.label{border:2px solid #222;padding:24px}table{width:100%}th,td{padding:10px;text-align:left;border-bottom:1px solid #ddd}@media print{body{background:white}.document{margin:0;padding:0;max-width:none}.no-print{display:none}tr{break-inside:avoid}}@media(max-width:600px){.document{margin:0;padding:20px}}</style></head>
<body><main class="document"><button class="btn btn-dark no-print mb-4" onclick="window.print()">Cetak / simpan PDF</button>
<header class="d-flex justify-content-between gap-3"><div><h1>Dthree</h1><p>Dikirim dari Tasikmalaya</p></div><div><strong>{{ ['invoice'=>'Invoice','picking'=>'Picking list','packing'=>'Packing slip','label'=>'Label pengiriman'][$kind] }}</strong><br>{{ $order->number }}<br>{{ $order->created_at->format('d M Y H:i') }} WIB</div></header>
@if($kind!=='picking')<section class="{{ $kind==='label'?'label':'border-top pt-3' }} my-4"><strong>Penerima: {{ $order->name }}</strong><p>{{ $order->phone }}<br>{{ $order->address['detail'] }}<br>{{ $order->address['district'] }}, {{ $order->address['city'] }}, {{ $order->address['province'] }} {{ $order->address['postal_code'] }}</p><p>{{ $order->shipping['courier'] }} {{ $order->shipping['service'] }}<br>Resi: <strong>{{ $order->tracking_number ?: 'Belum tersedia' }}</strong></p>
@if($kind==='label' && $order->payment_method==='cod')<strong>COD @rupiah($order->total)</strong>@endif</section>@endif
@if($kind==='invoice')
<p>Status: {{ __('status.'.$order->status) }} · Pembayaran: {{ __('status.'.$order->payment_status) }}</p>
@include('partials.order-summary')
@if($order->refunds->isNotEmpty())<div class="summary-row"><span>Refund tercatat</span><strong>@rupiah($order->refunds->sum('amount'))</strong></div>@endif
<p class="mt-4 small">Dokumen ini merangkum transaksi. Status pembayaran menentukan apakah dana sudah diterima.</p>
@elseif($kind!=='label')
<table><thead><tr><th>SKU / produk</th><th>Atribut</th><th>Jumlah</th><th>Cek</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td>{{ $item->sku }}<br>{{ $item->name }}</td><td>{{ implode(' · ',array_filter($item->attributes)) }}</td><td>{{ $item->quantity }}</td><td>□</td></tr>@endforeach</tbody></table>
<p class="mt-4">Catatan: {{ $order->notes ?: '—' }}</p><p>Petugas: ____________________ &nbsp; Tanggal: ____________________</p>
@endif
</main></body></html>
