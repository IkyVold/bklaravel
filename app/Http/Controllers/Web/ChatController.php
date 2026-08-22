<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Konseling;
use Illuminate\Http\Request;
use App\Services\AiChatService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function room(int $konselingId)
    {
        $row = $this->assertSiswaChatAccess($konselingId);
        return $this->renderRoom($row, 'siswa');
    }

    public function roomGuru(int $konselingId)
    {
        $row = $this->assertGuruChatAccess($konselingId);
        return $this->renderRoom($row, 'guru');
    }

    public function send(Request $request, int $konselingId)
    {
        $row = $this->assertSiswaChatAccess($konselingId);
        return $this->storeMessage($request, $row, 'siswa');
    }

    public function sendGuru(Request $request, int $konselingId)
    {
        $row = $this->assertGuruChatAccess($konselingId);
        return $this->storeMessage($request, $row, 'guru');
    }

    public function historyJson(Request $request, int $konselingId)
    {
        $role = Session::get('auth_role');
        if ($role === 'guru') {
            $row = $this->assertGuruChatAccess($konselingId);
        } else {
            $row = $this->assertSiswaChatAccess($konselingId);
        }

        $sessionId = $this->sessionIdFor($row);
        $afterId = (int) $request->query('after_id', 0);

        $q = ChatMessage::where('session_id', $sessionId)->orderBy('id');
        if ($afterId > 0) {
            $q->where('id', '>', $afterId);
        }
        $messages = $q->limit(200)->get()->map(fn ($m) => [
            'id' => $m->id,
            'sender_id' => (string) $m->sender_id,
            'sender_name' => $m->sender_name,
            'sender_type' => $m->sender_type,
            'message' => $m->message,
            'created_at' => optional($m->created_at)->format('c'),
            'time' => optional($m->created_at)->format('H:i'),
            'date' => optional($m->created_at)->translatedFormat('d M'),
        ]);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    public function ai(Request $request, AiChatService $ai)
    {
        // Rate limit sama seperti Api/ChatController::ai() — supaya siswa
        // tidak bisa memakai jalur web untuk melewati batas API dan
        // menghabiskan quota Groq.
        $key = 'ai-chat:' . (Session::get('auth_id') ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) { // 20 req / menit
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'reply' => "Terlalu banyak permintaan. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $messages = $request->input('messages');
        if (!is_array($messages) || $messages === []) {
            $single = trim((string) $request->input('message', ''));
            if ($single === '') {
                return response()->json(['success' => false, 'reply' => 'Pesan kosong.'], 400);
            }
            $messages = [['role' => 'user', 'content' => $single]];
        }

        // Paksa role user — cegah prompt injection
        $safe = [];
        foreach ($messages as $m) {
            if (!is_array($m)) continue;
            $c = trim((string) ($m['content'] ?? ''));
            if ($c === '') continue;
            if (mb_strlen($c) > 2000) $c = mb_substr($c, 0, 2000);
            $safe[] = ['role' => 'user', 'content' => $c];
        }
        if (count($safe) > 20) $safe = array_slice($safe, -20);
        if ($safe === []) {
            return response()->json(['success' => false, 'reply' => 'Pesan tidak valid.'], 400);
        }

        $result = $ai->chat($safe);

        if (!empty($result['error'])) {
            return response()->json([
                'success' => false,
                'reply' => $result['error']['message'] ?? 'Maaf, terjadi kesalahan.',
            ]);
        }

        return response()->json([
            'success' => true,
            'reply' => $result['reply'] ?? '',
        ]);
    }

    protected function renderRoom(Konseling $row, string $role)
    {
        $sessionId = $this->sessionIdFor($row);
        $messages = ChatMessage::where('session_id', $sessionId)->orderBy('id')->limit(300)->get();
        $siswa = $row->siswa;
        $guruName = $row->guru_bk ?: 'Guru BK';
        $siswaName = $siswa->nama ?? 'Siswa';

        $view = $role === 'guru' ? 'guru.chat' : 'siswa.chat';
        return view($view, [
            'row' => $row,
            'sessionId' => $sessionId,
            'messages' => $messages,
            'guruName' => $guruName,
            'siswaName' => $siswaName,
            'role' => $role,
            'myId' => (string) Session::get('auth_id'),
            'myName' => Session::get('auth_user')['nama'] ?? ($role === 'guru' ? $guruName : $siswaName),
        ]);
    }

    protected function storeMessage(Request $request, Konseling $row, string $senderType)
    {
        $data = $request->validate(['message' => 'required|string|max:2000']);
        $text = trim($data['message']);
        if ($text === '') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => 'Pesan kosong'], 422);
            }
            return back()->with('error', 'Pesan kosong');
        }

        $auth = Session::get('auth_user', []);
        $msg = ChatMessage::create([
            'session_id' => $this->sessionIdFor($row),
            'sender_id' => (string) Session::get('auth_id'),
            'sender_name' => $auth['nama'] ?? ($senderType === 'guru' ? 'Guru BK' : 'Siswa'),
            'sender_type' => $senderType,
            'message' => $text,
            'created_at' => now(),
        ]);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $msg->id,
                    'sender_id' => (string) $msg->sender_id,
                    'sender_name' => $msg->sender_name,
                    'sender_type' => $msg->sender_type,
                    'message' => $msg->message,
                    'created_at' => optional($msg->created_at)->format('c'),
                    'time' => optional($msg->created_at)->format('H:i'),
                    'date' => optional($msg->created_at)->translatedFormat('d M'),
                ],
            ]);
        }

        $route = $senderType === 'guru' ? 'guru.chat' : 'siswa.chat';
        return redirect()->route($route, $row->id);
    }

    /**
     * Satu sumber kebenaran untuk ID room chat: konseling.chat_session_id (UUID),
     * sama persis dengan yang dipakai Api/ChatController. Dibuat sekali (lazy)
     * kalau konsultasi ini belum pernah punya session, lalu dipakai seterusnya.
     * Web TIDAK BOLEH lagi memakai pola 'konseling_{id}' sendiri.
     */
    protected function sessionIdFor(Konseling $row): string
    {
        if (empty($row->chat_session_id)) {
            $row->chat_session_id = (string) Str::uuid();
            $row->save();
        }
        return $row->chat_session_id;
    }

    protected function assertSiswaChatAccess(int $konselingId): Konseling
    {
        $siswaId = Session::get('auth_id');
        $row = Konseling::with('siswa')->where('id', $konselingId)->where('siswa_id', $siswaId)->firstOrFail();
        $this->assertChatAllowed($row);
        return $row;
    }

    protected function assertGuruChatAccess(int $konselingId): Konseling
    {
        $nama = Session::get('auth_user')['nama'] ?? '';
        $guruId = Session::get('auth_id');
        $q = Konseling::with('siswa')->where('id', $konselingId);
        $q->where(function ($w) use ($guruId, $nama) {
            if (Schema::hasColumn('konseling', 'guru_id') && $guruId) {
                $w->where('guru_id', $guruId)
                    ->orWhere(function ($w2) use ($nama) {
                        $w2->whereNull('guru_id')->where('guru_bk', $nama);
                    });
            } else {
                $w->where('guru_bk', $nama);
            }
        });
        $row = $q->firstOrFail();
        $this->assertChatAllowed($row);
        return $row;
    }

    /** Hanya Daring + sudah dikonfirmasi + tidak dibatalkan */
    protected function assertChatAllowed(Konseling $row): void
    {
        $jenis = $row->jenis ?? '';
        $sk = $row->status_konfirmasi ?? '';
        $status = $row->status ?? '';
        $okJenis = in_array($jenis, ['Daring', 'Online'], true);
        $okKonf = in_array($sk, ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'], true);
        if ($status === 'Dibatalkan') {
            abort(403, 'Konseling dibatalkan — chat tidak tersedia.');
        }
        if (!$okJenis) {
            abort(403, 'Chat hanya untuk konseling Daring.');
        }
        if (!$okKonf) {
            abort(403, 'Chat tersedia setelah jadwal dikonfirmasi Guru BK.');
        }
    }
}
