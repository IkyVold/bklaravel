@extends('layouts.app')
@section('title', 'Monitoring')
@section('heading', 'Semua Konseling')
@section('content')
<div class="card"><div class="table-wrap"><table>
<thead><tr><th>Siswa</th><th>Kelas</th><th>Guru BK</th><th>Status</th><th>Konfirmasi</th><th></th></tr></thead>
<tbody>
@foreach($rows as $k)
<tr>
<td>{{ $k->siswa->nama ?? '-' }}</td>
<td>{{ $k->siswa->kelas ?? $k->kelas_siswa }}</td>
<td>{{ $k->guru_bk }}</td>
<td><span class="badge badge-blue">{{ $k->status }}</span></td>
<td><span class="badge badge-yellow">{{ $k->status_konfirmasi }}</span></td>
<td><a href="{{ route('kepsek.konseling.show', $k->id) }}">Detail</a></td>
</tr>
@endforeach
</tbody></table></div>
<div style="margin-top:16px">{{ $rows->links() }}</div>
</div>
@endsection
