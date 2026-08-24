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
use Illuminate\Support\Str;

class ChatController extends Controller
{
    use AuthorizesBk;

    public function history(Request $request): JsonResponse
    {
        $v = Validator::make($request->query(), [
            'konseling_id' => 'required|integer',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'konseling_id wajib'], 400);
        }

        $konseling = Konseling::find($request->query('konseling_id'));
        if (!$konseling) {
            return response()->json(['success' => false, 'message' => 'Konseling tidak ditemukan'], 404);
        }
        $this->assertCanViewKonseling($request, $konseling);

        if (!$konseling->chat_session_id) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $rows = ChatMessage::where('session_id', $konseling->chat_session_id)
            ->orderBy('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function send(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'konseling_id' => 'required|integer',
            'message' => 'required|string|max:5000',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $konseling = Konseling::find($request->input('konseling_id'));
        if (!$konseling) {
            return response()->json(['success' => false, 'message' => 'Konseling tidak ditemukan'], 404);
        }

        // Kepemilikan/keanggotaan sesi selalu diperiksa di server; session_id
        // TIDAK pernah diterima langsung dari client.
        //
        // PERBAIKAN (revisi 24 Agustus 2026, poin 2): sebelumnya di sini
        // dipakai assertCanViewKonseling(), yang SENGAJA meloloskan
        // Admin & Kepsek untuk keperluan monitoring (lihat data). Karena
        // dipakai juga di endpoint kirim pesan, Admin/Kepsek jadi ikut
        // bisa MENGIRIM chat konseling siswa, padahal mereka bukan
        // peserta sesi. assertCanChatKonseling() hanya meloloskan siswa
        // pemilik atau Guru BK pemilik.
        $this->assertCanChatKonseling($request, $konseling);

        if (in_array($konseling->status, ['Dibatalkan', 'Ditolak'], true)) {
            return response()->json(['success' => false, 'message' => 'Konseling sudah dibatalkan'], 403);
        }

        // PERBAIKAN (revisi 24 Agustus 2026, poin 4): sebelumnya hanya
        // status Dibatalkan/Ditolak yang diperiksa, sehingga chat bisa
        // dipakai walau konsultasi belum dikonfirmasi Guru BK (status
        // 'Menunggu') atau untuk konsultasi Luring (tatap muka langsung,
        // yang seharusnya tidak butuh chat online). Dua aturan tambahan
        // berikut menutup celah itu. Urutan pesan error sengaja spesifik
        // per aturan supaya klien tahu persis alasan penolakan.
        if (!$konseling->isDaring()) {
            return response()->json([
                'success' => false,
                'message' => 'Chat hanya tersedia untuk konsultasi Daring',
            ], 403);
        }
        if (!$konseling->isKonfirmasi()) {
            return response()->json([
                'success' => false,
                'message' => 'Chat hanya tersedia setelah konsultasi dikonfirmasi Guru BK',
            ], 403);
        }

        if (!$konseling->chat_session_id) {
            $konseling->chat_session_id = (string) Str::uuid();
            $konseling->save();
        }

        $user = $request->user();
        $role = $this->currentRole($request);

        // Identitas pengirim diambil dari token, BUKAN dari client.
        // assertCanChatKonseling() di atas menjamin $role di sini hanya
        // 'siswa' atau 'guru' (Admin/Kepsek sudah ditolak sebelum sampai
        // sini), jadi pemetaan sender_type berikut aman dan tidak lagi
        // bisa mencatat pesan Admin/Kepsek seolah-olah dari 'guru'.
        $senderId = (string) $user->id;
        $senderName = $user->nama ?? $user->username ?? 'User';
        $senderType = $role === 'siswa' ? 'siswa' : 'guru';

        $row = ChatMessage::create([
            'session_id' => $konseling->chat_session_id,
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
