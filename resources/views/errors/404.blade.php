@extends('errors.minimal')
@section('title', 'Halaman tidak ditemukan')
@section('code', 'ERROR 404')
@section('heading', 'Halaman ini tidak ditemukan.')
@section('message', 'Alamat mungkin sudah berubah atau halaman tidak lagi tersedia. Anda dapat kembali menjelajahi koleksi Asfara.')
@section('secondary')<a href="{{ url('/koleksi') }}">LIHAT KOLEKSI</a>@endsection
