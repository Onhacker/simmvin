# Desain bisnis MVIN

## Event

Satu event dapat mencakup banyak kabupaten/kota. Nama wilayah disimpan sebagai snapshot pada event dan registrasi agar laporan lama tidak berubah saat master wilayah diperbarui.

Event menyediakan tiga mode tagihan:

- `per_village`: setiap desa dikenakan satu biaya tetap, berapa pun jumlah pesertanya.
- `per_participant`: tagihan desa adalah jumlah peserta dikalikan biaya per peserta; pembayaran ditujukan kepada masing-masing peserta.
- `per_village_extra`: desa membayar satu biaya paket yang mencakup sejumlah peserta, lalu setiap peserta di atas kuota dikenakan biaya tambahan. Rumusnya `biaya paket desa + max(0, jumlah peserta - peserta dalam paket) × biaya peserta tambahan`.

Sebagai contoh, paket desa Rp18.000.000 mencakup empat peserta dan biaya tambahan Rp3.500.000 per orang. Desa dengan empat peserta tetap ditagih Rp18.000.000, sedangkan desa dengan lima peserta ditagih Rp21.500.000. Biaya paket tetap berlaku penuh bila jumlah peserta belum mencapai kuota.

Mode, tarif yang digunakan, dan jumlah peserta dalam paket tidak dapat diubah setelah event mempunyai registrasi, termasuk bila registrasinya kemudian dibatalkan. Aktivasi event memeriksa agar konfigurasi tagihan yang dipilih sudah lengkap dan bernilai lebih besar dari nol.

## Registrasi dan pembayaran

Registrasi unik untuk kombinasi event dan desa. Satu desa memiliki satu atau lebih peserta. Pada mode `per_village` dan `per_village_extra`, tagihan serta pembayaran berada pada registrasi desa. Pada mode `per_participant`, masing-masing peserta memiliki tagihan dan pembayaran sendiri.

Form registrasi dapat sekaligus mencatat pembayaran awal agar operator tidak perlu membuka halaman pembayaran lagi. Pembayaran ini bersifat opsional: mode per peserta menampilkan bagian pembayaran pada setiap peserta, sedangkan mode per desa dan paket menampilkannya sekali pada setiap desa. Cicilan atau pembayaran berikutnya dicatat melalui modal pada detail registrasi. Metode Tunai memakai akun kas, Transfer memakai rekening bank/pribadi, dan QRIS memakai akun QRIS. Bukti wajib untuk Transfer/QRIS dan opsional untuk Tunai.

Status `Belum Bayar`, `Bayar Sebagian`, dan `Lunas` dihitung dari transaksi, bukan dipilih manual. Pembayaran awal langsung berstatus terverifikasi dan masuk buku kas bila pencatat mempunyai izin verifikasi; jika tidak, pembayaran berstatus menunggu sampai diperiksa petugas berwenang. Pembayaran menunggu maupun terverifikasi diperhitungkan saat mencegah kelebihan bayar, tetapi hanya pembayaran terverifikasi yang dihitung sebagai dana masuk.

Nilai `registrations.expected_amount` merupakan snapshot total tagihan saat registrasi dibuat. Untuk mode per peserta, `participants.expected_amount` menyimpan biaya peserta; untuk mode paket, nilainya nol bagi peserta yang termasuk kuota dan sebesar biaya tambahan bagi peserta di atas kuota. Peserta tambahan yang dimasukkan kemudian akan menambah snapshot tagihan desa. Detail event, status pembayaran, dan laporan menggunakan snapshot tersebut, bukan menghitung ulang memakai tarif event saat ini.

Hanya pembayaran berstatus terverifikasi yang masuk laporan pemasukan dan buku kas.

Laporan per desa dan per peserta tetap menampilkan entitas yang belum membayar. Rekap menunjukkan jumlah desa, jumlah peserta, total tagihan, total masuk, sisa tagihan, serta pemisahan Tunai, Transfer, dan QRIS.

## Kas, rekening, dan transfer internal

Semua lokasi dana dimodelkan sebagai akun: kas tunai, rekening perusahaan, QRIS, atau rekening pribadi/titipan seperti SeaBank. Rekening pribadi dapat tetap ditandai sebagai bagian dari dana perusahaan. Transfer antar akun bukan pengeluaran; biaya transfer merupakan pengurang dana.

## Pengeluaran

Pengeluaran memuat event opsional, kategori, tanggal, penerima/vendor, uraian, nominal, metode, akun sumber, biaya admin, dan bukti. Kategori awal mencakup tiket, transportasi, akomodasi, konsumsi, honor, sewa, perlengkapan, publikasi, operasional, pajak, biaya bank, dan lainnya.

Pengeluaran dan transfer dapat memakai alur `pending → verified/rejected`. Hanya izin verifikasi yang dapat memposting transaksi tersebut ke buku kas.

## Hutang perusahaan

`company_debts` menyimpan kewajiban kepada kreditur, tanggal hutang dan jatuh tempo, nilai pokok, event opsional, serta status. Pencatatan hutang tidak langsung membuat jurnal kas karena belum terjadi perpindahan dana.

Setiap pembayaran hutang disimpan sebagai baris `expenses` yang memiliki `debt_id`. Dengan demikian pembayaran memakai alur verifikasi, bukti transaksi, akun dana, biaya admin, laporan pengeluaran, dan `ledger_entries` yang sama dengan pengeluaran lain. Pembayaran terverifikasi memposting jurnal keluar sebesar `amount + admin_fee`; hanya `amount` yang mengurangi pokok hutang.

Sisa pokok dihitung dari nilai hutang dikurangi pembayaran terverifikasi. Pembayaran menunggu ikut dihitung sebagai dana yang sudah dialokasikan ketika memvalidasi pembayaran baru, sehingga dua pencatatan bersamaan tidak dapat melebihi sisa hutang. Status **Lunas** ditentukan otomatis ketika jumlah pokok terverifikasi mencapai nilai hutang dan kembali **Terbuka** bila verifikasi dibalik.

Hutang tidak dihapus untuk koreksi. Hutang tanpa pembayaran dapat dibatalkan, sedangkan transaksi pembayaran diperbaiki melalui status menunggu/terverifikasi/ditolak dan audit trail.

## Hak akses

Peran awal: Super Admin, Direktur, Manajer, Keuangan/Bendahara, Anggota/Operator, dan Auditor. Hak akses ditegakkan per aksi pada controller dan dapat diubah per peran maupun dikecualikan per pengguna.

## Aturan integritas

- Tanggal selesai event tidak boleh sebelum tanggal mulai.
- Desa registrasi wajib berada pada salah satu kabupaten event.
- Rekening asal dan tujuan transfer wajib berbeda.
- Posting finansial dilakukan dalam transaksi database.
- ID wilayah diperlakukan sebagai string opaque, bukan angka.
- Transaksi finansial memiliki audit trail dan tidak dihapus sebagai cara koreksi.
- Pembayaran hutang menggunakan transaksi pengeluaran dan jurnal yang sama agar saldo tidak dihitung dua kali.
- Tagihan registrasi dan komponen biaya peserta disimpan sebagai snapshot agar riwayat finansial tidak berubah ketika data event ditampilkan kembali.
