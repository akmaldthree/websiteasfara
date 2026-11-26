@extends('layouts.admin')
@section('title', 'Editor beranda')
@section('content')
@php
    $sectionIds = collect(array_keys($sections));
    $order = collect(explode(',', old('section_order', implode(',', $layout['home_section_order'] ?? $sectionIds->all()))))
        ->intersect($sectionIds)->unique();
    $order = $order->concat($sectionIds->diff($order))->values();
    $hidden = old('home_section_hidden', $layout['home_section_hidden'] ?? []);
    $cards = old('home_visual_cards', $layout['home_visual_cards'] ?? []);
@endphp
<div class="admin-heading">
    <div><h1>Editor beranda</h1><p>Simpan draft, cek tampilannya, lalu terbitkan perubahan.</p></div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-dark" href="{{ route('admin.settings') }}#settings-home">Pengaturan konten dan kurasi</a>
        <a class="btn btn-outline-dark" href="{{ route('admin.home-layout.preview') }}" target="_blank" rel="noopener">Buka pratinjau</a>
    </div>
</div>
@if($errors->any())
    <ul class="alert alert-danger ps-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
@endif
<form method="post" action="{{ route('admin.home-layout.save') }}">
    @csrf
    <section class="panel mb-4">
        <h2>Urutan dan visibilitas</h2>
        <input type="hidden" name="section_order" id="editorial-home-order" value="{{ $order->implode(',') }}">
        <ol class="home-order-list" id="editorial-home-list">
            @foreach($order as $key)
                <li data-section="{{ $key }}">
                    <label><input type="checkbox" name="home_section_hidden[]" value="{{ $key }}" @checked(in_array($key, $hidden, true))> Sembunyikan {{ $sections[$key] }}</label>
                    <span>
                        <button type="button" class="btn btn-outline-dark btn-sm" data-direction="-1" aria-label="Naikkan {{ $sections[$key] }}">↑</button>
                        <button type="button" class="btn btn-outline-dark btn-sm" data-direction="1" aria-label="Turunkan {{ $sections[$key] }}">↓</button>
                    </span>
                </li>
            @endforeach
        </ol>
        <label for="home-editorial-sections">Konten Shop the Look</label>
        <select class="form-select" id="home-editorial-sections" name="home_editorial_sections[]" multiple size="5">
            @foreach($editorialSections as $entry)
                <option value="{{ $entry->id }}" @selected(in_array($entry->id, array_map('intval', old('home_editorial_sections', $layout['home_editorial_sections'] ?? [])), true))>{{ $entry->title }}</option>
            @endforeach
        </select>
        <small>Maksimal enam. CMS mengikuti jadwal dan status terbit tiap konten.</small>
    </section>
    <section class="panel mb-4">
        <h2>Kartu kategori visual</h2>
        <p>Isi maksimal enam kartu. Jika semua kosong, beranda memakai kurasi kategori bawaan.</p>
        @for($row = 0; $row < 6; $row++)
            <details class="panel mb-2" @if($row === 0 || !empty($cards[$row]['label'])) open @endif>
                <summary>Kartu {{ $row + 1 }} @if(!empty($cards[$row]['label'])) · {{ $cards[$row]['label'] }} @endif</summary>
                <div class="form-grid mt-3">
                    <label>Label<input class="form-control" name="home_visual_cards[{{ $row }}][label]" value="{{ $cards[$row]['label'] ?? '' }}" maxlength="80"></label>
                    <label>Tautan<input class="form-control" name="home_visual_cards[{{ $row }}][url]" value="{{ $cards[$row]['url'] ?? '' }}" placeholder="/koleksi"></label>
                    @foreach(['desktop_id' => 'Gambar desktop', 'mobile_id' => 'Gambar HP (opsional)'] as $field => $label)
                        <label>{{ $label }}
                            <select class="form-select" name="home_visual_cards[{{ $row }}][{{ $field }}]">
                                <option value="">Pilih dari pustaka media</option>
                                @foreach($assets as $asset)
                                    <option value="{{ $asset->id }}" @selected((int) ($cards[$row][$field] ?? 0) === $asset->id)>{{ $asset->name }} · {{ $asset->alt_text }}</option>
                                @endforeach
                    <label>Fokus gambar horizontal (%)<input class="form-control" type="number" name="home_visual_cards[{{ $row }}][focal_x]" min="0" max="100" value="{{ $cards[$row]['focal_x'] ?? 50 }}"></label>
                    <label>Fokus gambar vertikal (%)<input class="form-control" type="number" name="home_visual_cards[{{ $row }}][focal_y]" min="0" max="100" value="{{ $cards[$row]['focal_y'] ?? 50 }}"></label>
                            </select>
                        </label>
                    @endforeach
                </div>
            </details>
        @endfor
        @if(auth()->user()->allowed('content.write'))<a href="{{ route('admin.media') }}">Buka pustaka media</a>@endif
    </section>
    <button class="btn btn-dark">Simpan draft</button>
</form>
<section class="panel mt-4">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <h2 class="mb-0">Pratinjau beranda</h2>
        <div class="preview-device-buttons">
            <button type="button" class="btn btn-outline-dark btn-sm" data-layout-width="100%">Desktop</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-layout-width="768px">Tablet</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-layout-width="390px">HP</button>
        </div>
    </div>
    <div class="storefront-preview-shell mt-3"><iframe id="home-layout-frame" title="Pratinjau draft beranda" src="{{ route('admin.home-layout.preview') }}" loading="lazy"></iframe></div>
    @if(!empty($layout['revision']))
        <form class="mt-3" method="post" action="{{ route('admin.home-layout.publish') }}">
            @csrf
            <input type="hidden" name="revision" value="{{ $layout['revision'] }}">
            <button class="btn btn-dark">Terbitkan draft tersimpan</button>
        </form>
    @endif
</section>
@endsection
@push('scripts')
<script>
const homeList = document.getElementById('editorial-home-list');
homeList.addEventListener('click', event => {
    const button = event.target.closest('[data-direction]');
    if (!button) return;
    const item = button.closest('[data-section]');
    if (button.dataset.direction === '-1' && item.previousElementSibling) item.previousElementSibling.before(item);
    if (button.dataset.direction === '1' && item.nextElementSibling) item.nextElementSibling.after(item);
    document.getElementById('editorial-home-order').value = [...homeList.children].map(row => row.dataset.section).join(',');
    button.focus();
});
document.querySelectorAll('[data-layout-width]').forEach(button => button.addEventListener('click', () => {
    document.getElementById('home-layout-frame').style.width = button.dataset.layoutWidth;
}));
</script>
@endpush
