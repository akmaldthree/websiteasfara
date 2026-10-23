@extends('layouts.admin')
@section('title',$schema['title'])
@section('content')
<div class="admin-heading">
    <div><span class="eyebrow">{{ $record->exists?'PERBARUI DATA':'DATA BARU' }}</span><h1>{{ $schema['title'] }}</h1></div>
    <a href="{{ route('admin.resources',$resource) }}">← Kembali</a>
</div>
<form method="post" enctype="multipart/form-data" action="{{ $record->exists?route('admin.update',[$resource,$record->id]):route('admin.store',$resource) }}" class="panel">
    @csrf
    <div class="form-grid">
        @foreach($schema['fields'] as $key=>$field)
            @php($value=old($key,$record->$key))
            <div class="{{ $field[1]==='textarea'?'span-2':'' }}">
                <label for="{{ $key }}">{{ $field[0] }}</label>
                @if($field[1]==='textarea')
                    <textarea id="{{ $key }}" name="{{ $key }}" class="form-control" rows="5">{{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : $value }}</textarea>
                @elseif($field[1]==='blocks')
                    <input type="hidden" id="blocks" name="blocks" value="{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : $value }}">
                    <div id="content-block-editor" class="d-grid gap-3"></div>
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <select id="new-block-type" class="form-select" style="max-width:240px"><option value="paragraph">Paragraf</option><option value="heading">Subjudul</option><option value="quote">Kutipan</option><option value="button">Tombol</option><option value="image">Gambar dari URL</option></select>
                        <button id="add-content-block" type="button" class="btn btn-outline-dark">+ Tambah blok</button>
                    </div>
                    <small>Susun konten dengan tombol naik/turun. Teks selalu ditampilkan secara aman; blok tombol dan gambar menerima URL HTTPS atau path internal.</small>
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
                        @foreach($options as $k=>$label)<option value="{{ $k }}" @selected((string)$value===(string)$k)>{{ $label }}</option>@endforeach
                    </select>
                @elseif($field[1]==='checkbox')
                    <input id="{{ $key }}" type="checkbox" name="{{ $key }}" value="1" @checked($value)>
                @elseif($field[1]==='file')
                    <input class="form-control" id="{{ $key }}" name="{{ $key }}" type="file" accept="image/png,image/jpeg,image/webp">
                @else
                    <input id="{{ $key }}" name="{{ $key }}" type="{{ $field[1] }}" class="form-control" value="{{ $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d\TH:i') : $value }}" {{ str_contains($field[2],'required')?'required':'' }}>
                @endif
            </div>
        @endforeach
    </div>
    <button class="btn btn-dark mt-4">Simpan perubahan</button>
</form>
@endsection
@if(array_key_exists('blocks',$schema['fields']))
@php($availableMedia=\App\Models\MediaAsset::where('active',true)->latest()->get()->map(fn($asset)=>['url'=>$asset->url,'alt'=>$asset->alt_text,'name'=>$asset->name])->values()->all())
@push('scripts')
<script>
const blockInput=document.getElementById('blocks');const blockEditor=document.getElementById('content-block-editor');const mediaAssets=@json($availableMedia);let contentBlocks=[];
try{contentBlocks=JSON.parse(blockInput.value||'[]')}catch(e){contentBlocks=[]}
function syncBlocks(){blockInput.value=contentBlocks.length?JSON.stringify(contentBlocks):''}
function blockField(label,value,onInput,multiline=false){const wrap=document.createElement('div');const caption=document.createElement('label');caption.textContent=label;const input=document.createElement(multiline?'textarea':'input');input.className='form-control';input.value=value||'';if(multiline)input.rows=3;input.addEventListener('input',()=>onInput(input.value));wrap.append(caption,input);return wrap}
function renderBlocks(){blockEditor.replaceChildren();contentBlocks.forEach((block,index)=>{const card=document.createElement('div');card.className='panel';const top=document.createElement('div');top.className='d-flex justify-content-between align-items-center mb-3';const title=document.createElement('strong');title.textContent={heading:'Subjudul',paragraph:'Paragraf',quote:'Kutipan',button:'Tombol',image:'Gambar'}[block.type]||block.type;const actions=document.createElement('div');actions.className='d-flex gap-2';[['↑',-1],['↓',1]].forEach(([label,move])=>{const button=document.createElement('button');button.type='button';button.className='btn btn-outline-dark btn-sm';button.textContent=label;button.disabled=(move<0&&index===0)||(move>0&&index===contentBlocks.length-1);button.addEventListener('click',()=>{const target=index+move;[contentBlocks[index],contentBlocks[target]]=[contentBlocks[target],contentBlocks[index]];syncBlocks();renderBlocks()});actions.append(button)});const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger btn-sm';remove.textContent='Hapus';remove.addEventListener('click',()=>{contentBlocks.splice(index,1);syncBlocks();renderBlocks()});actions.append(remove);top.append(title,actions);card.append(top);
if(['heading','paragraph','quote'].includes(block.type))card.append(blockField('Teks',block.text,value=>{block.text=value;syncBlocks()},true));
if(['button','image'].includes(block.type)){if(block.type==='image'&&mediaAssets.length){const mediaWrap=document.createElement('div');const mediaLabel=document.createElement('label');mediaLabel.textContent='Pilih dari media library';const mediaSelect=document.createElement('select');mediaSelect.className='form-select';mediaSelect.innerHTML='<option value="">— Pilih gambar —</option>';mediaAssets.forEach(asset=>{const option=document.createElement('option');option.value=asset.url;option.textContent=asset.name+' — '+asset.alt;mediaSelect.append(option)});mediaSelect.addEventListener('change',()=>{const asset=mediaAssets.find(item=>item.url===mediaSelect.value);if(asset){block.url=asset.url;block.label=asset.alt;syncBlocks();renderBlocks()}});mediaWrap.append(mediaLabel,mediaSelect);card.append(mediaWrap)}card.append(blockField('URL / path internal',block.url,value=>{block.url=value;syncBlocks()}));card.append(blockField(block.type==='image'?'Alt text':'Label tombol',block.label,value=>{block.label=value;syncBlocks()}))}
blockEditor.append(card)});syncBlocks()}
document.getElementById('add-content-block').addEventListener('click',()=>{const type=document.getElementById('new-block-type').value;contentBlocks.push({type,...(['heading','paragraph','quote'].includes(type)?{text:''}:{url:'',label:''})});renderBlocks()});renderBlocks();
</script>
@endpush
@endif
