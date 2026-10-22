@php($policy=app(\App\Services\AfterSalesService::class)->policy())
<div class="panel mt-4"><h2>Bantuan, retur & garansi</h2>
<p class="small">Retur sampai {{ $policy['return_days'] }} hari dan garansi {{ $policy['warranty_days'] }} hari setelah paket diterima. {{ $policy['shipping_policy'] }}</p>
<form method="post" enctype="multipart/form-data" action="{{ URL::signedRoute('orders.case',['order'=>$order->id]) }}">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key',(string) Str::uuid()) }}">
<label for="case-type">Jenis permintaan</label><select id="case-type" class="form-select mb-3" name="type"><option value="complaint">Pertanyaan / komplain</option><option value="return">Permintaan retur</option><option value="warranty">Garansi</option></select>
<label for="case-reason-code">Alasan retur / garansi</label><select id="case-reason-code" name="reason_code" class="form-select mb-3"><option value="">Pilih untuk retur / garansi</option>@foreach($policy['reasons'] as $reason)<option>{{ $reason }}</option>@endforeach</select>
<details class="mb-3"><summary>Pilih item untuk retur / garansi</summary>@foreach($order->items as $item)<label class="d-block mt-3" for="return-item-{{ $item->id }}">{{ $item->name }} · {{ $item->sku }} (maks. {{ $item->quantity }})</label><input id="return-item-{{ $item->id }}" class="form-control" type="number" name="items[{{ $item->id }}]" min="0" max="{{ $item->quantity }}" value="0">@endforeach</details>
<label for="case-reason">Jelaskan permintaan Anda</label><textarea id="case-reason" class="form-control mb-3" name="reason" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea>
<label for="case-evidence">Bukti JPG/PNG/PDF, maksimal 5 MB{{ $policy['evidence_required']?' (wajib untuk retur / garansi)':'' }}</label><input id="case-evidence" type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf" class="form-control mb-3">
<button class="btn btn-outline-dark w-100">Buat nomor kasus</button></form>
@foreach($order->cases as $case)<div class="border-top mt-4 pt-3"><strong>{{ $case->number }}</strong> · {{ __('status.'.$case->status) }}<p class="small">{{ $case->resolution }}</p>
@foreach($case->items as $line)<p class="small">{{ $line->item->sku }} · {{ $line->quantity }} unit @if($line->received_at) · Sudah diterima Gudang @endif</p>@endforeach
@if(!empty($shop['whatsapp']))<a class="text-link" target="_blank" rel="noopener" href="https://wa.me/{{ $shop['whatsapp'] }}?text={{ urlencode('Halo Dthree, saya ingin membahas kasus '.$case->number.' untuk pesanan '.$order->number) }}">Lanjutkan ke WhatsApp ↗</a>@endif</div>@endforeach</div>
