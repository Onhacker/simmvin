<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$registrations = isset($registrations) && is_array($registrations) ? $registrations : array();
$activeEvents = isset($activeEvents) && is_array($activeEvents) ? $activeEvents : array();
$positions = isset($positions) && is_array($positions) ? $positions : array();
$totalVillages = count($registrations);
$totalParticipants = 0;
foreach ($registrations as $registration) $totalParticipants += (int) $registration['participant_count'];
$canCreate = $this->Auth_model->can('registrations.create');
$canPrint = $this->Auth_model->can('registrations.view');
$positionPayload = array();
foreach ($positions as $position) {
    $positionPayload[] = array(
        'id' => (int) $position['id'],
        'name' => $position['name'],
        'category' => isset($position['category_label']) ? $position['category_label'] : (isset($position['category']) ? $position['category'] : 'Lainnya')
    );
}
?>

<div id="registration-index-content">
<?php if ($activeEvents): ?>
<div class="card card-style">
    <div class="content mb-3">
        <div class="d-flex align-items-center">
            <span class="icon icon-s rounded-xl bg-blue-light color-blue-dark me-3 flex-shrink-0"><i class="fa fa-calendar-check"></i></span>
            <div class="min-width-zero"><p class="font-11 opacity-60 mb-n1">Registrasi otomatis mengikuti</p><strong class="font-13"><?= number_format(count($activeEvents)) ?> event aktif</strong></div>
            <span class="badge bg-blue-dark color-white ms-auto"><?= number_format($totalVillages) ?> desa</span>
        </div>
        <?php if (($canPrint || $canCreate) && $activeEvents): ?>
            <div class="divider mt-3 mb-3"></div>
            <div class="registration-index-actions">
                <?php if ($canPrint): ?>
                    <a href="<?= site_url('registrasi/cetak') ?>" class="btn btn-m btn-full bg-theme color-theme border rounded-s font-600 shadow-0" data-report-preview-open="registration-print-modal"><i class="fa fa-print me-1 color-highlight"></i> Cetak Peserta</a>
                <?php endif; ?>
                <?php if ($canCreate): ?>
                    <button type="button" class="btn btn-m btn-full gradient-highlight rounded-s font-600 shadow-s" data-registration-add-open><i class="fa fa-plus me-1"></i> Tambah Peserta</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!$activeEvents): ?>
    <div class="card card-style"><div class="content text-center py-5"><span class="icon icon-l rounded-xl bg-blue-light color-blue-dark mb-3"><i class="fa fa-calendar-times"></i></span><h3 class="font-20">Belum Ada Event Aktif</h3><p class="font-12 opacity-60 mb-0">Aktifkan event terlebih dahulu untuk menambahkan registrasi peserta.</p></div></div>
<?php else: ?>
    <div class="content mb-0">
        <div class="d-flex align-items-center mb-3"><div><p class="font-600 color-highlight mb-n1">Desa dan peserta</p><h3 class="font-20 mb-0">Daftar Registrasi</h3></div><span class="badge bg-blue-dark color-white ms-auto"><?= number_format($totalParticipants) ?> peserta</span></div>
        <?php if (!$registrations): ?>
            <div class="card card-style mx-0"><div class="content text-center py-5"><span class="icon icon-l rounded-xl bg-blue-light color-blue-dark mb-3"><i class="fa fa-inbox"></i></span><h4 class="font-18">Belum Ada Registrasi</h4><p class="font-12 opacity-60 mb-3">Tambahkan desa dan peserta pada event aktif.</p><?php if ($canCreate): ?><button type="button" class="btn btn-m gradient-highlight rounded-s font-600 font-12" data-registration-add-open><i class="fa fa-plus me-1"></i> Tambah Registrasi</button><?php endif; ?></div></div>
        <?php endif; ?>
        <?php foreach ($registrations as $row): ?>
            <?php
            $verifiedAmountCents = simp_money_cents($row['paid_amount'] ?? '0');
            $pendingAmountCents = simp_money_cents($row['pending_amount'] ?? '0');
            if ($verifiedAmountCents === NULL) $verifiedAmountCents = 0;
            if ($pendingAmountCents === NULL) $pendingAmountCents = 0;
            $committedAmountCents = isset($row['committed_amount']) ? simp_money_cents($row['committed_amount']) : $verifiedAmountCents + $pendingAmountCents;
            if ($committedAmountCents === NULL) $committedAmountCents = $verifiedAmountCents + $pendingAmountCents;
            $expectedAmountCents = simp_money_cents($row['expected_amount']);
            if ($expectedAmountCents === NULL) $expectedAmountCents = 0;
            $remainingAmountCents = max(0, $expectedAmountCents - $committedAmountCents);
            $verifiedAmount = simp_money_from_cents($verifiedAmountCents);
            $pendingAmount = simp_money_from_cents($pendingAmountCents);
            $committedAmount = simp_money_from_cents($committedAmountCents);
            $remainingAmount = simp_money_from_cents($remainingAmountCents);
            $paymentStatus = payment_status($committedAmount, $row['expected_amount']);
            ?>
            <div class="card card-style mx-0 mb-3"><div class="content mb-3">
                <div class="d-flex align-items-start"><span class="icon icon-m rounded-xl gradient-blue color-white shadow-s me-3 flex-shrink-0"><i class="fa fa-building"></i></span><div class="min-width-zero me-2"><p class="font-10 color-highlight text-uppercase font-600 mb-n1"><?= e($row['district_name']) ?></p><h3 class="font-20 mb-0 text-break"><?= e($row['village_name']) ?></h3></div><div class="ms-auto flex-shrink-0"><?= status_badge($paymentStatus) ?></div></div>
                <div class="divider mt-3 mb-2"></div>
                <div class="d-flex py-2 border-bottom"><span class="font-12 opacity-60"><i class="fa fa-users color-highlight icon-20"></i> Peserta</span><strong class="font-13 ms-auto"><?= number_format((int) $row['participant_count']) ?> orang</strong></div>
                <div class="d-flex py-2 border-bottom"><span class="font-12 opacity-60"><i class="fa fa-file-invoice-dollar color-highlight icon-20"></i> Tagihan</span><strong class="font-13 ms-auto simp-balance-value"><?= rupiah($row['expected_amount']) ?></strong></div>
                <div class="d-flex py-2 border-bottom"><span class="font-12 opacity-60"><i class="fa fa-check-circle color-green-dark icon-20"></i> Terverifikasi</span><strong class="font-13 color-green-dark ms-auto simp-balance-value"><?= rupiah($verifiedAmount) ?></strong></div>
                <?php if ($pendingAmount > 0): ?><div class="d-flex py-2 border-bottom"><span class="font-12 opacity-60"><i class="fa fa-clock color-yellow-dark icon-20"></i> Menunggu verifikasi</span><strong class="font-13 color-yellow-dark ms-auto simp-balance-value"><?= rupiah($pendingAmount) ?></strong></div><?php endif; ?>
                <div class="d-flex py-2"><span class="font-12 opacity-60"><i class="fa fa-hourglass-half color-highlight icon-20"></i> Sisa setelah komitmen</span><strong class="font-13 <?= $remainingAmountCents > 0 ? 'color-yellow-dark' : 'color-green-dark' ?> ms-auto simp-balance-value"><?= rupiah($remainingAmount) ?></strong></div>
                <a class="btn btn-full btn-m gradient-highlight rounded-s font-600 font-12 mt-3" href="<?= site_url('registrasi/'.$row['id']) ?>">Lihat Detail <i class="fa fa-arrow-right ms-1"></i></a>
            </div></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<?php if ($canPrint && $activeEvents): ?>
    <?php $this->load->view('reports/print_modal', array(
        'printModalId' => 'registration-print-modal',
        'printModalTitle' => 'Daftar Registrasi Peserta',
        'printPreviewUrl' => site_url('registrasi/cetak'),
        'printPdfUrl' => site_url('registrasi/pdf'),
        'printExcelUrl' => site_url('registrasi/excel'),
        'printExcelLabel' => 'Excel Mailing',
        'printFormatLabel' => '',
        'printPaperNote' => '',
        'printIconOnly' => TRUE
    )); ?>
<?php endif; ?>

<?php if ($canCreate && $activeEvents): ?>
    <?php
    $eventPayload = array();
    foreach ($activeEvents as $event) {
        $eventPayload[] = array('id'=>(int)$event['id'],'name'=>$event['name'],'code'=>$event['code'],'billing_mode'=>$event['billing_mode'],'regencies'=>isset($event['regencies'])?$event['regencies']:array());
    }
    ?>
    <a id="registration-add-opener" href="#" class="d-none" data-menu="registration-add-modal" aria-hidden="true" tabindex="-1"></a>
    <div id="registration-add-modal" class="menu menu-box-modal rounded-m simp-full-form-modal" data-menu-width="980" data-menu-height="820" role="dialog" aria-modal="true" aria-labelledby="registration-add-title">
        <div class="content mb-0">
            <div class="d-flex align-items-start mb-3"><div class="min-width-zero pe-3"><p class="font-600 color-highlight mb-n1">Registrasi baru</p><h3 id="registration-add-title" class="font-20 mb-0">Tambah Peserta</h3></div><button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto flex-shrink-0" aria-label="Tutup"><i class="fa fa-times"></i></button></div>
            <form id="registration-add-form" method="post" action="<?= site_url('registrasi/ajax/tambah') ?>" data-events="<?= e(json_encode($eventPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>" data-positions="<?= e(json_encode($positionPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>" data-districts-url="<?= site_url('wilayah/kecamatan') ?>" data-villages-url="<?= site_url('wilayah/desa') ?>">
                <?= csrf_field() ?>
                <?php if (count($activeEvents) === 1): $onlyEvent = $activeEvents[0]; ?>
                    <input type="hidden" name="event_id" id="registration-add-event" value="<?= (int)$onlyEvent['id'] ?>"><div class="d-flex align-items-center rounded-s bg-blue-light px-3 py-2 mb-3"><span class="icon icon-s rounded-xl bg-blue-dark color-white me-3"><i class="fa fa-calendar-check"></i></span><div class="min-width-zero"><p class="font-10 color-blue-dark font-600 mb-n1">Event aktif</p><p class="font-12 font-600 mb-0 text-break"><?= e($onlyEvent['name']) ?></p></div></div>
                <?php else: ?>
                    <div class="input-style has-borders no-icon input-style-always-active mb-3"><label for="registration-add-event" class="color-highlight">Event aktif</label><select id="registration-add-event" name="event_id" required><option value="">Pilih event aktif</option><?php foreach ($activeEvents as $event): ?><option value="<?= (int)$event['id'] ?>"><?= e($event['name']) ?></option><?php endforeach; ?></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em></div>
                <?php endif; ?>
                <div class="row mb-0"><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active registration-cascade-select-wrap mb-3"><label for="registration-add-district" class="color-highlight">Kecamatan</label><select id="registration-add-district" class="registration-cascade-select" required disabled><option value="">Pilih event dahulu</option></select><i class="fa fa-check d-none disabled valid color-green-dark"></i><i class="fa fa-times d-none disabled invalid color-red-dark"></i><em>*</em></div></div><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active registration-cascade-select-wrap mb-3"><label for="registration-add-village" class="color-highlight">Desa</label><select id="registration-add-village" class="registration-cascade-select" name="village_id" required disabled><option value="">Pilih kecamatan dahulu</option></select><i class="fa fa-check d-none disabled valid color-green-dark"></i><i class="fa fa-times d-none disabled invalid color-red-dark"></i><em>*</em></div></div></div>
                <div class="d-flex align-items-center mb-2"><p class="font-600 color-highlight mb-0">Data peserta</p><button type="button" class="btn btn-xxs border-blue-dark color-blue-dark rounded-s ms-auto" data-add-modal-participant><i class="fa fa-plus me-1"></i> Peserta</button></div>
                <div id="registration-add-participants"></div>
                <button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600 mt-3" data-registration-add-submit><i class="fa fa-save me-1"></i> Simpan Registrasi</button>
            </form>
        </div>
    </div>
<?php endif; ?>
