-- Modul Hutang Perusahaan untuk instalasi MVIN yang sudah berjalan.
-- Jalankan dengan database transaksi MVIN terpilih: mysql simp < database/patch_debts.sql
-- Seluruh langkah dibuat aman untuk dijalankan ulang.

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

SET @has_debt_id = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND COLUMN_NAME='debt_id'
);
SET @ddl = IF(@has_debt_id=0,
  'ALTER TABLE expenses ADD COLUMN debt_id BIGINT UNSIGNED NULL AFTER event_id',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_debt_index = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND INDEX_NAME='idx_expenses_debt'
);
SET @ddl = IF(@has_debt_index=0,
  'ALTER TABLE expenses ADD KEY idx_expenses_debt (debt_id, status, expense_date)',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_debt_fk = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='expenses' AND CONSTRAINT_NAME='fk_expenses_debt'
);
SET @ddl = IF(@has_debt_fk=0,
  'ALTER TABLE expenses ADD CONSTRAINT fk_expenses_debt FOREIGN KEY (debt_id) REFERENCES company_debts(id) ON DELETE RESTRICT',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO expense_categories (name,code,is_active)
VALUES ('Pembayaran Hutang','pembayaran-hutang',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1;

INSERT INTO permissions (module,code,name,description) VALUES
('Hutang','debts.view','Lihat Hutang','Melihat kewajiban dan riwayat pembayaran hutang perusahaan.'),
('Hutang','debts.create','Tambah Hutang','Mencatat hutang perusahaan baru.'),
('Hutang','debts.pay','Bayar Hutang','Mencatat pembayaran hutang melalui kas atau rekening.'),
('Hutang','debts.verify','Verifikasi Pembayaran Hutang','Memverifikasi atau menolak pembayaran hutang.'),
('Hutang','debts.manage','Kelola Hutang','Membatalkan hutang yang belum memiliki pembayaran.')
ON DUPLICATE KEY UPDATE module=VALUES(module),name=VALUES(name),description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code IN
('debts.view','debts.create','debts.pay','debts.verify','debts.manage') WHERE r.slug='super-admin';
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code='debts.view' WHERE r.slug IN ('direktur','auditor');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code IN ('debts.view','debts.create','debts.pay') WHERE r.slug IN ('manajer','anggota');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code IN
('debts.view','debts.create','debts.pay','debts.verify','debts.manage') WHERE r.slug='keuangan';

-- Existing accounts can already have explicit permission overrides. Seed the
-- new module according to their current role so the permission matrix remains
-- predictable after this patch.
INSERT IGNORE INTO user_permissions (user_id,permission_id,allowed)
SELECT u.id,p.id,CASE WHEN rp.permission_id IS NULL THEN 0 ELSE 1 END
FROM users u
CROSS JOIN permissions p
LEFT JOIN role_permissions rp ON rp.role_id=u.role_id AND rp.permission_id=p.id
WHERE p.code IN ('debts.view','debts.create','debts.pay','debts.verify','debts.manage');

INSERT INTO settings (setting_key,setting_value,updated_by)
VALUES ('debt_prefix','DEBT',NULL)
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
