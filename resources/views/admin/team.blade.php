@extends('layouts.admin')
@section('title','Tim & hak akses')
@section('content')
<h1 class="mb-4">Tim & hak akses</h1>
<div class="panel"><h2>Pengguna operasional</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>Nama / email</th><th>Akses</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
    @foreach($users as $user)<tr><td>{{ $user->name }}<br><small>{{ $user->email }}</small></td>
        <td><select name="role" form="team-{{ $user->id }}" class="form-select" aria-label="Peran {{ $user->name }}">@foreach($roles as $role)<option value="{{ $role->name }}" @selected($user->role===$role->name)>{{ ucfirst($role->name) }}</option>@endforeach</select></td>
        <td><label><input type="checkbox" name="active" form="team-{{ $user->id }}" value="1" @checked($user->active)> Aktif</label></td>
        <td><div class="d-flex gap-2 flex-wrap"><form id="team-{{ $user->id }}" action="{{ route('admin.team.update',$user) }}" method="post">@csrf<button class="btn btn-outline-dark btn-sm">Simpan</button></form>@if(!$user->is(auth()->user()))<form action="{{ route('admin.team.destroy',$user) }}" method="post" onsubmit="return confirm('Hapus pengguna operasional ini secara permanen?')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Hapus</button></form>@endif</div></td>
    </tr>@endforeach</tbody></table></div>
    <details><summary>+ Tambah pengguna</summary><form action="{{ route('admin.team.save') }}" method="post" class="form-grid mt-4">@csrf
        @foreach(['name'=>'Nama','email'=>'Email','password'=>'Kata sandi awal (min. 12 karakter)'] as $key=>$label)<div><label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" class="form-control" name="{{ $key }}" type="{{ $key==='password'?'password':'text' }}" required></div>@endforeach
        <div><label for="role">Peran</label><select id="role" name="role" class="form-select">@foreach($roles as $role)<option value="{{ $role->name }}">{{ $role->name }}</option>@endforeach</select></div>
        <div><button class="btn btn-dark">Buat pengguna</button></div>
    </form></details>
</div>
@foreach($roles->where('name','!=','admin') as $role)
<form class="panel mt-3" method="post" action="{{ route('admin.role.save',$role) }}">@csrf<h2>{{ ucfirst($role->name) }}</h2>
    <div class="row">@foreach(config('commerce.permissions') as $permission)<label class="col-md-4"><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission,$role->permissions))> {{ $permission }}</label>@endforeach</div>
    <div class="d-flex gap-2 mt-3"><button class="btn btn-outline-dark btn-sm">Simpan hak akses</button><button class="btn btn-outline-danger btn-sm" name="_method" value="DELETE" formaction="{{ route('admin.role.destroy',$role) }}" onclick="return confirm('Hapus peran ini secara permanen?')">Hapus peran</button></div>
</form>
@endforeach
@endsection
