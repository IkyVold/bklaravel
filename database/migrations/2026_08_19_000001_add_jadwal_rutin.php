<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jadwal_rutin')) {
            Schema::create('jadwal_rutin', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('guru_id');
                $table->tinyInteger('hari'); // 1=Senin ... 7=Minggu
                $table->time('jam_mulai');
                $table->time('jam_selesai')->nullable();
                $table->string('jenis', 20)->default('Luring'); // Luring|Daring
                $table->string('keterangan', 150)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('guru_id');
            });
        }

        if (Schema::hasTable('konseling')) {
            Schema::table('konseling', function (Blueprint $table) {
                if (!Schema::hasColumn('konseling', 'tipe_jadwal')) {
                    $table->string('tipe_jadwal', 20)->default('Nonrutin')->after('jenis');
                }
                if (!Schema::hasColumn('konseling', 'jadwal_rutin_id')) {
                    $table->unsignedBigInteger('jadwal_rutin_id')->nullable()->after('tipe_jadwal');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('konseling')) {
            Schema::table('konseling', function (Blueprint $table) {
                if (Schema::hasColumn('konseling', 'jadwal_rutin_id')) {
                    $table->dropColumn('jadwal_rutin_id');
                }
                if (Schema::hasColumn('konseling', 'tipe_jadwal')) {
                    $table->dropColumn('tipe_jadwal');
                }
            });
        }
        Schema::dropIfExists('jadwal_rutin');
    }
};
