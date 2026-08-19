<?php

namespace App\Services;

use App\Models\InformasiBk;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class AiChatService
{
    /**
     * Port dari backend Node: services/aiChatService.js
     * Input: array of ['role' => 'user'|'assistant'|'system', 'content' => string]
     */
    public function chat(array $messages): array
    {
        if ($messages === []) {
            return ['success' => false, 'error' => ['message' => 'Format pesan tidak valid', 'status' => 400]];
        }

        $apiKey = config('services.groq.key') ?: env('GROQ_API_KEY');
        $model = env('GROQ_MODEL', 'groq/compound');

        if (!$apiKey) {
            return [
                'success' => true,
                'reply' => 'Fitur AI belum dikonfigurasi (GROQ_API_KEY kosong). Silakan hubungi Guru BK secara langsung.',
            ];
        }

        $referensiText = $this->buildReferensiText();
        $systemPrompt = $this->buildSystemPrompt($referensiText);
        $safeMessages = $this->sanitizeMessages($messages);
        $finalMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $safeMessages
        );

        $maxAttempts = 3;
        $lastStatus = 0;

        try {
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $res = Http::withToken($apiKey)
                    ->timeout(45)
                    ->post('https://api.groq.com/openai/v1/chat/completions', [
                        'model' => $model,
                        'messages' => $finalMessages,
                        'max_tokens' => 512,
                        'temperature' => 0.7,
                    ]);

                $lastStatus = $res->status();

                if ($res->successful()) {
                    $reply = $res->json('choices.0.message.content')
                        ?? 'Maaf, saya tidak dapat memproses permintaan Anda saat ini.';

                    return ['success' => true, 'reply' => $reply];
                }

                if ($lastStatus === 401) {
                    return [
                        'success' => true,
                        'reply' => 'Maaf, layanan AI sedang tidak tersedia (API key tidak valid/kosong). Hubungi admin atau Guru BK.',
                    ];
                }

                // Rate limit — tunggu lalu coba lagi
                if ($lastStatus === 429) {
                    if ($attempt < $maxAttempts) {
                        // Baca Retry-After jika ada, default naik bertahap
                        $retryAfter = (int) ($res->header('Retry-After') ?: 0);
                        $waitSec = $retryAfter > 0 ? min($retryAfter, 8) : (1.5 * $attempt);
                        usleep((int) ($waitSec * 1_000_000));
                        continue;
                    }

                    return [
                        'success' => true,
                        'reply' => 'Maaf, server AI sedang sibuk (batas permintaan tercapai). Tunggu ±15 detik lalu kirim lagi, ya.',
                        'rate_limited' => true,
                    ];
                }

                $apiMsg = (string) ($res->json('error.message') ?? '');
                if (str_contains($apiMsg, 'does not exist') || str_contains($apiMsg, 'model_not_found')) {
                    return [
                        'success' => true,
                        'reply' => 'Model AI di .env tidak tersedia di Groq. Ganti GROQ_MODEL misalnya ke: groq/compound atau openai/gpt-oss-20b, lalu jalankan php artisan config:clear.',
                    ];
                }

                // Error lain — jangan retry berulang
                break;
            }

            return [
                'success' => true,
                'reply' => 'Maaf, layanan AI sementara gangguan. Silakan coba lagi nanti atau hubungi Guru BK.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => true,
                'reply' => 'Maaf, terjadi kesalahan pada server. Silakan coba lagi nanti atau hubungi Guru BK.',
            ];
        }
    }

    protected function buildReferensiText(): string
    {
        $fallback = '(Belum ada informasi tambahan dari Guru BK)';
        try {
            if (!Schema::hasTable('informasi_bk')) {
                return $fallback;
            }
            $rows = InformasiBk::query()
                ->orderByDesc('updated_at')
                ->get(['judul', 'kategori', 'isi']);

            if ($rows->isEmpty()) {
                return $fallback;
            }

            return $rows->map(function ($r) {
                return "### {$r->judul} ({$r->kategori})\n{$r->isi}";
            })->implode("\n\n");
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    protected function buildSystemPrompt(string $referensiText): string
    {
        return <<<PROMPT
Anda adalah konselor BK profesional untuk siswa SMP/SMA.

**BATASAN KETAT - HANYA 6 KATEGORI KONSELING SEKOLAH INI:**
1. AKADEMIK - Kesulitan belajar, ujian, nilai, tugas, PR, motivasi belajar, konsentrasi, cara belajar efektif
2. SOSIAL - Pertemanan, pergaulan, konflik dengan teman, rasa dikucilkan, cara berbaur
3. PRIBADI - Stres, cemas, kepercayaan diri rendah, emosi, perasaan, overthinking, kegelisahan
4. KARIR - Cita-cita, pilihan jurusan SMA/SMK, rencana kuliah/kerja, bakat dan minat
5. BULLYING - Perundungan, dihina, dijauhi, intimidasi, cyberbullying, cara melaporkan
6. KELUARGA - Masalah dengan orang tua/saudara, kondisi rumah, broken home, komunikasi keluarga

**ATURAN YANG HARUS DIPATUHI:**
- Jika pertanyaan di LUAR 6 kategori di atas DAN di luar topik FAQ referensi di bawah, jawab dengan tegas:
  "Maaf, saya adalah asisten konseling BK. Saya hanya bisa membantu terkait Akademik, Sosial, Pribadi, Karir, Bullying, Keluarga, atau info seputar sekolah/beasiswa/pendaftaran PT. Ada masalah yang ingin kamu ceritakan?"
- JANGAN pernah menjawab pertanyaan tentang: Matematika, Fisika, Kimia, Biologi, Sejarah, Geografi, Coding, Programming, Game, Film, Musik, Olahraga, atau pengetahuan umum lainnya
- Gunakan bahasa yang hangat, lembut, empatik, dan mendukung seperti konselor profesional
- Panggil siswa dengan "kamu" atau "adik" (jika terkesan lebih muda)
- Jangan memberikan diagnosis medis (depresi, gangguan kecemasan, dll) - cukup beri dukungan psikologis sederhana
- Jika siswa menunjukkan tanda-tanda bahaya (ingin menyakiti diri), segera sarankan untuk menemui guru BK atau orang dewasa terpercaya
- Panjang jawaban: 2-4 kalimat yang padat dan membantu
- Beri solusi praktis yang bisa dilakukan siswa

**FAQ / INFORMASI SEKOLAH-KARIR (dikelola Guru BK):**
Selain 6 kategori konseling di atas, Anda BOLEH menjawab pertanyaan seputar beasiswa, pendaftaran perguruan tinggi, jalur masuk (SNBP/SNBT/mandiri), bimbingan karir, dan info sekolah — TAPI HANYA berdasarkan referensi di bawah ini. JANGAN mengarang detail (tanggal, syarat, kuota, link) yang tidak ada di referensi. Jika pertanyaan relevan tapi infonya tidak ada di referensi, jawab jujur: "Maaf, saya belum punya info spesifik soal itu. Coba tanya langsung ke Guru BK ya."

--- REFERENSI ---
{$referensiText}
--- AKHIR REFERENSI ---

Ingat: Anda BUKAN guru mata pelajaran. Anda adalah KONSELOR BK. Fokus pada membantu siswa mengatasi masalah pribadi dan sosial mereka, plus info sekolah/karir dari referensi di atas.
PROMPT;
    }

    /** Hanya izinkan role user/assistant; batasi history agar hemat token (kurangi rate-limit) */
    protected function sanitizeMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if (!is_array($m)) {
                continue;
            }
            $role = $m['role'] ?? '';
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if (mb_strlen($content) > 2000) {
                $content = mb_substr($content, 0, 2000);
            }
            $out[] = ['role' => $role, 'content' => $content];
        }
        // Ambil maksimal 8 pesan terakhir (hemat token / RPM)
        if (count($out) > 8) {
            $out = array_slice($out, -8);
        }
        return $out;
    }
}
