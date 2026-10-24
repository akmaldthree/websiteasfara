@extends('layouts.store')
@section('title',$currentCategory?->seo_title ?: ($currentCategory?->name ? $currentCategory->name.' — Dthree' : 'Koleksi — Dthree'))
@if($currentCategory)
@section('description',$currentCategory->seo_description ?: \Illuminate\Support\Str::limit($currentCategory->description ?? '',160))
@section('canonical',route('catalog',['category'=>$currentCategory->slug]))
@php($categorySchema=['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$currentCategory->name,'description'=>$currentCategory->seo_description ?: $currentCategory->description,'url'=>route('catalog',['category'=>$currentCategory->slug])])
@push('head')<script type="application/ld+json">{!! json_encode($categorySchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endpush
@endif
@section('content')
<section class="catalog-page container-xxl">
    <div class="catalog-title"><div><span>DTHREE COLLECTIONS</span><h1>{{ $currentCategory?->name ?? 'Semua Koleksi' }}</h1>@if($currentCategory?->description)<p>{{ $currentCategory->description }}</p>@endif</div><span>{{ $collections->total() }} produk</span></div>
    <details class="catalog-filter" @if(request()->hasAny(['q','category','sort','color','size','product_type','min','max','fulfillment','available'])) open @endif>
        <summary>☷ &nbsp; Filter & Urutan</summary>
        <form method="get"><div class="row g-3">
            <div class="col-lg-4"><label for="search">Cari produk atau SKU</label><input id="search" class="form-control" name="q" value="{{ request('q') }}" placeholder="Cari koleksi..."></div>
            <div class="col-6 col-lg-2"><label for="category">Kategori</label><select id="category" class="form-select" name="category"><option value="">Semua</option>@foreach($categories as $c)<option value="{{ $c->slug }}" @selected(request('category')===$c->slug)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-6 col-lg-2"><label for="sort">Urutkan</label><select id="sort" name="sort" class="form-select"><option value="new">Terbaru</option><option value="price_asc" @selected(request('sort')==='price_asc')>Harga terendah</option><option value="price_desc" @selected(request('sort')==='price_desc')>Harga tertinggi</option></select></div>
            <div class="col-6 col-lg-2"><label for="color">Warna</label><input class="form-control" id="color" name="color" value="{{ request('color') }}"></div>
            <div class="col-6 col-lg-2"><label for="size">Ukuran</label><input class="form-control" id="size" name="size" value="{{ request('size') }}"></div>
            <div class="col-6 col-lg-2"><label for="product_type">Jenis produk</label><input class="form-control" id="product_type" name="product_type" value="{{ request('product_type') }}"></div>
            <div class="col-6 col-lg-2"><label for="min">Harga minimum</label><input class="form-control" id="min" name="min" value="{{ request('min') }}" inputmode="numeric"></div>
            <div class="col-6 col-lg-2"><label for="max">Harga maksimum</label><input class="form-control" id="max" name="max" value="{{ request('max') }}" inputmode="numeric"></div>
            <div class="col-6 col-lg-2"><label for="fulfillment">Pemenuhan</label><select id="fulfillment" name="fulfillment" class="form-select"><option value="">Semua</option><option value="ready_stock" @selected(request('fulfillment')==='ready_stock')>Ready stock</option><option value="pre_order" @selected(request('fulfillment')==='pre_order')>Pre-order</option></select></div>
            <div class="col-lg-4 d-flex align-items-end gap-2"><label class="catalog-check"><input type="checkbox" name="available" value="1" @checked(request('available'))> Hanya stok tersedia</label><button class="btn btn-dark flex-grow-1">Terapkan</button><a href="{{ route('catalog') }}" class="btn btn-outline-dark">Reset</a></div>
        </div></form>
    </details>
    <div class="row g-3 g-lg-4 catalog-grid">@forelse($collections as $collection)<div class="col-6 col-lg-4">@include('partials.product-card')</div>@empty<div class="col-12 empty-state"><h2>Produk tidak ditemukan</h2><p>Coba kata kunci atau pilihan filter lain.</p><a href="{{ route('catalog') }}" class="btn btn-outline-dark">Hapus filter</a></div>@endforelse</div>
    <div class="mt-5">{{ $collections->links() }}</div>
</section>
@endsection
