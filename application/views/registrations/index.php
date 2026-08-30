<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$registrations = isset($registrations) && is_array($registrations) ? $registrations : array();
$activeEvents = isset($activeEvents) && is_array($activeEvents) ? $activeEvents : array();
$positions = isset($positions) && is_array($positions) ? $positions : array();
$totalRows = isset($totalRows) ? (int) $totalRows : count($registrations);
$perPage = max(1, isset($perPage) ? (int) $perPage : 20);
$totalPages = max(1, isset($totalPages) ? (int) $totalPages : (int) ceil($totalRows / $perPage));
$currentPage = max(1, isset($currentPage) ? (int) $currentPage : 1);
$totalVillages = $totalRows;
$hasTotalParticipants = isset($totalParticipants);
$totalParticipants = $hasTotalParticipants ? (int) $totalParticipants : 0;
if (!$hasTotalParticipants) foreach ($registrations as $registration) $totalParticipants += (int) $registration['participant_count'];
$canCreate = $this->Auth_model->can('registrations.create');
$canPrint = $this->Auth_model->can('registrations.view');
$canDelete = $this->Auth_model->can('registrations.edit');
$canRecordPayment = !empty($canRecordPayment);
$accounts = isset($accounts) && is_array($accounts) ? $accounts : array();
$positionPayload = array();
foreach ($positions as $position) {
    $positionPayload[] = array(
        'id' => (int) $position['id'],
        'name' => $position['name'],
        'category' => isset($position['category_label']) ? $position['category_label'] : (isset($position['category']) ? $position['category'] : 'Lainnya')
    );
}
$accountPayload = array();
foreach ($accounts as $account) $accountPayload[] = array('id'=>(int)$account['id'],'name'=>$account['name'],'type'=>$account['type']);
$registrationPageUrl = function ($page) {
    $page = max(1, (int) $page);
    return site_url('registrasi') . ($page > 1 ? '?page=' . $page : '');
};
$attendanceDefaultDate = date('Y-m-d');
$attendanceMinDate = '';
$attendanceMaxDate = '';
if ($activeEvents) {
    $firstEventStart = isset($activeEvents[0]['start_date']) ? trim((string) $activeEvents[0]['start_date']) : '';
    $firstEventEnd = isset($activeEvents[0]['end_date']) ? trim((string) $activeEvents[0]['end_date']) : '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstEventStart)) $attendanceDefaultDate = $firstEventStart;
    if (count($activeEvents) === 1) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstEventStart)) $attendanceMinDate = $firstEventStart;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstEventEnd)) $attendanceMaxDate = $firstEventEnd;
    }
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
                    <a href="#" class="btn btn-m btn-full bg-theme color-theme border rounded-s font-600 shadow-0" data-registration-attendance-open><i class="fa fa-clipboard-check me-1 color-highlight"></i> Cetak Absen</a>
                    <a href="<?= site_url('registrasi/peserta/cetak') ?>" class="btn btn-m btn-full bg-theme color-theme border rounded-s font-600 shadow-0" data-report-preview-open="registration-participant-print-modal"><i class="fa fa-users me-1 color-highlight"></i> Cetak Data Peserta</a>
                    <a href="<?= site_url('registrasi/desa/cetak') ?>" class="btn btn-m btn-full bg-theme color-theme border rounded-s font-600 shadow-0" data-report-preview-open="registration-village-print-modal"><i class="fa fa-building me-1 color-highlight"></i> Cetak Desa</a>
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
        <div class="d-flex align-items-center mb-3"><div><h3 class="font-20 mb-0">Data Registrasi</h3></div><span class="badge bg-blue-dark color-white ms-auto"><?= number_format($totalParticipants) ?> peserta</span></div>
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
            <div class="card card-style mx-0 mb-3 registration-card" data-registration-card-id="<?= (int) $row['id'] ?>"><div class="content mb-3">
                <div class="registration-card-details" aria-label="Rincian registrasi">
                    <div class="registration-card-detail-row"><span class="registration-card-label">Kecamatan</span><span class="registration-card-separator" aria-hidden="true">|</span><strong class="registration-card-value color-highlight"><?= e($row['district_name']) ?></strong></div>
                    <div class="registration-card-detail-row"><span class="registration-card-label">Desa</span><span class="registration-card-separator" aria-hidden="true">|</span><strong class="registration-card-value"><?= e($row['village_name']) ?></strong></div>
                    <div class="registration-card-detail-row"><span class="registration-card-label">Status</span><span class="registration-card-separator" aria-hidden="true">|</span><span class="registration-card-value"><?= status_badge($paymentStatus) ?></span></div>
                    <div class="registration-card-detail-row"><span class="registration-card-label">Peserta</span><span class="registration-card-separator" aria-hidden="true">|</span><strong class="registration-card-value"><?= number_format((int) $row['participant_count']) ?> orang</strong></div>
                    <div class="registration-card-detail-row"><span class="registration-card-label">Tagihan</span><span class="registration-card-separator" aria-hidden="true">|</span><strong class="registration-card-value simp-balance-value"><?= rupiah($row['expected_amount']) ?></strong></div>
                    <div class="registration-card-detail-row"><span class="registration-card-label">Terverifikasi</span><span class="registration-card-separator" aria-hidden="true">|</span><strong class="registration-card-value color-green-dark simp-balance-value"><?= rupiah($verifiedAmount) ?></strong></div>
                    <?php if ($pendingAmount > 0): ?><div class="registration-card-detail-row"><span class="registration-card-label">Menunggu verifikasi</span><span class="registration-card-separator" aria-hidden="true">|</span><strong class="registration-card-value color-yellow-dark simp-balance-value"><?= rupiah($pendingAmount) ?></strong></div><?php endif; ?>
                    <div class="registration-card-detail-row"><span class="registration-card-label">Sisa</span><span class="registration-card-separator" aria-hidden="true">|</span><strong class="registration-card-value <?= $remainingAmountCents > 0 ? 'color-yellow-dark' : 'color-green-dark' ?> simp-balance-value"><?= rupiah($remainingAmount) ?></strong></div>
                </div>
                <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-2">
                    <a class="btn btn-s gradient-highlight rounded-s font-600 font-11 px-3" href="<?= site_url('registrasi/'.$row['id']) ?>">Lihat Detail <i class="fa fa-arrow-right ms-1"></i></a>
                    <?php if ($canDelete): ?>
                        <button type="button" class="btn btn-s bg-theme color-red-dark border-red-dark rounded-s font-600 font-11 px-3" data-registration-delete-open data-registration-delete-url="<?= site_url('registrasi/'.(int)$row['id'].'/ajax/hapus') ?>" data-registration-delete-label="<?= e($row['village_name'].' · '.$row['district_name']) ?>" data-registration-delete-participants="<?= (int) $row['participant_count'] ?>"><i class="fa fa-trash-alt me-1"></i> Hapus</button>
                    <?php endif; ?>
                </div>
            </div></div>
        <?php endforeach; ?>
        <div id="registration-pagination" class="card card-style mx-0" data-registration-pagination>
            <div class="content py-2 mb-0">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <?php if ($currentPage > 1): ?>
                        <a class="btn btn-s bg-theme color-highlight border-highlight rounded-s simp-pagination-icon" href="<?= e($registrationPageUrl($currentPage - 1)) ?>" data-registration-page-link aria-label="Sebelumnya" title="Sebelumnya"><i class="fa fa-chevron-left" aria-hidden="true"></i></a>
                    <?php else: ?>
                        <button type="button" class="btn btn-s bg-gray-light color-gray-dark rounded-s simp-pagination-icon" disabled aria-label="Sebelumnya" title="Sebelumnya"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                    <?php endif; ?>
                    <span class="font-12 font-600 text-center opacity-70">Hal <?= number_format($currentPage) ?> dr <?= number_format($totalPages) ?></span>
                    <?php if ($currentPage < $totalPages): ?>
                        <a class="btn btn-s gradient-highlight rounded-s simp-pagination-icon" href="<?= e($registrationPageUrl($currentPage + 1)) ?>" data-registration-page-link aria-label="Berikutnya" title="Berikutnya"><i class="fa fa-chevron-right" aria-hidden="true"></i></a>
                    <?php else: ?>
                        <button type="button" class="btn btn-s bg-gray-light color-gray-dark rounded-s simp-pagination-icon" disabled aria-label="Berikutnya" title="Berikutnya"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>
                    <?php endif; ?>
                </div>
                <p class="font-11 opacity-60 text-center mb-0 mt-2">Menampilkan <?= $totalRows ? number_format((($currentPage - 1) * $perPage) + 1) : 0 ?>–<?= number_format(min($currentPage * $perPage, $totalRows)) ?> dari <?= number_format($totalRows) ?> data · maksimal <?= number_format($perPage) ?> per halaman</p>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<?php if ($canPrint && $activeEvents): ?>
    <a id="registration-attendance-date-opener" href="#" class="d-none" data-menu="registration-attendance-date-modal" aria-hidden="true" tabindex="-1"></a>
    <a id="registration-attendance-preview-trigger" href="<?= site_url('registrasi/absen/cetak') ?>" class="d-none" data-report-preview-open="registration-attendance-print-modal" aria-hidden="true" tabindex="-1"></a>
    <div id="registration-attendance-date-modal" class="menu menu-box-modal rounded-m" data-menu-width="390" data-menu-height="390" role="dialog" aria-modal="true" aria-labelledby="registration-attendance-date-title">
        <div class="content mb-0">
            <div class="d-flex align-items-start mb-3">
                <div class="min-width-zero pe-3"><p class="font-600 color-highlight mb-n1">Daftar hadir</p><h3 id="registration-attendance-date-title" class="font-20 mb-0">Pilih Tanggal Absen</h3></div>
                <button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto flex-shrink-0" aria-label="Tutup"><i class="fa fa-times"></i></button>
            </div>
            <p class="font-12 mb-3">Tanggal awal event memakai judul <strong>Data Registrasi</strong>. Tanggal berikutnya memakai judul <strong>Absen</strong>.</p>
            <form id="registration-attendance-date-form" data-preview-url="<?= site_url('registrasi/absen/cetak') ?>" data-pdf-url="<?= site_url('registrasi/absen/pdf') ?>" data-default-date="<?= e($attendanceDefaultDate) ?>">
                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="registration-attendance-date" class="color-highlight">Tanggal</label>
                    <input id="registration-attendance-date" name="attendance_date" type="date" value="<?= e($attendanceDefaultDate) ?>"<?= $attendanceMinDate !== '' ? ' min="' . e($attendanceMinDate) . '"' : '' ?><?= $attendanceMaxDate !== '' ? ' max="' . e($attendanceMaxDate) . '"' : '' ?> required>
                    <em>*</em>
                </div>
                <div class="row mb-0">
                    <div class="col-5"><button type="button" class="close-menu btn btn-s btn-full bg-theme color-theme border rounded-s font-600">Batal</button></div>
                    <div class="col-7"><button type="submit" class="btn btn-s btn-full gradient-highlight rounded-s font-600"><i class="fa fa-eye me-1"></i>Pratinjau</button></div>
                </div>
            </form>
        </div>
    </div>
    <?php $this->load->view('reports/print_modal', array(
        'printModalId' => 'registration-attendance-print-modal',
        'printModalTitle' => 'Cetak Absen',
        'printPreviewUrl' => site_url('registrasi/absen/cetak'),
        'printPdfUrl' => site_url('registrasi/absen/pdf'),
        'printFormatLabel' => '',
        'printPaperNote' => '',
        'printIconOnly' => TRUE
    )); ?>
    <?php $this->load->view('reports/print_modal', array(
        'printModalId' => 'registration-participant-print-modal',
        'printModalTitle' => 'Cetak Data Peserta',
        'printPreviewUrl' => site_url('registrasi/peserta/cetak'),
        'printPdfUrl' => site_url('registrasi/peserta/pdf'),
        'printExcelUrl' => site_url('registrasi/excel'),
        'printExcelLabel' => 'Excel Mailing',
        'printFormatLabel' => '',
        'printPaperNote' => '',
        'printIconOnly' => TRUE
    )); ?>
    <?php $this->load->view('reports/print_modal', array(
        'printModalId' => 'registration-village-print-modal',
        'printModalTitle' => 'Cetak Data Desa',
        'printPreviewUrl' => site_url('registrasi/desa/cetak'),
        'printPdfUrl' => site_url('registrasi/desa/pdf'),
        'printExcelUrl' => site_url('registrasi/desa/excel'),
        'printExcelLabel' => 'Excel MOU',
        'printFormatLabel' => '',
        'printPaperNote' => '',
        'printIconOnly' => TRUE
    )); ?>
<?php endif; ?>

<?php if ($canCreate && $activeEvents): ?>
    <?php
    $eventPayload = array();
    foreach ($activeEvents as $event) {
        $eventPayload[] = array('id'=>(int)$event['id'],'name'=>$event['name'],'code'=>$event['code'],'billing_mode'=>$event['billing_mode'],'village_fee'=>isset($event['village_fee'])?$event['village_fee']:'0.00','participant_fee'=>isset($event['participant_fee'])?$event['participant_fee']:'0.00','included_participant_count'=>isset($event['included_participant_count'])?$event['included_participant_count']:0,'regencies'=>isset($event['regencies'])?$event['regencies']:array());
    }
    ?>
    <a id="registration-add-opener" href="#" class="d-none" data-menu="registration-add-modal" aria-hidden="true" tabindex="-1"></a>
    <div id="registration-add-modal" class="menu menu-box-modal rounded-m simp-full-form-modal" data-menu-width="980" data-menu-height="820" role="dialog" aria-modal="true" aria-labelledby="registration-add-title">
        <div class="content mb-0">
            <div class="d-flex align-items-start mb-3"><div class="min-width-zero pe-3"><p class="font-600 color-highlight mb-n1">Registrasi baru</p><h3 id="registration-add-title" class="font-20 mb-0">Tambah Peserta</h3></div><button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto flex-shrink-0" aria-label="Tutup"><i class="fa fa-times"></i></button></div>
            <form id="registration-add-form" method="post" enctype="multipart/form-data" action="<?= site_url('registrasi/ajax/tambah') ?>" data-events="<?= e(json_encode($eventPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>" data-positions="<?= e(json_encode($positionPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>" data-accounts="<?= e(json_encode($accountPayload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>" data-can-record-payment="<?= $canRecordPayment ? '1' : '0' ?>" data-districts-url="<?= site_url('wilayah/kecamatan') ?>" data-villages-url="<?= site_url('wilayah/desa') ?>">
                <?= csrf_field() ?>
                <?php if (count($activeEvents) === 1): $onlyEvent = $activeEvents[0]; ?>
                    <input type="hidden" name="event_id" id="registration-add-event" value="<?= (int)$onlyEvent['id'] ?>"><div class="d-flex align-items-center rounded-s bg-blue-light px-3 py-2 mb-3"><span class="icon icon-s rounded-xl bg-blue-dark color-white me-3"><i class="fa fa-calendar-check"></i></span><div class="min-width-zero"><p class="font-10 color-blue-dark font-600 mb-n1">Event aktif</p><p class="font-12 font-600 mb-0 text-break"><?= e($onlyEvent['name']) ?></p></div></div>
                <?php else: ?>
                    <div class="input-style has-borders no-icon input-style-always-active mb-3"><label for="registration-add-event" class="color-highlight">Event aktif</label><select id="registration-add-event" name="event_id" required><option value="">Pilih event aktif</option><?php foreach ($activeEvents as $event): ?><option value="<?= (int)$event['id'] ?>"><?= e($event['name']) ?></option><?php endforeach; ?></select><span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em>*</em></div>
                <?php endif; ?>
                <div class="row mb-0"><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active registration-cascade-select-wrap mb-3"><label for="registration-add-district" class="color-highlight">Kecamatan</label><select id="registration-add-district" class="registration-cascade-select" required disabled><option value="">Pilih event dahulu</option></select><i class="fa fa-check d-none disabled valid color-green-dark"></i><i class="fa fa-times d-none disabled invalid color-red-dark"></i><em>*</em></div></div><div class="col-12"><div class="input-style has-borders no-icon input-style-always-active registration-cascade-select-wrap mb-3"><label for="registration-add-village" class="color-highlight">Desa</label><select id="registration-add-village" class="registration-cascade-select" name="village_id" required disabled><option value="">Pilih kecamatan dahulu</option></select><i class="fa fa-check d-none disabled valid color-green-dark"></i><i class="fa fa-times d-none disabled invalid color-red-dark"></i><em>*</em></div></div></div>
                <div class="d-flex align-items-center mb-2"><p class="font-600 color-highlight mb-0">Data peserta</p><button type="button" class="btn btn-xxs border-blue-dark color-blue-dark rounded-s ms-auto" data-add-modal-participant><i class="fa fa-plus me-1"></i> Peserta</button></div>
                <div id="registration-add-participants"></div>
                <?php if ($canRecordPayment): ?><div id="registration-add-village-payment"></div><?php endif; ?>
                <button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600 mt-3" data-registration-add-submit><i class="fa fa-save me-1"></i> Simpan Registrasi</button>
            </form>
        </div>
    </div>
<?php endif; ?>
