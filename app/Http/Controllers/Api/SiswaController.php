<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Support\MasterKelas;
use App\Support\TempPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        // menentukan passwordnya sendiri — apa pun yang dikirim di body
        // ditolak validasi ('prohibited' di bawah) dan password akan
        // dibuat otomatis secara acak oleh sistem (lihat poin 1). Reset
        // password siswa yang SUDAH ADA tetap hanya lewat
        // Api/ProfileController (siswa sendiri atau Admin).
        $isAdmin = $this->isAdmin($request);

        $rules = [
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): dulu
            // 'string|max:10' — tidak konsisten dengan Web (max:20) dan
            // halaman login (yang sudah menjanjikan "4 digit angka").
            // Diputuskan NIS = NIS lokal sekolah, tepat 4 digit angka.
            'nis' => 'required|digits:4|unique:siswa,nis',
            'nama' => 'required|string|max:100',
            // PERBAIKAN (revisi 27 Agustus 2026, poin 9): dulu
            // 'string|max:20' — request API bisa membuat siswa dengan
            // kelas bebas apa pun (mis. "KELAS SEMBARANG") walau Web
            // sudah menolaknya lewat VALID_KELAS. Sekarang divalidasi
            // terhadap App\Support\MasterKelas, sumber yang sama dengan
            // yang dipakai Web dan import.
            'kelas' => ['required', 'string', Rule::in(MasterKelas::LIST)],
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        ];
        $rules['password'] = $isAdmin
            // PERBAIKAN (revisi 27 Agustus 2026, poin 10): dulu 'min:4' —
            // password custom yang ditentukan Admin bisa hanya 4 karakter,
            // terlalu rendah untuk sebuah password (walau wajib diganti
            // saat login pertama lewat must_change_password, password
            // tersebut tetap sempat aktif sebelum diganti). Rekomendasi
            // review: idealnya masalah ini sekalian hilang kalau password
            // awal dibuat otomatis secara acak; tapi selama Admin memang
            // diperbolehkan menentukan temporary password sendiri (dipakai
            // di beberapa alur onboarding manual), naikkan syarat minimal
            // jadi 10 karakter — sesuai rentang 10–12 karakter yang
            // direkomendasikan. must_change_password tetap true seperti
            // sebelumnya (lihat bawah), jadi password ini tetap hanya
            // dipakai sekali oleh siswa untuk login pertama.
            ? 'nullable|string|min:10'
            : 'prohibited';

        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $data = $v->validated();
        // PERBAIKAN (revisi 27 Agustus 2026, poin 1): dulu fallback-nya
        // $data['nis'] — password awal siswa jadi = NIS-nya sendiri, yang
        // BUKAN rahasia (lihat TempPassword untuk penjelasan lengkap
        // kenapa ini celah). Sekarang kalau tidak ada password custom
        // dari Admin, password awal dibuat ACAK, tidak berhubungan
        // dengan NIS sama sekali.
        $generatedPassword = null;
        if (empty($data['password'])) {
            $generatedPassword = TempPassword::generate();
            $data['password'] = $generatedPassword;
        }
        // PERBAIKAN (revisi 25 Agustus 2026, poin 11): password awal siswa
        // selalu ditentukan oleh orang lain (Guru BK/Admin), bukan siswa
        // itu sendiri — baik itu acak maupun password custom dari Admin.
        // Tandai wajib ganti password supaya siswa dipaksa menentukan
        // password sendiri saat login pertama kali.
        $data['must_change_password'] = true;

        $siswa = Siswa::create($data);

        // PERBAIKAN (revisi 27 Agustus 2026, poin 1): password acak yang
        // baru dibuat WAJIB dikembalikan di response supaya pemanggil
        // (Guru BK/Admin) tahu apa yang harus disampaikan ke siswa —
        // begitu response ini terkirim, tidak ada tempat lain yang
        // menyimpan password ini dalam bentuk plain text (kolom di DB
        // sudah di-hash). Kalau Admin mengirim password custom sendiri,
        // tidak perlu dikembalikan karena Admin sudah tahu isinya.
        $responseData = $siswa->only(['id', 'nis', 'nama', 'kelas']);
        if ($generatedPassword !== null) {
            $responseData['password'] = $generatedPassword;
        }

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil ditambahkan',
            'data' => $responseData,
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
        // dibuat otomatis secara acak (lihat poin 1). Hanya Admin yang
        // boleh menyertakan password custom per baris import.
        $isAdmin = $this->isAdmin($request);

        $inserted = 0;
        $skipped = 0;
        $errors = [];
        // PERBAIKAN (revisi 27 Agustus 2026, poin 1): password default
        // per baris sekarang acak (lihat di bawah), bukan lagi = NIS
        // yang sudah diketahui pemanggil dari body request-nya sendiri.
        // Daftar ini WAJIB dikembalikan supaya pemanggil (Guru BK/Admin)
        // tahu password apa yang harus disampaikan ke tiap siswa baru.
        $newAccounts = [];

        foreach ($rows as $i => $row) {
            $nis = trim((string) ($row['nis'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? ''));
            $kelas = trim((string) ($row['kelas'] ?? ''));
            // PERBAIKAN (revisi 27 Agustus 2026, poin 10): dulu baris ini
            // langsung memakai `$row['password'] ?? $nis` sebagai password
            // final TANPA validasi panjang sama sekali — lebih lemah
            // daripada create() (yang setidaknya masih mensyaratkan
            // 'min:4' sebelum diperbaiki). Admin bisa mengirim password
            // custom sepanjang 1 karakter lewat import massal dan langsung
            // tersimpan ke database. Sekarang password custom per baris
            // dipisah dulu dari nilai default (acak) supaya bisa diperiksa
            // panjangnya sebelum dipakai — lihat pengecekan di bawah.
            $customPassword = $isAdmin ? trim((string) ($row['password'] ?? '')) : '';
            // PERBAIKAN (revisi 27 Agustus 2026, poin 1): fallback dulu
            // = $nis (lihat TempPassword untuk alasan lengkap kenapa itu
            // celah). $isGenerated dipakai di bawah untuk menandai baris
            // mana yang passwordnya perlu dikembalikan ke pemanggil.
            $isGenerated = $customPassword === '';
            $password = $isGenerated ? TempPassword::generate() : $customPassword;

            if (!$nis || !$nama || !$kelas) {
                $skipped++;
                $errors[] = "Baris " . ($i + 1) . ": data tidak lengkap";
                continue;
            }

            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): baris sebelumnya
            // hanya mengecek NIS tidak kosong, apa pun formatnya lolos
            // masuk ke Siswa::create() — tidak konsisten dengan aturan
            // digits:4 di create(). Sekarang baris import juga wajib NIS
            // tepat 4 digit angka, sama seperti endpoint create tunggal.
            if (!preg_match('/^[0-9]{4}$/', $nis)) {
                $skipped++;
                $errors[] = "Baris " . ($i + 1) . ": NIS \"{$nis}\" harus tepat 4 digit angka";
                continue;
            }

            // PERBAIKAN (revisi 27 Agustus 2026, poin 9): sama seperti
            // create() di atas — baris import lewat API sebelumnya tidak
            // memeriksa kelas sama sekali terhadap master kelas, jadi bisa
            // menyisipkan kelas bebas ke database. Sekarang diperiksa
            // terhadap App\Support\MasterKelas, sama seperti jalur import
            // Web (Web\SiswaController::import()/importRows()).
            if (!MasterKelas::isValid($kelas)) {
                $skipped++;
                $errors[] = "Baris " . ($i + 1) . ": kelas \"{$kelas}\" tidak valid";
                continue;
            }

            // PERBAIKAN (revisi 27 Agustus 2026, poin 10): password custom
            // dari Admin (kalau diisi) wajib minimal 10 karakter, sama
            // seperti create(). Password default (acak, dipakai kalau
            // kolom password di baris ini kosong — lihat poin 1) TIDAK
            // terkena aturan ini — itu memang dirancang hanya sebagai
            // nilai awal sementara yang wajib segera diganti siswa
            // (must_change_password selalu true di bawah), bukan
            // password custom yang sengaja dipilih Admin.
            if ($customPassword !== '' && strlen($customPassword) < 10) {
                $skipped++;
                $errors[] = "Baris " . ($i + 1) . ": password kustom harus minimal 10 karakter";
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
                    // PERBAIKAN (revisi 25 Agustus 2026, poin 11): sama
                    // seperti create() di atas — password awal (acak atau
                    // custom dari Admin) bukan pilihan siswa sendiri, jadi
                    // wajib diganti saat login pertama.
                    'must_change_password' => true,
                ]);
                $inserted++;
                if ($isGenerated) {
                    $newAccounts[] = ['nis' => $nis, 'nama' => $nama, 'password' => $password];
                }
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
            'new_accounts' => $newAccounts,
        ]);
    }
}
