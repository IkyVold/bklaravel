<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Konseling;
use Illuminate\Http\Request;
use App\Services\AiChatService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

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
            $this->assertGuruChatAccess($konselingId);
        } else {
            $this->assertSiswaChatAccess($konselingId);
        }

        $this->ensureTable();
        $sessionId = 'konseling_' . $konselingId;
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
        $messages = $request->input('messages');
        if (!is_array($messages) || $messages === []) {
            $single = trim((string) $request->input('message', ''));
            if ($single === '') {
                return response()->json(['success' => false, 'reply' => 'Pesan kosong.'], 400);
            }
            $messages = [['role' => 'user', 'content' => $single]];
        }

        $result = $ai->chat($messages);

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
        $this->ensureTable();
        $sessionId = 'konseling_' . $row->id;
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
        $this->ensureTable();
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
            'session_id' => 'konseling_' . $row->id,
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

    protected function ensureTable(): void
    {
        if (Schema::hasTable('chat_messages')) {
            return;
        }
        Schema::create('chat_messages', function ($table) {
            $table->id();
            $table->string('session_id', 64)->index();
            $table->string('sender_id', 64)->nullable();
            $table->string('sender_name', 100)->nullable();
            $table->string('sender_type', 20)->nullable();
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });
    }
}
