<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$summary = isset($report['summary']) ? $report['summary'] : array();
?>

<div id="payment-data-results" aria-live="polite">
    <div class="card card-style">
        <div class="content mb-2">
            <h2 class="mb-0">Ringkasan Pembayaran</h2>
            <p class="font-12 color-blue-dark font-600 mb-2 text-break"><?= e($filterScope) ?></p>
            <?php
            $summaryItems = array(
                array('building', 'Jumlah Desa', number_format((int)($summary['villages'] ?? $summary['registrations'] ?? 0)), 'color-blue-dark'),
                array('users', 'Jumlah Peserta', number_format((int)($summary['participants'] ?? 0)) . ' orang', 'color-magenta-dark'),
                array('file-invoice-dollar', 'Total Tagihan', rupiah($summary['total_due'] ?? 0), 'color-brown-dark'),
                array('check-circle', 'Terverifikasi', rupiah($summary['verified'] ?? 0), 'color-green-dark'),
                array('clock', 'Menunggu Verifikasi', rupiah($summary['pending'] ?? 0), 'color-orange-dark'),
                array('hourglass-half', 'Sisa Tagihan', rupiah($summary['outstanding'] ?? 0), simp_money_cents($summary['outstanding'] ?? 0) > 0 ? 'color-red-dark' : 'color-green-dark')
            );
            ?>
            <?php foreach ($summaryItems as $index => $item): ?>
                <div class="d-flex align-items-center py-3 <?= $index + 1 < count($summaryItems) ? 'border-bottom' : '' ?>">
                    <span class="icon icon-s rounded-xl bg-blue-light color-blue-dark me-3"><i class="fa fa-<?= $item[0] ?>"></i></span>
                    <span class="font-13 color-theme"><?= e($item[1]) ?></span>
                    <strong class="font-15 <?= $item[3] ?> ms-auto text-end simp-balance-value"><?= $item[2] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card-style">
        <div class="content mb-2">
            <h2 class="mb-2">Metode Terverifikasi</h2>
            <?php $methods = array(array('money-bill-wave','Tunai',$summary['cash_total'] ?? 0,'color-green-dark'),array('university','Transfer',$summary['transfer_total'] ?? 0,'color-blue-dark'),array('qrcode','QRIS',$summary['qris_total'] ?? 0,'color-magenta-dark')); ?>
            <?php foreach ($methods as $index => $method): ?>
                <div class="d-flex align-items-center py-3 <?= $index + 1 < count($methods) ? 'border-bottom' : '' ?>"><i class="fa fa-<?= $method[0] ?> color-highlight icon-30"></i><span class="font-13 color-theme"><?= e($method[1]) ?></span><strong class="font-15 <?= $method[3] ?> ms-auto simp-balance-value"><?= rupiah($method[2]) ?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>

</div>
