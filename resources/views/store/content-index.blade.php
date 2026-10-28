@extends('layouts.store')
@section('title',ucfirst($type).' — Dthree')
@section('content')
<section class="catalog-hero"><div class="container-xxl"><nav><a href="{{ route('home') }}">HOME</a> / {{ strtoupper($type) }}</nav><h1>{{ strtoupper($type) }}</h1></div></section>
<section class="editorial-index container-xxl">
    <div class="editorial-grid">
        @forelse($entries as $entry)
        <article class="editorial-card reveal">
            <a href="{{ route('content.show',[$type,$entry->slug]) }}">@if($entry->image_url)<img src="{{ $entry->image_url }}" alt="{{ $entry->title }}" width="1200" height="800" loading="lazy" decoding="async">@endif<div><span>{{ strtoupper($type) }}</span><h2>{{ $entry->title }}</h2><p>{{ $entry->summary ?: \Illuminate\Support\Str::limit($entry->body,150) }}</p><b>BACA SELENGKAPNYA →</b></div></a>
        </article>
        @empty<div class="empty-state"><h2>Konten sedang disiapkan.</h2><p>Kunjungi kembali halaman ini dalam waktu dekat.</p></div>@endforelse
    </div>
    <div class="mt-5">{{ $entries->links() }}</div>
</section>
@endsection
