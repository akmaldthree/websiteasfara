@extends('layouts.store')
@section('title',$series->seo_title ?: $series->name.' — '.($shop['brand_name'] ?? 'Dthree'))
@section('description',$series->seo_description ?: \Illuminate\Support\Str::limit($series->description ?? '',160))
@php($gallery=$series->media)
@php($mainImage=$gallery->first()?->image_url ?? $series->image_url)
@php($productSchema=['@context'=>'https://schema.org','@type'=>'Product','name'=>$series->name,'description'=>$series->seo_description ?: $series->description,'image'=>$gallery->pluck('image_url')->prepend($series->image_url)->unique()->values()->all(),'sku'=>$series->variants->first()?->sku,'brand'=>['@type'=>'Brand','name'=>$shop['brand_name'] ?? 'Dthree'],'offers'=>['@type'=>'AggregateOffer','priceCurrency'=>'IDR','lowPrice'=>(int)$series->variants->min('price'),'highPrice'=>(int)$series->variants->max('price'),'offerCount'=>$series->variants->count(),'availability'=>$series->variants->sum(fn($variant)=>$variant->available)>0?'https://schema.org/InStock':'https://schema.org/OutOfStock']])
@push('head')
<script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<style>.product-gallery-main img{width:100%;aspect-ratio:4/5;object-fit:cover}.product-thumbnails{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.65rem;margin-top:.75rem}.product-thumbnail{padding:0;border:2px solid transparent;background:none}.product-thumbnail.active{border-color:#151515}.product-thumbnail img{width:100%;aspect-ratio:1;object-fit:cover}</style>
@endpush
@section('content')
<section class="container-xxl section">
    <nav class="breadcrumb"><a href="{{ route('catalog') }}">Koleksi</a> / {{ $series->category->name }} / {{ $series->name }}</nav>
    <div class="row g-5">
        <div class="col-lg-6">
            <div class="detail-image product-gallery-main"><img id="product-main-image" src="{{ $mainImage }}" alt="{{ $gallery->first()?->alt_text ?? $series->name }}"></div>
            @if($gallery->isNotEmpty())
                <div class="product-thumbnails" aria-label="Galeri foto produk">
                    @foreach($gallery as $media)<button type="button" class="product-thumbnail {{ $loop->first?'active':'' }}" data-image="{{ $media->image_url }}" data-alt="{{ $media->alt_text }}" data-variant="{{ $media->variant_id }}" data-color="{{ mb_strtolower($media->color ?? '') }}" aria-label="Tampilkan {{ $media->alt_text }}"><img src="{{ $media->image_url }}" alt="{{ $media->alt_text }}"></button>@endforeach
                </div>
            @elseif(!$series->image)<small class="text-muted">Ilustrasi koleksi. Foto produk dapat ditambahkan melalui dashboard admin.</small>@endif
        </div>
        <div class="col-lg-6">
            <span class="eyebrow">{{ $series->category->name }}</span>
            <h1 class="display-4 mt-3">{{ $series->name }}</h1>
            <p class="product-description">{{ $series->description }}</p>
            @if($priceGroup)
                <div class="alert alert-success">Harga privat <strong>{{ $priceGroup->name }}</strong> aktif. Harga menyesuaikan jumlah SKU yang dipilih.</div>
            @endif
            @php($standardVariants=$series->variants->where('fulfillment','!=','custom_made'))
            @if($standardVariants->isNotEmpty())<form method="post" action="{{ route('cart.add') }}">@csrf
                <label for="variant">Pilih {{ $series->uses_product_type?'jenis produk, ':'' }}warna & ukuran</label>
                <select name="variant_id" id="variant" class="form-select mb-3" required>
                    <option value="">Pilih varian Anda</option>
                    @foreach($standardVariants as $v)
                        @php($tiers=$businessTiers[$v->id]->map(fn($tier)=>['minimum'=>$tier->minimum_quantity,'price'=>$tier->price])->values())
                        <option value="{{ $v->id }}" data-price="{{ $v->price }}" data-tiers="{{ $tiers->toJson() }}" data-stock="{{ $v->available }}" data-sku="{{ $v->sku }}" data-color="{{ mb_strtolower($v->color) }}" data-method="{{ $v->fulfillment==='ready_stock'?'Ready stock':($v->fulfillment==='bundle'?'Bundle':'Pre-order') }}" data-estimate="{{ $v->lead_time }}" @disabled($v->available<1) @selected(request('variant')==$v->id)>{{ $v->label }} — @rupiah($v->price) {{ $v->available<1?'(habis)':'' }}</option>
                    @endforeach
                </select>
                <div class="variant-info" id="variant-info" aria-live="polite">Pilih varian untuk melihat harga dan ketersediaan.</div>
                <div class="d-flex gap-3 mt-4"><div><label for="qty">Jumlah</label><input class="form-control quantity" type="number" name="quantity" id="qty" min="1" max="100" value="1" required></div><button class="btn btn-dark align-self-end flex-grow-1">Tambahkan ke keranjang ↗</button></div>
            </form>@endif
            @foreach($series->variants->where('fulfillment','custom_made') as $v)
                @if($v->measurementTemplate?->active)
                @php($template=$v->measurementTemplate)
                <div class="panel mt-4"><span class="eyebrow">UKURAN CUSTOM</span><h2 class="mt-2">{{ $v->label }}</h2>
                    <p>{{ $template->policy }}<br><strong>@if($template->pricing_mode==='fixed')Biaya custom @rupiah($template->surcharge)@else Harga custom ditentukan melalui penawaran admin @endif</strong> · {{ $template->lead_time }}</p>
                    <form method="post" action="{{ route('custom.submit',$v) }}">@csrf<input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div class="form-grid">@foreach($template->fields as $field)<div><label for="custom-{{ $v->id }}-{{ $field['key'] }}">{{ $field['label'] }} ({{ $field['unit'] }})</label><input id="custom-{{ $v->id }}-{{ $field['key'] }}" class="form-control" type="number" step="0.1" name="measurements[{{ $field['key'] }}]" min="{{ $field['min'] ?? '' }}" max="{{ $field['max'] ?? '' }}" placeholder="{{ $field['example'] ?? '' }}" @required($field['required'] ?? true)>@if(!empty($field['help']))<small>{{ $field['help'] }}</small>@endif</div>@endforeach</div>
                        <label class="d-block my-3"><input type="checkbox" name="measurement_approval" value="1" required> Saya memastikan ukuran sudah benar dan menyetujui kebijakan custom.</label>
                        <button class="btn btn-dark">{{ $template->pricing_mode==='fixed'?'Tambahkan custom ke keranjang':'Kirim permintaan penawaran' }}</button>
                    </form>
                </div>
                @endif
            @endforeach
            @auth<div class="mt-4"><p class="small mb-2"><strong>Simpan varian ke wishlist</strong></p><div class="d-flex flex-wrap gap-2">@foreach($series->variants as $v)<form method="post" action="{{ route('wishlist.toggle',$v) }}">@csrf<button class="btn btn-outline-dark btn-sm">{{ in_array($v->id,$wishlistIds)?'♥ Tersimpan':'♡ Simpan' }} · {{ $v->label }}</button></form>@endforeach</div></div>@endauth
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
                @foreach(['material'=>'Bahan','care'=>'Perawatan','size_guide'=>'Panduan ukuran'] as $key=>$label)<details><summary>{{ $label }}</summary><p class="preserve-lines">{{ $series->$key ?: 'Informasi sedang dilengkapi. Hubungi Dthree untuk bantuan.' }}</p></details>@endforeach
                <details><summary>Pengiriman & pembayaran</summary><p>Pesanan dikirim dari Tasikmalaya melalui POS atau JNE. Pilihan COD mengikuti wilayah dan nilai pesanan. Biaya final ditampilkan sebelum pesanan dibuat.</p></details>
            </div>
        </div>
    </div>
</section>
<section class="container-xxl section pt-0"><div class="section-top"><div><span class="eyebrow">PEMBELIAN TERVERIFIKASI</span><h2 class="mt-3">Ulasan pelanggan</h2></div>@if($reviews->isNotEmpty())<strong>{{ number_format($reviews->avg('rating'),1) }} / 5</strong>@endif</div>
@forelse($reviews as $review)<article class="panel mb-3"><p><strong>{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</strong> · {{ $review->user->name }} <span class="badge">Terverifikasi</span></p><p>{{ $review->body }}</p>@if($review->photos)<div class="d-flex gap-2">@foreach($review->photos as $photo)<img src="{{ asset('storage/'.$photo) }}" alt="Foto ulasan pelanggan" style="width:96px;height:96px;object-fit:cover">@endforeach</div>@endif</article>@empty<p>Belum ada ulasan untuk series ini.</p>@endforelse
</section>
@endsection
@push('scripts')
<script>
const select=document.getElementById('variant');const qty=document.getElementById('qty');const mainImage=document.getElementById('product-main-image');const thumbnails=[...document.querySelectorAll('.product-thumbnail')];
function showImage(button){if(!button||!mainImage)return;mainImage.src=button.dataset.image;mainImage.alt=button.dataset.alt;thumbnails.forEach(item=>item.classList.toggle('active',item===button));}
function update(){const o=select.selectedOptions[0];if(!o.value){document.getElementById('variant-info').textContent='Pilih varian untuk melihat harga dan ketersediaan.';return;}const amount=Number(qty.value)||1;let price=Number(o.dataset.price);let applied=null;for(const tier of JSON.parse(o.dataset.tiers||'[]'))if(amount>=tier.minimum){price=tier.price;applied=tier.minimum;}document.getElementById('variant-info').textContent=new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(price)+(applied?' / item · tier '+applied+'+':' / item')+' · '+o.dataset.method+' · Tersedia '+o.dataset.stock+' · SKU '+o.dataset.sku+(o.dataset.estimate?' · '+o.dataset.estimate:'');const photo=thumbnails.find(item=>item.dataset.variant===o.value)||thumbnails.find(item=>item.dataset.color&&item.dataset.color===o.dataset.color);if(photo)showImage(photo);}
thumbnails.forEach(button=>button.addEventListener('click',()=>showImage(button)));
if(select){select.addEventListener('change',update);qty.addEventListener('input',update);update();}
</script>
@endpush
