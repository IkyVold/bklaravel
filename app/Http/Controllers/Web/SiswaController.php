<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Konseling;
use App\Models\Siswa;
use App\Support\SimpleXlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiswaController extends Controller
{
    public const VALID_KELAS = [
        'X - 1', 'X - 2', 'X - 3', 'X - 4', 'X - 5', 'X - 6', 'X - 7', 'X - 8', 'X - 9', 'X - 10',
        'XI - 1', 'XI - 2', 'XI - 3', 'XI - 4', 'XI - 5', 'XI - 6', 'XI - 7', 'XI - 8', 'XI - 9', 'XI - 10',
        'XII - 1', 'XII - 2', 'XII - 3', 'XII - 4', 'XII - 5', 'XII - 6', 'XII - 7', 'XII - 8', 'XII - 9', 'XII - 10',
    ];

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
            'kelasOptions' => self::VALID_KELAS,
        ]);
    }

    public function create()
    {
        return redirect()->route('guru.siswa.index', ['open' => 'tambah']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nis' => 'required|string|max:20|unique:siswa,nis',
            'nama' => 'required|string|max:100',
            'kelas' => 'required|string|max:20',
            'password' => 'nullable|string|min:4',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        ]);
        if (!in_array($data['kelas'], self::VALID_KELAS, true)) {
            return back()->withInput()->withErrors(['kelas' => 'Kelas tidak valid']);
        }
        if (empty($data['password'])) {
            $data['password'] = $data['nis'];
        }
        $data['jenis_kelamin'] = $this->normalizeJk($data['jenis_kelamin'] ?? null);
        Siswa::create($data);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Siswa ditambahkan. Password default = NIS.']);
        }
        return redirect()->route('guru.siswa.index')->with('success', 'Siswa ditambahkan. Password default = NIS.');
    }

    public function edit(int $id)
    {
        $siswa = Siswa::findOrFail($id);
        return view('guru.siswa-form', compact('siswa'));
    }

    public function update(Request $request, int $id)
    {
        $siswa = Siswa::findOrFail($id);
        $data = $request->validate([
            'nis' => 'required|string|max:20|unique:siswa,nis,' . $id,
            'nama' => 'required|string|max:100',
            'kelas' => 'required|string|max:20',
            'password' => 'nullable|string|min:4',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
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
        for ($r = 1; $r < count($rows); $r++) {
            $line = $rows[$r];
            $nis = trim((string) ($line[$map['nis']] ?? ''));
            $nama = trim((string) ($line[$map['nama']] ?? ''));
            $kelas = trim((string) ($line[$map['kelas']] ?? ''));
            $jk = isset($map['jk']) ? ($line[$map['jk']] ?? null) : null;
            if ($nis === '' && $nama === '') {
                continue;
            }
            if ($nis === '' || !preg_match('/^[0-9]+$/', $nis)) {
                $skipped[] = ['row' => $r + 1, 'reason' => 'NIS kosong atau bukan angka'];
                continue;
            }
            if ($nama === '') {
                $skipped[] = ['row' => $r + 1, 'reason' => 'Nama kosong'];
                continue;
            }
            if (!in_array($kelas, self::VALID_KELAS, true)) {
                $skipped[] = ['row' => $r + 1, 'reason' => 'Kelas "' . $kelas . '" tidak valid'];
                continue;
            }
            try {
                $res = $this->upsertSiswa($nis, $nama, $kelas, $jk);
                $res === 'updated' ? $updated++ : $inserted++;
            } catch (\Throwable $e) {
                $skipped[] = ['row' => $r + 1, 'reason' => $e->getMessage()];
            }
        }

        $message = "Import selesai — {$inserted} siswa baru, {$updated} diperbarui, " . count($skipped) . ' dilewati.';
        return $this->importResponse(true, $message, $request, [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
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
        foreach ($rows as $i => $r) {
            $nis = trim((string) ($r['nis'] ?? ''));
            $nama = trim((string) ($r['nama'] ?? ''));
            $kelas = trim((string) ($r['kelas'] ?? ''));
            $jk = $r['jenis_kelamin'] ?? $r['jk'] ?? null;
            if ($nis === '' || !preg_match('/^[0-9]+$/', $nis)) {
                $skipped[] = ['row' => $i + 1, 'reason' => "NIS \"{$nis}\" tidak valid"];
                continue;
            }
            if ($nama === '') {
                $skipped[] = ['row' => $i + 1, 'reason' => "Nama kosong (NIS {$nis})"];
                continue;
            }
            if (!in_array($kelas, self::VALID_KELAS, true)) {
                $skipped[] = ['row' => $i + 1, 'reason' => "Kelas \"{$kelas}\" tidak valid"];
                continue;
            }
            try {
                $res = $this->upsertSiswa($nis, $nama, $kelas, $jk);
                $res === 'updated' ? $updated++ : $inserted++;
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

    private function upsertSiswa(string $nis, string $nama, string $kelas, $jk): string
    {
        $jk = $this->normalizeJk($jk);
        $existing = Siswa::where('nis', $nis)->first();
        if ($existing) {
            $existing->update([
                'nama' => $nama,
                'kelas' => $kelas,
                'jenis_kelamin' => $jk ?? $existing->jenis_kelamin,
            ]);
            return 'updated';
        }
        Siswa::create([
            'nis' => $nis,
            'nama' => $nama,
            'kelas' => $kelas,
            'jenis_kelamin' => $jk,
            'password' => $nis,
        ]);
        return 'inserted';
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
