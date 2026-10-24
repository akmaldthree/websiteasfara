<article class="product-card">
    @php($sellingPrice=(int)($collection->variants_min_price ?? $collection->variants->min('price') ?? 0))
    @php($cardImages=$collection->media->pluck('image_url')->prepend($collection->image_url)->filter()->unique()->values())
    @php($saving=max(0,(int)$collection->compare_at_price-$sellingPrice))
    <a class="product-image" href="{{ route('product',$collection->slug) }}">
        <img class="product-image-primary" src="{{ $cardImages->first() }}" alt="{{ $collection->name }}{{ $collection->image ? '' : ' — ilustrasi koleksi' }}" loading="lazy">
        @if($cardImages->count()>1)<img class="product-image-secondary" src="{{ $cardImages->get(1) }}" alt="{{ $collection->name }} — tampilan berikutnya" loading="lazy">@endif
        @if($saving>0)<span class="product-tag">HEMAT @rupiah($saving)</span>@elseif($collection->featured)<span class="product-tag">FEATURED</span>@endif
        <span class="product-quick">PILIH OPSI</span>
    </a>
    <div class="product-meta"><h3><a href="{{ route('product',$collection->slug) }}">{{ strtoupper($collection->name) }}</a></h3><div class="product-card-prices"><span @class(['sale-price'=>$collection->compare_at_price>$sellingPrice])><small>{{ $collection->compare_at_price>$sellingPrice?'HARGA SALE':'HARGA' }}</small>@rupiah($sellingPrice)</span>@if($collection->compare_at_price>$sellingPrice)<del><small>HARGA NORMAL</small>@rupiah($collection->compare_at_price)</del>@endif</div></div>
    <p>{{ $collection->variants->pluck('color')->filter()->unique()->count() }} WARNA TERSEDIA</p>
    @if(($collection->reviews_count ?? 0)>0)<div class="product-rating"><span>★★★★★</span> {{ number_format((float)$collection->reviews_avg_rating,1) }} ({{ $collection->reviews_count }})</div>@endif
</article>
