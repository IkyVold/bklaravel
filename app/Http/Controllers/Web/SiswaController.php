<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Konseling;
use App\Models\Siswa;
use App\Support\MasterKelas;
use App\Support\SimpleXlsx;
use App\Support\TempPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiswaController extends Controller
{
    // PERBAIKAN (revisi 27 Agustus 2026, poin 9): daftar kelas dipindahkan
    // ke App\Support\MasterKelas supaya Web, API, dan import memakai satu
    // sumber kebenaran yang sama. Lihat MasterKelas::LIST untuk daftarnya
    // dan alasan lengkap perubahan ini.

    public function index(Request $request)
    {
        $query = Siswa::query()->orderBy('kelas')->orderBy('nama');
        if ($kelas = $request->query('kelas')) {
            $query->where('kelas', $kelas);
        }
        if ($jk = $request->query('jk')) {
            $query->where('jenis_kelamin', $jk);
        }
        if ($search = $request->query('q')) {
            $query->where(function ($w) use ($search) {
                $w->where('nama', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }
        $totalCount = Siswa::count();
        $rows = $query->paginate(50)->withQueryString();
        $kelasList = Siswa::select('kelas')->whereNotNull('kelas')->distinct()->orderBy('kelas')->pluck('kelas');
        $prosesCount = 0;
        try {
            $nama = session('auth_user')['nama'] ?? '';
            if ($nama) {
                // "Menunggu Konfirmasi" = pengajuan baru berstatus Menunggu,
                // disamakan dengan Web/KonselingController::indexGuru().
                $prosesCount = Konseling::where('guru_bk', $nama)
                    ->where('status', 'Menunggu')
                    ->count();
            }
        } catch (\Throwable $e) {
        }

        return view('guru.siswa-index', [
            'rows' => $rows,
            'kelasList' => $kelasList,
            'totalCount' => $totalCount,
            'activeTab' => 'siswa',
            'currentFilter' => 'all',
            'prosesCount' => $prosesCount,
            'kelas' => $request->query('kelas', ''),
            'jk' => $request->query('jk', ''),
            'q' => $request->query('q', ''),
            'kelasOptions' => MasterKelas::LIST,
        ]);
    }

    public function create()
    {
        return redirect()->route('guru.siswa.index', ['open' => 'tambah']);
    }

    public function store(Request $request)
    {
        // PERBAIKAN (revisi 24 Agustus 2026, poin 10): 'password' SENGAJA
        // dihapus dari rules. Dulu Guru BK bisa mengisi password custom
        // saat menambah siswa baru lewat form ini — sekarang password
        // SELALU dibuat otomatis oleh sistem saat pembuatan (lihat poin
        // 1 di bawah), sama seperti jalur import (upsertSiswa()). Guru
        // BK tidak lagi bisa menentukan atau mengubah password siswa
        // lewat rute mana pun di bawah role:guru; reset password siswa
        // hanya lewat Admin atau siswa itu sendiri (lihat
        // Api/ProfileController, poin 1).
        $data = $request->validate([
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): dulu
            // 'string|max:20' — tidak konsisten dengan API (max:10) dan
            // halaman login (yang sudah menjanjikan "4 digit angka").
            // Diputuskan NIS = NIS lokal sekolah, tepat 4 digit angka.
            'nis' => 'required|digits:4|unique:siswa,nis',
            'nama' => 'required|string|max:100',
            'kelas' => 'required|string|max:20',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        ]);
        // PERBAIKAN (revisi 27 Agustus 2026, poin 9): kelas diperiksa
        // terhadap MasterKelas::LIST (dulu VALID_KELAS lokal). Sebelum
        // perbaikan ini kode pengecekannya hilang/terputus (hanya
        // menyisakan `return back()...; }` tanpa `if` pembukanya), yang
        // membuat store() tidak pernah bisa dieksekusi sampai selesai.
        if (!MasterKelas::isValid($data['kelas'])) {
            return back()->withInput()->withErrors(['kelas' => 'Kelas tidak valid']);
        }
        // PERBAIKAN (revisi 27 Agustus 2026, poin 1): password awal siswa
        // TIDAK BOLEH lagi = NIS (lihat TempPassword untuk alasan
        // lengkap — NIS bukan rahasia). Password acak ini WAJIB
        // ditampilkan ke Guru BK di bawah supaya bisa disampaikan ke
        // siswa; begitu response ini terkirim, password plain text-nya
        // tidak disimpan di mana pun lagi (kolom 'password' di database
        // sudah di-hash lewat Siswa::setPasswordAttribute()).
        $tempPassword = TempPassword::generate();
        $data['password'] = $tempPassword;
        // PERBAIKAN (revisi 25 Agustus 2026, poin 11): password awal siswa
        // di jalur ini ditentukan sistem, bukan siswa sendiri — wajib
        // diganti saat login pertama.
        $data['must_change_password'] = true;
        $data['jenis_kelamin'] = $this->normalizeJk($data['jenis_kelamin'] ?? null);
        Siswa::create($data);

        $message = "Siswa ditambahkan. Password awal: {$tempPassword} — sampaikan ke siswa, wajib diganti saat login pertama.";
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $message, 'password' => $tempPassword]);
        }
        return redirect()->route('guru.siswa.index')->with('success', $message);
    }

    public function edit(int $id)
    {
        $siswa = Siswa::findOrFail($id);
        return view('guru.siswa-form', compact('siswa'));
    }

    public function update(Request $request, int $id)
    {
        $siswa = Siswa::findOrFail($id);
        // PERBAIKAN (revisi 24 Agustus 2026, poin 10): 'password' SENGAJA
        // dihapus dari rules — sebelumnya Guru BK bisa reset password
        // siswa mana pun lewat form edit data master ini, celah yang
        // sama persis dengan yang sudah ditutup di Api/ProfileController
        // (poin 1). Guru BK di sini hanya boleh mengubah data
        // administratif (nama/NIS/kelas/jenis kelamin), tidak pernah
        // password.
        $data = $request->validate([
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): lihat catatan
            // lengkap di store() — diseragamkan jadi tepat 4 digit angka.
            'nis' => 'required|digits:4|unique:siswa,nis,' . $id,
            'nama' => 'required|string|max:100',
            'kelas' => 'required|string|max:20',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        ]);
        // PERBAIKAN (revisi 27 Agustus 2026, poin 9): update() sebelumnya
        // tidak pernah memeriksa kelas terhadap master kelas sama sekali
        // (berbeda dengan store(), yang seharusnya sudah memeriksa).
        // Sekarang disamakan: kelas juga diperiksa terhadap
        // MasterKelas::LIST saat mengedit data siswa.
        if (!MasterKelas::isValid($data['kelas'])) {
            return back()->withInput()->withErrors(['kelas' => 'Kelas tidak valid']);
        }
        $data['jenis_kelamin'] = $this->normalizeJk($data['jenis_kelamin'] ?? null);
        $siswa->update($data);
        return redirect()->route('guru.siswa.index')->with('success', 'Data siswa diperbarui.');
    }

    /** Download template CSV (buka di Excel). */
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['NIS', 'Nama', 'Kelas', 'Jenis Kelamin']);
            fputcsv($out, ['1234', 'Contoh Siswa', 'X - 1', 'Laki-laki']);
            fputcsv($out, ['5678', 'Contoh Siswi', 'XI - 2', 'Perempuan']);
            fclose($out);
        }, 'template-import-siswa.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Import Excel/CSV: NIS, Nama, Kelas, Jenis Kelamin. */
    public function importExcel(Request $request)
    {
        $request->validate(['file' => 'required|file|max:5120']);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        try {
            $rows = $this->readTabular($path, $ext);
        } catch (\Throwable $e) {
            return $this->importResponse(false, $e->getMessage(), $request);
        }

        if (count($rows) < 2) {
            return $this->importResponse(false, 'File kosong atau tidak ada data.', $request);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
        $map = [];
        foreach ($header as $i => $h) {
            if (in_array($h, ['nis', 'nisn'], true)) $map['nis'] = $i;
            if (in_array($h, ['nama', 'name', 'nama siswa'], true)) $map['nama'] = $i;
            if (in_array($h, ['kelas', 'class'], true)) $map['kelas'] = $i;
            if (in_array($h, ['jenis kelamin', 'jenis_kelamin', 'jk', 'gender'], true)) $map['jk'] = $i;
        }
        if (!isset($map['nis'], $map['nama'], $map['kelas'])) {
            return $this->importResponse(false, 'Header wajib: NIS, Nama, Kelas (Jenis Kelamin opsional).', $request);
        }

        $inserted = 0;
        $updated = 0;
        $skipped = [];
        // PERBAIKAN (revisi 27 Agustus 2026, poin 1): password siswa
        // baru sekarang acak (lihat upsertSiswa()/TempPassword), bukan
        // lagi = NIS yang sudah diketahui Guru BK dari file yang
        // diupload. Daftar ini WAJIB dikirim balik ke frontend supaya
        // Guru BK bisa menyalin/mencatat password tiap siswa baru —
        // lihat resultExcel di siswa-index.blade.php.
        $newAccounts = [];
        for ($r = 1; $r < count($rows); $r++) {
            $line = $rows[$r];
            $nis = trim((string) ($line[$map['nis']] ?? ''));
            $nama = trim((string) ($line[$map['nama']] ?? ''));
            $kelas = trim((string) ($line[$map['kelas']] ?? ''));
            $jk = isset($map['jk']) ? ($line[$map['jk']] ?? null) : null;
            if ($nis === '' && $nama === '') {
                continue;
            }
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): dulu regex hanya
            // mengecek "seluruhnya angka" tanpa batas panjang — NIS 3
            // digit atau 15 digit tetap lolos, tidak konsisten dengan
            // aturan tepat 4 digit di create()/update()/login.
            if ($nis === '' || !preg_match('/^[0-9]{4}$/', $nis)) {
                $skipped[] = ['row' => $r + 1, 'reason' => 'NIS kosong atau bukan tepat 4 digit angka'];
                continue;
            }
            if ($nama === '') {
                $skipped[] = ['row' => $r + 1, 'reason' => 'Nama kosong'];
                continue;
            }
            if (!MasterKelas::isValid($kelas)) {
                $skipped[] = ['row' => $r + 1, 'reason' => 'Kelas "' . $kelas . '" tidak valid'];
                continue;
            }
            try {
                $res = $this->upsertSiswa($nis, $nama, $kelas, $jk);
                if ($res['status'] === 'updated') {
                    $updated++;
                } else {
                    $inserted++;
                    $newAccounts[] = ['nis' => $nis, 'nama' => $nama, 'password' => $res['password']];
                }
            } catch (\Throwable $e) {
                $skipped[] = ['row' => $r + 1, 'reason' => $e->getMessage()];
            }
        }

        $message = "Import selesai — {$inserted} siswa baru, {$updated} diperbarui, " . count($skipped) . ' dilewati.';
        return $this->importResponse(true, $message, $request, [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'new_accounts' => $newAccounts,
        ]);
    }

    /** Preview absen: sheet X/XI/XII, blok KELAS ... */
    public function previewAbsen(Request $request)
    {
        $request->validate(['file' => 'required|file|max:5120']);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return response()->json(['success' => false, 'error' => 'File absen harus .xlsx'], 400);
        }
        try {
            $all = SimpleXlsx::allSheets($file->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }

        $sections = [];
        foreach (['X', 'XI', 'XII'] as $sheetName) {
            if (!isset($all[$sheetName])) {
                continue;
            }
            $current = null;
            foreach ($all[$sheetName] as $row) {
                $c0 = $row[0] ?? '';
                $c1 = $row[1] ?? '';
                $c2 = $row[2] ?? '';
                $c3 = $row[3] ?? '';
                if (is_string($c0) && str_contains(strtoupper($c0), 'KELAS')) {
                    $current = ['sheet' => $sheetName, 'label' => trim($c0), 'siswa' => []];
                    $sections[] = &$current;
                    continue;
                }
                $isNo = is_numeric($c0);
                $nisOk = $c1 !== '' && $c1 !== null;
                $namaOk = is_string($c2) && trim($c2) !== '';
                $jkOk = $c3 === 'L' || $c3 === 'P' || $c3 === 'l' || $c3 === 'p';
                if ($current && $isNo && $nisOk && $namaOk && $jkOk) {
                    $current['siswa'][] = [
                        'nis' => trim((string) $c1),
                        'nama' => trim((string) $c2),
                        'jk' => strtoupper((string) $c3),
                    ];
                }
            }
            unset($current);
        }

        // fix references - rebuild clean
        $clean = [];
        foreach ($sections as $sec) {
            if (is_array($sec)) {
                $clean[] = $sec;
            }
        }
        // The & reference approach is messy - re-parse cleanly
        $clean = $this->parseAbsenSections($all);
        $total = array_sum(array_map(fn ($s) => count($s['siswa']), $clean));
        $sheets = array_values(array_unique(array_column($clean, 'sheet')));

        return response()->json([
            'success' => true,
            'sections' => $clean,
            'totalSiswa' => $total,
            'message' => 'Ditemukan ' . count($clean) . ' kelas dengan total ' . $total . ' siswa di sheet ' . implode(', ', $sheets) . '.',
        ]);
    }

    public function importRows(Request $request)
    {
        $rows = $request->input('rows', []);
        if (!is_array($rows) || count($rows) === 0) {
            return response()->json(['success' => false, 'error' => 'Tidak ada baris siswa'], 400);
        }
        $inserted = 0;
        $updated = 0;
        $skipped = [];
        // PERBAIKAN (revisi 27 Agustus 2026, poin 1): lihat catatan yang
        // sama di importExcel() — password siswa baru sekarang acak,
        // jadi daftarnya wajib dikirim balik supaya bisa ditampilkan
        // (dipakai oleh alur "Import dari Absen" di siswa-index.blade.php).
        $newAccounts = [];
        foreach ($rows as $i => $r) {
            $nis = trim((string) ($r['nis'] ?? ''));
            $nama = trim((string) ($r['nama'] ?? ''));
            $kelas = trim((string) ($r['kelas'] ?? ''));
            $jk = $r['jenis_kelamin'] ?? $r['jk'] ?? null;
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): sama seperti
            // jalur import CSV/manual di atas — panjang NIS harus tepat
            // 4 digit, bukan cuma "seluruhnya angka".
            if ($nis === '' || !preg_match('/^[0-9]{4}$/', $nis)) {
                $skipped[] = ['row' => $i + 1, 'reason' => "NIS \"{$nis}\" harus tepat 4 digit angka"];
                continue;
            }
            if ($nama === '') {
                $skipped[] = ['row' => $i + 1, 'reason' => "Nama kosong (NIS {$nis})"];
                continue;
            }
            if (!MasterKelas::isValid($kelas)) {
                $skipped[] = ['row' => $i + 1, 'reason' => "Kelas \"{$kelas}\" tidak valid"];
                continue;
            }
            try {
                $res = $this->upsertSiswa($nis, $nama, $kelas, $jk);
                if ($res['status'] === 'updated') {
                    $updated++;
                } else {
                    $inserted++;
                    $newAccounts[] = ['nis' => $nis, 'nama' => $nama, 'password' => $res['password']];
                }
            } catch (\Throwable $e) {
                $skipped[] = ['row' => $i + 1, 'reason' => $e->getMessage()];
            }
        }
        return response()->json([
            'success' => true,
            'message' => "Import selesai — {$inserted} baru, {$updated} diperbarui, " . count($skipped) . ' dilewati.',
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'new_accounts' => $newAccounts,
        ]);
    }

    private function parseAbsenSections(array $all): array
    {
        $sections = [];
        foreach (['X', 'XI', 'XII'] as $sheetName) {
            if (!isset($all[$sheetName])) {
                continue;
            }
            $current = null;
            foreach ($all[$sheetName] as $row) {
                $c0 = $row[0] ?? '';
                $c1 = $row[1] ?? '';
                $c2 = $row[2] ?? '';
                $c3 = $row[3] ?? '';
                if (is_string($c0) && str_contains(strtoupper($c0), 'KELAS')) {
                    if ($current !== null) {
                        $sections[] = $current;
                    }
                    $current = ['sheet' => $sheetName, 'label' => trim($c0), 'siswa' => []];
                    continue;
                }
                $isNo = is_numeric($c0);
                $nisOk = $c1 !== '' && $c1 !== null;
                $namaOk = is_string($c2) && trim($c2) !== '';
                $jkOk = in_array($c3, ['L', 'P', 'l', 'p'], true);
                if ($current !== null && $isNo && $nisOk && $namaOk && $jkOk) {
                    $current['siswa'][] = [
                        'nis' => trim((string) $c1),
                        'nama' => trim((string) $c2),
                        'jk' => strtoupper((string) $c3),
                    ];
                }
            }
            if ($current !== null) {
                $sections[] = $current;
            }
        }
        return $sections;
    }

    private function readTabular(string $path, string $ext): array
    {
        if (in_array($ext, ['csv', 'txt'], true)) {
            $rows = [];
            if (($h = fopen($path, 'r')) === false) {
                throw new \RuntimeException('Gagal membaca CSV');
            }
            while (($data = fgetcsv($h)) !== false) {
                $rows[] = $data;
            }
            fclose($h);
            return $rows;
        }
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $sheets = SimpleXlsx::allSheets($path);
            if (!$sheets) {
                throw new \RuntimeException('Tidak ada sheet terbaca');
            }
            return array_values($sheets)[0];
        }
        throw new \RuntimeException('Format file harus .csv atau .xlsx');
    }

    /**
     * PERBAIKAN (revisi 27 Agustus 2026, poin 1): dulu mengembalikan
     * string status ('inserted'/'updated') saja, karena password siswa
     * baru selalu = NIS sehingga tidak perlu dikembalikan ke pemanggil
     * (Guru BK sudah tahu NIS-nya). Sekarang password dibuat acak (lihat
     * TempPassword), jadi pemanggil (importExcel()/importRows()) WAJIB
     * tahu password yang baru dibuat supaya bisa ditampilkan — makanya
     * return value diubah jadi array ['status' => ..., 'password' =>
     * ...]. Untuk baris yang hanya di-update (siswa sudah ada), password
     * TIDAK disentuh sama sekali dan 'password' bernilai null.
     *
     * @return array{status: 'inserted'|'updated', password: ?string}
     */
    private function upsertSiswa(string $nis, string $nama, string $kelas, $jk): array
    {
        $jk = $this->normalizeJk($jk);
        $existing = Siswa::where('nis', $nis)->first();
        if ($existing) {
            $existing->update([
                'nama' => $nama,
                'kelas' => $kelas,
                'jenis_kelamin' => $jk ?? $existing->jenis_kelamin,
            ]);
            return ['status' => 'updated', 'password' => null];
        }
        $tempPassword = TempPassword::generate();
        Siswa::create([
            'nis' => $nis,
            'nama' => $nama,
            'kelas' => $kelas,
            'jenis_kelamin' => $jk,
            'password' => $tempPassword,
            // PERBAIKAN (revisi 25 Agustus 2026, poin 11): sama seperti
            // store() di atas — password awal wajib diganti saat login
            // pertama.
            'must_change_password' => true,
        ]);
        return ['status' => 'inserted', 'password' => $tempPassword];
    }

    private function normalizeJk($val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }
        $v = strtolower(trim((string) $val));
        if (in_array($v, ['l', 'laki-laki', 'laki laki', 'pria', 'male'], true)) {
            return 'Laki-laki';
        }
        if (in_array($v, ['p', 'perempuan', 'wanita', 'female'], true)) {
            return 'Perempuan';
        }
        return null;
    }

    private function importResponse(bool $ok, string $message, Request $request, array $extra = [])
    {
        $payload = array_merge(['success' => $ok, 'message' => $message], $extra);
        if (!$ok) {
            $payload['error'] = $message;
        }
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json($payload, $ok ? 200 : 400);
        }
        return redirect()->route('guru.siswa.index')->with($ok ? 'success' : 'error', $message);
    }

    public function lookupByNis(string $nis)
    {
        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'error' => 'Siswa tidak ditemukan'], 404);
        }
        return response()->json([
            'success' => true,
            'nama' => $siswa->nama,
            'kelas' => $siswa->kelas,
            'nis' => $siswa->nis,
            'id' => $siswa->id,
        ]);
    }
}
