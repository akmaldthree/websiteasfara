@extends('layouts.admin')
@section('title', 'Navigasi & footer')
@section('content')
@php
    $footerSections = \App\Support\StorefrontNavigation::FOOTER_SECTIONS;
    $footerOrder = array_values(array_intersect((array) old('footer_order', $navigation['footer_order']), array_keys($footerSections)));
    $footerOrder = [...$footerOrder, ...array_diff(array_keys($footerSections), $footerOrder)];
@endphp
<div class="admin-heading">
    <div><h1>Navigasi &amp; footer</h1><p>Atur label, urutan, dan penempatan tautan. Pembaruan berlaku setelah disimpan.</p></div>
    <a class="btn btn-outline-dark" href="{{ route('home') }}" target="_blank" rel="noopener">Lihat website ↗</a>
</div>
@if($errors->any())
    <ul class="alert alert-danger ps-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
@endif
<form method="post" action="{{ route('admin.navigation.save') }}">
    @csrf
    @foreach(['header' => 'Menu utama', 'footer' => 'Tautan footer'] as $area => $heading)
        <section class="panel mb-4" data-navigation-area="{{ $area }}" data-limit="{{ $area === 'header' ? 18 : 64 }}">
            <h2>{{ $heading }}</h2>
            <p>{{ $area === 'header' ? 'Jenis Shop membuka kategori. Jenis Collections membuka kampanye. Pilih perangkat untuk setiap tautan.' : 'Tautan dikelompokkan menurut kolom footer. Urutannya mengikuti daftar di bawah.' }}</p>
            <div data-navigation-rows>
                @foreach(array_values((array) old($area, $navigation[$area])) as $index => $link)
                    @include('admin.partials.navigation-row', ['link' => (array) $link])
                @endforeach
            </div>
            <template data-navigation-template>
                @include('admin.partials.navigation-row', ['index' => 'new', 'link' => ['kind' => 'link']])
            </template>
            <button type="button" class="btn btn-outline-dark mt-2" data-add-navigation>Tambah tautan</button>
        </section>
    @endforeach
    <section class="panel mb-4">
        <h2>Susunan footer</h2>
        <ol class="home-order-list" data-footer-order>
            @foreach($footerOrder as $key)
                <li>
                    <input type="hidden" name="footer_order[]" value="{{ $key }}">
                    <span>{{ $footerSections[$key] }}</span>
                    <span>
                        <button type="button" class="btn btn-outline-dark btn-sm" data-move="-1" aria-label="Naikkan {{ $footerSections[$key] }}">↑</button>
                        <button type="button" class="btn btn-outline-dark btn-sm" data-move="1" aria-label="Turunkan {{ $footerSections[$key] }}">↓</button>
                    </span>
                </li>
            @endforeach
        </ol>
        <label><input type="hidden" name="newsletter_enabled" value="0"><input type="checkbox" name="newsletter_enabled" value="1" @checked(old('newsletter_enabled', $navigation['newsletter_enabled']))> Tampilkan formulir newsletter</label>
    </section>
    <button class="btn btn-dark">Simpan navigasi dan footer</button>
</form>
@endsection
@push('scripts')
<script>
const moveNavigationRow = (row, direction) => {
    if (direction === '-1') row.previousElementSibling?.before(row);
    if (direction === '1') row.nextElementSibling?.after(row);
};
document.querySelectorAll('[data-navigation-area]').forEach(area => {
    const rows = area.querySelector('[data-navigation-rows]');
    const add = area.querySelector('[data-add-navigation]');
    const template = area.querySelector('[data-navigation-template]');
    const refresh = () => {
        [...rows.children].forEach((row, index) => {
            const kind = row.querySelector('[data-field="kind"]');
            const label = row.querySelector('[data-field="label_id"]').value.trim();
            row.querySelector('summary').textContent = label || kind.selectedOptions[0].textContent;
            row.querySelectorAll('[data-for-kind]').forEach(field => {
                field.hidden = field.dataset.forKind !== kind.value;
                field.querySelector('input, select').disabled = field.hidden;
            });
            row.querySelectorAll('[data-field]').forEach(input => {
                input.name = area.dataset.navigationArea + '[' + index + '][' + input.dataset.field + ']';
            });
        });
        add.disabled = rows.children.length >= Number(area.dataset.limit);
    };
    area.addEventListener('change', refresh);
    area.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button === add) {
            rows.append(template.content.cloneNode(true));
            refresh();
            rows.lastElementChild.open = true;
            rows.lastElementChild.querySelector('[data-field="kind"]').focus();
            return;
        }
        const row = button.closest('[data-navigation-row]');
        if (!row) return;
        if (button.hasAttribute('data-remove-navigation')) {
            row.remove();
            add.focus();
        } else {
            moveNavigationRow(row, button.dataset.move);
            button.focus();
        }
        refresh();
    });
    refresh();
});
document.querySelector('[data-footer-order]').addEventListener('click', event => {
    const button = event.target.closest('[data-move]');
    if (!button) return;
    moveNavigationRow(button.closest('li'), button.dataset.move);
    button.focus();
});
</script>
@endpush
