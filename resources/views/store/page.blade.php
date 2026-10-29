@extends('layouts.store')
@section('title',$page->seo_title ?: $page->title.' — '.($shop['brand_name'] ?? 'Dthree'))
@section('description',$page->seo_description ?: \Illuminate\Support\Str::limit($page->body,160))
@php($template=$page->template ?: 'standard')
@section('content')
<article class="container-xxl section {{ $template==='standard'?'page-narrow':'page-template-'.$template }}">
    <header @class(['text-center py-5'=>$template==='landing'])><span class="eyebrow">INFORMASI DTHREE</span><h1 class="my-4">{{ $page->title }}</h1></header>
    @if($page->blocks)@include('partials.content-blocks',['contentBlocks'=>$page->blocks])@else<div class="preserve-lines">{{ $page->body }}</div>@endif
</article>
@endsection
