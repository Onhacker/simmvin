-- Buat database lokasi MVIN secara mandiri.
-- Jalankan dari root proyek dengan:
--   mysql -u root -p < database/install_wilayah.sql

CREATE DATABASE IF NOT EXISTS simp_wilayah
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simp_wilayah;
SOURCE database/wilayah_schema.sql;
