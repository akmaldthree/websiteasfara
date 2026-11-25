@extends('layouts.store')
@section('title',ucfirst($type).' — Asfara')
@section('description','Cerita, panduan, dan inspirasi terbaru dari Asfara.')
@section('body_class','editorial-body')
@php($isCampaign = $type === 'campaign')
@if($entries->first()?->image_url)@section('image',$entries->first()->image_url)@endif
@section('content')
<section class="catalog-hero"><div class="container-xxl"><nav><a href="{{ route('home') }}">HOME</a> / {{ $isCampaign ? 'KAMPANYE' : strtoupper($type) }}</nav><div class="catalog-hero-heading"><div>@unless($isCampaign)<span>STORIES BY ASFARA</span>@endunless<h1>{{ $isCampaign ? 'Kampanye Asfara' : strtoupper($type) }}</h1>@unless($isCampaign)<p>Cerita, panduan, dan inspirasi untuk menemani setiap pilihan modest Anda.</p>@endunless</div><strong>{{ $entries->total() }} {{ $isCampaign ? 'KAMPANYE' : 'CERITA' }}</strong></div></div></section>
<section class="editorial-index container-xxl">
    <div class="editorial-grid">
        @forelse($entries as $entry)
        @php($mobileCover = $isCampaign ? $entry->mobileCoverUrl() : null)
        <article class="editorial-card reveal">
            <a href="{{ route('content.show',[$type,$entry->slug]) }}">
                @if($entry->image_url || $mobileCover)
                    <picture>
                        @if($mobileCover)<source media="(max-width: 767px)" srcset="{{ $mobileCover }}">@endif
                        <img src="{{ $entry->image_url ?: $mobileCover }}" alt="" width="1200" height="800" loading="lazy" decoding="async">
                    </picture>
                @endif
                <div><span>{{ $isCampaign ? config('storefront.campaign_groups.'.$entry->campaignGroup()) : strtoupper($type) }}</span><h2>{{ $entry->title }}</h2><p>{{ $entry->summary ?: \Illuminate\Support\Str::limit($entry->body,150) }}</p><b>{{ $isCampaign ? 'LIHAT KOLEKSI' : 'BACA SELENGKAPNYA' }}</b></div>
            </a>
        </article>
        @empty<div class="empty-state"><h2>Konten sedang disiapkan.</h2><p>Kunjungi kembali halaman ini dalam waktu dekat.</p></div>@endforelse
    </div>
    <div class="mt-5">{{ $entries->links() }}</div>
</section>
@endsection
