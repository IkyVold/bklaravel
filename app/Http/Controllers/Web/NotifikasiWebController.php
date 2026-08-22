<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Models\NotifikasiGuru;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class NotifikasiWebController extends Controller
{
    public function readAll(Request $request)
    {
        $role = Session::get('auth_role');
        if ($role === 'guru') {
            $username = Session::get('auth_user')['username'] ?? '';
            if ($username) {
                NotifikasiGuru::where('guru_username', $username)->where('is_read', 0)->update(['is_read' => 1]);
            }
            return back()->with('success', 'Semua notifikasi ditandai dibaca.');
        }

        // Skema tunggal: penerima_id (NIS) + penerima_role, sama dengan
        // Api\NotifikasiController — bukan lagi siswa_id/is_read.
        $nis = Session::get('auth_user')['nis'] ?? null;
        if ($nis) {
            Notifikasi::untukPenerima((string) $nis, 'siswa')->belumDibaca()->update(['dibaca' => true]);
        }
        return back()->with('success', 'Semua notifikasi ditandai dibaca.');
    }

    public function markReadGuru(int $id)
    {
        $username = Session::get('auth_user')['username'] ?? '';
        $n = NotifikasiGuru::where('id', $id)->where('guru_username', $username)->firstOrFail();
        $n->is_read = true;
        $n->save();
        if ($n->konseling_id) {
            return redirect()->route('guru.konseling.show', $n->konseling_id);
        }
        return back();
    }

    public function jsonGuru()
    {
        $username = Session::get('auth_user')['username'] ?? '';
        $rows = NotifikasiGuru::where('guru_username', $username)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'judul' => $r->judul,
                'pesan' => $r->pesan,
                'isRead' => (bool) $r->is_read,
                'konselingId' => $r->konseling_id,
                'createdAt' => optional($r->created_at)->toIso8601String(),
            ]);
        $unread = NotifikasiGuru::where('guru_username', $username)->where('is_read', 0)->count();
        return response()->json(['notifikasi' => $rows, 'unreadCount' => $unread]);
    }

    public static function notifyGuru(string $username, string $judul, string $pesan, ?int $konselingId = null, string $tipe = 'pengajuan'): void
    {
        if ($username === '') {
            return;
        }
        try {
            NotifikasiGuru::create([
                'guru_username' => $username,
                'konseling_id' => $konselingId,
                'tipe' => $tipe,
                'judul' => $judul,
                'pesan' => $pesan,
                'is_read' => false,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // jangan gagalkan alur utama
        }
    }
}
