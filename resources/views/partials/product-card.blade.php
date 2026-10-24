<article class="product-card">
    <a class="product-image" href="{{ route('product',$collection->slug) }}">
        <img src="{{ $collection->image_url }}" alt="{{ $collection->name }}{{ $collection->image ? '' : ' — ilustrasi koleksi' }}" loading="lazy">
        @if($collection->featured)<span class="product-tag">FEATURED</span>@endif
        <span class="product-quick">PILIH VARIAN</span>
    </a>
    <div class="product-meta"><h3><a href="{{ route('product',$collection->slug) }}">{{ strtoupper($collection->name) }}</a></h3><span>@rupiah($collection->variants_min_price ?? 0)</span></div>
    <p>{{ strtoupper($collection->category->name) }} · PILIHAN WARNA & UKURAN</p>
</article>
