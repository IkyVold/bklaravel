<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InformasiBk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class InformasiController extends Controller
{
    public function index()
    {
        $rows = InformasiBk::orderByDesc('created_at')->get();
        $role = Session::get('auth_role');
        if ($role === 'guru') {
            $activeTab = 'informasi';
            $currentFilter = 'all';
            $prosesCount = 0;
            return view('guru.informasi-index', compact('rows', 'activeTab', 'currentFilter', 'prosesCount'));
        }
        return view('shared.informasi-index', compact('rows', 'role'));
    }

    public function create()
    {
        return view('guru.informasi-form', ['row' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'judul' => 'required|string|max:150',
            'kategori' => 'required|string|max:50',
            'isi' => 'required|string',
        ]);
        $data['guru_bk'] = Session::get('auth_user')['nama'] ?? 'Guru BK';
        // PERBAIKAN (revisi 26 Agustus 2026, poin 4): guru_id disimpan
        // sebagai sumber kebenaran ownership — lihat
        // AuthorizesBk::informasiOwnedByGuru() (dipakai jalur API;
        // ownership check di Web mengikuti pola yang sama, lihat
        // update()/destroy() di bawah).
        $data['guru_id'] = Session::get('auth_id');
        InformasiBk::create($data);
        return redirect()->route('guru.informasi')->with('success', 'Informasi dipublikasikan.');
    }

    public function edit(int $id)
    {
        $row = InformasiBk::findOrFail($id);
        $this->assertOwnsInformasi($row);
        return view('guru.informasi-form', compact('row'));
    }

    public function update(Request $request, int $id)
    {
        $row = InformasiBk::findOrFail($id);
        // PERBAIKAN (revisi 26 Agustus 2026, poin 4): dulu di sini tidak
        // ada pengecekan kepemilikan sama sekali — Guru B bisa mengubah
        // informasi yang tercatat atas nama Guru A. Route ini hanya
        // dipasang di grup middleware role:guru, jadi role sudah
        // dijamin; yang kurang adalah kepemilikan per-baris.
        $this->assertOwnsInformasi($row);
        $data = $request->validate([
            'judul' => 'required|string|max:150',
            'kategori' => 'required|string|max:50',
            'isi' => 'required|string',
        ]);
        $row->update($data);
        return redirect()->route('guru.informasi')->with('success', 'Informasi diperbarui.');
    }

    public function destroy(int $id)
    {
        $row = InformasiBk::findOrFail($id);
        // PERBAIKAN (revisi 26 Agustus 2026, poin 4): sama seperti
        // update() — hapus sekarang juga menegakkan kepemilikan.
        $this->assertOwnsInformasi($row);
        $row->delete();
        return redirect()->route('guru.informasi')->with('success', 'Informasi dihapus.');
    }

    /**
     * Guru BK hanya boleh mengelola informasi miliknya sendiri. Pola sama
     * persis dengan AuthorizesBk::informasiOwnedByGuru() yang dipakai
     * jalur API: guru_id (kalau ada) SATU-SATUNYA sumber kebenaran;
     * fallback nama HANYA untuk baris lama yang guru_id-nya masih null.
     * Route ini hanya dipasang di grup role:guru, jadi tidak perlu jalur
     * bypass Admin di sini (beda dengan API yang juga melayani Admin).
     */
    private function assertOwnsInformasi(InformasiBk $row): void
    {
        $authId = Session::get('auth_id');

        if (!is_null($row->guru_id)) {
            if ((int) $row->guru_id === (int) $authId) {
                return;
            }
        } else {
            $nama = Session::get('auth_user')['nama'] ?? '';
            if ($nama !== '' && strcasecmp((string) $row->guru_bk, $nama) === 0) {
                return;
            }
        }

        abort(403, 'Informasi ini milik Guru BK lain.');
    }
}
