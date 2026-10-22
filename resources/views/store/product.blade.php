@extends('layouts.store')
@section('title',$series->name.' — Dthree')
@section('description',\Illuminate\Support\Str::limit($series->description ?? '',160))
@section('content')
<section class="container-xxl section">
    <nav class="breadcrumb"><a href="{{ route('catalog') }}">Koleksi</a> / {{ $series->category->name }} / {{ $series->name }}</nav>
    <div class="row g-5">
        <div class="col-lg-6"><div class="detail-image"><img src="{{ $series->image_url }}" alt="{{ $series->name }}"></div>@unless($series->image)<small class="text-muted">Ilustrasi koleksi. Foto produk akan ditambahkan oleh Dthree.</small>@endunless</div>
        <div class="col-lg-6">
            <span class="eyebrow">{{ $series->category->name }}</span>
            <h1 class="display-4 mt-3">{{ $series->name }}</h1>
            <p class="product-description">{{ $series->description }}</p>
            @if($priceGroup)
                <div class="alert alert-success">Harga privat <strong>{{ $priceGroup->name }}</strong> aktif. Harga menyesuaikan jumlah SKU yang dipilih.</div>
            @endif
            <form method="post" action="{{ route('cart.add') }}">@csrf
                <label for="variant">Pilih {{ $series->uses_product_type?'jenis produk, ':'' }}warna & ukuran</label>
                <select name="variant_id" id="variant" class="form-select mb-3" required>
                    <option value="">Pilih varian Anda</option>
                    @foreach($series->variants as $v)
                        @php($tiers=$businessTiers[$v->id]->map(fn($tier)=>['minimum'=>$tier->minimum_quantity,'price'=>$tier->price])->values())
                        <option value="{{ $v->id }}" data-price="{{ $v->price }}" data-tiers="{{ $tiers->toJson() }}" data-stock="{{ $v->available }}" data-sku="{{ $v->sku }}" data-method="{{ $v->fulfillment==='ready_stock'?'Ready stock':'Pre-order' }}" data-estimate="{{ $v->lead_time }}" @disabled($v->available<1) @selected(request('variant')==$v->id)>{{ $v->label }} — @rupiah($v->price) {{ $v->available<1?'(habis)':'' }}</option>
                    @endforeach
                </select>
                <div class="variant-info" id="variant-info" aria-live="polite">Pilih varian untuk melihat harga dan ketersediaan.</div>
                <div class="d-flex gap-3 mt-4"><div><label for="qty">Jumlah</label><input class="form-control quantity" type="number" name="quantity" id="qty" min="1" max="100" value="1" required></div><button class="btn btn-dark align-self-end flex-grow-1">Tambahkan ke keranjang ↗</button></div>
            </form>
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
@endsection
@push('scripts')
<script>
const select=document.getElementById('variant');const qty=document.getElementById('qty');
function update(){const o=select.selectedOptions[0];if(!o.value){document.getElementById('variant-info').textContent='Pilih varian untuk melihat harga dan ketersediaan.';return;}const amount=Number(qty.value)||1;let price=Number(o.dataset.price);let applied=null;for(const tier of JSON.parse(o.dataset.tiers||'[]'))if(amount>=tier.minimum){price=tier.price;applied=tier.minimum;}document.getElementById('variant-info').textContent=new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(price)+(applied?' / item · tier '+applied+'+':' / item')+' · '+o.dataset.method+' · Tersedia '+o.dataset.stock+' · SKU '+o.dataset.sku+(o.dataset.estimate?' · '+o.dataset.estimate:'');}
select.addEventListener('change',update);qty.addEventListener('input',update);update();
</script>
@endpush
