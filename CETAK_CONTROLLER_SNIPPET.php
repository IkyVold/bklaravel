// ============================================================
// TAMBAHKAN method ini ke dalam class KonselingController
// (app/Http/Controllers/Web/KonselingController.php)
// JANGAN ganti seluruh file controller.
// ============================================================

    /**
     * Cetak Jurnal Kerja Guru BK — format sama React CetakLaporan.jsx
     */
    public function cetakLaporan(\Illuminate\Http\Request $request)
    {
        $user = \Illuminate\Support\Facades\Session::get('auth_user', []);
        $guruId = \Illuminate\Support\Facades\Session::get('auth_id');
        $nama = $user['nama'] ?? '';

        $filter = $request->query('filter', 'all');
        $q = trim((string) $request->query('q', ''));

        $query = \App\Models\Konseling::with('siswa:id,nis,nama,kelas,jenis_kelamin')
            ->orderBy('tanggal')
            ->orderBy('id');

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
            $query->where('status', 'Proses');
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
                    $s->where('nama', 'like', "%{$q}%")->orWhere('nis', 'like', "%{$q}%");
                });
            });
        }

        $rows = $query->get();

        $meta = [
            'guruName' => $nama ?: 'Guru BK',
            'sekolahName' => env('SEKOLAH_NAMA', 'SMA Negeri Darussholah Singojuruh'),
            'sekolahAlamat' => env('SEKOLAH_ALAMAT', 'Jl. Aruji Karta Winata, No. 39 Gumirih Kec. Singojuruh - Banyuwangi'),
            'sekolahTelp' => env('SEKOLAH_TELP', 'Telp. 0333 - 635381'),
            'kepalaSekolah' => env('SEKOLAH_KEPSEK', 'WAHYU WINDARI, M.Pd.'),
            'kepalaSekolahNip' => env('SEKOLAH_KEPSEK_NIP', 'NIP. 19730317 199903 2 007'),
            'filter' => $filter,
        ];

        return view('guru.cetak-laporan', compact('rows', 'meta', 'filter'));
    }
