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
