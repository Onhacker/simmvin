<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="card card-style d-print-none">
    <div class="content mb-0">
        <div class="d-flex align-items-center mb-3">
            <div>
                <p class="font-600 color-highlight mb-n1">Arus dana terverifikasi</p>
                <h2 class="mb-0">Periode Laporan</h2>
            </div>
            <button class="btn btn-s font-13 font-600 bg-theme color-theme border rounded-s ms-auto" type="button" onclick="window.print()">
                <i class="fas fa-print me-1 color-highlight"></i> Cetak
            </button>
        </div>

        <form method="get">
            <div class="row mb-0">
                <div class="col-md-4">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="finance-filter-from" type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                        <label for="finance-filter-from" class="color-highlight font-12 font-500">Dari Tanggal</label>
                        <i class="fa fa-check disabled valid me-4 pe-3 font-12 color-green-dark"></i>
                        <i class="fa fa-times disabled invalid me-4 pe-3 font-12 color-red-dark"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="finance-filter-to" type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                        <label for="finance-filter-to" class="color-highlight font-12 font-500">Sampai Tanggal</label>
                        <i class="fa fa-check disabled valid me-4 pe-3 font-12 color-green-dark"></i>
                        <i class="fa fa-times disabled invalid me-4 pe-3 font-12 color-red-dark"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-full btn-m font-13 font-600 gradient-highlight rounded-s mb-4" type="submit">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="content mb-0 mt-n2">
    <div class="row mb-0">
        <div class="col-xl-3 col-6">
            <div class="card card-style mx-0 mb-3 p-3">
                <i class="fas fa-arrow-down color-green-dark font-24 mb-2"></i>
                <p class="font-12 opacity-60 mb-n1">Pemasukan akun masuk total</p>
                <h3 class="color-green-dark mb-0 font-18"><?= rupiah($report['income']) ?></h3>
            </div>
        </div>
        <div class="col-xl-3 col-6">
            <div class="card card-style mx-0 mb-3 p-3">
                <i class="fas fa-arrow-up color-red-dark font-24 mb-2"></i>
                <p class="font-12 opacity-60 mb-n1">Pengeluaran akun masuk total</p>
                <h3 class="color-red-dark mb-0 font-18"><?= rupiah($report['expenses']) ?></h3>
            </div>
        </div>
        <div class="col-xl-3 col-6">
            <div class="card card-style mx-0 mb-3 p-3">
                <i class="fas fa-percentage color-blue-dark font-24 mb-2"></i>
                <p class="font-12 opacity-60 mb-n1">Biaya transfer dari akun masuk total</p>
                <h3 class="color-blue-dark mb-0 font-18"><?= rupiah($report['transfer_fees']) ?></h3>
            </div>
        </div>
        <div class="col-xl-3 col-6">
            <div class="card card-style mx-0 mb-3 p-3">
                <i class="fas fa-balance-scale <?= (int)$report['net_cents'] >= 0 ? 'color-green-dark' : 'color-red-dark' ?> font-24 mb-2"></i>
                <p class="font-12 opacity-60 mb-n1">Net transaksi</p>
                <h3 class="mb-0 font-18 <?= (int)$report['net_cents'] >= 0 ? 'color-green-dark' : 'color-red-dark' ?>"><?= rupiah($report['net']) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card card-style mx-3 mb-3 bg-blue-light shadow-0">
    <div class="content py-2 mb-0">
        <div class="d-flex flex-wrap align-items-center">
            <span class="font-11 color-blue-dark me-3"><i class="fas fa-info-circle me-1"></i>Net transaksi sudah memperhitungkan transfer lintas akun (masuk/keluar) dan tidak memasukkan saldo awal.</span>
            <span class="font-11 color-blue-dark ms-auto">Saldo awal periode: <strong><?= rupiah($report['period_opening_balance']) ?></strong> · Saldo akhir: <strong><?= rupiah($report['period_ending_balance']) ?></strong></span>
        </div>
        <?php if ((int)$report['transfer_in_cents'] > 0 || (int)$report['transfer_out_cents'] > 0): ?>
            <div class="font-10 color-blue-dark mt-2">Transfer masuk dari akun dikecualikan: <strong><?= rupiah($report['transfer_in']) ?></strong> · Transfer keluar ke akun dikecualikan: <strong><?= rupiah($report['transfer_out']) ?></strong></div>
        <?php endif; ?>
    </div>
</div>

<div class="card card-style">
    <div class="content mb-2">
        <div class="d-flex align-items-center mb-3">
            <div>
                <p class="font-600 color-highlight mb-n1">Saldo berdasarkan buku besar<?= $filters['date_to'] ? ' per ' . tanggal_id($filters['date_to']) : '' ?></p>
                <h2 class="mb-0">Posisi Kas &amp; Rekening</h2>
            </div>
            <div class="text-end ms-auto d-none d-sm-block">
                <p class="font-11 opacity-60 mb-n1">Total dihitung</p>
                <h4 class="color-blue-dark mb-0"><?= rupiah($report['included_balance']) ?></h4>
            </div>
        </div>

        <div class="card bg-blue-dark rounded-s shadow-0 d-sm-none mb-3">
            <div class="content my-3">
                <p class="color-white opacity-70 font-11 mb-n1">Total saldo dihitung</p>
                <h3 class="color-white mb-0"><?= rupiah($report['included_balance']) ?></h3>
            </div>
        </div>

        <?php if(!$report['accounts']): ?><div class="text-center py-5 opacity-60"><i class="fas fa-wallet d-block font-24 mb-2"></i>Belum ada akun dana.</div><?php endif; ?>
        <?php foreach($report['accounts'] as $account): ?>
            <div class="d-flex align-items-start py-3 border-bottom"><span class="icon icon-s rounded-xl <?= $account['type']==='cash'?'bg-green-light color-green-dark':($account['type']==='qris'?'bg-magenta-light color-magenta-dark':'bg-blue-light color-blue-dark') ?> me-3"><i class="fas <?= $account['type']==='cash'?'fa-money-bill-wave':($account['type']==='qris'?'fa-qrcode':'fa-university') ?>"></i></span><div class="min-width-zero"><h5 class="font-15 mb-n1"><?= e($account['name']) ?></h5><p class="font-11 opacity-60 mb-0"><?= e(ucfirst($account['type']).' · '.($account['bank_name']?:'-')) ?><?= $account['account_number']?' · '.e($account['account_number']):'' ?></p><p class="font-10 mt-1 mb-0"><?= (int)$account['include_in_total']?'<span class="badge bg-green-dark color-white">Masuk Total</span>':'<span class="badge bg-gray-dark color-white">Tidak Dihitung</span>' ?></p></div><strong class="color-blue-dark ms-auto text-end simp-balance-value"><?= rupiah($account['balance']) ?></strong></div>
        <?php endforeach; ?>
    </div>
</div>
