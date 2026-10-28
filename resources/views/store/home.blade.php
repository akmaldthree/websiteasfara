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
    $bestCollections = $curated('home_best_slugs', $collections->sortByDesc(fn($item)=>(int)$item->sold_quantity));
    $saleCollections = $curated('home_sale_slugs', $collections->filter(fn($item)=>(int)$item->compare_at_price>(int)$item->variants->min('price')));
    $featuredCollection = $collections->firstWhere('slug', $shop['home_featured_slug'] ?? '') ?: $collections->first();
    $categorySlugs = collect(preg_split('/[,\r\n]+/', $shop['home_category_slugs'] ?? ''))->map(fn($slug)=>trim($slug))->filter();
    $categoryCollections = $categorySlugs->isEmpty() ? $collections->unique('category_id')->take(3) : $categorySlugs->map(fn($slug)=>$collections->firstWhere('slug',$slug))->filter()->unique('category_id')->take(3);
    $homeOrder = collect($shop['home_section_order'] ?? array_keys(config('storefront.home_sections')));
    $homePosition = fn(string $section) => (($position=$homeOrder->search($section)) === false ? 99 : $position) + 1;
    $heroSlides = collect([[
        'image' => !empty($shop['hero_image']) ? asset('storage/'.$shop['hero_image']) : asset('images/hero.svg'),
        'eyebrow' => $shop['hero_eyebrow'] ?? 'NEW COLLECTION',
        'title' => $shop['hero_title'] ?? '',
        'text' => $shop['hero_text'] ?? '',
        'url' => $shop['hero_button_url'] ?? route('catalog'),
        'label' => $shop['hero_button_label'] ?? 'Belanja sekarang',
        'align' => 'left', 'tone' => 'light', 'duration' => 6500, 'mobile_image' => null,
    ]])->concat($contentEntries->where('type', 'banner')->map(fn ($entry) => [
        'image' => $entry->image_url ?: (!empty($shop['hero_image']) ? asset('storage/'.$shop['hero_image']) : asset('images/hero.svg')),
        'eyebrow' => 'DTHREE EDIT',
        'title' => $entry->title,
        'text' => $entry->summary ?: $entry->body,
        'url' => $entry->link_url ?: route('catalog'),
        'label' => $entry->link_label ?: 'Lihat koleksi',
        'align' => collect($entry->blocks)->firstWhere('type','hero_options')['align'] ?? 'left',
        'tone' => collect($entry->blocks)->firstWhere('type','hero_options')['tone'] ?? 'light',
        'duration' => collect($entry->blocks)->firstWhere('type','hero_options')['duration'] ?? 6500,
        'mobile_image' => collect($entry->blocks)->firstWhere('type','hero_options')['mobile_url'] ?? null,
    ])->values());
@endphp
@push('head')<style>.home-transparent-header #main{display:flex;flex-direction:column}</style>@endpush
@php($heroAutoplayMs=(int)round((float)($shop['hero_autoplay_seconds'] ?? 5)*1000))
<section class="commerce-hero hero-content-{{ $shop['hero_content_style'] ?? 'editorial' }}" style="order:{{ $homePosition('hero') }}" data-hero-slider aria-roledescription="carousel">
    @foreach($heroSlides as $slide)
        <article class="commerce-hero-slide hero-align-{{ $slide['align'] }} hero-tone-{{ $slide['tone'] }} {{ $loop->first?'is-active':'' }}" data-hero-slide data-duration="{{ $heroAutoplayMs }}" aria-hidden="{{ $loop->first?'false':'true' }}">
            <picture>@if($slide['mobile_image'])<source media="(max-width:767px)" srcset="{{ $slide['mobile_image'] }}">@endif<img src="{{ $slide['image'] }}" alt="{{ $slide['title'] }}"></picture>
            <div class="commerce-hero-overlay"></div>
            <div class="container-xxl commerce-hero-copy"><span>{{ $slide['eyebrow'] }}</span><h1>{{ $slide['title'] }}</h1><p>{{ $slide['text'] }}</p><a class="btn btn-light" href="{{ $slide['url'] }}">{{ strtoupper($slide['label']) }}</a></div>
        </article>
    @endforeach
    @if($heroSlides->count()>1)<div class="hero-dots" aria-label="Posisi banner">@foreach($heroSlides as $slide)<span class="{{ $loop->first?'is-active':'' }}" data-hero-dot="{{ $loop->index }}" aria-hidden="true"></span>@endforeach</div>@endif
    <a class="hero-next-section" href="#featured" aria-label="Ke produk unggulan">↓</a>
</section>
@if(($shop['home_featured_enabled'] ?? true) && ($featured=$featuredCollection))
@php($featuredVariants=$featured->variants->where('fulfillment','!=','custom_made')->filter(fn($variant)=>$variant->available>0))
@php($featuredImages=$featured->media->pluck('image_url')->prepend($featured->image_url)->filter()->unique()->values())
@php($featuredDefault=$featuredVariants->first())
<section class="featured-product-home container-xxl reveal" id="featured" style="order:{{ $homePosition('featured') }}">
    <h2>{{ $storeCopy['featured_product'] }}</h2>
    <div class="row g-0">
        <div class="col-lg-7 featured-product-photo"><img src="{{ $featuredImages->first() }}" alt="{{ $featured->name }}" data-featured-image><button type="button" class="featured-gallery-arrow is-prev" data-featured-prev aria-label="Foto sebelumnya">‹</button><button type="button" class="featured-gallery-arrow is-next" data-featured-next aria-label="Foto berikutnya">›</button><div class="featured-gallery-nav">@foreach($featuredImages as $image)<button type="button" class="{{ $loop->first?'is-active':'' }}" data-featured-image-choice="{{ $image }}" aria-label="Foto {{ $loop->iteration }}"><img src="{{ $image }}" alt="" loading="lazy"></button>@endforeach</div></div>
        <div class="col-lg-5 featured-product-info"><span>{{ strtoupper($featured->category->name) }}</span><h3><a href="{{ route('product',$featured->slug) }}">{{ strtoupper($featured->name) }}</a></h3><strong>@rupiah($featured->variants->min('price'))</strong><p>{{ $featured->description }}</p>
            <form method="post" action="{{ route('cart.add') }}" data-featured-form>@csrf<div class="featured-options">@if($featured->uses_product_type)<small>JENIS PRODUK</small><div>@foreach($featuredVariants->pluck('product_type')->filter()->unique() as $type)<button type="button" @class(['is-active'=>mb_strtolower($featuredDefault?->product_type ?? '')===mb_strtolower($type)]) data-featured-kind="type" data-value="{{ mb_strtolower($type) }}">{{ $type }}</button>@endforeach</div>@endif<small>WARNA</small><div>@foreach($featuredVariants->pluck('color')->unique()->take(5) as $color)<button type="button" @class(['color-choice','is-active'=>mb_strtolower($featuredDefault?->color ?? '')===mb_strtolower($color)]) data-featured-kind="color" data-value="{{ mb_strtolower($color) }}"><i style="--swatch:{{ \App\Support\ColorSwatch::hex($color) }}"></i>{{ $color }}</button>@endforeach</div><small>UKURAN</small><div>@foreach($featuredVariants->pluck('size')->unique()->take(6) as $size)<button type="button" @class(['is-active'=>mb_strtolower($featuredDefault?->size ?? '')===mb_strtolower($size)]) data-featured-kind="size" data-value="{{ mb_strtolower($size) }}">{{ $size }}</button>@endforeach</div></div><select class="visually-hidden" name="variant_id" data-featured-select required><option value="">Pilih varian</option>@foreach($featuredVariants as $variant)<option value="{{ $variant->id }}" data-type="{{ mb_strtolower($variant->product_type) }}" data-color="{{ mb_strtolower($variant->color) }}" data-size="{{ mb_strtolower($variant->size) }}" @selected($featuredDefault?->id===$variant->id)>{{ $variant->label }}</option>@endforeach</select><div class="featured-quantity"><label>JUMLAH</label><div class="quantity-stepper"><button type="button" data-featured-qty="-1">−</button><input type="number" name="quantity" value="1" min="1" max="100" data-featured-quantity><button type="button" data-featured-qty="1">+</button></div></div><div class="featured-actions"><button class="btn btn-dark" @disabled(!$featuredDefault) data-featured-submit>ADD TO CART</button><button class="btn btn-outline-dark" name="buy_now" value="1" @disabled(!$featuredDefault) data-featured-submit>BELI SEKARANG</button></div></form>
        </div>
    </div>
</section>
@endif

@if($shop['home_categories_enabled'] ?? true)<section class="category-media-grid container-xxl reveal" style="order:{{ $homePosition('categories') }}">
    @foreach($categoryCollections as $categoryProduct)<a href="{{ route('catalog',['category'=>$categoryProduct->category->slug]) }}"><img src="{{ $categoryProduct->image_url }}" alt="{{ $categoryProduct->category->name }}"><span>{{ strtoupper($categoryProduct->category->name) }}</span></a>@endforeach
</section>@endif

@if($shop['home_collections_enabled'] ?? true)<section class="commerce-section container-xxl reveal" style="order:{{ $homePosition('collections') }}">
    <div class="commerce-heading"><h2>{{ $storeCopy['featured_collection'] }}</h2><a href="{{ route('catalog') }}">{{ $storeCopy['view_all'] }}</a></div>
    <div class="collection-tabs" role="tablist"><button class="is-active" type="button" data-collection-tab="new">{{ $storeCopy['new_arrival'] }}</button><button type="button" data-collection-tab="best">{{ $storeCopy['best_seller'] }}</button></div>
    <div class="collection-pane is-active" data-collection-pane="new"><div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@forelse($newCollections as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@empty<div class="empty-state"><h3>Koleksi sedang disiapkan.</h3></div>@endforelse</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div></div>
    <div class="collection-pane" data-collection-pane="best"><div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@foreach($bestCollections as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div></div>
</section>@endif

@php($looks=$contentEntries->where('type','home_section')->filter(fn($entry)=>collect($entry->blocks)->contains('type','product_hotspot'))->values())
@if(($shop['home_look_enabled'] ?? true) && $looks->isNotEmpty())
<section class="shop-look-carousel reveal" style="order:{{ $homePosition('look') }}" data-look-carousel>
    <header><span>{{ $storeCopy['look_eyebrow'] }}</span><h2>{{ $storeCopy['look_title'] }}</h2></header>
    @foreach($looks as $look)<article class="shop-look {{ $loop->first?'is-active':'' }}" data-look-slide aria-hidden="{{ $loop->first?'false':'true' }}">
        <div class="shop-look-copy"><span>SHOP THE LOOK</span><h2>{{ $look->title }}</h2><p>{{ $look->summary ?: \Illuminate\Support\Str::limit($look->body,180) }}</p><div class="look-product-list">@foreach(collect($look->blocks)->where('type','product_hotspot')->pluck('series_id')->unique() as $lookSeriesId)@if($lookProduct=$lookProducts->get($lookSeriesId))<a href="{{ route('product',$lookProduct->slug) }}"><img src="{{ $lookProduct->image_url }}" alt="{{ $lookProduct->name }}"><span><b>{{ $lookProduct->name }}</b><small>@rupiah($lookProduct->variants_min_price)</small></span><i>→</i></a>@endif @endforeach</div><div class="look-product-detail" data-look-detail><small>{{ $storeCopy['look_instruction'] }}</small><h3>Temukan setiap produk dalam tampilan ini.</h3></div><a class="btn btn-outline-dark" href="{{ $look->link_url ?: route('catalog') }}">{{ $storeCopy['look_cta'] }}</a></div>
        <div class="shop-look-image"><img src="{{ $look->image_url ?: $collections->first()?->image_url }}" alt="{{ $look->title }}">@foreach(collect($look->blocks)->where('type','product_hotspot') as $hotspot)@if($product=$lookProducts->get($hotspot['series_id']??null))<button type="button" class="look-hotspot" style="left:{{ (float)($hotspot['x']??50) }}%;top:{{ (float)($hotspot['y']??50) }}%" data-look-product="look-product-{{ $product->id }}" data-look-source="look-product-{{ $look->id }}-{{ $product->id }}" aria-label="Lihat {{ $product->name }}">+</button><script type="application/json" id="look-product-{{ $look->id }}-{{ $product->id }}">{!! json_encode(['name'=>$product->name,'url'=>route('product',$product->slug),'image'=>$product->image_url,'price'=>'Rp '.number_format((int)$product->variants_min_price,0,',','.')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endif @endforeach</div>
    </article>@endforeach
    @if($looks->count()>1)<div class="look-navigation"><button type="button" data-look-prev aria-label="Tampilan sebelumnya">←</button><span><b data-look-current>1</b> / {{ $looks->count() }}</span><button type="button" data-look-next aria-label="Tampilan berikutnya">→</button></div>@endif
</section>
@endif

@if(($shop['home_sale_enabled'] ?? true) && $saleCollections->isNotEmpty())<section class="commerce-section sale-section container-xxl reveal" style="order:{{ $homePosition('sale') }}"><div class="commerce-heading"><h2>{{ $storeCopy['sale_title'] }}</h2><a href="{{ route('catalog',['sale'=>1]) }}">{{ $storeCopy['view_all'] }}</a></div><div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@foreach($saleCollections as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div></section>@endif

@if($shop['home_editorial_sections_enabled'] ?? false)
@foreach($contentEntries->where('type','home_section')->reject(fn($entry)=>$looks->contains('id',$entry->id)) as $entry)
<section class="commerce-campaign reveal {{ $loop->even?'commerce-campaign-reverse':'' }}" style="order:{{ $homePosition('editorial') }}">
    @if($entry->image_url)<div class="commerce-campaign-image"><img src="{{ $entry->image_url }}" alt="{{ $entry->title }}"></div>@endif
    <div class="commerce-campaign-copy"><span>{{ strtoupper(str_replace('_',' ',$entry->type)) }}</span><h2>{{ $entry->title }}</h2><p>{{ $entry->summary ?: \Illuminate\Support\Str::limit($entry->body,300) }}</p>@if($entry->link_url)<a class="btn btn-outline-dark" href="{{ $entry->link_url }}">{{ $entry->link_label ?: 'Selengkapnya' }}</a>@endif</div>
</section>
@endforeach
@endif

@if(($shop['home_story_enabled'] ?? false) && (!empty($shop['story_title']) || !empty($shop['story_text'])))
<section class="commerce-story" style="order:{{ $homePosition('story') }}"><div><span>{{ $shop['story_eyebrow'] ?? 'DTHREE' }}</span><h2>{{ $shop['story_title'] ?? '' }}</h2><p>{{ $shop['story_text'] ?? '' }}</p><a href="{{ route('catalog') }}">{{ $storeCopy['story_cta'] }}</a></div></section>
@endif
<script>
document.addEventListener('DOMContentLoaded',()=>{const look=document.querySelector('[data-look-carousel]');if(!look)return;let startX=0,timer;const restart=()=>{clearInterval(timer);if(!matchMedia('(prefers-reduced-motion: reduce)').matches)timer=setInterval(()=>document.querySelector('[data-look-next]')?.click(),7000)};look.addEventListener('pointerdown',event=>startX=event.clientX);look.addEventListener('pointerup',event=>{const delta=event.clientX-startX;if(Math.abs(delta)>55)document.querySelector(delta<0?'[data-look-next]':'[data-look-prev]')?.click();restart()});look.addEventListener('mouseenter',()=>clearInterval(timer));look.addEventListener('mouseleave',restart);restart()});
</script>
@endsection
@push('scripts')<script>document.querySelectorAll('[data-collection-tab]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-collection-tab]').forEach(item=>item.classList.toggle('is-active',item===button));document.querySelectorAll('[data-collection-pane]').forEach(pane=>pane.classList.toggle('is-active',pane.dataset.collectionPane===button.dataset.collectionTab))}));const featuredSelect=document.querySelector('[data-featured-select]');const featuredSelected={};const featuredOption=featuredSelect?.selectedOptions[0];if(featuredOption?.value){featuredSelected.type=featuredOption.dataset.type;featuredSelected.color=featuredOption.dataset.color;featuredSelected.size=featuredOption.dataset.size}const updateFeatured=()=>{if(!featuredSelect)return;const options=[...featuredSelect.options].filter(option=>option.value);document.querySelectorAll('[data-featured-kind]').forEach(button=>{const kind=button.dataset.featuredKind;const possible=options.some(option=>option.dataset[kind]===button.dataset.value&&Object.entries(featuredSelected).every(([key,value])=>key===kind||!value||option.dataset[key]===value));button.disabled=!possible;button.classList.toggle('is-unavailable',!possible)});const match=options.find(option=>(!featuredSelected.type||option.dataset.type===featuredSelected.type)&&(!featuredSelected.color||option.dataset.color===featuredSelected.color)&&(!featuredSelected.size||option.dataset.size===featuredSelected.size));featuredSelect.value=match?.value||'';document.querySelectorAll('[data-featured-submit]').forEach(item=>item.disabled=!match)};document.querySelectorAll('[data-featured-kind]').forEach(button=>button.addEventListener('click',()=>{featuredSelected[button.dataset.featuredKind]=button.dataset.value;document.querySelectorAll('[data-featured-kind="'+button.dataset.featuredKind+'"]').forEach(item=>item.classList.toggle('is-active',item===button));updateFeatured()}));updateFeatured();const featuredChoices=[...document.querySelectorAll('[data-featured-image-choice]')];let featuredImageIndex=0;const showFeaturedImage=index=>{featuredImageIndex=(index+featuredChoices.length)%featuredChoices.length;const button=featuredChoices[featuredImageIndex];if(!button)return;document.querySelector('[data-featured-image]').src=button.dataset.featuredImageChoice;featuredChoices.forEach(item=>item.classList.toggle('is-active',item===button))};featuredChoices.forEach((button,index)=>button.addEventListener('click',()=>showFeaturedImage(index)));document.querySelector('[data-featured-prev]')?.addEventListener('click',()=>showFeaturedImage(featuredImageIndex-1));document.querySelector('[data-featured-next]')?.addEventListener('click',()=>showFeaturedImage(featuredImageIndex+1));const featuredQuantity=document.querySelector('[data-featured-quantity]');document.querySelectorAll('[data-featured-qty]').forEach(button=>button.addEventListener('click',()=>{featuredQuantity.value=Math.max(1,Math.min(100,Number(featuredQuantity.value)+Number(button.dataset.featuredQty)))}));const lookEsc=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));document.querySelectorAll('[data-look-slide]').forEach(slide=>slide.querySelectorAll('[data-look-product]').forEach(button=>button.addEventListener('click',()=>{const product=JSON.parse(document.getElementById(button.dataset.lookSource).textContent);slide.querySelectorAll('[data-look-product]').forEach(item=>item.classList.toggle('is-active',item===button));slide.querySelector('[data-look-detail]').innerHTML=`<img src="${lookEsc(product.image)}" alt=""><div><small>SHOP THE LOOK</small><h3>${lookEsc(product.name)}</h3><strong>${lookEsc(product.price)}</strong><a href="${lookEsc(product.url)}">LIHAT PRODUK →</a></div>`})));const lookSlides=[...document.querySelectorAll('[data-look-slide]')];let lookActive=0;const showLook=index=>{lookActive=(index+lookSlides.length)%lookSlides.length;lookSlides.forEach((slide,i)=>{slide.classList.toggle('is-active',i===lookActive);slide.setAttribute('aria-hidden',i===lookActive?'false':'true')});document.querySelector('[data-look-current]')?.replaceChildren(document.createTextNode(String(lookActive+1)))};document.querySelector('[data-look-prev]')?.addEventListener('click',()=>showLook(lookActive-1));document.querySelector('[data-look-next]')?.addEventListener('click',()=>showLook(lookActive+1));</script>@endpush
