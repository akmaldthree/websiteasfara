@extends('layouts.store')
@section('title',$currentCategory?->seo_title ?: ($currentCategory?->name ? $currentCategory->name.' — Dthree' : 'Koleksi — Dthree'))
@if($currentCategory)
@section('description',$currentCategory->seo_description ?: \Illuminate\Support\Str::limit($currentCategory->description ?? '',160))
@section('canonical',route('catalog',['category'=>$currentCategory->slug]))
@php($categorySchema=['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$currentCategory->name,'description'=>$currentCategory->seo_description ?: $currentCategory->description,'url'=>route('catalog',['category'=>$currentCategory->slug])])
@push('head')<script type="application/ld+json">{!! json_encode($categorySchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endpush
@endif
@section('content')
<section class="catalog-hero">
    <div class="container-xxl"><nav><a href="{{ route('home') }}">HOME</a> / <a href="{{ route('catalog') }}">SHOP</a> / {{ strtoupper($currentCategory?->name ?? (request('sale')?'SALE':'PRODUK')) }}</nav><h1>{{ strtoupper($currentCategory?->name ?? (request('sale')?'SALE':'PRODUK')) }}</h1>@if($currentCategory?->description)<p>{{ $currentCategory->description }}</p>@endif</div>
</section>
<section class="catalog-page">
    <div class="catalog-toolbar"><div class="catalog-density" aria-label="Ukuran gambar"><button type="button" data-grid="large" aria-label="Gambar besar">▦</button><button class="is-active" type="button" data-grid="normal" aria-label="Gambar sedang">▦</button><button type="button" data-grid="compact" aria-label="Gambar kecil">☷</button></div><strong>{{ $collections->total() }} PRODUK</strong><label for="sort-top">URUTKAN <select id="sort-top" data-sort-select><option value="new">Terbaru</option><option value="price_asc" @selected(request('sort')==='price_asc')>Harga terendah</option><option value="price_desc" @selected(request('sort')==='price_desc')>Harga tertinggi</option></select></label></div>
    <div class="catalog-content">
        <aside class="catalog-sidebar">
            <form method="get" id="catalog-filter-form">@if(request('sale'))<input type="hidden" name="sale" value="1">@endif<input type="hidden" name="sort" value="{{ request('sort','new') }}" data-sort-input>
                <div class="catalog-search"><input name="q" value="{{ request('q') }}" placeholder="Cari produk atau SKU"><button aria-label="Cari">⌕</button></div>
                <details open><summary>KATEGORI</summary><div class="catalog-filter-list"><label><input type="radio" name="category" value="" @checked(!request('category'))> Semua produk</label>@foreach($categories as $c)<label><input type="radio" name="category" value="{{ $c->slug }}" @checked(request('category')===$c->slug)> {{ $c->name }}</label>@endforeach</div></details>
                <details open><summary>KETERSEDIAAN</summary><label><input type="checkbox" name="available" value="1" @checked(request('available'))> Hanya stok tersedia</label></details>
                <details><summary>HARGA</summary><div class="catalog-price-inputs"><input name="min" value="{{ request('min') }}" inputmode="numeric" placeholder="Minimum"><input name="max" value="{{ request('max') }}" inputmode="numeric" placeholder="Maksimum"></div></details>
                <details><summary>VARIAN</summary><input name="color" value="{{ request('color') }}" placeholder="Warna"><input name="size" value="{{ request('size') }}" placeholder="Ukuran"><input name="product_type" value="{{ request('product_type') }}" placeholder="Jenis produk"></details>
                <details><summary>PEMENUHAN</summary><select name="fulfillment"><option value="">Semua</option><option value="ready_stock" @selected(request('fulfillment')==='ready_stock')>Ready stock</option><option value="pre_order" @selected(request('fulfillment')==='pre_order')>Pre-order</option></select></details>
                <button class="btn btn-dark w-100">TERAPKAN FILTER</button>@if(request()->hasAny(['q','category','color','size','product_type','min','max','fulfillment','available']))<a class="catalog-reset" href="{{ route('catalog',request('sale')?['sale'=>1]:[]) }}">HAPUS FILTER</a>@endif
            </form>
        </aside>
        <div class="catalog-results"><div class="catalog-grid" data-catalog-grid="normal">@forelse($collections as $collection)<div>@include('partials.product-card')</div>@empty<div class="empty-state"><h2>Produk tidak ditemukan</h2><p>Coba kata kunci atau pilihan filter lain.</p><a href="{{ route('catalog') }}" class="btn btn-outline-dark">Hapus filter</a></div>@endforelse</div><div class="mt-5">{{ $collections->links() }}</div></div>
    </div>
</section>
@if($recentlyViewed->isNotEmpty())<section class="recently-viewed container-xxl"><h2>RECENTLY VIEWED</h2><div class="product-carousel" data-drag-scroll>@foreach($recentlyViewed as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div></section>@endif
@endsection
@push('scripts')<script>
document.querySelectorAll('[data-grid]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-grid]').forEach(item=>item.classList.toggle('is-active',item===button));document.querySelector('[data-catalog-grid]').dataset.catalogGrid=button.dataset.grid;localStorage.setItem('dthree-grid',button.dataset.grid)}));const savedGrid=localStorage.getItem('dthree-grid');if(savedGrid){document.querySelector('[data-catalog-grid]').dataset.catalogGrid=savedGrid;document.querySelectorAll('[data-grid]').forEach(item=>item.classList.toggle('is-active',item.dataset.grid===savedGrid))}document.querySelector('[data-sort-select]')?.addEventListener('change',event=>{document.querySelector('[data-sort-input]').value=event.target.value;document.getElementById('catalog-filter-form').submit()});
</script>@endpush
