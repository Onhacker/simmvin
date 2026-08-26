<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$registration = isset($registration) && is_array($registration) ? $registration : array();
$event = isset($event) && is_array($event) ? $event : array();
$participants = isset($registration['participants']) && is_array($registration['participants']) ? $registration['participants'] : array();
$payments = isset($registration['payments']) && is_array($registration['payments']) ? $registration['payments'] : array();
$generatedAt = isset($generatedAt) ? (string) $generatedAt : date('Y-m-d H:i:s');
$isPdf = !empty($isPdf);

$logoDataUri = '';
$logoPath = defined('FCPATH') ? FCPATH . 'assets/images/mvin-logo.jpg' : '';
if ($logoPath !== '' && is_file($logoPath) && is_readable($logoPath)) {
    $logoData = @file_get_contents($logoPath);
    if ($logoData !== FALSE) $logoDataUri = 'data:image/jpeg;base64,' . base64_encode($logoData);
}

$moneyCents = function ($value) {
    $cents = simp_money_cents($value);
    return $cents === NULL ? 0 : $cents;
};
$money = function ($cents) {
    return rupiah(simp_money_from_cents(max(0, (int) $cents)));
};
$methodLabels = array('cash'=>'Tunai', 'transfer'=>'Transfer', 'qris'=>'QRIS');
$paymentRecordLabels = array('verified'=>'Terverifikasi', 'pending'=>'Menunggu Verifikasi', 'rejected'=>'Ditolak');
$billingLabels = array(
    'per_village'=>'Per Desa',
    'per_participant'=>'Per Peserta',
    'per_village_extra'=>'Per Desa + Peserta Tambahan'
);
$billingMode = isset($registration['billing_mode']) ? (string) $registration['billing_mode'] : 'per_village';

$paymentState = function ($verified, $pending, $expected, $cancelled = FALSE) {
    $verified = max(0, (int) $verified);
    $pending = max(0, (int) $pending);
    $expected = max(0, (int) $expected);
    if ($cancelled) return array('label'=>'Dibatalkan', 'class'=>'red');
    if ($expected <= 0) return array('label'=>'Tidak Ditagih', 'class'=>'gray');
    if ($verified > $expected) return array('label'=>'Lebih Bayar', 'class'=>'blue');
    if ($verified >= $expected) return array('label'=>'Lunas', 'class'=>'green');
    if ($verified > 0 && $pending > 0) return array('label'=>'Sebagian + Menunggu', 'class'=>'yellow');
    if ($verified > 0) return array('label'=>'Bayar Sebagian', 'class'=>'yellow');
    if ($pending > 0) return array('label'=>'Menunggu Verifikasi', 'class'=>'blue');
    return array('label'=>'Belum Bayar', 'class'=>'red');
};

$expectedCents = $moneyCents(isset($registration['expected_amount']) ? $registration['expected_amount'] : 0);
$verifiedCents = $moneyCents(isset($registration['paid_amount']) ? $registration['paid_amount'] : 0);
$pendingCents = $moneyCents(isset($registration['pending_amount']) ? $registration['pending_amount'] : 0);
$remainingCents = max(0, $expectedCents - $verifiedCents);
$remainingAfterCommitmentCents = max(0, $expectedCents - $verifiedCents - $pendingCents);
$isCancelled = isset($registration['status']) && $registration['status'] === 'cancelled';
$overallState = $paymentState($verifiedCents, $pendingCents, $expectedCents, $isCancelled);

$eventStart = isset($event['start_date']) ? $event['start_date'] : '';
$eventEnd = isset($event['end_date']) ? $event['end_date'] : '';
$eventDate = tanggal_id($eventStart);
if ($eventEnd !== '' && $eventEnd !== $eventStart) $eventDate .= ' s.d. ' . tanggal_id($eventEnd);
$eventLocation = trim((string) (isset($event['location']) ? $event['location'] : ''));
if ($eventLocation === '') $eventLocation = '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=10, user-scalable=yes">
    <title>Laporan Detail Registrasi | MVIN</title>
    <style>
        @page { size: 210mm 330mm; margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        html { padding: 0; color: #111827; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 12px; line-height: 1.3; touch-action: pan-x pan-y; }
        body { margin: 0; padding: 0; color: #111827; font-family: inherit; font-size: inherit; line-height: inherit; touch-action: inherit; }
        body { background: #e9eef5; }
        .sheet-stage { width: 210mm; min-height: 330mm; margin: 14px auto 24px; }
        .sheet { width: 210mm; min-height: 330mm; margin: 0; padding: 12mm 10mm 14mm; background: #fff; box-shadow: 0 10px 34px rgba(15, 23, 42, .14); transform-origin: top left; }
        .document-head, .meta-table, .summary-grid, .report-table, .document-foot { width: 100%; border-collapse: collapse; }
        .document-head td { padding: 0 0 7px; border-bottom: 2px solid #1f5fab; vertical-align: middle; }
        .logo-cell { width: 35mm; }
        .logo-cell img { display: block; width: 28mm; height: auto; }
        .brand-code { color: #1f5fab; font-size: 23px; font-weight: 800; letter-spacing: 1px; }
        .title-block { text-align: right; }
        .title-block h1 { margin: 0; color: #111827; font-size: 18px; line-height: 1.15; }
        .title-block p { margin: 3px 0 0; color: #4b5563; font-size: 12px; font-weight: 700; }
        .meta-table { margin: 8px 0 9px; }
        .meta-table td { padding: 2px 0; vertical-align: top; }
        .meta-label { width: 27mm; color: #6b7280; }
        .meta-separator { width: 4mm; color: #6b7280; }
        .meta-value { font-weight: 700; }
        .section-title { margin: 10px 0 5px; color: #1f5fab; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .35px; }
        .summary-grid { table-layout: fixed; margin-bottom: 8px; }
        .summary-grid td { width: 33.333%; padding: 6px 7px; border: 1px solid #d7dee8; vertical-align: top; }
        .summary-label { display: block; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .2px; }
        .summary-value { display: block; margin-top: 2px; color: #111827; font-size: 12px; font-weight: 800; overflow-wrap: anywhere; }
        .summary-value.positive { color: #18743d; }
        .summary-value.negative { color: #b42318; }
        .summary-value.warning { color: #9a5b06; }
        .report-note { margin: -1px 0 6px; padding: 5px 7px; border-left: 3px solid #1f5fab; background: #f3f7fc; color: #4b5563; font-size: 12px; }
        .report-table { table-layout: fixed; }
        .report-table thead { display: table-header-group; }
        .report-table tr { page-break-inside: avoid; }
        .report-table th { padding: 5px 3px; border: 1px solid #9eacbd; background: #1f5fab; color: #fff; font-size: 12px; line-height: 1.15; text-align: left; text-transform: uppercase; }
        .report-table td { padding: 5px 3px; border: 1px solid #cfd7e2; vertical-align: top; font-size: 12px; line-height: 1.2; overflow-wrap: break-word; word-wrap: break-word; }
        .report-table tbody tr:nth-child(even) td { background: #f7f9fc; }
        .report-table .number { text-align: center; }
        .report-table .money { text-align: right; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
        .primary { display: block; font-weight: 700; }
        .secondary { display: block; margin-top: 1px; color: #5f6b7a; font-size: 12px; line-height: 1.2; }
        .status { display: block; width: 100%; max-width: 100%; padding: 2px 4px; border-radius: 3px; color: #fff; font-size: 12px; font-weight: 700; line-height: 1.15; text-align: center; white-space: normal; overflow-wrap: normal; word-break: normal; }
        .status.green { background: #1f7a45; }
        .status.yellow { background: #a76608; }
        .status.red { background: #b42318; }
        .status.blue { background: #1f5fab; }
        .status.gray { background: #5f6b7a; }
        .empty-row td { padding: 20px 8px; color: #6b7280; text-align: center; }
        .empty-note { padding: 8px; border: 1px solid #cfd7e2; background: #f8fafc; color: #6b7280; text-align: center; page-break-inside: avoid; }
        .cancellation-note { margin: 7px 0; padding: 6px 8px; border: 1px solid #efb4b4; background: #fff1f1; color: #8f1d1d; }
        .registration-note { margin: 7px 0; padding: 6px 8px; border: 1px solid #cfd7e2; background: #f8fafc; }
        .document-foot { margin-top: 10px; border-top: 1px solid #cfd7e2; }
        .document-foot td { padding-top: 5px; color: #6b7280; font-size: 12px; vertical-align: top; }
        .document-foot .right { text-align: right; }
        @media print {
            html, body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet-stage { width: auto !important; height: auto !important; min-height: 0 !important; margin: 0 !important; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; transform: none !important; }
        }
    </style>
</head>
<body>
<div class="sheet-stage" data-sheet-stage>
<article class="sheet" data-print-sheet>
    <table class="document-head" role="presentation">
        <tr>
            <td class="logo-cell"><?php if ($logoDataUri !== ''): ?><img src="<?= e($logoDataUri) ?>" alt="Logo MVIN"><?php else: ?><div class="brand-code">MVIN</div><?php endif; ?></td>
            <td class="title-block">
                <h1>LAPORAN DETAIL REGISTRASI</h1>
                <p><?= e(isset($registration['event_name']) ? $registration['event_name'] : (isset($event['name']) ? $event['name'] : 'Event Pelatihan')) ?></p>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr><td class="meta-label">Registrasi</td><td class="meta-separator">:</td><td class="meta-value">#<?= (int) (isset($registration['id']) ? $registration['id'] : 0) ?> · <?= e(isset($registration['village_name']) ? $registration['village_name'] : '-') ?></td></tr>
        <tr><td class="meta-label">Wilayah</td><td class="meta-separator">:</td><td class="meta-value"><?= e((isset($registration['district_name']) ? $registration['district_name'] : '-') . ' · ' . (isset($registration['regency_name']) ? $registration['regency_name'] : '-') . ' · ' . (isset($registration['province_name']) ? $registration['province_name'] : '-')) ?></td></tr>
        <tr><td class="meta-label">Event</td><td class="meta-separator">:</td><td class="meta-value"><?= e((isset($registration['event_code']) ? $registration['event_code'] : '-') . ' · ' . (isset($registration['event_name']) ? $registration['event_name'] : '-')) ?></td></tr>
        <tr><td class="meta-label">Pelaksanaan</td><td class="meta-separator">:</td><td class="meta-value"><?= e($eventDate) ?> · <?= e($eventLocation) ?></td></tr>
        <tr><td class="meta-label">Mode Tagihan</td><td class="meta-separator">:</td><td class="meta-value"><?= e(isset($billingLabels[$billingMode]) ? $billingLabels[$billingMode] : ucwords(str_replace('_', ' ', $billingMode))) ?></td></tr>
        <tr><td class="meta-label">Status Registrasi</td><td class="meta-separator">:</td><td class="meta-value"><?= $isCancelled ? 'Dibatalkan' : 'Aktif' ?></td></tr>
        <tr><td class="meta-label">Dibuat</td><td class="meta-separator">:</td><td class="meta-value"><?= e(tanggal_id(isset($registration['created_at']) ? $registration['created_at'] : '', TRUE)) ?></td></tr>
    </table>

    <?php if ($isCancelled): ?><div class="cancellation-note"><strong>Registrasi dibatalkan.</strong> <?= e(!empty($registration['cancellation_reason']) ? $registration['cancellation_reason'] : 'Alasan pembatalan tidak tercatat.') ?></div><?php endif; ?>
    <?php if (!empty($registration['notes'])): ?><div class="registration-note"><strong>Catatan registrasi:</strong> <?= nl2br(e($registration['notes'])) ?></div><?php endif; ?>

    <div class="section-title">Ringkasan Pembayaran</div>
    <table class="summary-grid">
        <tr>
            <td><span class="summary-label">Jumlah Peserta</span><span class="summary-value"><?= number_format(count($participants), 0, ',', '.') ?> orang</span></td>
            <td><span class="summary-label">Total Tagihan</span><span class="summary-value"><?= e($money($expectedCents)) ?></span></td>
            <td><span class="summary-label">Status Pembayaran</span><span class="summary-value"><?= e($overallState['label']) ?></span></td>
        </tr>
        <tr>
            <td><span class="summary-label">Terverifikasi</span><span class="summary-value positive"><?= e($money($verifiedCents)) ?></span></td>
            <td><span class="summary-label">Menunggu Verifikasi</span><span class="summary-value warning"><?= e($money($pendingCents)) ?></span></td>
            <td><span class="summary-label">Sisa Tagihan</span><span class="summary-value <?= $remainingCents > 0 ? 'negative' : 'positive' ?>"><?= e($money($remainingCents)) ?></span><?php if ($pendingCents > 0): ?><span class="secondary">Sisa: <?= e($money($remainingAfterCommitmentCents)) ?></span><?php endif; ?></td>
        </tr>
    </table>

    <div class="section-title">Rincian Peserta</div>
    <?php if ($billingMode !== 'per_participant'): ?><p class="report-note">Pembayaran event ini dicatat pada tingkat desa. Status pada setiap peserta mengikuti status pembayaran registrasi desa dan bukan transaksi individual.</p><?php endif; ?>
    <table class="report-table">
        <colgroup><col width="4%" style="width:4%"><col width="20%" style="width:20%"><col width="12%" style="width:12%"><col width="14%" style="width:14%"><col width="14%" style="width:14%"><col width="12%" style="width:12%"><col width="12%" style="width:12%"><col width="12%" style="width:12%"></colgroup>
        <thead><tr><th width="4%">No.</th><th width="20%">Nama / Jabatan</th><th width="12%">Kontak</th><th width="14%">Komponen Tagihan</th><th width="14%" class="money">Terverifikasi</th><th width="12%" class="money">Menunggu</th><th width="12%" class="money">Sisa</th><th width="12%">Status</th></tr></thead>
        <tbody>
        <?php if (!$participants): ?><tr class="empty-row"><td colspan="8">Tidak ada peserta aktif pada registrasi ini.</td></tr><?php endif; ?>
        <?php foreach ($participants as $index => $participant): ?>
            <?php
            $participantExpected = $moneyCents(isset($participant['expected_amount']) ? $participant['expected_amount'] : 0);
            $participantVerified = $moneyCents(isset($participant['paid_amount']) ? $participant['paid_amount'] : 0);
            $participantPending = $moneyCents(isset($participant['pending_amount']) ? $participant['pending_amount'] : 0);
            $participantRemaining = max(0, $participantExpected - $participantVerified);
            $participantState = $billingMode === 'per_participant'
                ? $paymentState($participantVerified, $participantPending, $participantExpected, $isCancelled)
                : $overallState;
            if ($billingMode === 'per_participant') {
                $componentLabel = $money($participantExpected);
                $verifiedLabel = $money($participantVerified);
                $pendingLabel = $money($participantPending);
                $remainingLabel = $money($participantRemaining);
            } elseif ($billingMode === 'per_village_extra') {
                $componentLabel = $participantExpected > 0 ? 'Tambahan ' . $money($participantExpected) : 'Termasuk paket';
                $verifiedLabel = $pendingLabel = $remainingLabel = 'Tingkat desa';
            } else {
                $componentLabel = 'Paket desa';
                $verifiedLabel = $pendingLabel = $remainingLabel = 'Tingkat desa';
            }
            ?>
            <tr>
                <td class="number"><?= number_format($index + 1, 0, ',', '.') ?></td>
                <td><span class="primary"><?= e(isset($participant['full_name']) ? $participant['full_name'] : '-') ?></span><span class="secondary"><?= e(!empty($participant['position']) ? $participant['position'] : '-') ?></span></td>
                <td><?= e(!empty($participant['phone']) ? $participant['phone'] : '-') ?></td>
                <td><?= e($componentLabel) ?></td>
                <td class="money"><?= e($verifiedLabel) ?></td>
                <td class="money"><?= e($pendingLabel) ?></td>
                <td class="money"><?= e($remainingLabel) ?></td>
                <td><span class="status <?= e($participantState['class']) ?>"><?= e($participantState['label']) ?><?= $billingMode !== 'per_participant' ? ' (Desa)' : '' ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">Riwayat Transaksi</div>
    <?php if (!$payments): ?>
        <div class="empty-note">Belum ada transaksi pembayaran pada registrasi ini.</div>
    <?php else: ?>
        <table class="report-table">
        <colgroup><col width="4%" style="width:4%"><col width="15%" style="width:15%"><col width="16%" style="width:16%"><col width="21%" style="width:21%"><col width="14%" style="width:14%"><col width="13%" style="width:13%"><col width="17%" style="width:17%"></colgroup>
        <thead><tr><th width="4%">No.</th><th width="15%">Tanggal / Bukti</th><th width="16%">Untuk</th><th width="21%">Metode / Akun</th><th width="14%" class="money">Nominal</th><th width="13%">Status</th><th width="17%">Catatan</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $index => $payment): ?>
            <?php $paymentStatus = isset($payment['status']) ? (string) $payment['status'] : 'pending'; ?>
            <tr>
                <td class="number"><?= number_format($index + 1, 0, ',', '.') ?></td>
                <td><span class="primary"><?= e(tanggal_id(isset($payment['payment_date']) ? $payment['payment_date'] : '')) ?></span><span class="secondary"><?= e(isset($payment['receipt_no']) ? $payment['receipt_no'] : '-') ?></span></td>
                <td><?= e(!empty($payment['participant_name']) ? $payment['participant_name'] : (isset($registration['village_name']) ? $registration['village_name'] : '-')) ?></td>
                <td><span class="primary"><?= e(isset($methodLabels[$payment['method']]) ? $methodLabels[$payment['method']] : ucfirst((string) (isset($payment['method']) ? $payment['method'] : '-'))) ?></span><span class="secondary"><?= e(isset($payment['account_name']) ? $payment['account_name'] : '-') ?></span></td>
                <td class="money"><?= e(rupiah(isset($payment['amount']) ? $payment['amount'] : 0)) ?></td>
                <td><span class="status <?= $paymentStatus === 'verified' ? 'green' : ($paymentStatus === 'rejected' ? 'red' : 'blue') ?>"><?= e(isset($paymentRecordLabels[$paymentStatus]) ? $paymentRecordLabels[$paymentStatus] : ucwords(str_replace('_', ' ', $paymentStatus))) ?></span></td>
                <td><?= e(!empty($payment['note']) ? $payment['note'] : '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    <?php endif; ?>

    <table class="document-foot"><tr><td>Laporan operasional satu registrasi. Dokumen ini bukan formulir daftar hadir atau lembar tanda tangan.</td><td class="right">Dicetak <?= e(tanggal_id($generatedAt, TRUE)) ?> · F4</td></tr></table>
</article>
</div>
<?php if (!$isPdf): ?>
<script>
(function () {
    var stage = document.querySelector('[data-sheet-stage]');
    var sheet = document.querySelector('[data-print-sheet]');
    if (!stage || !sheet) return;
    var userZoom = 100;
    function fitSheet() {
        sheet.style.transform = 'none';
        stage.style.width = '210mm';
        stage.style.height = 'auto';
        stage.style.minHeight = '330mm';
        var naturalWidth = sheet.offsetWidth;
        var naturalHeight = sheet.offsetHeight;
        var availableWidth = Math.max(1, document.documentElement.clientWidth - 24);
        var fitScale = Math.min(1, availableWidth / naturalWidth);
        var scale = fitScale * (userZoom / 100);
        if (Math.abs(scale - 1) > 0.001) sheet.style.transform = 'scale(' + scale + ')';
        stage.style.width = Math.round(naturalWidth * scale) + 'px';
        stage.style.height = Math.round(naturalHeight * scale) + 'px';
        stage.style.minHeight = '0';
    }
    function setZoom(percent) {
        userZoom = Math.max(50, Math.min(1000, Math.round(Number(percent) || 100)));
        fitSheet();
        return userZoom;
    }
    window.SIMPPrintPreview = { getZoom: function () { return userZoom; }, setZoom: setZoom, reset: function () { return setZoom(100); } };
    fitSheet();
    window.addEventListener('resize', fitSheet);
}());
</script>
<script src="<?= e(base_url('assets/js/print-preview.js')) ?>?v=3"></script>
<?php endif; ?>
</body>
</html>
