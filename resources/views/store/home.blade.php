@extends('layouts.store')
@section('body_class','home-transparent-header')
@section('content')
@php
    $curated = function (string $key, $fallback) use ($shop, $collections) {
        $slugs = collect(preg_split('/[,\r\n]+/', $shop[$key] ?? ''))->map(fn ($slug) => trim($slug))->filter();
        if ($slugs->isEmpty()) return collect($fallback)->take(8);
        return $slugs->map(fn ($slug) => $collections->firstWhere('slug', $slug))->filter()->values()->take(8);
    };
    $newCollections = $curated('home_new_slugs', $collections);
    $categorySlugs = collect(preg_split('/[,\r\n]+/', $shop['home_category_slugs'] ?? ''))->map(fn($slug)=>trim($slug))->filter();
    $categoryCollections = $categorySlugs->isEmpty() ? $collections->unique('category_id')->take(3) : $categorySlugs->map(fn($slug)=>$collections->firstWhere('slug',$slug))->filter()->unique('category_id')->take(3);
    $defaultOrder = collect(array_keys(config('storefront.home_sections')));
    $homeOrder = collect($shop['home_section_order'] ?? $defaultOrder->all());
    if (!$homeOrder->contains('campaign') || !$homeOrder->contains('new_in')) {
        $openingSections = $defaultOrder->take(5);
        $homeOrder = $openingSections->concat($homeOrder->diff($openingSections));
    }
    $homeOrder = $homeOrder->intersect($defaultOrder)->concat($defaultOrder->diff($homeOrder))->values();
    $homePosition = fn(string $section) => (($position=$homeOrder->search($section)) === false ? 99 : $position) + 1;
    $heroVideoUrl = fn ($path) => match ($path) {
        'demo/hero-desktop.webm' => route('media.demo-hero', ['format' => 'desktop', 'v' => filemtime(database_path('seeders/assets/demo/hero-desktop.webm'))]),
        'demo/hero-mobile.webm' => route('media.demo-hero', ['format' => 'mobile', 'v' => filemtime(database_path('seeders/assets/demo/hero-mobile.webm'))]),
        null, '' => null,
        default => asset('storage/'.$path),
    };
    $videoHero = ($shop['hero_video_enabled'] ?? false) && !empty($shop['hero_video']);
    $heroContentStyle = $shop['hero_content_style'] ?? 'editorial';
    $heroContentClass = $videoHero && $heroContentStyle === 'editorial' ? '' : 'hero-content-'.$heroContentStyle;
    $homeBanners = $contentEntries->where('type', 'banner');
    $campaign = $homeBanners->first();
    $heroSlides = collect([[
        'image' => !empty($shop['hero_image']) ? asset('storage/'.$shop['hero_image']) : asset('images/hero.svg'),
        'eyebrow' => $shop['hero_eyebrow'] ?? 'NEW COLLECTION',
        'title' => $shop['hero_title'] ?? '',
        'text' => $shop['hero_text'] ?? '',
        'url' => $shop['hero_button_url'] ?? route('catalog'),
        'label' => $shop['hero_button_label'] ?? 'Belanja sekarang',
        'align' => 'left', 'tone' => 'light', 'duration' => 6500, 'mobile_image' => null,
        'video' => $videoHero ? $heroVideoUrl($shop['hero_video']) : null,
        'mobile_video' => $videoHero ? $heroVideoUrl($shop['hero_video_mobile'] ?? null) : null,
    ]])->concat(($videoHero ? collect() : $homeBanners)->map(fn ($entry) => [
        'image' => $entry->image_url ?: (!empty($shop['hero_image']) ? asset('storage/'.$shop['hero_image']) : asset('images/hero.svg')),
        'eyebrow' => 'ASFARA EDIT',
        'title' => $entry->title,
        'text' => $entry->summary ?: $entry->body,
        'url' => $entry->link_url ?: route('catalog'),
        'label' => $entry->link_label ?: 'Lihat koleksi',
        'align' => collect($entry->blocks)->firstWhere('type','hero_options')['align'] ?? 'left',
        'tone' => collect($entry->blocks)->firstWhere('type','hero_options')['tone'] ?? 'light',
        'duration' => collect($entry->blocks)->firstWhere('type','hero_options')['duration'] ?? 6500,
        'mobile_image' => collect($entry->blocks)->firstWhere('type','hero_options')['mobile_url'] ?? null,
        'video' => collect($entry->blocks)->firstWhere('type','hero_options')['video_url'] ?? null,
        'mobile_video' => collect($entry->blocks)->firstWhere('type','hero_options')['mobile_video_url'] ?? null,
    ])->values());
@endphp
@push('head')
<link rel="preload" as="image" href="{{ $heroSlides->first()['image'] }}" fetchpriority="high"@if($heroSlides->first()['mobile_image']) imagesrcset="{{ $heroSlides->first()['mobile_image'] }} 767w, {{ $heroSlides->first()['image'] }} 1600w" imagesizes="100vw"@endif>
<style>.home-transparent-header #main{display:flex;flex-direction:column}</style>
@endpush
@php($heroAutoplayMs=(int)round((float)($shop['hero_autoplay_seconds'] ?? 5)*1000))
<section @class(['commerce-hero', 'hero-content-video' => $videoHero, $heroContentClass => $heroContentClass !== '']) style="order:{{ $homePosition('hero') }}" data-hero-slider @if($heroSlides->count()>1) aria-roledescription="carousel" @endif>
    @foreach($heroSlides as $slide)
        <article class="commerce-hero-slide hero-align-{{ $slide['align'] }} hero-tone-{{ $slide['tone'] }} {{ $loop->first?'is-active':'' }}" data-hero-slide data-duration="{{ $loop->first ? $heroAutoplayMs : (int) $slide['duration'] }}" aria-hidden="{{ $loop->first?'false':'true' }}">
            @if($slide['video'])
                <video class="commerce-hero-video" poster="{{ $slide['image'] }}" autoplay muted loop playsinline preload="metadata" data-hero-video>@if($slide['mobile_video'])<source media="(max-width:767px)" src="{{ $slide['mobile_video'] }}">@endif<source src="{{ $slide['video'] }}">Browser Anda belum mendukung video.</video>
                @if(!($loop->first && ($shop['hero_video'] ?? '') === 'demo/hero-desktop.webm'))<button type="button" class="hero-sound-toggle" data-hero-sound aria-label="Aktifkan suara video" aria-pressed="false"><span aria-hidden="true">⌁</span><em>SUARA</em></button>@endif
            @else
                <picture>@if($slide['mobile_image'])<source media="(max-width:767px)" srcset="{{ $slide['mobile_image'] }}">@endif<img src="{{ $slide['image'] }}" alt="{{ $slide['title'] }}" width="1600" height="800" loading="{{ $loop->first ? 'eager' : 'lazy' }}" fetchpriority="{{ $loop->first ? 'high' : 'low' }}" decoding="async"></picture>
            @endif
            <div class="commerce-hero-overlay"></div>
            <div class="container-xxl commerce-hero-copy">
                @unless($videoHero)<span>{{ $slide['eyebrow'] }}</span>@endunless
                <h1>{{ $videoHero ? (($shop['hero_eyebrow'] ?? '') ?: ($shop['brand_name'] ?? 'Asfara')) : $slide['title'] }}</h1>
                @unless($videoHero)<p>{{ $slide['text'] }}</p>@endunless
                <a class="btn btn-light" href="{{ $slide['url'] }}">{{ strtoupper($slide['label']) }}</a>
            </div>
        </article>
    @endforeach
    @if($heroSlides->count()>1)<div class="hero-dots" aria-label="Posisi banner">@foreach($heroSlides as $slide)<span class="{{ $loop->first?'is-active':'' }}" data-hero-dot="{{ $loop->index }}" aria-hidden="true"></span>@endforeach</div>@endif
    <a class="hero-next-section" href="{{ ($shop['home_collections_enabled'] ?? true) ? '#new-in' : route('catalog') }}" aria-label="Ke koleksi terbaru">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </a>
</section>
@if($videoHero && $campaign)
    <section class="home-campaign-preview" style="order:{{ $homePosition('campaign') }}" aria-label="{{ $campaign->title }}">
        <a href="{{ $campaign->link_url ?: route('catalog') }}">
            <picture>
                @if($mobileBanner = collect($campaign->blocks)->firstWhere('type', 'hero_options')['mobile_url'] ?? null)<source media="(max-width: 767px)" srcset="{{ $mobileBanner }}">@endif
                <img src="{{ $campaign->image_url ?: $heroSlides->first()['image'] }}" alt="" width="1600" height="800" loading="lazy" decoding="async">
            </picture>
            <span>{{ $campaign->link_label ?: 'Lihat koleksi' }}</span>
        </a>
    </section>
@endif
@if($shop['home_categories_enabled'] ?? true)<section class="category-media-grid container-xxl reveal" style="order:{{ $homePosition('categories') }}">
    @foreach($categoryCollections as $categoryProduct)<a href="{{ route('catalog',['category'=>$categoryProduct->category->slug]) }}"><img src="{{ $categoryProduct->image_url }}" alt="{{ $categoryProduct->category->name }}" width="800" height="1000" loading="lazy" decoding="async"><span>{{ strtoupper($categoryProduct->category->name) }}</span></a>@endforeach
</section>@endif

@if($shop['home_collections_enabled'] ?? true)
<section class="commerce-section home-new-in container-xxl reveal" id="new-in" style="order:{{ $homePosition('new_in') }}">
    <div class="commerce-heading"><h2>NEW IN</h2><a href="{{ route('catalog',['sort'=>'newest']) }}">{{ $storeCopy['view_all'] }}</a></div>
    <div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@forelse($newCollections as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@empty<div class="empty-state"><h3>Koleksi sedang disiapkan.</h3></div>@endforelse</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div>
</section>

@foreach(config('storefront.campaign_groups') as $group => $groupTitle)
    @php($edits = $campaignEdits->get($group, collect()))
    @continue($edits->isEmpty())
    @php($sectionKey = $group === 'main' ? 'collections' : $group)
    @php($banner = $group === 'main' ? null : $edits->firstWhere('image', '!=', null))
    @if($banner)
        <section class="home-campaign-preview home-group-banner" style="order:{{ $homePosition($group.'_banner') }}" aria-label="{{ $banner['label'] }}">
            <a href="{{ $banner['url'] }}">
                <picture>
                    @if($banner['mobile_image'])<source media="(max-width: 767px)" srcset="{{ $banner['mobile_image'] }}">@endif
                    <img src="{{ $banner['image'] }}" alt="" width="1600" height="800" loading="lazy" decoding="async">
                </picture>
                <span>{{ $banner['label'] }}</span>
            </a>
        </section>
    @endif
    <section class="commerce-section latest-collection container-xxl reveal" style="order:{{ $homePosition($sectionKey) }}" data-campaign-tabs="{{ $group }}" aria-labelledby="campaign-title-{{ $group }}">
        <div class="commerce-heading">
            <h2 id="campaign-title-{{ $group }}">{{ $group === 'main' ? $storeCopy['featured_collection'] : $groupTitle }}</h2>
        </div>
        <div class="collection-tabs" role="group" aria-label="{{ $groupTitle }}">
            @foreach($edits as $key => $edit)
                <button @class(['is-active' => $loop->first]) type="button" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-controls="pane-{{ $key }}" data-collection-tab="{{ $key }}">{{ $edit['label'] }}</button>
            @endforeach
        </div>
        @foreach($edits as $key => $edit)
            <div @class(['collection-pane', 'is-active' => $loop->first]) id="pane-{{ $key }}" data-collection-pane="{{ $key }}">
                <div class="reference-carousel">
                    <button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button>
                    <div class="product-carousel" data-drag-scroll>
                        @foreach($edit['items'] as $collection)
                            <div class="product-carousel-item">@include('partials.product-card', ['cardContext' => $key.'-'])</div>
                        @endforeach
                    </div>
                    <button type="button" data-carousel-next aria-label="Produk berikutnya">›</button>
                </div>
                <div class="text-center mt-4"><a class="btn btn-outline-dark" href="{{ $edit['url'] }}">{{ $storeCopy['view_all'] }}</a></div>
            </div>
        @endforeach
    </section>
@endforeach
@endif

@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-campaign-tabs]').forEach(group => {
    const tabs = group.querySelectorAll('[data-collection-tab]');
    const panes = group.querySelectorAll('[data-collection-pane]');
    tabs.forEach(button => button.addEventListener('click', () => {
        tabs.forEach(tab => {
            const selected = tab === button;
            tab.classList.toggle('is-active', selected);
            tab.setAttribute('aria-pressed', String(selected));
        });
        panes.forEach(pane => {
            pane.classList.toggle('is-active', pane.dataset.collectionPane === button.dataset.collectionTab);
        });
    }));
});
</script>
@endpush
