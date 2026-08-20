<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Models\ChatMessage;
use App\Models\Konseling;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    use AuthorizesBk;

    public function history(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id');
        $konselingId = $request->query('konseling_id');

        if (!$sessionId && !$konselingId) {
            return response()->json(['success' => false, 'message' => 'session_id atau konseling_id wajib'], 400);
        }

        if ($konselingId) {
            $konseling = Konseling::find($konselingId);
            if (!$konseling) {
                return response()->json(['success' => false, 'message' => 'Konseling tidak ditemukan'], 404);
            }
            $this->assertGuruOwnsKonseling($request, $konseling);
            $sessionId = $konseling->chat_session_id ?? ('konseling_' . $konseling->id);
        } else {
            // Cari konseling yang punya session ini
            $konseling = Konseling::where('chat_session_id', $sessionId)->first();
            if ($konseling) {
                $this->assertGuruOwnsKonseling($request, $konseling);
            } else {
                // Fallback: hanya staff boleh lihat history arbitrary (untuk kompatibilitas)
                if (!$this->isStaff($request)) {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
                }
            }
        }

        $rows = ChatMessage::where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function send(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'session_id' => 'required|string|max:150',
            'message' => 'required|string|max:5000',
            'konseling_id' => 'nullable|integer',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $user = $request->user();
        $role = $this->currentRole($request);
        $sessionId = $request->input('session_id');
        $konselingId = $request->input('konseling_id');

        if ($konselingId) {
            $konseling = Konseling::find($konselingId);
            if (!$konseling) {
                return response()->json(['success' => false, 'message' => 'Konseling tidak ditemukan'], 404);
            }
            $this->assertGuruOwnsKonseling($request, $konseling);
            if (in_array($konseling->status, ['Dibatalkan', 'Ditolak'], true)) {
                return response()->json(['success' => false, 'message' => 'Konseling sudah dibatalkan'], 403);
            }
            $sessionId = $konseling->chat_session_id ?? $sessionId;
        }

        // Identitas pengirim diambil dari token, BUKAN dari client
        $senderId = (string) $user->id;
        $senderName = $user->nama ?? $user->username ?? 'User';
        $senderType = $role === 'siswa' ? 'siswa' : 'guru';

        $row = ChatMessage::create([
            'session_id' => $sessionId,
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'sender_type' => $senderType,
            'message' => $request->input('message'),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $row], 201);
    }

    /**
     * AI Chatbot — rate limited, role forced to user, auth required.
     */
    public function ai(Request $request, AiChatService $ai): JsonResponse
    {
        $user = $request->user();
        $key = 'ai-chat:' . ($user?->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 20)) { // 20 req / menit
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'error' => [
                    'message' => "Terlalu banyak permintaan. Coba lagi dalam {$seconds} detik.",
                    'status' => 429,
                ],
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $messages = $request->input('messages');

        if (!is_array($messages) || $messages === []) {
            $single = trim((string) $request->input('message', ''));
            if ($single === '') {
                return response()->json([
                    'error' => ['message' => 'Format pesan tidak valid', 'status' => 400],
                ], 400);
            }
            $messages = [['role' => 'user', 'content' => $single]];
        }

        // PAKSA semua message dari client menjadi role "user" — cegah prompt injection
        $safeMessages = [];
        foreach ($messages as $m) {
            if (!is_array($m)) continue;
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') continue;
            // Batasi panjang
            if (mb_strlen($content) > 2000) {
                $content = mb_substr($content, 0, 2000);
            }
            $safeMessages[] = ['role' => 'user', 'content' => $content];
        }

        // Batasi jumlah pesan (mencegah abuse quota)
        if (count($safeMessages) > 20) {
            $safeMessages = array_slice($safeMessages, -20);
        }

        if ($safeMessages === []) {
            return response()->json([
                'error' => ['message' => 'Tidak ada pesan valid', 'status' => 400],
            ], 400);
        }

        $result = $ai->chat($safeMessages);

        if (!empty($result['error'])) {
            $status = (int) ($result['error']['status'] ?? 503);
            if ($status === 401) {
                $status = 503;
            }
            return response()->json(['error' => $result['error']], $status >= 400 ? $status : 503);
        }

        return response()->json([
            'success' => true,
            'reply' => $result['reply'] ?? '',
        ]);
    }
}
