@extends('layouts.admin')
@section('title','Kelola '.$product->name)
@section('content')
@php
$hasPhoto=(bool)$product->image || $product->media->isNotEmpty();$hasVariant=$product->variants->isNotEmpty();$hasDescription=filled($product->description);$ready=collect([$hasPhoto,$hasVariant,$hasDescription])->filter()->count();$variantMap=$product->variants->keyBy('id');$batchGroups=old('groups',[['color'=>'','sizes'=>'','alt_text'=>'']]);
@endphp
<div class="admin-heading">
    <div><span class="eyebrow">PRODUCT WORKSPACE</span><h1>{{ $product->name }}</h1><p>{{ $product->category?->name }} · {{ $product->variants->count() }} varian · {{ $product->active?'Tayang':'Draft' }}</p></div>
    <div class="d-flex gap-2 flex-wrap">@if($product->active)<a class="btn btn-outline-dark" href="{{ route('product',$product->slug) }}" target="_blank" rel="noopener">Lihat produk ↗</a>@endif<form method="post" action="{{ route('admin.products.duplicate',$product) }}">@csrf<button class="btn btn-outline-dark">Duplikat produk</button></form><form method="post" action="{{ route('admin.destroy',['series',$product->id]) }}" onsubmit="return confirm('Hapus produk beserta varian dan fotonya secara permanen?')">@csrf @method('DELETE')<input type="hidden" name="return_to" value="products"><button class="btn btn-outline-danger">Hapus</button></form><a class="btn btn-dark" href="{{ route('admin.products') }}">Selesai</a></div>
</div>
<div class="workspace-summary mb-4"><div><strong>Kesiapan produk: {{ $ready }}/3</strong><span @class(['complete'=>$hasDescription])>{{ $hasDescription?'✓':'○' }} Deskripsi</span><span @class(['complete'=>$hasVariant])>{{ $hasVariant?'✓':'○' }} Varian & harga</span><span @class(['complete'=>$hasPhoto])>{{ $hasPhoto?'✓':'○' }} Foto</span></div>@if($ready<3)<p>Lengkapi bagian yang masih kosong sebelum menayangkan produk.</p>@else<p>Informasi utama sudah lengkap. Periksa preview sebelum tayang.</p>@endif</div>
<nav class="workspace-tabs" aria-label="Bagian produk"><a href="#basic">1. Informasi</a><a href="#variants">2. Varian & stok <span>{{ $product->variants->count() }}</span></a><a href="#media">3. Foto <span>{{ $product->media->count() }}</span></a><a href="#advanced">Pengaturan lanjutan</a></nav>

<section class="panel workspace-section" id="basic"><div class="section-top"><div><span class="eyebrow">LANGKAH 1</span><h2>Informasi produk</h2></div><span class="badge">{{ $product->active?'Tayang':'Draft' }}</span></div>
<form method="post" action="{{ route('admin.products.update',$product) }}">@csrf<input type="hidden" name="section" value="basic"><div class="form-grid">
    <div><label for="category_id">Kategori <span class="required-mark">*</span></label><select id="category_id" class="form-select" name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id',$product->category_id)===(string)$category->id)>{{ $category->name }}</option>@endforeach</select><small><a href="{{ route('admin.create','categories') }}">Kelola kategori ↗</a></small></div>
    <div><label for="name">Nama produk <span class="required-mark">*</span></label><input id="name" class="form-control" name="name" value="{{ old('name',$product->name) }}" required></div>
    <div><label for="slug">Alamat produk <span class="required-mark">*</span></label><input id="slug" class="form-control" name="slug" value="{{ old('slug',$product->slug) }}" required></div>
    <div><label for="compare_at_price">Harga normal / harga coret series</label><input id="compare_at_price" class="form-control" type="number" min="1" max="100000000" name="compare_at_price" value="{{ old('compare_at_price',$product->compare_at_price) }}" placeholder="Contoh: 480000"><small>Kosongkan jika tidak ada diskon. Harus lebih tinggi dari harga jual terendah{{ $product->variants->isNotEmpty() ? ' (saat ini Rp '.number_format($product->variants->min('price'),0,',','.').')' : '' }}.</small>@error('compare_at_price')<div class="text-danger small">{{ $message }}</div>@enderror</div>
    <div><label>Foto utama</label>@if($product->image)<div class="current-product-image"><img src="{{ $product->image_url }}" alt=""><small>Foto utama saat ini · <a href="#media">ubah di bagian Foto produk</a></small></div>@else<div class="admin-help">Foto utama dipilih saat membuat varian dan foto, atau melalui bagian <a href="#media">Foto produk</a>.</div>@endif</div>
    <div class="span-2"><label for="description">Deskripsi produk</label><textarea id="description" class="form-control" name="description" rows="5">{{ old('description',$product->description) }}</textarea></div>
    <div><label for="material">Bahan</label><textarea id="material" class="form-control" name="material" rows="4">{{ old('material',$product->material) }}</textarea></div><div><label for="care">Petunjuk perawatan</label><textarea id="care" class="form-control" name="care" rows="4">{{ old('care',$product->care) }}</textarea></div>
    <div class="span-2"><label for="size_guide">Panduan ukuran</label><textarea id="size_guide" class="form-control" name="size_guide" rows="4">{{ old('size_guide',$product->size_guide) }}</textarea></div>
    <div><label for="seo_title">Judul SEO</label><input id="seo_title" class="form-control" name="seo_title" value="{{ old('seo_title',$product->seo_title) }}"></div><div><label for="seo_description">Deskripsi SEO</label><textarea id="seo_description" class="form-control" name="seo_description" rows="3">{{ old('seo_description',$product->seo_description) }}</textarea></div>
    <label class="checkbox-card"><input type="checkbox" name="uses_product_type" value="1" @checked(old('uses_product_type',$product->uses_product_type)) @disabled($hasVariant)><span>Gunakan jenis produk untuk Sarimbit @if($hasVariant)<small class="d-block">Dikunci setelah varian dibuat.</small>@endif</span></label>@if($hasVariant)<input type="hidden" name="uses_product_type" value="{{ $product->uses_product_type?1:0 }}">@endif
    <label class="checkbox-card"><input type="checkbox" name="featured" value="1" @checked(old('featured',$product->featured))><span>Tampilkan sebagai produk unggulan</span></label>
    <label class="checkbox-card"><input type="checkbox" name="active" value="1" @checked(old('active',$product->active))><span>Tayangkan produk di website</span></label>
</div><div class="workspace-save"><button class="btn btn-dark">Simpan informasi</button></div></form></section>

<section class="panel workspace-section" id="variants"><div class="section-top"><div><span class="eyebrow">LANGKAH 2</span><h2>Varian, harga & stok</h2><p class="small mb-0">Edit semua SKU lalu simpan sekali. Perubahan stok selalu memerlukan alasan dan masuk audit.</p></div><a class="btn btn-outline-dark btn-sm" href="{{ route('admin.import',['type'=>'variants']) }}">Import Excel</a></div>
@if($product->variants->isNotEmpty())
<form method="post" action="{{ route('admin.products.variants.save',$product) }}">@csrf<div class="table-responsive"><table class="table workspace-variant-table"><thead><tr><th>SKU & atribut</th><th>Harga & berat</th><th>Pemenuhan</th><th>Stok</th><th>Status</th></tr></thead><tbody>
@foreach($product->variants as $index=>$variant)<tr><td><input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant->id }}"><label>SKU</label><input class="form-control" name="variants[{{ $index }}][sku]" value="{{ $variant->sku }}" required>@if($product->uses_product_type)<label>Jenis</label><input class="form-control" name="variants[{{ $index }}][product_type]" value="{{ $variant->product_type }}" required>@else<input type="hidden" name="variants[{{ $index }}][product_type]" value="">@endif<div class="d-flex gap-2"><div><label>Warna</label><input class="form-control" name="variants[{{ $index }}][color]" value="{{ $variant->color }}" required></div><div><label>Ukuran</label><input class="form-control" name="variants[{{ $index }}][size]" value="{{ $variant->size }}" required></div></div></td>
<td><label>Harga</label><input class="form-control" type="number" name="variants[{{ $index }}][price]" value="{{ $variant->price }}" required><label>Berat (gram)</label><input class="form-control" type="number" name="variants[{{ $index }}][weight]" value="{{ $variant->weight }}" required><label>Batas stok rendah</label><input class="form-control" type="number" name="variants[{{ $index }}][low_stock]" value="{{ $variant->low_stock }}" required></td>
<td><label>Metode</label><select class="form-select" name="variants[{{ $index }}][fulfillment]">@foreach(['ready_stock'=>'Ready stock','pre_order'=>'Pre-order','custom_made'=>'Ukuran custom','bundle'=>'Bundle'] as $value=>$label)<option value="{{ $value }}" @selected($variant->fulfillment===$value)>{{ $label }}</option>@endforeach</select><label>Template ukuran</label><select class="form-select" name="variants[{{ $index }}][measurement_template_id]"><option value="">— Tidak ada —</option>@foreach($measurementTemplates as $template)<option value="{{ $template->id }}" @selected($variant->measurement_template_id===$template->id)>{{ $template->name }}</option>@endforeach</select><label>Lead time</label><input class="form-control" name="variants[{{ $index }}][lead_time]" value="{{ $variant->lead_time }}"></td>
<td><div class="stock-balance"><strong>{{ $variant->stock }}</strong><span>{{ $variant->reserved }} reservasi · {{ $variant->available }} tersedia</span></div><label>Tambah / kurangi</label><input class="form-control" type="number" name="variants[{{ $index }}][stock_delta]" placeholder="Contoh: 10 atau -2"><label>Alasan</label><input class="form-control" name="variants[{{ $index }}][stock_reason]" placeholder="Wajib jika stok berubah"></td>
<td><input type="hidden" name="variants[{{ $index }}][active]" value="0"><label class="checkbox-card"><input type="checkbox" name="variants[{{ $index }}][active]" value="1" @checked($variant->active)><span>Aktif</span></label></td></tr>@endforeach
</tbody></table></div><div class="workspace-save"><button class="btn btn-dark">Simpan semua varian</button></div></form>
@else<div class="admin-help">Belum ada varian. Gunakan “Buat varian dan foto sekaligus” untuk mengisi produk ini dengan cepat.</div>@endif

<details class="panel workspace-batch-builder mt-4" @if(!$hasVariant || old('sku_prefix')) open @endif>
<summary><span><strong>+ Buat varian dan foto sekaligus</strong><small>Satu proses untuk banyak warna, ukuran, SKU, stok, dan foto.</small></span><b>Direkomendasikan</b></summary>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.products.variants-media.create',$product) }}" class="mt-4">@csrf
    <div class="workspace-batch-basics form-grid">
        <div><label>Awalan SKU</label><input class="form-control" name="sku_prefix" value="{{ old('sku_prefix') }}" required placeholder="{{ strtoupper(Str::limit($product->slug,12,'')) }}"></div>
        @if($product->uses_product_type)<div><label>Jenis produk, pisahkan koma</label><input class="form-control" name="product_types" value="{{ old('product_types') }}" required placeholder="Ayah, Ibu, Anak"></div>@endif
        <div><label>Harga jual semua varian</label><input class="form-control" type="number" min="1" name="price" value="{{ old('price') }}" required></div>
        <div><label>Stok awal per SKU</label><input class="form-control" type="number" min="0" name="stock" value="{{ old('stock',0) }}" required></div>
        <div><label>Berat per SKU (gram)</label><input class="form-control" type="number" min="1" name="weight" value="{{ old('weight',500) }}" required></div>
        <div><label>Batas stok rendah</label><input class="form-control" type="number" min="0" name="low_stock" value="{{ old('low_stock',5) }}" required></div>
        <div><label>Metode</label><select class="form-select" name="fulfillment"><option value="ready_stock" @selected(old('fulfillment')==='ready_stock')>Ready stock</option><option value="pre_order" @selected(old('fulfillment')==='pre_order')>Pre-order</option></select></div>
        <div><label>Lead time</label><input class="form-control" name="lead_time" value="{{ old('lead_time') }}" placeholder="Opsional"></div>
    </div>
    <div class="batch-color-heading"><div><h3>Kelompok warna</h3><p class="small mb-0">Masukkan ukuran dan semua foto yang sesuai untuk setiap warna.</p></div><button type="button" class="btn btn-outline-dark btn-sm" data-add-color>+ Tambah warna</button></div>
    <div class="batch-color-groups" data-color-groups>
        @foreach($batchGroups as $batchIndex=>$batchGroup)<article class="batch-color-group" data-color-group>
            <div class="batch-color-number">Warna <span>{{ $loop->iteration }}</span></div>
            <div><label>Nama warna</label><input class="form-control" name="groups[{{ $batchIndex }}][color]" value="{{ $batchGroup['color'] ?? '' }}" required placeholder="Contoh: Sage"></div>
            <div><label>Ukuran, pisahkan koma</label><input class="form-control" name="groups[{{ $batchIndex }}][sizes]" value="{{ $batchGroup['sizes'] ?? '' }}" required placeholder="S, M, L, XL"></div>
            <div class="batch-file-field"><label>Foto warna ini</label><input class="form-control" type="file" name="groups[{{ $batchIndex }}][images][]" accept="image/jpeg,image/png,image/webp" multiple data-group-images><small>Opsional · maksimal 10 foto per warna.</small>@include('admin.partials.image-guideline',['preset'=>'product'])</div>
            <div><label>Deskripsi foto</label><input class="form-control" name="groups[{{ $batchIndex }}][alt_text]" value="{{ $batchGroup['alt_text'] ?? '' }}" placeholder="Contoh: {{ $product->name }} warna Sage"></div>
            <div class="batch-image-preview" data-group-preview hidden></div>
            <button type="button" class="batch-remove-color" data-remove-color @if(count($batchGroups)===1) hidden @endif>Hapus warna</button>
        </article>@endforeach
    </div>
    <template id="batch-color-template"><article class="batch-color-group" data-color-group>
        <div class="batch-color-number">Warna <span>__NUMBER__</span></div>
        <div><label>Nama warna</label><input class="form-control" name="groups[__INDEX__][color]" required placeholder="Contoh: Broken White"></div>
        <div><label>Ukuran, pisahkan koma</label><input class="form-control" name="groups[__INDEX__][sizes]" required placeholder="S, M, L, XL"></div>
        <div class="batch-file-field"><label>Foto warna ini</label><input class="form-control" type="file" name="groups[__INDEX__][images][]" accept="image/jpeg,image/png,image/webp" multiple data-group-images><small>Opsional · maksimal 10 foto per warna.</small>@include('admin.partials.image-guideline',['preset'=>'product'])</div>
        <div><label>Deskripsi foto</label><input class="form-control" name="groups[__INDEX__][alt_text]" placeholder="Deskripsi warna ini"></div>
        <div class="batch-image-preview" data-group-preview hidden></div>
        <button type="button" class="batch-remove-color" data-remove-color>Hapus warna</button>
    </article></template>
    <div class="workspace-save"><label class="checkbox-card mb-0"><input type="checkbox" name="set_first_as_main" value="1" @checked(!$product->image)><span>Jadikan foto pertama sebagai foto utama</span></label><button class="btn btn-dark">Buat semua varian & simpan foto</button></div>
</form>
</details>

<div class="workspace-tools">
<details class="panel" @if(!$hasVariant) open @endif><summary><strong>+ Tambah satu varian</strong><span>Cocok untuk produk sederhana</span></summary><form method="post" action="{{ route('admin.products.variants.add',$product) }}" class="form-grid mt-4">@csrf
    <div><label>SKU</label><input class="form-control" name="sku" required placeholder="NAIRA-BLK-M"></div>@if($product->uses_product_type)<div><label>Jenis produk</label><input class="form-control" name="product_type" required placeholder="Ibu"></div>@else<input type="hidden" name="product_type" value="">@endif<div><label>Warna</label><input class="form-control" name="color" required></div><div><label>Ukuran</label><input class="form-control" name="size" required></div><div><label>Harga</label><input class="form-control" type="number" name="price" required></div><div><label>Stok awal</label><input class="form-control" type="number" min="0" name="stock" value="0" required></div><div><label>Berat (gram)</label><input class="form-control" type="number" min="1" name="weight" value="500" required></div><div><label>Batas stok rendah</label><input class="form-control" type="number" min="0" name="low_stock" value="5" required></div><div><label>Metode</label><select class="form-select" name="fulfillment"><option value="ready_stock">Ready stock</option><option value="pre_order">Pre-order</option><option value="custom_made">Ukuran custom</option><option value="bundle">Bundle</option></select></div><div><label>Template ukuran</label><select class="form-select" name="measurement_template_id"><option value="">— Tidak ada —</option>@foreach($measurementTemplates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select></div><div><label>Lead time</label><input class="form-control" name="lead_time"></div><label class="checkbox-card"><input type="checkbox" name="active" value="1" checked><span>Varian aktif</span></label><div class="span-2"><button class="btn btn-dark">Tambah varian</button></div>
</form></details>
</div></section>

<section class="panel workspace-section" id="media">
<div class="section-top"><div><span class="eyebrow">LANGKAH 3</span><h2>Foto produk</h2><p class="small mb-0">Unggah banyak foto lalu atur seluruh galeri series ini tanpa pindah halaman.</p></div><span class="badge">{{ $product->media->count() }} foto</span></div>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.products.media.upload',$product) }}" class="workspace-media-upload">@csrf
    <label class="workspace-media-drop" for="images"><strong>+ Pilih banyak foto sekaligus</strong><span>JPG, PNG, atau WebP · maksimal 10 foto dan 5 MB per foto</span>@include('admin.partials.image-guideline',['preset'=>'product'])<input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
    <div id="media-upload-preview" class="workspace-upload-preview" hidden></div>
    <div class="workspace-media-defaults">
        <div><label for="media-color">Warna untuk semua foto baru</label><input id="media-color" class="form-control" name="color" placeholder="Opsional, contoh: Sage"></div>
        <div><label for="media-variant">Varian / SKU untuk semua foto baru</label><select id="media-variant" class="form-select" name="variant_id"><option value="">Semua varian</option>@foreach($product->variants as $variant)<option value="{{ $variant->id }}">{{ $variant->sku }} · {{ $variant->color }} / {{ $variant->size }}</option>@endforeach</select></div>
        <div><label for="alt_text">Deskripsi umum</label><input id="alt_text" class="form-control" name="alt_text" placeholder="Otomatis dari nama file jika kosong"></div>
        <label class="checkbox-card media-main-check"><input type="checkbox" name="set_first_as_main" value="1" @checked(!$product->image)><span>Jadikan foto pertama sebagai foto utama</span></label>
    </div>
    <div class="workspace-save"><button class="btn btn-dark">Unggah semua foto</button></div>
</form>
@if($product->media->isNotEmpty())
<form method="post" action="{{ route('admin.products.media.manage',$product) }}" class="workspace-media-manager mt-4">@csrf
    <div class="media-manager-heading"><div><h3>Atur galeri</h3><p class="small mb-0">Edit keterangan, kaitkan dengan warna atau SKU, pilih foto utama, lalu simpan satu kali.</p></div><button class="btn btn-dark">Simpan semua foto</button></div>
    <div class="workspace-media-list">
    @foreach($product->media as $index=>$media)
        <article class="workspace-media-row">
            <input type="hidden" name="media[{{ $index }}][id]" value="{{ $media->id }}">
            <div class="workspace-media-thumb"><img src="{{ $media->image_url }}" alt=""><label><input type="radio" name="main_media_id" value="{{ $media->id }}" @checked($product->image===$media->image)> Foto utama</label></div>
            <div class="workspace-media-fields">
                <div class="media-field-wide"><label>Deskripsi gambar</label><input class="form-control" name="media[{{ $index }}][alt_text]" value="{{ $media->alt_text }}" required></div>
                <div><label>Warna</label><input class="form-control" name="media[{{ $index }}][color]" value="{{ $media->color }}" placeholder="Semua warna"></div>
                <div><label>Varian / SKU</label><select class="form-select" name="media[{{ $index }}][variant_id]"><option value="">Semua varian</option>@foreach($product->variants as $variant)<option value="{{ $variant->id }}" @selected($media->variant_id===$variant->id)>{{ $variant->sku }}</option>@endforeach</select></div>
                <div><label>Urutan</label><input class="form-control" type="number" min="0" max="10000" name="media[{{ $index }}][sort_order]" value="{{ $media->sort_order }}" required></div>
            </div>
            <div class="workspace-media-actions"><input type="hidden" name="media[{{ $index }}][active]" value="0"><label><input type="checkbox" name="media[{{ $index }}][active]" value="1" @checked($media->active)> Aktif</label><label class="media-delete"><input type="checkbox" name="media[{{ $index }}][delete]" value="1"> Hapus</label></div>
        </article>
    @endforeach
    </div>
    <div class="workspace-save"><span class="small">Perubahan belum diterapkan sampai tombol disimpan.</span><button class="btn btn-dark">Simpan semua foto</button></div>
</form>
@else<div class="admin-empty mt-4"><strong>Belum ada foto galeri.</strong><span>Pilih beberapa gambar di atas untuk mengisi galeri produk ini.</span></div>@endif
</section>

<section class="panel workspace-section" id="advanced"><div class="section-top"><div><span class="eyebrow">OPSIONAL</span><h2>Pengaturan lanjutan</h2><p class="small mb-0">Fitur khusus tetap tersedia dan langsung difilter berdasarkan produk saat memungkinkan.</p></div></div><div class="admin-quick-grid"><a class="admin-quick-card" href="{{ route('admin.resources','variant-prices') }}"><strong>Harga bisnis</strong><span>Harga reseller, dropshipper, dan tier kuantitas.</span><small>Buka pengaturan →</small></a><a class="admin-quick-card" href="{{ route('admin.resources','bundle-components') }}"><strong>Komponen bundle</strong><span>Hubungkan SKU bundle dengan komponen penyusunnya.</span><small>Buka pengaturan →</small></a><a class="admin-quick-card" href="{{ route('admin.resources','measurement-templates') }}"><strong>Ukuran custom</strong><span>Template ukuran, surcharge, dan lead time.</span><small>Buka pengaturan →</small></a><a class="admin-quick-card" href="{{ route('admin.inventory',['q'=>$product->variants->first()?->sku]) }}"><strong>Riwayat inventori</strong><span>Lihat saldo, reservasi, dan pergerakan stok.</span><small>Buka inventori →</small></a></div>
@if($movements->isNotEmpty())<div class="mt-4"><h3>Pergerakan stok terakhir</h3><div class="table-responsive"><table class="table"><thead><tr><th>Waktu</th><th>SKU</th><th>Perubahan</th><th>Alasan</th></tr></thead><tbody>@foreach($movements as $movement)<tr><td>{{ $movement->created_at }}</td><td>{{ $variantMap[$movement->variant_id]?->sku }}</td><td>{{ $movement->stock_delta>0?'+':'' }}{{ $movement->stock_delta }}</td><td>{{ $movement->reason }}</td></tr>@endforeach</tbody></table></div></div>@endif</section>
@endsection
@push('scripts')<script>
const section=new URLSearchParams(location.search).get('section');if(section)document.getElementById(section)?.scrollIntoView({behavior:'smooth'});
const mediaInput=document.getElementById('images');const mediaPreview=document.getElementById('media-upload-preview');let mediaPreviewUrls=[];
mediaInput?.addEventListener('change',()=>{mediaPreviewUrls.forEach(URL.revokeObjectURL);mediaPreviewUrls=[];mediaPreview.replaceChildren();const files=[...mediaInput.files];mediaPreview.hidden=!files.length;files.forEach((file,index)=>{const url=URL.createObjectURL(file);mediaPreviewUrls.push(url);const item=document.createElement('div');const img=document.createElement('img');const text=document.createElement('span');img.src=url;img.alt='Pratinjau '+(index+1);text.textContent=(index+1)+'. '+file.name;item.append(img,text);mediaPreview.append(item)})});
document.querySelectorAll('.workspace-media-row .media-delete input').forEach(input=>input.addEventListener('change',()=>input.closest('.workspace-media-row').classList.toggle('is-deleting',input.checked)));
const colorGroups=document.querySelector('[data-color-groups]');const colorTemplate=document.getElementById('batch-color-template');const previewUrls=new WeakMap();
function bindColorGroup(group){const input=group.querySelector('[data-group-images]');const preview=group.querySelector('[data-group-preview]');input?.addEventListener('change',()=>{(previewUrls.get(input)||[]).forEach(URL.revokeObjectURL);const urls=[];preview.replaceChildren();preview.hidden=!input.files.length;[...input.files].forEach(file=>{const url=URL.createObjectURL(file);urls.push(url);const img=document.createElement('img');img.src=url;img.alt=file.name;img.title=file.name;preview.append(img)});previewUrls.set(input,urls)});group.querySelector('[data-remove-color]')?.addEventListener('click',()=>group.remove())}
colorGroups?.querySelectorAll('[data-color-group]').forEach(bindColorGroup);document.querySelector('[data-add-color]')?.addEventListener('click',()=>{const index=Date.now();const number=colorGroups.children.length+1;colorGroups.insertAdjacentHTML('beforeend',colorTemplate.innerHTML.replaceAll('__INDEX__',index).replaceAll('__NUMBER__',number));bindColorGroup(colorGroups.lastElementChild)});
</script>@endpush
