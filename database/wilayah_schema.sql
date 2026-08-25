-- MVIN - Master lokasi mandiri
-- Database ini sengaja tidak memiliki tabel transaksi RAB.
-- Pertahankan nilai ID dari sumber lama agar snapshot event/registrasi tetap
-- dapat dicocokkan saat migrasi. Tabel tidak memakai foreign key supaya impor
-- tetap dapat menyimpan baris lama yang belum lengkap; endpoint aplikasi hanya
-- menampilkan relasi yang valid melalui JOIN.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS data_provinsi (
  id VARCHAR(30) NOT NULL,
  provinsi VARCHAR(120) NOT NULL,
  kode_prov VARCHAR(20) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_data_provinsi_name (provinsi),
  KEY idx_data_provinsi_code (kode_prov)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_kota (
  id VARCHAR(30) NOT NULL,
  kota VARCHAR(120) NOT NULL,
  kode_kota VARCHAR(20) NULL,
  id_provinsi VARCHAR(30) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_data_kota_parent_name (kota, id_provinsi),
  KEY idx_data_kota_provinsi (id_provinsi),
  KEY idx_data_kota_code (kode_kota)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_kecamatan (
  id VARCHAR(30) NOT NULL,
  kecamatan VARCHAR(300) NOT NULL DEFAULT '',
  kode_kecamatan VARCHAR(20) NULL,
  id_kota VARCHAR(30) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_data_kecamatan_kota (id_kota),
  KEY idx_data_kecamatan_code (kode_kecamatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_desa (
  id VARCHAR(30) NOT NULL,
  desa VARCHAR(180) NOT NULL,
  kode_desa VARCHAR(20) NULL,
  id_kecamatan VARCHAR(30) NOT NULL,
  kelompok INT NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_data_desa_kecamatan (id_kecamatan),
  KEY idx_data_desa_code (kode_desa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
