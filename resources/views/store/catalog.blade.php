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
<div data-catalog-browser>
<section class="catalog-hero" data-catalog-heading>
    <div class="container-xxl">
        <nav><a href="{{ route('home') }}">HOME</a> / <a href="{{ route('catalog') }}">SHOP</a> / {{ strtoupper($currentCategory?->name ?? (request('sale')?$storeCopy['sale_title']:$storeCopy['catalog_title'])) }}</nav>
        <div class="catalog-hero-heading"><div><h1>{{ $currentCategory?->name ?? (request('sale') ? $storeCopy['sale_title'] : $storeCopy['catalog_title']) }}</h1>@if($currentCategory?->description)<p>{{ $currentCategory->description }}</p>@endif</div></div>
        <nav class="catalog-category-strip" aria-label="Kategori produk"><a @class(['is-active'=>!request('category') && !request('sale')]) href="{{ route('catalog') }}" data-catalog-url>ALL</a>@foreach($categories as $category)<a @class(['is-active'=>request('category')===$category->slug]) href="{{ route('catalog',['category'=>$category->slug]) }}" data-catalog-url>{{ strtoupper($category->name) }}</a>@endforeach<a @class(['is-active'=>request('sale')]) href="{{ route('catalog',['sale'=>1]) }}" data-catalog-url>SPECIAL OFFER</a></nav>
    </div>
</section>
<section class="catalog-page">
    <div class="catalog-toolbar"><div class="catalog-density" aria-label="Ukuran gambar"><button class="catalog-filter-button" type="button" aria-expanded="false" aria-controls="catalog-filters" data-filter-open><span aria-hidden="true">≡</span> {{ $storeCopy['catalog_filter'] }}@if($activeFilterCount=request()->collect()->only(['q','category','color','size','product_type','min','max','fulfillment','available'])->filter()->count()) <b>{{ $activeFilterCount }}</b>@endif</button><button type="button" data-grid="large" aria-label="Gambar besar">▦</button><button class="is-active" type="button" data-grid="normal" aria-label="Gambar sedang">▦</button><button type="button" data-grid="compact" aria-label="Gambar kecil">☷</button></div><strong>{{ $collections->total() }} {{ $storeCopy['catalog_title'] }}</strong><label for="sort-top">{{ $storeCopy['catalog_sort'] }} <select id="sort-top" data-sort-select>@foreach(['new'=>'Terbaru','featured'=>'Pilihan Asfara','best_selling'=>'Terlaris','price_asc'=>'Harga terendah','price_desc'=>'Harga tertinggi','title_asc'=>'Nama A–Z','title_desc'=>'Nama Z–A','oldest'=>'Terlama'] as $value=>$label)<option value="{{ $value }}" @selected(request('sort','new')===$value)>{{ $label }}</option>@endforeach</select></label></div>
    <div class="catalog-content">
        <button type="button" class="catalog-filter-backdrop" aria-label="Tutup panel filter" data-filter-backdrop tabindex="-1" hidden></button>
        <aside class="catalog-sidebar" id="catalog-filters" aria-label="Filter produk" data-filter-panel><button type="button" class="filter-close" data-filter-close aria-label="Tutup filter">×</button>
            <form method="get" action="{{ route('catalog') }}" id="catalog-filter-form"><input type="hidden" name="sale" value="{{ request('sale') ? '1' : '' }}"><input type="hidden" name="sort" value="{{ request('sort','new') }}" data-sort-input>
                <div class="catalog-search"><input type="search" name="q" value="{{ request('q') }}" aria-label="Cari produk atau SKU" placeholder="Cari produk atau SKU"><button type="submit" aria-label="Cari">⌕</button></div>
                <details @if($currentCategory) open @endif><summary>Kategori</summary><div class="catalog-filter-list"><label><input type="radio" name="category" value="" data-filter-instant @checked(!request('category'))> Semua produk</label>@foreach($categories as $c)<label><input type="radio" name="category" value="{{ $c->slug }}" data-filter-instant @checked(request('category')===$c->slug)> {{ $c->name }}</label>@endforeach</div></details>
                <details open><summary>Ketersediaan</summary><label><input type="checkbox" name="available" value="1" data-filter-instant @checked(request('available'))> Hanya stok tersedia</label></details>
                <details open><summary>Harga</summary><div class="catalog-price-inputs"><input name="min" value="{{ request('min') }}" inputmode="numeric" aria-label="Harga minimum" placeholder="Minimum"><input name="max" value="{{ request('max') }}" inputmode="numeric" aria-label="Harga maksimum" placeholder="Maksimum"></div></details>
                <details class="catalog-advanced" @if(request()->filled('color') || request()->filled('size') || request()->filled('product_type') || request()->filled('fulfillment')) open @endif>
                    <summary>Filter lanjutan</summary>
                <details @if(request()->filled('color')) open @endif><summary>WARNA</summary><div class="catalog-filter-list">@foreach($filterOptions['colors'] as $option)<label><input type="radio" name="color" value="{{ $option->color }}" data-filter-instant @checked(request('color')===$option->color)> {{ $option->color }} <small>({{ $option->total }})</small></label>@endforeach</div></details>
                <details @if(request()->filled('size')) open @endif><summary>UKURAN</summary><div class="catalog-filter-list filter-chip-list">@foreach($filterOptions['sizes'] as $option)<label><input type="radio" name="size" value="{{ $option->size }}" data-filter-instant @checked(request('size')===$option->size)><span>{{ $option->size }}</span><small>{{ $option->total }}</small></label>@endforeach</div></details>
                @if($filterOptions['types']->isNotEmpty())<details @if(request()->filled('product_type')) open @endif><summary>JENIS PRODUK</summary><div class="catalog-filter-list">@foreach($filterOptions['types'] as $option)<label><input type="radio" name="product_type" value="{{ $option->product_type }}" data-filter-instant @checked(request('product_type')===$option->product_type)> {{ $option->product_type }} <small>({{ $option->total }})</small></label>@endforeach</div></details>@endif
                <details @if(request()->filled('fulfillment')) open @endif><summary>PEMENUHAN</summary><select name="fulfillment" data-filter-instant aria-label="Pemenuhan"><option value="">Semua</option><option value="ready_stock" @selected(request('fulfillment')==='ready_stock')>Ready stock</option><option value="pre_order" @selected(request('fulfillment')==='pre_order')>Pre-order</option></select></details>
                </details>
                <button type="submit" class="btn btn-dark w-100" data-filter-submit>TERAPKAN FILTER</button><a class="catalog-reset" href="{{ route('catalog',request('sale')?['sale'=>1]:[]) }}" data-catalog-url @if(!$activeFilterCount) hidden @endif>HAPUS FILTER</a>
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
        <div class="catalog-results" data-catalog-results tabindex="-1">@if($activeFilters->isNotEmpty())<div class="catalog-active-filters" aria-label="Filter aktif"><span>FILTER AKTIF</span>@foreach($activeFilters as $key=>$label)<a href="{{ $withoutFilter($key) }}" data-catalog-url>{{ $label }} <b aria-hidden="true">×</b></a>@endforeach<a class="catalog-clear-filters" href="{{ route('catalog',request('sale')?['sale'=>1]:[]) }}" data-catalog-url>Hapus semua</a></div>@endif<div class="catalog-grid" data-catalog-grid="normal">@forelse($collections as $collection)<div>@include('partials.product-card')</div>@empty<div class="empty-state catalog-empty-state"><span class="eyebrow">BELUM MENEMUKAN YANG DICARI</span><h2>{{ $storeCopy['catalog_empty_title'] }}</h2>@if(request('q'))<p>Tidak ada hasil untuk <strong>“{{ \Illuminate\Support\Str::limit(request('q'),50) }}”</strong>. Coba kata yang lebih umum, warna, ukuran, atau nama koleksi.</p>@else<p>{{ $storeCopy['catalog_empty_text'] }}</p>@endif<div><a href="{{ route('catalog') }}" class="btn btn-dark" data-catalog-url>Lihat semua produk</a><a href="{{ route('catalog',['sort'=>'best_selling']) }}" class="btn btn-outline-dark" data-catalog-url>Lihat produk terlaris</a></div>@if($categories->isNotEmpty())<nav aria-label="Kategori yang disarankan"><span>JELAJAHI KATEGORI</span>@foreach($categories->take(4) as $suggestedCategory)<a href="{{ route('catalog',['category'=>$suggestedCategory->slug]) }}" data-catalog-url>{{ strtoupper($suggestedCategory->name) }}</a>@endforeach</nav>@endif</div>@endforelse</div><div class="mt-5">{{ $collections->links() }}</div></div>
    </div>
</section>
<p class="visually-hidden" role="status" data-catalog-status></p>
</div>
@if($recentlyViewed->isNotEmpty())<section class="recently-viewed container-xxl"><h2>{{ $storeCopy['recently_viewed'] }}</h2><div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@foreach($recentlyViewed as $collection)<div class="product-carousel-item">@include('partials.product-card', ['cardContext' => 'catalog-recent-'])</div>@endforeach</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div></section>@endif
@endsection
@push('scripts')<script>
const catalog = document.querySelector('[data-catalog-browser]');
const catalogForm = document.getElementById('catalog-filter-form');
const resultCount = catalog.querySelector('.catalog-toolbar > strong');
const catalogStatus = catalog.querySelector('[data-catalog-status]');
const sort = catalog.querySelector('[data-sort-select]');
const gridButtons = [...catalog.querySelectorAll('[data-grid]')];
let gridDensity = 'normal';
try { gridDensity = localStorage.getItem('asfara-grid') || gridDensity; } catch {}
const setGrid = density => {
    gridDensity = gridButtons.some(button => button.dataset.grid === density) ? density : 'normal';
    catalog.querySelector('[data-catalog-grid]').dataset.catalogGrid = gridDensity;
    gridButtons.forEach(button => {
        const active = button.dataset.grid === gridDensity;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
    });
};
gridButtons.forEach(button => button.addEventListener('click', () => {
    setGrid(button.dataset.grid);
    try { localStorage.setItem('asfara-grid', gridDensity); } catch {}
}));
setGrid(gridDensity);

const formUrl = () => {
    const url = new URL(catalogForm.action);
    for (const [field, value] of new FormData(catalogForm)) {
        if (value.trim()) url.searchParams.set(field, value.trim());
    }
    return url;
};
const filterPanel = document.querySelector('[data-filter-panel]');
const filterTrigger = document.querySelector('[data-filter-open]');
const filterClose = document.querySelector('[data-filter-close]');
const filterBackdrop = document.querySelector('[data-filter-backdrop]');
const setFilterOpen = open => {
    filterPanel.classList.toggle('is-open', open);
    filterTrigger.setAttribute('aria-expanded', String(open));
    filterBackdrop.hidden = !open;
    (open ? filterClose : filterTrigger).focus();
};
filterTrigger.addEventListener('click', () => setFilterOpen(filterTrigger.getAttribute('aria-expanded') !== 'true'));
filterClose.addEventListener('click', () => setFilterOpen(false));
filterBackdrop.addEventListener('click', () => setFilterOpen(false));
document.addEventListener('keydown', event => {
    if (!filterPanel.classList.contains('is-open')) return;
    if (event.key === 'Escape') setFilterOpen(false);
    if (event.key !== 'Tab') return;
    const lastControl = catalogForm.querySelector('.catalog-reset:not([hidden])') ?? catalogForm.querySelector('[data-filter-submit]');
    const edge = event.shiftKey ? filterClose : lastControl;
    if (document.activeElement !== edge && filterPanel.contains(document.activeElement)) return;
    event.preventDefault();
    (event.shiftKey ? lastControl : filterClose).focus();
});
matchMedia('(min-width: 768px)').addEventListener('change', event => {
    if (event.matches && filterPanel.classList.contains('is-open')) setFilterOpen(false);
});

let pendingRequest;
const updateCatalog = async (url, {fromHistory = false, closeFilter = false, scrollToResults = false, syncForm = false} = {}) => {
    pendingRequest?.abort();
    const request = new AbortController();
    pendingRequest = request;
    const heading = catalog.querySelector('[data-catalog-heading]');
    const results = catalog.querySelector('[data-catalog-results]');
    results.setAttribute('aria-busy', 'true');
    catalogStatus.textContent = 'Memuat produk…';
    try {
        const response = await fetch(url, {signal: request.signal});
        if (!response.ok) throw new Error('Catalog response failed');
        const next = new DOMParser().parseFromString(await response.text(), 'text/html');
        if (request.signal.aborted) return;
        const nextHeading = next.querySelector('[data-catalog-heading]');
        const nextResults = next.querySelector('[data-catalog-results]');
        const nextCount = next.querySelector('.catalog-toolbar > strong');
        if (!nextHeading || !nextResults || !nextCount) throw new Error('Catalog response is incomplete');
        const moveFocus = results.contains(document.activeElement) || heading.contains(document.activeElement);
        heading.replaceWith(nextHeading);
        results.replaceWith(nextResults);
        resultCount.textContent = nextCount.textContent;
        const badge = filterTrigger.querySelector('b');
        const nextBadge = next.querySelector('[data-filter-open] b');
        badge?.remove();
        if (nextBadge) filterTrigger.append(nextBadge);
        const reset = catalogForm.querySelector('.catalog-reset');
        const nextReset = next.querySelector('.catalog-reset');
        reset.href = nextReset.href;
        reset.hidden = nextReset.hidden;
        if (syncForm) {
            for (const input of catalogForm.elements) {
                if (!input.name) continue;
                const value = url.searchParams.get(input.name) || '';
                if (input.type === 'checkbox' || input.type === 'radio') input.checked = input.value === value;
                else input.value = value;
            }
            sort.value = next.querySelector('[data-sort-select]').value;
            catalogForm.elements.namedItem('sort').value = sort.value;
        }
        setGrid(gridDensity);
        if (next.title) document.title = next.title;
        if (!fromHistory && url.href !== location.href) history.pushState(null, '', url);
        catalogStatus.textContent = resultCount.textContent.trim() + ' ditampilkan.';
        if (closeFilter && filterPanel.classList.contains('is-open')) setFilterOpen(false);
        if (moveFocus || scrollToResults) nextResults.focus({preventScroll: true});
        if (scrollToResults) catalog.querySelector('.catalog-toolbar').scrollIntoView();
    } catch {
        if (!request.signal.aborted) {
            if (fromHistory) location.replace(url);
            else location.assign(url);
        }
    } finally {
        if (pendingRequest === request) {
            pendingRequest = null;
            results.removeAttribute('aria-busy');
        }
    }
};

catalogForm.addEventListener('submit', event => {
    event.preventDefault();
    updateCatalog(formUrl(), {closeFilter: true});
});
catalogForm.addEventListener('change', event => {
    if (event.target.matches('[data-filter-instant]')) updateCatalog(formUrl());
});
sort.addEventListener('change', () => {
    catalogForm.elements.namedItem('sort').value = sort.value;
    updateCatalog(formUrl());
});
catalog.addEventListener('click', event => {
    const link = event.target.closest('[data-catalog-url], [data-catalog-results] .pagination a');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    updateCatalog(new URL(link.href), {scrollToResults: Boolean(link.closest('.pagination')), syncForm: true});
});
window.addEventListener('popstate', () => updateCatalog(new URL(location.href), {fromHistory: true, syncForm: true}));
</script>@endpush
