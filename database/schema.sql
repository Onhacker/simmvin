-- MVIN - Sistem Informasi Manajemen Pelatihan
-- Database transaksi MVIN; master lokasi berada di database simp_wilayah.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module VARCHAR(80) NOT NULL,
  code VARCHAR(120) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(160) NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
  user_id BIGINT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  allowed TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, permission_id),
  CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_failures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identity_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_failures_identity (identity_hash, attempted_at),
  KEY idx_login_failures_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fund_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('cash','bank','qris','personal') NOT NULL,
  bank_name VARCHAR(100) NULL,
  account_number VARCHAR(80) NULL,
  account_holder VARCHAR(120) NULL,
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  include_in_total TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_fund_accounts_active (is_active, sort_order),
  CONSTRAINT fk_fund_accounts_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_fund_accounts_opening_balance CHECK (opening_balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  billing_mode ENUM('per_village','per_participant','per_village_extra') NOT NULL,
  village_fee DECIMAL(18,2) NOT NULL DEFAULT 0,
  participant_fee DECIMAL(18,2) NOT NULL DEFAULT 0,
  included_participant_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  location VARCHAR(180) NOT NULL,
  address TEXT NULL,
  notes TEXT NULL,
  status ENUM('draft','open','closed','cancelled') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_training_events_date (start_date, end_date),
  KEY idx_training_events_status (status),
  CONSTRAINT fk_training_events_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_training_events_village_fee CHECK (village_fee >= 0),
  CONSTRAINT chk_training_events_participant_fee CHECK (participant_fee >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_regencies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  province_id VARCHAR(30) NOT NULL,
  province_name VARCHAR(120) NOT NULL,
  regency_id VARCHAR(30) NOT NULL,
  regency_name VARCHAR(150) NOT NULL,
  UNIQUE KEY uq_event_regency (event_id, regency_id),
  KEY idx_event_regencies_regency (regency_id),
  CONSTRAINT fk_event_regencies_event FOREIGN KEY (event_id) REFERENCES training_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Counter MOU dipisahkan dari registrasi agar penomoran tetap atomik ketika
-- beberapa event/koneksi menambahkan desa pada kabupaten dan tahun yang sama.
-- Nomor yang sudah diberikan tidak pernah dihitung ulang dari urutan ekspor.
CREATE TABLE IF NOT EXISTS registration_mou_counters (
  regency_code VARCHAR(30) NOT NULL,
  mou_year SMALLINT UNSIGNED NOT NULL,
  last_sequence INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (regency_code, mou_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  province_id VARCHAR(30) NOT NULL,
  province_name VARCHAR(120) NOT NULL,
  regency_id VARCHAR(30) NOT NULL,
  regency_name VARCHAR(150) NOT NULL,
  district_id VARCHAR(30) NOT NULL,
  district_name VARCHAR(180) NOT NULL,
  village_id VARCHAR(30) NOT NULL,
  village_name VARCHAR(180) NOT NULL,
  mou_no VARCHAR(120) NOT NULL,
  mou_sequence INT UNSIGNED NOT NULL,
  mou_regency_code VARCHAR(30) NOT NULL,
  mou_year SMALLINT UNSIGNED NOT NULL,
  expected_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  cancellation_reason VARCHAR(500) NULL,
  cancelled_by BIGINT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_registration_event_village (event_id, village_id),
  UNIQUE KEY uq_registration_mou_no (mou_no),
  UNIQUE KEY uq_registration_mou_sequence (mou_regency_code, mou_year, mou_sequence),
  KEY idx_registrations_region (province_id, regency_id, district_id, village_id),
  KEY idx_registrations_mou_counter (mou_regency_code, mou_year, mou_sequence),
  CONSTRAINT fk_registrations_event FOREIGN KEY (event_id) REFERENCES training_events(id),
  CONSTRAINT fk_registrations_canceller FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_registrations_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_registrations_expected_amount CHECK (expected_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS village_positions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL UNIQUE,
  category VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_village_positions_active (is_active, category, sort_order, name),
  CONSTRAINT fk_village_positions_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  position_id INT UNSIGNED NULL,
  position VARCHAR(120) NULL,
  phone VARCHAR(30) NULL,
  expected_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  deactivation_reason VARCHAR(500) NULL,
  deactivated_by BIGINT UNSIGNED NULL,
  replacement_for_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_participants_registration (registration_id, is_active),
  KEY idx_participants_position (position_id),
  CONSTRAINT fk_participants_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_participants_position FOREIGN KEY (position_id) REFERENCES village_positions(id) ON DELETE SET NULL,
  CONSTRAINT fk_participants_deactivator FOREIGN KEY (deactivated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_participants_replacement FOREIGN KEY (replacement_for_id) REFERENCES participants(id) ON DELETE SET NULL,
  CONSTRAINT fk_participants_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_participants_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_participants_expected_amount CHECK (expected_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(50) NOT NULL UNIQUE,
  event_id BIGINT UNSIGNED NOT NULL,
  registration_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  payment_date DATE NOT NULL,
  method ENUM('cash','transfer','qris') NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  proof_path VARCHAR(255) NULL,
  note TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  rejected_by BIGINT UNSIGNED NULL,
  rejected_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_payments_report (event_id, payment_date, status, method),
  KEY idx_payments_registration (registration_id, participant_id),
  UNIQUE KEY uq_payments_proof_path (proof_path),
  CONSTRAINT fk_payments_event FOREIGN KEY (event_id) REFERENCES training_events(id),
  CONSTRAINT fk_payments_registration FOREIGN KEY (registration_id) REFERENCES registrations(id),
  CONSTRAINT fk_payments_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_account FOREIGN KEY (account_id) REFERENCES fund_accounts(id),
  CONSTRAINT fk_payments_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_rejector FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_payments_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS expense_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(60) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hutang perusahaan dicatat sebagai kewajiban. Pencatatan hutang tidak
-- membuat jurnal kas; jurnal baru dibuat ketika pembayaran diverifikasi.
CREATE TABLE IF NOT EXISTS company_debts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  debt_no VARCHAR(50) NOT NULL UNIQUE,
  event_id BIGINT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  creditor VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  debt_date DATE NOT NULL,
  due_date DATE NULL,
  principal_amount DECIMAL(18,2) NOT NULL,
  status ENUM('open','paid','cancelled') NOT NULL DEFAULT 'open',
  note TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  settled_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_company_debts_status_due (status, due_date),
  KEY idx_company_debts_event (event_id),
  CONSTRAINT fk_company_debts_event FOREIGN KEY (event_id) REFERENCES training_events(id) ON DELETE SET NULL,
  CONSTRAINT fk_company_debts_category FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_company_debts_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_company_debts_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_company_debts_principal CHECK (principal_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_no VARCHAR(50) NOT NULL UNIQUE,
  event_id BIGINT UNSIGNED NULL,
  debt_id BIGINT UNSIGNED NULL,
  category_id INT UNSIGNED NOT NULL,
  expense_date DATE NOT NULL,
  payee VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  method ENUM('cash','transfer','qris') NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  admin_fee DECIMAL(18,2) NOT NULL DEFAULT 0,
  proof_path VARCHAR(255) NULL,
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  note TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_expenses_report (event_id, expense_date, status, method),
  KEY idx_expenses_debt (debt_id, status, expense_date),
  UNIQUE KEY uq_expenses_proof_path (proof_path),
  CONSTRAINT fk_expenses_event FOREIGN KEY (event_id) REFERENCES training_events(id) ON DELETE SET NULL,
  CONSTRAINT fk_expenses_debt FOREIGN KEY (debt_id) REFERENCES company_debts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
  CONSTRAINT fk_expenses_account FOREIGN KEY (account_id) REFERENCES fund_accounts(id),
  CONSTRAINT fk_expenses_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_expenses_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_expenses_amount CHECK (amount > 0),
  CONSTRAINT chk_expenses_fee CHECK (admin_fee >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fund_transfers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_no VARCHAR(50) NOT NULL UNIQUE,
  transfer_date DATE NOT NULL,
  from_account_id BIGINT UNSIGNED NOT NULL,
  to_account_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  admin_fee DECIMAL(18,2) NOT NULL DEFAULT 0,
  proof_path VARCHAR(255) NULL,
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  note TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_fund_transfers_date (transfer_date, status),
  UNIQUE KEY uq_fund_transfers_proof_path (proof_path),
  CONSTRAINT fk_fund_transfers_from FOREIGN KEY (from_account_id) REFERENCES fund_accounts(id),
  CONSTRAINT fk_fund_transfers_to FOREIGN KEY (to_account_id) REFERENCES fund_accounts(id),
  CONSTRAINT fk_fund_transfers_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_fund_transfers_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_transfer_accounts CHECK (from_account_id <> to_account_id),
  CONSTRAINT chk_transfer_amount CHECK (amount > 0),
  CONSTRAINT chk_transfer_fee CHECK (admin_fee >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ledger_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL,
  entry_date DATE NOT NULL,
  direction ENUM('in','out') NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  source_type ENUM('payment','expense','transfer','adjustment') NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ledger_source (account_id, source_type, source_id, direction),
  KEY idx_ledger_balance (account_id, entry_date),
  CONSTRAINT fk_ledger_account FOREIGN KEY (account_id) REFERENCES fund_accounts(id),
  CONSTRAINT fk_ledger_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_ledger_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id VARCHAR(80) NULL,
  details_json LONGTEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
