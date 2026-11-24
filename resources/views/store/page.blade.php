@extends('layouts.store')
@section('title',$page->seo_title ?: $page->title.' — '.($shop['brand_name'] ?? 'Asfara'))
@section('description',$page->seo_description ?: \Illuminate\Support\Str::limit($page->body,160))
@section('body_class','information-body')
@php($template=$page->template ?: 'standard')
@section('content')
<article class="container-xxl section information-page {{ $template==='standard'?'page-narrow':'page-template-'.$template }}">
    <nav class="content-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">HOME</a><span>/</span><span>INFORMASI</span></nav>
    <header @class(['text-center py-5'=>$template==='landing'])><span class="eyebrow">INFORMASI ASFARA</span><h1>{{ $page->title }}</h1>@if($page->seo_description)<p>{{ $page->seo_description }}</p>@endif</header>
    <div class="information-page-body">@if($page->blocks)@include('partials.content-blocks',['contentBlocks'=>$page->blocks])@else<div class="preserve-lines">{{ $page->body }}</div>@endif</div>
    <aside class="information-help"><span>MASIH MEMBUTUHKAN BANTUAN?</span><p>Tim Asfara siap membantu menjawab pertanyaan tentang produk, pesanan, dan layanan.</p>@if(!empty($shop['whatsapp']))<a href="https://wa.me/{{ $shop['whatsapp'] }}" target="_blank" rel="noopener">HUBUNGI ASFARA</a>@else<a href="{{ route('track') }}">LACAK PESANAN</a>@endif</aside>
</article>
@endsection
