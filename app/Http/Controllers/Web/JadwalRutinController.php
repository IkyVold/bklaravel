<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\JadwalRutin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class JadwalRutinController extends Controller
{
    public function index()
    {
        $guruId = (int) Session::get('auth_id');
        $slots = JadwalRutin::where('guru_id', $guruId)
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return view('guru.jadwal-rutin', [
            'slots' => $slots,
            'hariList' => JadwalRutin::HARI,
            'activeTab' => 'jadwal-rutin',
        ]);
    }

    public function store(Request $request)
    {
        $guruId = (int) Session::get('auth_id');
        $data = $request->validate([
            'hari' => 'required|integer|min:1|max:7',
            'jam_mulai' => 'required|string|max:8',
            'jam_selesai' => 'nullable|string|max:8',
            'jenis' => 'required|string|in:Luring,Daring',
            'keterangan' => 'nullable|string|max:150',
        ]);

        $payload = [
            'guru_id' => $guruId,
            'hari' => (int) $data['hari'],
            'jam_mulai' => strlen($data['jam_mulai']) === 5 ? $data['jam_mulai'] . ':00' : $data['jam_mulai'],
        ];
        if (!empty($data['jam_selesai']) && Schema::hasColumn('jadwal_rutin', 'jam_selesai')) {
            $js = $data['jam_selesai'];
            $payload['jam_selesai'] = strlen($js) === 5 ? $js . ':00' : $js;
        }
        if (Schema::hasColumn('jadwal_rutin', 'jenis')) {
            $payload['jenis'] = $data['jenis'];
        }
        if (Schema::hasColumn('jadwal_rutin', 'keterangan')) {
            $payload['keterangan'] = $data['keterangan'] ?? null;
        }
        if (Schema::hasColumn('jadwal_rutin', 'is_active')) {
            $payload['is_active'] = true;
        }

        try {
            JadwalRutin::create($payload);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'hari' => 'Gagal simpan slot: ' . $e->getMessage()
                    . ' — Pastikan tabel jadwal_rutin lengkap (jalankan SQL fix kolom).',
            ]);
        }

        return redirect()->route('guru.jadwal-rutin.index')
            ->with('success', 'Slot jadwal rutin ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $guruId = (int) Session::get('auth_id');
        $slot = JadwalRutin::where('guru_id', $guruId)->findOrFail($id);

        $data = $request->validate([
            'hari' => 'required|integer|min:1|max:7',
            'jam_mulai' => 'required|string|max:8',
            'jam_selesai' => 'nullable|string|max:8',
            'jenis' => 'required|string|in:Luring,Daring',
            'keterangan' => 'nullable|string|max:150',
            'is_active' => 'nullable|boolean',
        ]);

        $slot->update([
            'hari' => (int) $data['hari'],
            'jam_mulai' => $data['jam_mulai'],
            'jam_selesai' => $data['jam_selesai'] ?: null,
            'jenis' => $data['jenis'],
            'keterangan' => $data['keterangan'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('guru.jadwal-rutin.index')
            ->with('success', 'Slot jadwal rutin diperbarui.');
    }

    public function destroy(int $id)
    {
        $guruId = (int) Session::get('auth_id');
        $slot = JadwalRutin::where('guru_id', $guruId)->findOrFail($id);
        $slot->delete();

        return redirect()->route('guru.jadwal-rutin.index')
            ->with('success', 'Slot jadwal rutin dihapus.');
    }

    public function toggle(int $id)
    {
        $guruId = (int) Session::get('auth_id');
        $slot = JadwalRutin::where('guru_id', $guruId)->findOrFail($id);
        $slot->update(['is_active' => !$slot->is_active]);

        return redirect()->route('guru.jadwal-rutin.index')
            ->with('success', $slot->is_active ? 'Slot diaktifkan.' : 'Slot dinonaktifkan.');
    }
}
