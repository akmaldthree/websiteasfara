@extends('layouts.store')
@section('title',$series->seo_title ?: $series->name.' — '.($shop['brand_name'] ?? 'Asfara'))
@section('description',$series->seo_description ?: \Illuminate\Support\Str::limit($series->description ?? '',160))
@section('body_class','product-detail-body')
@php
    $previewSubmitUrl = ($productPreview ?? false) ? route('admin.products.preview', $series->slug) : null;
    $standardVariants = $series->variants->where('fulfillment', '!=', 'custom_made');
    $availableVariants = $standardVariants->filter(fn ($variant) => $variant->available > 0);
    $variantGroups = $series->uses_product_type ? ['type', 'color', 'size'] : ['color', 'size'];
    $requestedVariant = $standardVariants->firstWhere('id', request()->integer('variant'));
    $selectedVariant = $requestedVariant ?? $availableVariants->first();
    $canBuySelected = $selectedVariant && $selectedVariant->available > 0;
    $needsVariantSelection = $availableVariants->isNotEmpty() && (!$canBuySelected || (!$requestedVariant && $availableVariants->count() > 1));
    $displayPrice = $selectedVariant ? $unitPrices[$selectedVariant->id] : (int) $unitPrices->min();
    $compareAtPrice = (int) $series->compare_at_price;
    $saving = max(0, $compareAtPrice - $displayPrice);
    $stockLabel = match (true) {
        $canBuySelected && $selectedVariant->fulfillment === 'pre_order' => 'Pre-order tersedia',
        $canBuySelected => 'Stok tersedia',
        $selectedVariant !== null => 'Varian ini habis',
        $standardVariants->isEmpty() && $series->variants->contains('fulfillment', 'custom_made') => 'Dibuat sesuai pesanan',
        default => 'Stok habis',
    };

    $gallery = $series->media->sortByDesc(fn ($media) => $series->image === $media->image)->values();
    $visibleGallery = $gallery;
    if ($selectedVariant) {
        $color = mb_strtolower((string) $selectedVariant->color);
        $exactPhotos = $gallery->where('variant_id', $selectedVariant->id);
        $colorPhotos = $gallery->filter(fn ($media) => ! $media->variant_id && $color !== '' && mb_strtolower($media->color ?? '') === $color);
        $generalPhotos = $gallery->filter(fn ($media) => ! $media->variant_id && ! $media->color);
        $matchingPhotos = $exactPhotos->concat($colorPhotos)->concat($generalPhotos);
        if ($matchingPhotos->isNotEmpty()) {
            $visibleGallery = $matchingPhotos;
        }
    }
    $mainMedia = $visibleGallery->first();
    $mainIndex = $mainMedia ? (int) $gallery->search($mainMedia) : 0;
@endphp
@php($mainImage=$mainMedia?->image_url ?? $series->image_url)
@section('image',$mainImage)
@php($productSchema=['@context'=>'https://schema.org','@type'=>'Product','name'=>$series->name,'description'=>$series->seo_description ?: $series->description,'image'=>$gallery->pluck('image_url')->prepend($series->image_url)->unique()->values()->all(),'sku'=>$series->variants->first()?->sku,'brand'=>['@type'=>'Brand','name'=>$shop['brand_name'] ?? 'Asfara'],'offers'=>['@type'=>'AggregateOffer','priceCurrency'=>'IDR','lowPrice'=>(int)$series->variants->min('price'),'highPrice'=>(int)$series->variants->max('price'),'offerCount'=>$series->variants->count(),'availability'=>$series->variants->sum(fn($variant)=>$variant->available)>0?'https://schema.org/InStock':'https://schema.org/OutOfStock']])
@push('head')
<script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<style>.product-gallery-main img{width:100%;aspect-ratio:4/5;object-fit:cover}.product-gallery-pager{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:14px}.product-gallery-dot{width:26px;height:3px;padding:0;border:0;background:#d2d2ce}.product-gallery-dot.active{background:#222}</style>
@endpush
@section('content')
@if($productPreview ?? false)<div class="container-xxl demo-note">Pratinjau admin · Pembelian dinonaktifkan.</div>@endif
<section class="container-xxl section product-page">
    <nav class="breadcrumb"><a href="{{ route('catalog') }}">Koleksi</a> / {{ $series->category->name }} / {{ $series->name }}</nav>
    <div class="row g-5 product-layout">
        <div class="col-lg-7 product-gallery-column" data-gallery-photo-count="{{ $visibleGallery->count() }}">
            <div class="detail-image product-gallery-main product-gallery-stage"><img id="product-main-image" src="{{ $mainImage }}" draggable="false" alt="{{ $mainMedia?->alt_text ?? $series->name }}" style="object-position:{{ $mainMedia?->focal_x ?? 50 }}% {{ $mainMedia?->focal_y ?? 50 }}%" width="800" height="800" loading="eager" fetchpriority="high" decoding="async"><button type="button" class="product-gallery-arrow is-prev" data-product-gallery-prev aria-label="Foto sebelumnya" @disabled($visibleGallery->count() < 2)>‹</button><button type="button" class="product-gallery-arrow is-next" data-product-gallery-next aria-label="Foto berikutnya" @disabled($visibleGallery->count() < 2)>›</button><button type="button" class="product-zoom-button" id="open-product-zoom" aria-label="Perbesar foto">⌕</button><span class="product-gallery-count" @if($visibleGallery->count() < 2) hidden @endif><b data-product-gallery-current>1</b> / <span data-product-gallery-total>{{ $visibleGallery->count() }}</span></span></div>
            @if($gallery->isNotEmpty())
                <div class="product-gallery-pager" aria-label="Galeri foto produk">
                    @foreach($gallery as $media)<button type="button" class="product-thumbnail {{ $loop->index === $mainIndex ? 'active' : '' }}" data-image="{{ $media->image_url }}" data-alt="{{ $media->alt_text ?: $series->name }}" data-focal-x="{{ $media->focal_x }}" data-focal-y="{{ $media->focal_y }}" data-variant="{{ $media->variant_id }}" data-color="{{ mb_strtolower($media->color ?? '') }}" @if(!$visibleGallery->contains($media)) hidden @endif aria-label="Tampilkan foto {{ $loop->iteration }}" aria-pressed="{{ $loop->index === $mainIndex ? 'true' : 'false' }}"><img src="{{ $media->image_url }}" alt="" width="64" height="64" loading="lazy" decoding="async"></button>@endforeach
                </div>
                @if($gallery->count()>1)<div class="product-gallery-grid">@foreach($gallery as $media)<button type="button" data-gallery-grid="{{ $loop->index }}" data-image="{{ $media->image_url }}" data-alt="{{ $media->alt_text ?: $series->name }}" aria-label="Perbesar {{ $media->alt_text ?: $series->name }}" @if($loop->index === $mainIndex || !$visibleGallery->contains($media)) hidden @endif><img src="{{ $media->image_url }}" alt="{{ $media->alt_text }}" style="object-position:{{ $media->focal_x }}% {{ $media->focal_y }}%" width="320" height="320" loading="lazy" decoding="async"></button>@endforeach</div>@endif
            @elseif(!$series->image)<small class="text-muted">Ilustrasi koleksi. Foto produk dapat ditambahkan melalui dashboard admin.</small>@endif
        </div>
        <div class="col-lg-5 product-info-column">
            <span class="eyebrow">{{ $series->category->name }}</span>
            <h1 class="product-title">{{ $series->name }}</h1>
            @if($reviews->isNotEmpty())
                <div class="product-heading-meta"><a href="#product-reviews"><span aria-hidden="true">★★★★★</span> {{ number_format($reviews->avg('rating'),1).' ('.$reviews->count().')' }}</a></div>
            @endif
            <div class="product-pricing">
                <div class="product-price-row"><div @class(['product-price','sale-price'=>$saving>0]) id="display-product-price">@rupiah($displayPrice)</div><del id="display-compare-price" @if($saving<=0) hidden @endif>@rupiah($compareAtPrice)</del></div>
                <div class="product-price-status">
                    <span class="product-stock" id="display-product-stock">{{ $stockLabel }}</span>
                    @if($compareAtPrice > 0)<span class="product-saving" id="display-product-saving" @if($saving<=0) hidden @endif>Hemat @rupiah($saving)</span>@endif
                </div>
            </div>
            @if($priceGroup)
                <div class="alert alert-success">Harga privat <strong>{{ $priceGroup->name }}</strong> aktif. Harga menyesuaikan jumlah SKU yang dipilih.</div>
            @endif
            @if($standardVariants->isNotEmpty())<form method="post" action="{{ $previewSubmitUrl ?? route('cart.add') }}" id="product-purchase-form" data-cart-add>@csrf
                <div class="variant-option-groups" id="product-variant-options">
                    @if($series->uses_product_type)<div><div class="variant-option-label" id="variant-type-label">Jenis produk</div><div class="variant-choices" role="group" aria-labelledby="variant-type-label">@foreach($standardVariants->pluck('product_type')->unique() as $type)<button type="button" class="variant-choice" data-kind="type" data-value="{{ mb_strtolower($type) }}">{{ $type }}</button>@endforeach</div></div>@endif
                    <div><div class="variant-option-label" id="variant-color-label">Warna</div><div class="variant-choices" role="group" aria-labelledby="variant-color-label">@foreach($standardVariants->pluck('color')->unique() as $color)<button type="button" class="variant-choice color-choice" data-kind="color" data-value="{{ mb_strtolower($color) }}"><i style="--swatch:{{ \App\Support\ColorSwatch::hex($color) }}"></i>{{ $color }}</button>@endforeach</div></div>
                    <div><div class="variant-option-label"><span id="variant-size-label">Ukuran</span><button type="button" class="size-guide-link" data-open-size-guide aria-haspopup="dialog">Panduan ukuran</button></div><div class="variant-choices" role="group" aria-labelledby="variant-size-label">@foreach($standardVariants->pluck('size')->unique() as $size)<button type="button" class="variant-choice" data-kind="size" data-value="{{ mb_strtolower($size) }}">{{ $size }}</button>@endforeach</div></div>
                </div>
                <label class="visually-hidden" for="variant">Kombinasi varian</label>
                <select name="variant_id" id="variant" class="visually-hidden" required>
                    <option value="">Pilih varian Anda</option>
                    @foreach($standardVariants as $v)
                        @php($tiers=$businessTiers[$v->id]->map(fn($tier)=>['minimum'=>$tier->minimum_quantity,'price'=>$tier->price])->values())
                        <option value="{{ $v->id }}" data-price="{{ $unitPrices[$v->id] }}" data-tiers="{{ $tiers->toJson() }}" data-stock="{{ $v->available }}" data-sku="{{ $v->sku }}" data-label="{{ $v->label }}" data-type="{{ mb_strtolower($v->product_type) }}" data-color="{{ mb_strtolower($v->color) }}" data-size="{{ mb_strtolower($v->size) }}" data-method="{{ $v->fulfillment==='ready_stock'?'Ready stock':($v->fulfillment==='bundle'?'Bundle':'Pre-order') }}" data-estimate="{{ $v->lead_time }}" @disabled($v->available<1) @selected($selectedVariant?->id === $v->id)>{{ $v->label }} — @rupiah($unitPrices[$v->id]) {{ $v->available<1?'(habis)':'' }}</option>
                    @endforeach
                </select>
                <div class="product-purchase">
                    <div class="product-quantity-block">
                        <label for="qty">Jumlah</label>
                        <div class="quantity-stepper">
                            <button type="button" data-qty-change="-1" aria-label="Kurangi jumlah">−</button>
                            <input class="form-control quantity" type="number" name="quantity" id="qty" min="1" max="100" value="1" required>
                            <button type="button" data-qty-change="1" aria-label="Tambah jumlah">+</button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-dark" aria-describedby="variant-info" data-product-submit @disabled(!$canBuySelected)>TAMBAH KE KERANJANG</button>
                    <button type="submit" class="btn btn-outline-dark product-buy-now" name="buy_now" value="1" aria-describedby="variant-info" data-product-submit @disabled(!$canBuySelected)>BELI SEKARANG</button>
                </div>
                <div class="variant-info" id="variant-info" aria-live="polite">Pilih varian untuk melihat harga dan ketersediaan.</div>
                <p class="cart-form-error" data-cart-add-error role="alert" tabindex="-1" hidden></p>
            </form>@endif
            <div class="product-share"><button type="button" id="share-product" class="product-share-link">Bagikan pilihan ↗</button><span id="share-status" class="small ms-2" aria-live="polite"></span></div>
            @foreach($series->variants->where('fulfillment','custom_made') as $v)
                @if($v->measurementTemplate?->active)
                @php($template=$v->measurementTemplate)
                <div class="panel mt-4"><span class="eyebrow">UKURAN CUSTOM</span><h2 class="mt-2">{{ $v->label }}</h2>
                    <p>{{ $template->policy }}<br><strong>@if($template->pricing_mode==='fixed')Biaya custom @rupiah($template->surcharge)@else Harga custom ditentukan melalui penawaran admin @endif</strong> · {{ $template->lead_time }}</p>
                    <form method="post" enctype="multipart/form-data" action="{{ $previewSubmitUrl ?? route('custom.submit',$v) }}">@csrf<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div class="form-grid">@foreach($template->fields as $field)<div><label for="custom-{{ $v->id }}-{{ $field['key'] }}">{{ $field['label'] }} ({{ $field['unit'] }})</label><input id="custom-{{ $v->id }}-{{ $field['key'] }}" class="form-control" type="number" step="0.1" name="measurements[{{ $field['key'] }}]" min="{{ $field['min'] ?? '' }}" max="{{ $field['max'] ?? '' }}" placeholder="{{ $field['example'] ?? '' }}" @required($field['required'] ?? true)>@if(!empty($field['help']))<small>{{ $field['help'] }}</small>@endif</div>@endforeach</div>
                        <label class="mt-3" for="custom-reference-{{ $v->id }}">Referensi desain (opsional, JPG/PNG/PDF maks. 5 MB)</label><input id="custom-reference-{{ $v->id }}" class="form-control" type="file" name="reference" accept="image/jpeg,image/png,application/pdf"><small>File disimpan privat dan hanya dapat dibuka tim yang berwenang.</small>
                        <label class="d-block my-3"><input type="checkbox" name="measurement_approval" value="1" required> Saya memastikan ukuran sudah benar dan menyetujui kebijakan custom.</label>
                        <button class="btn btn-dark">{{ $template->pricing_mode==='fixed'?'Tambahkan custom ke keranjang':'Kirim permintaan penawaran' }}</button>
                    </form>
                </div>
                @endif
            @endforeach
            @auth
                <div class="mt-4">
                    <form method="post" action="{{ $previewSubmitUrl ?? route('wishlist.toggle', $series) }}">
                        @csrf
                        <button class="btn btn-outline-dark w-100" type="submit" aria-pressed="{{ $isWishlisted ? 'true' : 'false' }}">
                            {{ $isWishlisted ? '♥ Tersimpan di wishlist' : '♡ Simpan ke wishlist' }}
                        </button>
                    </form>
                </div>
            @endauth
            @if($priceGroup && $businessTiers->flatten()->isNotEmpty())
                <details class="mt-4"><summary>Lihat tier harga akun {{ $priceGroup->name }}</summary>
                    @foreach($series->variants as $v)
                        @if($businessTiers[$v->id]->isNotEmpty())
                            <p class="small mb-1"><strong>{{ $v->label }} · {{ $v->sku }}</strong></p>
                            <p class="small">@foreach($businessTiers[$v->id] as $tier){{ $tier->minimum_quantity }}+ @rupiah($tier->price){{ !$loop->last?' · ':'' }}@endforeach</p>
                        @endif
                    @endforeach
                </details>
            @endif
            <div class="product-details">
                <details><summary>{{ $storeCopy['material'] }}</summary><p class="preserve-lines">{{ $series->material ?: 'Informasi material sedang dilengkapi. Hubungi Asfara untuk bantuan.' }}</p></details>
                <details><summary>{{ $storeCopy['feature'] }}</summary><p class="preserve-lines">{{ $series->description ?: 'Rancangan Asfara untuk kenyamanan dalam setiap aktivitas.' }}</p></details>
                <details><summary>{{ $storeCopy['care'] }}</summary><p class="preserve-lines">{{ $series->care ?: 'Cuci lembut dengan warna sejenis dan hindari pemutih.' }}</p></details>
                <details id="size-guide"><summary>{{ $storeCopy['size_detail'] }}</summary>@include('partials.size-chart')</details>
                <details><summary>{{ $storeCopy['return_terms'] }}</summary><p>Permintaan retur dapat diajukan maksimal {{ (int)($shop['return_days'] ?? 7) }} hari setelah barang diterima. Produk harus belum digunakan, label tetap terpasang, dan memenuhi kebijakan retur Asfara.</p></details>
                <details><summary>{{ $storeCopy['shipping_payment'] }}</summary><p>Pesanan dikirim dari Tasikmalaya melalui POS atau JNE. Pilihan COD mengikuti wilayah dan nilai pesanan. Biaya final ditampilkan sebelum pesanan dibuat.</p><p>{{ $storeCopy['payment_method_text'] }}</p></details>
            </div>
        </div>
    </div>
</section>
@if(($shop['product_related_enabled'] ?? true) && $relatedProducts->isNotEmpty())
<section class="commerce-section product-related container-xxl reveal"><div class="commerce-heading"><h2>{{ $storeCopy['related_products'] }}</h2><a href="{{ route('catalog',['category'=>$series->category->slug]) }}">{{ $storeCopy['view_all'] }}</a></div><div class="reference-carousel"><button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button><div class="product-carousel" data-drag-scroll>@foreach($relatedProducts as $collection)<div class="product-carousel-item">@include('partials.product-card')</div>@endforeach</div><button type="button" data-carousel-next aria-label="Produk berikutnya">›</button></div></section>
@endif
<section class="container-xxl section pt-0 product-community" id="product-reviews"><div class="section-top"><div><span class="eyebrow">PEMBELIAN TERVERIFIKASI</span><h2 class="mt-3">Ulasan pelanggan</h2><p>Pengalaman nyata pelanggan setelah menerima produk Asfara.</p></div>@if($reviews->isNotEmpty())<div class="review-summary"><strong>{{ number_format($reviews->avg('rating'),1) }}</strong><span>★★★★★</span><small>{{ $reviews->count() }} ulasan terverifikasi</small></div>@endif</div>
@forelse($reviews as $review)<article class="panel mb-3"><p><strong>{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</strong> · {{ $review->user->name }} <span class="badge">Terverifikasi</span></p><p>{{ $review->body }}</p>@if($review->photos)<div class="d-flex gap-2 mb-3">@foreach($review->photos as $photo)<img src="{{ asset('storage/'.$photo) }}" alt="Foto ulasan pelanggan" width="96" height="96" loading="lazy" decoding="async" style="width:96px;height:96px;object-fit:cover">@endforeach</div>@endif<div class="d-flex align-items-center gap-2"><span class="small">{{ $review->helpful_votes_count }} orang merasa ulasan ini membantu.</span>@auth<form method="post" action="{{ $previewSubmitUrl ?? route('review.helpful',$review) }}">@csrf<button class="btn btn-outline-dark btn-sm">{{ in_array($review->id,$helpfulReviewIds)?'Batalkan':'Membantu' }}</button></form>@endauth</div></article>@empty<p>Belum ada ulasan untuk series ini.</p>@endforelse
</section>
<section class="container-xxl section pt-0"><div class="section-top"><div><span class="eyebrow">TANYA PRODUK</span><h2 class="mt-3">Pertanyaan pelanggan</h2></div></div>
@forelse($questions as $question)<article class="panel mb-3"><p class="mb-2"><strong>{{ $question->user->name }}</strong> bertanya:</p><p>{{ $question->question }}</p><div class="border-top pt-3"><strong>Jawaban Asfara</strong><p class="mb-0 mt-2">{{ $question->answer }}</p></div></article>@empty<p>Belum ada pertanyaan yang dijawab untuk series ini.</p>@endforelse
@auth<form class="panel mt-4" method="post" action="{{ $previewSubmitUrl ?? route('product.question',$series) }}">@csrf<label for="product-question"><strong>Ajukan pertanyaan</strong></label><textarea id="product-question" class="form-control mt-2" name="question" rows="3" minlength="10" maxlength="1000" required>{{ old('question') }}</textarea><button class="btn btn-dark mt-3">Kirim pertanyaan</button></form>@else<p><a href="{{ route('login') }}">Masuk</a> untuk mengajukan pertanyaan produk.</p>@endauth
</section>
<dialog class="product-lightbox" id="product-lightbox" aria-label="Foto {{ $series->name }} diperbesar"><div class="product-lightbox-controls"><button type="button" id="close-product-zoom" aria-label="Tutup foto">×</button><button type="button" class="product-zoom-toggle" aria-label="Perbesar gambar" aria-pressed="false">+</button></div><img id="product-lightbox-image" src="{{ $mainImage }}" alt="{{ $series->name }} diperbesar" width="800" height="800" loading="lazy" decoding="async"></dialog>
<dialog class="size-guide-dialog" id="size-guide-dialog" aria-labelledby="size-guide-title"><button type="button" class="drawer-close" data-close-size-guide aria-label="Tutup panduan ukuran">×</button><span class="eyebrow">{{ strtoupper($series->name) }}</span><h2 id="size-guide-title">PANDUAN UKURAN</h2>@include('partials.size-chart')<p class="small">Jika berada di antara dua ukuran, pilih ukuran lebih besar atau hubungi Asfara melalui WhatsApp.</p></dialog>
@if($standardVariants->isNotEmpty())
    <div class="mobile-purchase-bar">
        <div>
            <strong id="mobile-product-price">@rupiah($displayPrice)</strong>
            <small data-mobile-variant>{{ $selectedVariant?->label ?? 'Stok habis' }}</small>
        </div>
        <button type="button" class="btn btn-dark" data-mobile-add aria-controls="product-variant-options" @disabled($availableVariants->isEmpty())>{{ $needsVariantSelection ? 'Pilih varian' : ($canBuySelected ? 'Tambah ke keranjang' : 'Stok habis') }}</button>
    </div>
@endif
@endsection
@push('scripts')
<script>
@if($productPreview ?? false)
document.getElementById('main').addEventListener('submit', event => {
    event.preventDefault();
    event.stopPropagation();
}, {capture: true});
@endif
const select = document.getElementById('variant');
const productForm = select?.form;
const qty = document.getElementById('qty');
const mainImage = document.getElementById('product-main-image');
const thumbnails = [...document.querySelectorAll('.product-thumbnail')];
const gridPhotos = [...document.querySelectorAll('[data-gallery-grid]')];
const variantChoices = [...document.querySelectorAll('.variant-choice')];
const availableOptions = select ? [...select.options].filter(option => option.value && !option.disabled) : [];
const submitButtons = productForm ? [...productForm.querySelectorAll('[data-product-submit]')] : [];
const quantityButtons = [...document.querySelectorAll('[data-qty-change]')];
const variantInfo = document.getElementById('variant-info');
const mobileAdd = document.querySelector('[data-mobile-add]');
const mobilePrice = document.getElementById('mobile-product-price');
const mobileVariant = document.querySelector('[data-mobile-variant]');
const variantGroups = @json($variantGroups);
const displayPrice = document.getElementById('display-product-price');
const displayCompare = document.getElementById('display-compare-price');
const displaySaving = document.getElementById('display-product-saving');
const displayStock = document.getElementById('display-product-stock');
const compareAtPrice = @json($compareAtPrice);
const rupiah = value => new Intl.NumberFormat('id-ID', {style: 'currency', currency: 'IDR', maximumFractionDigits: 0}).format(value);
let galleryIndex = @json($mainIndex);
let visiblePhotos = @json($visibleGallery->map(fn ($media) => $gallery->search($media))->values()).map(index => thumbnails[index]);
const galleryPrevious = document.querySelector('[data-product-gallery-prev]');
const galleryNext = document.querySelector('[data-product-gallery-next]');
const galleryTotal = document.querySelector('[data-product-gallery-total]');
let lastVariant = select?.value;
let needsVariantSelection = @json($needsVariantSelection);
let focusVariantKind;
function showImage(button) {
    if (!button || !mainImage) return;
    galleryIndex = thumbnails.indexOf(button);
    mainImage.src = button.dataset.image;
    mainImage.alt = button.dataset.alt;
    mainImage.style.objectPosition = (button.dataset.focalX || 50) + '% ' + (button.dataset.focalY || 50) + '%';
    thumbnails.forEach(item => {
        item.classList.toggle('active', item === button);
        item.setAttribute('aria-pressed', String(item === button));
    });
    gridPhotos.forEach(item => {
        const photo = thumbnails[Number(item.dataset.galleryGrid)];
        item.hidden = photo === button || !visiblePhotos.includes(photo);
    });
    document.querySelector('[data-product-gallery-current]').textContent = String(visiblePhotos.indexOf(button) + 1);
}
const choicesFor = (kind, current) => storeVariants.choices(availableOptions, variantGroups, kind, current);
function updateVariant() {
    const option = select.selectedOptions[0];
    const hasVariant = Boolean(option?.value);
    const purchasable = hasVariant && !option.disabled;
    submitButtons.forEach(button => button.disabled = !purchasable);
    variantChoices.forEach(choice => {
        const {kind, value} = choice.dataset;
        const possible = choicesFor(kind, option).some(candidate => candidate.dataset[kind] === value);
        const active = hasVariant && option.dataset[kind] === value;
        choice.disabled = !possible;
        choice.classList.toggle('is-unavailable', !possible);
        choice.classList.toggle('active', active);
        choice.setAttribute('aria-pressed', String(active));
    });
    qty.disabled = !purchasable;
    const variantChanged = option.value !== lastVariant;
    qty.max = String(purchasable ? Math.min(100, Number(option.dataset.stock)) : 1);
    if (variantChanged && Number(qty.value) > Number(qty.max)) qty.value = qty.max;
    const amount = Math.min(Number(qty.max), Math.max(1, Number.parseInt(qty.value, 10) || 1));
    quantityButtons.forEach(button => {
        button.disabled = !purchasable || (Number(button.dataset.qtyChange) < 0 ? amount <= 1 : amount >= Number(qty.max));
    });
    mobileAdd.disabled = availableOptions.length === 0;
    mobileAdd.textContent = mobileAdd.disabled ? 'Stok habis' : (needsVariantSelection || !purchasable ? 'Pilih varian' : 'Tambah ke keranjang');
    mobileVariant.textContent = hasVariant ? option.dataset.label : 'Stok habis';
    if (!hasVariant) {
        variantInfo.textContent = 'Stok habis.';
        return;
    }
    const stockLabel = storeVariants.stock(purchasable, option.dataset.method);
    displayStock.textContent = stockLabel;
    let price = Number(option.dataset.price);
    let applied = null;
    for (const tier of JSON.parse(option.dataset.tiers || '[]')) {
        if (amount >= tier.minimum) {
            price = tier.price;
            applied = tier.minimum;
        }
    }
    displayPrice.textContent = rupiah(price);
    displayPrice.classList.toggle('sale-price', compareAtPrice > price);
    displayCompare.hidden = compareAtPrice <= price;
    if (displaySaving) {
        displaySaving.hidden = compareAtPrice <= price;
        displaySaving.textContent = 'Hemat ' + rupiah(compareAtPrice - price);
    }
    mobilePrice.textContent = rupiah(price);
    variantInfo.textContent = [
        option.dataset.label,
        purchasable ? option.dataset.method : stockLabel,
        'SKU ' + option.dataset.sku,
        purchasable ? option.dataset.estimate : '',
        applied ? 'Harga akun tier ' + applied + '+' : '',
    ].filter(Boolean).join(' · ');
    if (!variantChanged) return;
    lastVariant = option.value;
    const matchingPhotos = storeVariants.gallery(thumbnails, option.value, option.dataset.color);
    const nextPhotos = matchingPhotos.length ? matchingPhotos : thumbnails;
    if (nextPhotos.length !== visiblePhotos.length || nextPhotos.some((photo, index) => photo !== visiblePhotos[index])) {
        visiblePhotos = nextPhotos;
        thumbnails.forEach(photo => photo.hidden = !visiblePhotos.includes(photo));
        galleryPrevious.disabled = galleryNext.disabled = visiblePhotos.length < 2;
        mainImage.closest('.product-gallery-column').dataset.galleryPhotoCount = visiblePhotos.length;
        galleryTotal.textContent = String(visiblePhotos.length);
        galleryTotal.parentElement.hidden = visiblePhotos.length < 2;
        showImage(visiblePhotos[0]);
    }
    const url = new URL(window.location.href);
    url.searchParams.set('variant', option.value);
    history.replaceState(history.state, '', url);
}
thumbnails.forEach(button => button.addEventListener('click', () => showImage(button)));
const stepGallery = direction => {
    if (visiblePhotos.length < 2) return;
    const index = visiblePhotos.indexOf(thumbnails[galleryIndex]);
    showImage(visiblePhotos[(index + direction + visiblePhotos.length) % visiblePhotos.length]);
};
galleryPrevious.addEventListener('click', () => stepGallery(-1));
galleryNext.addEventListener('click', () => stepGallery(1));
variantChoices.forEach(button => button.addEventListener('click', () => {
    const {kind, value} = button.dataset;
    const current = select.selectedOptions[0];
    const match = storeVariants.match(availableOptions, variantGroups, kind, value, current);
    if (!match) return;
    const sizes = new Set(choicesFor('size', match).map(option => option.dataset.size));
    needsVariantSelection = kind !== 'size' && sizes.size > 1
        && (needsVariantSelection || current?.dataset.size !== match.dataset.size);
    focusVariantKind = needsVariantSelection ? 'size' : undefined;
    select.value = match.value;
    updateVariant();
}));
if (select) {
    const normalizeQuantity = () => {
        const value = Number.parseInt(qty.value, 10);
        qty.value = String(Math.min(Number(qty.max), Math.max(1, Number.isFinite(value) ? value : 1)));
        updateVariant();
    };
    select.tabIndex = -1;
    select.addEventListener('change', () => {
        needsVariantSelection = false;
        updateVariant();
    });
    qty.addEventListener('input', updateVariant);
    qty.addEventListener('change', normalizeQuantity);
    productForm.addEventListener('submit', normalizeQuantity);
    quantityButtons.forEach(button => button.addEventListener('click', () => {
        if (qty.disabled) return;
        qty.value = Number(qty.value) + Number(button.dataset.qtyChange);
        normalizeQuantity();
    }));
    mobileAdd.addEventListener('click', () => {
        if (needsVariantSelection || submitButtons[0].disabled) {
            const choices = variantChoices.filter(choice => !choice.disabled);
            const group = focusVariantKind ?? variantGroups.find(kind => choices.filter(choice => choice.dataset.kind === kind).length > 1);
            const target = choices.find(choice => choice.dataset.kind === group) ?? choices[0];
            target?.focus({preventScroll: true});
            target?.scrollIntoView({block: 'center'});
            return;
        }
        productForm.requestSubmit(submitButtons[0]);
    });
    updateVariant();
}
const lightbox = document.getElementById('product-lightbox');
const lightboxImage = document.getElementById('product-lightbox-image');
const zoomToggle = lightbox.querySelector('.product-zoom-toggle');
const setZoom = magnified => {
    lightboxImage.classList.toggle('is-magnified', magnified);
    zoomToggle.setAttribute('aria-pressed', String(magnified));
    zoomToggle.setAttribute('aria-label', magnified ? 'Perkecil gambar' : 'Perbesar gambar');
    zoomToggle.textContent = magnified ? '−' : '+';
};
const openZoom = (src, alt) => {
    lightboxImage.src = src;
    lightboxImage.alt = alt;
    setZoom(false);
    lightbox.showModal();
};
document.getElementById('open-product-zoom').addEventListener('click', () => openZoom(mainImage.src, mainImage.alt));
document.getElementById('close-product-zoom').addEventListener('click', () => lightbox.close());
lightbox.addEventListener('click', event => { if (event.target === lightbox) lightbox.close(); });
zoomToggle.addEventListener('click', () => setZoom(zoomToggle.getAttribute('aria-pressed') !== 'true'));
gridPhotos.forEach(button => button.addEventListener('click', () => openZoom(button.dataset.image, button.dataset.alt)));
bindGallerySwipe(mainImage.closest('.product-gallery-stage'), mainImage, stepGallery);
const sizeGuide=document.getElementById('size-guide-dialog');document.querySelector('[data-open-size-guide]')?.addEventListener('click',()=>sizeGuide.showModal());document.querySelector('[data-close-size-guide]')?.addEventListener('click',()=>sizeGuide.close());sizeGuide?.addEventListener('click',event=>{if(event.target===sizeGuide)sizeGuide.close()});
document.getElementById('share-product').addEventListener('click',async()=>{const url=new URL(window.location.href);if(select?.value)url.searchParams.set('variant',select.value);const data={title:@json($series->name),text:@json('Lihat '.$series->name.' dari '.($shop['brand_name'] ?? 'Asfara')),url:url.href};try{if(navigator.share)await navigator.share(data);else{await navigator.clipboard.writeText(data.url);document.getElementById('share-status').textContent='Tautan disalin.'}}catch(error){if(error.name!=='AbortError')document.getElementById('share-status').textContent='Salin alamat halaman dari browser Anda.'}});
</script>
@endpush
