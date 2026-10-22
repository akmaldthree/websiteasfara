@if(auth()->user()->allowed('payments.verify') && $order->paid_at)
<div class="panel mt-4"><h2>Catat refund</h2><p>Catat hanya dana yang sudah dikembalikan melalui bank atau metode lain. Form ini tidak mengirim uang.</p>
@foreach($order->refunds as $refund)<p><a class="text-link" href="{{ route('admin.refund.proof',$refund) }}">Bukti refund #{{ $refund->id }}</a> · {{ $refund->reference }} · @rupiah($refund->amount)</p>@endforeach
@php($remaining=$order->total-$order->refunds->sum('amount'))
@if($remaining>0)
<p>Sisa maksimum: <strong>@rupiah($remaining)</strong>. Sebelum pengiriman, refund harus penuh dan membatalkan pesanan.</p>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.refund',$order) }}">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key',(string) Str::uuid()) }}">
<label for="refund-amount">Nominal (rupiah)</label><input id="refund-amount" type="number" name="amount" min="1" max="{{ $remaining }}" class="form-control mb-3" required>
<label for="refund-method">Metode pengembalian</label><input id="refund-method" name="method" maxlength="80" class="form-control mb-3" placeholder="Transfer bank" required>
<label for="refund-reference">Referensi transaksi bank / bukti kas</label><input id="refund-reference" name="reference" maxlength="100" class="form-control mb-3" required>
<label for="refund-date">Tanggal pengembalian dana</label><input id="refund-date" type="date" name="paid_at" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control mb-3" required>
<label for="refund-case">Kasus terkait (opsional)</label><select id="refund-case" name="service_case_id" class="form-select mb-3"><option value="">Tanpa kasus</option>@foreach($order->cases->whereIn('status',['approved','resolved']) as $case)<option value="{{ $case->id }}">{{ $case->number }}</option>@endforeach</select>
<label for="refund-reason">Alasan refund</label><textarea id="refund-reason" name="reason" minlength="5" maxlength="2000" class="form-control mb-3" required></textarea>
<label for="refund-proof">Bukti penyelesaian (JPG/PNG/PDF, maks. 5 MB)</label><input id="refund-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="form-control mb-3" required>
<button class="btn btn-dark">Simpan catatan refund</button></form>@endif</div>@endif
