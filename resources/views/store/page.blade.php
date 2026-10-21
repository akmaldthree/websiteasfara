@extends('layouts.store') @section('title',$page->title.' — Dthree')
@section('content')<article class="container-xxl section page-narrow"><span class="eyebrow">INFORMASI DTHREE</span><h1 class="my-4">{{ $page->title }}</h1><div class="preserve-lines">{{ $page->body }}</div></article>@endsection
