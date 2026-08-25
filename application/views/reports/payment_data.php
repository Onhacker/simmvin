<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$documentTitle = isset($documentTitle) ? (string) $documentTitle : 'Data Bayar';
$organizationName = isset($organizationName) ? (string) $organizationName : 'MEDIAVERSE INOVASI NUSANTARA';
$activeEvents = isset($activeEvents) && is_array($activeEvents) ? $activeEvents : array();
$reportEvents = isset($reportEvents) && is_array($reportEvents) ? $reportEvents : $activeEvents;
$report = isset($report) && is_array($report) ? $report : array('summary'=>array(), 'rows'=>array());
$summary = isset($report['summary']) ? $report['summary'] : array();
$rows = isset($report['rows']) && is_array($report['rows']) ? $report['rows'] : array();
$filterScope = isset($filterScope) ? (string) $filterScope : 'Semua Kecamatan dan Desa';
$generatedAt = isset($generatedAt) ? (string) $generatedAt : date('Y-m-d H:i:s');
$isPdf = !empty($isPdf);

$logoDataUri = '';
$logoPath = defined('FCPATH') ? FCPATH . 'assets/images/mvin-logo.jpg' : '';
if ($logoPath !== '' && is_file($logoPath) && is_readable($logoPath)) {
    $logoData = @file_get_contents($logoPath);
    if ($logoData !== FALSE) $logoDataUri = 'data:image/jpeg;base64,' . base64_encode($logoData);
}

$stateLabels = array(
    'paid' => array('Lunas', 'green'),
    'overpaid' => array('Lebih Bayar', 'blue'),
    'partial_pending' => array('Sebagian + Menunggu', 'yellow'),
    'partial' => array('Sebagian', 'yellow'),
    'pending' => array('Menunggu', 'yellow'),
    'unpaid' => array('Belum Bayar', 'red'),
    'no_charge' => array('Tidak Ditagih', 'blue')
);
$money = function ($value, $withPrefix = TRUE) { return rupiah($value, $withPrefix); };
$eventScope = $activeEvents ? 'Tidak ada event dengan data pada filter ini' : 'Belum ada event aktif';
if (count($reportEvents) === 1) $eventScope = $reportEvents[0]['name'] . ' (' . $reportEvents[0]['code'] . ')';
elseif (count($reportEvents) > 1) $eventScope = number_format(count($reportEvents)) . ' event pada hasil laporan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <title><?= e($documentTitle) ?> | MVIN</title>
    <style>
        @page { size: 330mm 210mm; margin: 9mm 9mm 11mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; color: #111; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9.5px; line-height: 1.35; touch-action: pan-x pan-y; }
        body { background: #e9eef5; }
        .sheet-stage { width: 330mm; min-height: 210mm; margin: 14px auto 24px; }
        .sheet { width: 330mm; min-height: 210mm; padding: 9mm 9mm 11mm; background: #fff; box-shadow: 0 10px 34px rgba(15, 23, 42, .14); transform-origin: top left; }
        .document-head, .meta-table, .summary-grid, .report-table, .document-foot { width: 100%; border-collapse: collapse; }
        .document-head td { padding: 0 0 6px; border-bottom: 2px solid #1f5fab; vertical-align: middle; }
        .logo-cell { width: 30mm; }
        .logo-cell img { display: block; width: 24mm; height: auto; }
        .title-cell { text-align: center; }
        .title-cell h1 { margin: 0; color: #111; font-size: 21px; line-height: 1.15; letter-spacing: .4px; text-transform: uppercase; }
        .title-cell p { margin: 3px 0 0; color: #1f5fab; font-size: 10px; font-weight: 700; }
        .balance-cell { width: 30mm; }
        .meta-table { margin: 7px 0; }
        .meta-table td { padding: 1.5px 0; vertical-align: top; }
        .meta-label { width: 25mm; color: #4b5563; }
        .meta-separator { width: 4mm; color: #4b5563; }
        .meta-value { color: #111; font-weight: 700; }
        .event-list { margin: 0 0 7px; padding: 5px 7px; border-left: 3px solid #1f5fab; background: #f3f7fc; font-size: 8px; }
        .event-list strong { color: #1f5fab; }
        .event-list span { display: inline; }
        .event-list span + span::before { content: "  |  "; color: #8291a4; }
        .section-title { margin: 7px 0 4px; color: #1f5fab; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .25px; }
        .summary-grid { table-layout: fixed; margin-bottom: 7px; }
        .summary-grid td { width: 12.5%; padding: 5px 6px; border: 1px solid #cfd7e2; vertical-align: top; }
        .summary-label { display: block; color: #4b5563; font-size: 7px; text-transform: uppercase; }
        .summary-value { display: block; margin-top: 1px; color: #111; font-size: 10px; font-weight: 800; }
        .summary-value.positive { color: #18743d; }
        .summary-value.negative { color: #b42318; }
        .report-table { table-layout: fixed; }
        .report-table thead { display: table-header-group; }
        .report-table tr { page-break-inside: avoid; }
        .report-table th { padding: 5px 4px; border: 1px solid #8795a7; background: #1f5fab; color: #fff; font-size: 7.8px; line-height: 1.2; text-align: left; text-transform: uppercase; }
        .report-table td { padding: 5px 4px; border: 1px solid #c8d1dd; color: #111; font-size: 8.1px; vertical-align: top; overflow-wrap: break-word; }
        .report-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .report-table tbody tr + tr td { border-top: 2px solid #1f5fab; }
        .number { text-align: center; }
        .money-cell { text-align: right; white-space: nowrap; }
        .primary { display: block; color: #111; font-weight: 700; }
        .secondary { display: block; margin-top: 1px; color: #4b5563; font-size: 7px; line-height: 1.25; white-space: normal; }
        .participant-list { margin: 0; padding: 0; list-style: none; }
        .participant-list li { margin: 0 0 1px; line-height: 1.25; }
        .participant-number { display: inline-block; width: 14px; color: #4b5563; font-size: 7px; vertical-align: top; }
        .participant-name { display: inline; }
        .participant-empty { color: #4b5563; font-style: italic; }
        .status { display: inline-block; max-width: 100%; padding: 2px 4px; border-radius: 3px; color: #fff; font-size: 6.8px; font-weight: 700; line-height: 1.25; text-align: center; }
        .status.green { background: #1f7a45; }
        .status.yellow { background: #a76608; }
        .status.red { background: #b42318; }
        .status.blue { background: #1f5fab; }
        .empty-row td { padding: 18px 8px; text-align: center; }
        .document-foot { margin-top: 8px; border-top: 1px solid #cfd7e2; }
        .document-foot td { padding-top: 4px; color: #4b5563; font-size: 7px; }
        .document-foot td:last-child { text-align: right; }
        @media print {
            html, body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet-stage { width: auto !important; height: auto !important; min-height: 0 !important; margin: 0 !important; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; transform: none !important; }
            .report-table th { background: #e8eef7 !important; color: #111 !important; border-color: #6b7280 !important; }
            .status { background: #fff !important; color: #111 !important; border: 1px solid #6b7280 !important; }
        }
    </style>
</head>
<body>
<div class="sheet-stage" data-sheet-stage>
    <article class="sheet" data-print-sheet>
        <table class="document-head" role="presentation">
            <tr>
                <td class="logo-cell"><?php if ($logoDataUri !== ''): ?><img src="<?= e($logoDataUri) ?>" alt="Logo MVIN"><?php endif; ?></td>
                <td class="title-cell"><h1>Data Bayar</h1><p><?= e($organizationName) ?></p></td>
                <td class="balance-cell"></td>
            </tr>
        </table>

        <table class="meta-table" role="presentation">
            <tr><td class="meta-label">Event</td><td class="meta-separator">:</td><td class="meta-value"><?= e($eventScope) ?></td></tr>
            <?php if (count($reportEvents) === 1): ?>
                <tr><td class="meta-label">Pelaksanaan</td><td class="meta-separator">:</td><td class="meta-value"><?= e(tanggal_id($reportEvents[0]['start_date'])) ?> s.d. <?= e(tanggal_id($reportEvents[0]['end_date'])) ?><?= trim((string)($reportEvents[0]['location'] ?? '')) !== '' ? ' · ' . e($reportEvents[0]['location']) : '' ?></td></tr>
            <?php endif; ?>
            <tr><td class="meta-label">Wilayah</td><td class="meta-separator">:</td><td class="meta-value"><?= e($filterScope) ?></td></tr>
            <tr><td class="meta-label">Dibuat</td><td class="meta-separator">:</td><td class="meta-value"><?= e(tanggal_id($generatedAt, TRUE)) ?></td></tr>
        </table>

        <?php if (count($reportEvents) > 1): ?>
            <div class="event-list"><strong>Event disertakan: </strong><?php foreach ($reportEvents as $event): ?><span><?= e($event['code']) ?> · <?= e($event['name']) ?> (<?= e(tanggal_id($event['start_date'])) ?> s.d. <?= e(tanggal_id($event['end_date'])) ?>)</span><?php endforeach; ?></div>
        <?php endif; ?>

        <div class="section-title">Ringkasan Pembayaran</div>
        <table class="summary-grid" role="presentation">
            <tr>
                <td><span class="summary-label">Desa</span><span class="summary-value"><?= number_format((int)($summary['villages'] ?? $summary['registrations'] ?? 0)) ?></span></td>
                <td><span class="summary-label">Peserta</span><span class="summary-value"><?= number_format((int)($summary['participants'] ?? 0)) ?></span></td>
                <td><span class="summary-label">Tagihan</span><span class="summary-value"><?= e($money($summary['total_due'] ?? 0)) ?></span></td>
                <td><span class="summary-label">Terverifikasi</span><span class="summary-value positive"><?= e($money($summary['verified'] ?? 0)) ?></span></td>
                <td><span class="summary-label">Menunggu</span><span class="summary-value"><?= e($money($summary['pending'] ?? 0)) ?></span></td>
                <td><span class="summary-label">Sisa</span><span class="summary-value <?= simp_money_cents($summary['outstanding'] ?? 0) > 0 ? 'negative' : 'positive' ?>"><?= e($money($summary['outstanding'] ?? 0)) ?></span></td>
                <td><span class="summary-label">Tunai</span><span class="summary-value"><?= e($money($summary['cash_total'] ?? 0)) ?></span></td>
                <td><span class="summary-label">Transfer / QRIS</span><span class="summary-value"><?= e($money($summary['transfer_total'] ?? 0, FALSE)) ?> / <?= e($money($summary['qris_total'] ?? 0, FALSE)) ?></span></td>
            </tr>
        </table>

        <div class="section-title">Rincian Status Pembayaran</div>
        <table class="report-table">
            <colgroup><col style="width:3%"><col style="width:16%"><col style="width:25%"><col style="width:5%"><col style="width:10%"><col style="width:17%"><col style="width:9%"><col style="width:9%"><col style="width:6%"></colgroup>
            <thead><tr><th>No.</th><th>Desa / Kecamatan</th><th>Nama Peserta</th><th>Jml.</th><th class="money-cell">Tagihan</th><th class="money-cell">Terverifikasi / Metode</th><th class="money-cell">Menunggu</th><th class="money-cell">Sisa</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr class="empty-row"><td colspan="9">Belum ada data pembayaran pada lingkup yang dipilih.</td></tr><?php endif; ?>
            <?php foreach ($rows as $index => $row): ?>
                <?php
                $state = isset($stateLabels[$row['payment_state']]) ? $stateLabels[$row['payment_state']] : $stateLabels['unpaid'];
                $participantNames = isset($row['participant_names']) && is_array($row['participant_names']) ? $row['participant_names'] : array();
                ?>
                <tr>
                    <td class="number"><?= number_format($index + 1) ?></td>
                    <td><span class="primary"><?= e($row['village_name']) ?></span><span class="secondary"><?= e($row['district_name']) ?> · <?= e($row['regency_name']) ?></span></td>
                    <td><?php if ($participantNames): ?><ol class="participant-list"><?php foreach ($participantNames as $participantIndex => $participantName): ?><li><span class="participant-number"><?= number_format($participantIndex + 1) ?>.</span><span class="participant-name"><?= e($participantName) ?></span></li><?php endforeach; ?></ol><?php else: ?><span class="participant-empty">Belum ada peserta</span><?php endif; ?></td>
                    <td class="number"><?= number_format((int)$row['participant_count']) ?></td>
                    <td class="money-cell"><?= e($money($row['due_amount'])) ?></td>
                    <td class="money-cell"><span class="primary"><?= e($money($row['verified_amount'])) ?></span><span class="secondary">T <?= e($money($row['cash_total'], FALSE)) ?> · TF <?= e($money($row['transfer_total'], FALSE)) ?> · Q <?= e($money($row['qris_total'], FALSE)) ?></span></td>
                    <td class="money-cell"><?= e($money($row['pending_amount'])) ?></td>
                    <td class="money-cell"><?= e($money($row['outstanding_amount'])) ?></td>
                    <td><span class="status <?= e($state[1]) ?>"><?= e($state[0]) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <table class="document-foot" role="presentation"><tr><td>Sisa tagihan dihitung dari tagihan dikurangi pembayaran terverifikasi.</td><td>Dicetak <?= e(tanggal_id(substr($generatedAt, 0, 10))) ?></td></tr></table>
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
        stage.style.width = '330mm';
        stage.style.height = 'auto';
        stage.style.minHeight = '210mm';
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
        userZoom = Math.max(50, Math.min(400, Math.round(Number(percent) || 100)));
        fitSheet();
        return userZoom;
    }
    window.SIMPPrintPreview = { getZoom: function () { return userZoom; }, setZoom: setZoom, reset: function () { return setZoom(100); } };
    fitSheet();
    window.addEventListener('resize', fitSheet);
}());
</script>
<script src="<?= e(base_url('assets/js/print-preview.js')) ?>?v=2"></script>
<?php endif; ?>
</body>
</html>
