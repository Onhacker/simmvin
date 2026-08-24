# MVIN — Sistem Informasi Manajemen Pelatihan

Aplikasi CodeIgniter 3.1.13 untuk mengelola event pelatihan lintas kabupaten/kota, registrasi peserta per desa, pembayaran, pemasukan, pengeluaran, hutang perusahaan, transfer antar akun dana, saldo, laporan, pengguna, dan hak akses.

Antarmuka menggunakan aset dan pola komponen AppKit yang sudah tersedia di folder `template/`: stylesheet, font/icon, card, form, serta footer navigasi mobile. Logika aplikasi tetap dibuat sebagai modul CI3 agar mudah dipelihara.

## Instalasi lokal

1. Pastikan PHP 8, ekstensi `mysqli`, `dom`, `mbstring`, Apache `mod_rewrite`, Composer, dan MariaDB/MySQL aktif. Gunakan versi yang menegakkan `CHECK` constraint (MariaDB modern atau MySQL 8.0.16+).
2. Pasang dependensi aplikasi MVIN dari folder proyek:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Salin `.env.example` menjadi `.env`, lalu isi kredensial database. Untuk debugging lokal pada mesin pribadi, Anda boleh mengganti `APP_ENV=development`; server yang dapat diakses pengguna harus tetap `production`.
4. Buat database transaksi MVIN dan impor:

   ```bash
   mysql -u root -p -e "CREATE DATABASE simp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -u root -p simp < database/schema.sql
   mysql -u root -p simp < database/seed.sql
   ```

5. Pastikan koneksi `REGIONAL_DB_*` menunjuk database `rab_new`. Aplikasi hanya membaca tabel master `data_provinsi`, `data_kota`, `data_kecamatan`, dan `data_desa` dari koneksi tersebut.
6. Buka `http://localhost/simp/`.

Pada server Linux, pastikan proses PHP dapat menulis ke `application/cache`. Generator PDF otomatis memakai direktori sementara sistem sebagai cadangan apabila cache aplikasi tidak dapat ditulis.

Akun awal (khusus instalasi lokal/demo; jangan gunakan kredensial ini pada server publik):

- Username: `admin`
- Kata sandi: `Admin@123`

Segera ganti kata sandi melalui modul Pengguna setelah masuk.

## Prinsip pencatatan dana

- Pembayaran terverifikasi menambah saldo akun tujuan.
- Pengeluaran terverifikasi mengurangi akun sumber sebesar nominal ditambah biaya admin.
- Operator dapat menginput pembayaran/pengeluaran/transfer sebagai transaksi menunggu; saldo baru berubah setelah pengguna dengan izin verifikasi menyetujuinya.
- Transfer rekening perusahaan ke SeaBank/rekening pribadi dicatat sebagai transfer internal: akun sumber berkurang, akun tujuan bertambah, dan hanya biaya admin yang mengurangi total dana gabungan. Pada ringkasan yang memakai tanda **Masuk Total**, pokok transfer yang menyeberang ke akun yang dikecualikan dihitung sebagai arus keluar, sedangkan pokok dari akun yang dikecualikan ke akun yang dihitung dihitung sebagai arus masuk; ini menjaga rekonsiliasi saldo akun dan net transaksi tetap konsisten.
- Saldo tidak disimpan manual setelah transaksi; saldo dihitung dari saldo awal dan buku kas (`ledger_entries`).
- Status pembayaran `Belum Bayar`, `Sebagian`, `Lunas`, dan `Lebih Bayar` dihitung dari jumlah pembayaran terverifikasi dibanding tagihan desa/peserta.

## Hutang perusahaan

- Mencatat hutang hanya menambah daftar kewajiban; saldo kas/rekening belum berubah.
- Hutang dapat dikaitkan dengan event tertentu atau dicatat sebagai hutang umum perusahaan.
- Pembayaran dapat dilakukan sebagian atau lunas melalui modal **Bayar Hutang** dan, sesuai alur operasional aplikasi, hanya memakai akun **Kas Tunai**.
- Pembayaran hutang otomatis menjadi transaksi pengeluaran terhubung. Pembayaran terverifikasi langsung membuat jurnal keluar sebesar pokok pembayaran, sehingga saldo, dashboard, dan laporan keuangan ikut berubah tanpa input ulang.
- Pembayaran berstatus menunggu memesan sisa hutang agar tidak terjadi kelebihan bayar, tetapi belum mengurangi saldo sampai diverifikasi.
- Status hutang berubah menjadi **Lunas** otomatis setelah seluruh pokok terverifikasi. Jika verifikasi dibatalkan/ditolak, jurnal dibalik dan status hutang dihitung kembali.
- Pembayaran hutang tidak memakai biaya admin. Transfer/QRIS dan biayanya dicatat melalui alur pengeluaran umum.

Untuk instalasi lama, jalankan `database/patch_debts.sql` satu kali. Instalasi baru sudah memuat tabel, kategori, izin, dan pengaitan transaksi melalui `database/schema.sql` serta `database/seed.sql`.

Untuk instalasi lama, jalankan `database/patch_security_hardening.sql` setelah patch skema lain. Patch ini mengubah default transaksi baru menjadi `pending` dan menambahkan batas nominal nonnegatif tanpa mengubah transaksi historis yang sudah ada.

## Skema tagihan dan pembayaran registrasi

Event mendukung tiga skema tagihan:

- **Per Desa** (`per_village`): satu biaya tetap untuk setiap desa.
- **Per Peserta** (`per_participant`): jumlah peserta dikalikan biaya per peserta; pembayaran dan status dicatat untuk masing-masing peserta.
- **Paket Desa + Peserta Tambahan** (`per_village_extra`): biaya paket desa mencakup sejumlah peserta, kemudian peserta di atas kuota dikenakan biaya tambahan per orang. Rumusnya `biaya paket + max(0, jumlah peserta - peserta dalam paket) × biaya tambahan`.

Contoh paket Rp18.000.000 yang mencakup empat peserta dengan biaya tambahan Rp3.500.000 menghasilkan tagihan Rp18.000.000 untuk empat peserta dan Rp21.500.000 untuk lima peserta. Paket tidak diprorata bila jumlah peserta lebih sedikit dari kuota.

Pembayaran awal dapat dicatat langsung pada form registrasi dan bersifat opsional. Untuk skema per peserta, bagian pembayaran tersedia pada setiap peserta. Untuk skema per desa atau paket, pembayaran dicatat sekali pada tingkat desa. Cicilan atau pembayaran berikutnya dicatat melalui modal **Catat Bayar** pada detail registrasi, tanpa berpindah halaman. Tunai diarahkan ke akun kas, Transfer ke rekening bank/pribadi, dan QRIS ke akun QRIS; bukti wajib untuk Transfer/QRIS.

Total tagihan disimpan pada `registrations.expected_amount` sebagai snapshot. Komponen biaya peserta juga disimpan pada `participants.expected_amount`, termasuk penanda peserta tambahan pada skema paket. Karena itu detail dan laporan lama tidak menghitung ulang tagihan dari tarif event. Mode, tarif terkait, dan kuota paket dikunci setelah event mempunyai registrasi.

Untuk instalasi lama yang sudah memiliki tabel `training_events`, jalankan `database/patch_hybrid_billing.sql` sebelum memakai skema paket. Instalasi baru sudah memuat kolom dan nilai enum tersebut melalui `database/schema.sql`.

## Keamanan produksi

- Ganti `APP_KEY` dan kata sandi admin.
- Login dibatasi maksimal 10 kegagalan dalam jendela 10 menit berdasarkan identitas atau alamat IP.
- Pakai akun DB berbeda untuk MVIN dan akun **read-only** untuk `REGIONAL_DB_*`.
- Set `APP_ENV=production`, `APP_URL` HTTPS yang tepat, dan pastikan folder `uploads` tidak mengeksekusi PHP.
- Bukti pembayaran/pengeluaran/hutang/transfer tidak dapat dibuka langsung dari folder upload; file disajikan melalui controller yang memeriksa sesi dan hak akses.
- Folder `output` (PDF/XLSX hasil generate) dan `vendor` diblokir dari akses HTTP langsung. Dokumen hanya boleh diunduh melalui endpoint controller yang memeriksa sesi dan hak akses.
- Jangan menaruh `.env` di DocumentRoot pada server produksi bila konfigurasi web memungkinkan lokasi di luar web root. Jika harus berada di dalamnya, pertahankan aturan penolakan `.htaccess` dan uji respons `403` setelah deployment.
- Backup database `simp` dan folder `uploads` secara berkala.

## Siklus Event

- Event baru selalu disimpan sebagai `Draft` dan belum tersedia pada modul registrasi.
- Pengguna dengan izin **Aktifkan / Tutup Event** mengaktifkan event dari halaman detail. Beberapa event dapat aktif bersamaan.
- Event aktif tersedia pada registrasi peserta. Saat event ditutup, registrasi dan penambahan peserta baru berhenti, tetapi riwayat peserta serta transaksi tetap tersimpan.
- Event yang masih mempunyai pembayaran atau pengeluaran berstatus menunggu verifikasi harus diselesaikan sebelum ditutup.
- Untuk instalasi lama, jalankan `database/patch_event_activation.sql` satu kali agar izin aktivasi tersedia pada Super Admin dan Manajer.

## Master Jabatan Desa

- Form peserta menggunakan pilihan dari modul **Master Jabatan**; pengguna tidak perlu mengetik jabatan secara bebas.
- Master awal mencakup Pemerintah Desa, BPD, RT/RW, LPM/LPMD, TP-PKK, Karang Taruna, BUM Desa, Posyandu/kesehatan, Satlinmas, pendamping, dan unsur masyarakat.
- Jabatan dapat ditambah, diubah, atau dinonaktifkan. Data peserta menyimpan ID master sekaligus snapshot nama, sehingga riwayat lama tidak ikut berubah saat master diperbarui.
- Untuk instalasi lama, jalankan `database/patch_master_positions.sql` satu kali.

## Arsip registrasi dan koreksi peserta

- Registrasi event yang sudah ditutup tetap dapat dibuka, diperiksa, dan dicetak tanpa membuka penerimaan peserta kembali.
- Identitas peserta dapat dikoreksi pada event aktif tanpa mengubah snapshot tagihan.
- Penggantian atau penonaktifan peserta dicatat sebagai riwayat; alasan, pelaku, dan waktu perubahan disimpan untuk audit.
- Setiap koreksi menyimpan snapshot **sebelum** dan **sesudah** di `participant_revisions` dalam transaksi database yang sama. Jika riwayat audit gagal disimpan, perubahan peserta ikut dibatalkan.
- Setiap pembayaran menyimpan jejak status berurutan di `payment_status_history`. Pelaku/waktu verifikasi dan penolakan disimpan pada kolom terpisah sehingga penolakan tidak menimpa identitas verifikator sebelumnya.
- Penonaktifan ditolak apabila membuat tagihan lebih kecil daripada pembayaran terverifikasi/menunggu. Peserta terakhir tidak dapat dinonaktifkan; batalkan registrasi desa sebagai gantinya.
- Pada paket **Desa + Peserta Tambahan**, penonaktifan setelah ada pembayaran desa ditolak agar slot peserta yang sudah dibayar tidak bergeser; gunakan **Ganti Peserta** untuk mempertahankan slot tagihan.
- Registrasi hanya dapat dibatalkan bila tidak memiliki pembayaran terverifikasi atau menunggu.
- Pembatalan registrasi bersifat arsip dan tidak menghapus baris peserta. Jika pembatalan keliru, tombol **Pulihkan Registrasi** tersedia selama event aktif dan tidak ada pembayaran menunggu/terverifikasi.
- Pemulihan hanya mengaktifkan peserta yang ikut dinonaktifkan pada pembatalan terakhir; peserta yang sudah nonaktif sebelumnya tetap berada di arsip. Pembatalan dan pemulihan sama-sama tersimpan pada jejak audit.

Untuk instalasi lama, jalankan `database/patch_registration_lifecycle.sql`. Patch bersifat idempoten dan juga membuat jejak status awal untuk pembayaran historis yang belum memiliki riwayat.
- Untuk instalasi lama, jalankan `database/patch_registration_lifecycle.sql` satu kali.

## Pengamanan administrasi dan saldo

- Hanya Super Admin yang dapat membuat akun pengguna dan mengubah hak individual; minimal satu Super Admin aktif selalu dipertahankan.
- Jenis akun dana dan saldo awal dikunci setelah akun memiliki jurnal. Koreksi berikutnya harus tercatat sebagai transaksi, bukan dengan menulis ulang saldo awal.
- Akun dengan saldo tidak nol tidak dapat dinonaktifkan.
- Pembatalan verifikasi pembayaran atau transfer ditolak bila penarikan kembali jurnal akan membuat saldo akun menjadi negatif.

## Modul lanjutan yang disiapkan dalam rancangan

Fondasi data dapat dikembangkan untuk absensi, sertifikat, anggaran per event, impor/ekspor Excel, notifikasi WhatsApp, rekonsiliasi rekening, dan alur persetujuan berjenjang.
# simmvin
