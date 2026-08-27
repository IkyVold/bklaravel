<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GuruBk;
use App\Models\Kepsek;
use Illuminate\Http\Request;

class AkunController extends Controller
{
    public function guruIndex()
    {
        return redirect()->route('admin.dashboard', ['tab' => 'guru']);
    }

    public function guruCreate()
    {
        return redirect()->route('admin.dashboard', ['tab' => 'guru']);
    }

    public function guruStore(Request $request)
    {
        // PERBAIKAN (revisi 25 Agustus 2026, poin 12): min:4 dinaikkan ke
        // min:8 — disamakan dengan Api/AkunController::createGuru().
        $data = $request->validate([
            'username' => 'required|string|max:50|unique:guru_bk,username',
            'password' => 'required|string|min:8',
            'nama' => 'required|string|max:100',
            'spesialisasi' => 'nullable|string|max:100',
            'npsn' => 'nullable|string|max:30',
            'alamat' => 'nullable|string|max:150',
        ]);
        GuruBk::create($data);
        return redirect()->route('admin.dashboard', ['tab' => 'guru'])
            ->with('success', 'Akun guru ditambahkan.');
    }

    public function guruEdit(int $id)
    {
        return redirect()->route('admin.dashboard', ['tab' => 'guru', 'edit_guru' => $id]);
    }

    public function guruUpdate(Request $request, int $id)
    {
        $row = GuruBk::findOrFail($id);
        // PERBAIKAN (revisi 25 Agustus 2026, poin 12): min:4 dinaikkan ke
        // min:8 pada update juga, supaya tidak jadi celah untuk melewati
        // aturan min:8 yang sudah dipasang di guruStore().
        $data = $request->validate([
            'username' => 'required|string|max:50|unique:guru_bk,username,' . $id,
            'password' => 'nullable|string|min:8',
            'nama' => 'required|string|max:100',
            'spesialisasi' => 'nullable|string|max:100',
            'npsn' => 'nullable|string|max:30',
            'alamat' => 'nullable|string|max:150',
            'is_active' => 'nullable|boolean',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['is_active'] = $request->boolean('is_active');
        $passwordChanged = !empty($data['password']);
        $row->update($data);

        // PERBAIKAN (revisi 26 Agustus 2026, poin 3): endpoint update ini
        // (dari form Admin) bisa menonaktifkan akun atau mereset password
        // Guru BK. Token API lama wajib dicabut di sini juga; untuk sesi
        // web yang sudah berjalan, RoleAuth middleware akan menolaknya
        // pada request berikutnya begitu is_active tidak lagi true.
        if ($passwordChanged || !$row->is_active) {
            $row->tokens()->delete();
        }

        return redirect()->route('admin.dashboard', ['tab' => 'guru'])
            ->with('success', 'Akun guru diperbarui.');
    }

    public function guruDeactivate(int $id)
    {
        $row = GuruBk::findOrFail($id);
        $row->update(['is_active' => false]);
        // PERBAIKAN (revisi 26 Agustus 2026, poin 3): cabut token API lama
        // — session web yang sudah berjalan akan tertolak sendiri di
        // request berikutnya lewat pengecekan is_active pada RoleAuth.
        $row->tokens()->delete();
        return redirect()->route('admin.dashboard', ['tab' => 'guru'])
            ->with('success', 'Akun Guru BK dinonaktifkan.');
    }

    public function guruActivate(int $id)
    {
        $row = GuruBk::findOrFail($id);
        $row->update(['is_active' => true]);
        return redirect()->route('admin.dashboard', ['tab' => 'guru'])
            ->with('success', 'Akun Guru BK diaktifkan kembali.');
    }

    public function kepsekIndex()
    {
        return redirect()->route('admin.dashboard', ['tab' => 'kepsek']);
    }

    public function kepsekCreate()
    {
        return redirect()->route('admin.dashboard', ['tab' => 'kepsek']);
    }

    public function kepsekStore(Request $request)
    {
        // PERBAIKAN (revisi 25 Agustus 2026, poin 12): min:4 dinaikkan ke
        // min:8 — disamakan dengan akun Guru BK.
        $data = $request->validate([
            'username' => 'required|string|max:50|unique:kepsek,username',
            'password' => 'required|string|min:8',
            'nama' => 'required|string|max:100',
            'npsn' => 'nullable|string|max:30',
        ]);
        Kepsek::create($data);
        return redirect()->route('admin.dashboard', ['tab' => 'kepsek'])
            ->with('success', 'Akun kepsek ditambahkan.');
    }

    public function kepsekEdit(int $id)
    {
        return redirect()->route('admin.dashboard', ['tab' => 'kepsek', 'edit_kepsek' => $id]);
    }

    public function kepsekUpdate(Request $request, int $id)
    {
        $row = Kepsek::findOrFail($id);
        // PERBAIKAN (revisi 25 Agustus 2026, poin 12): min:4 dinaikkan ke
        // min:8 pada update juga — sama seperti guruUpdate().
        $data = $request->validate([
            'username' => 'required|string|max:50|unique:kepsek,username,' . $id,
            'password' => 'nullable|string|min:8',
            'nama' => 'required|string|max:100',
            'npsn' => 'nullable|string|max:30',
            'is_active' => 'nullable|boolean',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['is_active'] = $request->boolean('is_active');
        $passwordChanged = !empty($data['password']);
        $row->update($data);

        // PERBAIKAN (revisi 26 Agustus 2026, poin 3): sama seperti
        // guruUpdate() — cabut token API lama kalau akun dinonaktifkan
        // atau password-nya baru saja diganti.
        if ($passwordChanged || !$row->is_active) {
            $row->tokens()->delete();
        }

        return redirect()->route('admin.dashboard', ['tab' => 'kepsek'])
            ->with('success', 'Akun kepsek diperbarui.');
    }

    public function kepsekDeactivate(int $id)
    {
        $row = Kepsek::findOrFail($id);
        $row->update(['is_active' => false]);
        // PERBAIKAN (revisi 26 Agustus 2026, poin 3): lihat penjelasan di
        // guruDeactivate().
        $row->tokens()->delete();
        return redirect()->route('admin.dashboard', ['tab' => 'kepsek'])
            ->with('success', 'Akun Kepala Sekolah dinonaktifkan.');
    }

    public function kepsekActivate(int $id)
    {
        $row = Kepsek::findOrFail($id);
        $row->update(['is_active' => true]);
        return redirect()->route('admin.dashboard', ['tab' => 'kepsek'])
            ->with('success', 'Akun Kepala Sekolah diaktifkan kembali.');
    }
}
