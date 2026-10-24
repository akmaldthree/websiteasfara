<article class="product-card">
    @php($sellingPrice=(int)($collection->variants_min_price ?? 0))
    <a class="product-image" href="{{ route('product',$collection->slug) }}">
        <img src="{{ $collection->image_url }}" alt="{{ $collection->name }}{{ $collection->image ? '' : ' — ilustrasi koleksi' }}" loading="lazy">
        @if($collection->featured)<span class="product-tag">FEATURED</span>@endif
        <span class="product-quick">PILIH VARIAN</span>
    </a>
    <div class="product-meta"><h3><a href="{{ route('product',$collection->slug) }}">{{ strtoupper($collection->name) }}</a></h3><div class="product-card-prices"><span @class(['sale-price'=>$collection->compare_at_price>$sellingPrice])>@rupiah($sellingPrice)</span>@if($collection->compare_at_price>$sellingPrice)<del>@rupiah($collection->compare_at_price)</del>@endif</div></div>
    <p>{{ strtoupper($collection->category->name) }} · PILIHAN WARNA & UKURAN</p>
</article>
