<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->currentAccessToken()?->abilities[0] ?? '';
        $id = $role === 'siswa' ? ($user->nis ?? $user->id) : ($user->username ?? $user->id);

        $rows = Notifikasi::where('penerima_id', (string) $id)
            ->where('penerima_role', $role)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $n = Notifikasi::find($id);
        if (!$n) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $n->dibaca = true;
        $n->save();
        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->currentAccessToken()?->abilities[0] ?? '';
        $id = $role === 'siswa' ? ($user->nis ?? $user->id) : ($user->username ?? $user->id);

        Notifikasi::where('penerima_id', (string) $id)
            ->where('penerima_role', $role)
            ->where('dibaca', false)
            ->update(['dibaca' => true]);

        return response()->json(['success' => true]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->currentAccessToken()?->abilities[0] ?? '';
        $id = $role === 'siswa' ? ($user->nis ?? $user->id) : ($user->username ?? $user->id);

        $endpoint = $request->input('endpoint');
        $keys = $request->input('keys', []);

        if (!$endpoint) {
            return response()->json(['success' => false, 'message' => 'endpoint wajib'], 400);
        }

        PushSubscription::updateOrCreate(
            ['user_id' => (string) $id, 'role' => $role, 'endpoint' => $endpoint],
            ['p256dh' => $keys['p256dh'] ?? null, 'auth' => $keys['auth'] ?? null]
        );

        return response()->json(['success' => true]);
    }
}
