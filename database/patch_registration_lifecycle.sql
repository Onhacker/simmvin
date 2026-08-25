-- Arsip registrasi dan siklus koreksi peserta untuk instalasi MVIN berjalan.
-- Jalankan terhadap database transaksi MVIN. Seluruh langkah aman diulang.

SET @mvin_schema := DATABASE();

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND COLUMN_NAME='cancellation_reason'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD COLUMN cancellation_reason VARCHAR(500) NULL AFTER status',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND COLUMN_NAME='cancelled_by'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER cancellation_reason',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND COLUMN_NAME='cancelled_at'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD COLUMN cancelled_at DATETIME NULL AFTER cancelled_by',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@mvin_schema AND TABLE_NAME='registrations' AND CONSTRAINT_NAME='fk_registrations_canceller'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE registrations ADD CONSTRAINT fk_registrations_canceller FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='participants' AND COLUMN_NAME='deactivation_reason'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE participants ADD COLUMN deactivation_reason VARCHAR(500) NULL AFTER is_active',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='participants' AND COLUMN_NAME='deactivated_by'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE participants ADD COLUMN deactivated_by BIGINT UNSIGNED NULL AFTER deactivation_reason',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='participants' AND COLUMN_NAME='replacement_for_id'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE participants ADD COLUMN replacement_for_id BIGINT UNSIGNED NULL AFTER deactivated_by',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='participants' AND COLUMN_NAME='created_by'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE participants ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER replacement_for_id',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='participants' AND COLUMN_NAME='updated_by'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE participants ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER created_by',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@mvin_schema AND TABLE_NAME='participants' AND CONSTRAINT_NAME='fk_participants_deactivator'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE participants ADD CONSTRAINT fk_participants_deactivator FOREIGN KEY (deactivated_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@mvin_schema AND TABLE_NAME='participants' AND CONSTRAINT_NAME='fk_participants_replacement'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE participants ADD CONSTRAINT fk_participants_replacement FOREIGN KEY (replacement_for_id) REFERENCES participants(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@mvin_schema AND TABLE_NAME='participants' AND CONSTRAINT_NAME='fk_participants_creator'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE participants ADD CONSTRAINT fk_participants_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@mvin_schema AND TABLE_NAME='participants' AND CONSTRAINT_NAME='fk_participants_updater'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE participants ADD CONSTRAINT fk_participants_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='payments' AND COLUMN_NAME='rejected_by'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE payments ADD COLUMN rejected_by BIGINT UNSIGNED NULL AFTER verified_at',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='payments' AND COLUMN_NAME='rejected_at'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE payments ADD COLUMN rejected_at DATETIME NULL AFTER rejected_by',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=@mvin_schema AND TABLE_NAME='payments' AND CONSTRAINT_NAME='fk_payments_rejector'
);
SET @mvin_ddl := IF(@mvin_exists=0,
  'ALTER TABLE payments ADD CONSTRAINT fk_payments_rejector FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

CREATE TABLE IF NOT EXISTS participant_revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id BIGINT UNSIGNED NOT NULL,
  registration_id BIGINT UNSIGNED NOT NULL,
  action ENUM('update','replace','deactivate','registration_cancel','registration_restore') NOT NULL,
  before_json LONGTEXT NOT NULL,
  after_json LONGTEXT NULL,
  reason VARCHAR(500) NULL,
  actor_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(120) NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_participant_revisions_participant (participant_id, occurred_at),
  KEY idx_participant_revisions_registration (registration_id, occurred_at),
  CONSTRAINT fk_participant_revisions_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  CONSTRAINT fk_participant_revisions_registration FOREIGN KEY (registration_id) REFERENCES registrations(id),
  CONSTRAINT fk_participant_revisions_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @mvin_action_type := (
  SELECT COLUMN_TYPE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@mvin_schema AND TABLE_NAME='participant_revisions' AND COLUMN_NAME='action'
  LIMIT 1
);
SET @mvin_ddl := IF(LOCATE('registration_restore',COALESCE(@mvin_action_type,''))=0,
  'ALTER TABLE participant_revisions MODIFY COLUMN action ENUM(''update'',''replace'',''deactivate'',''registration_cancel'',''registration_restore'') NOT NULL',
  'SELECT 1');
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

CREATE TABLE IF NOT EXISTS payment_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  from_status ENUM('pending','verified','rejected') NULL,
  to_status ENUM('pending','verified','rejected') NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(120) NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(500) NULL,
  KEY idx_payment_status_history_payment (payment_id, occurred_at),
  CONSTRAINT fk_payment_status_history_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
  CONSTRAINT fk_payment_status_history_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Give pre-migration payments one immutable starting point. The NOT EXISTS
-- guard keeps this safe when the patch is run more than once.
INSERT INTO payment_status_history (
  payment_id, from_status, to_status, actor_id, actor_name, occurred_at, note
)
SELECT
  py.id,
  NULL,
  py.status,
  CASE
    WHEN py.status='rejected' THEN COALESCE(py.rejected_by,py.verified_by,py.created_by)
    WHEN py.status='verified' THEN COALESCE(py.verified_by,py.created_by)
    ELSE py.created_by
  END,
  actor.name,
  CASE
    WHEN py.status='rejected' THEN COALESCE(py.rejected_at,py.verified_at,py.created_at)
    WHEN py.status='verified' THEN COALESCE(py.verified_at,py.created_at)
    ELSE py.created_at
  END,
  'Status awal dimigrasikan dari data pembayaran.'
FROM payments py
LEFT JOIN users actor ON actor.id = CASE
  WHEN py.status='rejected' THEN COALESCE(py.rejected_by,py.verified_by,py.created_by)
  WHEN py.status='verified' THEN COALESCE(py.verified_by,py.created_by)
  ELSE py.created_by
END
WHERE NOT EXISTS (
  SELECT 1 FROM payment_status_history history WHERE history.payment_id=py.id
);

SET @mvin_schema := NULL;
SET @mvin_exists := NULL;
SET @mvin_ddl := NULL;
SET @mvin_action_type := NULL;
