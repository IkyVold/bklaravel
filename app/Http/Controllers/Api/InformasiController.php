<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Models\GuruBk;
use App\Models\InformasiBk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InformasiController extends Controller
{
    use AuthorizesBk;

    public function list(): JsonResponse
    {
        $rows = InformasiBk::orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function create(Request $request): JsonResponse
    {
        if (!$this->isRole($request, 'guru', 'admin')) {
            return response()->json(['success' => false, 'message' => 'Hanya Guru BK / Admin yang dapat menambah informasi'], 403);
        }

        $rules = [
            'judul' => 'required|string|max:150',
            'kategori' => 'required|string|max:50',
            'isi' => 'required|string',
        ];

        // PERBAIKAN (revisi 25 Agustus 2026, poin 14): dulu 'guru_bk'
        // diterima langsung sebagai string bebas dari client
        // ('guru_bk' => 'required|string|max:100'). Artinya Guru A bisa
        // mengirim guru_bk = nama Guru B, dan informasi tercatat seolah-
        // olah dibuat Guru B — integritas sumber informasi jadi tidak bisa
        // dipercaya, terutama karena bisa dipakai sebagai basis knowledge
        // chatbot. Sekarang identitas penulis TIDAK PERNAH diterima dari
        // client:
        //  - Guru BK: attribution dipaksa = nama akun yang sedang login
        //    (dari token, sama seperti pola di ChatController@send).
        //  - Admin: attribution wajib eksplisit lewat 'guru_id' yang
        //    menunjuk Guru BK yang benar-benar ada & aktif (bukan string
        //    bebas) — proses "Admin membuat informasi atas nama Guru BK"
        //    jadi jelas & terverifikasi server, sama seperti pola Admin
        //    pada KonselingController@walkin.
        if ($this->isAdmin($request)) {
            $rules['guru_id'] = 'required|integer';
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }
        $data = $v->validated();

        if ($this->isGuru($request)) {
            $user = $request->user();
            $data['guru_bk'] = $user->nama ?? 'Guru BK';
        } else {
            $guru = GuruBk::find($data['guru_id']);
            if (!$guru) {
                return response()->json(['success' => false, 'message' => 'Guru BK tidak ditemukan'], 404);
            }
            if (!($guru->is_active ?? true)) {
                return response()->json(['success' => false, 'message' => 'Guru BK tidak aktif'], 400);
            }
            $data['guru_bk'] = $guru->nama;
        }
        unset($data['guru_id']);

        $row = InformasiBk::create($data);
        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->isRole($request, 'guru', 'admin')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $row = InformasiBk::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        // PERBAIKAN (revisi 25 Agustus 2026, poin 14): 'guru_bk' dihapus
        // dari daftar field yang bisa diubah lewat sini — sebelumnya baris
        // ini juga membolehkan siapa pun (Guru mana pun / Admin) mengganti
        // attribution informasi milik orang lain jadi nama sembarang lewat
        // update(), bukan hanya lewat create(). Mengedit ISI informasi
        // tidak mengubah siapa penulis aslinya; reassignment authorship
        // (kalau memang dibutuhkan suatu saat) perlu endpoint eksplisit
        // tersendiri, bukan lewat field bebas di update konten seperti ini.
        $row->fill($request->only(['judul', 'kategori', 'isi']))->save();
        return response()->json(['success' => true, 'data' => $row]);
    }

    public function remove(Request $request, int $id): JsonResponse
    {
        if (!$this->isRole($request, 'guru', 'admin')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $row = InformasiBk::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $row->delete();
        return response()->json(['success' => true, 'message' => 'Dihapus']);
    }
}
