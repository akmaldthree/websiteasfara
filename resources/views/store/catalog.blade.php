@extends('layouts.store')
@section('body_class','catalog-body')
@section('title',$currentCategory?->seo_title ?: ($currentCategory?->name ? $currentCategory->name.' — Asfara' : 'Koleksi — Asfara'))
@if($currentCategory)
@section('description',$currentCategory->seo_description ?: \Illuminate\Support\Str::limit($currentCategory->description ?? '',160))
@section('canonical',route('catalog',['category'=>$currentCategory->slug]))
@php($categorySchema=['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$currentCategory->name,'description'=>$currentCategory->seo_description ?: $currentCategory->description,'url'=>route('catalog',['category'=>$currentCategory->slug])])
@push('head')<script type="application/ld+json">{!! json_encode($categorySchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endpush
@endif
@section('content')
<section class="catalog-hero">
    <div class="container-xxl">
        <nav><a href="{{ route('home') }}">HOME</a> / <a href="{{ route('catalog') }}">SHOP</a> / {{ strtoupper($currentCategory?->name ?? (request('sale')?$storeCopy['sale_title']:$storeCopy['catalog_title'])) }}</nav>
        <div class="catalog-hero-heading"><div><h1>{{ $currentCategory?->name ?? (request('sale') ? $storeCopy['sale_title'] : $storeCopy['catalog_title']) }}</h1>@if($currentCategory?->description)<p>{{ $currentCategory->description }}</p>@endif</div></div>
        <nav class="catalog-category-strip" aria-label="Kategori produk"><a @class(['is-active'=>!request('category')]) href="{{ route('catalog') }}">ALL</a>@foreach($categories as $category)<a @class(['is-active'=>request('category')===$category->slug]) href="{{ route('catalog',['category'=>$category->slug]) }}">{{ strtoupper($category->name) }}</a>@endforeach<a @class(['is-active'=>request('sale')]) href="{{ route('catalog',['sale'=>1]) }}">SPECIAL OFFER</a></nav>
    </div>
</section>
<section class="catalog-page">
    <div class="catalog-toolbar"><div class="catalog-density" aria-label="Ukuran gambar"><button class="catalog-filter-button" type="button" aria-expanded="false" aria-controls="catalog-filters" data-filter-open><span aria-hidden="true">≡</span> {{ $storeCopy['catalog_filter'] }}@if($activeFilterCount=request()->collect()->only(['q','category','color','size','product_type','min','max','fulfillment','available'])->filter()->count()) <b>{{ $activeFilterCount }}</b>@endif</button><button type="button" data-grid="large" aria-label="Gambar besar">▦</button><button class="is-active" type="button" data-grid="normal" aria-label="Gambar sedang">▦</button><button type="button" data-grid="compact" aria-label="Gambar kecil">☷</button></div><strong>{{ $collections->total() }} {{ $storeCopy['catalog_title'] }}</strong><label for="sort-top">{{ $storeCopy['catalog_sort'] }} <select id="sort-top" data-sort-select>@foreach(['new'=>'Terbaru','featured'=>'Pilihan Asfara','best_selling'=>'Terlaris','price_asc'=>'Harga terendah','price_desc'=>'Harga tertinggi','title_asc'=>'Nama A–Z','title_desc'=>'Nama Z–A','oldest'=>'Terlama'] as $value=>$label)<option value="{{ $value }}" @selected(request('sort','new')===$value)>{{ $label }}</option>@endforeach</select></label></div>
    <div class="catalog-content">
        <aside class="catalog-sidebar" id="catalog-filters" aria-label="Filter produk" data-filter-panel><button type="button" class="filter-close" data-filter-close aria-label="Tutup filter">×</button>
            <form method="get" id="catalog-filter-form">@if(request('sale'))<input type="hidden" name="sale" value="1">@endif<input type="hidden" name="sort" value="{{ request('sort','new') }}" data-sort-input>
                <div class="catalog-search"><input type="search" name="q" value="{{ request('q') }}" aria-label="Cari produk atau SKU" placeholder="Cari produk atau SKU"><button type="submit" aria-label="Cari">⌕</button></div>
                <details open><summary>Ketersediaan</summary><label><input type="checkbox" name="available" value="1" @checked(request('available'))> Hanya stok tersedia</label></details>
                <details open><summary>Harga</summary><div class="catalog-price-inputs"><input name="min" value="{{ request('min') }}" inputmode="numeric" aria-label="Harga minimum" placeholder="Minimum"><input name="max" value="{{ request('max') }}" inputmode="numeric" aria-label="Harga maksimum" placeholder="Maksimum"></div></details>
                <details class="catalog-advanced" @if(request()->filled('color') || request()->filled('size') || request()->filled('product_type') || request()->filled('fulfillment')) open @endif>
                    <summary>Filter lanjutan</summary>
                <details><summary>KATEGORI</summary><div class="catalog-filter-list"><label><input type="radio" name="category" value="" @checked(!request('category'))> Semua produk</label>@foreach($categories as $c)<label><input type="radio" name="category" value="{{ $c->slug }}" @checked(request('category')===$c->slug)> {{ $c->name }}</label>@endforeach</div></details>
                <details><summary>WARNA</summary><div class="catalog-filter-list">@foreach($filterOptions['colors'] as $option)<label><input type="radio" name="color" value="{{ $option->color }}" @checked(request('color')===$option->color)> {{ $option->color }} <small>({{ $option->total }})</small></label>@endforeach</div></details>
                <details><summary>UKURAN</summary><div class="catalog-filter-list filter-chip-list">@foreach($filterOptions['sizes'] as $option)<label><input type="radio" name="size" value="{{ $option->size }}" @checked(request('size')===$option->size)><span>{{ $option->size }}</span><small>{{ $option->total }}</small></label>@endforeach</div></details>
                @if($filterOptions['types']->isNotEmpty())<details><summary>JENIS PRODUK</summary><div class="catalog-filter-list">@foreach($filterOptions['types'] as $option)<label><input type="radio" name="product_type" value="{{ $option->product_type }}" @checked(request('product_type')===$option->product_type)> {{ $option->product_type }} <small>({{ $option->total }})</small></label>@endforeach</div></details>@endif
                <details><summary>PEMENUHAN</summary><select name="fulfillment"><option value="">Semua</option><option value="ready_stock" @selected(request('fulfillment')==='ready_stock')>Ready stock</option><option value="pre_order" @selected(request('fulfillment')==='pre_order')>Pre-order</option></select></details>
                </details>
                <button type="submit" class="btn btn-dark w-100" data-filter-submit>TERAPKAN FILTER</button>@if(request()->hasAny(['q','category','color','size','product_type','min','max','fulfillment','available']))<a class="catalog-reset" href="{{ route('catalog',request('sale')?['sale'=>1]:[]) }}">HAPUS FILTER</a>@endif
            </form>
        </aside>
        @php($activeFilters=collect([
            'q'=>request('q') ? 'Pencarian: '.\Illuminate\Support\Str::limit(request('q'),28) : null,
            'category'=>$currentCategory?->name,
            'available'=>request('available') ? 'Stok tersedia' : null,
            'min'=>request('min') ? 'Harga mulai Rp '.number_format((int)request('min'),0,',','.') : null,
            'max'=>request('max') ? 'Harga sampai Rp '.number_format((int)request('max'),0,',','.') : null,
            'color'=>request('color') ? 'Warna: '.request('color') : null,
            'size'=>request('size') ? 'Ukuran: '.request('size') : null,
            'product_type'=>request('product_type') ? 'Jenis: '.request('product_type') : null,
            'fulfillment'=>request('fulfillment') ? (request('fulfillment')==='ready_stock'?'Ready stock':'Pre-order') : null,
        ])->filter())
        @php($withoutFilter=fn($key)=>route('catalog',collect(request()->query())->except([$key,'page'])->filter(fn($value)=>$value!==null&&$value!=='')->all()))
        <div class="catalog-results">@if($activeFilters->isNotEmpty())<div class="catalog-active-filters" aria-label="Filter aktif"><span>FILTER AKTIF</span>@foreach($activeFilters as $key=>$label)<a href="{{ $withoutFilter($key) }}" data-preserve-catalog-scroll>{{ $label }} <b aria-hidden="true">×</b></a>@endforeach<a class="catalog-clear-filters" href="{{ route('catalog',request('sale')?['sale'=>1]:[]) }}">Hapus semua</a></div>@endif<div class="catalog-grid" data-catalog-grid="normal">@forelse($collections as $collection)<div>@include('partials.product-card')</div>@empty<div class="empty-state catalog-empty-state"><span class="eyebrow">BELUM MENEMUKAN YANG DICARI</span><h2>{{ $storeCopy['catalog_empty_title'] }}</h2>@if(request('q'))<p>Tidak ada hasil untuk <strong>“{{ \Illuminate\Support\Str::limit(request('q'),50) }}”</strong>. Coba kata yang lebih umum, warna, ukuran, atau nama koleksi.</p>@else<p>{{ $storeCopy['catalog_empty_text'] }}</p>@endif<div><a href="{{ route('catalog') }}" class="btn btn-dark">Lihat semua produk</a><a href="{{ route('catalog',['sort'=>'best_selling']) }}" class="btn btn-outline-dark">Lihat produk terlaris</a></div>@if($categories->isNotEmpty())<nav aria-label="Kategori yang disarankan"><span>JELAJAHI KATEGORI</span>@foreach($categories->take(4) as $suggestedCategory)<a href="{{ route('catalog',['category'=>$suggestedCategory->slug]) }}">{{ strtoupper($suggestedCategory->name) }}</a>@endforeach</nav>@endif</div>@endforelse</div><div class="mt-5">{{ $collections->links() }}</div></div>
    </div>
</section>
@if($recentlyViewed->isNotEmpty())<section class="recently-viewed container-xxl"><h2>{{ $storeCopy['recently_viewed'] }}</h2><div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@foreach($recentlyViewed as $collection)<div class="product-carousel-item">@include('partials.product-card', ['cardContext' => 'catalog-recent-'])</div>@endforeach</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div></section>@endif
@endsection
@push('scripts')<script>
document.querySelectorAll('[data-grid]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-grid]').forEach(item=>item.classList.toggle('is-active',item===button));document.querySelector('[data-catalog-grid]').dataset.catalogGrid=button.dataset.grid;localStorage.setItem('asfara-grid',button.dataset.grid)}));const savedGrid=localStorage.getItem('asfara-grid');if(savedGrid){document.querySelector('[data-catalog-grid]').dataset.catalogGrid=savedGrid;document.querySelectorAll('[data-grid]').forEach(item=>item.classList.toggle('is-active',item.dataset.grid===savedGrid))}
const catalogForm = document.getElementById('catalog-filter-form');
const catalogScrollKey = 'asfara-catalog-scroll';
const rememberCatalogScroll = () => {
    try {
        sessionStorage.setItem(catalogScrollKey, JSON.stringify({path: location.pathname, y: scrollY}));
    } catch {}
};
catalogForm.addEventListener('submit', rememberCatalogScroll);
document.querySelector('[data-sort-select]')?.addEventListener('change', event => {
    const url = new URL(location.href);
    url.searchParams.set('sort', event.target.value);
    url.searchParams.delete('page');
    rememberCatalogScroll();
    location.assign(url);
});
document.querySelectorAll('[data-preserve-catalog-scroll]').forEach(link => link.addEventListener('click', rememberCatalogScroll));
try {
    const savedScroll = JSON.parse(sessionStorage.getItem(catalogScrollKey) || 'null');
    sessionStorage.removeItem(catalogScrollKey);
    if (savedScroll?.path === location.pathname) requestAnimationFrame(() => scrollTo(0, savedScroll.y));
} catch {}
const filterPanel = document.querySelector('[data-filter-panel]');
const filterTrigger = document.querySelector('[data-filter-open]');
const filterClose = document.querySelector('[data-filter-close]');
const setFilterOpen = open => {
    filterPanel.classList.toggle('is-open', open);
    filterTrigger?.setAttribute('aria-expanded', String(open));
    (open ? filterClose : filterTrigger)?.focus();
};
filterTrigger?.addEventListener('click', () => setFilterOpen(filterTrigger.getAttribute('aria-expanded') !== 'true'));
filterClose?.addEventListener('click', () => setFilterOpen(false));
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && filterTrigger?.getAttribute('aria-expanded') === 'true') setFilterOpen(false);
});
</script>@endpush
