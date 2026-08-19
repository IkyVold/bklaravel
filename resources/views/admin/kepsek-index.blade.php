@extends('layouts.app')
@section('title', 'Akun Kepsek')
@section('heading', 'Akun Kepala Sekolah')
@section('actions')
<a href="{{ route('admin.kepsek.create') }}" class="btn btn-primary">+ Tambah</a>
@endsection
@section('content')
<div class="card"><div class="table-wrap"><table>
<thead><tr><th>Username</th><th>Nama</th><th>Status</th><th></th></tr></thead>
<tbody>
@foreach($rows as $r)
<tr>
<td>{{ $r->username }}</td><td>{{ $r->nama }}</td>
<td><span class="badge {{ $r->is_active ? 'badge-green' : 'badge-red' }}">{{ $r->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
<td><a href="{{ route('admin.kepsek.edit', $r->id) }}">Edit</a></td>
</tr>
@endforeach
</tbody></table></div></div>
@endsection
