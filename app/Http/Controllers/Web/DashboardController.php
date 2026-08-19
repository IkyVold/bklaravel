<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GuruBk;
use App\Models\InformasiBk;
use App\Models\Kepsek;
use App\Models\Konseling;
use App\Models\Notifikasi;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class DashboardController extends Controller
{
    public function siswa()
    {
        $id = Session::get('auth_id');
        $siswa = Siswa::findOrFail($id);

        $konseling = Konseling::where('siswa_id', $id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $chatSessions = $konseling->filter(function ($k) {
            $sk = $k->status_konfirmasi ?? '';
            $okKonf = in_array($sk, ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'], true);
            return ($k->jenis ?? '') === 'Daring' && $okKonf && ($k->status ?? '') !== 'Dibatalkan';
        })->values();

        $informasi = InformasiBk::orderByDesc('id')->limit(5)->get();

        $notif = 0;
        if (Schema::hasTable('notifikasi') && Schema::hasColumn('notifikasi', 'siswa_id')) {
            $notif = Notifikasi::where('siswa_id', $siswa->id)
                ->where('is_read', 0)
                ->count();
        }

        return view('siswa.dashboard', compact('siswa', 'konseling', 'informasi', 'notif', 'chatSessions'));
    }

    public function guru()
    {
        return redirect()->route('guru.konseling.index');
    }

    public function kepsek()
    {
        return redirect()->route('kepsek.dashboard');
    }

    public function admin(Request $request)
    {
        $guruList = GuruBk::orderBy('nama')->get();
        $kepsekList = Kepsek::orderBy('nama')->get();

        $editGuru = null;
        $editKepsek = null;
        if ($request->filled('edit_guru')) {
            $editGuru = GuruBk::find($request->integer('edit_guru'));
        }
        if ($request->filled('edit_kepsek')) {
            $editKepsek = Kepsek::find($request->integer('edit_kepsek'));
        }

        return view('admin.dashboard', compact('guruList', 'kepsekList', 'editGuru', 'editKepsek'));
    }
}
