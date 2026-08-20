<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Models\Notifikasi;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    use AuthorizesBk;

    private function penerimaKey(Request $request): array
    {
        $user = $request->user();
        $role = $this->currentRole($request) ?? '';
        $id = $role === 'siswa' ? ($user->nis ?? $user->id) : ($user->username ?? $user->id);
        return [(string) $id, $role];
    }

    public function list(Request $request): JsonResponse
    {
        [$id, $role] = $this->penerimaKey($request);

        $rows = Notifikasi::where('penerima_id', $id)
            ->where('penerima_role', $role)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        [$penerimaId, $role] = $this->penerimaKey($request);

        $n = Notifikasi::where('id', $id)
            ->where('penerima_id', $penerimaId)
            ->where('penerima_role', $role)
            ->first();

        if (!$n) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $n->dibaca = true;
        $n->save();
        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        [$id, $role] = $this->penerimaKey($request);

        Notifikasi::where('penerima_id', $id)
            ->where('penerima_role', $role)
            ->where('dibaca', false)
            ->update(['dibaca' => true]);

        return response()->json(['success' => true]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        [$id, $role] = $this->penerimaKey($request);

        $endpoint = $request->input('endpoint');
        $keys = $request->input('keys', []);

        if (!$endpoint) {
            return response()->json(['success' => false, 'message' => 'endpoint wajib'], 400);
        }

        PushSubscription::updateOrCreate(
            ['user_id' => $id, 'role' => $role, 'endpoint' => $endpoint],
            ['p256dh' => $keys['p256dh'] ?? null, 'auth' => $keys['auth'] ?? null]
        );

        return response()->json(['success' => true]);
    }
}
