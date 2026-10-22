@extends('layouts.store')
@section('title','Akun saya — Dthree')
@section('content')
<section class="container-xxl section">
    <div class="section-top">
        <div><span class="eyebrow">AKUN SAYA</span><h1 class="mt-3">Halo, {{ auth()->user()->name }}.</h1></div>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-dark">Keluar</button></form>
    </div>
    <div class="row g-4">
        <div class="col-lg-7"><div class="panel"><h2>Riwayat pesanan</h2>
            @forelse($orders as $order)
                <a class="summary-row" href="{{ $order->customerUrl() }}"><span>{{ $order->number }}<br><small>{{ $order->created_at->format('d M Y') }} · {{ __('status.'.$order->status) }}</small></span><span>@rupiah($order->total) ↗</span></a>
            @empty
                <p>Belum ada pesanan. <a href="{{ route('catalog') }}">Temukan koleksi Anda ↗</a></p>
            @endforelse
            {{ $orders->links() }}
        </div></div>
        <div class="col-lg-5"><div class="panel"><h2>Buku alamat</h2>
            @foreach($addresses as $address)
                <div class="border-bottom py-3">
                    <p><strong>{{ $address->name }}</strong> @if($address->is_default)<span class="badge">Utama</span>@endif<br>{{ $address->phone }}<br>{{ $address->detail }}, {{ $address->city }} {{ $address->postal_code }}</p>
                    <div class="d-flex gap-2">
                        @unless($address->is_default)<form action="{{ route('address.default',$address->id) }}" method="post">@csrf<button class="btn btn-outline-dark btn-sm">Jadikan utama</button></form>@endunless
                        <form action="{{ route('address.delete',$address->id) }}" method="post">@csrf @method('DELETE')<button class="btn btn-outline-dark btn-sm">Hapus</button></form>
                    </div>
                </div>
            @endforeach
            <details class="mt-4"><summary>+ Tambahkan alamat</summary>
                <form action="{{ route('address.store') }}" method="post" class="mt-4">@csrf
                    @foreach(['name'=>'Penerima','phone'=>'Telepon','province'=>'Provinsi','city'=>'Kota / kabupaten','district'=>'Kecamatan','postal_code'=>'Kode pos','detail'=>'Alamat lengkap'] as $key=>$label)
                        <label for="address-{{ $key }}">{{ $label }}</label><input id="address-{{ $key }}" class="form-control mb-3" name="{{ $key }}" required>
                    @endforeach
                    <label class="d-block mb-3"><input type="checkbox" name="is_default" value="1"> Jadikan alamat utama</label>
                    <button class="btn btn-dark">Simpan alamat</button>
                </form>
            </details>
        </div></div>
    </div>
</section>
@endsection
