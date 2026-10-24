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
<section class="featured-product-home container-xxl reveal" id="featured">
    <h2>FEATURED PRODUCT</h2>
    <div class="row g-0">
        <div class="col-lg-7 featured-product-photo"><img src="{{ $featured->media->first()?->image_url ?? $featured->image_url }}" alt="{{ $featured->name }}"></div>
        <div class="col-lg-5 featured-product-info"><span>{{ strtoupper($featured->category->name) }}</span><h3>{{ strtoupper($featured->name) }}</h3><strong>@rupiah($featured->variants->min('price'))</strong><p>{{ $featured->description }}</p><div class="featured-options"><small>WARNA</small><div>@foreach($featured->variants->pluck('color')->unique()->take(5) as $color)<span>{{ $color }}</span>@endforeach</div><small>UKURAN</small><div>@foreach($featured->variants->pluck('size')->unique()->take(6) as $size)<span>{{ $size }}</span>@endforeach</div></div><a class="btn btn-dark w-100" href="{{ route('product',$featured->slug) }}">PILIH VARIAN</a></div>
    </div>
</section>
@endif

<section class="category-media-grid container-xxl reveal">
    @foreach($collections->unique('category_id')->take(3) as $categoryProduct)<a href="{{ route('catalog',['category'=>$categoryProduct->category->slug]) }}"><img src="{{ $categoryProduct->image_url }}" alt="{{ $categoryProduct->category->name }}"><span>{{ strtoupper($categoryProduct->category->name) }}</span></a>@endforeach
</section>

<section class="commerce-section container-xxl reveal">
    <div class="commerce-heading"><h2>FEATURED COLLECTION</h2><a href="{{ route('catalog') }}">VIEW ALL</a></div>
    <div class="product-carousel" data-drag-scroll>@forelse($collections->take(8) as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@empty<div class="empty-state"><h3>Koleksi sedang disiapkan.</h3></div>@endforelse</div>
</section>

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
