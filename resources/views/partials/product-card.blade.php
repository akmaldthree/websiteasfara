<article @class(['product-card', 'product-card-editorial' => $editorialCard ?? false])>
    @php
        $editorialCard = $editorialCard ?? false;
        $cardUnitPrices = $cardUnitPrices ?? null;
        $sellingPrice = (int) ($cardUnitPrices?->min() ?? $collection->variants_min_price ?? $collection->variants->min('price') ?? 0);
        $primaryMedia = $collection->image ? $collection->media->firstWhere('image', $collection->image) : $collection->media->first();
        $primaryImage = $collection->image ? $collection->image_url : ($primaryMedia?->image_url ?? $collection->image_url);
        $otherPhotos = $collection->media->reject(fn ($media) => $media->image_url === $primaryImage);
        $secondaryMedia = $otherPhotos->first(fn ($media) => $primaryMedia && !$media->variant_id && $media->color === $primaryMedia->color)
            ?? $otherPhotos->first();
        $secondaryImage = $secondaryMedia?->image_url;
        $saving = max(0, (int) $collection->compare_at_price - $sellingPrice);
        $showPromotion = !$editorialCard && $saving > 0;
        $quickVariants = $collection->variants->where('active', true)->where('fulfillment', '!=', 'custom_made')->sortBy('id')
            ->map(function ($variant) use ($collection, $cardUnitPrices, $primaryImage) {
                $photo = $variant->storefrontMedia($collection);

                return [
                    'id' => $variant->id, 'type' => $variant->product_type, 'color' => $variant->color, 'size' => $variant->size,
                    'label' => $variant->label, 'price' => (int) ($cardUnitPrices[$variant->id] ?? $variant->price),
                    'available' => $variant->available, 'sku' => $variant->sku,
                    'method' => $variant->fulfillment === 'pre_order' ? 'Pre-order' : ($variant->fulfillment === 'bundle' ? 'Bundle' : 'Ready stock'),
                    'image' => $photo?->image_url ?? $primaryImage,
                    'focal_x' => $photo?->focal_x ?? 50,
                    'focal_y' => $photo?->focal_y ?? 50,
                ];
            })->values();
        $purchasableVariants = $quickVariants->where('available', '>', 0)->values();
        $availableStock = $collection->variants->sum(fn ($variant) => $variant->available);
        $isSignature = str_contains(mb_strtolower($collection->name), 'signature');
        $cardKey = ($cardContext ?? '').$collection->id;

        if ($purchasableVariants->count() > 1) {
            $quickGallery = $collection->media->where('active', true)->map(fn ($photo) => [
                'image' => $photo->image_url,
                'alt' => $photo->alt_text ?: $collection->name,
                'variant' => $photo->variant_id,
                'color' => mb_strtolower($photo->color ?? ''),
                'focal_x' => $photo->focal_x ?? 50,
                'focal_y' => $photo->focal_y ?? 50,
            ])->values();
            $quickProduct = [
                'name' => $collection->name, 'url' => route('product', $collection->slug), 'image' => $primaryImage,
                'usesType' => (bool) $collection->uses_product_type, 'variants' => $quickVariants, 'gallery' => $quickGallery,
            ];
        }
    @endphp
    <a @class(['product-image', 'has-secondary-image' => $secondaryImage]) href="{{ route('product',$collection->slug) }}">
        <img class="product-image-primary" src="{{ $primaryImage }}" style="object-position:{{ $primaryMedia?->focal_x ?? 50 }}% {{ $primaryMedia?->focal_y ?? 50 }}%" alt="{{ $collection->name }}{{ $collection->image ? '' : ' — ilustrasi koleksi' }}" width="800" height="800" loading="lazy" decoding="async">
        @if($secondaryImage)<img class="product-image-secondary" src="{{ $secondaryImage }}" style="object-position:{{ $secondaryMedia->focal_x }}% {{ $secondaryMedia->focal_y }}%" alt="" aria-hidden="true" width="800" height="800" loading="lazy" decoding="async">@endif
        @if($availableStock <= 0)<span class="product-tag product-tag-sold">SOLD OUT</span>
        @elseif($showPromotion)<span class="product-tag product-tag-sale">Hemat @rupiah($saving)</span>
        @elseif(!$editorialCard && $isSignature)<span class="product-tag product-tag-signature">SIGNATURE</span>
        @elseif(!$editorialCard && $collection->featured)<span class="product-tag product-tag-new">NEW IN</span>@endif
    </a>
    @if($purchasableVariants->count()===1)
        <form id="direct-add-{{ $cardKey }}" method="post" action="{{ route('cart.add') }}" data-cart-add hidden>@csrf<input type="hidden" name="variant_id" value="{{ $purchasableVariants->first()['id'] }}"><input type="hidden" name="quantity" value="1"></form>
        <button type="submit" form="direct-add-{{ $cardKey }}" class="product-quick">{{ strtoupper(__('store.purchase.add_to_cart')) }}</button>
    @elseif($purchasableVariants->isNotEmpty())
        <button type="button" class="product-quick" aria-haspopup="dialog" aria-controls="quick-product-dialog" data-quick-open="quick-product-{{ $cardKey }}">{{ strtoupper(__('store.purchase.choose_options')) }}</button>
        <script type="application/json" id="quick-product-{{ $cardKey }}">{!! json_encode($quickProduct, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    @endif
    <div class="product-meta">
        <h3><a href="{{ route('product', $collection->slug) }}">{{ $collection->name }}</a></h3>
        <div class="product-card-prices">
            <span @class(['sale-price' => $showPromotion])><small class="visually-hidden">{{ $showPromotion ? 'HARGA SALE' : 'HARGA' }}</small>@rupiah($sellingPrice)</span>
            @if($showPromotion)<del><small class="visually-hidden">HARGA NORMAL</small>@rupiah($collection->compare_at_price)</del>@endif
        </div>
    </div>
    @unless($editorialCard)
        @php($cardColors = $collection->variants->pluck('color')->filter()->unique()->values())
        <div class="product-card-details">
            <div class="product-card-colors" aria-label="{{ $cardColors->count() }} warna tersedia">@foreach($cardColors->take(5) as $color)<i style="--swatch:{{ \App\Support\ColorSwatch::hex($color) }}" title="{{ $color }}"></i>@endforeach @if($cardColors->count()>5)<small>+{{ $cardColors->count()-5 }}</small>@endif</div>
            @if(($collection->reviews_count ?? 0)>0)<div class="product-rating"><span>★★★★★</span> {{ number_format((float)$collection->reviews_avg_rating,1) }} ({{ $collection->reviews_count }})</div>@endif
        </div>
    @endunless
</article>
