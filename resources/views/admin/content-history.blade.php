@extends('layouts.admin')
@section('title','Riwayat '.$record->title)
@section('content')
<div class="admin-heading"><div><span class="eyebrow">VERSI KONTEN</span><h1>{{ $record->title }}</h1><p>Setiap penyimpanan menyimpan versi sebelumnya. Pemulihan juga dapat dibatalkan karena kondisi saat ini disimpan sebagai versi baru.</p></div><a href="{{ route('admin.resources',$resource) }}">← Kembali</a></div>
<div class="table-panel"><div class="table-responsive"><table class="table">
    <thead><tr><th>Waktu</th><th>Editor</th><th>Judul versi</th><th>Status</th><th></th></tr></thead>
    <tbody>@forelse($revisions as $revision)<tr>
        <td>{{ $revision->created_at->format('d M Y H:i') }}</td><td>{{ $revision->user?->name ?? 'Sistem' }}</td><td>{{ $revision->snapshot['title'] ?? 'Tanpa judul' }}</td><td>{{ !empty($revision->snapshot['active'])?'Terbit':'Draft' }}</td>
        <td><form method="post" action="{{ route('admin.content.restore',$revision) }}" onsubmit="return confirm('Pulihkan versi ini? Kondisi saat ini akan tetap disimpan di riwayat.')">@csrf<button class="btn btn-outline-dark btn-sm">Pulihkan</button></form></td>
    </tr>@empty<tr><td colspan="5" class="text-center py-5">Belum ada revisi. Riwayat dibuat saat konten yang sudah ada disimpan.</td></tr>@endforelse</tbody>
</table></div>{{ $revisions->links() }}</div>
@endsection
