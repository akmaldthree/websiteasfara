@extends('layouts.store')
@section('title','Checkout — Dthree')
@section('body_class','checkout-body')
@php
    $shippingRegions = $rates->map(fn ($rate) => [
        'id' => (int) $rate->id,
        'province' => $rate->province,
        'city' => $rate->city,
        'label' => $rate->city.', '.$rate->province,
        'price' => (int) $rate->checkout_cost,
        'price_label' => 'Rp '.number_format($rate->checkout_cost, 0, ',', '.'),
        'estimate' => $rate->estimate,
    ])->values();
    $addressProvince = old('province', session('checkout_data.province', ''));
    $addressCity = old('city', session('checkout_data.city', ''));
    $selectedShippingRate = old('shipping_rate_id', session('checkout_data.shipping_rate_id', ''));
    $shippingSearchValue = $addressCity && $addressProvince ? $addressCity.', '.$addressProvince : '';
@endphp
@section('content')
<section class="container-xxl section checkout-page">
    <div class="checkout-steps"><span class="is-active"><b>1</b>{{ __('store.address_shipping') }}</span><span><b>2</b>{{ __('store.review_order') }}</span><span><b>3</b>{{ __('store.payment') }}</span></div>
    <h1 class="my-4">{{ strtoupper(__('store.checkout')) }}</h1>
    <div class="row g-5 checkout-layout">
        <div class="col-lg-7">
            <form action="{{ route('checkout.review') }}" method="post" id="checkout-form" data-submit-once>@csrf
                <div class="panel">
                    <h2>Kontak & alamat penerima</h2>
                    @auth
                        @if(auth()->user()->addresses->count())
                            <label for="saved-address">Alamat tersimpan</label>
                            <select id="saved-address" class="form-select mb-4">
                                <option value="">Isi alamat baru</option>
                                @foreach(auth()->user()->addresses as $address)
                                    <option value="{{ $address->id }}" data-address="{{ json_encode($address->only('name','phone','province','city','district','postal_code','detail')) }}">{{ $address->name }} — {{ $address->city }}, {{ $address->detail }}</option>
                                @endforeach
                            </select>
                        @endif
                    @endauth
                    <div class="form-grid">
                        @foreach(['name'=>'Nama penerima','phone'=>'Nomor WhatsApp / telepon','email'=>'Email (opsional)'] as $name=>$label)
                            <div>
                                <label for="{{ $name }}">{{ $label }}</label>
                                <input id="{{ $name }}" class="form-control" name="{{ $name }}" value="{{ old($name, session('checkout_data.'.$name, auth()->user()?->$name ?? '')) }}" {{ $name!=='email'?'required':'' }} {{ $name==='email'?'type=email':'' }} maxlength="150">
                            </div>
                        @endforeach
                        <div class="span-2 shipping-region-picker" data-shipping-region-picker>
                            <label for="shipping-region-search">Kota / kabupaten atau provinsi</label>
                            <div class="shipping-region-control">
                                <input id="shipping-region-search" class="form-control" type="search" value="{{ $shippingSearchValue }}" placeholder="Contoh: Bandung atau Jawa Barat" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="shipping-region-options" aria-expanded="false" required>
                                <span aria-hidden="true">⌕</span>
                            </div>
                            <input id="province" type="hidden" name="province" value="{{ $addressProvince }}">
                            <input id="city" type="hidden" name="city" value="{{ $addressCity }}">
                            <input id="shipping-rate" type="hidden" name="shipping_rate_id" value="{{ $selectedShippingRate }}">
                            <div id="shipping-region-options" class="shipping-region-options" role="listbox" hidden></div>
                            <small class="field-help">Ketik beberapa huruf, lalu pilih wilayah dari saran untuk melihat ongkir dan estimasi.</small>
                            <div id="shipping-rate-summary" class="shipping-rate-summary" aria-live="polite" hidden></div>
                        </div>
                        @foreach(['district'=>'Kecamatan','postal_code'=>'Kode pos','detail'=>'Alamat lengkap & patokan'] as $name=>$label)
                            <div class="{{ $name==='detail'?'span-2':'' }}">
                                <label for="{{ $name }}">{{ $label }}</label>
                                <input id="{{ $name }}" class="form-control" name="{{ $name }}" value="{{ old($name, session('checkout_data.'.$name, '')) }}" required maxlength="{{ $name==='detail'?1000:150 }}">
                            </div>
                        @endforeach
                    </div>
                </div>
                @if($businessAccount?->status==='approved' && (!$businessAccount->expires_at || $businessAccount->expires_at->isFuture()) && $businessAccount->business_type==='dropshipper')
                    <div class="panel mt-4"><h2>Identitas pengirim dropship</h2><p class="small">Nama ini akan tampil pada packing slip dan label sebagai pengirim.</p><div class="form-grid"><div><label for="sender_name">Nama toko / pengirim</label><input id="sender_name" class="form-control" name="sender_name" value="{{ old('sender_name',session('checkout_data.sender_name',$businessAccount->business_name)) }}"></div><div><label for="sender_phone">Telepon pengirim</label><input id="sender_phone" class="form-control" name="sender_phone" value="{{ old('sender_phone',session('checkout_data.sender_phone')) }}"></div></div></div>
                @endif
                <div class="panel mt-4">
                    <h2>Pengiriman & pembayaran</h2>
                    <p class="small text-muted">Pesanan dikirim dari Tasikmalaya. Ongkir dan estimasi ditentukan langsung berdasarkan wilayah yang Anda pilih.</p>
                    @if($rates->isEmpty())<p>Tarif belum tersedia. Hubungi Dthree sebelum memesan.</p>@endif
                    <fieldset class="payment-methods"><legend>Metode pembayaran</legend><label><input type="radio" name="payment_method" value="transfer" @checked(old('payment_method','transfer')==='transfer')><span><b>Transfer bank manual</b><small>Konfirmasi pembayaran setelah pesanan dibuat</small></span><i>›</i></label>@if($businessAccount?->status==='approved' && $businessAccount->credit_terms_days>0 && $businessAccount->credit_limit>0)<label><input type="radio" name="payment_method" value="credit" @checked(old('payment_method')==='credit')><span><b>Termin {{ $businessAccount->credit_terms_days }} hari</b><small>Limit tersedia @rupiah($businessAccount->credit_limit)</small></span><i>›</i></label>@endif @if($shop['cod_enabled'] ?? false)<label><input type="radio" name="payment_method" value="cod" @checked(old('payment_method')==='cod')><span><b>COD — bayar di tempat</b><small>Ketersediaan diperiksa sesuai wilayah dan pesanan</small></span><i>›</i></label>@endif</fieldset>
                    <div class="form-grid"><div><label for="coupon">Kode voucher (pisahkan dengan koma jika dapat digabung)</label><input id="coupon" class="form-control" name="coupon" value="{{ old('coupon') }}"></div>@auth @if(\App\Models\Setting::get('loyalty_enabled',false))<div><label for="loyalty_points">Gunakan poin (saldo {{ app(\App\Services\LoyaltyService::class)->balance(auth()->user()) }})</label><input id="loyalty_points" class="form-control" type="number" min="0" name="loyalty_points" value="{{ old('loyalty_points',0) }}"></div>@endif @endauth<div><label for="notes">Catatan pesanan</label><input id="notes" class="form-control" name="notes" value="{{ old('notes') }}" maxlength="1000"></div></div>
                    <label class="mt-4"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))> Saya menyetujui <a class="text-decoration-underline" href="{{ route('page','syarat-ketentuan') }}" target="_blank">syarat dan ketentuan</a> serta <a class="text-decoration-underline" href="{{ route('page','privasi') }}" target="_blank">kebijakan privasi</a>.</label>
                    <button class="btn btn-dark w-100 mt-3">Tinjau pesanan & total akhir ↗</button>
                </div>
            </form>
        </div>
        <div class="col-lg-5"><div class="panel checkout-summary"><h2>RINGKASAN PESANAN</h2>@foreach($lines as $line)<div class="checkout-product"><img src="{{ $line['variant']->series->image_url }}" alt="{{ $line['variant']->series->name }}" width="140" height="176" loading="lazy" decoding="async"><span>{{ $line['variant']->series->name }}<br><small>{{ $line['variant']->label }} × {{ $line['quantity'] }}</small></span><strong>@rupiah($line['total'])</strong></div>@endforeach<div class="summary-row total"><span>Subtotal</span><span>@rupiah(array_sum(array_column($lines,'total')))</span></div><p class="small">Belum termasuk ongkir dan biaya pembayaran. Rincian akhir tampil sebelum konfirmasi.</p><div class="secure-checkout">⌾ TRANSAKSI AMAN & DATA TERLINDUNGI</div></div></div>
    </div>
</section>
@endsection
@push('scripts')
<script>
(() => {
    const regions = @json($shippingRegions);
    const picker = document.querySelector('[data-shipping-region-picker]');
    const search = document.getElementById('shipping-region-search');
    const list = document.getElementById('shipping-region-options');
    const province = document.getElementById('province');
    const city = document.getElementById('city');
    const rate = document.getElementById('shipping-rate');
    const summary = document.getElementById('shipping-rate-summary');
    const form = document.getElementById('checkout-form');
    let matches = [];
    let activeIndex = -1;
    let selectedRegion = null;

    if (!picker || !search || !list) return;

    const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
    const setHiddenValue = (input, value) => {
        input.value = String(value ?? '');
        input.setAttribute('value', String(value ?? ''));
    };
    const distance = (left, right) => {
        const row = Array.from({length: right.length + 1}, (_, index) => index);
        for (let i = 1; i <= left.length; i++) {
            let diagonal = row[0]; row[0] = i;
            for (let j = 1; j <= right.length; j++) {
                const above = row[j];
                row[j] = Math.min(row[j] + 1, row[j - 1] + 1, diagonal + (left[i - 1] === right[j - 1] ? 0 : 1));
                diagonal = above;
            }
        }
        return row[right.length];
    };
    const score = (region, query) => {
        const target = normalize(`${region.city} ${region.province}`);
        if (!query) return 1;
        if (target === query) return 1000;
        if (target.startsWith(query)) return 900 - query.length;
        if (target.includes(query)) return 800 - target.indexOf(query);
        const words = target.split(' ');
        const tokens = query.split(' ');
        let total = 0;
        for (const token of tokens) {
            const best = Math.min(...words.map(word => word.includes(token) ? 0 : distance(token, word)));
            const tolerance = token.length <= 4 ? 1 : 2;
            if (best > tolerance) return -1;
            total += 100 - best * 20;
        }
        return total;
    };
    const ranked = value => {
        const query = normalize(value);
        return regions.map(region => ({region, score: score(region, query)})).filter(item => item.score >= 0).sort((a, b) => b.score - a.score || a.region.label.localeCompare(b.region.label, 'id')).slice(0, 8).map(item => item.region);
    };
    const close = () => { list.hidden = true; search.setAttribute('aria-expanded', 'false'); activeIndex = -1; };
    const updateActive = () => list.querySelectorAll('button').forEach((button, index) => button.classList.toggle('is-active', index === activeIndex));
    const selectRegion = region => {
        selectedRegion = region;
        search.value = region.label;
        setHiddenValue(province, region.province);
        setHiddenValue(city, region.city);
        setHiddenValue(rate, region.id);
        search.setCustomValidity('');
        const title = document.createElement('strong');
        const detail = document.createElement('span');
        title.textContent = region.label;
        detail.textContent = `Ongkir ${region.price_label} · estimasi ${region.estimate}`;
        summary.replaceChildren(title, detail);
        summary.hidden = false;
        close();
    };
    const render = value => {
        matches = ranked(value);
        list.replaceChildren();
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.className = 'shipping-region-empty';
            empty.textContent = 'Wilayah tidak ditemukan. Coba nama kota atau provinsi lainnya.';
            list.append(empty);
        } else {
            matches.forEach(region => {
                const button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('role', 'option');
                const location = document.createElement('span');
                const cityName = document.createElement('strong');
                const provinceName = document.createElement('small');
                const tariff = document.createElement('span');
                const price = document.createElement('b');
                const estimate = document.createElement('small');
                cityName.textContent = region.city;
                provinceName.textContent = region.province;
                price.textContent = region.price_label;
                estimate.textContent = region.estimate;
                location.append(cityName, provinceName);
                tariff.append(price, estimate);
                button.append(location, tariff);
                button.addEventListener('mousedown', event => event.preventDefault());
                button.addEventListener('click', () => selectRegion(region));
                list.append(button);
            });
        }
        activeIndex = -1;
        list.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    };

    search.addEventListener('focus', () => render(search.value));
    search.addEventListener('input', () => {
        if (selectedRegion && normalize(search.value) === normalize(selectedRegion.label)) return;
        selectedRegion = null;
        setHiddenValue(province, '');
        setHiddenValue(city, '');
        setHiddenValue(rate, '');
        summary.hidden = true;
        search.setCustomValidity('');
        render(search.value);
    });
    search.addEventListener('blur', () => setTimeout(close, 120));
    search.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) render(search.value);
            activeIndex = Math.max(0, Math.min(matches.length - 1, activeIndex + (event.key === 'ArrowDown' ? 1 : -1)));
            updateActive();
        } else if (event.key === 'Enter' && activeIndex >= 0 && matches[activeIndex]) {
            event.preventDefault(); selectRegion(matches[activeIndex]);
        } else if (event.key === 'Escape') close();
    });
    form.addEventListener('submit', event => {
        if (!rate.value) {
            const exact = ranked(search.value).find(region => normalize(region.label) === normalize(search.value));
            if (exact) selectRegion(exact);
        }
        if (!rate.value) {
            event.preventDefault();
            search.setCustomValidity('Pilih kota atau provinsi dari daftar saran.');
            search.reportValidity();
            render(search.value);
        }
    });
    document.getElementById('saved-address')?.addEventListener('change', function () {
        const data = this.selectedOptions[0].dataset.address;
        if (!data) return;
        const address = JSON.parse(data);
        for (const [key, value] of Object.entries(address)) {
            const input = document.getElementById(key);
            if (input) input.value = value;
        }
        const exact = regions.find(region => normalize(region.city) === normalize(address.city) && normalize(region.province) === normalize(address.province));
        if (exact) selectRegion(exact);
        else {
            search.value = [address.city, address.province].filter(Boolean).join(', ');
            setHiddenValue(rate, '');
            summary.hidden = true;
        }
    });

    const initial = regions.find(region => String(region.id) === String(rate.value)) || regions.find(region => normalize(region.city) === normalize(city.value) && normalize(region.province) === normalize(province.value));
    if (initial) selectRegion(initial);
})();
</script>
@endpush
