<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use App\Support\KategoriKonseling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class KepsekController extends Controller
{
    public function dashboard()
    {
        $rows = $this->allKonseling();
        $stats = $this->hitungStatistik($rows);
        $recent = $rows->take(5);
        $activeTab = 'dashboard';
        $guruCards = $this->buildGuruCards($rows);

        return view('kepsek.dashboard', compact('rows', 'stats', 'recent', 'activeTab', 'guruCards'));
    }

    public function rekapGuru()
    {
        $rows = $this->allKonseling();
        $guruList = Schema::hasTable('guru_bk')
            ? GuruBk::orderBy('nama')->get()
            : collect();

        $rekap = $guruList->map(function ($g) use ($rows) {
            $own = $rows->filter(function ($r) use ($g) {
                if (Schema::hasColumn('konseling', 'guru_id') && $r->guru_id) {
                    return (int) $r->guru_id === (int) $g->id;
                }
                return strcasecmp((string) $r->guru_bk, (string) $g->nama) === 0;
            });
            // Rekap per kategori memakai master kategori yang sama dengan
            // dashboard utama (KategoriKonseling::ALL) — sebelumnya di sini
            // hanya menghitung 4 dari 6 kategori (Karir & Keluarga hilang).
            $byKat = [];
            foreach (KategoriKonseling::ALL as $kat) {
                $byKat[strtolower($kat)] = $own->filter(
                    fn ($r) => strcasecmp((string) $r->kategori, $kat) === 0
                )->count();
            }
            return array_merge([
                'guru' => $g,
                'total' => $own->count(),
            ], $byKat, [
                'proses' => $own->whereIn('status', ['Menunggu', 'Proses'])->count(),
                'selesai' => $own->where('status', 'Selesai')->count(),
                'dibatalkan' => $own->where('status', 'Dibatalkan')->count(),
                'laporan' => $own->filter(fn ($r) => !empty($r->laporan_kesimpulan) || !empty($r->laporan_created_at))->count(),
            ]);
        });

        $activeTab = 'rekap-guru';
        return view('kepsek.rekap-guru', compact('rekap', 'activeTab'));
    }

    public function konseling(Request $request)
    {
        $rows = $this->allKonseling();
        $filter = $request->query('filter', 'all');
        $q = trim((string) $request->query('q', ''));
        $kategori = $request->query('kategori', '');

        if ($filter === 'proses') {
            $rows = $rows->whereIn('status', ['Menunggu', 'Proses']);
        } elseif ($filter === 'selesai') {
            $rows = $rows->where('status', 'Selesai');
        } elseif ($filter === 'dibatalkan') {
            $rows = $rows->where('status', 'Dibatalkan');
        }
        if ($kategori !== '') {
            $rows = $rows->where('kategori', $kategori);
        }
        if ($q !== '') {
            $rows = $rows->filter(function ($r) use ($q) {
                $nama = optional($r->siswa)->nama ?? '';
                $nis = optional($r->siswa)->nis ?? '';
                return stripos($nama, $q) !== false || stripos($nis, $q) !== false || stripos((string) $r->guru_bk, $q) !== false;
            });
        }

        $activeTab = 'semua-konseling';
        return view('kepsek.konseling', compact('rows', 'filter', 'q', 'kategori', 'activeTab'));
    }

    public function statistik()
    {
        $rows = $this->allKonseling();
        $stats = $this->hitungStatistik($rows);
        $activeTab = 'statistik';
        return view('kepsek.statistik', compact('rows', 'stats', 'activeTab'));
    }

    public function show(int $id)
    {
        $konseling = Konseling::with('siswa')->findOrFail($id);
        $activeTab = 'semua-konseling';

        $row = $konseling->untukMonitoringKepsek();

        return view('kepsek.detail', compact('row', 'activeTab'));
    }

    protected function allKonseling()
    {
        return Konseling::with('siswa:id,nis,nama,kelas,jenis_kelamin')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }

    protected function hitungStatistik($rows): array
    {
        $total = $rows->count();
        $byKat = function (string $k) use ($rows) {
            return $rows->filter(fn ($r) => strcasecmp((string) $r->kategori, $k) === 0)->count();
        };

        return [
            'total' => $total,
            'siswaAktif' => $rows->pluck('siswa_id')->unique()->count(),
            'guruAktif' => $rows->pluck('guru_bk')->filter()->unique()->count(),
            'selesai' => $rows->where('status', 'Selesai')->count(),
            'proses' => $rows->whereIn('status', ['Menunggu', 'Proses'])->count(),
            'dibatalkan' => $rows->where('status', 'Dibatalkan')->count(),
            'terkonfirmasi' => $rows->filter(function ($r) {
                return in_array($r->status_konfirmasi, ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'], true);
            })->count(),
            'akademik' => $byKat('Akademik'),
            'sosial' => $byKat('Sosial'),
            'pribadi' => $byKat('Pribadi'),
            'karir' => $byKat('Karir'),
            'bullying' => $byKat('Bullying'),
            'keluarga' => $byKat('Keluarga'),
            'lainnya' => $byKat('Lainnya'),
        ];
    }

    protected function buildGuruCards($rows)
    {
        $guruList = Schema::hasTable('guru_bk')
            ? GuruBk::orderBy('nama')->get()
            : collect();

        if ($guruList->isEmpty()) {
            // fallback from data
            $names = $rows->pluck('guru_bk')->filter()->unique()->values();
            return $names->map(function ($nama) use ($rows) {
                $own = $rows->filter(fn ($r) => strcasecmp((string)$r->guru_bk, (string)$nama) === 0);
                return [
                    'nama' => $nama,
                    'total' => $own->count(),
                    'selesai' => $own->where('status', 'Selesai')->count(),
                    'proses' => $own->whereIn('status', ['Menunggu', 'Proses'])->count(),
                    'laporan' => $own->filter(fn ($r) => !empty($r->laporan_kesimpulan) || !empty($r->laporan_created_at))->count(),
                ];
            })->sortByDesc('total')->values();
        }

        return $guruList->map(function ($g) use ($rows) {
            $own = $rows->filter(function ($r) use ($g) {
                if (Schema::hasColumn('konseling', 'guru_id') && $r->guru_id) {
                    return (int) $r->guru_id === (int) $g->id;
                }
                return strcasecmp((string) $r->guru_bk, (string) $g->nama) === 0;
            });
            return [
                'nama' => $g->nama,
                'total' => $own->count(),
                'selesai' => $own->where('status', 'Selesai')->count(),
                'proses' => $own->whereIn('status', ['Menunggu', 'Proses'])->count(),
                'laporan' => $own->filter(fn ($r) => !empty($r->laporan_kesimpulan) || !empty($r->laporan_created_at))->count(),
            ];
        })->filter(fn ($g) => $g['total'] > 0)->sortByDesc('total')->values();
    }
}
