@extends('layouts.admin')
@section('title','Aturan retur & garansi')
@section('content')
<h1 class="mb-4">Aturan retur & garansi</h1>
<form method="post" action="{{ route('admin.service.policy.save') }}" class="panel">@csrf
<div class="row g-4">
@foreach(['return_days'=>'Batas pengajuan retur sejak diterima (hari)','warranty_days'=>'Batas pengajuan garansi (hari)'] as $key=>$label)<div class="col-md-6"><label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" type="number" name="{{ $key }}" value="{{ old($key,$policy[$key]) }}" min="1" max="{{ $key==='return_days'?365:1095 }}" class="form-control" required></div>@endforeach
<div class="col-md-6"><label for="reasons">Alasan yang diperbolehkan, satu per baris</label><textarea id="reasons" name="return_reasons" class="form-control" rows="5" required>{{ old('return_reasons',implode("\n",$policy['reasons'])) }}</textarea></div>
<div class="col-md-6"><label for="excluded">SKU pengecualian, satu per baris</label><textarea id="excluded" name="return_excluded_skus" class="form-control" rows="5">{{ old('return_excluded_skus',implode("\n",$policy['excluded_skus'])) }}</textarea></div>
<div><label for="shipping-policy">Kebijakan biaya pengiriman retur</label><textarea id="shipping-policy" name="return_shipping_policy" class="form-control" required>{{ old('return_shipping_policy',$policy['shipping_policy']) }}</textarea></div>
<div><label><input type="checkbox" name="case_evidence_required" value="1" @checked(old('case_evidence_required',$policy['evidence_required']))> Bukti wajib untuk retur dan garansi</label></div>
<div><p>Produk custom ditinjau melalui komplain. Perubahan berlaku untuk pengajuan berikutnya. Perbarui juga halaman kebijakan resmi toko agar konsisten.</p><button class="btn btn-dark">Simpan aturan</button></div>
</div></form>
@endsection
