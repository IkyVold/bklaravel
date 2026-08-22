<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Konseling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * Cetak Jurnal Kerja Guru BK — format sama React CetakLaporan.jsx
 * Controller terpisah agar tidak menimpa KonselingController yang sudah stabil.
 */
class CetakLaporanController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Session::get('auth_user', []);
        $guruId = Session::get('auth_id');
        $nama = $user['nama'] ?? '';

        $filter = $request->query('filter', 'all');
        $q = trim((string) $request->query('q', ''));

        $query = Konseling::with('siswa:id,nis,nama,kelas,jenis_kelamin')
            ->orderBy('tanggal')
            ->orderBy('id');

        // Data milik guru yang login
        $query->where(function ($w) use ($guruId, $nama) {
            if ($guruId) {
                $w->where('guru_id', $guruId);
            }
            if ($nama !== '') {
                $w->orWhere(function ($w2) use ($nama) {
                    $w2->whereNull('guru_id')->where('guru_bk', $nama);
                });
                if (!$guruId) {
                    $w->orWhere('guru_bk', $nama);
                }
            }
        });

        if ($filter === 'proses') {
            $query->whereIn('status', ['Menunggu', 'Proses']);
        } elseif ($filter === 'terkonfirmasi') {
            $query->whereIn('status_konfirmasi', ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'])
                ->whereNotIn('status', ['Selesai', 'Dibatalkan']);
        } elseif ($filter === 'selesai') {
            $query->where('status', 'Selesai');
        } elseif ($filter === 'dibatalkan') {
            $query->where('status', 'Dibatalkan');
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->whereHas('siswa', function ($s) use ($q) {
                    $s->where('nama', 'like', "%{$q}%")
                        ->orWhere('nis', 'like', "%{$q}%");
                });
            });
        }

        $rows = $query->get();

        $meta = [
            'guruName' => $nama ?: 'Guru BK',
            'kepalaSekolah' => env('SEKOLAH_KEPSEK', 'WAHYU WINDARI, M.Pd.'),
            'kepalaSekolahNip' => env('SEKOLAH_KEPSEK_NIP', 'NIP. 19730317 199903 2 007'),
            'filter' => $filter,
        ];

        return view('guru.cetak-laporan', compact('rows', 'meta', 'filter'));
    }
}
