<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sebelumnya tabel ini dibuat diam-diam oleh
 * Web\NotifikasiWebController::ensureTable() saat request pertama datang.
 * Itu membuat struktur database bergantung pada urutan request, bukan pada
 * migration — kalau migration belum lengkap, seharusnya deployment gagal
 * dan diperbaiki, bukan controller membuat tabel dengan struktur
 * alternatif secara diam-diam. Sekarang tabel ini murni berasal dari
 * migration, sama seperti tabel lainnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifikasi_guru')) {
            return;
        }

        Schema::create('notifikasi_guru', function (Blueprint $table) {
            $table->id();
            $table->string('guru_username', 50);
            $table->unsignedInteger('konseling_id')->nullable();
            $table->string('tipe', 30)->default('pengajuan');
            $table->string('judul', 150);
            $table->text('pesan');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['guru_username', 'created_at'], 'idx_notif_guru_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_guru');
    }
};
