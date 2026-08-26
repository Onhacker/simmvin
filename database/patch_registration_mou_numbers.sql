-- Nomor MOU permanen untuk registrasi MVIN.
-- Jalankan sekali pada database transaksi yang sudah berisi data, misalnya:
--   mysql -u root -p simp < database/patch_registration_mou_numbers.sql
--
-- Patch ini aman dijalankan ulang. Baris lama yang belum memiliki nomor diberi
-- nomor satu kali dengan urutan event (terbaru), kecamatan, desa, lalu id.
-- Setelah nomor tersimpan, urutan ekspor tidak pernah menulis ulang nomor itu.
-- Kode kabupaten pada instalasi lama diambil dari regency_id sebagai fallback;
-- registrasi baru selalu memakai data_kota.kode_kota dari database wilayah.

SET @mvin_schema := DATABASE();

CREATE TABLE IF NOT EXISTS registration_mou_counters (
  regency_code VARCHAR(30) NOT NULL,
  mou_year SMALLINT UNSIGNED NOT NULL,
  last_sequence INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (regency_code, mou_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND COLUMN_NAME='mou_no'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD COLUMN mou_no VARCHAR(120) NULL AFTER village_name',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND COLUMN_NAME='mou_sequence'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD COLUMN mou_sequence INT UNSIGNED NULL AFTER mou_no',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND COLUMN_NAME='mou_regency_code'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD COLUMN mou_regency_code VARCHAR(30) NULL AFTER mou_sequence',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND COLUMN_NAME='mou_year'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD COLUMN mou_year SMALLINT UNSIGNED NULL AFTER mou_regency_code',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

-- Normalise metadata for an already populated MOU value. This is useful when
-- a previous deployment wrote only the formatted string manually.
UPDATE registrations
SET mou_sequence = CASE
      WHEN mou_sequence IS NULL AND mou_no REGEXP '^[0-9]+[.]RAB/'
        THEN CAST(SUBSTRING_INDEX(mou_no, '.RAB/', 1) AS UNSIGNED)
      ELSE mou_sequence END,
    mou_regency_code = CASE
      WHEN (mou_regency_code IS NULL OR TRIM(mou_regency_code)='')
           AND mou_no LIKE '%/SPK/%'
        THEN SUBSTRING_INDEX(SUBSTRING_INDEX(mou_no, '/SPK/', 1), '/', -1)
      ELSE mou_regency_code END,
    mou_year = CASE
      WHEN mou_year IS NULL AND mou_no REGEXP '/[0-9]{4}$'
        THEN CAST(SUBSTRING_INDEX(mou_no, '/', -1) AS UNSIGNED)
      ELSE mou_year END
WHERE mou_no IS NOT NULL AND TRIM(mou_no) <> '';

-- Build a stable sequence for rows that have no MOU yet. The code fallback is
-- intentionally based on the immutable registration snapshot because this SQL
-- runs against the transaction DB only; new rows use the official regional
-- catalog code in Registration_model.
DROP TEMPORARY TABLE IF EXISTS mvin_registration_mou_backfill;
CREATE TEMPORARY TABLE mvin_registration_mou_backfill (
  id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  regency_code VARCHAR(30) NOT NULL,
  mou_year SMALLINT UNSIGNED NOT NULL,
  mou_month TINYINT UNSIGNED NOT NULL,
  sequence_no INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

INSERT INTO mvin_registration_mou_backfill (id, regency_code, mou_year, mou_month, sequence_no)
SELECT numbered.id,
       numbered.regency_code,
       numbered.mou_year,
       numbered.mou_month,
       CAST(numbered.row_no + COALESCE(existing.max_sequence, 0) AS UNSIGNED) AS sequence_no
FROM (
  SELECT base.id,
         base.regency_code,
         base.mou_year,
         base.mou_month,
         ROW_NUMBER() OVER (
           PARTITION BY base.regency_code, base.mou_year
           ORDER BY base.event_start DESC, base.event_id DESC,
                    base.district_name ASC, base.village_name ASC, base.id ASC
         ) AS row_no
  FROM (
    SELECT r.id,
           r.event_id,
           r.district_name,
           r.village_name,
           COALESCE(NULLIF(TRIM(r.regency_id), ''), 'KAB') AS regency_code,
           YEAR(COALESCE(e.start_date, DATE(r.created_at), CURRENT_DATE)) AS mou_year,
           MONTH(COALESCE(e.start_date, DATE(r.created_at), CURRENT_DATE)) AS mou_month,
           COALESCE(e.start_date, DATE(r.created_at), CURRENT_DATE) AS event_start
    FROM registrations r
    LEFT JOIN training_events e ON e.id=r.event_id
    WHERE r.mou_no IS NULL OR TRIM(r.mou_no)=''
  ) AS base
) AS numbered
LEFT JOIN (
  SELECT mou_regency_code, mou_year, MAX(mou_sequence) AS max_sequence
  FROM registrations
  WHERE mou_no IS NOT NULL AND TRIM(mou_no) <> ''
    AND mou_regency_code IS NOT NULL AND TRIM(mou_regency_code) <> ''
    AND mou_sequence IS NOT NULL
  GROUP BY mou_regency_code, mou_year
) AS existing
  ON existing.mou_regency_code=numbered.regency_code
 AND existing.mou_year=numbered.mou_year;

UPDATE registrations r
JOIN mvin_registration_mou_backfill b ON b.id=r.id
SET r.mou_no = CONCAT(
      LPAD(CAST(b.sequence_no AS CHAR), 3, '0'), '.RAB/', b.regency_code,
      '/SPK/',
      CASE b.mou_month
        WHEN 1 THEN 'I' WHEN 2 THEN 'II' WHEN 3 THEN 'III'
        WHEN 4 THEN 'IV' WHEN 5 THEN 'V' WHEN 6 THEN 'VI'
        WHEN 7 THEN 'VII' WHEN 8 THEN 'VIII' WHEN 9 THEN 'IX'
        WHEN 10 THEN 'X' WHEN 11 THEN 'XI' WHEN 12 THEN 'XII'
        ELSE 'I'
      END,
      '/', CAST(b.mou_year AS CHAR)
    ),
    r.mou_sequence=b.sequence_no,
    r.mou_regency_code=b.regency_code,
    r.mou_year=b.mou_year
WHERE r.mou_no IS NULL OR TRIM(r.mou_no)='';

-- Existing non-null rows may have been imported with metadata only partly
-- populated. Use their stored sequence to initialise the allocator and avoid
-- a duplicate on the next registration.
INSERT INTO registration_mou_counters (regency_code, mou_year, last_sequence)
SELECT mou_regency_code, mou_year, MAX(mou_sequence)
FROM registrations
WHERE mou_no IS NOT NULL AND TRIM(mou_no) <> ''
  AND mou_regency_code IS NOT NULL AND TRIM(mou_regency_code) <> ''
  AND mou_year IS NOT NULL AND mou_sequence IS NOT NULL
GROUP BY mou_regency_code, mou_year
ON DUPLICATE KEY UPDATE last_sequence=GREATEST(last_sequence, VALUES(last_sequence));

DROP TEMPORARY TABLE IF EXISTS mvin_registration_mou_backfill;

-- Add indexes only once. The unique key is the final guard against accidental
-- reuse of a persisted number.
SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND INDEX_NAME='uq_registration_mou_no'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD UNIQUE KEY uq_registration_mou_no (mou_no)',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND INDEX_NAME='uq_registration_mou_sequence'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD UNIQUE KEY uq_registration_mou_sequence (mou_regency_code,mou_year,mou_sequence)',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND INDEX_NAME='idx_registrations_mou_counter'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD KEY idx_registrations_mou_counter (mou_regency_code,mou_year,mou_sequence)',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

-- Make the invariant strict once every legacy row has a value. If a row is
-- still incomplete, leave the columns nullable and let the application report
-- the migration problem rather than silently inventing a number.
SET @mvin_missing := (
  SELECT COUNT(*) FROM registrations
  WHERE mou_no IS NULL OR TRIM(mou_no)='' OR mou_sequence IS NULL
     OR mou_regency_code IS NULL OR TRIM(mou_regency_code)='' OR mou_year IS NULL
);
SET @mvin_ddl := IF(@mvin_missing=0,
  'ALTER TABLE registrations MODIFY mou_no VARCHAR(120) NOT NULL, MODIFY mou_sequence INT UNSIGNED NOT NULL, MODIFY mou_regency_code VARCHAR(30) NOT NULL, MODIFY mou_year SMALLINT UNSIGNED NOT NULL',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_schema := NULL;
SET @mvin_ddl := NULL;
SET @mvin_exists := NULL;
SET @mvin_missing := NULL;
