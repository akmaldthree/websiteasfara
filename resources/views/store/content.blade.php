@extends('layouts.store')
@section('title',$entry->seo_title ?: $entry->title.' — '.($shop['brand_name'] ?? 'Asfara'))
@section('description',$entry->seo_description ?: \Illuminate\Support\Str::limit($entry->summary ?: $entry->body,160))
@section('body_class','editorial-body')
@if($entry->image_url)@section('image',$entry->image_url)@endif
@php
    $template = $entry->template ?: 'standard';
    $isCampaign = $entry->type === 'campaign';
    $hasProducts = isset($campaignProducts) && $campaignProducts->isNotEmpty();
    $mobileCover = $isCampaign ? $entry->mobileCoverUrl() : null;
    $film = $isCampaign ? collect($entry->blocks)->firstWhere('type', 'hero_options') : null;
    $filmDesktop = $film['video_url'] ?? null;
    $filmMobile = $film['mobile_video_url'] ?? null;
    if (!$filmDesktop && !$filmMobile && $entry->slug === 'demo-seri-ruang-raya' && ($shop['home_abaya_editorial_enabled'] ?? false)) {
        $filmDesktop = route('media.demo-hero', 'desktop');
        $filmMobile = route('media.demo-hero', 'mobile');
    }
@endphp
@section('content')
@if($preview ?? false)<div class="container-xxl demo-note">Pratinjau admin · konten ini mungkin belum terbit.</div>@endif
<article @class(['container-xxl section editorial-detail', 'campaign-detail' => $isCampaign || $hasProducts, 'page-narrow' => !$isCampaign && !$hasProducts && $template === 'standard', 'page-template-'.$template => !$isCampaign && !$hasProducts && $template !== 'standard'])>
    <nav class="content-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">HOME</a><span>/</span>@if(in_array($type ?? $entry->type, ['journal', 'lookbook', 'campaign'], true))<a href="{{ route('content.index',$type ?? $entry->type) }}">{{ strtoupper($type ?? $entry->type) }}</a><span>/</span>@endif<span>{{ strtoupper(\Illuminate\Support\Str::limit($entry->title,35)) }}</span></nav>
    @if($isCampaign)
        <header @class(['campaign-intro', 'campaign-intro-with-image' => $entry->image_url || $mobileCover])>
            <div class="campaign-intro-copy">
                <span class="eyebrow">{{ config('storefront.campaign_groups.'.$entry->campaignGroup()) }}</span>
                <h1>{{ $entry->title }}</h1>
                @if($entry->summary)<p>{{ $entry->summary }}</p>@endif
                @if($hasProducts)<a class="btn btn-dark" href="#campaign-products-heading">Lihat produk</a>
                @elseif($entry->link_url)<a class="btn btn-dark" href="{{ $entry->link_url }}">{{ $entry->link_label ?: 'Selengkapnya' }}</a>@endif
            </div>
            @if($entry->image_url || $mobileCover)
                <picture>
                    @if($mobileCover)<source media="(max-width: 767px)" srcset="{{ $mobileCover }}">@endif
                    <img src="{{ $entry->image_url ?: $mobileCover }}" alt="" width="1200" height="800" loading="eager" fetchpriority="high" decoding="async">
                </picture>
            @endif
        </header>
        @if($filmDesktop || $filmMobile)
            <section class="campaign-film" aria-labelledby="campaign-film-title">
                <h2 id="campaign-film-title">Film Kampanye</h2>
                <video controls playsinline preload="none" @if($entry->image_url) poster="{{ $entry->image_url }}" @endif>
                    @if($filmDesktop && $filmMobile)<source media="(max-width: 767px)" src="{{ $filmMobile }}">@endif
                    <source src="{{ $filmDesktop ?: $filmMobile }}">
                    <a href="{{ $filmDesktop ?: $filmMobile }}">Buka video kampanye</a>
                </video>
            </section>
        @endif
    @else
    <header @class(['text-center py-5'=>$template==='landing'])><span class="eyebrow">{{ strtoupper(str_replace('_',' ',$type ?? $entry->type)) }}</span><h1>{{ $entry->title }}</h1>@if($entry->summary)<p class="lead">{{ $entry->summary }}</p>@endif</header>
    @if($entry->image_url)
        <div class="editorial-cover mb-4">
            <img class="w-100" src="{{ $entry->image_url }}" alt="{{ $entry->title }}" width="1200" height="800" loading="eager" fetchpriority="high" decoding="async">
            @if(($type ?? $entry->type) === 'lookbook')
                @foreach(collect($entry->blocks)->where('type', 'product_hotspot') as $spot)
                    @if($spotProduct = ($campaignProducts ?? collect())->firstWhere('id', $spot['series_id'] ?? null))
                        <a class="look-hotspot" style="left:{{ (float) ($spot['x'] ?? 50) }}%;top:{{ (float) ($spot['y'] ?? 50) }}%" href="{{ route('product', $spotProduct->slug) }}" aria-label="Lihat {{ $spotProduct->name }}">
                            <span class="look-hotspot-marker" aria-hidden="true"></span>
                        </a>
                    @endif
                @endforeach
            @endif
        </div>
    @endif
    @endif
    <div class="editorial-detail-body">
        @if($entry->body && (!$entry->blocks || $entry->campaignProductIds()->isNotEmpty() || $entry->type === 'home_section'))<div class="preserve-lines">{{ $entry->body }}</div>@endif
        @if($entry->blocks)@include('partials.content-blocks',['contentBlocks'=>$entry->blocks])@endif
    </div>
    @if($hasProducts)
        <section class="campaign-products" aria-labelledby="campaign-products-heading">
            <h2 id="campaign-products-heading" class="mt-5 mb-4" tabindex="-1">Belanja koleksi</h2>
            <div class="campaign-product-grid">
                @foreach($campaignProducts as $collection)
                    @include('partials.product-card', ['cardContext' => 'campaign-page-'])
                @endforeach
            </div>
        </section>
    @endif
    @if($entry->link_url && (!$isCampaign || $hasProducts))<a class="btn btn-dark mt-4" href="{{ $entry->link_url }}">{{ $entry->link_label ?: 'Selengkapnya' }}</a>@endif
</article>
@endsection
