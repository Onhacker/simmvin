-- Jalankan satu kali pada instalasi MVIN yang sudah ada.
-- Nama jabatan disimpan tanpa kata "Desa" agar dapat dipakai sebagai
-- field mailing; template Word dapat menambahkan konteks "Desa" sendiri.

START TRANSACTION;

UPDATE village_positions
SET name = TRIM(
        REGEXP_REPLACE(
            REGEXP_REPLACE(name, '[[:space:]]+Desa([[:space:]]|$)', ' '),
            '[[:space:]]+', ' '
        )
    ),
    updated_at = NOW()
WHERE name REGEXP '[[:space:]]+Desa([[:space:]]|$)';

-- Sinkronkan snapshot jabatan peserta lama tanpa mengubah position_id.
UPDATE participants p
JOIN village_positions v ON v.id = p.position_id
SET p.position = v.name,
    p.updated_at = NOW()
WHERE p.position_id IS NOT NULL;

-- Hubungkan snapshot lama yang belum memiliki position_id berdasarkan nama
-- yang sudah dinormalisasi.
UPDATE participants p
JOIN village_positions v
  ON v.name = TRIM(
        REGEXP_REPLACE(
            REGEXP_REPLACE(p.position, '[[:space:]]+Desa([[:space:]]|$)', ' '),
            '[[:space:]]+', ' '
        )
     )
SET p.position_id = v.id,
    p.position = v.name,
    p.updated_at = NOW()
WHERE p.position_id IS NULL
  AND NULLIF(TRIM(p.position), '') IS NOT NULL;

COMMIT;
