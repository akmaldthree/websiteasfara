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
    $homeOrder = collect($shop['home_section_order'] ?? $defaultOrder->all())->intersect($defaultOrder)->values();
    if ($homeOrder->all() === ['hero', 'campaign', 'new_in', 'categories', 'collections', 'signature_banner', 'signature', 'studio_banner', 'studio']) {
        $homeOrder = $defaultOrder;
    }
    if (!$homeOrder->contains('campaign') || !$homeOrder->contains('new_in')) {
        $openingSections = $defaultOrder->take(5);
        $homeOrder = $openingSections->concat($homeOrder->diff($openingSections));
    }
    $homeOrder = $homeOrder->intersect($defaultOrder)->concat($defaultOrder->diff($homeOrder))->values();
    $heroVideoUrl = fn ($path) => match ($path) {
        'demo/hero-desktop.webm' => route('media.demo-hero', ['format' => 'desktop', 'v' => filemtime(database_path('seeders/assets/demo/hero-desktop.webm'))]),
        'demo/hero-mobile.webm' => route('media.demo-hero', ['format' => 'mobile', 'v' => filemtime(database_path('seeders/assets/demo/hero-mobile.webm'))]),
        null, '' => null,
        default => asset('storage/'.$path),
    };
    $videoEnabled = $shop['hero_video_enabled'] ?? false;
    $heroContentStyle = $shop['hero_content_style'] ?? 'editorial';
    $homeBanners = $contentEntries->where('type', 'banner');
    $primaryEdit = $campaignEdits->get('main', collect())->firstWhere('image', '!=', null);
    $campaign = $homeBanners->first();
    $heroCampaign = ($shop['hero_campaign_enabled'] ?? true) && $heroContentStyle === 'editorial'
        ? $campaignEdits->get('main', collect())->first()
        : null;
    $spotlightProducts = $heroCampaign ? $heroCampaign['items']->whereNotIn('id', $newCollections->pluck('id'))->take(4) : collect();
    $firstSlide = [
        'image' => !empty($shop['hero_image']) ? asset('storage/'.$shop['hero_image']) : asset('images/hero.svg'),
        'eyebrow' => $shop['hero_eyebrow'] ?? 'NEW COLLECTION',
        'title' => $shop['hero_title'] ?? '',
        'text' => $shop['hero_text'] ?? '',
        'url' => $shop['hero_button_url'] ?? route('catalog'),
        'label' => $shop['hero_button_label'] ?? 'Belanja sekarang',
        'align' => 'left', 'tone' => 'light', 'duration' => 6500, 'mobile_image' => null,
        'video' => $videoEnabled ? $heroVideoUrl($shop['hero_video'] ?? null) : null,
        'mobile_video' => $videoEnabled ? $heroVideoUrl($shop['hero_video_mobile'] ?? null) : null,
        'has_audio' => ($shop['hero_video_has_audio'] ?? false) && ($shop['hero_video'] ?? null) !== 'demo/hero-desktop.webm',
    ];
    if ($heroCampaign) {
        $options = $heroCampaign['hero_options'];
        $campaignVideo = $videoEnabled ? (($options['video_url'] ?? null) ?: ($options['mobile_video_url'] ?? null)) : null;
        $demoVideo = ($shop['hero_video'] ?? null) === 'demo/hero-desktop.webm';
        if ($campaignVideo) {
            $firstSlide['video'] = $campaignVideo;
            $firstSlide['mobile_video'] = $options['mobile_video_url'] ?? null;
            $firstSlide['has_audio'] = $options['has_audio'] ?? false;
        } elseif ($demoVideo && $heroCampaign['slug'] !== 'demo-seri-ruang-raya') {
            $firstSlide['video'] = null;
            $firstSlide['mobile_video'] = null;
        }
        $firstSlide['image'] = $heroCampaign['image'] ?: $firstSlide['image'];
        $firstSlide['mobile_image'] = $heroCampaign['mobile_image']
            ?: ($demoVideo && $heroCampaign['slug'] === 'demo-seri-ruang-raya' ? asset('storage/demo/product-family-sage-4x5.webp') : null);
        $firstSlide['title'] = $heroCampaign['label'];
        $firstSlide['url'] = $heroCampaign['url'];
        $firstSlide['label'] = 'Lihat koleksi';
    }
    $videoHero = (bool) $firstSlide['video'];
    $heroContentClass = $videoHero && $heroContentStyle === 'editorial' ? '' : 'hero-content-'.$heroContentStyle;
    $heroSlides = collect([$firstSlide])->concat(($videoHero || $heroCampaign ? collect() : $homeBanners)->map(fn ($entry) => [
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
        'has_audio' => collect($entry->blocks)->firstWhere('type','hero_options')['has_audio'] ?? false,
    ])->values());
@endphp
@push('head')
<link rel="preload" as="image" href="{{ $heroSlides->first()['image'] }}" fetchpriority="high"@if($heroSlides->first()['mobile_image']) imagesrcset="{{ $heroSlides->first()['mobile_image'] }} 767w, {{ $heroSlides->first()['image'] }} 1600w" imagesizes="100vw"@endif>
@endpush
@php($heroAutoplayMs=(int)round((float)($shop['hero_autoplay_seconds'] ?? 5)*1000))
@section('home-hero')
<section @class(['commerce-hero', 'hero-content-video' => $videoHero, 'hero-content-campaign' => $heroCampaign, $heroContentClass => $heroContentClass !== '']) data-hero-slider @if($heroSlides->count()>1) aria-roledescription="carousel" @endif>
    @foreach($heroSlides as $slide)
        <article class="commerce-hero-slide hero-align-{{ $slide['align'] }} hero-tone-{{ $slide['tone'] }} {{ $loop->first?'is-active':'' }}" data-hero-slide data-duration="{{ $loop->first ? $heroAutoplayMs : (int) $slide['duration'] }}" aria-hidden="{{ $loop->first?'false':'true' }}">
            @if($slide['video'])
                <video class="commerce-hero-video" poster="{{ $slide['image'] }}" @if($slide['mobile_image']) data-mobile-poster="{{ $slide['mobile_image'] }}" @endif muted loop playsinline preload="none" data-hero-video>@if($slide['mobile_video'])<source media="(max-width:767px)" src="{{ $slide['mobile_video'] }}">@endif<source src="{{ $slide['video'] }}">Browser Anda belum mendukung video.</video>
                <div class="hero-media-controls">
                    <button type="button" data-hero-play aria-label="Putar video">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path class="hero-icon-play" d="m9 5 11 7-11 7z"/><path class="hero-icon-pause" d="M9 5v14m6-14v14"/></svg>
                    </button>
                    @if($slide['has_audio'])
                        <button type="button" data-hero-sound aria-label="Suara video" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9h4l5-4v14l-5-4H3z"/><path class="hero-icon-muted" d="m16 9 5 6m0-6-5 6"/><path class="hero-icon-sound" d="M16 9a4 4 0 0 1 0 6m3-9a8 8 0 0 1 0 12"/></svg>
                        </button>
                    @endif
                </div>
            @else
                <picture>@if($slide['mobile_image'])<source media="(max-width:767px)" srcset="{{ $slide['mobile_image'] }}">@endif<img src="{{ $slide['image'] }}" alt="{{ $slide['title'] }}" width="1600" height="800" loading="{{ $loop->first ? 'eager' : 'lazy' }}" fetchpriority="{{ $loop->first ? 'high' : 'low' }}" decoding="async"></picture>
            @endif
            <div class="commerce-hero-overlay"></div>
            <div class="container-xxl commerce-hero-copy">
                @unless($videoHero || $heroCampaign)<span>{{ $slide['eyebrow'] }}</span>@endunless
                <h1>{{ $videoHero && !$heroCampaign ? (($shop['hero_eyebrow'] ?? '') ?: ($shop['brand_name'] ?? 'Asfara')) : $slide['title'] }}</h1>
                @unless($videoHero || $heroCampaign)<p>{{ $slide['text'] }}</p>@endunless
                <a class="btn btn-light" href="{{ $slide['url'] }}">{{ strtoupper($slide['label']) }}</a>
            </div>
        </article>
    @endforeach
    @if($heroSlides->count()>1)<div class="hero-dots" aria-label="Posisi banner">@foreach($heroSlides as $slide)<span class="{{ $loop->first?'is-active':'' }}" data-hero-dot="{{ $loop->index }}" aria-hidden="true"></span>@endforeach</div>@endif
    <a class="hero-next-section" href="{{ ($shop['home_collections_enabled'] ?? true) ? '#new-in' : route('catalog') }}" aria-label="Ke koleksi terbaru">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </a>
</section>
@endsection
@if($heroCampaign && ($shop['home_collections_enabled'] ?? true))
    @section('home-campaign')
    <section class="container-xxl home-campaign-spotlight" aria-labelledby="home-campaign-title">
        <div class="home-campaign-spotlight-heading">
            <div>
                <h2 id="home-campaign-title">{{ $heroCampaign['label'] }}</h2>
                @if($heroCampaign['summary'])<p>{{ $heroCampaign['summary'] }}</p>@endif
            </div>
            <a class="btn btn-outline-dark" href="{{ $heroCampaign['url'] }}">Belanja kampanye</a>
        </div>
        @if($spotlightProducts->isNotEmpty())
            <div class="campaign-product-grid">
                @foreach($spotlightProducts as $collection)
                    @include('partials.product-card', ['cardContext' => 'home-spotlight-'])
                @endforeach
            </div>
        @endif
    </section>
    @endsection
@elseif(!$heroCampaign && $videoHero && ($primaryEdit || $campaign))
    @section('home-campaign')
    @php($campaignTitle = $primaryEdit['label'] ?? $campaign->title)
    <section class="home-campaign-preview home-primary-campaign" aria-labelledby="home-campaign-title">
        <a href="{{ $primaryEdit['url'] ?? ($campaign->link_url ?: route('catalog')) }}">
            <picture>
                @if($mobileBanner = $primaryEdit ? $primaryEdit['mobile_image'] : $campaign->mobileCoverUrl())<source media="(max-width: 767px)" srcset="{{ $mobileBanner }}">@endif
                <img src="{{ $primaryEdit['image'] ?? ($campaign->image_url ?: $heroSlides->first()['image']) }}" alt="" width="1600" height="800" loading="lazy" decoding="async">
            </picture>
            <div class="home-primary-campaign-copy">
                <h2 id="home-campaign-title">{{ $campaignTitle }}</h2>
                <span>{{ $primaryEdit ? 'Jelajahi kampanye' : ($campaign->link_label ?: 'Lihat koleksi') }}</span>
            </div>
        </a>
    </section>
    @endsection
@endif
@if($shop['home_categories_enabled'] ?? true)
@section('home-categories')
<section class="category-media-grid container-xxl reveal">
    @foreach($categoryCollections as $categoryProduct)<a href="{{ route('catalog',['category'=>$categoryProduct->category->slug]) }}"><img src="{{ $categoryProduct->image_url }}" alt="{{ $categoryProduct->category->name }}" width="800" height="1000" loading="lazy" decoding="async"><span>{{ strtoupper($categoryProduct->category->name) }}</span></a>@endforeach
</section>
@endsection
@endif

@if($shop['home_collections_enabled'] ?? true)
@section('home-new_in')
<section class="commerce-section home-new-in container-xxl reveal" id="new-in">
    <div class="commerce-heading"><h2>NEW IN</h2><a href="{{ route('catalog',['sort'=>'newest']) }}">{{ $storeCopy['view_all'] }}</a></div>
    <div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@forelse($newCollections as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@empty<div class="empty-state"><h3>Koleksi sedang disiapkan.</h3></div>@endforelse</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div>
</section>
@endsection

@foreach(config('storefront.campaign_groups') as $group => $groupTitle)
    @php($edits = $campaignEdits->get($group, collect()))
    @if($heroCampaign)
        @php($edits = $edits->where('slug', '!=', $heroCampaign['slug']))
        @continue($edits->isEmpty())
        @section('home-'.($group === 'main' ? 'collections' : $group))
        <section class="container-xxl home-campaign-directory" aria-labelledby="home-directory-{{ $group }}">
            <div class="home-campaign-spotlight-heading">
                <h2 id="home-directory-{{ $group }}">{{ $group === 'main' ? 'Kampanye lainnya' : $groupTitle }}</h2>
                @if($group === 'main')<a href="{{ route('content.index','campaign') }}">{{ $storeCopy['view_all'] }}</a>@endif
            </div>
            <div class="home-campaign-directory-grid">
                @foreach($edits->take(4) as $edit)
                    <a class="home-campaign-directory-link" href="{{ $edit['url'] }}">
                        @if($edit['image'])
                            <picture>
                                @if($edit['mobile_image'])<source media="(max-width: 767px)" srcset="{{ $edit['mobile_image'] }}">@endif
                                <img src="{{ $edit['image'] }}" alt="" width="1200" height="600" loading="lazy" decoding="async">
                            </picture>
                        @endif
                        <h3>{{ $edit['label'] }}</h3>
                    </a>
                @endforeach
            </div>
        </section>
        @endsection
        @continue
    @endif
    @continue($edits->isEmpty())
    @php($sectionKey = $group === 'main' ? 'collections' : $group)
    @php($banner = $group === 'main' ? null : $edits->firstWhere('image', '!=', null))
    @if($banner)
        @section('home-'.$group.'_banner')
        <section class="home-campaign-preview home-group-banner" aria-label="{{ $banner['label'] }}">
            <a href="{{ $banner['url'] }}">
                <picture>
                    @if($banner['mobile_image'])<source media="(max-width: 767px)" srcset="{{ $banner['mobile_image'] }}">@endif
                    <img src="{{ $banner['image'] }}" alt="" width="1600" height="800" loading="lazy" decoding="async">
                </picture>
                <span>{{ $banner['label'] }}</span>
            </a>
        </section>
        @endsection
    @endif
    @section('home-'.$sectionKey)
    <section class="commerce-section latest-collection container-xxl reveal" data-campaign-tabs="{{ $group }}" aria-labelledby="campaign-title-{{ $group }}">
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
    @endsection
@endforeach
@endif

@foreach($homeOrder as $section)
    @yield('home-'.$section)
@endforeach
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
