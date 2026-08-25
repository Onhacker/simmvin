-- MVIN security/data-integrity hardening for existing installations.
-- Run against the selected transaction database, for example:
--   mysql simp < database/patch_security_hardening.sql
--
-- The statements are safe to run repeatedly.  New financial records default
-- to pending and require an explicit verification step before journaling.
-- CHECK additions intentionally fail if an existing installation contains
-- invalid negative balances/amounts; correct those rows and rerun rather than
-- silently rewriting financial history.

SET @mvin_schema := DATABASE();

-- New transaction rows must wait for an explicit verification decision.
SET @mvin_has_payments_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'payments'
    AND COLUMN_NAME = 'status'
    AND REPLACE(COALESCE(COLUMN_DEFAULT, ''), '''', '') <> 'pending'
);
SET @mvin_ddl := IF(
  @mvin_has_payments_status = 1,
  'ALTER TABLE payments MODIFY status ENUM(''pending'',''verified'',''rejected'') NOT NULL DEFAULT ''pending''',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_has_expenses_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'expenses'
    AND COLUMN_NAME = 'status'
    AND REPLACE(COALESCE(COLUMN_DEFAULT, ''), '''', '') <> 'pending'
);
SET @mvin_ddl := IF(
  @mvin_has_expenses_status = 1,
  'ALTER TABLE expenses MODIFY status ENUM(''pending'',''verified'',''rejected'') NOT NULL DEFAULT ''pending''',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_has_transfers_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'fund_transfers'
    AND COLUMN_NAME = 'status'
    AND REPLACE(COALESCE(COLUMN_DEFAULT, ''), '''', '') <> 'pending'
);
SET @mvin_ddl := IF(
  @mvin_has_transfers_status = 1,
  'ALTER TABLE fund_transfers MODIFY status ENUM(''pending'',''verified'',''rejected'') NOT NULL DEFAULT ''pending''',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

-- Add each invariant only when it is not already present.
SET @mvin_has_table := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'fund_accounts'
);
SET @mvin_has_check := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @mvin_schema AND TABLE_NAME = 'fund_accounts'
    AND CONSTRAINT_NAME = 'chk_fund_accounts_opening_balance'
);
SET @mvin_ddl := IF(
  @mvin_has_table = 1 AND @mvin_has_check = 0,
  'ALTER TABLE fund_accounts ADD CONSTRAINT chk_fund_accounts_opening_balance CHECK (opening_balance >= 0)',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_has_table := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'training_events'
);
SET @mvin_has_check := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @mvin_schema AND TABLE_NAME = 'training_events'
    AND CONSTRAINT_NAME = 'chk_training_events_village_fee'
);
SET @mvin_ddl := IF(
  @mvin_has_table = 1 AND @mvin_has_check = 0,
  'ALTER TABLE training_events ADD CONSTRAINT chk_training_events_village_fee CHECK (village_fee >= 0)',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_has_table := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'training_events'
);
SET @mvin_has_check := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @mvin_schema AND TABLE_NAME = 'training_events'
    AND CONSTRAINT_NAME = 'chk_training_events_participant_fee'
);
SET @mvin_ddl := IF(
  @mvin_has_table = 1 AND @mvin_has_check = 0,
  'ALTER TABLE training_events ADD CONSTRAINT chk_training_events_participant_fee CHECK (participant_fee >= 0)',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_has_table := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'registrations'
);
SET @mvin_has_check := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @mvin_schema AND TABLE_NAME = 'registrations'
    AND CONSTRAINT_NAME = 'chk_registrations_expected_amount'
);
SET @mvin_ddl := IF(
  @mvin_has_table = 1 AND @mvin_has_check = 0,
  'ALTER TABLE registrations ADD CONSTRAINT chk_registrations_expected_amount CHECK (expected_amount >= 0)',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_has_table := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'participants'
);
SET @mvin_has_check := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @mvin_schema AND TABLE_NAME = 'participants'
    AND CONSTRAINT_NAME = 'chk_participants_expected_amount'
);
SET @mvin_ddl := IF(
  @mvin_has_table = 1 AND @mvin_has_check = 0,
  'ALTER TABLE participants ADD CONSTRAINT chk_participants_expected_amount CHECK (expected_amount >= 0)',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

-- A private upload is evidence for one transaction only. UNIQUE permits
-- multiple NULL rows while preventing one stored file from being attached to
-- two different records, including under concurrent requests.
SET @mvin_has_table := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'payments'
);
SET @mvin_has_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'payments'
    AND INDEX_NAME = 'uq_payments_proof_path'
);
SET @mvin_ddl := IF(
  @mvin_has_table = 1 AND @mvin_has_index = 0,
  'ALTER TABLE payments ADD UNIQUE KEY uq_payments_proof_path (proof_path)',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_has_table := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'expenses'
);
SET @mvin_has_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'expenses'
    AND INDEX_NAME = 'uq_expenses_proof_path'
);
SET @mvin_ddl := IF(
  @mvin_has_table = 1 AND @mvin_has_index = 0,
  'ALTER TABLE expenses ADD UNIQUE KEY uq_expenses_proof_path (proof_path)',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

SET @mvin_has_table := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'fund_transfers'
);
SET @mvin_has_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @mvin_schema AND TABLE_NAME = 'fund_transfers'
    AND INDEX_NAME = 'uq_fund_transfers_proof_path'
);
SET @mvin_ddl := IF(
  @mvin_has_table = 1 AND @mvin_has_index = 0,
  'ALTER TABLE fund_transfers ADD UNIQUE KEY uq_fund_transfers_proof_path (proof_path)',
  'SELECT 1'
);
PREPARE mvin_stmt FROM @mvin_ddl; EXECUTE mvin_stmt; DEALLOCATE PREPARE mvin_stmt;

-- Keep the session in the normal state for callers that source this file.
SET @mvin_schema := NULL;
SET @mvin_ddl := NULL;
SET @mvin_has_index := NULL;
