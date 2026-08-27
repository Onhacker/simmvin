<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="card card-style bg-11" data-card-height="160">
    <div class="card-bottom ps-3 pb-3">
        <h1 class="font-17 color-white mb-2">Ringkasan Pelatihan</h1>
        <h4 class="font-13 color-white mb-4 opacity-50">Data otomatis dari event yang sedang aktif</h4>
        <div class="d-flex">
            <div class="pe-3">
                <h5 class="color-white"><?= number_format($stats['events']) ?></h5>
                <h6 class="color-white opacity-60">Event aktif</h6>
            </div>
            <div class="pe-3">
                <h5 class="color-white"><?= number_format($stats['participants']) ?></h5>
                <h6 class="color-white opacity-60">Peserta</h6>
            </div>
            <div class="ms-auto text-end pe-3">
                <h5 class="color-white"><?= rupiah($stats['income']) ?></h5>
                <h6 class="color-white opacity-60">Dana masuk</h6>
            </div>
        </div>
    </div>
    <div class="card-overlay bg-black opacity-80"></div>
</div>

<div class="content mb-0">
    <div class="row mb-0">
        <div class="col-6 pe-1">
            <div class="card card-style mx-0 mb-2 p-3">
                <h6 class="font-14">Event Aktif</h6>
                <h3 class="color-blue-dark mb-0"><?= number_format($stats['events']) ?></h3>
            </div>
        </div>
        <div class="col-6 ps-1">
            <div class="card card-style mx-0 mb-2 p-3">
                <h6 class="font-14">Desa Terdaftar</h6>
                <h3 class="color-brown-dark mb-0"><?= number_format($stats['villages']) ?></h3>
            </div>
        </div>
        <div class="col-6 pe-1">
            <div class="card card-style mx-0 p-3">
                <h6 class="font-14">Total Peserta</h6>
                <h3 class="color-magenta-dark mb-0"><?= number_format($stats['participants']) ?></h3>
            </div>
        </div>
        <div class="col-6 ps-1">
            <div class="card card-style mx-0 p-3">
                <h6 class="font-14">Pengeluaran</h6>
                <h3 class="color-red-dark mb-0 simp-balance-value"><?= rupiah($stats['expenses']) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card card-style">
    <div class="content mb-2">
        <div class="d-flex align-items-start"><div><p class="font-600 color-highlight mb-n1">Data event aktif</p><h2 class="font-22 mb-1">Ringkasan Registrasi</h2><p class="font-12 opacity-60 mb-0">Rekap desa, peserta, dan pembayaran.</p></div><span class="icon icon-s rounded-xl bg-blue-light color-blue-dark ms-auto"><i class="fa fa-users"></i></span></div>
        <div class="divider mt-3 mb-2"></div>
        <?php $summaryRows = array(array('home','Desa',number_format($registrationSummary['villages']),'color-blue-dark'),array('users','Peserta',number_format($registrationSummary['participants']),'color-highlight'),array('check-circle','Terbayar',rupiah($registrationSummary['total_paid']),'color-green-dark'),array('clock','Sisa Tagihan',rupiah($registrationSummary['remaining']),$registrationSummary['remaining_cents'] > 0 ? 'color-yellow-dark' : 'color-green-dark')); ?>
        <?php foreach ($summaryRows as $summaryIndex => $summaryRow): ?><div class="d-flex align-items-center py-2 <?= $summaryIndex + 1 < count($summaryRows) ? 'border-bottom' : '' ?>"><span class="icon icon-s rounded-xl bg-theme <?= $summaryRow[3] ?> me-3"><i class="fa fa-<?= $summaryRow[0] ?>"></i></span><span class="font-12 opacity-60"><?= e($summaryRow[1]) ?></span><strong class="font-14 <?= $summaryRow[3] ?> ms-auto text-end simp-balance-value"><?= $summaryRow[2] ?></strong></div><?php endforeach; ?>
    </div>
</div>

<div class="content mt-0 mb-0">
<div class="row mb-0">
    <div class="col-lg-7">
        <div class="card card-style mx-0">
            <div class="content mb-0">
                <div class="simp-divider-title">
                    <h5 class="font-14 opacity-50 mb-0">Event Pelatihan Aktif</h5>
                    <?php if ($this->Auth_model->can('events.create')): ?>
                        <a href="<?= site_url('event/tambah') ?>" class="btn btn-xxs rounded-s font-700 bg-highlight color-white"><i class="fa fa-plus me-1"></i> Event</a>
                    <?php elseif ($this->Auth_model->can('events.view')): ?>
                        <a href="<?= site_url('event') ?>" class="color-highlight font-12">Lihat Semua</a>
                    <?php endif; ?>
                </div>
                <div class="divider mb-3 mt-3"></div>
                <?php if (!$latestEvents): ?>
                    <div class="d-flex align-items-center py-4">
                        <span class="icon icon-m rounded-xl bg-blue-light color-blue-dark me-3"><i class="fa fa-calendar-times"></i></span>
                        <div><h5 class="font-14 mb-0">Belum ada event aktif</h5><p class="font-11 opacity-60 mb-0">Aktifkan event untuk menampilkan ringkasan operasional.</p></div>
                    </div>
                <?php else: ?>
                    <div class="row mb-0">
                        <?php $dashboardEventBackgrounds = array(34, 20, 17, 11); ?>
                        <?php foreach ($latestEvents as $eventIndex => $event): ?>
                            <div class="col-12 col-md-6">
                                <a href="<?= site_url('event/' . $event['id']) ?>" class="card card-style mx-0 mb-3 bg-<?= $dashboardEventBackgrounds[$eventIndex % count($dashboardEventBackgrounds)] ?> default-link" data-card-height="220">
                                    <div class="card-top ps-3 pt-3"><?= status_badge($event['status']) ?></div>
                                    <div class="card-bottom ps-3 pe-3 pb-3">
                                        <p class="color-white opacity-70 font-600 font-10 mb-n1"><?= e($event['code']) ?> · <?= tanggal_id($event['start_date']) ?></p>
                                        <h3 class="color-white font-18 font-800 mb-1"><?= e($event['name']) ?></h3>
                                        <p class="color-white font-11 mb-2"><i class="fa fa-map-marker-alt icon-20"></i><?= e($event['location']) ?></p>
                                        <div class="d-flex color-white font-10 opacity-80">
                                            <span><i class="fa fa-map icon-20"></i><?= number_format($event['regency_count']) ?> kab/kota</span>
                                            <span class="ms-auto"><i class="fa fa-home icon-20"></i><?= number_format($event['village_count']) ?> desa</span>
                                        </div>
                                    </div>
                                    <div class="card-overlay bg-black opacity-70"></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card card-style mx-0">
            <div class="content">
                <div class="simp-divider-title">
                    <h5 class="font-14 opacity-50 mb-0">Saldo Kas & Rekening</h5>
                    <?php if ($this->Auth_model->can('accounts.view')): ?><a href="<?= site_url('akun-dana') ?>" class="color-highlight font-12">Detail</a><?php endif; ?>
                </div>
                <div class="divider mb-3 mt-3"></div>
                <?php if (!$accounts): ?><p class="text-center opacity-50 py-4">Belum ada akun dana.</p><?php endif; ?>
                <?php foreach ($accounts as $account): ?>
                    <div class="d-flex mb-3 align-items-center">
                        <div class="me-3">
                            <span class="icon icon-m rounded-m <?= $account['type'] === 'cash' ? 'gradient-green' : ($account['type'] === 'qris' ? 'gradient-magenta' : 'gradient-blue') ?> color-white">
                                <i class="fas <?= $account['type'] === 'cash' ? 'fa-money-bill-wave' : ($account['type'] === 'qris' ? 'fa-qrcode' : 'fa-university') ?>"></i>
                            </span>
                        </div>
                        <div class="min-width-zero">
                            <h6 class="mb-n1"><?= e($account['name']) ?></h6>
                            <p class="font-11 mb-0 opacity-50"><?= e($account['bank_name'] ?: ucfirst($account['type'])) ?></p>
                        </div>
                        <strong class="ms-auto ps-2 text-end simp-balance-value"><?= rupiah($account['balance']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="divider mt-2 mb-3"></div>
                <div class="row mb-0">
                    <div class="col-6">
                        <p class="font-11 mb-0">Total Pemasukan</p>
                        <h5 class="color-green-dark simp-balance-value"><?= rupiah($financeStats['income']) ?></h5>
                    </div>
                    <div class="col-6 text-end">
                        <p class="font-11 mb-0">Total Pengeluaran</p>
                        <h5 class="color-red-dark simp-balance-value"><?= rupiah($financeStats['outgoing']) ?></h5>
                    </div>
                </div>
                <div class="divider mt-2 mb-3"></div>
                <?php
                $includedBalance = isset($financeStats['included_balance']) ? $financeStats['included_balance'] : 0;
                $includedBalanceCents = isset($financeStats['included_balance_cents']) ? (int)$financeStats['included_balance_cents'] : 0;
                ?>
                <div class="dashboard-balance-card rounded-s bg-blue-light px-3 py-3">
                    <div class="d-flex align-items-center">
                        <span class="icon icon-s rounded-xl bg-blue-dark color-white me-3 flex-shrink-0"><i class="fas fa-balance-scale"></i></span>
                        <div class="min-width-zero">
                            <p class="font-11 color-blue-dark font-600 mb-n1">Sisa Dana</p>
                            <p class="font-10 opacity-70 mb-0">Saldo tersedia saat ini</p>
                        </div>
                    </div>
                    <div class="dashboard-balance-amount <?= $includedBalanceCents >= 0 ? 'color-green-dark' : 'color-red-dark' ?> text-end mt-2 simp-balance-value"><?= rupiah($includedBalance) ?></div>
                    <p class="font-10 opacity-70 mb-0 mt-2">Jumlah saldo akun yang ditandai <strong>Masuk Total</strong>. Sudah termasuk saldo awal dan seluruh mutasi.</p>
                </div>
            </div>
        </div>
        <?php if ($this->Auth_model->can('debts.view')): ?>
            <div class="card card-style mx-0">
                <div class="content mb-2">
                    <div class="d-flex align-items-start"><div><p class="font-600 color-highlight mb-n1">Kewajiban perusahaan</p><h3 class="mb-1">Hutang Berjalan</h3><p class="font-11 opacity-60 mb-0"><?= number_format((int)($debtSummary['open_count'] ?? 0)) ?> hutang belum lunas</p></div><span class="icon icon-s rounded-xl bg-red-light color-red-dark ms-auto"><i class="fa fa-file-invoice-dollar"></i></span></div>
                    <div class="divider mt-3 mb-2"></div>
                    <div class="d-flex py-2 border-bottom"><span class="opacity-60">Total pokok</span><strong class="ms-auto simp-balance-value"><?= rupiah($debtSummary['total_principal'] ?? 0) ?></strong></div>
                    <div class="d-flex py-2 border-bottom"><span class="opacity-60">Sudah dibayar</span><strong class="ms-auto color-green-dark simp-balance-value"><?= rupiah($debtSummary['total_paid'] ?? 0) ?></strong></div>
                    <div class="d-flex py-2"><span class="font-600">Sisa hutang</span><strong class="ms-auto color-red-dark simp-balance-value"><?= rupiah($debtSummary['total_outstanding'] ?? 0) ?></strong></div>
                    <?php if ($this->Auth_model->can('debts.view')): ?><a href="<?= site_url('hutang') ?>" class="btn btn-full btn-m border-red-dark color-red-dark rounded-s font-600 mt-3"><i class="fa fa-arrow-right me-1"></i> Buka Modul Hutang</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
