@if($items->isNotEmpty())
    <div class="reference-carousel home-product-carousel">
        <button type="button" data-carousel-prev aria-label="Produk sebelumnya">‹</button>
        <div class="product-carousel" data-drag-scroll>
            @foreach($items as $collection)
                <div class="product-carousel-item">
                    @include('partials.product-card', ['cardContext' => $cardContext, 'editorialCard' => true])
                </div>
            @endforeach
        </div>
        <button type="button" data-carousel-next aria-label="Produk berikutnya">›</button>
    </div>
@else
    <p class="home-product-empty">Koleksi sedang disiapkan.</p>
@endif
<div class="home-product-cta">
    <a href="{{ $viewAllUrl }}">{{ $storeCopy['view_all'] }} <span aria-hidden="true">→</span></a>
</div>
