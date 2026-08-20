<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            if (!Schema::hasColumn('konseling', 'chat_session_id')) {
                $table->uuid('chat_session_id')->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('konseling', 'guru_id')) {
                $table->unsignedBigInteger('guru_id')->nullable()->after('siswa_id')->index();
            }
            if (!Schema::hasColumn('konseling', 'alasan_batal')) {
                $table->text('alasan_batal')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            $table->dropColumn(['chat_session_id']);
        });
    }
};
