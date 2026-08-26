<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('informasi_bk', function (Blueprint $table) {
            if (!Schema::hasColumn('informasi_bk', 'guru_id')) {
                $table->unsignedBigInteger('guru_id')->nullable()->after('guru_bk')->index();
            }
        });

        if (Schema::hasTable('guru_bk')) {
            $duplikatNama = DB::table('guru_bk')
                ->select('nama')
                ->groupBy('nama')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('nama');

            DB::table('informasi_bk')
                ->whereNull('guru_id')
                ->whereNotIn('guru_bk', $duplikatNama)
                ->orderBy('id')
                ->each(function ($row) {
                    $guru = DB::table('guru_bk')->where('nama', $row->guru_bk)->first();
                    if ($guru) {
                        DB::table('informasi_bk')->where('id', $row->id)->update(['guru_id' => $guru->id]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('informasi_bk', function (Blueprint $table) {
            if (Schema::hasColumn('informasi_bk', 'guru_id')) {
                $table->dropColumn('guru_id');
            }
        });
    }
};
