@extends('layouts.admin')
@section('title',$schema['title'])
@section('content')
<div class="admin-heading"><div><span class="eyebrow">MASTER DTHREE</span><h1>{{ $schema['title'] }}</h1><p>{{ $rows->total() }} data. Gunakan status aktif untuk mengarsipkan data.</p></div><a class="btn btn-dark" href="{{ route('admin.create',$resource) }}">+ Tambah data</a></div>
@if($resource==='variants')<p class="small">SKU diisi manual. Stok awal dan perubahan kuota dicatat melalui menu Inventori dengan alasan penyesuaian.</p>@endif
@if($resource==='variant-prices')<p class="small">Harga bertingkat hanya terlihat oleh akun bisnis yang sudah disetujui. Harga per item harus tetap atau turun saat jumlah minimum bertambah.</p>@endif
<div class="table-panel"><div class="table-responsive"><table class="table">
    <thead><tr>@foreach($schema['columns'] as $label)<th>{{ $label }}</th>@endforeach<th></th></tr></thead>
    <tbody>@forelse($rows as $row)<tr>
        @foreach($schema['columns'] as $key=>$label)
            <td>
                @if($resource==='variant-prices' && $key==='variant_id'){{ $row->variant?->sku }}
                @elseif($resource==='variant-prices' && $key==='price_group_id'){{ $row->priceGroup?->name }}
                @elseif($resource==='product-media' && $key==='series_id'){{ $row->series?->name }}
                @elseif($resource==='product-media' && $key==='variant_id'){{ $row->variant?->sku ?: 'Semua varian' }}
                @elseif(in_array($key,['active','cod'])){{ $row->$key?'Ya':'Tidak' }}
                @elseif(in_array($key,['price','minimum_order_amount']))@rupiah($row->$key)
                @else{{ $row->$key }}
                @endif
            </td>
        @endforeach
        <td>@if($resource==='content')<a href="{{ route('admin.content.preview',$row) }}" target="_blank">Preview ↗</a> · @endif<a href="{{ route('admin.edit',[$resource,$row->id]) }}">Kelola ↗</a></td>
    </tr>@empty<tr><td colspan="{{ count($schema['columns'])+1 }}" class="text-center py-5">Belum ada data. Tambahkan data pertama Anda.</td></tr>@endforelse</tbody>
</table></div>{{ $rows->links() }}</div>
@endsection
