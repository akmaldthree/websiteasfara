@extends('layouts.store')
@section('content')
@php
    $shop = array_replace($shop, $homeOverrides ?? []);
    $curated = function (string $key, $fallback) use ($shop, $collections) {
        $slugs = collect(preg_split('/[\s,]+/', $shop[$key] ?? ''))->filter()->unique();
        if ($slugs->isEmpty()) return collect($fallback)->take(8);
        return $slugs->map(fn ($slug) => $collections->firstWhere('slug', $slug))->filter()->values()->take(\App\Support\HomeLayout::NEW_IN_LIMIT);
    };
    $newCollections = $curated('home_new_slugs', $collections);
    $categorySlugs = collect(preg_split('/[,\r\n]+/', $shop['home_category_slugs'] ?? ''))->map(fn($slug)=>trim($slug))->filter();
    $categoryProducts = $categorySlugs->map(fn ($slug) => $collections->firstWhere('slug', $slug))->filter();
    $categoryTiles = ($categoryProducts->isEmpty() ? $collections : $categoryProducts)->unique('category_id')->take(3)
        ->filter(fn ($product) => $product->category)
        ->map(fn ($product) => [
            'label' => $product->category->name,
            'url' => route('catalog', ['category' => $product->category->slug]),
            'image' => $product->image_url,
            'mobile_image' => null,
        ])->values();
    if ($categoryTiles->count() === 1 && $collections->contains(fn ($product) => $product->category?->slug === 'abaya-dewasa')) {
        $editsBySlug = $campaignEdits->flatten(1)->keyBy('slug');
        $abayaTiles = collect([
            'demo-studio-lapis-kota' => 'Abaya Harian',
            'demo-seri-siluet-senja' => 'Abaya Formal',
            'demo-seri-langkah-ringan' => 'Abaya Travel',
        ])->filter(fn ($label, $slug) => !empty($editsBySlug->get($slug)['image']))
            ->map(function ($label, $slug) use ($editsBySlug, $shop) {
                $edit = $editsBySlug->get($slug);
                $tile = match ($slug) {
                    'demo-studio-lapis-kota' => 'harian',
                    'demo-seri-siluet-senja' => 'formal',
                    default => 'travel',
                };
                $demoCover = ($shop['home_abaya_editorial_enabled'] ?? false) && str_contains($edit['image'], '/storage/demo/abaya-');
                $portrait = $demoCover ? asset('storage/demo/abaya-editorial-tile-'.$tile.'.webp') : ($edit['mobile_image'] ?: $edit['image']);

                return array_replace($edit, ['label' => $label, 'image' => $portrait, 'mobile_image' => null]);
            })->values();
        if ($abayaTiles->isNotEmpty()) {
            $categoryTiles = $abayaTiles;
        }
    }
    if ($homeVisualCards->isNotEmpty()) {
        $categoryTiles = $homeVisualCards;
    }
    $homeOrder = collect(\App\Support\HomeLayout::order($shop['home_section_order'] ?? null))->diff($shop['home_section_hidden'] ?? [])->values();
    $heroVideoUrl = fn ($path) => match ($path) {
        'demo/hero-desktop.webm' => route('media.demo-hero', ['format' => 'desktop', 'v' => filemtime(database_path('seeders/assets/demo/hero-desktop.webm'))]),
        'demo/hero-mobile.webm' => route('media.demo-hero', ['format' => 'mobile', 'v' => filemtime(database_path('seeders/assets/demo/hero-mobile.webm'))]),
        null, '' => null,
        default => asset('storage/'.$path),
    };
    $videoEnabled = $shop['hero_video_enabled'] ?? false;
    $heroContentStyle = $shop['hero_content_style'] ?? 'editorial';
    $homeBanners = $contentEntries->where('type', 'banner');
    $heroCampaign = ($shop['hero_campaign_enabled'] ?? true) && $heroContentStyle === 'editorial'
        ? ($campaignEdits->flatMap(fn ($edits) => $edits->values())->firstWhere('id', $heroCampaignId)
            ?? $campaignEdits->get('main', collect())->first())
        : null;
    $firstSlide = [
        'image' => !empty($shop['hero_image']) ? asset('storage/'.$shop['hero_image']) : asset('images/hero.svg'),
        'eyebrow' => $shop['hero_eyebrow'] ?? 'NEW COLLECTION',
        'title' => $shop['hero_title'] ?? '',
        'text' => $shop['hero_text'] ?? '',
        'url' => $shop['hero_button_url'] ?? route('catalog'),
        'label' => $shop['hero_button_label'] ?? 'Belanja sekarang',
        'align' => 'left', 'tone' => 'light', 'duration' => 6500,
        'mobile_image' => !empty($shop['hero_image_mobile']) ? asset('storage/'.$shop['hero_image_mobile']) : null,
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
            ?: $firstSlide['mobile_image'];
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
@section('body_class', $homeOrder->first() === 'hero' ? 'home-transparent-header' : '')
@push('head')
<link rel="preload" as="image" href="{{ $heroSlides->first()['image'] }}" fetchpriority="high"@if($heroSlides->first()['mobile_image']) imagesrcset="{{ $heroSlides->first()['mobile_image'] }} 767w, {{ $heroSlides->first()['image'] }} 1600w" imagesizes="100vw"@endif>
@endpush
@php($heroAutoplayMs=(int)round((float)($shop['hero_autoplay_seconds'] ?? 5)*1000))
@section('home-hero')
<section @class(['commerce-hero', 'hero-content-video' => $videoHero, 'hero-content-campaign' => $heroCampaign, 'hero-height-fixed' => ($shop['hero_height_mode'] ?? 'auto') === 'fixed', $heroContentClass => $heroContentClass !== '']) data-hero-slider @if($heroSlides->count()>1) aria-roledescription="carousel" @endif>
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
    <a class="hero-next-section" href="#home-after-hero" aria-label="Ke bagian berikutnya">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </a>
</section>
@endsection
@if($campaign && ($heroCampaign || $videoHero))
    @section('home-campaign')
    <section class="home-campaign-preview home-primary-campaign" aria-labelledby="home-campaign-title">
        <a href="{{ $campaign->link_url ?: route('catalog') }}">
            <picture>
                @if($mobileBanner = $campaign->mobileCoverUrl())<source media="(max-width: 767px)" srcset="{{ $mobileBanner }}">@endif
                <img src="{{ $campaign->image_url }}" alt="" width="1600" height="800" loading="lazy" decoding="async">
            </picture>
            <div class="home-primary-campaign-copy">
                <h2 id="home-campaign-title">{{ $campaign->title }}</h2>
                <span>{{ $campaign->link_label ?: 'Lihat koleksi' }}</span>
            </div>
        </a>
    </section>
    @endsection
@endif
@if($bannerProducts->isNotEmpty() && (!empty($closing['products']) || ($campaign && ($heroCampaign || $videoHero))))
    @section('home-campaign_products')
    <section class="home-product-section container-xxl" id="home-campaign-products" aria-labelledby="home-campaign-products-title">
        <h2 id="home-campaign-products-title" class="home-product-title">{{ ($closing['title_'.app()->getLocale()] ?? null) ?: __('store.campaign_products') }}</h2>
        @include('partials.home-product-carousel', [
            'items' => $bannerProducts,
            'cardContext' => 'home-campaign-',
            'viewAllUrl' => ($closing['url'] ?? null) ?: (empty($closing['products']) ? $campaign?->link_url : null) ?: route('catalog'),
        ])
    </section>
    @endsection
@endif
@if(($shop['home_categories_enabled'] ?? true) && $categoryTiles->isNotEmpty())
    @section('home-categories')
    <section class="home-category-section" aria-label="Jelajahi koleksi">
        <div class="category-media-grid" style="--category-cards: {{ $categoryTiles->count() }}; --category-columns: {{ min(3, $categoryTiles->count()) }}">
            @foreach($categoryTiles as $tile)
                <a href="{{ $tile['url'] }}">
                    <picture>
                        @if($tile['mobile_image'])<source media="(max-width: 767px)" srcset="{{ $tile['mobile_image'] }}">@endif
                        <img src="{{ $tile['image'] }}" style="object-position:{{ $tile['focal_x'] ?? 50 }}% {{ $tile['focal_y'] ?? 50 }}%" alt="" width="760" height="950" loading="lazy" decoding="async">
                    </picture>
                    <span class="home-category-label">{{ $tile['label'] }}</span>
                </a>
            @endforeach
        </div>
    </section>
    @endsection
@endif

@if($shop['home_collections_enabled'] ?? true)
@section('home-new_in')
<section class="home-product-section container-xxl" id="new-in" aria-labelledby="home-new-in-title">
    <h2 class="home-product-title" id="home-new-in-title">New In</h2>
    @include('partials.home-product-carousel', [
        'items' => $newCollections,
        'cardContext' => 'home-new-',
        'viewAllUrl' => route('catalog', ['sort' => 'new']),
    ])
</section>
@endsection

@foreach(config('storefront.campaign_groups') as $group => $groupTitle)
    @php($edits = $campaignEdits->get($group, collect()))
    @continue($edits->isEmpty())
    @php($sectionKey = $group === 'main' ? 'collections' : $group)
    @php($groupLabel = $group === 'main' ? $storeCopy['featured_collection'] : $groupTitle)
    @php($hasTabs = $edits->count() > 1)
    @php($banner = $group === 'main' ? null : $edits->firstWhere('image', '!=', null))
    @if($banner)
        @section('home-'.$group.'_banner')
        <section class="home-campaign-preview home-group-banner" data-campaign-banner="{{ $group }}" aria-label="{{ $banner['label'] }}">
            <a href="{{ $banner['url'] }}">
                <picture>
                    @if($banner['mobile_image'])<source media="(max-width: 767px)" srcset="{{ $banner['mobile_image'] }}">@endif
                    <img src="{{ $banner['image'] }}" alt="" width="1600" height="800" loading="lazy" decoding="async">
                </picture>
                <div class="home-campaign-caption">
                    <h2>{{ $banner['label'] }}</h2>
                    <span>{{ __('store.campaign_cta') }} →</span>
                </div>
            </a>
        </section>
        @endsection
    @endif
    @section('home-'.$sectionKey)
    <section class="home-product-section container-xxl" data-campaign-tabs="{{ $group }}" aria-labelledby="campaign-title-{{ $group }}">
        @if($hasTabs)
            <h2 class="home-product-label" id="campaign-title-{{ $group }}">{{ $groupLabel }}</h2>
            <div class="collection-tabs home-collection-tabs" role="tablist" aria-labelledby="campaign-title-{{ $group }}">
                @foreach($edits as $key => $edit)
                    <button @class(['is-active' => $loop->first]) type="button" role="tab" id="tab-{{ $group }}-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}" aria-controls="pane-{{ $group }}-{{ $key }}" data-collection-tab="{{ $key }}">{{ $edit['label'] }}</button>
                @endforeach
            </div>
        @else
            <p class="home-product-label">{{ $groupLabel }}</p>
            <h2 class="home-product-title" id="campaign-title-{{ $group }}">{{ $edits->first()['label'] }}</h2>
        @endif
        @foreach($edits as $key => $edit)
            <div @class(['collection-pane', 'is-active' => $loop->first]) id="pane-{{ $group }}-{{ $key }}" @if($hasTabs) role="tabpanel" aria-labelledby="tab-{{ $group }}-{{ $key }}" @endif @if(!$loop->first) hidden @endif>
                @include('partials.home-product-carousel', [
                    'items' => $edit['items'],
                    'cardContext' => $group.'-'.$key.'-',
                    'viewAllUrl' => $edit['url'],
                ])
            </div>
        @endforeach
    </section>
    @endsection
@endforeach
@endif

@if($editorialSections->isNotEmpty())
    @section('home-editorial')
        @foreach($editorialSections as $entry)
            <section class="container-xxl section" aria-labelledby="home-editorial-{{ $entry->id }}">
                <h2 id="home-editorial-{{ $entry->id }}">{{ $entry->title }}</h2>
                @if($entry->summary)<p>{{ $entry->summary }}</p>@endif
                @if($entry->image_url)<a href="{{ route('content.show', ['lookbook', $entry->slug]) }}"><img class="img-fluid d-block mb-3" src="{{ $entry->image_url }}" alt="{{ $entry->title }}" loading="lazy"></a>@endif
                @include('partials.content-blocks', ['contentBlocks' => $entry->blocks ?? []])
                <a class="btn btn-outline-dark" href="{{ route('content.show', ['lookbook', $entry->slug]) }}">Lihat lookbook</a>
            </section>
        @endforeach
    @endsection
@endif

@if($homePreview)<div class="container-xxl demo-note">Pratinjau admin · Draft beranda</div>@endif
@foreach($homeOrder as $section)
    @yield('home-'.$section)
    @if($section === 'hero')<div id="home-after-hero" tabindex="-1"></div>@endif
@endforeach
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-campaign-tabs]').forEach(group => {
    const tabs = [...group.querySelectorAll('[data-collection-tab]')];
    const selectTab = selected => {
        tabs.forEach(tab => {
            const active = tab === selected;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            const panel = document.getElementById(tab.getAttribute('aria-controls'));
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab));
        tab.addEventListener('keydown', event => {
            const destinations = {ArrowLeft: (index - 1 + tabs.length) % tabs.length, ArrowRight: (index + 1) % tabs.length, Home: 0, End: tabs.length - 1};
            if (!Object.hasOwn(destinations, event.key)) return;
            event.preventDefault();
            const target = tabs[destinations[event.key]];
            selectTab(target);
            target.focus();
        });
    });
});
</script>
@endpush
