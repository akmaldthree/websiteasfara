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
