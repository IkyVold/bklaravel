<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\JadwalRutin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class JadwalRutinController extends Controller
{
    /**
     * Durasi default (menit) yang dipakai untuk cek overlap ketika sebuah
     * slot tidak mengisi jam_selesai (kolom ini nullable). Tanpa asumsi
     * ini, slot tanpa jam_selesai tidak akan pernah terdeteksi bentrok
     * dengan slot lain di sekitarnya.
     */
    private const DEFAULT_DURATION_MINUTES = 60;

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

        $jamMulai = $this->normalizeTime($data['jam_mulai']);
        $jamSelesai = !empty($data['jam_selesai']) ? $this->normalizeTime($data['jam_selesai']) : null;

        try {
            $this->assertValidInterval($jamMulai, $jamSelesai);
            $this->assertNoOverlap($guruId, (int) $data['hari'], $jamMulai, $jamSelesai);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $payload = [
            'guru_id' => $guruId,
            'hari' => (int) $data['hari'],
            'jam_mulai' => $jamMulai,
        ];
        if ($jamSelesai !== null && Schema::hasColumn('jadwal_rutin', 'jam_selesai')) {
            $payload['jam_selesai'] = $jamSelesai;
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

        $jamMulai = $this->normalizeTime($data['jam_mulai']);
        $jamSelesai = !empty($data['jam_selesai']) ? $this->normalizeTime($data['jam_selesai']) : null;

        try {
            $this->assertValidInterval($jamMulai, $jamSelesai);
            $this->assertNoOverlap($guruId, (int) $data['hari'], $jamMulai, $jamSelesai, $slot->id);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $slot->update([
            'hari' => (int) $data['hari'],
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
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

    /**
     * Normalisasi input jam ke format H:i:s (input bisa H:i atau H:i:s).
     */
    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }

    /**
     * Pastikan jam_selesai > jam_mulai ketika jam_selesai diisi. Sebelumnya
     * hanya format waktu yang divalidasi, sehingga slot seperti
     * 10.00–09.00 masih bisa tersimpan.
     *
     * @throws ValidationException
     */
    private function assertValidInterval(string $jamMulai, ?string $jamSelesai): void
    {
        if ($jamSelesai === null) {
            return;
        }
        if ($jamSelesai <= $jamMulai) {
            throw ValidationException::withMessages([
                'jam_selesai' => 'Jam selesai harus lebih besar dari jam mulai.',
            ]);
        }
    }

    /**
     * Pastikan slot baru/diedit tidak overlap dengan slot lain milik guru
     * yang sama pada hari yang sama. Slot tanpa jam_selesai dianggap
     * berdurasi DEFAULT_DURATION_MINUTES untuk keperluan pengecekan ini.
     * Hanya slot yang masih aktif yang diperiksa — slot nonaktif dianggap
     * tidak lagi dipakai sehingga tidak perlu diikutkan.
     *
     * @throws ValidationException
     */
    private function assertNoOverlap(
        int $guruId,
        int $hari,
        string $jamMulai,
        ?string $jamSelesai,
        ?int $excludeId = null
    ): void {
        $newStart = strtotime($jamMulai);
        $newEnd = $jamSelesai !== null
            ? strtotime($jamSelesai)
            : $newStart + self::DEFAULT_DURATION_MINUTES * 60;

        $query = JadwalRutin::where('guru_id', $guruId)
            ->where('hari', $hari)
            ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        foreach ($query->get() as $existing) {
            $existingStart = strtotime((string) $existing->jam_mulai);
            $existingEnd = $existing->jam_selesai
                ? strtotime((string) $existing->jam_selesai)
                : $existingStart + self::DEFAULT_DURATION_MINUTES * 60;

            // Dua interval overlap jika salah satu mulai sebelum yang lain
            // berakhir, di kedua arah.
            if ($newStart < $existingEnd && $existingStart < $newEnd) {
                $existingLabel = substr((string) $existing->jam_mulai, 0, 5)
                    . ($existing->jam_selesai ? '–' . substr((string) $existing->jam_selesai, 0, 5) : '');
                throw ValidationException::withMessages([
                    'jam_mulai' => "Slot bentrok dengan jadwal rutin lain pada hari yang sama ({$existingLabel}).",
                ]);
            }
        }
    }
}
