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
        InformasiBk::create($data);
        return redirect()->route('guru.informasi')->with('success', 'Informasi dipublikasikan.');
    }

    public function edit(int $id)
    {
        $row = InformasiBk::findOrFail($id);
        return view('guru.informasi-form', compact('row'));
    }

    public function update(Request $request, int $id)
    {
        $row = InformasiBk::findOrFail($id);
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
        InformasiBk::findOrFail($id)->delete();
        return redirect()->route('guru.informasi')->with('success', 'Informasi dihapus.');
    }
}
