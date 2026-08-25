-- Jalankan pada instalasi MVIN yang sudah ada; aman dijalankan ulang.
-- Untuk instalasi baru, schema.sql sudah memuat perubahan ini.

ALTER TABLE training_events
  MODIFY COLUMN billing_mode ENUM('per_village','per_participant','per_village_extra') NOT NULL;

SET @has_included_participant_count = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='training_events'
    AND COLUMN_NAME='included_participant_count'
);
SET @ddl = IF(
  @has_included_participant_count=0,
  'ALTER TABLE training_events ADD COLUMN included_participant_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER participant_fee',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
