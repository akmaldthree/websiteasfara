@php
    $types = $area === 'header' ? \App\Support\StorefrontNavigation::HEADER_TYPES : \App\Support\StorefrontNavigation::FOOTER_TYPES;
    $name = $area.'['.$index.']';
    $kind = $link['kind'] ?? 'link';
@endphp
<details class="panel mb-2" data-navigation-row @if($errors->any() && $index !== 'new') open @endif>
    <summary>{{ ($link['label_id'] ?? null) ?: ($types[$kind] ?? 'Tautan') }}</summary>
    <div class="form-grid mt-3">
        <label>Jenis tautan
            <select class="form-select" name="{{ $name }}[kind]" data-field="kind">
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" @selected($value === $kind)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if($area === 'header')
            <label>Perangkat
                <select class="form-select" name="{{ $name }}[placement]" data-field="placement">
                    @foreach(['both' => 'Desktop & HP', 'desktop' => 'Desktop', 'mobile' => 'HP'] as $value => $label)
                        <option value="{{ $value }}" @selected(($link['placement'] ?? 'both') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <label>Kolom footer
                <select class="form-select" name="{{ $name }}[group]" data-field="group">
                    @foreach(\App\Support\StorefrontNavigation::FOOTER_GROUPS as $value => $label)
                        <option value="{{ $value }}" @selected(($link['group'] ?? 'shop') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="span-2" data-for-kind="page">Halaman informasi
                <select class="form-select" name="{{ $name }}[page_id]" data-field="page_id">
                    <option value="">Pilih halaman</option>
                    @foreach($pages as $page)
                        <option value="{{ $page->id }}" @selected((int) ($link['page_id'] ?? 0) === $page->id)>{{ $page->title }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label>Label Indonesia<input class="form-control" name="{{ $name }}[label_id]" value="{{ $link['label_id'] ?? '' }}" maxlength="65" data-field="label_id"></label>
        <label>Label Inggris<input class="form-control" name="{{ $name }}[label_en]" value="{{ $link['label_en'] ?? '' }}" maxlength="65" data-field="label_en"></label>
        <label class="span-2" data-for-kind="link">URL tautan kustom<input class="form-control" name="{{ $name }}[url]" value="{{ $link['url'] ?? '' }}" maxlength="400" placeholder="/koleksi" data-field="url"></label>
        <label>
            <input type="hidden" name="{{ $name }}[enabled]" value="0" data-field="enabled">
            <input type="checkbox" name="{{ $name }}[enabled]" value="1" data-field="enabled" @checked($link['enabled'] ?? true)>
            Tampilkan tautan
        </label>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button type="button" class="btn btn-outline-dark btn-sm" data-move="-1">Naik</button>
            <button type="button" class="btn btn-outline-dark btn-sm" data-move="1">Turun</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-navigation>Hapus tautan</button>
        </div>
    </div>
</details>
