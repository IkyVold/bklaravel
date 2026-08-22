<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('siswa')) {
            Schema::create('siswa', function (Blueprint $table) {
                $table->id();
                $table->string('nis', 10)->unique();
                $table->string('nama', 100);
                $table->string('kelas', 20);
                $table->string('password', 255);
                $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->nullable();
                $table->date('tanggal_lahir')->nullable();
                $table->text('alamat')->nullable();
                $table->string('no_telepon', 15)->nullable();
                $table->string('foto_profile', 255)->nullable();
                $table->unsignedInteger('failed_login_attempts')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('siswa', function (Blueprint $table) {
                if (!Schema::hasColumn('siswa', 'failed_login_attempts')) {
                    $table->unsignedInteger('failed_login_attempts')->default(0);
                }
                if (!Schema::hasColumn('siswa', 'locked_until')) {
                    $table->timestamp('locked_until')->nullable();
                }
            });
        }

        if (!Schema::hasTable('guru_bk')) {
            Schema::create('guru_bk', function (Blueprint $table) {
                $table->id();
                $table->string('username', 50)->unique();
                $table->string('password', 255);
                $table->string('nama', 100);
                $table->string('spesialisasi', 100)->default('Guru BK');
                $table->string('npsn', 30)->nullable();
                $table->string('alamat', 150)->nullable();
                $table->string('avatar', 10)->nullable();
                $table->string('foto_profile', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('kepsek')) {
            Schema::create('kepsek', function (Blueprint $table) {
                $table->id();
                $table->string('username', 50)->unique();
                $table->string('password', 255);
                $table->string('nama', 100);
                $table->string('npsn', 30)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admin')) {
            Schema::create('admin', function (Blueprint $table) {
                $table->id();
                $table->string('username', 50)->unique();
                $table->string('password', 255);
                $table->string('nama', 100)->default('Administrator');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('riwayat_kelas')) {
            Schema::create('riwayat_kelas', function (Blueprint $table) {
                $table->id();
                $table->string('nis', 20);
                $table->string('tahun_ajaran', 9);
                $table->string('kelas', 20);
                $table->enum('status', ['aktif', 'arsip'])->default('aktif');
                $table->timestamps();
                $table->unique(['nis', 'tahun_ajaran'], 'unique_nis_tahun');
            });
        }

        if (!Schema::hasTable('informasi_bk')) {
            Schema::create('informasi_bk', function (Blueprint $table) {
                $table->id();
                $table->string('judul', 150);
                $table->string('kategori', 50);
                $table->text('isi');
                $table->string('guru_bk', 100);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('konseling')) {
            Schema::create('konseling', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('siswa_id');
                $table->string('guru_bk', 100)->nullable();
                $table->date('tanggal')->nullable();
                $table->time('jam')->nullable();
                $table->string('jenis', 20)->nullable();
                $table->string('kategori', 50)->nullable();
                $table->text('deskripsi')->nullable();
                $table->string('kelas_siswa', 20)->nullable();
                $table->string('status', 20)->default('Menunggu');
                $table->string('status_konfirmasi', 30)->default('Belum Dikonfirmasi');
                $table->date('tanggal_konfirmasi')->nullable();
                $table->time('jam_konfirmasi')->nullable();
                $table->text('laporan')->nullable();
                $table->date('laporan_tanggal')->nullable();
                $table->time('laporan_waktu')->nullable();
                $table->string('laporan_dibuat_oleh', 100)->nullable();
                $table->text('laporan_kesimpulan')->nullable();
                $table->text('laporan_rekomendasi')->nullable();
                $table->string('laporan_status_penanganan', 50)->nullable();
                $table->text('laporan_catatan_tambahan')->nullable();
                $table->timestamp('laporan_created_at')->nullable();
                $table->boolean('input_manual')->default(false);
                $table->text('catatan_walkin')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('siswa_id');
            });
        }

        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 150);
                $table->string('sender_id', 50);
                $table->string('sender_name', 100)->nullable();
                $table->enum('sender_type', ['siswa', 'guru']);
                $table->text('message');
                $table->timestamp('created_at')->useCurrent();
                $table->index('session_id');
            });
        }

        if (!Schema::hasTable('notifikasi')) {
            Schema::create('notifikasi', function (Blueprint $table) {
                $table->id();
                $table->string('penerima_id', 50);
                $table->string('penerima_role', 20);
                $table->string('judul', 150);
                $table->text('pesan')->nullable();
                $table->string('tipe', 50)->nullable();
                $table->json('data')->nullable();
                $table->boolean('dibaca')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->index(['penerima_id', 'penerima_role']);
            });
        }

        if (!Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('user_id', 50);
                $table->string('role', 20);
                $table->text('endpoint');
                $table->string('p256dh', 255)->nullable();
                $table->string('auth', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('master_pelanggaran')) {
            Schema::create('master_pelanggaran', function (Blueprint $table) {
                $table->id();
                $table->enum('kategori', ['Ringan', 'Sedang', 'Berat']);
                $table->string('jenis_pelanggaran', 100);
                $table->integer('poin');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('catatan_pelanggaran')) {
            Schema::create('catatan_pelanggaran', function (Blueprint $table) {
                $table->id();
                $table->string('siswa_nis', 10);
                $table->string('siswa_nama', 100);
                $table->string('siswa_kelas', 20);
                $table->date('tanggal');
                $table->unsignedBigInteger('pelanggaran_id');
                $table->integer('poin');
                $table->text('keterangan')->nullable();
                $table->string('dicatat_oleh', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('rekap_pelanggaran_siswa')) {
            Schema::create('rekap_pelanggaran_siswa', function (Blueprint $table) {
                $table->id();
                $table->string('siswa_nis', 10)->unique();
                $table->string('siswa_nama', 100);
                $table->string('siswa_kelas', 20);
                $table->integer('total_poin')->default(0);
                $table->integer('total_pelanggaran')->default(0);
                $table->enum('level_pelanggaran', [
                    'Teguran', 'Pembinaan', 'Peringatan', 'Panggilan Orang Tua', 'Skorsing'
                ])->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // aman: tidak drop tabel agar data tidak hilang
    }
};
