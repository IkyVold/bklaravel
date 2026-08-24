<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SiswaController extends Controller
{
    use AuthorizesBk;

    public function list(Request $request): JsonResponse
    {
        $q = Siswa::query()->orderBy('kelas')->orderBy('nama');

        if ($kelas = $request->query('kelas')) {
            $q->where('kelas', $kelas);
        }
        if ($search = $request->query('search')) {
            $q->where(function ($w) use ($search) {
                $w->where('nama', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $rows = $q->get(['id', 'nis', 'nama', 'kelas', 'jenis_kelamin', 'foto_profile', 'created_at']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function create(Request $request): JsonResponse
    {
        // PERBAIKAN (revisi 24 Agustus 2026, poin 10): 'password' hanya
        // wajib/dipakai untuk Admin. Guru BK boleh membuat siswa baru
        // (rute ini sekarang 'ability:guru,admin'), tapi tidak boleh
        // menentukan passwordnya sendiri — dipaksa = NIS, apa pun yang
        // dikirim di body. Reset password siswa yang SUDAH ADA tetap
        // hanya lewat Api/ProfileController (siswa sendiri atau Admin).
        $isAdmin = $this->isAdmin($request);

        $rules = [
            'nis' => 'required|string|max:10|unique:siswa,nis',
            'nama' => 'required|string|max:100',
            'kelas' => 'required|string|max:20',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        ];
        $rules['password'] = $isAdmin ? 'nullable|string|min:4' : 'prohibited';

        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $data = $v->validated();
        if (empty($data['password'])) {
            $data['password'] = $data['nis'];
        }

        $siswa = Siswa::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil ditambahkan',
            'data' => $siswa->only(['id', 'nis', 'nama', 'kelas']),
        ], 201);
    }

    public function importRows(Request $request): JsonResponse
    {
        $rows = $request->input('rows', []);
        if (!is_array($rows) || empty($rows)) {
            return response()->json(['success' => false, 'message' => 'Data kosong'], 400);
        }

        // PERBAIKAN (revisi 24 Agustus 2026, poin 10): sama seperti create()
        // di atas — Guru BK boleh import siswa (rute ini sekarang
        // 'ability:guru,admin'), tapi field 'password' pada tiap baris
        // diabaikan sepenuhnya kalau pemanggil bukan Admin; password
        // selalu = NIS. Hanya Admin yang boleh menyertakan password custom
        // per baris import.
        $isAdmin = $this->isAdmin($request);

        $inserted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $nis = trim((string) ($row['nis'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? ''));
            $kelas = trim((string) ($row['kelas'] ?? ''));
            $password = $isAdmin ? (string) ($row['password'] ?? $nis) : $nis;

            if (!$nis || !$nama || !$kelas) {
                $skipped++;
                $errors[] = "Baris " . ($i + 1) . ": data tidak lengkap";
                continue;
            }

            if (Siswa::where('nis', $nis)->exists()) {
                $skipped++;
                continue;
            }

            try {
                Siswa::create([
                    'nis' => $nis,
                    'nama' => $nama,
                    'kelas' => $kelas,
                    'password' => $password,
                    'jenis_kelamin' => $row['jenis_kelamin'] ?? null,
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Baris " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Import selesai: {$inserted} ditambahkan, {$skipped} dilewati",
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }
}
