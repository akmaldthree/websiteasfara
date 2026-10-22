@extends('layouts.store')
@section('title',($entry->seo_title ?: $entry->title).' — Dthree')
@section('description',$entry->seo_description ?: \Illuminate\Support\Str::limit($entry->summary ?: $entry->body,160))
@section('content')
@if($preview ?? false)<div class="container-xxl demo-note">Pratinjau admin · konten ini mungkin belum terbit.</div>@endif
<article class="container-xxl section page-narrow"><span class="eyebrow">{{ strtoupper(str_replace('_',' ',$entry->type)) }}</span><h1 class="my-4">{{ $entry->title }}</h1>@if($entry->image_url)<img class="w-100 mb-4" src="{{ $entry->image_url }}" alt="{{ $entry->title }}">@endif @if($entry->summary)<p class="lead">{{ $entry->summary }}</p>@endif<div class="preserve-lines">{{ $entry->body }}</div>@if($entry->link_url)<a class="btn btn-dark mt-4" href="{{ $entry->link_url }}">{{ $entry->link_label ?: 'Selengkapnya' }} ↗</a>@endif</article>
@endsection
