@extends('layouts.admin')
@section('title','Produk saya')
@section('content')
<div class="admin-heading seller-product-heading">
    <div><span class="eyebrow">PENGELOLAAN KATALOG</span><h1>Produk saya</h1><p>Kelola produk, penjualan, harga, stok, dan kualitas informasi dari satu halaman.</p></div>
    <div class="seller-heading-actions"><details class="seller-mass-menu"><summary class="btn btn-outline-dark">Pengaturan massal ▾</summary><div><a href="{{ route('admin.import',['type'=>'series']) }}">Tambah / update produk</a><a href="{{ route('admin.import',['type'=>'variants']) }}">Update varian & SKU</a><a href="{{ route('admin.import',['type'=>'stock-adjustments']) }}">Update stok</a><a href="{{ route('admin.import',['type'=>'product-media']) }}">Update galeri</a></div></details><a class="btn btn-dark" href="{{ route('admin.products.create') }}">+ Tambah produk baru</a></div>
</div>

<nav class="seller-status-tabs" aria-label="Status produk">
@foreach(['all'=>'Semua','live'=>'Tayang','action'=>'Perlu diperbaiki','out'=>'Habis','draft'=>'Belum ditampilkan'] as $key=>$label)
    <a href="{{ route('admin.products',array_filter(['status'=>$key==='all'?null:$key,'q'=>request('q'),'category'=>request('category')])) }}" @class(['active'=>(request('status')?:'all')===$key])><span>{{ $label }}</span><b>{{ $statusCounts[$key] }}</b></a>
@endforeach
</nav>

<form class="seller-product-filter" method="get">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="seller-search-main"><label for="product-q">Nama produk atau SKU</label><input id="product-q" class="form-control" name="q" value="{{ request('q') }}" placeholder="Cari nama produk, SKU, atau slug"></div>
    <div><label for="product-category">Kategori</label><select id="product-category" class="form-select" name="category"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)request('category')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
    <div><label for="product-stock">Kondisi stok</label><select id="product-stock" class="form-select" name="stock"><option value="">Semua stok</option><option value="low" @selected(request('stock')==='low')>Stok perlu perhatian</option><option value="out" @selected(request('stock')==='out')>Ada SKU habis</option></select></div>
    <div class="seller-filter-actions"><button class="btn btn-dark">Cari</button><a class="btn btn-outline-dark" href="{{ route('admin.products',request('status')?['status'=>request('status')]:[]) }}">Atur ulang</a></div>
</form>

<form method="post" action="{{ route('admin.products.bulk') }}" id="bulk-products-form">@csrf
<div class="seller-bulk-bar" data-bulk-bar hidden><strong><span data-selected-count>0</span> produk dipilih</strong><div><button class="btn btn-outline-dark btn-sm" name="action" value="activate">Tayangkan</button><button class="btn btn-outline-dark btn-sm" name="action" value="deactivate">Sembunyikan</button><button class="btn btn-outline-dark btn-sm" name="action" value="feature">Jadikan unggulan</button><button class="btn btn-outline-dark btn-sm" name="action" value="unfeature">Hapus dari unggulan</button></div></div>
<div class="seller-product-table table-responsive"><table class="table"><thead><tr><th><input type="checkbox" data-select-all aria-label="Pilih semua produk"></th><th>Produk</th><th>Penjualan</th><th>Harga</th><th>Stok</th><th>Kualitas informasi</th><th>Aksi</th></tr></thead><tbody>
@forelse($products as $product)
    @php
        $available=max(0,(int)$product->variants_sum_stock-(int)$product->variants_sum_reserved);
        $qualityChecks=[filled($product->name),filled($product->description),$product->variants_count>0,(bool)$product->image||$product->media->isNotEmpty(),$product->variants_count>0&&$product->variants_min_price>0];
        $quality=(int)round(collect($qualityChecks)->filter()->count()/count($qualityChecks)*100);
        $priceText=$product->variants_min_price ? 'Rp '.number_format($product->variants_min_price,0,',','.') : '—';
        if($product->variants_max_price && $product->variants_max_price!==$product->variants_min_price)$priceText.=' – '.number_format($product->variants_max_price,0,',','.');
    @endphp
    <tr><td><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" data-product-check aria-label="Pilih {{ $product->name }}"></td>
    <td><div class="seller-product-cell"><img src="{{ $product->media->first()?->image_url ?? $product->image_url }}" alt=""><div><a href="{{ route('admin.products.edit',$product) }}"><strong>{{ $product->name }}</strong></a><span>ID {{ $product->id }} · {{ $product->category?->name }}</span><small>{{ $product->variants_count }} varian @if($product->featured) · Produk unggulan @endif</small></div></div></td>
    <td><strong>{{ number_format((int)$product->order_items_sum_quantity,0,',','.') }}</strong><small class="d-block">unit terjual</small></td>
    <td><strong>{{ $priceText }}</strong>@if($product->compare_at_price>$product->variants_min_price)<small class="d-block text-decoration-line-through">Rp {{ number_format($product->compare_at_price,0,',','.') }}</small>@endif</td>
    <td><strong @class(['text-danger'=>$available<=0])>{{ number_format($available,0,',','.') }}</strong><small class="d-block">{{ (int)$product->variants_sum_reserved }} direservasi</small></td>
    <td><div class="quality-meter"><div><span style="width:{{ $quality }}%"></span></div><strong>{{ $quality }}%</strong></div><small @class(['text-warning'=>$quality<100,'text-success'=>$quality===100])>{{ $quality===100?'Informasi lengkap':'Perlu ditingkatkan' }}</small></td>
    <td><div class="seller-row-actions"><a href="{{ route('admin.products.edit',$product) }}">Ubah</a>@if($product->active)<a href="{{ route('product',$product->slug) }}" target="_blank" rel="noopener">Lihat</a>@endif<details><summary>Lainnya ▾</summary><div><button type="submit" form="duplicate-{{ $product->id }}">Duplikat produk</button><a href="{{ route('admin.inventory',['q'=>$product->variants->first()?->sku]) }}">Lihat inventori</a><button class="text-danger" type="submit" form="delete-product-{{ $product->id }}">Hapus produk</button></div></details></div></td></tr>
@empty<tr><td colspan="7"><div class="admin-empty"><h3>Produk tidak ditemukan</h3><p>{{ request()->query()?'Ubah filter atau atur ulang pencarian.':'Tambahkan produk pertama untuk mulai mengisi katalog.' }}</p>@if(!request()->query())<a class="btn btn-dark" href="{{ route('admin.products.create') }}">+ Tambah produk baru</a>@endif</div></td></tr>@endforelse
</tbody></table></div></form>
@foreach($products as $product)<form method="post" action="{{ route('admin.products.duplicate',$product) }}" id="duplicate-{{ $product->id }}" class="d-none">@csrf</form><form method="post" action="{{ route('admin.destroy',['series',$product->id]) }}" id="delete-product-{{ $product->id }}" class="d-none" onsubmit="return confirm('Hapus produk beserta varian dan fotonya secara permanen?')">@csrf @method('DELETE')<input type="hidden" name="return_to" value="products"></form>@endforeach
<div class="seller-product-footer"><span>Menampilkan {{ $products->firstItem()??0 }}–{{ $products->lastItem()??0 }} dari {{ $products->total() }} produk</span>{{ $products->links() }}</div>
@endsection
@push('scripts')
<script>
const productChecks=[...document.querySelectorAll('[data-product-check]')],selectAll=document.querySelector('[data-select-all]'),bulkBar=document.querySelector('[data-bulk-bar]'),selectedCount=document.querySelector('[data-selected-count]');function syncProductSelection(){const count=productChecks.filter(item=>item.checked).length;selectedCount.textContent=count;bulkBar.hidden=count===0;if(selectAll){selectAll.checked=count>0&&count===productChecks.length;selectAll.indeterminate=count>0&&count<productChecks.length}}selectAll?.addEventListener('change',()=>{productChecks.forEach(item=>item.checked=selectAll.checked);syncProductSelection()});productChecks.forEach(item=>item.addEventListener('change',syncProductSelection));
</script>
@endpush
