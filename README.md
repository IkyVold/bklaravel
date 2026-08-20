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
php artisan db:seed          # akun demo
# ATAU import dump lama:
# mysql -u root -p bk_system < database/bk_system_dump.sql

php artisan storage:link
php artisan serve
```

**Sanctum** sudah ditambahkan di `composer.json`. Setelah `composer install` jalankan:

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

Buka: http://127.0.0.1:8000

## Akun demo (setelah db:seed)

| Role   | Login        | Password  |
|--------|--------------|-----------|
| Admin  | admin        | admin123  |
| Guru   | guru_bk      | guru123   |
| Kepsek | kepsek       | kepsek123 |
| Siswa  | NIS: 1234    | 1234      |

## API Auth

```
POST /api/login
{ "role": "siswa", "nis": "1234", "password": "1234" }
→ { "token": "...", "role": "siswa", "user": {...} }

Header: Authorization: Bearer <token>
```

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
