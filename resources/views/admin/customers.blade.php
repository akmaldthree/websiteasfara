@extends('layouts.admin')
@section('title','Pelanggan')
@section('content')
<div class="admin-heading"><div><span class="eyebrow">PELANGGAN & MITRA</span><h1>Pelanggan</h1><p>Tinjau pengajuan reseller, dropshipper, dan grosir sebelum harga privat dapat dipakai.</p></div></div>
<div class="table-panel"><div class="table-responsive"><table class="table">
    <thead><tr><th>Nama</th><th>Kontak</th><th>Pesanan</th><th>Akun bisnis</th><th>Bergabung</th><th></th></tr></thead>
    <tbody>@foreach($users as $u)
        @php($business=$u->businessAccount)
        <tr>
            <td>{{ $u->name }}</td><td>{{ $u->email }}<br><small>{{ $u->phone }}</small></td><td>{{ $u->orders_count }}</td>
            <td>@if($business)<strong>{{ ucfirst($business->business_type==='wholesale'?'grosir':$business->business_type) }}</strong> · {{ $business->business_name }}<br><small>{{ $business->sales_channel }} · Status: {{ $business->status }} @if($business->priceGroup)· {{ $business->priceGroup->name }}@endif</small>@else—@endif</td>
            <td>{{ $u->created_at->format('d M Y') }}</td>
            <td>
                @if($business && auth()->user()->allowed('users.write'))
                    <details><summary>Tinjau</summary>
                        <form method="post" action="{{ route('admin.business.review',$business) }}" class="mt-3" style="min-width:280px">@csrf
                            <label>Status</label><select class="form-select mb-2" name="status"><option value="approved">Setujui</option><option value="rejected">Tolak</option></select>
                            <label>Grup harga</label><select class="form-select mb-2" name="price_group_id"><option value="">Pilih jika disetujui</option>@foreach($priceGroups as $group)<option value="{{ $group->id }}" @selected($business->price_group_id===$group->id)>{{ $group->name }} · {{ $group->business_type }}</option>@endforeach</select>
                            <label>Catatan peninjauan</label><textarea class="form-control mb-2" name="review_notes" rows="2">{{ $business->review_notes }}</textarea>
                            <button class="btn btn-dark btn-sm">Simpan keputusan</button>
                        </form>
                    </details>
                @endif
            </td>
        </tr>
    @endforeach</tbody>
</table></div>{{ $users->links() }}</div>
@endsection
