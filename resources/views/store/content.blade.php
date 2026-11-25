@extends('layouts.store')
@section('title',$entry->seo_title ?: $entry->title.' — '.($shop['brand_name'] ?? 'Asfara'))
@section('description',$entry->seo_description ?: \Illuminate\Support\Str::limit($entry->summary ?: $entry->body,160))
@section('body_class','editorial-body')
@if($entry->image_url)@section('image',$entry->image_url)@endif
@php($template=$entry->template ?: 'standard')
@section('content')
@if($preview ?? false)<div class="container-xxl demo-note">Pratinjau admin · konten ini mungkin belum terbit.</div>@endif
<article class="container-xxl section editorial-detail {{ !empty($campaignProducts) && $campaignProducts->isNotEmpty() ? 'campaign-detail' : ($template==='standard'?'page-narrow':'page-template-'.$template) }}">
    <nav class="content-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">HOME</a><span>/</span>@if($entry->type!=='faq')<a href="{{ route('content.index',$entry->type) }}">{{ strtoupper($entry->type) }}</a><span>/</span>@endif<span>{{ strtoupper(\Illuminate\Support\Str::limit($entry->title,35)) }}</span></nav>
    <header @class(['text-center py-5'=>$template==='landing'])><span class="eyebrow">{{ strtoupper(str_replace('_',' ',$entry->type)) }}</span><h1>{{ $entry->title }}</h1>@if($entry->summary)<p class="lead">{{ $entry->summary }}</p>@endif</header>
    @if($entry->image_url)<img class="w-100 mb-4" src="{{ $entry->image_url }}" alt="{{ $entry->title }}" width="1200" height="800" loading="eager" fetchpriority="high" decoding="async">@endif
    <div class="editorial-detail-body">
        @if($entry->body && (!$entry->blocks || $entry->campaignProductIds()->isNotEmpty()))<div class="preserve-lines">{{ $entry->body }}</div>@endif
        @if($entry->blocks)@include('partials.content-blocks',['contentBlocks'=>$entry->blocks])@endif
    </div>
    @if(isset($campaignProducts) && $campaignProducts->isNotEmpty())
        <section aria-labelledby="campaign-products-heading">
            <h2 id="campaign-products-heading" class="mt-5 mb-4">Belanja koleksi</h2>
            <div class="campaign-product-grid">
                @foreach($campaignProducts as $collection)
                    @include('partials.product-card', ['cardContext' => 'campaign-page-'])
                @endforeach
            </div>
        </section>
    @endif
    @if($entry->link_url)<a class="btn btn-dark mt-4" href="{{ $entry->link_url }}">{{ $entry->link_label ?: 'Selengkapnya' }}</a>@endif
</article>
@endsection
