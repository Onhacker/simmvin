-- Jalankan satu kali pada instalasi MVIN yang sudah ada.
-- Untuk instalasi baru, schema.sql dan seed.sql sudah memuat perubahan ini.

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

SET @has_position_id = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='participants' AND COLUMN_NAME='position_id'
);
SET @ddl = IF(
  @has_position_id=0,
  'ALTER TABLE participants ADD COLUMN position_id INT UNSIGNED NULL AFTER full_name',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_position_index = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='participants' AND INDEX_NAME='idx_participants_position'
);
SET @ddl = IF(
  @has_position_index=0,
  'ALTER TABLE participants ADD KEY idx_participants_position (position_id)',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_position_fk = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='participants' AND CONSTRAINT_NAME='fk_participants_position'
);
SET @ddl = IF(
  @has_position_fk=0,
  'ALTER TABLE participants ADD CONSTRAINT fk_participants_position FOREIGN KEY (position_id) REFERENCES village_positions(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO permissions (module,code,name,description) VALUES
('Master Jabatan','positions.view','Lihat Master Jabatan','Melihat daftar jabatan yang dapat dipilih saat input peserta.'),
('Master Jabatan','positions.manage','Kelola Master Jabatan','Menambah, mengubah, dan mengaktifkan atau menonaktifkan jabatan peserta.')
ON DUPLICATE KEY UPDATE module=VALUES(module),name=VALUES(name),description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code IN ('positions.view','positions.manage')
WHERE r.slug='super-admin';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code='positions.view'
WHERE r.slug='direktur';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.code IN ('positions.view','positions.manage')
WHERE r.slug='manajer';

-- Pengguna yang sudah memiliki matriks izin eksplisit mendapat nilai awal sesuai perannya.
INSERT INTO user_permissions (user_id,permission_id,allowed)
SELECT u.id,p.id,CASE WHEN rp.permission_id IS NULL THEN 0 ELSE 1 END
FROM users u
CROSS JOIN permissions p
LEFT JOIN role_permissions rp ON rp.role_id=u.role_id AND rp.permission_id=p.id
WHERE p.code IN ('positions.view','positions.manage')
ON DUPLICATE KEY UPDATE allowed=VALUES(allowed);

SOURCE database/seed_positions.sql;

-- Hubungkan data lama bila nama snapshot cocok tepat dengan satu nama master.
UPDATE participants participant
JOIN (
  SELECT name,MIN(id) AS id
  FROM village_positions
  GROUP BY name
  HAVING COUNT(*)=1
) master ON TRIM(participant.position)=master.name
SET participant.position_id=master.id
WHERE participant.position_id IS NULL
  AND NULLIF(TRIM(participant.position),'') IS NOT NULL;

-- Sinkronkan snapshot peserta setelah master mailing dinormalisasi.
UPDATE participants participant
JOIN village_positions master ON master.id=participant.position_id
SET participant.position=master.name,
    participant.updated_at=NOW()
WHERE participant.position_id IS NOT NULL;

-- Hubungkan snapshot lama yang belum memiliki position_id setelah kata
-- "Desa" dihapus dari label master.
UPDATE participants participant
JOIN village_positions master
  ON master.name = TRIM(
        REGEXP_REPLACE(
            REGEXP_REPLACE(participant.position, '[[:space:]]+Desa([[:space:]]|$)', ' '),
            '[[:space:]]+', ' '
        )
     )
SET participant.position_id=master.id,
    participant.position=master.name,
    participant.updated_at=NOW()
WHERE participant.position_id IS NULL
  AND NULLIF(TRIM(participant.position),'') IS NOT NULL;
