<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$summary = $report['summary'];
$activeEvents = isset($activeEvents) && is_array($activeEvents) ? $activeEvents : array();
$viewMode = $report['view'] === 'participant' ? 'participant' : 'village';
$ajaxPartial = !empty($ajaxPartial);
?>

<div id="income-report-content" data-income-report-content>
<?php if (!$activeEvents): ?>
    <div class="card card-style">
        <div class="content text-center py-4">
            <span class="icon icon-l rounded-xl bg-blue-light color-blue-dark mb-3"><i class="fa fa-calendar-times"></i></span>
            <h3>Belum Ada Event Aktif</h3>
            <p class="mb-0">Aktifkan event untuk menampilkan laporan pemasukan operasional.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card card-style d-print-none">
        <div class="content mb-3">
            <div class="d-flex align-items-start">
                <div class="min-width-zero pe-3">
                    <p class="font-600 color-highlight mb-n1"><?= count($activeEvents) === 1 ? 'Event Aktif Saat Ini' : 'Event Aktif' ?></p>
                    <?php if (count($activeEvents) === 1): ?>
                        <h4 class="mb-1"><?= e($activeEvents[0]['name']) ?></h4>
                        <p class="font-11 opacity-60 mb-0"><?= e($activeEvents[0]['code']) ?> · <?= tanggal_id($activeEvents[0]['start_date']) ?> s.d. <?= tanggal_id($activeEvents[0]['end_date']) ?></p>
                    <?php else: ?>
                        <h4 class="mb-1"><?= number_format(count($activeEvents)) ?> Event Sedang Aktif</h4>
                        <p class="font-11 opacity-60 mb-0">Pemasukan seluruh event aktif digabung otomatis.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (count($activeEvents) > 1): ?>
                <div class="divider mt-3 mb-3"></div>
                <?php foreach ($activeEvents as $eventIndex => $activeEvent): ?>
                    <div class="d-flex align-items-center py-2 <?= $eventIndex + 1 < count($activeEvents) ? 'border-bottom' : '' ?>"><i class="fa fa-calendar-check color-highlight icon-30"></i><span class="font-12"><?= e($activeEvent['name']) ?></span><small class="opacity-60 ms-auto"><?= e($activeEvent['code']) ?></small></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-style d-print-none">
        <div class="content mb-3">
            <p class="font-600 color-highlight mb-n1">Rincian pemasukan</p>
            <h2 class="mb-3">Tampilkan Berdasarkan</h2>
            <div class="row mb-0 gx-2">
                <div class="col-4 px-1"><a class="btn btn-full btn-s rounded-s font-600 <?= $viewMode === 'village' ? 'gradient-highlight color-white' : 'bg-theme color-theme border' ?>" href="<?= site_url('laporan/pemasukan?view=village') ?>" data-income-view="village" aria-pressed="<?= $viewMode === 'village' ? 'true' : 'false' ?>"><i class="fa fa-home me-1"></i> Per Desa</a></div>
                <div class="col-4 px-1"><a class="btn btn-full btn-s rounded-s font-600 <?= $viewMode === 'participant' ? 'gradient-highlight color-white' : 'bg-theme color-theme border' ?>" href="<?= site_url('laporan/pemasukan?view=participant') ?>" data-income-view="participant" aria-pressed="<?= $viewMode === 'participant' ? 'true' : 'false' ?>"><i class="fa fa-user me-1"></i> Per Peserta</a></div>
                <div class="col-4 px-1"><a class="btn btn-full btn-s rounded-s font-600 bg-theme color-theme border" href="<?= site_url('laporan/pemasukan/cetak') . '?view=' . rawurlencode($viewMode) ?>" data-income-print-trigger data-report-preview-open="income-print-modal"><i class="fas fa-print me-1 color-highlight"></i> Cetak</a></div>
            </div>
        </div>
    </div>

    <div class="card card-style">
        <div class="content mb-2">
            <p class="font-600 color-highlight mb-n1">Data event aktif</p>
            <h2>Ringkasan Pemasukan</h2>
            <?php $summaryItems=array(array('building','Jumlah Desa',number_format((int)$summary['villages']),'color-blue-dark'),array('users','Jumlah Peserta',number_format((int)$summary['participants']),'color-magenta-dark'),array('file-invoice-dollar','Total Tagihan',rupiah($summary['total_due']),'color-brown-dark'),array('arrow-down','Total Masuk (terverifikasi)',rupiah($summary['income']),'color-green-dark')); ?>
            <?php foreach ($summaryItems as $summaryIndex => $item): ?>
                <div class="d-flex align-items-center py-3 <?= $summaryIndex + 1 < count($summaryItems) ? 'border-bottom' : '' ?>"><span class="icon icon-s rounded-xl bg-blue-light color-blue-dark me-3"><i class="fa fa-<?= $item[0] ?>"></i></span><span class="font-12 opacity-60"><?= e($item[1]) ?></span><strong class="font-15 <?= $item[3] ?> ms-auto text-end simp-balance-value"><?= $item[2] ?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card-style">
        <div class="content mb-2">
            <p class="font-600 color-highlight mb-n1">Komposisi metode bayar</p>
            <h2>Rekap Dana Masuk</h2>
            <?php $outstandingCents=simp_money_cents($summary['outstanding']); $methodItems=array(array('money-bill-wave','Tunai terverifikasi',rupiah($summary['cash_total']),'color-green-dark'),array('university','Transfer terverifikasi',rupiah($summary['transfer_total']),'color-blue-dark'),array('qrcode','QRIS terverifikasi',rupiah($summary['qris_total']),'color-magenta-dark'),array('hourglass-half','Sisa tagihan setelah transaksi terverifikasi',rupiah($summary['outstanding']),($outstandingCents!==NULL&&$outstandingCents>0)?'color-red-dark':'color-green-dark')); ?>
            <?php foreach ($methodItems as $methodIndex => $item): ?>
                <div class="d-flex align-items-center py-3 <?= $methodIndex + 1 < count($methodItems) ? 'border-bottom' : '' ?>"><i class="fas fa-<?= $item[0] ?> color-highlight icon-30"></i><span class="font-12 opacity-60"><?= e($item[1]) ?></span><strong class="font-15 <?= $item[3] ?> ms-auto text-end simp-balance-value"><?= $item[2] ?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="content mb-0">
        <div class="d-flex align-items-center mb-3"><div><p class="font-600 color-highlight mb-n1">Termasuk yang belum membayar</p><h2 class="mb-0"><?= $viewMode === 'participant' ? 'Rincian Per Peserta' : 'Rincian Per Desa' ?></h2></div><span class="badge bg-blue-dark color-white ms-auto"><?= number_format(count($report['rows'])) ?> data</span></div>
        <?php if (!$report['rows']): ?><div class="card card-style mx-0"><div class="content text-center py-4"><span class="icon icon-l rounded-xl bg-blue-light color-blue-dark mb-3"><i class="fa fa-file-invoice-dollar"></i></span><h4>Belum Ada Registrasi</h4><p class="font-12 opacity-60 mb-0">Data event aktif akan tampil di sini.</p></div></div><?php endif; ?>
        <?php foreach ($report['rows'] as $row): ?>
            <?php
            $participantView = $viewMode === 'participant';
            $participantBilling = $row['billing_mode'] === 'per_participant';
            $villageLevelParticipant = $participantView && !$participantBilling;
            $villageExtraParticipant = $villageLevelParticipant && $row['billing_mode'] === 'per_village_extra';
            $participantExtraCents = $villageExtraParticipant ? simp_money_cents($row['due_amount']) : 0;
            if ($participantExtraCents === NULL) $participantExtraCents = 0;
            $participantExtraAmount = simp_money_from_cents($participantExtraCents);
            $isExtraParticipant = $villageExtraParticipant && $participantExtraCents > 0;
            $due = $villageLevelParticipant ? '0.00' : (simp_money_decimal($row['due_amount'], TRUE) ?: '0.00');
            $paid = $villageLevelParticipant ? '0.00' : (simp_money_decimal($row['paid'], TRUE) ?: '0.00');
            ?>
            <div class="card card-style mx-0 mb-3"><div class="content mb-3">
                <div class="d-flex align-items-start"><span class="icon icon-m rounded-xl gradient-blue color-white shadow-s me-3 flex-shrink-0"><i class="fa fa-<?= $viewMode === 'participant' ? 'user' : 'building' ?>"></i></span><div class="min-width-zero me-2"><p class="font-10 color-highlight text-uppercase font-600 mb-n1"><?= e($row['event_name']) ?></p><h3 class="font-20 mb-0"><?= e($viewMode === 'participant' ? $row['participant_name'] : $row['village_name']) ?></h3><?php if ($viewMode === 'participant'): ?><p class="font-11 opacity-60 mb-0"><?= e($row['position']) ?></p><?php endif; ?></div><div class="ms-auto flex-shrink-0"><?php if ($villageExtraParticipant): ?><span class="badge <?= $isExtraParticipant ? 'bg-yellow-dark' : 'bg-blue-dark' ?> color-white"><?= $isExtraParticipant ? 'Peserta Tambahan' : 'Termasuk Paket Desa' ?></span><?php elseif ($villageLevelParticipant): ?><span class="badge bg-blue-dark color-white">Dicatat di Desa</span><?php else: ?><?= status_badge(payment_status($paid,$due)) ?><?php endif; ?></div></div>
                <div class="divider mt-3 mb-2"></div>
                <?php if ($viewMode === 'participant'): ?><div class="d-flex align-items-center py-2 border-bottom"><i class="fa fa-home color-highlight icon-30"></i><span class="font-12 opacity-60">Desa</span><strong class="font-13 ms-auto text-end"><?= e($row['village_name']) ?></strong></div><?php else: ?><div class="d-flex align-items-center py-2 border-bottom"><i class="fa fa-map-marker-alt color-highlight icon-30"></i><span class="font-12 opacity-60">Wilayah</span><strong class="font-13 ms-auto text-end"><?= e($row['district_name'].', '.$row['regency_name']) ?></strong></div><div class="d-flex align-items-center py-2 border-bottom"><i class="fa fa-users color-highlight icon-30"></i><span class="font-12 opacity-60">Peserta</span><strong class="font-13 ms-auto"><?= number_format((int)$row['participant_count']) ?> orang</strong></div><?php endif; ?>
                <?php if (!$villageLevelParticipant): ?>
                    <?php $facts=array(array('Tagihan',rupiah($row['due_amount']),''),array('Tunai',rupiah($row['cash_total']),'color-green-dark'),array('Transfer',rupiah($row['transfer_total']),'color-blue-dark'),array('QRIS',rupiah($row['qris_total']),'color-magenta-dark'),array('Total Masuk',rupiah($row['paid']),'color-green-dark')); ?>
                    <?php foreach ($facts as $factIndex => $fact): ?><div class="d-flex align-items-center py-2 <?= $factIndex + 1 < count($facts) ? 'border-bottom' : '' ?>"><span class="font-12 opacity-60"><?= e($fact[0]) ?></span><strong class="font-13 <?= $fact[2] ?> ms-auto text-end simp-balance-value"><?= $fact[1] ?></strong></div><?php endforeach; ?>
                <?php elseif ($villageExtraParticipant): ?>
                    <div class="d-flex align-items-center py-2 border-bottom"><span class="font-12 opacity-60">Komponen biaya</span><strong class="font-13 ms-auto text-end simp-balance-value"><?= $isExtraParticipant ? rupiah($participantExtraAmount) : 'Termasuk paket' ?></strong></div>
                    <p class="font-12 opacity-60 mt-3 mb-0">Pembayaran paket desa dan peserta tambahan digabung pada transaksi desa.</p>
                <?php else: ?><p class="font-12 opacity-60 mt-3 mb-0">Pembayaran event ini dicatat pada tingkat desa.</p><?php endif; ?>
            </div></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>
<?php if ($activeEvents && !$ajaxPartial): ?>
    <?php $this->load->view('reports/print_modal', array(
        'printModalId' => 'income-print-modal',
        'printModalTitle' => 'Cetak Pemasukan',
        'printPreviewUrl' => site_url('laporan/pemasukan/cetak') . '?view=' . rawurlencode($viewMode),
        'printPdfUrl' => site_url('laporan/pemasukan/pdf') . '?view=' . rawurlencode($viewMode),
        'printExcelUrl' => site_url('laporan/pemasukan/excel') . '?view=' . rawurlencode($viewMode)
    )); ?>
<?php endif; ?>
