@php($themeFonts=['classic'=>['Arial,Helvetica,sans-serif',"Georgia,'Times New Roman',serif"],'modern'=>["'Trebuchet MS',Arial,sans-serif","'Trebuchet MS',Arial,sans-serif"],'system'=>['system-ui,-apple-system,sans-serif','system-ui,-apple-system,sans-serif']])
@php($themeFont=$themeFonts[$shop['theme_font'] ?? 'classic'] ?? $themeFonts['classic'])
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="@yield('description',$shop['default_seo_description'] ?? 'Dthree — koleksi busana muslim dan muslimah.')">
    <title>@yield('title',$shop['default_seo_title'] ?? 'Dthree — Dalam setiap cerita')</title>
    <link rel="canonical" href="@yield('canonical',url()->current())">
    @if(!empty($shop['favicon']))<link rel="icon" href="{{ asset('storage/'.$shop['favicon']) }}">@endif
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dthree.css') }}">
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">
    <style>:root{--ink:{{ $shop['theme_primary_color'] ?? '#252652' }};--gold:{{ $shop['theme_accent_color'] ?? '#b48249' }};--paper:{{ $shop['theme_background_color'] ?? '#ffffff' }};--surface:{{ $shop['theme_surface_color'] ?? '#ffffff' }};--text:{{ $shop['theme_text_color'] ?? '#252652' }};--theme-radius:{{ (int)($shop['theme_radius'] ?? 8) }}px;--theme-container:{{ (int)($shop['theme_container_width'] ?? 1440) }}px;--body-font:{!! $themeFont[0] !!};--heading-font:{!! $themeFont[1] !!}}body{color:var(--text);font-family:var(--body-font)}h1,h2,h3,.wordmark{font-family:var(--heading-font)}.container-xxl{max-width:var(--theme-container)}.panel,.btn,.form-control,.form-select,.alert,.product-image{border-radius:var(--theme-radius)}.panel,.form-control,.form-select{background-color:var(--surface)}.wordmark img{display:block;max-width:160px;max-height:58px;object-fit:contain}</style>
    @stack('head')
</head>
<body class="store-body theme-header-{{ $shop['theme_header_style'] ?? 'standard' }} @yield('body_class')">
<a href="#main" class="visually-hidden-focusable skip-link">Langsung ke konten</a>
@if($shop['header_announcement_enabled'] ?? true)<div class="announcement">{{ $shop['tagline'] ?? '' }}</div>@endif
<header @class(['site-header','sticky-top'=>($shop['header_sticky'] ?? true)])>
    <div class="container-xxl header-row">
        <button class="menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#mobile-nav" aria-label="Buka menu" aria-controls="mobile-nav" aria-expanded="false"><span></span><span></span><span></span></button>
        <a class="wordmark" href="{{ route('home') }}">
            @if(!empty($shop['logo']))<img src="{{ asset('storage/'.$shop['logo']) }}" alt="{{ $shop['brand_name'] ?? 'Dthree' }}">
            @else{{ strtolower($shop['brand_name'] ?? 'Dthree') }}<span>{{ $shop['brand_subtitle'] ?? 'MODEST WEAR' }}</span>@endif
        </a>
        <nav class="desktop-nav" aria-label="Utama">
            <a href="{{ route('home') }}">HOME</a>
            <a href="{{ route('catalog') }}">CATALOG</a>
            @if($shop['header_categories_enabled'] ?? true)@foreach($categories->take(2) as $category)<a href="{{ route('catalog',['category'=>$category->slug]) }}">{{ strtoupper($category->name) }}</a>@endforeach @endif
            @foreach($cmsMenus->take(2) as $menu)<a href="{{ $menu->link_url ?: '#' }}">{{ strtoupper($menu->title) }}</a>@endforeach
        </nav>
        <div class="header-actions">
            <a href="{{ route('catalog') }}" aria-label="Cari produk"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></a>
            @auth<a href="{{ route('account') }}" aria-label="Wishlist dan akun"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5a5.5 5.5 0 0 0 1-8.9z"/></svg></a>@endauth
            <a href="{{ route('cart') }}" aria-label="Keranjang"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l2 14H3L5 8zM8 8V5a4 4 0 0 1 8 0v3"/></svg>@if(array_sum(session('cart',[]))>0)<span>{{ array_sum(session('cart',[])) }}</span>@endif</a>
            <a href="{{ route('account') }}" aria-label="Akun saya"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/></svg></a>
        </div>
    </div>
    <nav class="collapse mobile-nav" id="mobile-nav">
        <a href="{{ route('home') }}">HOME</a><a href="{{ route('catalog') }}">CATALOG</a>
        @if($shop['header_categories_enabled'] ?? true)@foreach($categories as $category)<a href="{{ route('catalog',['category'=>$category->slug]) }}">{{ $category->name }}</a>@endforeach @endif
        @foreach($cmsMenus as $menu)<a href="{{ $menu->link_url ?: '#' }}">{{ $menu->title }}</a>@endforeach
        @if($shop['header_tracking_enabled'] ?? true)<a href="{{ route('track') }}">Lacak pesanan</a>@endif
    </nav>
</header>
<main id="main">@include('partials.alerts')@yield('content')</main>
@if(!empty($shop['whatsapp']))<a class="floating-whatsapp" href="https://wa.me/{{ $shop['whatsapp'] }}" target="_blank" rel="noopener" aria-label="Chat Dthree melalui WhatsApp">WA</a>@endif
<footer class="site-footer">
    <div class="container-xxl">
        <div class="row gy-5">
            <div class="col-lg-4"><a class="wordmark" href="{{ route('home') }}">{{ strtolower($shop['brand_name'] ?? 'Dthree') }}<span>{{ $shop['brand_subtitle'] ?? 'MODEST WEAR' }}</span></a><p class="mt-4">{{ $shop['footer_text'] ?? $shop['story_text'] ?? '' }}</p><small>{{ $shop['store_location'] ?? '' }}</small></div>
            <div class="col-6 col-lg-2"><h2>Belanja</h2><a href="{{ route('catalog') }}">Semua koleksi</a>@foreach($categories->take(4) as $category)<a href="{{ route('catalog',['category'=>$category->slug]) }}">{{ $category->name }}</a>@endforeach</div>
            <div class="col-6 col-lg-3"><h2>Bantuan</h2><a href="{{ route('account') }}">Akun saya</a>@if($shop['header_tracking_enabled'] ?? true)<a href="{{ route('track') }}">Lacak pesanan</a>@endif @if($shop['footer_pages_enabled'] ?? true)@foreach(\App\Models\Page::published()->get() as $p)<a href="{{ route('page',$p->slug) }}">{{ $p->title }}</a>@endforeach @endif</div>
            <div class="col-lg-3"><h2>Terhubung dengan Dthree</h2><p class="small">Dapatkan kabar koleksi, inspirasi gaya, dan penawaran terbaru.</p>@if($shop['footer_social_enabled'] ?? true)@if(!empty($shop['whatsapp']))<a href="https://wa.me/{{ $shop['whatsapp'] }}" target="_blank" rel="noopener">WhatsApp ↗</a>@endif @foreach(['instagram_url'=>'Instagram','tiktok_url'=>'TikTok','facebook_url'=>'Facebook'] as $key=>$label)@if(!empty($shop[$key]))<a href="{{ $shop[$key] }}" target="_blank" rel="noopener">{{ $label }} ↗</a>@endif @endforeach @endif</div>
        </div>
        <div class="footer-bottom">© {{ date('Y') }} {{ $shop['brand_name'] ?? 'Dthree' }} {{ $shop['footer_copyright'] ?? '' }} <span>Pembayaran aman · Privasi terlindungi</span></div>
    </div>
</footer>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
