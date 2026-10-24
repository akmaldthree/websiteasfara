@extends('layouts.admin')
@section('title','Produk baru')
@section('content')
<div class="admin-heading"><div><span class="eyebrow">LANGKAH 1 DARI 3</span><h1>Buat produk baru</h1><p>Isi identitas dasar. Setelah disimpan, Anda dapat menambahkan varian, stok, dan foto.</p></div><a class="btn btn-outline-dark" href="{{ route('admin.products') }}">← Daftar produk</a></div>
<form class="panel product-create-panel" method="post" action="{{ route('admin.products.store') }}">@csrf
    <div class="form-grid">
        <div><label for="category_id">Kategori <span class="required-mark">*</span></label><select id="category_id" class="form-select" name="category_id" required><option value="">— Pilih kategori —</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id')===(string)$category->id)>{{ $category->name }}</option>@endforeach</select>@error('category_id')<div class="text-danger small">{{ $message }}</div>@enderror<small>Belum ada kategori? <a class="text-decoration-underline" href="{{ route('admin.create','categories') }}">Tambah kategori</a>.</small></div>
        <div><label for="name">Nama produk <span class="required-mark">*</span></label><input id="name" class="form-control" name="name" value="{{ old('name') }}" required placeholder="Contoh: Abaya Naira"></div>
        <div><label for="slug">Alamat produk <span class="required-mark">*</span></label><input id="slug" class="form-control" name="slug" value="{{ old('slug') }}" required><small>Dibuat otomatis dari nama dan masih dapat diubah.</small></div>
        <div><label for="compare_at_price">Harga normal / harga coret series</label><input id="compare_at_price" class="form-control" type="number" min="1" max="100000000" name="compare_at_price" value="{{ old('compare_at_price') }}" placeholder="Contoh: 480000"><small>Opsional. Harga jual sebenarnya diatur saat membuat varian.</small></div>
        <div class="span-2"><label for="description">Deskripsi singkat</label><textarea id="description" class="form-control" name="description" rows="5" placeholder="Jelaskan model, karakter, dan keunggulan produk.">{{ old('description') }}</textarea></div>
        <label class="checkbox-card"><input type="checkbox" name="uses_product_type" value="1" @checked(old('uses_product_type'))><span><strong>Produk memiliki jenis anggota keluarga</strong><br><small>Aktifkan untuk sarimbit atau produk dengan tipe Ayah, Ibu, dan Anak.</small></span></label>
        <label class="checkbox-card"><input type="checkbox" name="active" value="1" @checked(old('active'))><span><strong>Langsung tayangkan</strong><br><small>Disarankan tetap draft sampai varian dan foto lengkap.</small></span></label>
    </div>
    <div class="form-actions"><a href="{{ route('admin.products') }}">Batal</a><button class="btn btn-dark">Simpan & lanjut ke varian →</button></div>
</form>
@endsection
@push('scripts')<script>const nameInput=document.getElementById('name');const slugInput=document.getElementById('slug');nameInput.addEventListener('input',()=>{if(!slugInput.dataset.edited)slugInput.value=nameInput.value.toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')});slugInput.addEventListener('input',()=>slugInput.dataset.edited='1');</script>@endpush
