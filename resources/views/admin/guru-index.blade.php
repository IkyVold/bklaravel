@extends('layouts.app')
@section('title', 'Akun Guru')
@section('heading', 'Akun Guru BK')
@section('actions')
<a href="{{ route('admin.guru.create') }}" class="btn btn-primary">+ Tambah</a>
@endsection
@section('content')
<div class="card"><div class="table-wrap"><table>
<thead><tr><th>Username</th><th>Nama</th><th>Spesialisasi</th><th>Status</th><th></th></tr></thead>
<tbody>
@foreach($rows as $r)
<tr>
<td>{{ $r->username }}</td><td>{{ $r->nama }}</td><td>{{ $r->spesialisasi }}</td>
<td><span class="badge {{ $r->is_active ? 'badge-green' : 'badge-red' }}">{{ $r->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
<td><a href="{{ route('admin.guru.edit', $r->id) }}">Edit</a></td>
</tr>
@endforeach
</tbody></table></div></div>
@endsection
