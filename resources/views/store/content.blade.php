@extends('layouts.store')
@section('title',($entry->seo_title ?: $entry->title).' — Dthree')
@section('description',$entry->seo_description ?: \Illuminate\Support\Str::limit($entry->summary ?: $entry->body,160))
@php($template=$entry->template ?: 'standard')
@section('content')
@if($preview ?? false)<div class="container-xxl demo-note">Pratinjau admin · konten ini mungkin belum terbit.</div>@endif
<article class="container-xxl section {{ $template==='standard'?'page-narrow':'page-template-'.$template }}">
    <header @class(['text-center py-5'=>$template==='landing'])><span class="eyebrow">{{ strtoupper(str_replace('_',' ',$entry->type)) }}</span><h1 class="my-4">{{ $entry->title }}</h1>@if($entry->summary)<p class="lead">{{ $entry->summary }}</p>@endif</header>
    @if($entry->image_url)<img class="w-100 mb-4" src="{{ $entry->image_url }}" alt="{{ $entry->title }}">@endif
    @if($entry->blocks)@include('partials.content-blocks',['contentBlocks'=>$entry->blocks])@else<div class="preserve-lines">{{ $entry->body }}</div>@endif
    @if($entry->link_url)<a class="btn btn-dark mt-4" href="{{ $entry->link_url }}">{{ $entry->link_label ?: 'Selengkapnya' }} ↗</a>@endif
</article>
@endsection
