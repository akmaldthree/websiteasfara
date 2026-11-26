@extends('layouts.store')
@section('title', 'Lokasi Toko — '.($shop['brand_name'] ?? 'Asfara'))
@section('description', 'Temukan alamat dan petunjuk menuju toko Asfara.')
@section('body_class', 'editorial-body')
@section('content')
<section class="container-xxl section location-page">
    <nav class="content-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Beranda</a><span>/</span><span>Lokasi toko</span></nav>
    <h1>Lokasi Toko Asfara</h1>
    <div class="location-list">
        @forelse($storeLocations as $location)
            <article class="location-card">
                @if($location->city)<span class="eyebrow">{{ $location->city }}</span>@endif
                <h2>{{ $location->name }}</h2>
                <p>{{ $location->address }}</p>
                @if($location->hours)<p>{{ $location->hours }}</p>@endif
                @if($location->map_url)<a class="btn btn-outline-dark" href="{{ $location->map_url }}" target="_blank" rel="noopener">Lihat di peta</a>@endif
            </article>
        @empty
            <p>{{ filled($shop['store_location'] ?? null) ? $shop['store_location'] : 'Informasi lokasi toko sedang disiapkan.' }}</p>
        @endforelse
    </div>
</section>
@endsection
