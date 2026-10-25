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
<section class="commerce-hero" data-hero-slider aria-roledescription="carousel">
    @foreach($heroSlides as $slide)
        <article class="commerce-hero-slide hero-align-{{ $slide['align'] }} hero-tone-{{ $slide['tone'] }} {{ $loop->first?'is-active':'' }}" data-hero-slide data-duration="{{ $slide['duration'] }}" aria-hidden="{{ $loop->first?'false':'true' }}">
            <picture>@if($slide['mobile_image'])<source media="(max-width:767px)" srcset="{{ $slide['mobile_image'] }}">@endif<img src="{{ $slide['image'] }}" alt="{{ $slide['title'] }}"></picture>
            <div class="commerce-hero-overlay"></div>
            <div class="container-xxl commerce-hero-copy"><span>{{ $slide['eyebrow'] }}</span><h1>{{ $slide['title'] }}</h1><p>{{ $slide['text'] }}</p><a class="btn btn-light" href="{{ $slide['url'] }}">{{ strtoupper($slide['label']) }}</a></div>
        </article>
    @endforeach
    @if($heroSlides->count()>1)<button class="hero-arrow hero-arrow-prev" type="button" data-hero-prev aria-label="Banner sebelumnya">‹</button><button class="hero-arrow hero-arrow-next" type="button" data-hero-next aria-label="Banner berikutnya">›</button><div class="hero-dots" aria-label="Pilih banner">@foreach($heroSlides as $slide)<button type="button" class="{{ $loop->first?'is-active':'' }}" data-hero-dot="{{ $loop->index }}" aria-label="Banner {{ $loop->iteration }}"></button>@endforeach</div>@endif
    <a class="hero-next-section" href="#featured" aria-label="Ke produk unggulan">↓</a>
</section>
<div class="benefit-strip"><div class="container-xxl">@foreach(['benefit_1','benefit_2','benefit_3'] as $key)@if(!empty($shop[$key]))<span>{{ $shop[$key] }}</span>@endif @endforeach</div></div>

@if($featured=$collections->first())
@php($featuredVariants=$featured->variants->where('fulfillment','!=','custom_made')->filter(fn($variant)=>$variant->available>0))
@php($featuredImages=$featured->media->pluck('image_url')->prepend($featured->image_url)->filter()->unique()->values())
<section class="featured-product-home container-xxl reveal" id="featured">
    <h2>FEATURED PRODUCT</h2>
    <div class="row g-0">
        <div class="col-lg-7 featured-product-photo"><img src="{{ $featuredImages->first() }}" alt="{{ $featured->name }}" data-featured-image><button type="button" class="featured-gallery-arrow is-prev" data-featured-prev aria-label="Foto sebelumnya">‹</button><button type="button" class="featured-gallery-arrow is-next" data-featured-next aria-label="Foto berikutnya">›</button><div class="featured-gallery-nav">@foreach($featuredImages as $image)<button type="button" class="{{ $loop->first?'is-active':'' }}" data-featured-image-choice="{{ $image }}" aria-label="Foto {{ $loop->iteration }}"></button>@endforeach</div></div>
        <div class="col-lg-5 featured-product-info"><span>{{ strtoupper($featured->category->name) }}</span><h3><a href="{{ route('product',$featured->slug) }}">{{ strtoupper($featured->name) }}</a></h3><strong>@rupiah($featured->variants->min('price'))</strong><p>{{ $featured->description }}</p>
            <form method="post" action="{{ route('cart.add') }}" data-featured-form>@csrf<div class="featured-options">@if($featured->uses_product_type)<small>JENIS PRODUK</small><div>@foreach($featuredVariants->pluck('product_type')->filter()->unique() as $type)<button type="button" data-featured-kind="type" data-value="{{ mb_strtolower($type) }}">{{ $type }}</button>@endforeach</div>@endif<small>WARNA</small><div>@foreach($featuredVariants->pluck('color')->unique()->take(5) as $color)<button type="button" class="color-choice" data-featured-kind="color" data-value="{{ mb_strtolower($color) }}"><i style="--swatch:{{ \App\Support\ColorSwatch::hex($color) }}"></i>{{ $color }}</button>@endforeach</div><small>UKURAN</small><div>@foreach($featuredVariants->pluck('size')->unique()->take(6) as $size)<button type="button" data-featured-kind="size" data-value="{{ mb_strtolower($size) }}">{{ $size }}</button>@endforeach</div></div><select class="visually-hidden" name="variant_id" data-featured-select required><option value="">Pilih varian</option>@foreach($featuredVariants as $variant)<option value="{{ $variant->id }}" data-type="{{ mb_strtolower($variant->product_type) }}" data-color="{{ mb_strtolower($variant->color) }}" data-size="{{ mb_strtolower($variant->size) }}">{{ $variant->label }}</option>@endforeach</select><div class="featured-quantity"><label>JUMLAH</label><div class="quantity-stepper"><button type="button" data-featured-qty="-1">−</button><input type="number" name="quantity" value="1" min="1" max="100" data-featured-quantity><button type="button" data-featured-qty="1">+</button></div></div><div class="featured-actions"><button class="btn btn-dark" disabled data-featured-submit>ADD TO CART</button><button class="btn btn-outline-dark" name="buy_now" value="1" disabled data-featured-submit>BELI SEKARANG</button></div></form>
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
    <div class="collection-pane is-active" data-collection-pane="new"><div class="product-carousel" data-drag-scroll>@forelse($newCollections as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@empty<div class="empty-state"><h3>Koleksi sedang disiapkan.</h3></div>@endforelse</div></div>
    <div class="collection-pane" data-collection-pane="best"><div class="product-carousel" data-drag-scroll>@foreach($bestCollections as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div></div>
</section>

@php($looks=$contentEntries->where('type','home_section')->filter(fn($entry)=>collect($entry->blocks)->contains('type','product_hotspot'))->values())
@if($looks->isNotEmpty())
<section class="shop-look-carousel reveal" data-look-carousel>
    <header><span>STEAL THE LOOK!</span><h2>BEST COMBINATION</h2></header>
    @foreach($looks as $look)<article class="shop-look {{ $loop->first?'is-active':'' }}" data-look-slide aria-hidden="{{ $loop->first?'false':'true' }}">
        <div class="shop-look-copy"><span>SHOP THE LOOK</span><h2>{{ $look->title }}</h2><p>{{ $look->summary ?: \Illuminate\Support\Str::limit($look->body,180) }}</p><div class="look-product-list">@foreach(collect($look->blocks)->where('type','product_hotspot')->pluck('series_id')->unique() as $lookSeriesId)@if($lookProduct=$lookProducts->get($lookSeriesId))<a href="{{ route('product',$lookProduct->slug) }}"><img src="{{ $lookProduct->image_url }}" alt="{{ $lookProduct->name }}"><span><b>{{ $lookProduct->name }}</b><small>@rupiah($lookProduct->variants_min_price)</small></span><i>→</i></a>@endif @endforeach</div><div class="look-product-detail" data-look-detail><small>PILIH TANDA + PADA FOTO</small><h3>Temukan setiap produk dalam tampilan ini.</h3></div><a class="btn btn-outline-dark" href="{{ $look->link_url ?: route('catalog') }}">VIEW PRODUCTS</a></div>
        <div class="shop-look-image"><img src="{{ $look->image_url ?: $collections->first()?->image_url }}" alt="{{ $look->title }}">@foreach(collect($look->blocks)->where('type','product_hotspot') as $hotspot)@if($product=$lookProducts->get($hotspot['series_id']??null))<button type="button" class="look-hotspot" style="left:{{ (float)($hotspot['x']??50) }}%;top:{{ (float)($hotspot['y']??50) }}%" data-look-product="look-product-{{ $product->id }}" data-look-source="look-product-{{ $look->id }}-{{ $product->id }}" aria-label="Lihat {{ $product->name }}">+</button><script type="application/json" id="look-product-{{ $look->id }}-{{ $product->id }}">{!! json_encode(['name'=>$product->name,'url'=>route('product',$product->slug),'image'=>$product->image_url,'price'=>'Rp '.number_format((int)$product->variants_min_price,0,',','.')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endif @endforeach</div>
    </article>@endforeach
    @if($looks->count()>1)<div class="look-navigation"><button type="button" data-look-prev aria-label="Tampilan sebelumnya">←</button><span><b data-look-current>1</b> / {{ $looks->count() }}</span><button type="button" data-look-next aria-label="Tampilan berikutnya">→</button></div>@endif
</section>
@endif

@if($saleCollections->isNotEmpty())<section class="commerce-section sale-section container-xxl reveal"><div class="commerce-heading"><h2>SALE</h2><a href="{{ route('catalog',['sale'=>1]) }}">VIEW ALL</a></div><div class="product-carousel" data-drag-scroll>@foreach($saleCollections as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div></section>@endif

@foreach($contentEntries->where('type','home_section')->reject(fn($entry)=>$looks->contains('id',$entry->id)) as $entry)
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
@push('scripts')<script>document.querySelectorAll('[data-collection-tab]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-collection-tab]').forEach(item=>item.classList.toggle('is-active',item===button));document.querySelectorAll('[data-collection-pane]').forEach(pane=>pane.classList.toggle('is-active',pane.dataset.collectionPane===button.dataset.collectionTab))}));const featuredSelected={};const featuredSelect=document.querySelector('[data-featured-select]');document.querySelectorAll('[data-featured-kind]').forEach(button=>button.addEventListener('click',()=>{featuredSelected[button.dataset.featuredKind]=button.dataset.value;document.querySelectorAll('[data-featured-kind="'+button.dataset.featuredKind+'"]').forEach(item=>item.classList.toggle('is-active',item===button));const match=[...featuredSelect.options].find(option=>option.value&&(!featuredSelected.type||option.dataset.type===featuredSelected.type)&&(!featuredSelected.color||option.dataset.color===featuredSelected.color)&&(!featuredSelected.size||option.dataset.size===featuredSelected.size));featuredSelect.value=match?.value||'';document.querySelectorAll('[data-featured-submit]').forEach(item=>item.disabled=!match)}));const featuredChoices=[...document.querySelectorAll('[data-featured-image-choice]')];let featuredImageIndex=0;const showFeaturedImage=index=>{featuredImageIndex=(index+featuredChoices.length)%featuredChoices.length;const button=featuredChoices[featuredImageIndex];if(!button)return;document.querySelector('[data-featured-image]').src=button.dataset.featuredImageChoice;featuredChoices.forEach(item=>item.classList.toggle('is-active',item===button))};featuredChoices.forEach((button,index)=>button.addEventListener('click',()=>showFeaturedImage(index)));document.querySelector('[data-featured-prev]')?.addEventListener('click',()=>showFeaturedImage(featuredImageIndex-1));document.querySelector('[data-featured-next]')?.addEventListener('click',()=>showFeaturedImage(featuredImageIndex+1));const featuredQuantity=document.querySelector('[data-featured-quantity]');document.querySelectorAll('[data-featured-qty]').forEach(button=>button.addEventListener('click',()=>{featuredQuantity.value=Math.max(1,Math.min(100,Number(featuredQuantity.value)+Number(button.dataset.featuredQty)))}));const lookEsc=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));document.querySelectorAll('[data-look-slide]').forEach(slide=>slide.querySelectorAll('[data-look-product]').forEach(button=>button.addEventListener('click',()=>{const product=JSON.parse(document.getElementById(button.dataset.lookSource).textContent);slide.querySelectorAll('[data-look-product]').forEach(item=>item.classList.toggle('is-active',item===button));slide.querySelector('[data-look-detail]').innerHTML=`<img src="${lookEsc(product.image)}" alt=""><div><small>SHOP THE LOOK</small><h3>${lookEsc(product.name)}</h3><strong>${lookEsc(product.price)}</strong><a href="${lookEsc(product.url)}">LIHAT PRODUK →</a></div>`})));const lookSlides=[...document.querySelectorAll('[data-look-slide]')];let lookActive=0;const showLook=index=>{lookActive=(index+lookSlides.length)%lookSlides.length;lookSlides.forEach((slide,i)=>{slide.classList.toggle('is-active',i===lookActive);slide.setAttribute('aria-hidden',i===lookActive?'false':'true')});document.querySelector('[data-look-current]')?.replaceChildren(document.createTextNode(String(lookActive+1)))};document.querySelector('[data-look-prev]')?.addEventListener('click',()=>showLook(lookActive-1));document.querySelector('[data-look-next]')?.addEventListener('click',()=>showLook(lookActive+1));</script>@endpush
