@extends('layouts.admin')
@section('title','Monitoring sistem')
@section('content')
<div class="admin-heading"><div><span class="eyebrow">OPERASIONAL & KESEHATAN</span><h1>Monitoring sistem</h1><p>Status database, scheduler, queue, notifikasi, storage, dan konfigurasi runtime.</p></div></div>
<div class="row g-4">
@foreach($checks as $check)<div class="col-md-6 col-xl-4"><div class="panel h-100"><div class="d-flex justify-content-between align-items-start"><h2>{{ $check['name'] }}</h2><span class="badge {{ $check['status']==='ok'?'text-bg-success':($check['status']==='warning'?'text-bg-warning':'text-bg-danger') }}">{{ strtoupper($check['status']) }}</span></div><p class="mb-0">{{ $check['detail'] }}</p></div></div>@endforeach
</div>
<div class="panel mt-4"><h2>Petunjuk operasional</h2><ul class="mb-0"><li>Pastikan cron menjalankan <code>php artisan schedule:run</code> setiap menit.</li><li>Jalankan worker queue secara terus-menerus dan restart setelah deployment.</li><li>Jalankan <code>bash scripts/backup.sh</code> setiap hari dan simpan salinan terenkripsi di lokasi eksternal.</li><li>Jalankan <code>php artisan system:production-check</code> setelah perubahan konfigurasi atau deployment.</li><li>Periksa notifikasi gagal dari menu Notifikasi transaksi.</li><li>Di produksi, <code>APP_DEBUG</code> harus bernilai <code>false</code>.</li></ul></div>
@endsection
