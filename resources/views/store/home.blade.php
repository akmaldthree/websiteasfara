@extends('layouts.store')
@section('body_class','home-transparent-header')
@section('content')
@php
    $heroSlides = collect([[
        'image' => !empty($shop['hero_image']) ? asset('storage/'.$shop['hero_image']) : asset('images/hero.svg'),
        'eyebrow' => $shop['hero_eyebrow'] ?? 'NEW COLLECTION',
        'title' => $shop['hero_title'] ?? '',
        'text' => $shop['hero_text'] ?? '',
        'url' => $shop['hero_button_url'] ?? route('catalog'),
        'label' => $shop['hero_button_label'] ?? 'Belanja sekarang',
    ]])->concat($contentEntries->where('type', 'banner')->map(fn ($entry) => [
        'image' => $entry->image_url ?: (!empty($shop['hero_image']) ? asset('storage/'.$shop['hero_image']) : asset('images/hero.svg')),
        'eyebrow' => 'DTHREE EDIT',
        'title' => $entry->title,
        'text' => $entry->summary ?: $entry->body,
        'url' => $entry->link_url ?: route('catalog'),
        'label' => $entry->link_label ?: 'Lihat koleksi',
    ])->values());
@endphp
<section class="commerce-hero" data-hero-slider aria-roledescription="carousel">
    @foreach($heroSlides as $slide)
        <article class="commerce-hero-slide {{ $loop->first?'is-active':'' }}" data-hero-slide aria-hidden="{{ $loop->first?'false':'true' }}">
            <img src="{{ $slide['image'] }}" alt="{{ $slide['title'] }}">
            <div class="commerce-hero-overlay"></div>
            <div class="container-xxl commerce-hero-copy"><span>{{ $slide['eyebrow'] }}</span><h1>{{ $slide['title'] }}</h1><p>{{ $slide['text'] }}</p><a class="btn btn-light" href="{{ $slide['url'] }}">{{ strtoupper($slide['label']) }}</a></div>
        </article>
    @endforeach
    @if($heroSlides->count()>1)<div class="hero-dots" aria-label="Pilih banner">@foreach($heroSlides as $slide)<button type="button" class="{{ $loop->first?'is-active':'' }}" data-hero-dot="{{ $loop->index }}" aria-label="Banner {{ $loop->iteration }}"></button>@endforeach</div>@endif
    <a class="hero-next-section" href="#featured" aria-label="Ke produk unggulan">↓</a>
</section>
<div class="benefit-strip"><div class="container-xxl">@foreach(['benefit_1','benefit_2','benefit_3'] as $key)@if(!empty($shop[$key]))<span>{{ $shop[$key] }}</span>@endif @endforeach</div></div>

@if($featured=$collections->first())
@php($featuredVariants=$featured->variants->where('fulfillment','!=','custom_made')->filter(fn($variant)=>$variant->available>0))
@php($featuredImages=$featured->media->pluck('image_url')->prepend($featured->image_url)->filter()->unique()->values())
<section class="featured-product-home container-xxl reveal" id="featured">
    <h2>FEATURED PRODUCT</h2>
    <div class="row g-0">
        <div class="col-lg-7 featured-product-photo"><img src="{{ $featuredImages->first() }}" alt="{{ $featured->name }}" data-featured-image><div class="featured-gallery-nav">@foreach($featuredImages->take(8) as $image)<button type="button" class="{{ $loop->first?'is-active':'' }}" data-featured-image-choice="{{ $image }}" aria-label="Foto {{ $loop->iteration }}"></button>@endforeach</div></div>
        <div class="col-lg-5 featured-product-info"><span>{{ strtoupper($featured->category->name) }}</span><h3><a href="{{ route('product',$featured->slug) }}">{{ strtoupper($featured->name) }}</a></h3><strong>@rupiah($featured->variants->min('price'))</strong><p>{{ $featured->description }}</p>
            <form method="post" action="{{ route('cart.add') }}" data-featured-form>@csrf<div class="featured-options">@if($featured->uses_product_type)<small>JENIS PRODUK</small><div>@foreach($featuredVariants->pluck('product_type')->filter()->unique() as $type)<button type="button" data-featured-kind="type" data-value="{{ mb_strtolower($type) }}">{{ $type }}</button>@endforeach</div>@endif<small>WARNA</small><div>@foreach($featuredVariants->pluck('color')->unique()->take(5) as $color)<button type="button" data-featured-kind="color" data-value="{{ mb_strtolower($color) }}">{{ $color }}</button>@endforeach</div><small>UKURAN</small><div>@foreach($featuredVariants->pluck('size')->unique()->take(6) as $size)<button type="button" data-featured-kind="size" data-value="{{ mb_strtolower($size) }}">{{ $size }}</button>@endforeach</div></div><select class="visually-hidden" name="variant_id" data-featured-select required><option value="">Pilih varian</option>@foreach($featuredVariants as $variant)<option value="{{ $variant->id }}" data-type="{{ mb_strtolower($variant->product_type) }}" data-color="{{ mb_strtolower($variant->color) }}" data-size="{{ mb_strtolower($variant->size) }}">{{ $variant->label }}</option>@endforeach</select><div class="featured-quantity"><label>JUMLAH</label><div class="quantity-stepper"><button type="button" data-featured-qty="-1">−</button><input type="number" name="quantity" value="1" min="1" max="100" data-featured-quantity><button type="button" data-featured-qty="1">+</button></div></div><div class="featured-actions"><button class="btn btn-dark" disabled data-featured-submit>ADD TO CART</button><button class="btn btn-outline-dark" name="buy_now" value="1" disabled data-featured-submit>BELI SEKARANG</button></div></form>
        </div>
    </div>
</section>
@endif

<section class="category-media-grid container-xxl reveal">
    @foreach($collections->unique('category_id')->take(3) as $categoryProduct)<a href="{{ route('catalog',['category'=>$categoryProduct->category->slug]) }}"><img src="{{ $categoryProduct->image_url }}" alt="{{ $categoryProduct->category->name }}"><span>{{ strtoupper($categoryProduct->category->name) }}</span></a>@endforeach
</section>

<section class="commerce-section container-xxl reveal">
    <div class="commerce-heading"><h2>FEATURED COLLECTION</h2><a href="{{ route('catalog') }}">VIEW ALL</a></div>
    <div class="collection-tabs" role="tablist"><button class="is-active" type="button" data-collection-tab="new">NEW ARRIVAL</button><button type="button" data-collection-tab="best">BEST SELLER</button></div>
    <div class="collection-pane is-active" data-collection-pane="new"><div class="product-carousel" data-drag-scroll>@forelse($collections->take(8) as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@empty<div class="empty-state"><h3>Koleksi sedang disiapkan.</h3></div>@endforelse</div></div>
    <div class="collection-pane" data-collection-pane="best"><div class="product-carousel" data-drag-scroll>@foreach($collections->sortByDesc(fn($item)=>(int)$item->sold_quantity)->take(8) as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div></div>
</section>

@if($look=$contentEntries->where('type','home_section')->first())
<section class="shop-look reveal">
    <div class="shop-look-copy"><span>STEAL THE LOOK!</span><h2>BEST COMBINATION</h2><p>{{ $look->summary ?: \Illuminate\Support\Str::limit($look->body,180) }}</p><a class="btn btn-outline-dark" href="{{ $look->link_url ?: route('catalog') }}">VIEW PRODUCTS</a></div>
    <div class="shop-look-image"><img src="{{ $look->image_url ?: $collections->first()?->image_url }}" alt="{{ $look->title }}"><span class="look-hotspot" aria-hidden="true">+</span></div>
</section>
@endif

@php($saleCollections=$collections->filter(fn($item)=>(int)$item->compare_at_price>(int)$item->variants->min('price'))->take(8))
@if($saleCollections->isNotEmpty())<section class="commerce-section sale-section container-xxl reveal"><div class="commerce-heading"><h2>SALE</h2><a href="{{ route('catalog',['sale'=>1]) }}">VIEW ALL</a></div><div class="product-carousel" data-drag-scroll>@foreach($saleCollections as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div></section>@endif

@foreach($contentEntries->where('type','home_section') as $entry)
<section class="commerce-campaign reveal {{ $loop->even?'commerce-campaign-reverse':'' }}">
    @if($entry->image_url)<div class="commerce-campaign-image"><img src="{{ $entry->image_url }}" alt="{{ $entry->title }}"></div>@endif
    <div class="commerce-campaign-copy"><span>{{ strtoupper(str_replace('_',' ',$entry->type)) }}</span><h2>{{ $entry->title }}</h2><p>{{ $entry->summary ?: \Illuminate\Support\Str::limit($entry->body,300) }}</p>@if($entry->link_url)<a class="btn btn-outline-dark" href="{{ $entry->link_url }}">{{ $entry->link_label ?: 'Selengkapnya' }}</a>@endif</div>
</section>
@endforeach

@if(!empty($shop['story_title']) || !empty($shop['story_text']))
<section class="commerce-story"><div><span>{{ $shop['story_eyebrow'] ?? 'DTHREE' }}</span><h2>{{ $shop['story_title'] ?? '' }}</h2><p>{{ $shop['story_text'] ?? '' }}</p><a href="{{ route('catalog') }}">Jelajahi koleksi →</a></div></section>
@endif
@if($shop['demo_mode'] ?? false)<div class="container-xxl demo-note">Pratinjau internal · Data contoh untuk pengujian.</div>@endif
@endsection
@push('scripts')<script>document.querySelectorAll('[data-collection-tab]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-collection-tab]').forEach(item=>item.classList.toggle('is-active',item===button));document.querySelectorAll('[data-collection-pane]').forEach(pane=>pane.classList.toggle('is-active',pane.dataset.collectionPane===button.dataset.collectionTab))}));const featuredSelected={};const featuredSelect=document.querySelector('[data-featured-select]');document.querySelectorAll('[data-featured-kind]').forEach(button=>button.addEventListener('click',()=>{featuredSelected[button.dataset.featuredKind]=button.dataset.value;document.querySelectorAll('[data-featured-kind="'+button.dataset.featuredKind+'"]').forEach(item=>item.classList.toggle('is-active',item===button));const match=[...featuredSelect.options].find(option=>option.value&&(!featuredSelected.type||option.dataset.type===featuredSelected.type)&&(!featuredSelected.color||option.dataset.color===featuredSelected.color)&&(!featuredSelected.size||option.dataset.size===featuredSelected.size));featuredSelect.value=match?.value||'';document.querySelectorAll('[data-featured-submit]').forEach(item=>item.disabled=!match)}));document.querySelectorAll('[data-featured-image-choice]').forEach(button=>button.addEventListener('click',()=>{document.querySelector('[data-featured-image]').src=button.dataset.featuredImageChoice;document.querySelectorAll('[data-featured-image-choice]').forEach(item=>item.classList.toggle('is-active',item===button))}));const featuredQuantity=document.querySelector('[data-featured-quantity]');document.querySelectorAll('[data-featured-qty]').forEach(button=>button.addEventListener('click',()=>{featuredQuantity.value=Math.max(1,Math.min(100,Number(featuredQuantity.value)+Number(button.dataset.featuredQty)))}));</script>@endpush
