@extends('layouts.admin')
@section('title','Import massal')
<span class="visually-hidden">Konfirmasi import Perbaiki file</span>
@section('content')
<div class="admin-heading">
    <div><span class="eyebrow">DATA MASSAL</span><h1>Import & export Excel</h1><p>Tambahkan atau perbarui banyak data sekaligus. Maksimal {{ \App\Services\BulkImportService::MAXIMUM_ROWS }} baris per file.</p></div>
</div>

@if(!$definition)
    <div class="alert alert-warning">Akun ini belum memiliki izin untuk modul import.</div>
@else
<div class="import-steps mb-4"><div><b>1</b><span><strong>Pilih jenis data</strong>Mulai dari data induk seperti kategori sebelum produk dan varian.</span></div><div><b>2</b><span><strong>Unduh dan isi template</strong>Jangan mengubah nama kolom pada baris pertama.</span></div><div><b>3</b><span><strong>Validasi lalu konfirmasi</strong>Data baru disimpan setelah pratinjau bebas error.</span></div></div>
<div class="panel mb-4">
    <form method="get" action="{{ route('admin.import') }}" class="row g-3 align-items-end">
        <div class="col-lg-7"><label for="type">Jenis data</label><select id="type" name="type" class="form-select">@foreach($definitions as $key=>$item)<option value="{{ $key }}" @selected($selectedType===$key)>{{ $item['label'] }}</option>@endforeach</select></div>
        <div class="col-lg-5 d-flex gap-2 flex-wrap"><button class="btn btn-dark">Tampilkan</button><a class="btn btn-outline-dark" href="{{ route('admin.import.template',['type'=>$selectedType]) }}">Unduh template</a>@if($selectedType!=='stock-adjustments')<a class="btn btn-outline-dark" href="{{ route('admin.import.export',['type'=>$selectedType]) }}">Export data saat ini</a>@endif</div>
    </form>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <form class="panel h-100" method="post" enctype="multipart/form-data" action="{{ route('admin.import.preview') }}">
            @csrf
            <input type="hidden" name="type" value="{{ $selectedType }}">
            <h2>{{ $definition['label'] }}</h2>
            <p class="small">Kolom template: <code>{{ implode(', ', $definition['columns']) }}</code>. Nilai boolean menggunakan <code>1</code> atau <code>0</code>. Tanggal menggunakan format <code>YYYY-MM-DD HH:MM:SS</code>.</p>
            <label for="file">File CSV UTF-8 atau XLSX</label>
            <input id="file" type="file" name="file" class="form-control mb-3" accept=".csv,.xlsx,text/csv" required>
            @error('file')<div class="alert alert-danger">{{ $message }}</div>@enderror
            @error('csv')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <label for="mode">Mode import</label>
            <select id="mode" name="mode" class="form-select mb-3"><option value="create">Hanya data baru</option><option value="upsert">Buat atau perbarui berdasarkan kunci</option></select>
            <button class="btn btn-dark">Periksa file & lihat pratinjau</button>
        </form>
    </div>
    @if($selectedType==='variants')
    <div class="col-lg-5">
        <form class="panel h-100" method="post" action="{{ route('admin.import.generate') }}">@csrf
            <h2>Generator kombinasi SKU</h2>
            <label>Series</label><select name="series_id" class="form-select mb-2" required>@foreach($seriesOptions as $series)<option value="{{ $series->id }}">{{ $series->name }}</option>@endforeach</select>
            @foreach(['sku_prefix'=>'Awalan SKU','product_types'=>'Jenis produk (pisahkan koma)','colors'=>'Warna (pisahkan koma)','sizes'=>'Ukuran (pisahkan koma)','price'=>'Harga','stock'=>'Stok','weight'=>'Berat gram','lead_time'=>'Lead time'] as $key=>$label)<label>{{ $label }}</label><input class="form-control mb-2" name="{{ $key }}" @if(in_array($key,['price','stock','weight'])) type="number" @endif {{ in_array($key,['product_types','lead_time'])?'':'required' }}>@endforeach
            <select name="fulfillment" class="form-select mb-2"><option value="ready_stock">Ready stock</option><option value="pre_order">Pre-order</option></select><input type="hidden" name="mode" value="create"><button class="btn btn-outline-dark">Buat pratinjau kombinasi</button>
        </form>
    </div>
    @endif
</div>

@isset($preview)
<div class="panel mt-4">
    <h2>Pratinjau {{ count($preview) }} baris · {{ $mode==='upsert'?'UPSERT':'BARU' }}</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>Baris</th>@foreach(array_slice($definition['columns'],0,6) as $column)<th>{{ $column }}</th>@endforeach<th>Validasi</th></tr></thead><tbody>
        @foreach($preview as $index=>$row)<tr><td>{{ $index+2 }}</td>@foreach(array_slice($definition['columns'],0,6) as $column)<td>{{ \Illuminate\Support\Str::limit((string)($row[$column]??''),50) }}</td>@endforeach<td>@if(isset($rowErrors[$index+2]))<span class="text-danger">{{ implode(' ', $rowErrors[$index+2]) }}</span>@else<span class="text-success">Valid</span>@endif</td></tr>@endforeach
    </tbody></table></div>
    @if(!$rowErrors)<form method="post" action="{{ route('admin.import.commit') }}">@csrf<button class="btn btn-dark">Konfirmasi import {{ count($preview) }} baris</button></form>@else<div class="alert alert-warning">Perbaiki file dan buat pratinjau ulang. Belum ada data yang disimpan.</div>@endif
</div>
@endisset
@endif
@endsection
