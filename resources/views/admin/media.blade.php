@extends('layouts.admin')
@section('title','Pustaka media')
@section('content')
<div class="admin-heading">
    <div><span class="eyebrow">GAMBAR WEBSITE</span><h1>Pustaka media</h1><p>Unggah gambar sekali, lalu pilih gambar tersebut saat menyusun halaman atau konten.</p></div>
    <a class="btn btn-outline-dark" href="{{ route('admin.resources','content') }}">Kelola banner & konten →</a>
</div>
<div class="context-help mb-4"><strong>Cara menggunakan gambar</strong><span>1. Unggah gambar dan isi deskripsi singkat. 2. Buka Banner & konten atau Halaman informasi. 3. Tambahkan blok Gambar, lalu pilih file dari pustaka ini.</span></div>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.media.upload') }}" class="panel mb-4">
    @csrf
    <div class="form-grid">
        <div><label for="media-image">Pilih gambar <span class="required-mark">*</span></label><input id="media-image" class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>@include('admin.partials.image-guideline',['preset'=>'media'])@error('image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
        <div><label for="media-alt">Deskripsi gambar (alt text) <span class="required-mark">*</span></label><input id="media-alt" class="form-control" name="alt_text" maxlength="200" required placeholder="Contoh: Koleksi gamis Dthree warna sage"><small>Jelaskan isi gambar agar mudah dicari dan ramah aksesibilitas.</small>@error('alt_text')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
    </div>
    <button class="btn btn-dark mt-3">Unggah gambar</button>
</form>
<div class="row g-4">
    @forelse($assets as $asset)
        <div class="col-md-6 col-xl-4"><article class="panel h-100">
            <img src="{{ $asset->url }}" alt="{{ $asset->alt_text }}" class="w-100 mb-3" style="aspect-ratio:4/3;object-fit:cover">
            <p class="small"><strong>{{ $asset->name }}</strong><br>{{ number_format($asset->size/1024,1,',','.') }} KB · {{ $asset->mime }} · {{ $asset->active?'Aktif':'Nonaktif' }}</p>
            <div class="input-group mb-3"><input class="form-control" readonly value="{{ $asset->url }}" aria-label="URL media"><button class="btn btn-outline-dark copy-media-url" type="button" data-url="{{ $asset->url }}">Salin URL</button></div>
            <form method="post" action="{{ route('admin.media.update',$asset) }}">@csrf
                <label for="alt-{{ $asset->id }}">Deskripsi gambar</label><input id="alt-{{ $asset->id }}" class="form-control mb-2" name="alt_text" value="{{ $asset->alt_text }}" required>
                <label class="checkbox-card"><input type="checkbox" name="active" value="1" @checked($asset->active)><span>Tampilkan dalam pilihan gambar di editor konten.</span></label>
                <button class="btn btn-outline-dark btn-sm mt-3">Simpan perubahan</button>
            </form>
        </article></div>
    @empty
        <div class="col-12"><div class="empty-state"><strong>Belum ada gambar.</strong><span>Unggah gambar pertama melalui formulir di atas.</span></div></div>
    @endforelse
</div>
<div class="mt-4">{{ $assets->links() }}</div>
@endsection
@push('scripts')
<script>document.querySelectorAll('.copy-media-url').forEach(button=>button.addEventListener('click',async()=>{await navigator.clipboard.writeText(button.dataset.url);const original=button.textContent;button.textContent='Tersalin ✓';setTimeout(()=>button.textContent=original,1600)}));</script>
@endpush
