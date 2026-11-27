@php
    $navigation = \App\Support\StorefrontNavigation::storefront($shop);
    $desktopItems = \App\Support\StorefrontNavigation::headerItems($navigation['desktop'], $cmsMenus->whereIn('placement', ['both', 'desktop']), $campaignMenu->isNotEmpty());
    $mobileItems = \App\Support\StorefrontNavigation::headerItems($navigation['mobile'], $cmsMenus->whereIn('placement', ['both', 'mobile']), $campaignMenu->isNotEmpty());
    $panelIds = ['shop' => 'catalog-mega-menu', 'collections' => 'campaign-desktop-menu', 'editorial' => 'editorial-desktop-menu', 'custom' => 'custom-desktop-menu'];
    $mobileOpenPanel = match (true) {
        request()->routeIs('catalog', 'product') => 'shop',
        request()->routeIs('content.*') && request()->route('type') === 'campaign' => 'collections',
        request()->routeIs('content.*') && in_array(request()->route('type'), ['lookbook', 'journal'], true) => 'editorial',
        default => null,
    };
@endphp
@php($themeFonts=['poppins'=>["'Poppins',Arial,sans-serif","'Poppins',Arial,sans-serif"],'classic'=>['Arial,Helvetica,sans-serif',"Georgia,'Times New Roman',serif"],'modern'=>['Helvetica,Arial,sans-serif','Helvetica,Arial,sans-serif'],'system'=>['system-ui,-apple-system,sans-serif','system-ui,-apple-system,sans-serif']])
@php($themeFont=$themeFonts[$shop['theme_font'] ?? 'poppins'] ?? $themeFonts['poppins'])
@php($seoTitle=trim($__env->yieldContent('title',$shop['default_seo_title'] ?? 'Asfara — Dalam setiap cerita')))
@php($seoDescription=trim($__env->yieldContent('description',$shop['default_seo_description'] ?? 'Asfara — koleksi busana muslim dan muslimah.')))
@php($seoCanonical=trim($__env->yieldContent('canonical',url()->current())))
@php($seoImage=trim($__env->yieldContent('image',!empty($shop['hero_image']) ? asset('storage/'.$shop['hero_image']) : asset('images/hero.svg'))))
@php($seoRobots=(request()->routeIs('admin.*') || request()->routeIs('cart','checkout','login','register','password.*','account','orders.*','track','two-factor.*'))?'noindex,nofollow':'index,follow')
@php($websiteSchema=['@context'=>'https://schema.org','@graph'=>[['@type'=>'Organization','@id'=>url('/').'#organization','name'=>$shop['brand_name'] ?? 'Asfara','url'=>url('/'),'logo'=>!empty($shop['logo'])?asset('storage/'.$shop['logo']):null,'email'=>$shop['contact_email'] ?? null,'sameAs'=>collect([$shop['instagram_url'] ?? null,$shop['tiktok_url'] ?? null,$shop['facebook_url'] ?? null])->filter()->values()->all()],['@type'=>'WebSite','@id'=>url('/').'#website','url'=>url('/'),'name'=>$shop['brand_name'] ?? 'Asfara','publisher'=>['@id'=>url('/').'#organization'],'inLanguage'=>app()->getLocale(),'potentialAction'=>['@type'=>'SearchAction','target'=>route('catalog').'?q={search_term_string}','query-input'=>'required name=search_term_string']]]])
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <meta name="theme-color" content="{{ $shop['theme_palette_surface'] ?? '#ffffff' }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $shop['brand_name'] ?? 'Asfara' }}">
    <meta property="og:locale" content="{{ app()->getLocale()==='id'?'id_ID':'en_US' }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    <title>{{ $seoTitle }}</title>
    <link rel="canonical" href="{{ $seoCanonical }}">
    @if(!empty($shop['favicon']))<link rel="icon" href="{{ asset('storage/'.$shop['favicon']) }}">@endif
    @if(($shop['theme_font'] ?? 'poppins') === 'poppins')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    @endif
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ filemtime(public_path('vendor/bootstrap/bootstrap.min.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/asfara.css') }}?v={{ filemtime(public_path('css/asfara.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}?v={{ filemtime(public_path('css/storefront.css')) }}">
    <style>:root{--palette-sage:{{ $shop['theme_palette_base'] ?? config('theme.palette.theme_palette_base.color') }};--palette-sand:{{ $shop['theme_palette_surface'] ?? config('theme.palette.theme_palette_surface.color') }};--palette-taupe:{{ $shop['theme_palette_structure'] ?? config('theme.palette.theme_palette_structure.color') }};--palette-gold:{{ $shop['theme_palette_accent'] ?? config('theme.palette.theme_palette_accent.color') }};--palette-cream:{{ $shop['theme_palette_base'] ?? config('theme.palette.theme_palette_base.color') }};--ink:{{ $shop['theme_text_color'] ?? config('theme.text_color') }};--gold:var(--palette-gold);--cream:var(--palette-cream);--paper:var(--palette-sage);--surface:var(--palette-sand);--text:{{ $shop['theme_text_color'] ?? config('theme.text_color') }};--line:var(--palette-taupe);--theme-radius:{{ (int)($shop['theme_radius'] ?? 8) }}px;--theme-container:{{ (int)($shop['theme_container_width'] ?? 1440) }}px;--hero-height:{{ (int)($shop['hero_height_desktop'] ?? 633) }}px;--store-motion:{{ (int)($shop['storefront_animation_speed'] ?? 550) }}ms;--store-grid-columns:{{ (int)($shop['product_grid_columns'] ?? 4) }};--body-font:{!! $themeFont[0] !!};--heading-font:{!! $themeFont[1] !!}}body{color:var(--text);font-family:var(--body-font)}h1,h2,h3,.wordmark{font-family:var(--heading-font)}.container-xxl{max-width:var(--theme-container)}.panel,.btn,.form-control,.form-select,.alert,.product-image{border-radius:var(--theme-radius)}.panel,.form-control,.form-select{background-color:var(--surface)}.wordmark img{display:block;max-width:160px;max-height:58px;object-fit:contain}</style>
    @stack('head')
    <script type="application/ld+json">{!! json_encode($websiteSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
</head>
<body class="store-body theme-header-{{ $shop['theme_header_style'] ?? 'standard' }} @yield('body_class')">
<a href="#main" class="visually-hidden-focusable skip-link">Langsung ke konten</a>
@php($announcements=collect(preg_split('/[\r\n]+/',$shop['announcement_lines'] ?? ''))->map(fn($line)=>trim($line))->filter())
@php($announcements=$announcements->isEmpty()?collect([$shop['tagline'] ?? '','WORLDWIDE SHIPPING'])->filter():$announcements)
@if($shop['header_announcement_enabled'] ?? true)<div class="announcement" data-announcement-interval="{{ (int)round((float)($shop['announcement_interval_seconds'] ?? 5)*1000) }}">
    <button type="button" data-notice-prev aria-label="Pengumuman sebelumnya" @disabled($announcements->count()<2)><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg></button>
    <div aria-live="polite">@foreach($announcements as $notice)<span class="{{ $loop->first?'is-active':'' }}">{{ $notice }}</span>@endforeach</div>
    <button type="button" data-notice-next aria-label="Pengumuman berikutnya" @disabled($announcements->count()<2)><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
</div>@endif
<header @class(['site-header','sticky-top'=>($shop['header_sticky'] ?? true)])>
    <div class="container-xxl header-row">
        <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#mobile-nav" aria-label="Buka menu" aria-controls="mobile-nav" aria-expanded="false"><span></span><span></span><span></span></button>
        <a class="wordmark" href="{{ route('home') }}">
            @if(!empty($shop['logo']))<img src="{{ asset('storage/'.$shop['logo']) }}" alt="{{ $shop['brand_name'] ?? 'Asfara' }}" width="240" height="80" decoding="async">
            @else<strong>{{ strtoupper($shop['brand_name'] ?? 'Asfara') }}</strong><span>{{ $shop['brand_subtitle'] ?? 'MODEST WEAR' }}</span>@endif
        </a>
        <nav class="desktop-nav" aria-label="Utama">
            @foreach($desktopItems as $item)
                @if($item['panel'])
                    @php($panelId = $panelIds[$item['panel']])
                    <div class="store-nav-entry">
                        <button type="button" class="store-nav-button" id="nav-trigger-{{ $item['panel'] }}" data-nav-trigger aria-expanded="false" aria-controls="{{ $panelId }}">
                            {{ $item['label'] }} <span aria-hidden="true"></span>
                        </button>
                        <section class="store-nav-panel" id="{{ $panelId }}" data-nav-panel aria-labelledby="nav-trigger-{{ $item['panel'] }}" aria-hidden="true" inert>
                            @include('partials.header-menu', ['mobile' => false])
                        </section>
                    </div>
                @else
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endif
            @endforeach
        </nav>
        <div class="header-actions">
            <details class="language-picker"><summary>{{ app()->getLocale()==='id'?'INDONESIA':'ENGLISH' }}</summary><div><a href="{{ route('locale','id') }}" @class(['is-active'=>app()->getLocale()==='id'])><span>Indonesia</span><small>ID</small></a><a href="{{ route('locale','en') }}" @class(['is-active'=>app()->getLocale()==='en'])><span>English</span><small>EN</small></a></div></details>
            <a href="{{ route('account') }}" aria-label="Akun saya"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/></svg></a>
            <button type="button" class="header-search-button" id="open-search" aria-label="Cari produk"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
            <a href="{{ route('cart') }}" data-open-cart aria-label="Keranjang"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l2 14H3L5 8zM8 8V5a4 4 0 0 1 8 0v3"/></svg>@if(array_sum(session('cart',[]))>0)<span>{{ array_sum(session('cart',[])) }}</span>@endif</a>
        </div>
    </div>
    <button type="button" class="mobile-nav-backdrop" data-mobile-nav-backdrop aria-label="Tutup navigasi" tabindex="-1" hidden></button>
    <nav class="collapse mobile-nav" id="mobile-nav" aria-label="Navigasi mobile">
        <header><a class="wordmark" href="{{ route('home') }}"><strong>{{ strtoupper($shop['brand_name'] ?? 'Asfara') }}</strong><span>{{ $shop['brand_subtitle'] ?? 'MODEST WEAR' }}</span></a><button type="button" class="mobile-nav-close" data-bs-toggle="collapse" data-bs-target="#mobile-nav" aria-label="Tutup menu">×</button></header>
        <div class="mobile-nav-links">
            @foreach($mobileItems as $item)
                @if($item['panel'])
                    <details name="mobile-discovery" @if($item['panel'] === $mobileOpenPanel) open @endif>
                        <summary>{{ $item['label'] }}</summary>
                        @include('partials.header-menu', ['mobile' => true])
                    </details>
                @else
                    <a href="{{ $item['href'] }}">{{ in_array($item['kind'], ['link', 'cms'], true) ? $item['label'] : mb_strtoupper($item['label']) }}</a>
                @endif
            @endforeach
        </div>
        <footer><a href="{{ route('account') }}">ACCOUNT</a><button type="button" id="mobile-open-search">SEARCH</button><div class="mobile-language"><a href="{{ route('locale','id') }}">INDONESIA</a><a href="{{ route('locale','en') }}">ENGLISH</a></div></footer>
    </nav>
</header>
<dialog class="search-drawer" id="search-drawer" aria-labelledby="search-drawer-title">
    <div class="store-dialog-dismiss"><button type="button" id="close-search" aria-label="Tutup pencarian">×</button></div>
    <form action="{{ route('catalog') }}" method="get">
        <label id="search-drawer-title" for="global-search">{{ $storeCopy['search_title'] }}</label>
        <div><input id="global-search" name="q" inputmode="search" enterkeyhint="search" maxlength="100" placeholder="{{ $storeCopy['search_placeholder'] }}" autocomplete="off" aria-describedby="search-hint" aria-controls="predictive-search"><button type="submit" aria-label="Mulai mencari">→</button></div>
    </form>
    <p id="search-hint">{{ $storeCopy['search_hint'] }}</p>
    <p id="predictive-search-status" class="visually-hidden" role="status"></p>
    <div class="predictive-search" id="predictive-search"></div>
</dialog>
<dialog class="cart-drawer" id="cart-drawer" aria-labelledby="cart-drawer-title">
    <button type="button" class="drawer-close" data-close-cart aria-label="Tutup keranjang">×</button>
    <header><h2 id="cart-drawer-title">{{ $storeCopy['cart_title'] }} <small id="cart-drawer-count"></small></h2></header>
    <p id="cart-drawer-status" class="cart-drawer-status" role="status" hidden></p>
    <div id="cart-drawer-lines" aria-live="polite"><p>Memuat keranjang…</p></div>
    <div class="cart-drawer-footer" id="cart-drawer-footer" hidden>
        <p><span>SUBTOTAL</span><strong id="cart-drawer-subtotal"></strong></p>
        <small>Ongkir dan total akhir dihitung saat checkout.</small>
        <a class="btn btn-dark" href="{{ route('checkout') }}">CHECKOUT</a>
        <a class="btn btn-outline-dark" href="{{ route('cart') }}">LIHAT KERANJANG</a>
    </div>
</dialog>
<dialog class="quick-product-dialog" id="quick-product-dialog" aria-labelledby="quick-product-name">
    <div class="store-dialog-dismiss"><button type="button" class="drawer-close" data-quick-close aria-label="Tutup pilihan produk">×</button></div>
    <div class="quick-product-layout">
        <figure class="quick-product-media" data-quick-gallery aria-label="{{ __('store.photo_gallery') }}">
            <img id="quick-product-image" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="" width="800" height="800" draggable="false" decoding="async">
            <div class="quick-gallery-controls" hidden>
                <button type="button" data-quick-photo-step="-1" aria-label="{{ __('store.photo_previous') }}">‹</button>
                <button type="button" data-quick-photo-step="1" aria-label="{{ __('store.photo_next') }}">›</button>
                <span class="quick-gallery-count" data-quick-photo-count aria-live="polite"></span>
            </div>
        </figure>
        <div>
            <span class="eyebrow">{{ $storeCopy['quick_shop'] }}</span>
            <h2 id="quick-product-name"></h2>
            <p class="quick-price" id="quick-product-price" aria-live="polite"></p>
            <p id="quick-product-stock" class="small" aria-live="polite"></p>
            <form method="post" action="{{ route('cart.add') }}" id="quick-product-form" data-cart-add>
                @csrf<div id="quick-product-options"></div>
                <input type="hidden" name="variant_id" id="quick-variant"><input type="hidden" name="quantity" value="1">
                <button class="btn btn-dark w-100" id="quick-submit" disabled>Pilih varian</button>
                <p class="cart-form-error" data-cart-add-error role="alert" hidden></p>
            </form>
            <a id="quick-product-link" href="#">{{ $storeCopy['quick_view_detail'] }}</a>
        </div>
    </div>
</dialog>
<main id="main" tabindex="-1">@include('partials.alerts')@yield('content')</main>
@php($welcomeStartsAt=!empty($shop['welcome_voucher_start'])?\Illuminate\Support\Carbon::parse($shop['welcome_voucher_start']):null)
@php($welcomeEndsAt=!empty($shop['welcome_voucher_end'])?\Illuminate\Support\Carbon::parse($shop['welcome_voucher_end']):null)
@php($welcomePage=request()->routeIs('home') || (request()->routeIs('content.show') && request()->route('type') === 'campaign' && request()->route('slug') === $popupCampaign?->slug))
@php($welcomeActive=$popupCoupon && $welcomePage && (!$welcomeStartsAt || $welcomeStartsAt->lte(now())) && (!$welcomeEndsAt || $welcomeEndsAt->gte(now())))
@if($welcomeActive)
    <dialog @class(['welcome-voucher', 'has-image' => !empty($shop['welcome_voucher_image'])]) id="welcome-voucher" aria-labelledby="welcome-voucher-title" data-delay="{{ (int) ($shop['welcome_voucher_delay_seconds'] ?? 1) }}" data-frequency-days="{{ (int) ($shop['welcome_voucher_frequency_days'] ?? 30) }}">
        @if(!empty($shop['welcome_voucher_image']))
            <div class="welcome-voucher-media"><img src="{{ asset('storage/'.$shop['welcome_voucher_image']) }}" alt="" width="800" height="1000" loading="lazy" decoding="async"></div>
        @endif
        <div class="welcome-voucher-content">
            <button type="button" class="drawer-close" data-close-welcome aria-label="Tutup">×</button>
            <span class="eyebrow">{{ $storeCopy['welcome_eyebrow'] }}</span>
            <h2 id="welcome-voucher-title">{{ ($shop['welcome_voucher_title'] ?? '') ?: 'Hadiah untuk pembelian pertama' }}</h2>
            <p>{{ $shop['welcome_voucher_text'] ?? '' }}</p>
            <button type="button" class="voucher-code" data-copy-voucher="{{ $popupCoupon->code }}"><span>{{ $popupCoupon->code }}</span><small>{{ $storeCopy['welcome_copy'] }}</small></button>
            <a class="btn btn-dark" href="{{ ($shop['welcome_voucher_cta_url'] ?? '') ?: route('content.show', ['campaign', $popupCampaign->slug]) }}">{{ strtoupper(($shop['welcome_voucher_cta_label'] ?? '') ?: $storeCopy['welcome_cta']) }}</a>
        </div>
    </dialog>
@endif
@if(($shop['floating_whatsapp_enabled'] ?? true) && !empty($shop['whatsapp']))<a class="floating-whatsapp" data-floating-whatsapp href="https://wa.me/{{ $shop['whatsapp'] }}" target="_blank" rel="noopener" aria-label="Chat Asfara melalui WhatsApp"><span>{{ $storeCopy['whatsapp_floating'] }}</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 3.5A11.7 11.7 0 0 0 2.1 17.6L.5 23.5l6-1.6A11.7 11.7 0 0 0 20.5 3.5Zm-8.3 17a9.6 9.6 0 0 1-4.9-1.3l-.4-.2-3.5.9.9-3.4-.2-.4a9.6 9.6 0 1 1 8.1 4.4Zm5.3-7.2c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.2l-.9 1.1c-.2.2-.4.2-.7.1-1.8-.9-3-1.6-4.2-3.6-.3-.5.3-.5.9-1.7.1-.2 0-.4 0-.6L9 5.9c-.2-.5-.5-.4-.7-.4h-.6c-.2 0-.6.1-.9.4-.3.4-1.2 1.2-1.2 2.9s1.2 3.3 1.4 3.6c.2.2 2.4 3.7 5.9 5.2 2.2.9 3.1 1 4.2.8.7-.1 1.7-.7 1.9-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.3Z"/></svg></a>@endif
<footer class="site-footer">
    <div class="container-xxl">
        <div class="row footer-columns">
            <div class="col-lg-3">
                <a class="wordmark" href="{{ route('home') }}"><strong>{{ strtoupper($shop['brand_name'] ?? 'Asfara') }}</strong><span>{{ $shop['brand_subtitle'] ?? 'MODEST WEAR' }}</span></a>
                @if(!empty($shop['footer_text']))<p class="footer-brand-description">{{ $shop['footer_text'] }}</p>@endif
                <ul class="footer-link-list">
                    @if(!empty($shop['contact_email']))<li><a href="mailto:{{ $shop['contact_email'] }}">{{ $shop['contact_email'] }}</a></li>@endif
                    @foreach($navigation['footer']->get('brand', collect()) as $link)
                        <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            @foreach($navigation['footer_order'] as $column)
                @if($column === 'connect')
                    @include('partials.footer-connect', ['newsletterEnabled' => $navigation['newsletter_enabled']])
                @else
                    @php($links = $navigation['footer']->get($column, collect()))
                    @continue($column !== 'stores' && $links->isEmpty())
                    <details class="col-lg-2 footer-group" @if($column === 'stores') id="store-locations" @endif open>
                        <summary>{{ __('store.'.$column) }}</summary>
                        @if($column === 'stores')
                            @forelse($storeLocations->take(2) as $location)
                                <div class="footer-store">
                                    <strong>{{ $location->name }}</strong>
                                    <small>{{ $location->address }}@if($location->city) · {{ $location->city }}@endif</small>
                                    @if($location->map_url)<a href="{{ $location->map_url }}" target="_blank" rel="noopener">MAPS ↗</a>@endif
                                </div>
                            @empty
                                <small>{{ $shop['store_location'] ?? '' }}</small>
                            @endforelse
                        @endif
                        @if($links->isNotEmpty())
                            <ul class="footer-link-list">
                                @foreach($links as $link)
                                    <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </details>
                @endif
            @endforeach
        </div>
        <div class="footer-bottom">© {{ date('Y') }} {{ $shop['brand_name'] ?? 'Asfara' }} {{ $shop['footer_copyright'] ?? '' }} <span>{{ $storeCopy['footer_trust'] }}</span></div>
    </div>
</footer>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ filemtime(public_path('vendor/bootstrap/bootstrap.bundle.min.js')) }}"></script>
<script>
(()=>{
    const bar=document.querySelector('.announcement');
    const notices=[...document.querySelectorAll('.announcement div>span')];
    if(!bar||notices.length<2)return;
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    let active=Math.max(0,notices.findIndex(notice=>notice.classList.contains('is-active'))),timer;
    let hovered = false;
    const show=index=>{
        active=(index+notices.length)%notices.length;
        notices.forEach((notice,i)=>notice.classList.toggle('is-active',i===active));
    };
    const play=()=>{
        clearTimeout(timer);
        if(document.hidden || reducedMotion.matches || hovered || bar.matches(':focus-within'))return;
        const delay=Math.max(2000,Number(bar.dataset.announcementInterval)||5000);
        timer=setTimeout(()=>{show(active+1);play()},delay);
    };
    document.addEventListener('visibilitychange',play);
    reducedMotion.addEventListener('change', play);
    bar.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') { hovered = true; play(); } });
    bar.addEventListener('pointerleave', () => { hovered = false; play(); });
    bar.addEventListener('focusin', play);
    bar.addEventListener('focusout', () => requestAnimationFrame(play));
    bar.querySelector('[data-notice-prev]')?.addEventListener('click',()=>{show(active-1);play()});
    bar.querySelector('[data-notice-next]')?.addEventListener('click',()=>{show(active+1);play()});
    play();
})();
</script>
<script>
(()=>{
    const hero=document.querySelector('[data-hero-slider]');
    const slides=[...document.querySelectorAll('[data-hero-slide]')];
    const dots=[...document.querySelectorAll('[data-hero-dot]')];
    if(!hero||!slides.length)return;
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const playbackPreferences = new WeakMap();
    let active=Math.max(0,slides.findIndex(slide=>slide.classList.contains('is-active'))),timer,startX=0;
    let heroVisible = hero.getBoundingClientRect().bottom > 0;
    const syncVideos = () => slides.forEach((slide, index) => {
        const video = slide.querySelector('[data-hero-video]');
        if (!video) return;
        const requestedPlayback = playbackPreferences.get(video) ?? !reducedMotion.matches;
        if (index === active && heroVisible && !document.hidden && requestedPlayback) {
            video.play().catch(() => {});
        } else {
            video.pause();
        }
    });
    const show=index=>{
        active=(index+slides.length)%slides.length;
        slides.forEach((slide,i)=>{slide.classList.toggle('is-active',i===active);slide.setAttribute('aria-hidden',i===active?'false':'true')});
        dots.forEach((dot,i)=>dot.classList.toggle('is-active',i===active));
        syncVideos();
    };
    const play=()=>{
        clearTimeout(timer);
        if(document.hidden || reducedMotion.matches || !heroVisible || hero.matches(':focus-within'))return;
        if(slides.length>1){const delay=Math.max(2500,Number(slides[active]?.dataset.duration)||5000);timer=setTimeout(()=>{show(active+1);play()},delay)}
    };
    slides.forEach(slide => {
        const video = slide.querySelector('[data-hero-video]');
        if (!video) return;
        const playButton = slide.querySelector('[data-hero-play]');
        const soundButton = slide.querySelector('[data-hero-sound]');
        if (video.dataset.mobilePoster) {
            const mobile = matchMedia('(max-width: 767px)');
            const desktopPoster = video.poster;
            const updatePoster = () => { video.poster = mobile.matches ? video.dataset.mobilePoster : desktopPoster; };
            mobile.addEventListener('change', updatePoster);
            updatePoster();
        }
        const updatePlayButton = () => {
            playButton.classList.toggle('is-playing', !video.paused);
            playButton.setAttribute('aria-label', video.paused ? 'Putar video' : 'Jeda video');
        };
        video.addEventListener('play', updatePlayButton);
        video.addEventListener('pause', updatePlayButton);
        playButton.addEventListener('click', () => {
            playbackPreferences.set(video, video.paused);
            syncVideos();
        });
        soundButton?.addEventListener('click', () => {
            video.muted = !video.muted;
            soundButton.setAttribute('aria-pressed', String(!video.muted));
        });
    });
    hero.addEventListener('pointerdown',event=>{startX=event.clientX});
    hero.addEventListener('pointerup',event=>{const delta=event.clientX-startX;if(Math.abs(delta)>55){show(active+(delta<0?1:-1));play()}});
    document.addEventListener('visibilitychange',()=>{syncVideos();play()});
    reducedMotion.addEventListener('change', () => { syncVideos(); play(); });
    hero.addEventListener('focusin', play);
    hero.addEventListener('focusout', () => requestAnimationFrame(play));
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            heroVisible = entry.isIntersecting;
            syncVideos();
            play();
        }).observe(hero);
    }
    syncVideos();
    play();
})();
</script>
<script>
const storeVariants = (() => {
    const field = (variant, key) => String((variant?.dataset ?? variant)?.[key] ?? '').toLowerCase();
    const choices = (variants, groups, kind, current) => {
        const preceding = groups.slice(0, Math.max(0, groups.indexOf(kind)));
        return variants.filter(variant => preceding.every(key => field(variant, key) === field(current, key)));
    };
    const match = (variants, groups, kind, value, current) => {
        const score = variant => groups.filter(key => key !== kind && field(variant, key) === field(current, key)).length;
        return choices(variants, groups, kind, current)
            .filter(variant => field(variant, kind) === String(value).toLowerCase())
            .sort((left, right) => score(right) - score(left))[0];
    };
    const stock = (available, method) => available
        ? (method === 'Pre-order' ? 'Pre-order tersedia' : 'Stok tersedia')
        : 'Varian ini habis';
    const gallery = (photos, variant, color) => [
        ...photos.filter(photo => field(photo, 'variant') === String(variant)),
        ...photos.filter(photo => !field(photo, 'variant') && field(photo, 'color') && field(photo, 'color') === String(color ?? '').toLowerCase()),
        ...photos.filter(photo => !field(photo, 'variant') && !field(photo, 'color')),
    ];
    return {field, choices, match, stock, gallery};
})();
const bindGallerySwipe = (stage, image, step) => {
    let swipe;
    stage.addEventListener('pointerdown', event => {
        if (event.target !== image || !event.isPrimary || event.button !== 0) return;
        swipe = {id: event.pointerId, x: event.clientX, y: event.clientY};
        stage.setPointerCapture(event.pointerId);
    });
    stage.addEventListener('pointerup', event => {
        if (swipe?.id !== event.pointerId) return;
        const delta = event.clientX - swipe.x;
        if (Math.abs(delta) > 45 && Math.abs(delta) > Math.abs(event.clientY - swipe.y)) step(delta < 0 ? 1 : -1);
        swipe = undefined;
    });
    stage.addEventListener('lostpointercapture', () => swipe = undefined);
};
(()=>{const header=document.querySelector('.site-header'),floatingWhatsApp=document.querySelector('[data-floating-whatsapp]');const syncHeader=()=>{header?.classList.toggle('is-scrolled',header.classList.contains('sticky-top')&&scrollY>80);floatingWhatsApp?.classList.toggle('is-compact',scrollY>160)};syncHeader();addEventListener('scroll',syncHeader,{passive:true});
const desktopNav = matchMedia('(min-width: 992px)');
const hoverPointer = matchMedia('(hover: hover) and (pointer: fine)');
const disclosures = [...header.querySelectorAll('[data-nav-trigger]')].map(trigger => ({
    trigger,
    panel: document.getElementById(trigger.getAttribute('aria-controls')),
}));
let activeMenu;
let pinned = false;
let navOpenTimer;
let navCloseTimer;

const clearMenuTimers = () => {
    clearTimeout(navOpenTimer);
    clearTimeout(navCloseTimer);
};
const renderMenu = (menu, open) => {
    menu.trigger.setAttribute('aria-expanded', String(open));
    menu.panel.setAttribute('aria-hidden', String(!open));
    menu.panel.inert = !open;
    menu.panel.classList.toggle('is-open', open);
};
function setMenu(next, returnFocus = false) {
    clearMenuTimers();
    if (next === activeMenu) return;
    const previous = activeMenu;
    if (previous) renderMenu(previous, false);
    activeMenu = next;
    pinned = false;
    if (next) renderMenu(next, true);
    header.classList.toggle('has-open-navigation', Boolean(next));
    if (returnFocus) previous?.trigger.focus({preventScroll: true});
}
const containsMenu = (menu, target) => Boolean(menu?.trigger.contains(target) || menu?.panel.contains(target));
const canHoverMenu = event => event.pointerType === 'mouse' && hoverPointer.matches && desktopNav.matches;
const scheduleMenuClose = (menu, event) => {
    if (!canHoverMenu(event) || containsMenu(menu, event.relatedTarget)) return;
    clearMenuTimers();
    if (activeMenu !== menu || pinned) return;
    navCloseTimer = setTimeout(() => {
        if (activeMenu === menu && !menu.panel.contains(document.activeElement)) setMenu();
    }, 170);
};
disclosures.forEach(menu => {
    menu.trigger.addEventListener('pointerenter', event => {
        if (!canHoverMenu(event)) return;
        clearMenuTimers();
        if (activeMenu) setMenu(menu);
        else navOpenTimer = setTimeout(() => setMenu(menu), 60);
    });
    menu.panel.addEventListener('pointerenter', event => {
        if (canHoverMenu(event)) clearMenuTimers();
    });
    menu.panel.addEventListener('click', event => {
        if (event.target.closest('a[href]')) setMenu();
    });
    [menu.trigger, menu.panel].forEach(element => element.addEventListener('pointerleave', event => scheduleMenuClose(menu, event)));
    menu.trigger.addEventListener('keydown', event => {
        if (!['ArrowDown', 'ArrowUp'].includes(event.key) || !desktopNav.matches) return;
        event.preventDefault();
        setMenu(menu);
        const links = [...menu.panel.querySelectorAll('a[href]')];
        (event.key === 'ArrowUp' ? links.at(-1) : links[0])?.focus();
    });
    menu.trigger.addEventListener('click', event => {
        if (activeMenu === menu && (event.detail === 0 || pinned)) {
            setMenu();
        } else {
            setMenu(menu);
            pinned = true;
        }
    });
});
header.querySelectorAll('.desktop-nav > a').forEach(link => link.addEventListener('pointerenter', event => {
    if (canHoverMenu(event) && !activeMenu?.panel.contains(document.activeElement)) setMenu();
}));
document.addEventListener('pointerdown', event => { if (!containsMenu(activeMenu, event.target)) setMenu(); });
document.addEventListener('focusin', event => { if (activeMenu && !containsMenu(activeMenu, event.target)) setMenu(); });
desktopNav.addEventListener('change', () => setMenu());
const esc=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
const storefrontCopy=@json($storeCopy);
const searchDrawer = document.getElementById('search-drawer');
const searchInput = document.getElementById('global-search');
const searchResults = document.getElementById('predictive-search');
const searchStatus = document.getElementById('predictive-search-status');
const searchCopy = @json(__('store.search_panel'));
let searchTimer;
let searchRequest;
const renderSearch = ({suggestions = [], products = [], collections = [], results_url}) => {
    if (!suggestions.length && !products.length && !collections.length) {
        searchResults.innerHTML = `<p>${esc(searchCopy.empty)}</p>`;
        searchStatus.textContent = searchCopy.empty;
        return;
    }

    const textLink = item => `<a href="${esc(item.url)}">${esc(item.name)}</a>`;
    const productLink = item => `<a class="predictive-product" href="${esc(item.url)}"><img src="${esc(item.image)}" alt="" width="96" height="112" loading="lazy" decoding="async"><span><b>${esc(item.name)}</b><small>${esc(item.price)}</small></span></a>`;
    const viewAll = results_url ? `<a class="predictive-all-results" href="${esc(results_url)}">${esc(searchCopy.all_results)} <span aria-hidden="true">→</span></a>` : '';
    const section = (key, items, link) => items.length ? `
        <section class="predictive-${key}" aria-labelledby="predictive-${key}-title">
            <div class="predictive-heading"><h2 id="predictive-${key}-title">${esc(searchCopy[key])}</h2>${key === 'products' ? viewAll : ''}</div>
            <ul role="list">${items.map(item => `<li>${link(item)}</li>`).join('')}</ul>
        </section>` : '';
    searchResults.innerHTML = `${section('keywords', suggestions, textLink)}
        <div class="predictive-groups">${section('products', products, productLink)}${section('collections', collections, textLink)}</div>`;
    searchStatus.textContent = searchCopy.updated;
};
const openSearch = () => {
    if (searchDrawer.open) return;
    searchDrawer.showModal();
    searchInput.focus({preventScroll: true});
    if (searchInput.value.trim().length >= 2 && !searchResults.hasChildNodes()) searchInput.dispatchEvent(new Event('input'));
};
document.getElementById('open-search')?.addEventListener('click', openSearch);
document.getElementById('mobile-open-search')?.addEventListener('click', () => {
    mobileNav.addEventListener('hidden.bs.collapse', openSearch, {once: true});
    mobileNavToggle.focus({preventScroll: true});
    hideMobileNav();
});
document.getElementById('close-search')?.addEventListener('click', () => searchDrawer.close());
searchDrawer.addEventListener('click', event => { if (event.target === searchDrawer) searchDrawer.close(); });
searchDrawer.addEventListener('close', () => {
    clearTimeout(searchTimer);
    searchRequest?.abort();
    if (searchResults.getAttribute('aria-busy') === 'true') searchResults.replaceChildren();
    searchResults.removeAttribute('aria-busy');
    searchStatus.textContent = '';
});
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchRequest?.abort();
    const query = searchInput.value.trim();
    if (query.length < 2) {
        searchResults.replaceChildren();
        searchResults.removeAttribute('aria-busy');
        searchStatus.textContent = '';
        return;
    }
    searchResults.setAttribute('aria-busy', 'true');
    searchResults.innerHTML = `<p>${esc(searchCopy.loading)}</p>`;
    searchStatus.textContent = searchCopy.loading;
    searchTimer = setTimeout(async () => {
        const request = new AbortController();
        searchRequest = request;
        try {
            const url = new URL(@json(route('search.suggestions')), location.href);
            url.searchParams.set('q', query);
            const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: request.signal});
            if (!response.ok) throw new Error('Search request failed');
            const data = await response.json();
            if (request.signal.aborted) return;
            renderSearch(data);
        } catch (error) {
            if (!request.signal.aborted) {
                searchResults.innerHTML = `<p>${esc(searchCopy.error)}</p>`;
                searchStatus.textContent = searchCopy.error;
            }
        } finally {
            if (!request.signal.aborted) searchResults.removeAttribute('aria-busy');
        }
    }, 220);
});
const cartDrawer = document.getElementById('cart-drawer');
const cartLines = document.getElementById('cart-drawer-lines');
const cartFooter = document.getElementById('cart-drawer-footer');
const cartDrawerStatus = document.getElementById('cart-drawer-status');
let cartReadSequence = 0;
let cartReturnFocus = null;
const cartRequest = async (url, options = {}) => {
    const endpoint = new URL(url, location.href);
    endpoint.searchParams.set('compact', '1');
    const response = await fetch(endpoint, {...options, headers: {Accept: 'application/json'}});
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Keranjang belum dapat diperbarui.');
    return data;
};
const showCartStatus = message => {
    cartDrawerStatus.textContent = message || '';
    cartDrawerStatus.hidden = !message;
};
const renderCartDrawer = data => {
    cartLines.innerHTML = data.lines.length ? data.lines.map(line => `
        <div class="cart-drawer-line" data-drawer-line="${line.variant_id}">
            <a href="${esc(line.url)}"><img src="${esc(line.image)}" alt="" width="176" height="224" decoding="async"></a>
            <div>
                <a href="${esc(line.url)}"><b>${esc(line.name)}</b></a>
                <small>${esc(line.variant)}</small>
                <strong>${esc(line.total)}</strong>
                <div class="drawer-quantity">
                    <button type="button" data-drawer-quantity="${line.quantity - 1}" data-variant="${line.variant_id}" data-cart-control="minus" aria-label="Kurangi jumlah">−</button>
                    <span>${line.quantity}</span>
                    <button type="button" data-drawer-quantity="${line.quantity + 1}" data-variant="${line.variant_id}" data-cart-control="plus" aria-label="Tambah jumlah" ${line.quantity >= 100 ? 'disabled' : ''}>+</button>
                    <button type="button" class="drawer-remove" data-drawer-quantity="0" data-variant="${line.variant_id}" aria-label="Hapus ${esc(line.name)}">Hapus</button>
                </div>
            </div>
        </div>`).join('') : `<div class="drawer-empty"><p>${esc(storefrontCopy.cart_empty)}</p><a href="{{ route('catalog') }}">${esc(storefrontCopy.cart_start)}</a></div>`;
    cartFooter.hidden = !data.lines.length;
    document.getElementById('cart-drawer-subtotal').textContent = data.subtotal;
    document.getElementById('cart-drawer-count').textContent = data.count ? '(' + data.count + ')' : '';
    const cartLink = document.querySelector('[data-open-cart]');
    let badge = cartLink?.querySelector('span');
    if (data.count > 0 && !badge && cartLink) {
        badge = document.createElement('span');
        cartLink.append(badge);
    }
    if (badge) {
        badge.textContent = data.count;
        badge.hidden = data.count < 1;
    }
};
const openCartDrawer = () => {
    if (!cartDrawer.open) cartDrawer.showModal();
};
const loadCartDrawer = async (message = '') => {
    const read = ++cartReadSequence;
    openCartDrawer();
    showCartStatus(message);
    cartLines.innerHTML = '<p>Memuat keranjang…</p>';
    cartFooter.hidden = true;
    try {
        const data = await cartRequest(@json(route('cart.summary')));
        if (read === cartReadSequence) renderCartDrawer(data);
    } catch {
        if (read === cartReadSequence) cartLines.innerHTML = '<p>Keranjang belum dapat dimuat. <a href="{{ route('cart') }}">Buka halaman keranjang</a>.</p>';
    }
};
let cartMutationPending = false;
document.querySelector('[data-open-cart]')?.addEventListener('click', event => {
    if (!cartDrawer?.showModal) return;
    event.preventDefault();
    if (!cartMutationPending) loadCartDrawer();
});
const cartErrorMessage = error => error instanceof SyntaxError || error instanceof TypeError
    ? 'Status keranjang belum bisa dipastikan. Periksa isi keranjang sebelum mencoba lagi.'
    : error.message;
cartLines.addEventListener('click', async event => {
    const button = event.target.closest('[data-drawer-quantity]');
    if (!button || cartMutationPending) return;
    cartMutationPending = true;
    button.disabled = true;
    showCartStatus('');
    try {
        const data = await cartRequest(@json(route('cart.update')), {
            method: 'POST',
            body: new URLSearchParams({_token: @json(csrf_token()), variant_id: button.dataset.variant, quantity: button.dataset.drawerQuantity}),
        });
        renderCartDrawer(data);
        const updated = cartLines.querySelector(`[data-drawer-line="${button.dataset.variant}"] [data-cart-control="${button.dataset.cartControl}"]`);
        (updated && !updated.disabled ? updated : cartDrawer.querySelector('[data-close-cart]')).focus();
    } catch (error) {
        button.disabled = false;
        showCartStatus(cartErrorMessage(error));
    } finally {
        cartMutationPending = false;
    }
});
document.addEventListener('submit', async event => {
    const form = event.target;
    if (!form.matches('form[data-cart-add]') || event.submitter?.name === 'buy_now' || !cartDrawer?.showModal) return;
    event.preventDefault();
    if (cartMutationPending) return;
    cartMutationPending = true;
    ++cartReadSequence;
    const button = event.submitter;
    const returnTarget = quickDialog.open ? quickTrigger : (document.activeElement || button);
    const errors = form.querySelector('[data-cart-add-error]');
    if (errors) errors.hidden = true;
    form.setAttribute('aria-busy', 'true');
    if (button) button.disabled = true;
    try {
        const data = await cartRequest(form.action, {method: 'POST', body: new FormData(form)});
        if (quickDialog.open) quickDialog.close();
        renderCartDrawer(data);
        showCartStatus('Produk ditambahkan ke keranjang.');
        cartReturnFocus = returnTarget;
        openCartDrawer();
    } catch (error) {
        if (errors) {
            errors.textContent = cartErrorMessage(error);
            errors.hidden = false;
            if (errors.hasAttribute('tabindex')) errors.focus();
        } else {
            cartReturnFocus = returnTarget;
            await loadCartDrawer(cartErrorMessage(error));
        }
    } finally {
        if (button) button.disabled = false;
        form.removeAttribute('aria-busy');
        cartMutationPending = false;
    }
});
document.querySelector('[data-close-cart]')?.addEventListener('click', () => cartDrawer.close());
cartDrawer?.addEventListener('click', event => { if (event.target === cartDrawer) cartDrawer.close(); });
cartDrawer.addEventListener('close', () => {
    const target = cartReturnFocus;
    cartReturnFocus = null;
    if (target?.isConnected) requestAnimationFrame(() => target.focus({preventScroll: true}));
});
const quickColor=value=>{const color=String(value).toLowerCase();if(color.includes('black'))return'#202020';if(color.includes('navy'))return'#26344c';if(color.includes('olive'))return'#747452';if(color.includes('sage'))return'#a8b19a';if(color.includes('rose')||color.includes('pink'))return'#d8a7ad';if(color.includes('plum'))return'#70445d';if(color.includes('mauve'))return'#b58b96';if(color.includes('taupe'))return'#a18d7d';if(color.includes('sand')||color.includes('beige'))return'#d5c2a5';if(color.includes('ivory'))return'#f1ebdd';return'#b6aca1'};
const quickDialog = document.getElementById('quick-product-dialog');
const quickOptions = document.getElementById('quick-product-options');
const quickVariant = document.getElementById('quick-variant');
const quickSubmit = document.getElementById('quick-submit');
const quickStage = quickDialog.querySelector('[data-quick-gallery]');
const quickImage = document.getElementById('quick-product-image');
const quickGalleryControls = quickStage.querySelector('.quick-gallery-controls');
const quickPhotoCount = quickStage.querySelector('[data-quick-photo-count]');
let quickData = null;
let quickSelected;
let quickConfirmed = false;
let quickTrigger = null;
let quickPhotos = [];
let quickPhotoIndex = 0;
const showQuickPhoto = index => {
    quickPhotoIndex = (index + quickPhotos.length) % quickPhotos.length;
    const photo = quickPhotos[quickPhotoIndex];
    quickImage.src = photo.image;
    quickImage.alt = photo.alt;
    quickImage.style.objectPosition = photo.focal_x + '% ' + photo.focal_y + '%';
    quickPhotoCount.textContent = (quickPhotoIndex + 1) + ' / ' + quickPhotos.length;
};
const stepQuickPhoto = direction => {
    if (quickPhotos.length > 1) showQuickPhoto(quickPhotoIndex + direction);
};
const renderQuickGallery = () => {
    const matches = storeVariants.gallery(quickData.gallery, quickSelected.id, quickSelected.color);
    const photos = matches.length ? matches : [{
        image: quickSelected.image ?? quickData.image, alt: quickData.name,
        focal_x: quickSelected.focal_x, focal_y: quickSelected.focal_y,
    }];
    if (photos.length === quickPhotos.length && photos.every((photo, index) => photo === quickPhotos[index])) return;

    quickPhotos = photos;
    quickGalleryControls.hidden = photos.length < 2;
    showQuickPhoto(0);
};
quickStage.querySelectorAll('[data-quick-photo-step]').forEach(button => {
    button.addEventListener('click', () => stepQuickPhoto(Number(button.dataset.quickPhotoStep)));
});
quickStage.addEventListener('keydown', event => {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
    event.preventDefault();
    stepQuickPhoto(event.key === 'ArrowLeft' ? -1 : 1);
});
bindGallerySwipe(quickStage, quickImage, stepQuickPhoto);
const renderQuick = () => {
    const available = quickData.variants.filter(item => item.available > 0);
    const kinds = [
        ...(quickData.usesType ? [['type', 'Jenis produk']] : []),
        ['color', 'Warna'],
        ['size', 'Ukuran'],
    ].filter(([kind]) => available.some(item => item[kind]));
    if (kinds.length === 0) quickConfirmed = true;
    const groups = kinds.map(([kind]) => kind);
    quickOptions.innerHTML = kinds.map(([kind, label]) => {
        const values = [...new Set(quickData.variants.map(item => item[kind]).filter(Boolean))];
        const choices = values.map(value => {
            const possible = storeVariants.choices(available, groups, kind, quickSelected)
                .some(item => storeVariants.field(item, kind) === String(value).toLowerCase());
            const selected = storeVariants.field(quickSelected, kind) === String(value).toLowerCase();
            const swatch = kind === 'color' ? `<i style="--swatch:${quickColor(value)}"></i>` : '';
            return `<button type="button" data-quick-kind="${kind}" data-quick-value="${esc(value)}" class="${selected ? 'is-active' : ''} ${kind === 'color' ? 'color-choice' : ''}" aria-pressed="${selected}" ${possible ? '' : 'disabled'}>${swatch}${esc(value)}</button>`;
        });
        return `<fieldset><legend>${label}</legend><div>${choices.join('')}</div></fieldset>`;
    }).join('');
    quickVariant.value = quickConfirmed ? quickSelected.id : '';
    quickSubmit.disabled = !quickConfirmed;
    quickSubmit.textContent = quickConfirmed ? 'Tambah ke keranjang' : 'Pilih varian';
    document.getElementById('quick-product-price').textContent = new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
    }).format(quickSelected.price);
    document.getElementById('quick-product-stock').textContent = [
        storeVariants.stock(true, quickSelected.method), quickSelected.label, 'SKU ' + quickSelected.sku,
    ].filter(Boolean).join(' · ');
    renderQuickGallery();
    const url = new URL(quickData.url, location.href);
    url.searchParams.set('variant', quickSelected.id);
    document.getElementById('quick-product-link').href = url.href;
    quickOptions.querySelectorAll('[data-quick-kind]').forEach(button => button.addEventListener('click', () => {
        const {quickKind, quickValue} = button.dataset;
        const closest = storeVariants.match(available, groups, quickKind, quickValue, quickSelected);
        if (!closest) return;
        const sizes = new Set(storeVariants.choices(available, groups, 'size', closest).map(item => storeVariants.field(item, 'size')));
        quickConfirmed = quickKind === 'size' || sizes.size < 2
            || (quickConfirmed && storeVariants.field(quickSelected, 'size') === storeVariants.field(closest, 'size'));
        quickSelected = closest;
        renderQuick();
        [...quickOptions.querySelectorAll('[data-quick-kind]')]
            .find(choice => choice.dataset.quickKind === quickKind && choice.dataset.quickValue === quickValue)?.focus();
    }));
};
document.addEventListener('click', event => {
    const button = event.target.closest('[data-quick-open]');
    if (!button) return;
    quickTrigger = button;
    quickData = JSON.parse(document.getElementById(button.dataset.quickOpen).textContent);
    quickSelected = quickData.variants.find(variant => variant.available > 0);
    if (!quickSelected) return;
    quickConfirmed = false;
    quickPhotos = [];
    quickDialog.querySelector('[data-cart-add-error]').hidden = true;
    document.getElementById('quick-product-name').textContent = quickData.name;
    renderQuick();
    quickDialog.showModal();
});
document.querySelector('[data-quick-close]')?.addEventListener('click', () => quickDialog.close());
quickDialog.addEventListener('click', event => { if (event.target === quickDialog) quickDialog.close(); });
const observer='IntersectionObserver'in window?new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target)}}),{threshold:.12}):null;document.querySelectorAll('.reveal').forEach(element=>observer?observer.observe(element):element.classList.add('is-visible'));
const markSecondaryReady = image => {
    if (image.matches?.('.product-image-secondary') && image.complete && image.naturalWidth) {
        image.closest('.product-image').classList.add('is-secondary-ready');
    }
};
document.addEventListener('load', event => markSecondaryReady(event.target), true);
document.querySelectorAll('.product-image-secondary').forEach(markSecondaryReady);
document.addEventListener('pointerover', event => {
    if (event.pointerType !== 'mouse') return;
    const image = event.target.closest('.product-card')?.querySelector('.product-image-secondary');
    if (image && !image.complete) image.loading = 'eager';
});

document.querySelectorAll('[data-drag-scroll]').forEach(track => {
    let gesture;
    let dragged = false;
    track.addEventListener('pointerdown', event => {
        dragged = false;
        if (track.scrollWidth <= track.clientWidth || event.pointerType !== 'mouse' || event.button !== 0 || event.target.closest('button, input, select, textarea')) return;
        gesture = {id: event.pointerId, x: event.clientX, left: track.scrollLeft};
    });
    track.addEventListener('dragstart', event => event.preventDefault());
    track.addEventListener('pointermove', event => {
        if (gesture?.id !== event.pointerId || !(event.buttons & 1)) return;
        const delta = event.clientX - gesture.x;
        if (!dragged && Math.abs(delta) < 8) return;
        if (!dragged) {
            dragged = true;
            track.setPointerCapture(event.pointerId);
            track.classList.add('is-dragging');
        }
        track.scrollLeft = gesture.left - delta;
    });
    const stop = event => {
        if (gesture?.id !== event.pointerId) return;
        gesture = undefined;
        track.classList.remove('is-dragging');
    };
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(name => track.addEventListener(name, stop));
    track.addEventListener('click', event => {
        if (!dragged || event.detail === 0) return;
        dragged = false;
        event.preventDefault();
        event.stopPropagation();
    }, true);
});
document.querySelectorAll('.reference-carousel').forEach(shell => {
    const track = shell.querySelector('.product-carousel');
    if (!track) return;
    const previous = shell.querySelector('[data-carousel-prev]');
    const next = shell.querySelector('[data-carousel-next]');
    const sync = () => {
        track.classList.toggle('is-scrollable', track.scrollWidth > track.clientWidth + 4);
        previous?.toggleAttribute('disabled', track.scrollLeft < 4);
        next?.toggleAttribute('disabled', track.scrollLeft >= track.scrollWidth - track.clientWidth - 4);
    };
    const move = direction => {
        const item = track.querySelector('.product-carousel-item');
        if (!item) return;
        const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
        track.scrollBy({left: direction * (item.getBoundingClientRect().width + gap), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
    };
    previous?.addEventListener('click', () => move(-1));
    next?.addEventListener('click', () => move(1));
    track.addEventListener('scroll', sync, {passive: true});
    if ('ResizeObserver' in window) new ResizeObserver(sync).observe(track);
    sync();
});
document.addEventListener('click',event=>document.querySelectorAll('.language-picker[open]').forEach(picker=>{if(!picker.contains(event.target))picker.removeAttribute('open')}));
document.querySelectorAll('[data-product-voucher-copy]').forEach(button=>button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(button.dataset.productVoucherCopy);button.querySelector('small').textContent='KODE TERSALIN'}catch(error){button.querySelector('small').textContent=button.dataset.productVoucherCopy}}));
const footerMobile = matchMedia('(max-width: 767px)');
const footerGroups = [...document.querySelectorAll('.footer-group')];
const syncFooterGroups = () => footerGroups.forEach(group => {
    group.open = !footerMobile.matches || (group.id === 'store-locations' && location.hash === '#store-locations');
});
syncFooterGroups();
footerMobile.addEventListener('change', syncFooterGroups);

const mobileNav = document.getElementById('mobile-nav');
const mobileNavToggle = header.querySelector('.menu-toggle');
const mobileNavBackdrop = header.querySelector('[data-mobile-nav-backdrop]');
const hideMobileNav = () => bootstrap.Collapse.getInstance(mobileNav)?.hide();
mobileNav.addEventListener('show.bs.collapse', () => { mobileNavBackdrop.hidden = false; });
mobileNav.addEventListener('shown.bs.collapse', () => requestAnimationFrame(() => {
    if (mobileNav.classList.contains('show')) mobileNav.querySelector('.mobile-nav-close').focus({preventScroll: true});
}));
mobileNav.addEventListener('hidden.bs.collapse', () => {
    mobileNavBackdrop.hidden = true;
    if (!document.querySelector('dialog[open]') && (mobileNav.contains(document.activeElement) || document.activeElement === document.body)) {
        mobileNavToggle.focus({preventScroll: true});
    }
});
mobileNavBackdrop.addEventListener('click', hideMobileNav);
mobileNav.querySelectorAll('a').forEach(link => link.addEventListener('click', hideMobileNav));
matchMedia('(min-width: 992px)').addEventListener('change', event => { if (event.matches) hideMobileNav(); });
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || document.querySelector('dialog[open]')) return;
    if (activeMenu) {
        event.preventDefault();
        setMenu(undefined, activeMenu.panel.contains(document.activeElement));
    } else if (mobileNav.classList.contains('show')) {
        event.preventDefault();
        hideMobileNav();
    }
});
const welcomeVoucher = document.getElementById('welcome-voucher');
if (welcomeVoucher) {
    const days = Number(welcomeVoucher.dataset.frequencyDays);
    const storage = days === 0 ? sessionStorage : localStorage;
    const key = 'asfara-welcome-voucher';
    const lastShown = Number(storage.getItem(key) || 0);
    const canShow = !lastShown || (days > 0 && Date.now() - lastShown >= days * 86400000);
    const showVoucher = () => {
        if (document.querySelector('dialog[open], [data-filter-panel].is-open')) return;
        welcomeVoucher.showModal();
        storage.setItem(key, String(Date.now()));
    };
    if (canShow) {
        setTimeout(() => {
            const hero = document.querySelector('[data-hero-slider]');
            if (!hero || hero.getBoundingClientRect().bottom <= 0 || !('IntersectionObserver' in window)) {
                showVoucher();
                return;
            }
            const observer = new IntersectionObserver(([entry]) => {
                if (!entry.isIntersecting && entry.boundingClientRect.bottom <= 0) {
                    observer.disconnect();
                    showVoucher();
                }
            });
            observer.observe(hero);
        }, Math.max(0, Number(welcomeVoucher.dataset.delay) || 0) * 1000);
    }
    welcomeVoucher.querySelector('[data-close-welcome]')?.addEventListener('click', () => welcomeVoucher.close());
    welcomeVoucher.addEventListener('click', event => {
        if (event.target === welcomeVoucher) welcomeVoucher.close();
    });
    welcomeVoucher.querySelector('[data-copy-voucher]')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        const label = button.querySelector('small');
        try {
            await navigator.clipboard.writeText(button.dataset.copyVoucher);
            label.textContent = 'KODE TERSALIN';
        } catch (error) {
            label.textContent = button.dataset.copyVoucher;
        }
    });
}
})();
</script>
@stack('scripts')
<script>
document.querySelectorAll('form[data-submit-once]').forEach(form=>form.addEventListener('submit',event=>{
    if(event.defaultPrevented||!form.checkValidity()||form.dataset.submitting==='true'){
        if(form.dataset.submitting==='true')event.preventDefault();
        return;
    }
    form.dataset.submitting='true';
    form.setAttribute('aria-busy','true');
    form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button=>{
        button.disabled=true;
        button.dataset.originalText=button.textContent;
        button.textContent=button.dataset.submittingText||'Memproses…';
    });
}));
const pageAlert=document.querySelector('[data-focus-alert]');
if(pageAlert)requestAnimationFrame(()=>pageAlert.focus());
</script>
</body>
</html>
