@extends('layouts.admin')
@section('title','Kelola produk')
@section('content')
<div class="admin-heading">
    <div><span class="eyebrow">PRODUCT WORKSPACE</span><h1>Kelola produk</h1><p>Informasi produk, varian, foto, harga, dan stok dalam satu alur kerja.</p></div>
    <div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-dark" href="{{ route('admin.import',['type'=>'series']) }}">Import Excel</a><a class="btn btn-dark" href="{{ route('admin.products.create') }}">+ Produk baru</a></div>
</div>
<form class="product-filter panel mb-4" method="get">
    <div><label for="product-q">Cari produk atau SKU</label><input id="product-q" class="form-control" name="q" value="{{ request('q') }}" placeholder="Nama produk, slug, atau SKU"></div>
    <div><label for="product-category">Kategori</label><select id="product-category" class="form-select" name="category"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)request('category')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
    <div><label for="product-status">Status</label><select id="product-status" class="form-select" name="status"><option value="">Semua status</option><option value="active" @selected(request('status')==='active')>Tayang</option><option value="draft" @selected(request('status')==='draft')>Draft</option></select></div>
    <div><label for="product-stock">Stok</label><select id="product-stock" class="form-select" name="stock"><option value="">Semua stok</option><option value="low" @selected(request('stock')==='low')>Perlu perhatian</option><option value="out" @selected(request('stock')==='out')>Habis</option></select></div>
    <div class="d-flex gap-2 align-items-end"><button class="btn btn-dark">Terapkan</button>@if(request()->query())<a class="btn btn-outline-dark" href="{{ route('admin.products') }}">Reset</a>@endif</div>
</form>
<div class="product-list">
@forelse($products as $product)
    @php($available=max(0,(int)$product->variants_sum_stock-(int)$product->variants_sum_reserved))
    @php($complete=$product->variants_count>0 && ($product->image || $product->media->isNotEmpty()) && filled($product->description))
    <article class="product-row-card">
        <a class="product-row-image" href="{{ route('admin.products.edit',$product) }}"><img src="{{ $product->media->first()?->image_url ?? $product->image_url }}" alt=""></a>
        <div class="product-row-main"><div class="d-flex gap-2 align-items-center flex-wrap"><h2>{{ $product->name }}</h2><span @class(['badge','workspace-status-live'=>$product->active])>{{ $product->active?'Tayang':'Draft' }}</span>@if(!$complete)<span class="badge workspace-status-warning">Belum lengkap</span>@endif</div><p>{{ $product->category?->name }} · <code>{{ $product->slug }}</code></p><div class="product-row-metrics"><span><strong>{{ $product->variants_count }}</strong> varian</span><span><strong>{{ $available }}</strong> tersedia</span><span><strong>{{ $product->variants_min_price ? 'Rp '.number_format($product->variants_min_price,0,',','.') : '—' }}</strong> harga mulai</span><span><strong>{{ $product->media->count() }}</strong> foto galeri</span></div></div>
        <div class="product-row-actions"><a class="btn btn-dark btn-sm" href="{{ route('admin.products.edit',$product) }}">Kelola produk</a>@if($product->active)<a class="btn btn-outline-dark btn-sm" href="{{ route('product',$product->slug) }}" target="_blank" rel="noopener">Preview ↗</a>@endif</div>
    </article>
@empty
    <div class="admin-empty panel"><h3>Produk tidak ditemukan</h3><p>{{ request()->query()?'Ubah filter atau reset pencarian.':'Buat produk pertama untuk mulai mengisi katalog.' }}</p>@if(!request()->query())<a class="btn btn-dark" href="{{ route('admin.products.create') }}">+ Produk baru</a>@endif</div>
@endforelse
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
