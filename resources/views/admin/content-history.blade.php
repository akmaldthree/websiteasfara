@extends('layouts.admin')
@section('title','Riwayat '.$record->title)
@section('content')
<div class="admin-heading"><div><span class="eyebrow">VERSI KONTEN</span><h1>{{ $record->title }}</h1><p>Setiap penyimpanan menyimpan versi sebelumnya. Pemulihan juga dapat dibatalkan karena kondisi saat ini disimpan sebagai versi baru.</p></div><a href="{{ route('admin.resources',$resource) }}">← Kembali</a></div>
<div class="table-panel"><div class="table-responsive"><table class="table">
    <thead><tr><th>Waktu</th><th>Editor</th><th>Judul versi</th><th>Status</th><th></th></tr></thead>
    <tbody>@forelse($revisions as $revision)
        @php
            $savedDraft = $revision->snapshot['editorial_draft'] ?? null;
            $savedDraft = is_string($savedDraft) ? json_decode($savedDraft, true) : $savedDraft;
            $version = $savedDraft ?? $revision->snapshot;
        @endphp
        <tr>
        <td>{{ $revision->created_at->format('d M Y H:i') }}</td><td>{{ $revision->user?->name ?? 'Sistem' }}</td><td>{{ $version['title'] ?? 'Tanpa judul' }}</td><td>{{ ['draft'=>'Draft','review'=>'Menunggu review','approved'=>'Disetujui'][$version['editorial_status'] ?? 'approved'] }} · {{ !empty($version['active'])?'Tayang':'Nonaktif' }}</td>
        <td><form method="post" action="{{ route('admin.content.restore',$revision) }}" onsubmit="return confirm('Pulihkan versi ini? Kondisi saat ini akan tetap disimpan di riwayat.')">@csrf<button class="btn btn-outline-dark btn-sm">{{ auth()->user()->allowed('content.approve') && !$savedDraft ? 'Pulihkan' : 'Pulihkan sebagai draft' }}</button></form></td>
    </tr>@empty<tr><td colspan="5" class="text-center py-5">Belum ada revisi. Riwayat dibuat saat konten yang sudah ada disimpan.</td></tr>@endforelse</tbody>
</table></div>{{ $revisions->links() }}</div>
@endsection
