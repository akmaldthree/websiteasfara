<!doctype html>
<html lang="id" class="admin-document">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>@yield('title','Dashboard') · Dthree Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ filemtime(public_path('vendor/bootstrap/bootstrap.min.css')) }}"><link rel="stylesheet" href="{{ asset('css/dthree.css') }}?v={{ filemtime(public_path('css/dthree.css')) }}">
    <style>:root{--palette-sage:{{ \App\Models\Setting::get('theme_palette_base',config('theme.palette.theme_palette_base.color')) }};--palette-sand:{{ \App\Models\Setting::get('theme_palette_surface',config('theme.palette.theme_palette_surface.color')) }};--palette-taupe:{{ \App\Models\Setting::get('theme_palette_structure',config('theme.palette.theme_palette_structure.color')) }};--palette-gold:{{ \App\Models\Setting::get('theme_palette_accent',config('theme.palette.theme_palette_accent.color')) }};--palette-cream:{{ \App\Models\Setting::get('theme_palette_base',config('theme.palette.theme_palette_base.color')) }};--ink:{{ \App\Models\Setting::get('theme_text_color',config('theme.text_color')) }};--gold:var(--palette-gold);--cream:var(--palette-cream);--paper:var(--palette-sage);--surface:var(--palette-sand);--line:var(--palette-taupe)}</style>
</head>
<body class="admin-body">
<a class="skip-link visually-hidden-focusable" href="#main-content">Lewati navigasi</a>
<div class="admin-shell">
<aside class="admin-sidebar" id="admin-sidebar">
    <div class="admin-sidebar-brand"><a class="wordmark" href="{{ route('admin.dashboard') }}">dthree<span>BUSINESS DESK</span></a><button class="admin-sidebar-close" type="button" aria-label="Tutup menu" data-sidebar-close hidden>×</button></div>
    <nav aria-label="Navigasi CMS">
        <div class="admin-nav-group"><p>Operasional</p>
            @foreach(['admin.dashboard'=>['Ringkasan','dashboard'],'admin.orders'=>['Pesanan','orders.read'],'admin.inventory'=>['Stok & inventori','inventory.read'],'admin.customers'=>['Pelanggan','customers.read'],'admin.cases'=>['Retur & layanan','orders.read'],'admin.notifications'=>['Notifikasi','cases.write'],'admin.reports'=>['Laporan','reports.read']] as $route=>$nav)
                @if(auth()->user()->allowed($nav[1]))<a href="{{ route($route) }}" @class(['active'=>request()->routeIs($route)]) @if(request()->routeIs($route)) aria-current="page" @endif>{{ $nav[0] }}</a>@endif
            @endforeach
        </div>
        @if(auth()->user()->allowed('catalog.write'))
        <div class="admin-nav-group"><p>Katalog produk</p>
            <a href="{{ route('admin.products') }}" @class(['active'=>request()->routeIs('admin.products*')]) @if(request()->routeIs('admin.products*')) aria-current="page" @endif>Kelola produk</a>
            @foreach(['categories','bundle-components','measurement-templates'] as $key)@php($item=config('admin_resources.'.$key))<a href="{{ route('admin.resources',$key) }}" @class(['active'=>request()->route('resource')===$key]) @if(request()->route('resource')===$key) aria-current="page" @endif>{{ $item['title'] }}</a>@endforeach
            <a href="{{ route('admin.custom-quotes') }}" @class(['active'=>request()->routeIs('admin.custom-quotes*')]) @if(request()->routeIs('admin.custom-quotes*')) aria-current="page" @endif>Penawaran custom</a>
        </div>
        @endif
        @if(auth()->user()->allowed('content.write') || auth()->user()->allowed('promotions.write'))
        <div class="admin-nav-group"><p>Website & promosi</p>
            @if(auth()->user()->allowed('content.write'))
                @foreach(['content','pages'] as $key)@php($item=config('admin_resources.'.$key))<a href="{{ route('admin.resources',$key) }}" @class(['active'=>request()->route('resource')===$key])>{{ $item['title'] }}</a>@endforeach
                <a href="{{ route('admin.media') }}" @class(['active'=>request()->routeIs('admin.media*')])>Pustaka media</a>
                <a href="{{ route('admin.reviews') }}" @class(['active'=>request()->routeIs('admin.reviews*')])>Ulasan & pertanyaan</a>
                @php($newsletter=config('admin_resources.newsletter'))<a href="{{ route('admin.resources','newsletter') }}" @class(['active'=>request()->route('resource')==='newsletter'])>{{ $newsletter['title'] }}</a>
                @php($locations=config('admin_resources.store-locations'))<a href="{{ route('admin.resources','store-locations') }}" @class(['active'=>request()->route('resource')==='store-locations'])>{{ $locations['title'] }}</a>
                @php($curated=config('admin_resources.curated-links'))<a href="{{ route('admin.resources','curated-links') }}" @class(['active'=>request()->route('resource')==='curated-links'])>{{ $curated['title'] }}</a>
            @endif
            @if(auth()->user()->allowed('promotions.write'))@php($item=config('admin_resources.coupons'))<a href="{{ route('admin.resources','coupons') }}" @class(['active'=>request()->route('resource')==='coupons'])>{{ $item['title'] }}</a>@endif
        </div>
        @endif
        @if(auth()->user()->allowed('catalog.write'))
        <div class="admin-nav-group"><p>Penjualan bisnis</p>
            @foreach(['price-groups','variant-prices','business-order-rules'] as $key)@php($item=config('admin_resources.'.$key))<a href="{{ route('admin.resources',$key) }}" @class(['active'=>request()->route('resource')===$key])>{{ $item['title'] }}</a>@endforeach
        </div>
        @endif
        @if(auth()->user()->allowed('settings.write'))
        <div class="admin-nav-group"><p>Pengiriman & kebijakan</p>
            @php($shipping=config('admin_resources.shipping-rates'))<a href="{{ route('admin.resources','shipping-rates') }}" @class(['active'=>request()->route('resource')==='shipping-rates'])>{{ $shipping['title'] }}</a>
            <a href="{{ route('admin.service.policy') }}" @class(['active'=>request()->routeIs('admin.service.policy*')])>Aturan retur & garansi</a>
        </div>
        @endif
        <div class="admin-nav-group"><p>Alat & pengaturan</p>
            @if(collect(['catalog.write','inventory.write','settings.write','promotions.write','content.write'])->contains(fn($permission)=>auth()->user()->allowed($permission)))<a href="{{ route('admin.import') }}" @class(['active'=>request()->routeIs('admin.import*')])>Import & export Excel</a>@endif
            @foreach(['admin.settings'=>['Pengaturan website','settings.write'],'admin.team'=>['Tim & hak akses','users.write'],'admin.security'=>['Keamanan akun','dashboard'],'admin.monitoring'=>['Monitoring sistem','audit.read'],'admin.audit'=>['Audit log','audit.read']] as $route=>$nav)@if(auth()->user()->allowed($nav[1]))<a href="{{ route($route) }}" @class(['active'=>request()->routeIs($route)])>{{ $nav[0] }}</a>@endif @endforeach
        </div>
    </nav>
    <footer>DTHREE OPERATIONS<br><span>Ruang kerja tim Anda.</span></footer>
</aside>
<div class="admin-sidebar-backdrop" data-sidebar-close hidden></div>
<div class="admin-main">
    <header class="admin-topbar"><div class="d-flex align-items-center gap-3"><button class="admin-menu-button" type="button" aria-label="Buka menu" aria-controls="admin-sidebar" aria-expanded="false" data-sidebar-open hidden>☰</button><div><small>CMS Dthree</small><strong>@yield('title','Ringkasan')</strong></div></div><div class="admin-topbar-actions"><a href="{{ route('home') }}" target="_blank" rel="noopener">Lihat website ↗</a><span class="admin-user">{{ auth()->user()->name }}<small>{{ auth()->user()->role }}</small></span><form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-dark btn-sm">Keluar</button></form></div></header>
    @include('partials.alerts')
    <main class="admin-content" id="main-content" tabindex="-1">@yield('content')</main>
</div>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ filemtime(public_path('vendor/bootstrap/bootstrap.bundle.min.js')) }}"></script>
<script>
const adminBody=document.body;
const adminSidebar=document.getElementById('admin-sidebar');
const menuButton=document.querySelector('[data-sidebar-open]');
const sidebarClose=document.querySelector('.admin-sidebar-close');
const sidebarBackdrop=document.querySelector('.admin-sidebar-backdrop');
const activeSidebarLink=adminSidebar?.querySelector('nav a.active');
const sidebarScrollKey='dthree-admin-sidebar-scroll';
const revealActiveSidebarLink=(center=false)=>{
    if(!adminSidebar||!activeSidebarLink)return;
    const sidebarBox=adminSidebar.getBoundingClientRect();
    const linkBox=activeSidebarLink.getBoundingClientRect();
    const linkTop=linkBox.top-sidebarBox.top+adminSidebar.scrollTop;
    const linkBottom=linkTop+linkBox.height;
    if(center||linkTop<adminSidebar.scrollTop||linkBottom>adminSidebar.scrollTop+adminSidebar.clientHeight){
        adminSidebar.scrollTop=Math.max(0,linkTop-(adminSidebar.clientHeight-linkBox.height)/2);
    }
};
const saveSidebarScroll=()=>{
    if(window.innerWidth>=901&&adminSidebar){
        try{sessionStorage.setItem(sidebarScrollKey,String(adminSidebar.scrollTop))}catch(error){}
    }
};
const restoreSidebarScroll=()=>{
    if(window.innerWidth<901||!adminSidebar)return;
    let savedPosition=null;
    try{savedPosition=sessionStorage.getItem(sidebarScrollKey)}catch(error){}
    if(savedPosition!==null&&Number.isFinite(Number(savedPosition))){
        adminSidebar.scrollTop=Number(savedPosition);
        revealActiveSidebarLink();
    }else revealActiveSidebarLink(true);
};
const syncAdminMenu=()=>{
    const mobile=window.innerWidth<901;
    menuButton.hidden=!mobile;
    sidebarClose.hidden=!mobile;
    if(!mobile){
        adminBody.classList.remove('admin-menu-open');
        sidebarBackdrop.hidden=true;
        adminSidebar?.removeAttribute('aria-hidden');
        if(adminSidebar)adminSidebar.inert=false;
        restoreSidebarScroll();
    }else if(!adminBody.classList.contains('admin-menu-open')){
        adminSidebar?.setAttribute('aria-hidden','true');
        if(adminSidebar)adminSidebar.inert=true;
    }
};
const closeMenu=(restoreFocus=false)=>{
    adminBody.classList.remove('admin-menu-open');
    sidebarBackdrop.hidden=true;
    adminSidebar?.setAttribute('aria-hidden','true');
    if(adminSidebar)adminSidebar.inert=true;
    menuButton?.setAttribute('aria-expanded','false');
    if(restoreFocus)menuButton?.focus();
};
menuButton?.addEventListener('click',()=>{
    const open=!adminBody.classList.contains('admin-menu-open');
    adminBody.classList.toggle('admin-menu-open',open);
    sidebarBackdrop.hidden=!open;
    menuButton.setAttribute('aria-expanded',open?'true':'false');
    if(open){
        adminSidebar?.removeAttribute('aria-hidden');
        if(adminSidebar)adminSidebar.inert=false;
        revealActiveSidebarLink(true);
        sidebarClose?.focus();
    }else closeMenu();
});
document.querySelectorAll('[data-sidebar-close]').forEach(button=>button.addEventListener('click',()=>closeMenu(true)));
document.querySelectorAll('.admin-sidebar nav a').forEach(link=>link.addEventListener('click',()=>{
    if(window.innerWidth<901)closeMenu();
    else saveSidebarScroll();
}));
adminSidebar?.addEventListener('scroll',saveSidebarScroll,{passive:true});
window.addEventListener('pagehide',saveSidebarScroll);
window.addEventListener('resize',syncAdminMenu);
document.addEventListener('keydown',event=>{
    if(event.key==='Escape'&&adminBody.classList.contains('admin-menu-open'))closeMenu(true);
});
document.addEventListener('click',event=>{
    document.querySelectorAll('.seller-mass-menu[open],.seller-row-actions details[open]').forEach(details=>{
        if(!details.contains(event.target))details.removeAttribute('open');
    });
});
restoreSidebarScroll();
syncAdminMenu();
const pageAlert=document.querySelector('[data-focus-alert]');
if(pageAlert)requestAnimationFrame(()=>pageAlert.focus());
</script>
@stack('scripts')
</body></html>
