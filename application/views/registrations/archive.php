<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$event = isset($event) && is_array($event) ? $event : array();
$registrations = isset($registrations) && is_array($registrations) ? $registrations : array();
$isReadOnly = !empty($isReadOnly);
$canPrint = $this->Auth_model->can('registrations.view');
$activeVillageCount = 0;
$activeParticipantCount = 0;
$verifiedTotalCents = 0;
$pendingTotalCents = 0;
foreach ($registrations as $archiveRow) {
    if (($archiveRow['status'] ?? '') === 'active') {
        $activeVillageCount++;
        $activeParticipantCount += (int) ($archiveRow['participant_count'] ?? 0);
    }
    $verifiedCents = simp_money_cents($archiveRow['paid_amount'] ?? '0');
    $pendingCents = simp_money_cents($archiveRow['pending_amount'] ?? '0');
    $verifiedTotalCents += $verifiedCents === NULL ? 0 : $verifiedCents;
    $pendingTotalCents += $pendingCents === NULL ? 0 : $pendingCents;
}
$verifiedTotal = simp_money_from_cents($verifiedTotalCents);
$pendingTotal = simp_money_from_cents($pendingTotalCents);
$status = isset($event['status']) ? (string) $event['status'] : 'closed';
$statusLabel = array('draft' => 'Draft', 'open' => 'Aktif', 'closed' => 'Ditutup', 'cancelled' => 'Dibatalkan');
$hasPrintableParticipants = $activeParticipantCount > 0;
?>

<div id="registration-archive-content">
    <div class="card card-style">
        <div class="content mb-3">
            <div class="d-flex align-items-start gap-3">
                <div class="min-width-zero pe-2">
                    <p class="font-600 color-highlight mb-n1">Arsip event</p>
                    <h2 class="font-22 mb-1 text-break"><?= e($event['name'] ?? 'Event') ?></h2>
                    <p class="font-12 opacity-70 mb-1"><?= e($event['code'] ?? '-') ?> · <?= e(($event['location'] ?? '') ?: '-') ?></p>
                    <p class="font-12 opacity-70 mb-0"><?= tanggal_id($event['start_date'] ?? '') ?> s.d. <?= tanggal_id($event['end_date'] ?? '') ?></p>
                </div>
                <div class="ms-auto text-end flex-shrink-0">
                    <?= status_badge($status) ?>
                    <?php if ($canPrint && $hasPrintableParticipants): ?>
                        <button type="button" class="btn btn-s border-blue-dark color-blue-dark rounded-s font-600 font-12 d-block mt-2" data-report-preview-open="registration-archive-print-modal"><i class="fa fa-print me-1"></i>Cetak</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isReadOnly): ?>
                <div class="d-flex align-items-start rounded-s bg-gray-light px-3 py-3 mt-3 mb-0">
                    <i class="fa fa-lock color-gray-dark font-18 me-3 mt-1"></i>
                    <div><strong class="font-13">Mode baca saja</strong><p class="font-11 opacity-70 mb-0">Event tidak aktif. Data peserta dan transaksi tetap dapat dilihat serta dicetak tanpa membuka kembali event.</p></div>
                </div>
            <?php endif; ?>
            <?php if ($canPrint && !$hasPrintableParticipants): ?>
                <div class="d-flex align-items-start rounded-s bg-yellow-light px-3 py-3 mt-3 mb-0">
                    <i class="fa fa-print color-yellow-dark font-18 me-3 mt-1"></i>
                    <div><strong class="font-13">Belum dapat dicetak</strong><p class="font-11 opacity-70 mb-0">Event ini belum memiliki peserta aktif untuk dimasukkan ke daftar hadir, PDF, atau Excel mailing.</p></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="content mt-0 mb-0"><div class="row mb-0">
        <div class="col-12 col-sm-6"><div class="card card-style mx-0 mb-2 p-3"><p class="font-11 opacity-60 mb-1">Desa aktif</p><h3 class="font-20 mb-0 color-blue-dark"><?= number_format($activeVillageCount) ?></h3></div></div>
        <div class="col-12 col-sm-6"><div class="card card-style mx-0 mb-2 p-3"><p class="font-11 opacity-60 mb-1">Peserta aktif</p><h3 class="font-20 mb-0 color-blue-dark"><?= number_format($activeParticipantCount) ?></h3></div></div>
        <div class="col-12 col-sm-6"><div class="card card-style mx-0 mb-2 p-3"><p class="font-11 opacity-60 mb-1">Terverifikasi</p><h4 class="font-16 mb-0 color-green-dark simp-balance-value"><?= rupiah($verifiedTotal) ?></h4></div></div>
        <div class="col-12 col-sm-6"><div class="card card-style mx-0 mb-2 p-3"><p class="font-11 opacity-60 mb-1">Menunggu</p><h4 class="font-16 mb-0 color-yellow-dark simp-balance-value"><?= rupiah($pendingTotal) ?></h4></div></div>
    </div></div>

    <div class="content mt-4 mb-0">
        <div class="d-flex align-items-center mb-3"><div><p class="font-600 color-highlight mb-n1">Riwayat desa</p><h3 class="font-20 mb-0">Registrasi Event</h3></div><span class="badge bg-blue-dark color-white ms-auto"><?= number_format(count($registrations)) ?> desa</span></div>
        <?php if (!$registrations): ?>
            <div class="card card-style mx-0"><div class="content text-center py-5"><span class="icon icon-l rounded-xl bg-blue-light color-blue-dark mb-3"><i class="fa fa-inbox"></i></span><h4 class="font-18">Belum ada registrasi</h4><p class="font-12 opacity-60 mb-0">Belum ada desa yang tersimpan pada event ini.</p></div></div>
        <?php else: ?>
            <?php foreach ($registrations as $row): ?>
                <?php
                $verified = simp_money_cents($row['paid_amount'] ?? '0');
                $pending = simp_money_cents($row['pending_amount'] ?? '0');
                $committed = simp_money_cents($row['committed_amount'] ?? '0');
                $expected = simp_money_cents($row['expected_amount'] ?? '0');
                $verified = $verified === NULL ? 0 : $verified;
                $pending = $pending === NULL ? 0 : $pending;
                $committed = $committed === NULL ? $verified + $pending : $committed;
                $expected = $expected === NULL ? 0 : $expected;
                $remaining = max(0, $expected - $committed);
                $paymentState = payment_status(simp_money_from_cents($committed), $row['expected_amount'] ?? '0');
                $registrationStatus = (string) ($row['status'] ?? 'active');
                ?>
                <div class="card card-style mx-0 mb-3"><div class="content mb-3">
                    <div class="d-flex align-items-start"><span class="icon icon-m rounded-xl <?= $registrationStatus === 'active' ? 'gradient-blue' : 'bg-gray-dark' ?> color-white shadow-s me-3 flex-shrink-0"><i class="fa fa-building"></i></span><div class="min-width-zero me-2"><p class="font-10 color-highlight text-uppercase font-600 mb-n1"><?= e($row['district_name'] ?? '-') ?></p><h3 class="font-19 mb-0 text-break"><?= e($row['village_name'] ?? '-') ?></h3><p class="font-11 opacity-60 mb-0 text-break"><?= e($row['regency_name'] ?? '-') ?></p></div><div class="ms-auto flex-shrink-0 text-end"><?= status_badge($registrationStatus) ?><?php if ($registrationStatus === 'active'): ?><div class="mt-1"><?= status_badge($paymentState) ?></div><?php endif; ?></div></div>
                    <div class="divider mt-3 mb-2"></div>
                    <div class="d-flex py-2 border-bottom"><span class="font-12 opacity-60"><i class="fa fa-users color-highlight icon-20"></i> Peserta aktif</span><strong class="font-13 ms-auto"><?= number_format((int) ($row['participant_count'] ?? 0)) ?> orang</strong></div>
                    <div class="d-flex py-2 border-bottom"><span class="font-12 opacity-60"><i class="fa fa-file-invoice-dollar color-highlight icon-20"></i> <?= $registrationStatus === 'cancelled' ? 'Tagihan arsip' : 'Tagihan' ?></span><strong class="font-13 ms-auto simp-balance-value"><?= rupiah($row['expected_amount'] ?? '0') ?></strong></div>
                    <div class="d-flex py-2 border-bottom"><span class="font-12 opacity-60">Terverifikasi</span><strong class="font-13 color-green-dark ms-auto simp-balance-value"><?= rupiah(simp_money_from_cents($verified)) ?></strong></div>
                    <?php if ($pending > 0): ?><div class="d-flex py-2 border-bottom"><span class="font-12 opacity-60">Menunggu verifikasi</span><strong class="font-13 color-yellow-dark ms-auto simp-balance-value"><?= rupiah(simp_money_from_cents($pending)) ?></strong></div><?php endif; ?>
                    <?php if ($registrationStatus === 'cancelled'): ?><div class="d-flex py-2"><span class="font-12 opacity-60">Status penagihan</span><strong class="font-13 color-red-dark ms-auto">Dibatalkan</strong></div><?php else: ?><div class="d-flex py-2"><span class="font-12 opacity-60">Sisa komitmen</span><strong class="font-13 <?= $remaining > 0 ? 'color-yellow-dark' : 'color-green-dark' ?> ms-auto simp-balance-value"><?= rupiah(simp_money_from_cents($remaining)) ?></strong></div><?php endif; ?>
                    <?php if ($registrationStatus === 'cancelled'): ?><div class="rounded-s bg-red-light px-3 py-2 mt-2"><p class="font-11 color-red-dark mb-1"><strong>Alasan batal:</strong> <?= e(!empty($row['cancellation_reason']) ? $row['cancellation_reason'] : 'Tidak tercatat') ?></p><p class="font-10 opacity-70 mb-0"><?= e(!empty($row['canceller_name']) ? $row['canceller_name'] : 'Pengguna tidak tersedia') ?><?php if (!empty($row['cancelled_at'])): ?> · <?= e(tanggal_id($row['cancelled_at'], TRUE)) ?><?php endif; ?></p></div><?php endif; ?>
                    <a class="btn btn-full btn-m <?= $registrationStatus === 'active' && !$isReadOnly ? 'gradient-highlight' : 'border-blue-dark color-blue-dark' ?> rounded-s font-600 font-12 mt-3" href="<?= site_url('registrasi/'.(int)$row['id']) ?>">Lihat Detail <i class="fa fa-arrow-right ms-1"></i></a>
                </div></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="content mt-1 mb-4"><a href="<?= site_url('event/'.(int)($event['id'] ?? 0)) ?>" class="btn btn-full btn-m bg-theme color-theme border rounded-s font-600"><i class="fa fa-arrow-left me-1"></i>Kembali ke Event</a></div>
</div>

<?php if ($canPrint && $hasPrintableParticipants): ?>
    <?php $this->load->view('reports/print_modal', array(
        'printModalId' => 'registration-archive-print-modal',
        'printModalTitle' => 'Daftar Peserta · '.($event['name'] ?? 'Event'),
        'printPreviewUrl' => site_url('event/'.(int)($event['id'] ?? 0).'/registrasi/cetak'),
        'printPdfUrl' => site_url('event/'.(int)($event['id'] ?? 0).'/registrasi/pdf'),
        'printExcelUrl' => site_url('event/'.(int)($event['id'] ?? 0).'/registrasi/excel'),
        'printExcelLabel' => 'Excel Mailing'
    )); ?>
<?php endif; ?>
