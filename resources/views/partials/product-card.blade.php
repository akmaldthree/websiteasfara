<article class="product-card">
    @php($sellingPrice=(int)($collection->variants_min_price ?? $collection->variants->min('price') ?? 0))
    @php($cardImages=$collection->media->pluck('image_url')->prepend($collection->image_url)->filter()->unique()->values())
    @php($saving=max(0,(int)$collection->compare_at_price-$sellingPrice))
    @php($quickVariants=$collection->variants->where('active',true)->where('fulfillment','!=','custom_made')->map(fn($variant)=>['id'=>$variant->id,'type'=>$variant->product_type,'color'=>$variant->color,'size'=>$variant->size,'label'=>$variant->label,'price'=>(int)$variant->price,'available'=>$variant->available])->values())
    @php($availableStock=$collection->variants->sum(fn($variant)=>max(0,(int)$variant->stock-(int)$variant->reserved)))
    <a class="product-image" href="{{ route('product',$collection->slug) }}">
        <img class="product-image-primary" src="{{ $cardImages->first() }}" alt="{{ $collection->name }}{{ $collection->image ? '' : ' — ilustrasi koleksi' }}" loading="lazy">
        @if($cardImages->count()>1)<img class="product-image-secondary" src="{{ $cardImages->get(1) }}" alt="{{ $collection->name }} — tampilan berikutnya" loading="lazy">@endif
        @if($availableStock<=0)<span class="product-tag product-tag-sold">SOLD OUT</span>@elseif($saving>0)<span class="product-tag product-tag-sale">SAVE IDR {{ number_format($saving,0,',','.') }},00</span>@elseif($collection->featured)<span class="product-tag">FEATURED</span>@endif
    </a>
    @if($quickVariants->isNotEmpty())
        <button type="button" class="product-quick" data-quick-open="quick-product-{{ $collection->id }}">PILIH OPSI</button>
        <script type="application/json" id="quick-product-{{ $collection->id }}">{!! json_encode(['name'=>$collection->name,'url'=>route('product',$collection->slug),'image'=>$cardImages->first(),'usesType'=>(bool)$collection->uses_product_type,'variants'=>$quickVariants], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    @endif
    <div class="product-meta"><h3><a href="{{ route('product',$collection->slug) }}">{{ strtoupper($collection->name) }}</a></h3><div class="product-card-prices"><span @class(['sale-price'=>$collection->compare_at_price>$sellingPrice])><small>{{ $collection->compare_at_price>$sellingPrice?'HARGA SALE':'HARGA' }}</small>@rupiah($sellingPrice)</span>@if($collection->compare_at_price>$sellingPrice)<del><small>HARGA NORMAL</small>@rupiah($collection->compare_at_price)</del>@endif</div></div>
    @php($cardColors=$collection->variants->pluck('color')->filter()->unique()->values())
    <div class="product-card-colors" aria-label="{{ $cardColors->count() }} warna tersedia">@foreach($cardColors->take(5) as $color)<i style="--swatch:{{ \App\Support\ColorSwatch::hex($color) }}" title="{{ $color }}"></i>@endforeach @if($cardColors->count()>5)<small>+{{ $cardColors->count()-5 }}</small>@endif</div>
    @if(($collection->reviews_count ?? 0)>0)<div class="product-rating"><span>★★★★★</span> {{ number_format((float)$collection->reviews_avg_rating,1) }} ({{ $collection->reviews_count }})</div>@endif
</article>
