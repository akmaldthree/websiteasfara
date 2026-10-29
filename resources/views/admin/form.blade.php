@extends('layouts.admin')
@section('title',$schema['title'])
@section('content')
@php
$hints=[
    'slug'=>'Alamat singkat untuk URL. Gunakan huruf kecil, angka, dan tanda hubung. Bisa dibuat otomatis dari nama atau judul.',
    'sku'=>'Kode unik varian untuk stok dan pesanan. Contoh: RAF-BLK-M.',
    'uses_product_type'=>'Aktifkan untuk produk seperti sarimbit yang memiliki jenis Ayah, Ibu, atau Anak.',
    'featured'=>'Produk aktif yang ditandai akan diprioritaskan di beranda.',
    'active'=>'Data nonaktif tetap tersimpan tetapi tidak ditampilkan kepada pelanggan.',
    'editorial_status'=>'Simpan sebagai Draft saat mengerjakan, Menunggu review saat siap diperiksa, atau Disetujui untuk diterbitkan.',
    'starts_at'=>'Kosongkan jika konten boleh tampil segera setelah aktif dan disetujui.',
    'ends_at'=>'Kosongkan jika tidak memiliki tanggal berakhir.',
    'sort_order'=>'Angka lebih kecil ditampilkan lebih dahulu.',
    'target_id'=>'Masukkan ID sesuai jenis target. ID terlihat pada URL halaman edit data terkait.',
    'fields'=>'Format JSON untuk field ukuran. Gunakan template contoh dari Import Excel jika datanya banyak.',
    'minimum_quantity'=>'Tier berlaku mulai jumlah item ini.',
];
@endphp
<div class="admin-heading">
    <div><span class="eyebrow">{{ $record->exists?'EDIT DATA':'DATA BARU' }}</span><h1>{{ $record->exists?'Edit':'Tambah' }} {{ strtolower($schema['title']) }}</h1><p>Kolom bertanda <span class="required-mark">*</span> wajib diisi. Anda dapat menyimpan tanpa keluar untuk melanjutkan pekerjaan.</p></div>
    <a class="btn btn-outline-dark" href="{{ route('admin.resources',$resource) }}">← Kembali ke daftar</a>
</div>
<form id="admin-record-form" method="post" enctype="multipart/form-data" action="{{ $record->exists?route('admin.update',[$resource,$record->id]):route('admin.store',$resource) }}" class="panel">
    @csrf
    <div class="form-grid">
        @foreach($schema['fields'] as $key=>$field)
            @php($value=old($key,$record->$key))
            @php($required=str_contains($field[2],'required'))
            <div @class(['field-wrap','span-2'=>in_array($field[1],['textarea','blocks','size_chart'])])>
                <label for="{{ $key }}">{{ $field[0] }}@if($required)<span class="required-mark" aria-label="wajib">*</span>@endif</label>
                @if($field[1]==='textarea')
                    <textarea id="{{ $key }}" name="{{ $key }}" class="form-control" rows="5">{{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : $value }}</textarea>
                @elseif($field[1]==='blocks')
                    <input type="hidden" id="blocks" name="blocks" value="{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : $value }}">
                    <div id="content-block-editor" class="d-grid gap-3"></div>
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <select id="new-block-type" class="form-select" style="max-width:300px"><option value="paragraph">Paragraf</option><option value="heading">Subjudul</option><option value="quote">Kutipan</option><option value="button">Tombol</option><option value="image">Gambar dari URL</option><option value="columns">Dua kolom</option><option value="product_hotspot">Hotspot produk (Shop the Look)</option><option value="hero_options">Pengaturan slide hero</option></select>
                        <button id="add-content-block" type="button" class="btn btn-outline-dark">+ Tambah blok</button>
                    </div>
                    <small>Susun konten dengan tombol naik/turun. Teks selalu ditampilkan secara aman; blok tombol dan gambar menerima URL HTTPS atau path internal.</small>
                @elseif($field[1]==='size_chart')
                    @php($chart=is_array($value)?$value:(json_decode((string)$value,true)?:[]))
                    <input type="hidden" id="size_chart" name="size_chart" value="{{ $chart ? json_encode($chart,JSON_UNESCAPED_UNICODE) : '' }}">
                    <div class="panel size-chart-editor" data-size-chart-editor>
                        <label for="size-chart-columns">Nama kolom, pisahkan dengan tanda |</label><input class="form-control" id="size-chart-columns" value="{{ implode(' | ',$chart['columns']??['Ukuran','Lingkar dada','Panjang']) }}" placeholder="Ukuran | Lingkar dada | Panjang">
                        <label class="mt-3" for="size-chart-rows">Data ukuran, satu baris per ukuran</label><textarea class="form-control" id="size-chart-rows" rows="7" placeholder="S | 96 cm | 136 cm&#10;M | 102 cm | 138 cm">@foreach($chart['rows']??[] as $row){{ implode(' | ',$row) }}{{ !$loop->last?"\n":'' }}@endforeach</textarea>
                        <label class="mt-3" for="size-chart-notes">Keterangan singkatan / catatan</label><textarea class="form-control" id="size-chart-notes" rows="3">{{ $chart['notes']??'' }}</textarea><small>Gunakan tanda | untuk memisahkan kolom. Pratinjau tabel diperbarui otomatis.</small><div class="table-responsive mt-3" id="size-chart-preview"></div>
                    </div>
                @elseif(in_array($field[1],['select','category','series','variant','price_group','measurement_template']))
                    @php($options=match($field[1]){
                        'category'=>\App\Models\Category::pluck('name','id'),
                        'series'=>\App\Models\Series::pluck('name','id'),
                        'variant'=>\App\Models\Variant::orderBy('sku')->pluck('sku','id'),
                        'price_group'=>\App\Models\PriceGroup::orderBy('name')->pluck('name','id'),
                        'measurement_template'=>[''=>'— Pilih template —']+\App\Models\MeasurementTemplate::orderBy('name')->pluck('name','id')->all(),
                        default=>$field[3],
                    })
                    @php($options=is_object($options)?$options->all():$options)
                    @php($options=str_contains($field[2],'nullable')&&!array_key_exists('',$options)?[''=>'— Semua / tidak dibatasi —']+$options:$options)
                    <select id="{{ $key }}" name="{{ $key }}" class="form-select">
                        @foreach($options as $k=>$label)<option value="{{ $k }}" @selected((string)$value===(string)$k) @disabled($key==='editorial_status' && $k==='approved' && !auth()->user()->allowed('content.approve'))>{{ $label }}</option>@endforeach
                    </select>
                    @if($key==='editorial_status' && !auth()->user()->allowed('content.approve'))<small>Anda dapat menyimpan draft atau mengajukannya untuk review. Persetujuan dilakukan oleh pengguna dengan izin persetujuan konten.</small>@endif
                @elseif($field[1]==='checkbox')
                    <div class="checkbox-card"><input id="{{ $key }}" type="checkbox" name="{{ $key }}" value="1" @checked($value)><span>{{ $value?'Saat ini aktif.':'Saat ini nonaktif.' }} Klik untuk mengubah status.</span></div>
                @elseif($field[1]==='file')
                    <input class="form-control" id="{{ $key }}" name="{{ $key }}" type="file" accept="image/png,image/jpeg,image/webp">
                    @php($imagePreset=match($resource){'curated-links'=>'promo_card','product-media','series'=>'product','content'=>'content',default=>'general'})
                    @include('admin.partials.image-guideline',['preset'=>$imagePreset])
                    @if($record->exists && $value)<div class="mt-2 d-flex align-items-center gap-2"><img src="{{ asset('storage/'.$value) }}" alt="Pratinjau gambar saat ini" width="64" height="64" style="object-fit:cover"><small>Gambar saat ini. Pilih file baru hanya jika ingin menggantinya.</small></div>@endif
                @else
                    <input id="{{ $key }}" name="{{ $key }}" type="{{ $field[1] }}" class="form-control" value="{{ $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d\TH:i') : $value }}" @required($required)>
                @endif
                @if(isset($hints[$key]))<small class="field-help">{{ $hints[$key] }}</small>@endif
                @error($key)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        @endforeach
    </div>
    <div class="form-actions"><a href="{{ route('admin.resources',$resource) }}">Batal</a><div><button class="btn btn-outline-dark" name="after_save" value="new">Simpan & tambah lagi</button><button class="btn btn-outline-dark" name="after_save" value="edit">Simpan tanpa keluar</button><button class="btn btn-dark" name="after_save" value="list">Simpan & kembali</button></div></div>
</form>
@if($record->exists)
<section class="panel mt-4 border-danger-subtle"><div class="section-top"><div><h2 class="mb-1">Hapus data</h2><p class="small mb-0">Data yang sudah dipakai transaksi mungkin tidak dapat dihapus dan sebaiknya dinonaktifkan agar riwayat tetap utuh.</p></div><form method="post" action="{{ route('admin.destroy',[$resource,$record->id]) }}" onsubmit="return confirm('Hapus data ini secara permanen? Tindakan ini tidak dapat dibatalkan.')">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit">Hapus permanen</button></form></div></section>
@endif
@endsection
@push('scripts')
<script>
const slugInput=document.getElementById('slug');const slugSource=document.getElementById('name')||document.getElementById('title');if(slugInput&&slugSource&&!slugInput.value){slugSource.addEventListener('input',()=>{slugInput.value=slugSource.value.toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')})}
</script>
@endpush
@if(array_key_exists('size_chart',$schema['fields']))
@push('scripts')<script>(()=>{const target=document.getElementById('size_chart'),columns=document.getElementById('size-chart-columns'),rows=document.getElementById('size-chart-rows'),notes=document.getElementById('size-chart-notes'),preview=document.getElementById('size-chart-preview');const esc=value=>String(value).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));const sync=()=>{const columnValues=columns.value.split('|').map(value=>value.trim()).filter(Boolean);const rowValues=rows.value.split(/\r?\n/).map(line=>line.split('|').map(value=>value.trim())).filter(row=>row.some(Boolean));target.value=columnValues.length&&rowValues.length?JSON.stringify({columns:columnValues,rows:rowValues,notes:notes.value.trim()}):'';preview.innerHTML=columnValues.length?`<table class="table"><thead><tr>${columnValues.map(value=>`<th>${esc(value)}</th>`).join('')}</tr></thead><tbody>${rowValues.map(row=>`<tr>${columnValues.map((_,index)=>`<td>${esc(row[index]||'')}</td>`).join('')}</tr>`).join('')}</tbody></table>`:''};[columns,rows,notes].forEach(input=>input.addEventListener('input',sync));sync()})();</script>@endpush
@endif
@if(array_key_exists('blocks',$schema['fields']))
@php($availableMedia=\App\Models\MediaAsset::where('active',true)->latest()->get()->map(fn($asset)=>['url'=>$asset->url,'alt'=>$asset->alt_text,'name'=>$asset->name])->values()->all())
@php($availableSeries=\App\Models\Series::orderBy('name')->get(['id','name'])->map(fn($series)=>['id'=>$series->id,'name'=>$series->name])->values()->all())
@push('scripts')
<script>
const blockInput=document.getElementById('blocks');const blockEditor=document.getElementById('content-block-editor');const mediaAssets=@json($availableMedia);const productSeries=@json($availableSeries);let contentBlocks=[];
try{contentBlocks=JSON.parse(blockInput.value||'[]')}catch(e){contentBlocks=[]}
function syncBlocks(){blockInput.value=contentBlocks.length?JSON.stringify(contentBlocks):''}
function blockField(label,value,onInput,multiline=false){const wrap=document.createElement('div');const caption=document.createElement('label');caption.textContent=label;const input=document.createElement(multiline?'textarea':'input');input.className='form-control';input.value=value||'';if(multiline)input.rows=3;input.addEventListener('input',()=>onInput(input.value));wrap.append(caption,input);return wrap}
function renderBlocks(){blockEditor.replaceChildren();contentBlocks.forEach((block,index)=>{const card=document.createElement('div');card.className='panel';const top=document.createElement('div');top.className='d-flex justify-content-between align-items-center mb-3';const title=document.createElement('strong');title.textContent={heading:'Subjudul',paragraph:'Paragraf',quote:'Kutipan',button:'Tombol',image:'Gambar',columns:'Dua kolom',product_hotspot:'Hotspot produk',hero_options:'Pengaturan slide hero'}[block.type]||block.type;const actions=document.createElement('div');actions.className='d-flex gap-2';[['↑',-1],['↓',1]].forEach(([label,move])=>{const button=document.createElement('button');button.type='button';button.className='btn btn-outline-dark btn-sm';button.textContent=label;button.disabled=(move<0&&index===0)||(move>0&&index===contentBlocks.length-1);button.addEventListener('click',()=>{const target=index+move;[contentBlocks[index],contentBlocks[target]]=[contentBlocks[target],contentBlocks[index]];syncBlocks();renderBlocks()});actions.append(button)});const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger btn-sm';remove.textContent='Hapus';remove.addEventListener('click',()=>{contentBlocks.splice(index,1);syncBlocks();renderBlocks()});actions.append(remove);top.append(title,actions);card.append(top);
if(['heading','paragraph','quote'].includes(block.type))card.append(blockField('Teks',block.text,value=>{block.text=value;syncBlocks()},true));
if(['button','image'].includes(block.type)){if(block.type==='image'&&mediaAssets.length){const mediaWrap=document.createElement('div');const mediaLabel=document.createElement('label');mediaLabel.textContent='Pilih dari media library';const mediaSelect=document.createElement('select');mediaSelect.className='form-select';mediaSelect.innerHTML='<option value="">— Pilih gambar —</option>';mediaAssets.forEach(asset=>{const option=document.createElement('option');option.value=asset.url;option.textContent=asset.name+' — '+asset.alt;mediaSelect.append(option)});mediaSelect.addEventListener('change',()=>{const asset=mediaAssets.find(item=>item.url===mediaSelect.value);if(asset){block.url=asset.url;block.label=asset.alt;syncBlocks();renderBlocks()}});mediaWrap.append(mediaLabel,mediaSelect);card.append(mediaWrap)}card.append(blockField('URL / path internal',block.url,value=>{block.url=value;syncBlocks()}));card.append(blockField(block.type==='image'?'Alt text':'Label tombol',block.label,value=>{block.label=value;syncBlocks()}))}
if(block.type==='columns'){block.items=block.items||[{heading:'',text:''},{heading:'',text:''}];block.items.forEach((item,itemIndex)=>{const group=document.createElement('div');group.className='panel mt-2';const heading=document.createElement('strong');heading.textContent='Kolom '+(itemIndex+1);group.append(heading);group.append(blockField('Judul',item.heading,value=>{item.heading=value;syncBlocks()}));group.append(blockField('Isi',item.text,value=>{item.text=value;syncBlocks()},true));card.append(group)})}
if(block.type==='product_hotspot'){const productWrap=document.createElement('div');const productLabel=document.createElement('label');productLabel.textContent='Produk yang ditautkan';const productSelect=document.createElement('select');productSelect.className='form-select';productSelect.innerHTML='<option value="">— Pilih produk —</option>';productSeries.forEach(series=>{const option=document.createElement('option');option.value=series.id;option.textContent=series.name;option.selected=String(block.series_id||'')===String(series.id);productSelect.append(option)});productSelect.addEventListener('change',()=>{block.series_id=Number(productSelect.value)||'';syncBlocks()});productWrap.append(productLabel,productSelect);card.append(productWrap);const position=document.createElement('div');position.className='form-grid mt-3';position.append(blockField('Posisi horizontal X (0–100)',block.x??50,value=>{block.x=value;syncBlocks()}),blockField('Posisi vertikal Y (0–100)',block.y??50,value=>{block.y=value;syncBlocks()}));card.append(position)}
if(block.type==='hero_options'){const options=document.createElement('div');options.className='form-grid';[['align','Posisi teks',[['left','Kiri'],['center','Tengah'],['right','Kanan']]],['tone','Warna teks',[['light','Terang'],['dark','Gelap']]]].forEach(([key,label,values])=>{const wrap=document.createElement('div');const caption=document.createElement('label');caption.textContent=label;const select=document.createElement('select');select.className='form-select';values.forEach(([value,text])=>{const option=document.createElement('option');option.value=value;option.textContent=text;option.selected=(block[key]||values[0][0])===value;select.append(option)});select.addEventListener('change',()=>{block[key]=select.value;syncBlocks()});wrap.append(caption,select);options.append(wrap)});options.append(blockField('URL gambar khusus mobile',block.mobile_url??'',value=>{block.mobile_url=value;syncBlocks()}));const note=document.createElement('small');note.className='span-2';note.textContent='Durasi seluruh carousel diatur melalui Pengaturan website → Tampilan global.';options.append(note);card.append(options)}
blockEditor.append(card)});syncBlocks()}
document.getElementById('add-content-block').addEventListener('click',()=>{const type=document.getElementById('new-block-type').value;const block=type==='columns'?{type,items:[{heading:'',text:''},{heading:'',text:''}]}:type==='product_hotspot'?{type,series_id:'',x:50,y:50}:type==='hero_options'?{type,align:'left',tone:'light',mobile_url:''}:{type,...(['heading','paragraph','quote'].includes(type)?{text:''}:{url:'',label:''})};contentBlocks.push(block);renderBlocks()});renderBlocks();
</script>
@endpush
@endif
