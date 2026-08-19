<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return response()->json(['success' => false, 'message' => 'session_id wajib'], 400);
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
            'sender_id' => 'required|string|max:50',
            'sender_name' => 'nullable|string|max:100',
            'sender_type' => 'required|in:siswa,guru',
            'message' => 'required|string',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $row = ChatMessage::create(array_merge($v->validated(), ['created_at' => now()]));

        return response()->json(['success' => true, 'data' => $row], 201);
    }

    /**
     * Kompatibel dengan backend Node: POST /api/chat  body: { messages: [...] }
     * Juga menerima format lama: { message: "teks" }
     */
    public function ai(Request $request, AiChatService $ai): JsonResponse
    {
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

        $result = $ai->chat($messages);

        if (!empty($result['error'])) {
            // Jangan pakai 401 (bentrok dengan auth); gunakan 503 Service Unavailable
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
