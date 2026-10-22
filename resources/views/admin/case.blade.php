@extends('layouts.admin')
@section('title',$case->number)
@section('content')
<div class="admin-heading"><div><span class="eyebrow">{{ __('status.'.$case->type) }}</span><h1>{{ $case->number }}</h1><p>{{ __('status.'.$case->status) }} · {{ $case->created_at->format('d M Y H:i') }}</p></div><a href="{{ route('admin.order',$case->order) }}" class="btn btn-outline-dark">{{ $case->order->number }} ↗</a></div>
<div class="row g-4"><div class="col-lg-7">
<div class="panel"><h2>Permintaan pelanggan</h2><p><strong>{{ $case->reason_code }}</strong></p><p class="text-break">{{ $case->reason }}</p>
@if($case->evidence_path && (auth()->user()->allowed('cases.write') || auth()->user()->allowed('payments.verify')))<a href="{{ route('admin.case.evidence',$case) }}" class="text-link">Unduh bukti privat</a>@endif
@if($case->policy_snapshot)<details class="mt-3"><summary>Aturan saat pengajuan</summary><p class="mt-2">Retur {{ $case->policy_snapshot['return_days'] }} hari · Garansi {{ $case->policy_snapshot['warranty_days'] }} hari.</p><p>{{ $case->policy_snapshot['shipping_policy'] }}</p></details>@endif</div>
<div class="panel mt-4"><h2>Item & pemeriksaan Gudang</h2>@forelse($case->items as $line)
<div class="border-bottom py-3"><strong>{{ $line->item->name }} · {{ $line->item->sku }}</strong><p>{{ implode(' · ',array_filter($line->item->attributes)) }} · {{ $line->quantity }} unit</p>
@if($line->received_at)<p>Diperiksa {{ $line->received_at->format('d M Y H:i') }}. {{ $line->restocked_quantity }} unit ditambahkan ke stok layak jual.</p>
@elseif($case->status==='approved' && auth()->user()->allowed('inventory.write'))
<form method="post" action="{{ route('admin.return.receive',$line) }}" class="row g-3">@csrf
<div class="col-sm-4"><label for="restock-{{ $line->id }}">Jumlah layak jual</label><input id="restock-{{ $line->id }}" type="number" name="restock" min="0" max="{{ $line->quantity }}" value="0" class="form-control" required></div>
<div class="col-sm-8"><label for="inspect-{{ $line->id }}">Hasil pemeriksaan semua barang</label><input id="inspect-{{ $line->id }}" name="reason" minlength="5" maxlength="500" class="form-control" required></div>
<div><button class="btn btn-dark">Catat penerimaan & stok</button></div></form>
@else<p class="small text-muted">Menunggu persetujuan CS dan pemeriksaan Gudang.</p>@endif</div>
@empty<p>Tidak ada item yang diminta kembali.</p>@endforelse</div>
</div><div class="col-lg-5">
@if(auth()->user()->allowed('cases.write') && !in_array($case->status,['resolved','rejected']))
<div class="panel"><h2>Keputusan CS</h2><form method="post" action="{{ route('admin.case.update',$case) }}">@csrf
<label for="case-status">Status berikutnya</label><select id="case-status" name="status" class="form-select mb-3">
@foreach(($case->status==='approved'?['approved','resolved']:['reviewing','approved','rejected']) as $status)<option value="{{ $status }}">{{ __('status.'.$status) }}</option>@endforeach</select>
<label for="resolution">Pesan untuk pelanggan</label><textarea id="resolution" name="resolution" class="form-control mb-3" minlength="5" maxlength="2000" required></textarea>
<p class="small">Catatan ini terlihat oleh pelanggan dan masuk antrean email.</p><button class="btn btn-dark">Simpan keputusan</button></form></div>@endif
<div class="panel mt-4"><h2>Riwayat kasus</h2>@foreach($case->events as $event)<div class="border-bottom py-3"><small>{{ $event->created_at->format('d M Y H:i') }}</small><p class="mb-0">{{ $event->message }}</p></div>@endforeach</div>
<p class="small mt-3">Refund dicatat Keuangan pada halaman pesanan. Persetujuan kasus tidak memindahkan dana.</p>
</div></div>
@endsection
