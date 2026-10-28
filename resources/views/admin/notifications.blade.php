@extends('layouts.admin')
@section('title','Notifikasi transaksi')
@section('content')
<h1 class="mb-4">Notifikasi transaksi</h1>
@if(!config('commerce.email_notifications'))<div class="alert alert-warning">Pengiriman email dijeda. Aktifkan SMTP dan TRANSACTION_EMAIL_ENABLED di hosting setelah pengujian.</div>@endif
<div class="d-flex gap-3 flex-wrap mb-4">@foreach(['pending','failed','uncertain','manual','sent','manual_sent'] as $status)<a href="{{ route('admin.notifications',['status'=>$status]) }}" class="badge">{{ __('status.'.$status) }}: {{ $counts[$status]??0 }}</a>@endforeach</div>
@forelse($deliveries as $delivery)<div class="panel mb-3"><div class="d-flex justify-content-between gap-3"><h2><a href="{{ route('admin.order',$delivery->event->order) }}">{{ $delivery->event->order->number }}</a></h2><span>{{ __('status.'.$delivery->status) }}</span></div><p>{{ $delivery->channel }} · {{ $delivery->recipient }} · {{ $delivery->attempts }} percobaan</p><p>{{ $delivery->event->message }}</p>@if($delivery->last_error)<div class="alert alert-warning">{{ $delivery->last_error }}</div>@endif
<details><summary>Isi pesan untuk tindak lanjut</summary><pre class="text-wrap text-break mt-3">{{ data_get($delivery->payload, 'body') ?: data_get($delivery->payload, 'message') ?: $delivery->event->message }}</pre></details>
@if(in_array($delivery->status,['failed','uncertain']))
<form method="post" action="{{ route('admin.notification.retry',$delivery) }}" class="mt-3">@csrf<label><input type="checkbox" name="confirmed" value="1" required> Saya sudah memeriksa penyedia dan memastikan pesan perlu dikirim ulang.</label><button class="btn btn-outline-dark btn-sm ms-2">Jadwalkan ulang</button></form>@endif
@if(in_array($delivery->status,['manual','failed','uncertain']))
<form method="post" action="{{ route('admin.notification.manual',$delivery) }}" class="mt-3">@csrf<label for="note-{{ $delivery->id }}">Catatan komunikasi manual yang sudah dilakukan</label><input id="note-{{ $delivery->id }}" name="note" minlength="5" maxlength="500" class="form-control my-2" required><button class="btn btn-dark btn-sm">Tandai sudah ditindaklanjuti</button></form>@endif
</div>@empty<div class="panel">Tidak ada notifikasi pada filter ini.</div>@endforelse{{ $deliveries->links() }}
@endsection
