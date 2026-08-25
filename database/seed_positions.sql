SET NAMES utf8mb4;

-- Master awal jabatan/peran peserta desa.
-- Nomenklatur daerah dapat berbeda; data tetap dapat ditambah, diubah, atau dinonaktifkan dari aplikasi.
INSERT INTO village_positions (code,name,category,description,sort_order,is_active,created_by) VALUES
-- Pemerintah Desa
('kepala-desa','Kepala Desa','pemerintah_desa','Struktur Pemerintah Desa.',10,1,1),
('penjabat-kepala-desa','Penjabat Kepala Desa','pemerintah_desa','Jabatan sementara atau variasi nomenklatur daerah.',20,1,1),
('pelaksana-tugas-kepala-desa','Pelaksana Tugas Kepala Desa','pemerintah_desa','Jabatan sementara atau variasi nomenklatur daerah.',30,1,1),
('sekretaris-desa','Sekretaris Desa','pemerintah_desa','Unsur sekretariat Pemerintah Desa.',40,1,1),
('kaur-tata-usaha-umum','Kepala Urusan Tata Usaha dan Umum','pemerintah_desa','Unsur staf sekretariat Pemerintah Desa.',50,1,1),
('kaur-keuangan','Kepala Urusan Keuangan','pemerintah_desa','Unsur staf sekretariat Pemerintah Desa.',60,1,1),
('kaur-perencanaan','Kepala Urusan Perencanaan','pemerintah_desa','Unsur staf sekretariat Pemerintah Desa.',70,1,1),
('kaur-umum-perencanaan','Kepala Urusan Umum dan Perencanaan','pemerintah_desa','Nomenklatur untuk struktur minimal dua urusan.',80,1,1),
('kasi-pemerintahan','Kepala Seksi Pemerintahan','pemerintah_desa','Pelaksana teknis Pemerintah Desa.',90,1,1),
('kasi-kesejahteraan','Kepala Seksi Kesejahteraan','pemerintah_desa','Pelaksana teknis Pemerintah Desa.',100,1,1),
('kasi-pelayanan','Kepala Seksi Pelayanan','pemerintah_desa','Pelaksana teknis Pemerintah Desa.',110,1,1),
('kasi-kesejahteraan-pelayanan','Kepala Seksi Kesejahteraan dan Pelayanan','pemerintah_desa','Nomenklatur untuk struktur minimal dua seksi.',120,1,1),
('kepala-dusun','Kepala Dusun / Kepala Kewilayahan','pemerintah_desa','Pelaksana kewilayahan Pemerintah Desa.',130,1,1),
('staf-desa','Staf Desa / Staf Perangkat Desa','pemerintah_desa','Jabatan operasional yang umum digunakan di desa.',140,1,1),
('bendahara-desa','Bendahara Desa','pemerintah_desa','Nomenklatur umum yang biasanya dijalankan Kepala Urusan Keuangan.',150,1,1),
('operator-siskeudes','Operator Siskeudes','pemerintah_desa','Jabatan operasional pengelola aplikasi keuangan desa.',160,1,1),
('operator-sipades','Operator SIPADES / Aset Desa','pemerintah_desa','Jabatan operasional pengelola aset desa.',170,1,1),
('operator-sid','Operator Sistem Informasi Desa','pemerintah_desa','Jabatan operasional pengelola sistem informasi desa.',180,1,1),
('operator-prodeskel','Operator Prodeskel','pemerintah_desa','Jabatan operasional pengelola profil desa dan kelurahan.',190,1,1),
('operator-sdgs-desa','Operator SDGs Desa','pemerintah_desa','Jabatan operasional pendataan SDGs Desa.',200,1,1),
('operator-adminduk','Operator Administrasi Kependudukan','pemerintah_desa','Jabatan operasional pelayanan administrasi kependudukan.',210,1,1),
('administrator-media-desa','Administrator Website / Media Desa','pemerintah_desa','Jabatan operasional informasi dan publikasi desa.',220,1,1),
('petugas-pelayanan-desa','Petugas Pelayanan Desa','pemerintah_desa','Jabatan operasional pelayanan masyarakat desa.',230,1,1),

-- Badan Permusyawaratan Desa
('ketua-bpd','Ketua BPD','bpd','Pimpinan Badan Permusyawaratan Desa.',10,1,1),
('wakil-ketua-bpd','Wakil Ketua BPD','bpd','Pimpinan Badan Permusyawaratan Desa.',20,1,1),
('sekretaris-bpd','Sekretaris BPD','bpd','Pimpinan Badan Permusyawaratan Desa.',30,1,1),
('ketua-bidang-pemerintahan-bpd','Ketua Bidang Penyelenggaraan Pemerintahan Desa dan Pembinaan Kemasyarakatan','bpd','Bidang Badan Permusyawaratan Desa.',40,1,1),
('ketua-bidang-pembangunan-bpd','Ketua Bidang Pembangunan Desa dan Pemberdayaan Masyarakat Desa','bpd','Bidang Badan Permusyawaratan Desa.',50,1,1),
('anggota-bpd','Anggota BPD','bpd','Anggota Badan Permusyawaratan Desa.',60,1,1),
('staf-administrasi-bpd','Staf Administrasi BPD','bpd','Staf administrasi Badan Permusyawaratan Desa.',70,1,1),

-- RT / RW
('ketua-rt','Ketua RT','rt_rw','Pengurus Rukun Tetangga.',10,1,1),
('wakil-ketua-rt','Wakil Ketua RT','rt_rw','Variasi kepengurusan lokal Rukun Tetangga.',20,1,1),
('sekretaris-rt','Sekretaris RT','rt_rw','Pengurus Rukun Tetangga.',30,1,1),
('bendahara-rt','Bendahara RT','rt_rw','Pengurus Rukun Tetangga.',40,1,1),
('ketua-bidang-rt','Ketua Bidang RT','rt_rw','Pengurus bidang Rukun Tetangga.',50,1,1),
('pengurus-rt','Pengurus / Anggota RT','rt_rw','Pengurus atau anggota Rukun Tetangga.',60,1,1),
('ketua-rw','Ketua RW','rt_rw','Pengurus Rukun Warga.',70,1,1),
('wakil-ketua-rw','Wakil Ketua RW','rt_rw','Variasi kepengurusan lokal Rukun Warga.',80,1,1),
('sekretaris-rw','Sekretaris RW','rt_rw','Pengurus Rukun Warga.',90,1,1),
('bendahara-rw','Bendahara RW','rt_rw','Pengurus Rukun Warga.',100,1,1),
('ketua-bidang-rw','Ketua Bidang RW','rt_rw','Pengurus bidang Rukun Warga.',110,1,1),
('pengurus-rw','Pengurus / Anggota RW','rt_rw','Pengurus atau anggota Rukun Warga.',120,1,1),

-- LPM / LPMD
('ketua-lpm','Ketua LPM / LPMD','lpmd','Pengurus Lembaga Pemberdayaan Masyarakat Desa.',10,1,1),
('wakil-ketua-lpm','Wakil Ketua LPM / LPMD','lpmd','Variasi kepengurusan lokal LPM atau LPMD.',20,1,1),
('sekretaris-lpm','Sekretaris LPM / LPMD','lpmd','Pengurus Lembaga Pemberdayaan Masyarakat Desa.',30,1,1),
('bendahara-lpm','Bendahara LPM / LPMD','lpmd','Pengurus Lembaga Pemberdayaan Masyarakat Desa.',40,1,1),
('ketua-bidang-lpm','Ketua Bidang LPM / LPMD','lpmd','Pengurus bidang Lembaga Pemberdayaan Masyarakat Desa.',50,1,1),
('anggota-lpm','Pengurus / Anggota LPM / LPMD','lpmd','Pengurus atau anggota Lembaga Pemberdayaan Masyarakat Desa.',60,1,1),

-- TP-PKK
('ketua-tp-pkk','Ketua TP-PKK Desa','pkk','Pengurus Tim Penggerak PKK Desa.',10,1,1),
('wakil-ketua-tp-pkk','Wakil Ketua TP-PKK Desa','pkk','Pengurus Tim Penggerak PKK Desa.',20,1,1),
('sekretaris-tp-pkk','Sekretaris TP-PKK Desa','pkk','Pengurus Tim Penggerak PKK Desa.',30,1,1),
('bendahara-tp-pkk','Bendahara TP-PKK Desa','pkk','Pengurus Tim Penggerak PKK Desa.',40,1,1),
('ketua-pokja-1-pkk','Ketua Pokja I TP-PKK','pkk','Pengurus Kelompok Kerja I TP-PKK.',50,1,1),
('wakil-ketua-pokja-1-pkk','Wakil Ketua Pokja I TP-PKK','pkk','Pengurus Kelompok Kerja I TP-PKK.',60,1,1),
('sekretaris-pokja-1-pkk','Sekretaris Pokja I TP-PKK','pkk','Pengurus Kelompok Kerja I TP-PKK.',70,1,1),
('anggota-pokja-1-pkk','Anggota Pokja I TP-PKK','pkk','Anggota Kelompok Kerja I TP-PKK.',80,1,1),
('ketua-pokja-2-pkk','Ketua Pokja II TP-PKK','pkk','Pengurus Kelompok Kerja II TP-PKK.',90,1,1),
('wakil-ketua-pokja-2-pkk','Wakil Ketua Pokja II TP-PKK','pkk','Pengurus Kelompok Kerja II TP-PKK.',100,1,1),
('sekretaris-pokja-2-pkk','Sekretaris Pokja II TP-PKK','pkk','Pengurus Kelompok Kerja II TP-PKK.',110,1,1),
('anggota-pokja-2-pkk','Anggota Pokja II TP-PKK','pkk','Anggota Kelompok Kerja II TP-PKK.',120,1,1),
('ketua-pokja-3-pkk','Ketua Pokja III TP-PKK','pkk','Pengurus Kelompok Kerja III TP-PKK.',130,1,1),
('wakil-ketua-pokja-3-pkk','Wakil Ketua Pokja III TP-PKK','pkk','Pengurus Kelompok Kerja III TP-PKK.',140,1,1),
('sekretaris-pokja-3-pkk','Sekretaris Pokja III TP-PKK','pkk','Pengurus Kelompok Kerja III TP-PKK.',150,1,1),
('anggota-pokja-3-pkk','Anggota Pokja III TP-PKK','pkk','Anggota Kelompok Kerja III TP-PKK.',160,1,1),
('ketua-pokja-4-pkk','Ketua Pokja IV TP-PKK','pkk','Pengurus Kelompok Kerja IV TP-PKK.',170,1,1),
('wakil-ketua-pokja-4-pkk','Wakil Ketua Pokja IV TP-PKK','pkk','Pengurus Kelompok Kerja IV TP-PKK.',180,1,1),
('sekretaris-pokja-4-pkk','Sekretaris Pokja IV TP-PKK','pkk','Pengurus Kelompok Kerja IV TP-PKK.',190,1,1),
('anggota-pokja-4-pkk','Anggota Pokja IV TP-PKK','pkk','Anggota Kelompok Kerja IV TP-PKK.',200,1,1),
('ketua-pkk-dusun','Ketua Kelompok PKK Dusun / Lingkungan','pkk','Pengurus kelompok PKK wilayah.',210,1,1),
('ketua-pkk-rw','Ketua Kelompok PKK RW','pkk','Pengurus kelompok PKK wilayah.',220,1,1),
('ketua-pkk-rt','Ketua Kelompok PKK RT','pkk','Pengurus kelompok PKK wilayah.',230,1,1),
('sekretaris-kelompok-pkk','Sekretaris Kelompok PKK','pkk','Pengurus kelompok PKK wilayah.',240,1,1),
('bendahara-kelompok-pkk','Bendahara Kelompok PKK','pkk','Pengurus kelompok PKK wilayah.',250,1,1),
('koordinator-dasawisma','Koordinator Dasawisma','pkk','Pengurus kelompok Dasawisma.',260,1,1),
('kader-dasawisma','Kader Dasawisma','pkk','Kader kelompok Dasawisma.',270,1,1),

-- Karang Taruna
('ketua-karang-taruna','Ketua Karang Taruna Desa','karang_taruna','Pengurus Karang Taruna Desa.',10,1,1),
('wakil-ketua-karang-taruna','Wakil Ketua Karang Taruna Desa','karang_taruna','Variasi kepengurusan lokal Karang Taruna.',20,1,1),
('sekretaris-karang-taruna','Sekretaris Karang Taruna Desa','karang_taruna','Pengurus Karang Taruna Desa.',30,1,1),
('bendahara-karang-taruna','Bendahara Karang Taruna Desa','karang_taruna','Pengurus Karang Taruna Desa.',40,1,1),
('ketua-bidang-karang-taruna','Ketua / Pengurus Bidang Karang Taruna','karang_taruna','Pengurus bidang Karang Taruna Desa.',50,1,1),
('anggota-karang-taruna','Anggota / Pengurus Karang Taruna','karang_taruna','Anggota atau pengurus Karang Taruna Desa.',60,1,1),

-- BUM Desa
('penasihat-bumdes','Penasihat BUM Desa','bumdes','Organisasi BUM Desa.',10,1,1),
('anggota-dewan-penasihat-bumdesma','Anggota Dewan Penasihat BUM Desa Bersama','bumdes','Organisasi BUM Desa Bersama.',20,1,1),
('direktur-bumdes','Direktur BUM Desa','bumdes','Pelaksana operasional BUM Desa.',30,1,1),
('direktur-bumdesma','Direktur BUM Desa Bersama','bumdes','Pelaksana operasional BUM Desa Bersama.',40,1,1),
('direktur-utama-bumdes','Direktur Utama BUM Desa / BUM Desa Bersama','bumdes','Pelaksana operasional BUM Desa atau BUM Desa Bersama.',50,1,1),
('pelaksana-operasional-bumdes','Anggota Pelaksana Operasional BUM Desa','bumdes','Pelaksana operasional BUM Desa.',60,1,1),
('ketua-pengawas-bumdes','Ketua Dewan Pengawas BUM Desa','bumdes','Pengawas BUM Desa.',70,1,1),
('pengawas-bumdes','Pengawas BUM Desa','bumdes','Pengawas BUM Desa.',80,1,1),
('anggota-pengawas-bumdes','Anggota Dewan Pengawas BUM Desa','bumdes','Pengawas BUM Desa.',90,1,1),
('sekretaris-bumdes','Sekretaris BUM Desa','bumdes','Jabatan operasional yang umum digunakan BUM Desa.',100,1,1),
('bendahara-bumdes','Bendahara BUM Desa','bumdes','Jabatan operasional yang umum digunakan BUM Desa.',110,1,1),
('manajer-unit-usaha-bumdes','Kepala / Manajer Unit Usaha BUM Desa','bumdes','Jabatan operasional unit usaha BUM Desa.',120,1,1),
('manajer-operasional-bumdes','Manajer Operasional BUM Desa','bumdes','Jabatan operasional BUM Desa.',130,1,1),
('manajer-keuangan-bumdes','Manajer Keuangan BUM Desa','bumdes','Jabatan operasional BUM Desa.',140,1,1),
('pegawai-bumdes','Pegawai / Staf Unit Usaha BUM Desa','bumdes','Pegawai atau staf BUM Desa.',150,1,1),

-- Kemasyarakatan dan adat
('ketua-lembaga-adat','Ketua Lembaga Adat Desa','kemasyarakatan','Nomenklatur mengikuti peraturan dan kebiasaan daerah.',10,1,1),
('wakil-ketua-lembaga-adat','Wakil Ketua Lembaga Adat Desa','kemasyarakatan','Nomenklatur mengikuti peraturan dan kebiasaan daerah.',20,1,1),
('sekretaris-lembaga-adat','Sekretaris Lembaga Adat Desa','kemasyarakatan','Nomenklatur mengikuti peraturan dan kebiasaan daerah.',30,1,1),
('bendahara-lembaga-adat','Bendahara Lembaga Adat Desa','kemasyarakatan','Nomenklatur mengikuti peraturan dan kebiasaan daerah.',40,1,1),
('anggota-lembaga-adat','Pengurus / Anggota Lembaga Adat Desa','kemasyarakatan','Nomenklatur mengikuti peraturan dan kebiasaan daerah.',50,1,1),
('pemangku-adat','Pemangku Adat','kemasyarakatan','Peran adat dengan nomenklatur yang dapat berbeda di setiap daerah.',60,1,1),
('kpmd','Kader Pemberdayaan Masyarakat Desa','kemasyarakatan','Kader pemberdayaan masyarakat desa.',70,1,1),
('kader-digital-desa','Kader Digital Desa','kemasyarakatan','Peran fungsional literasi dan transformasi digital desa.',80,1,1),
('tokoh-masyarakat','Tokoh Masyarakat','kemasyarakatan','Unsur masyarakat desa.',90,1,1),
('tokoh-agama','Tokoh Agama','kemasyarakatan','Unsur masyarakat desa.',100,1,1),
('tokoh-adat','Tokoh Adat','kemasyarakatan','Unsur masyarakat desa.',110,1,1),
('tokoh-perempuan','Tokoh Perempuan','kemasyarakatan','Unsur masyarakat desa.',120,1,1),
('tokoh-pemuda','Tokoh Pemuda','kemasyarakatan','Unsur masyarakat desa.',130,1,1),

-- Kesehatan Desa dan Posyandu
('ketua-posyandu','Ketua Posyandu','kesehatan','Pengurus Posyandu.',10,1,1),
('sekretaris-posyandu','Sekretaris Posyandu','kesehatan','Pengurus Posyandu.',20,1,1),
('bendahara-posyandu','Bendahara Posyandu','kesehatan','Pengurus Posyandu.',30,1,1),
('ketua-bidang-pendidikan-posyandu','Ketua Bidang Pendidikan Posyandu','kesehatan','Pengurus bidang Posyandu.',40,1,1),
('kader-bidang-pendidikan-posyandu','Kader Bidang Pendidikan Posyandu','kesehatan','Kader bidang Posyandu.',50,1,1),
('ketua-bidang-kesehatan-posyandu','Ketua Bidang Kesehatan Posyandu','kesehatan','Pengurus bidang Posyandu.',60,1,1),
('kader-bidang-kesehatan-posyandu','Kader Bidang Kesehatan Posyandu','kesehatan','Kader bidang Posyandu.',70,1,1),
('ketua-bidang-pu-posyandu','Ketua Bidang Pekerjaan Umum Posyandu','kesehatan','Pengurus bidang Posyandu.',80,1,1),
('kader-bidang-pu-posyandu','Kader Bidang Pekerjaan Umum Posyandu','kesehatan','Kader bidang Posyandu.',90,1,1),
('ketua-bidang-perumahan-posyandu','Ketua Bidang Perumahan Rakyat Posyandu','kesehatan','Pengurus bidang Posyandu.',100,1,1),
('kader-bidang-perumahan-posyandu','Kader Bidang Perumahan Rakyat Posyandu','kesehatan','Kader bidang Posyandu.',110,1,1),
('ketua-bidang-trantib-posyandu','Ketua Bidang Ketenteraman, Ketertiban Umum dan Perlindungan Masyarakat Posyandu','kesehatan','Pengurus bidang Posyandu.',120,1,1),
('kader-bidang-trantib-posyandu','Kader Bidang Ketenteraman, Ketertiban Umum dan Perlindungan Masyarakat Posyandu','kesehatan','Kader bidang Posyandu.',130,1,1),
('ketua-bidang-sosial-posyandu','Ketua Bidang Sosial Posyandu','kesehatan','Pengurus bidang Posyandu.',140,1,1),
('kader-bidang-sosial-posyandu','Kader Bidang Sosial Posyandu','kesehatan','Kader bidang Posyandu.',150,1,1),
('kader-posyandu','Kader Posyandu','kesehatan','Nomenklatur umum kader Posyandu.',160,1,1),
('kader-pembangunan-manusia','Kader Pembangunan Manusia','kesehatan','Peran fungsional pembangunan manusia desa.',170,1,1),
('kader-kesehatan-desa','Kader Kesehatan Desa','kesehatan','Peran fungsional kesehatan desa.',180,1,1),
('kader-kb','Kader Keluarga Berencana','kesehatan','Peran fungsional keluarga berencana.',190,1,1),
('tim-pendamping-keluarga','Tim Pendamping Keluarga','kesehatan','Peran fungsional pendampingan keluarga.',200,1,1),
('bidan-desa','Bidan Desa','kesehatan','Tenaga kesehatan yang bertugas di desa.',210,1,1),
('perawat-desa','Perawat Desa','kesehatan','Tenaga kesehatan yang bertugas di desa.',220,1,1),

-- Keamanan Desa
('kepala-satlinmas','Kepala Satlinmas','keamanan','Organisasi Satuan Perlindungan Masyarakat.',10,1,1),
('kepala-pelaksana-satlinmas','Kepala Pelaksana Satlinmas','keamanan','Organisasi Satuan Perlindungan Masyarakat.',20,1,1),
('komandan-regu-satlinmas','Komandan Regu Satlinmas','keamanan','Organisasi Satuan Perlindungan Masyarakat.',30,1,1),
('anggota-satlinmas','Anggota Satlinmas','keamanan','Anggota Satuan Perlindungan Masyarakat.',40,1,1),
('babinsa','Babinsa','keamanan','Unsur pembina kewilayahan yang bermitra dengan desa.',50,1,1),
('bhabinkamtibmas','Bhabinkamtibmas','keamanan','Unsur pembina keamanan yang bermitra dengan desa.',60,1,1),

-- Pendamping dan penyuluh
('pendamping-desa','Pendamping Desa','pendamping','Tenaga pendamping profesional.',10,1,1),
('pendamping-lokal-desa','Pendamping Lokal Desa','pendamping','Tenaga pendamping profesional.',20,1,1),
('tenaga-ahli-pemberdayaan','Tenaga Ahli Pemberdayaan Masyarakat','pendamping','Tenaga pendamping profesional.',30,1,1),
('penyuluh-pertanian','Penyuluh Pertanian','pendamping','Tenaga penyuluh yang bermitra dengan desa.',40,1,1),
('penyuluh-kb','Penyuluh Keluarga Berencana','pendamping','Tenaga penyuluh yang bermitra dengan desa.',50,1,1),

-- Umum
('masyarakat-umum','Masyarakat Umum','lainnya','Peserta dari unsur masyarakat yang tidak memiliki jabatan khusus.',10,1,1),
('lainnya','Lainnya','lainnya','Gunakan sementara bila jabatan belum tersedia, lalu tambahkan nomenklatur yang tepat pada master.',999,1,1)
ON DUPLICATE KEY UPDATE
  name=VALUES(name),
  category=VALUES(category),
  description=VALUES(description),
  sort_order=VALUES(sort_order);

-- Mailing menggunakan jabatan tanpa kata "Desa". Kode tetap memakai slug
-- lama agar referensi/URL yang sudah tersimpan tidak berubah.
UPDATE village_positions
SET name = TRIM(
        REGEXP_REPLACE(
            REGEXP_REPLACE(name, '[[:space:]]+Desa([[:space:]]|$)', ' '),
            '[[:space:]]+', ' '
        )
    ),
    updated_at = NOW()
WHERE name REGEXP '[[:space:]]+Desa([[:space:]]|$)';
