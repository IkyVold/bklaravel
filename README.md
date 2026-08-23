# BK System — Full Laravel (Blade) — Security Hardened

**Kompatibel PHP 8.2+** (Laravel 11).

Aplikasi Bimbingan Konseling sekolah **tanpa React** — pure Laravel + Blade + API Sanctum.

## Perbaikan Keamanan (revisi dosen)

1. **Autentikasi penuh**
   - Web: Session + middleware `role`
   - API: Laravel Sanctum (Bearer token) + abilities per role
   - Password: bcrypt (auto-upgrade dari MD5 legacy saat login)

2. **Otorisasi ketat**
   - Profile hanya milik sendiri (atau staff)
   - `/api/konseling-all` hanya Kepsek/Admin
   - Detail/update konseling dicek ownership (siswa/guru)
   - Informasi BK write hanya Guru/Admin
   - Notifikasi scoped ke penerima

3. **Chat**
   - `session_id` = UUID (`chat_session_id` di tabel konseling), bukan prediksi NIS+nama+tanggal
   - Identitas pengirim diambil dari token, bukan dari client
   - AI: rate limit 20/menit, semua message dipaksa role `user` (anti prompt injection)

4. **Upload foto**
   - Validasi MIME + magic, filename UUID, mimes terbatas

5. **Business logic**
   - Transisi status konseling divalidasi (state machine)
   - Pembatalan menyimpan `alasan_batal` + notifikasi
   - Cek konflik jadwal (double booking)
   - Tidak ada hard DELETE untuk “batalkan”

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate

# Set DB di .env (MySQL):
# DB_DATABASE=bk_system
# DB_USERNAME=root
# DB_PASSWORD=

php artisan migrate
php artisan db:seed          # lihat catatan di bagian "Akun awal" di bawah

php artisan storage:link
php artisan serve
```

**Sanctum** sudah ditambahkan di `composer.json`. Setelah `composer install` jalankan:

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

Buka: http://127.0.0.1:8000

## Menjalankan test

Test otomatis (PHPUnit/Feature test) memakai SQLite in-memory lewat
`phpunit.xml` — tidak menyentuh database MySQL di `.env`, jadi aman
dijalankan kapan saja tanpa setup tambahan:

```bash
php artisan test
# atau
vendor/bin/phpunit
```

Cakupan test saat ini fokus ke poin-poin revisi keamanan/logika (lihat
`tests/Feature/`):

- **Api/AdminEndpointAuthorizationTest** — token siswa tidak bisa mencapai
  endpoint admin (`/api/akun/*`, `/api/siswa` create, `/api/riwayat-kelas`
  delete)
- **Api/KonselingAuthorizationTest** — siswa tidak bisa konfirmasi/laporan
  konsultasinya sendiri; Guru A tidak bisa kelola konsultasi Guru B; Kepsek
  hanya bisa lihat, bukan kelola
- **Api/ChatOwnershipTest** — `konseling_id` wajib, tidak ada fallback
  `session_id` arbitrary, siswa tidak bisa kirim pesan ke sesi orang lain
- **Api/ScheduleConflictTest** — pengajuan konseling ditolak kalau bentrok
  jadwal dengan Guru BK yang sama
- **Web/KonselingWebOwnershipTest** — Guru A tidak bisa lihat detail
  konsultasi Guru B lewat web; route hard-delete sudah tidak ada; batal
  siswa memakai soft-cancel (baris tetap ada untuk audit)
- **Web/JadwalRutinOverlapTest** — slot jadwal rutin yang overlap atau
  `jam_selesai <= jam_mulai` ditolak
- **Auth/LoginLockoutTest** — akun yang terkunci lewat percobaan gagal di
  API juga ikut ditolak saat dicoba lewat web

Test ini belum mencakup semua modul (chat real-time, import Excel,
notifikasi, cetak laporan) — tambahkan test baru di folder yang sama
seiring modul lain diverifikasi.

## Akun awal

`BkSeeder` sengaja **tidak membuat akun apa pun secara otomatis** (lihat isi
`database/seeders/BkSeeder.php`) — ini untuk mencegah kredensial default yang
dapat ditebak ikut ter-deploy ke lingkungan produksi.

Untuk membuat akun pertama (misalnya Admin), buat secara manual lewat
`php artisan tinker` dan **tentukan sendiri password yang kuat**, contoh:

```bash
php artisan tinker
```
```php
\App\Models\Admin::create([
    'username' => 'admin',
    'password' => bcrypt('GANTI_DENGAN_PASSWORD_KUAT_ANDA'),
    'nama'     => 'Administrator',
]);
```

Lakukan hal yang sama untuk model `GuruBk`, `Kepsek`, dan `Siswa` sesuai
kebutuhan. **Jangan pernah menuliskan password asli di README, kode, atau
file yang ikut dikirim/di-commit** — password demo yang pernah ada di versi
sebelumnya (`admin123`, `guru123`, `kepsek123`) sudah dihapus dari
dokumentasi ini dan sebaiknya tidak dipakai lagi jika sempat diterapkan di
database mana pun.

## API Auth

```
POST /api/login
{ "role": "siswa", "nis": "<nis_siswa>", "password": "<password_siswa>" }
→ { "token": "...", "role": "siswa", "user": {...} }

Header: Authorization: Bearer <token>
```

(Nilai `<nis_siswa>` dan `<password_siswa>` di atas hanya placeholder format
request — bukan kredensial yang benar-benar aktif.)

## Struktur penting

```
app/Http/Controllers/Web/   # Auth, Dashboard, Konseling, ...
app/Http/Controllers/Api/   # Sanctum-protected API
app/Http/Controllers/Concerns/AuthorizesBk.php
app/Models/                 # Siswa, GuruBk, Konseling (+ HasApiTokens)
resources/views/            # Blade templates
routes/web.php              # Route web + role middleware
routes/api.php              # Route API + auth:sanctum
```
