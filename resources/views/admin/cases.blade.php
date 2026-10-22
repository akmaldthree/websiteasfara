@extends('layouts.admin')
@section('title','Layanan pelanggan')
@section('content')
<div class="admin-heading"><div><span class="eyebrow">PURNAJUAL</span><h1>Layanan pelanggan</h1></div>@if(auth()->user()->allowed('settings.write'))<a class="btn btn-outline-dark" href="{{ route('admin.service.policy') }}">Aturan retur & garansi</a>@endif</div>
<form method="get" class="panel mb-4 d-flex flex-wrap gap-3"><label for="filter-status">Status</label><select id="filter-status" name="status" class="form-select w-auto"><option value="">Semua status</option>@foreach(['open','reviewing','approved','resolved','rejected'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ __('status.'.$status) }}</option>@endforeach</select><button class="btn btn-dark">Tampilkan</button></form>
@forelse($cases as $case)
<a class="panel mb-3 d-block text-decoration-none" href="{{ route('admin.case',$case) }}"><div class="d-flex justify-content-between gap-3"><h2>{{ $case->number }}</h2><span>{{ __('status.'.$case->status) }} ↗</span></div><p>{{ $case->order->number }} · {{ __('status.'.$case->type) }} · {{ $case->created_at->format('d M Y H:i') }}</p><p class="mb-0">{{ Str::limit($case->reason,180) }}</p></a>
@empty<div class="panel">Belum ada kasus pada filter ini.</div>@endforelse
{{ $cases->links() }}
@endsection
