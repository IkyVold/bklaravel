<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            if (!Schema::hasColumn('konseling', 'durasi_menit')) {
                $table->unsignedSmallInteger('durasi_menit')->nullable()->after('jam');
            }
        });
    }

    public function down(): void
    {
        Schema::table('konseling', function (Blueprint $table) {
            $table->dropColumn('durasi_menit');
        });
    }
};
