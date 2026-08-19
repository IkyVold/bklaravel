-- Jalankan jika tidak pakai php artisan migrate
CREATE TABLE IF NOT EXISTS jadwal_rutin (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  guru_id BIGINT UNSIGNED NOT NULL,
  hari TINYINT NOT NULL COMMENT '1=Senin ... 7=Minggu',
  jam_mulai TIME NOT NULL,
  jam_selesai TIME NULL,
  jenis VARCHAR(20) NOT NULL DEFAULT 'Luring',
  keterangan VARCHAR(150) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX (guru_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kolom di tabel konseling (abaikan error jika sudah ada)
ALTER TABLE konseling
  ADD COLUMN tipe_jadwal VARCHAR(20) NOT NULL DEFAULT 'Nonrutin' AFTER jenis,
  ADD COLUMN jadwal_rutin_id BIGINT UNSIGNED NULL AFTER tipe_jadwal;
