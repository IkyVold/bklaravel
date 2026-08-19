-- Jalankan di phpMyAdmin / MySQL.
-- Jika error "Duplicate column name", lewati baris itu (kolom sudah ada).

ALTER TABLE jadwal_rutin ADD COLUMN jam_selesai TIME NULL AFTER jam_mulai;
ALTER TABLE jadwal_rutin ADD COLUMN jenis VARCHAR(20) NOT NULL DEFAULT 'Luring' AFTER jam_selesai;
ALTER TABLE jadwal_rutin ADD COLUMN keterangan VARCHAR(150) NULL AFTER jenis;
ALTER TABLE jadwal_rutin ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER keterangan;
ALTER TABLE jadwal_rutin ADD COLUMN created_at TIMESTAMP NULL;
ALTER TABLE jadwal_rutin ADD COLUMN updated_at TIMESTAMP NULL;
