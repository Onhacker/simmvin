-- Tambahkan workflow aktivasi event pada instalasi MVIN yang sudah berjalan.
START TRANSACTION;

INSERT INTO permissions (module,code,name,description)
VALUES (
  'Event',
  'events.activate',
  'Aktifkan / Tutup Event',
  'Mengaktifkan event agar tersedia untuk registrasi dan menutup penerimaan peserta baru.'
)
ON DUPLICATE KEY UPDATE
  module=VALUES(module),
  name=VALUES(name),
  description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT id, (SELECT id FROM permissions WHERE code='events.activate')
FROM roles
WHERE slug IN ('super-admin','manajer');

COMMIT;
