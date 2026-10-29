@extends('layouts.admin')
@section('title',$schema['title'])
@section('content')
@php
$descriptions=[
    'categories'=>'Kelompokkan produk agar pelanggan mudah menjelajahi katalog.',
    'series'=>'Satu produk utama beserta deskripsi, bahan, panduan ukuran, dan status tayangnya.',
    'variants'=>'Pilihan jual berdasarkan jenis, warna, dan ukuran. Setiap kombinasi mempunyai SKU sendiri.',
    'product-media'=>'Atur foto utama, foto per warna, atau foto khusus SKU.',
    'bundle-components'=>'Tentukan SKU apa saja dan berapa jumlahnya di dalam produk bundle.',
    'measurement-templates'=>'Susun field ukuran yang akan diisi pelanggan untuk produk custom.',
    'price-groups'=>'Kelompok harga untuk reseller, dropshipper, dan pembelian grosir.',
    'variant-prices'=>'Atur harga per SKU berdasarkan grup dan minimum jumlah pembelian.',
    'business-order-rules'=>'Atur minimum pembelian B2B untuk kategori, produk, atau SKU tertentu.',
    'shipping-rates'=>'Tarif manual berdasarkan kurir dan wilayah tujuan.',
    'coupons'=>'Buat voucher nominal, persentase, atau subsidi ongkir dengan target yang terkontrol.',
    'content'=>'Kelola banner, section beranda, menu, FAQ, journal, dan campaign dari satu tempat.',
    'pages'=>'Kelola halaman informasi seperti syarat, privasi, pengiriman, retur, dan garansi.',
];
$typeLabels=['banner'=>'Banner','home_section'=>'Section beranda','menu'=>'Menu','faq'=>'FAQ','journal'=>'Journal','campaign'=>'Campaign'];
@endphp
<div class="admin-heading"><div><span class="eyebrow">{{ in_array($resource,['content','pages'])?'WEBSITE & KONTEN':'MASTER DATA' }}</span><h1>{{ $schema['title'] }}</h1><p>{{ $descriptions[$resource]??'Kelola data yang digunakan oleh website dan operasional Dthree.' }}</p></div><a class="btn btn-dark" href="{{ route('admin.create',$resource) }}">+ Tambah {{ strtolower($schema['title']) }}</a></div>

@if($resource==='variants')<div class="admin-help"><strong>Urutan kerja yang disarankan:</strong> buat kategori → produk/series → varian & SKU → isi stok melalui Inventori. SKU yang pernah dipesan tidak dapat diubah identitasnya.</div>@endif
@if($resource==='content')<div class="admin-help"><strong>Untuk mengganti banner:</strong> tambah konten bertipe Banner, pilih gambar, ubah status menjadi Disetujui, lalu aktifkan. Gambar hero utama tetap dikelola melalui Pengaturan website.</div>@endif
@if($resource==='variant-prices')<div class="admin-help">Harga bertingkat hanya terlihat oleh akun bisnis yang disetujui. Harga per item harus tetap atau turun ketika jumlah minimum bertambah.</div>@endif

<div class="admin-toolbar">
    @if($searchable)<form method="get"><label class="visually-hidden" for="resource-search">Cari data</label><input id="resource-search" class="form-control" name="q" value="{{ request('q') }}" placeholder="Cari {{ strtolower($schema['title']) }}..."><button class="btn btn-outline-dark">Cari</button>@if(request()->filled('q'))<a class="btn btn-link" href="{{ route('admin.resources',$resource) }}">Reset</a>@endif</form>@else<div><strong>{{ $rows->total() }}</strong> data tersimpan</div>@endif
    <div class="admin-toolbar-actions">@if(array_key_exists($resource,app(\App\Services\BulkImportService::class)->definitions()))<a class="btn btn-outline-dark" href="{{ route('admin.import',['type'=>$resource]) }}">Import / export Excel</a>@endif<a class="btn btn-dark" href="{{ route('admin.create',$resource) }}">+ Tambah data</a></div>
</div>

<div class="table-panel"><div class="section-top mb-2"><div><h2 class="mb-1">Daftar data</h2><small>{{ $rows->total() }} hasil{{ request()->filled('q')?' untuk “'.request('q').'”':'' }}</small></div></div><div class="table-responsive"><table class="table resource-table">
    <thead><tr>@foreach($schema['columns'] as $label)<th>{{ $label }}</th>@endforeach<th><span class="visually-hidden">Tindakan</span></th></tr></thead>
    <tbody>@forelse($rows as $row)<tr>
        @foreach($schema['columns'] as $key=>$label)<td data-label="{{ $label }}">
            @if($resource==='variant-prices' && $key==='variant_id'){{ $row->variant?->sku }}
            @elseif($resource==='variant-prices' && $key==='price_group_id'){{ $row->priceGroup?->name }}
            @elseif($resource==='product-media' && $key==='series_id'){{ $row->series?->name }}
            @elseif($resource==='product-media' && $key==='variant_id'){{ $row->variant?->sku ?: 'Semua varian' }}
            @elseif($key==='type' && $resource==='content'){{ $typeLabels[$row->$key]??$row->$key }}
            @elseif($key==='editorial_status')<span class="badge">{{ ['draft'=>'Draft','review'=>'Menunggu review','approved'=>'Disetujui'][$row->$key] ?? $row->$key }}</span>
            @elseif(in_array($key,['active','cod']))<span class="badge">{{ $row->$key?'Aktif':'Nonaktif' }}</span>
            @elseif(in_array($key,['price','minimum_order_amount']))@rupiah($row->$key)
            @else{{ $row->$key }}
            @endif
        </td>@endforeach
        <td class="text-nowrap" data-label="Tindakan">@if($resource === 'content')<a href="{{ route('admin.content.preview',$row) }}" target="_blank">Preview</a> · @endif @if(in_array($resource, ['pages', 'content'], true))<a href="{{ route('admin.content.history',[$resource,$row->id]) }}">Riwayat</a> · @endif<a class="fw-semibold" href="{{ route('admin.edit',[$resource,$row->id]) }}">Edit</a> · <form class="d-inline" method="post" action="{{ route('admin.destroy',[$resource,$row->id]) }}" onsubmit="return confirm('Hapus data ini secara permanen? Tindakan ini tidak dapat dibatalkan.')">@csrf @method('DELETE')<button class="btn btn-link btn-sm p-0 text-danger" type="submit">Hapus</button></form></td>
    </tr>@empty<tr><td colspan="{{ count($schema['columns'])+1 }}"><div class="admin-empty"><h3>{{ request()->filled('q')?'Data tidak ditemukan':'Belum ada data' }}</h3><p>{{ request()->filled('q')?'Coba kata pencarian lain atau reset pencarian.':'Tambahkan data pertama untuk mulai mengisi bagian ini.' }}</p>@if(!request()->filled('q'))<a class="btn btn-dark" href="{{ route('admin.create',$resource) }}">+ Tambah data pertama</a>@endif</div></td></tr>@endforelse</tbody>
</table></div>{{ $rows->links() }}</div>
@endsection
