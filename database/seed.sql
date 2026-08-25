SET NAMES utf8mb4;

INSERT INTO roles (id,name,slug,description,is_system) VALUES
(1,'Super Admin','super-admin','Akses penuh seluruh sistem.',1),
(2,'Direktur','direktur','Memantau laporan, saldo, dan kendali manajemen.',1),
(3,'Manajer','manajer','Mengelola event, registrasi, dan operasional.',1),
(4,'Keuangan / Bendahara','keuangan','Mengelola pembayaran, pengeluaran, transfer, dan rekening.',1),
(5,'Anggota / Operator','anggota','Input registrasi peserta dan transaksi operasional terbatas.',1),
(6,'Auditor','auditor','Akses baca untuk laporan dan transaksi.',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description);

INSERT INTO permissions (id,module,code,name,description) VALUES
(1,'Dashboard','dashboard.view','Lihat Dashboard',NULL),
(2,'Event','events.view','Lihat Event',NULL),
(3,'Event','events.create','Tambah Event',NULL),
(4,'Event','events.edit','Ubah Event',NULL),
(5,'Registrasi','registrations.view','Lihat Registrasi',NULL),
(6,'Registrasi','registrations.create','Tambah Registrasi',NULL),
(7,'Registrasi','registrations.edit','Ubah Registrasi',NULL),
(8,'Pembayaran','payments.create','Catat Pembayaran',NULL),
(9,'Pembayaran','payments.verify','Verifikasi Pembayaran',NULL),
(10,'Laporan','reports.income','Lihat Laporan Pemasukan',NULL),
(11,'Laporan','reports.finance','Lihat Laporan Keuangan',NULL),
(12,'Pengeluaran','expenses.view','Lihat Pengeluaran',NULL),
(13,'Pengeluaran','expenses.create','Tambah Pengeluaran',NULL),
(14,'Transfer Dana','transfers.view','Lihat Transfer Dana',NULL),
(15,'Transfer Dana','transfers.create','Tambah Transfer Dana',NULL),
(16,'Kas dan Rekening','accounts.view','Lihat Kas dan Rekening',NULL),
(17,'Kas dan Rekening','accounts.manage','Kelola Kas dan Rekening',NULL),
(18,'Pengguna','users.view','Lihat Pengguna',NULL),
(19,'Pengguna','users.manage','Kelola Pengguna',NULL),
(20,'Hak Akses','roles.manage','Kelola Hak Akses',NULL),
(21,'Audit','audit.view','Lihat Audit Trail',NULL),
(22,'Pengeluaran','expenses.verify','Verifikasi Pengeluaran',NULL),
(23,'Transfer Dana','transfers.verify','Verifikasi Transfer Dana',NULL),
(24,'Event','events.activate','Aktifkan / Tutup Event','Mengaktifkan event agar tersedia untuk registrasi dan menutup penerimaan peserta baru.'),
(25,'Master Jabatan','positions.view','Lihat Master Jabatan','Melihat daftar jabatan yang dapat dipilih saat input peserta.'),
(26,'Master Jabatan','positions.manage','Kelola Master Jabatan','Menambah, mengubah, dan mengaktifkan atau menonaktifkan jabatan peserta.')
ON DUPLICATE KEY UPDATE module=VALUES(module),name=VALUES(name),description=VALUES(description);

-- Do not pin IDs for later modules: an upgraded installation can already use
-- the next auto-increment values for custom permissions.
INSERT INTO permissions (module,code,name,description) VALUES
('Hutang','debts.view','Lihat Hutang','Melihat kewajiban dan riwayat pembayaran hutang perusahaan.'),
('Hutang','debts.create','Tambah Hutang','Mencatat hutang perusahaan baru.'),
('Hutang','debts.pay','Bayar Hutang','Mencatat pembayaran hutang melalui kas atau rekening.'),
('Hutang','debts.verify','Verifikasi Pembayaran Hutang','Memverifikasi atau menolak pembayaran hutang.'),
('Hutang','debts.manage','Kelola Hutang','Membatalkan hutang yang belum memiliki pembayaran.')
ON DUPLICATE KEY UPDATE module=VALUES(module),name=VALUES(name),description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 1,id FROM permissions;
INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 2,id FROM permissions WHERE code IN ('dashboard.view','events.view','registrations.view','reports.income','reports.finance','expenses.view','transfers.view','accounts.view','users.view','audit.view','positions.view','debts.view');
INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 3,id FROM permissions WHERE code IN ('dashboard.view','events.view','events.create','events.edit','events.activate','registrations.view','registrations.create','registrations.edit','payments.create','reports.income','expenses.view','expenses.create','accounts.view','positions.view','positions.manage','debts.view','debts.create','debts.pay');
INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 4,id FROM permissions WHERE code IN ('dashboard.view','events.view','registrations.view','payments.create','payments.verify','reports.income','reports.finance','expenses.view','expenses.create','expenses.verify','transfers.view','transfers.create','transfers.verify','accounts.view','accounts.manage','debts.view','debts.create','debts.pay','debts.verify','debts.manage');
INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 5,id FROM permissions WHERE code IN ('dashboard.view','events.view','registrations.view','registrations.create','registrations.edit','payments.create','expenses.view','expenses.create','debts.view','debts.pay');
INSERT IGNORE INTO role_permissions (role_id,permission_id) SELECT 6,id FROM permissions WHERE code IN ('dashboard.view','events.view','registrations.view','reports.income','reports.finance','expenses.view','transfers.view','accounts.view','audit.view','debts.view');

INSERT INTO users (id,role_id,name,username,email,phone,password_hash,is_active) VALUES
(1,1,'Administrator MVIN','admin','admin@mvin.local',NULL,'$2y$10$kMwL3J983m4HlCwZRrbObOY8lh1SI2hX015YWtGuZarlLrg45Mt5u',1)
ON DUPLICATE KEY UPDATE role_id=VALUES(role_id),name=VALUES(name),email=VALUES(email),is_active=1;

INSERT INTO fund_accounts (id,name,type,bank_name,account_number,account_holder,opening_balance,include_in_total,is_active,sort_order,created_by) VALUES
(1,'Kas Tunai','cash',NULL,NULL,NULL,0,1,1,1,1),
(2,'Rekening Perusahaan','bank','Bank Perusahaan',NULL,NULL,0,1,1,2,1),
(3,'SeaBank / Rekening Titipan','personal','SeaBank',NULL,NULL,0,1,1,3,1),
(4,'QRIS','qris','QRIS',NULL,NULL,0,1,1,4,1)
ON DUPLICATE KEY UPDATE name=VALUES(name),type=VALUES(type),is_active=1;

INSERT INTO expense_categories (id,name,code,is_active) VALUES
(1,'Tiket','tiket',1),(2,'Transportasi','transportasi',1),(3,'Akomodasi','akomodasi',1),
(4,'Konsumsi','konsumsi',1),(5,'Honor','honor',1),(6,'Sewa Tempat / Alat','sewa',1),
(7,'Perlengkapan Pelatihan','perlengkapan',1),(8,'Publikasi dan Dokumentasi','publikasi',1),
(9,'Operasional','operasional',1),(10,'Pajak','pajak',1),(11,'Biaya Bank / Transfer','biaya-bank',1),
(12,'Lainnya','lainnya',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1;

INSERT INTO expense_categories (name,code,is_active)
VALUES ('Pembayaran Hutang','pembayaran-hutang',1)
ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1;

INSERT INTO settings (setting_key,setting_value,updated_by) VALUES
('organization_name','Penyelenggara Pelatihan',1),('receipt_prefix','BYR',1),('expense_prefix','OUT',1),('transfer_prefix','TRF',1),('debt_prefix','DEBT',1)
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

SOURCE database/seed_positions.sql;
