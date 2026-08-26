<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$report = isset($report) && is_array($report) ? $report : array();
$breakdown = isset($breakdown) && is_array($breakdown) ? $breakdown : array();
$income = isset($breakdown['income']) && is_array($breakdown['income']) ? $breakdown['income'] : array();
$events = isset($income['events']) && is_array($income['events']) ? $income['events'] : array();
$expenseCategories = isset($breakdown['expense_categories']) && is_array($breakdown['expense_categories']) ? $breakdown['expense_categories'] : array();
$filters = isset($filters) && is_array($filters) ? $filters : array();
$organizationName = isset($organizationName) ? trim((string)$organizationName) : 'MVIN';
if (strcasecmp($organizationName, 'Penyelenggara Pelatihan') === 0) $organizationName = '';
$generatedAt = isset($generatedAt) ? $generatedAt : date('Y-m-d H:i:s');
$isPdf = !empty($isPdf);

$printRupiah = function ($value, $withPrefix = TRUE) { return rupiah($value, $withPrefix); };
$modeLabels = array(
    'per_village' => 'Per Desa',
    'per_participant' => 'Per Peserta',
    'per_village_extra' => 'Desa + Peserta Tambahan'
);
$dateFrom = isset($filters['date_from']) ? trim((string)$filters['date_from']) : '';
$dateTo = isset($filters['date_to']) ? trim((string)$filters['date_to']) : '';
$periodLabel = $dateFrom !== '' && $dateTo !== '' && $dateFrom === $dateTo
    ? tanggal_id($dateFrom)
    : (($dateFrom !== '' ? tanggal_id($dateFrom) : 'Awal') . ' s.d. ' . ($dateTo !== '' ? tanggal_id($dateTo) : 'Hari ini'));
$moneyCents = function ($value) {
    $cents = simp_money_cents($value);
    return $cents === NULL ? 0 : (int)$cents;
};
$opening = isset($report['period_opening_balance']) ? $report['period_opening_balance'] : '0.00';
$incomeTotal = isset($report['income']) ? $report['income'] : '0.00';
$expenseTotal = isset($report['expenses']) ? $report['expenses'] : '0.00';
$transferFees = isset($report['transfer_fees']) ? $report['transfer_fees'] : '0.00';
$net = isset($report['net']) ? $report['net'] : '0.00';
$ending = isset($report['period_ending_balance']) ? $report['period_ending_balance'] : '0.00';
$netCents = isset($report['net_cents']) ? (int)$report['net_cents'] : $moneyCents($net);
$expenseGrandCents = 0;
foreach ($expenseCategories as $category) $expenseGrandCents += isset($category['total_cents']) ? (int)$category['total_cents'] : $moneyCents(isset($category['total']) ? $category['total'] : 0);
$expenseGrand = simp_money_from_cents($expenseGrandCents);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=10, user-scalable=yes">
    <title>Laporan Keuangan | MVIN</title>
    <style>
        @page { size: 330mm 210mm; margin: 10mm 10mm 12mm; }
        * { box-sizing: border-box; }
        html { padding: 0; color: #111827; background: #e9eef5; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9px; line-height: 1.4; touch-action: pan-x pan-y; }
        body { margin: 0; padding: 0; color: #111827; font-family: inherit; font-size: inherit; line-height: inherit; touch-action: inherit; }
        .sheet-stage { width: 330mm; min-height: 210mm; margin: 14px auto 24px; }
        .sheet { width: 330mm; min-height: 210mm; margin: 0; padding: 10mm 10mm 12mm; background: #fff; box-shadow: 0 10px 34px rgba(15,23,42,.14); transform-origin: top left; }
        .document-head, .meta-table, .summary-grid, .report-table, .document-foot { width: 100%; border-collapse: collapse; }
        .document-head td { vertical-align: top; padding: 0 0 7px; border-bottom: 2px solid #1f5fab; }
        .brand-code { color: #1f5fab; font-size: 23px; font-weight: 800; letter-spacing: 1px; line-height: 1; }
        .organization { margin-top: 4px; color: #374151; font-size: 10px; font-weight: 700; }
        .title-block { text-align: right; }
        .title-block h1 { margin: 0; color: #111827; font-size: 19px; line-height: 1.15; }
        .title-block p { margin: 4px 0 0; color: #4b5563; font-size: 9px; }
        .meta-table { margin: 8px 0 10px; }
        .meta-table td { padding: 2px 0; vertical-align: top; }
        .meta-label { width: 95px; color: #6b7280; }
        .meta-separator { width: 10px; color: #6b7280; }
        .meta-value { font-weight: 700; }
        .section-title { margin: 11px 0 5px; color: #1f5fab; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .35px; }
        .summary-grid { table-layout: fixed; margin-bottom: 9px; }
        .summary-grid td { width: 16.66%; padding: 7px 8px; border: 1px solid #d7dee8; vertical-align: top; }
        .summary-label { display: block; color: #6b7280; font-size: 7.5px; text-transform: uppercase; letter-spacing: .2px; }
        .summary-value { display: block; margin-top: 3px; color: #111827; font-size: 11px; font-weight: 800; white-space: nowrap; }
        .summary-value.positive { color: #18743d; }
        .summary-value.negative { color: #b42318; }
        .report-note { margin: 0 0 7px; padding: 5px 7px; border-left: 3px solid #1f5fab; background: #f3f7fc; color: #4b5563; font-size: 7.8px; }
        .report-table { table-layout: fixed; margin-bottom: 9px; }
        .report-table thead { display: table-header-group; }
        .report-table tr { page-break-inside: avoid; }
        .report-table th { padding: 5px 5px; border: 1px solid #9eacbd; background: #1f5fab; color: #fff; font-size: 7.4px; line-height: 1.25; text-align: left; text-transform: uppercase; }
        .report-table td { padding: 5px 5px; border: 1px solid #cfd7e2; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        .report-table tbody tr:nth-child(even) td { background: #f7f9fc; }
        .report-table .number { text-align: center; }
        .report-table .money { text-align: right; white-space: nowrap; }
        .report-table .primary { display: block; font-weight: 700; }
        .report-table .secondary { display: block; margin-top: 1px; color: #5f6b7a; font-size: 7.2px; line-height: 1.3; }
        .report-table tfoot td { background: #dcecff !important; border-top: 2px solid #174b8b; color: #111827; font-size: 10px; font-weight: 800; }
        .empty-row td { padding: 18px 8px; color: #6b7280; text-align: center; }
        .mode-badge { display: inline-block; padding: 2px 4px; border-radius: 3px; background: #eaf2fc; color: #174b8b; font-size: 7px; font-weight: 700; }
        .document-foot { margin-top: 10px; border-top: 1px solid #cfd7e2; }
        .document-foot td { padding-top: 5px; color: #6b7280; font-size: 7.5px; }
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
    <table class="document-head">
        <tr>
            <td>
                <div class="brand-code">MVIN</div>
                <?php if ($organizationName !== ''): ?><div class="organization"><?= e($organizationName) ?></div><?php endif; ?>
            </td>
            <td class="title-block">
                <h1>Laporan Keuangan</h1>
                <p>Pemasukan, pengeluaran, dan sisa dana</p>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr><td class="meta-label">Periode laporan</td><td class="meta-separator">:</td><td class="meta-value"><?= e($periodLabel) ?></td></tr>
        <tr><td class="meta-label">Dibuat</td><td class="meta-separator">:</td><td class="meta-value"><?= e(tanggal_id($generatedAt, TRUE)) ?></td></tr>
    </table>

    <div class="section-title">Ringkasan arus dana</div>
    <table class="summary-grid">
        <tr>
            <td><span class="summary-label">Saldo awal</span><span class="summary-value"><?= e($printRupiah($opening)) ?></span></td>
            <td><span class="summary-label">Pemasukan terverifikasi</span><span class="summary-value positive"><?= e($printRupiah($incomeTotal)) ?></span></td>
            <td><span class="summary-label">Pengeluaran terverifikasi</span><span class="summary-value negative"><?= e($printRupiah($expenseTotal)) ?></span></td>
            <td><span class="summary-label">Biaya transfer antar akun</span><span class="summary-value negative"><?= e($printRupiah($transferFees)) ?></span></td>
            <td><span class="summary-label">Mutasi bersih</span><span class="summary-value <?= $netCents < 0 ? 'negative' : 'positive' ?>"><?= e($printRupiah($net)) ?></span></td>
            <td><span class="summary-label">Sisa dana / saldo akhir</span><span class="summary-value <?= $moneyCents($ending) < 0 ? 'negative' : 'positive' ?>"><?= e($printRupiah($ending)) ?></span></td>
        </tr>
    </table>
    <?php $hasCrossPoolTransfer = (int)($report['transfer_in_cents'] ?? 0) !== 0 || (int)($report['transfer_out_cents'] ?? 0) !== 0; $hasAdjustment = (int)($report['adjustment_cents'] ?? 0) !== 0; ?>
    <?php if ($hasCrossPoolTransfer || $hasAdjustment): ?>
        <p class="report-note"><?php if ($hasCrossPoolTransfer): ?>Mutasi bersih memperhitungkan perpindahan pokok antara akun yang masuk dan tidak masuk total. Biaya transfer tetap dicatat sebagai pengeluaran dana.<?php endif; ?><?php if ($hasAdjustment): ?><?= $hasCrossPoolTransfer ? ' ' : '' ?>Penyesuaian saldo jurnal pada periode ini: <strong><?= e($printRupiah($report['adjustment'])) ?></strong>.<?php endif; ?></p>
    <?php else: ?>
        <p class="report-note">Hanya pemasukan dan pengeluaran berstatus terverifikasi yang dihitung. Saldo akhir berasal dari akun dana yang ditandai masuk total.</p>
    <?php endif; ?>

    <div class="section-title">Rincian pemasukan</div>
    <table class="summary-grid">
        <tr>
            <td><span class="summary-label">Event</span><span class="summary-value"><?= number_format((int)($income['event_count'] ?? 0)) ?></span></td>
            <td><span class="summary-label">Desa terdaftar</span><span class="summary-value"><?= number_format((int)($income['villages'] ?? 0)) ?></span></td>
            <td><span class="summary-label">Peserta</span><span class="summary-value"><?= number_format((int)($income['participants'] ?? 0)) ?></span></td>
            <td><span class="summary-label">Peserta tambahan</span><span class="summary-value"><?= number_format((int)($income['additional_participants'] ?? 0)) ?></span></td>
            <td><span class="summary-label">Tunai · Transfer · QRIS</span><span class="summary-value"><?= e($printRupiah($income['cash_total'] ?? 0, TRUE)) ?> · <?= e($printRupiah($income['transfer_total'] ?? 0, TRUE)) ?> · <?= e($printRupiah($income['qris_total'] ?? 0, TRUE)) ?></span></td>
            <td><span class="summary-label">Total pemasukan</span><span class="summary-value positive"><?= e($printRupiah($income['income'] ?? 0)) ?></span></td>
        </tr>
    </table>
    <table class="report-table">
        <colgroup><col style="width:4%"><col style="width:32%"><col style="width:16%"><col style="width:10%"><col style="width:10%"><col style="width:12%"><col style="width:16%"></colgroup>
        <thead><tr><th>No.</th><th>Event</th><th>Mode tagihan</th><th>Desa</th><th>Peserta</th><th>Tambahan</th><th class="money">Dana masuk</th></tr></thead>
        <tbody>
        <?php if (!$events): ?><tr class="empty-row"><td colspan="7">Belum ada pemasukan atau event aktif pada periode ini.</td></tr><?php endif; ?>
        <?php foreach ($events as $index => $event): ?>
            <tr>
                <td class="number"><?= number_format($index + 1) ?></td>
                <td><span class="primary"><?= e($event['name']) ?></span><span class="secondary"><?= e($event['code']) ?> · <?= e(tanggal_id($event['start_date'])) ?> s.d. <?= e(tanggal_id($event['end_date'])) ?></span></td>
                <td><span class="mode-badge"><?= e(isset($modeLabels[$event['billing_mode']]) ? $modeLabels[$event['billing_mode']] : ucfirst((string)$event['billing_mode'])) ?></span></td>
                <td class="number"><?= number_format((int)$event['villages']) ?></td>
                <td class="number"><?= number_format((int)$event['participants']) ?></td>
                <td class="number"><?= number_format((int)$event['additional_participants']) ?></td>
                <td class="money"><span class="primary"><?= e($printRupiah($event['income'])) ?></span><span class="secondary">Tunai <?= e($printRupiah($event['cash_total'], FALSE)) ?> · TF <?= e($printRupiah($event['transfer_total'], FALSE)) ?> · QRIS <?= e($printRupiah($event['qris_total'], FALSE)) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($events): ?><tfoot><tr><td colspan="3">TOTAL PEMASUKAN</td><td class="number"><?= number_format((int)($income['villages'] ?? 0)) ?></td><td class="number"><?= number_format((int)($income['participants'] ?? 0)) ?></td><td class="number"><?= number_format((int)($income['additional_participants'] ?? 0)) ?></td><td class="money"><?= e($printRupiah($income['income'] ?? 0)) ?></td></tr></tfoot><?php endif; ?>
    </table>

    <div class="section-title">Rincian pengeluaran per kategori</div>
    <table class="report-table">
        <colgroup><col style="width:5%"><col style="width:45%"><col style="width:15%"><col style="width:13%"><col style="width:22%"></colgroup>
        <thead><tr><th>No.</th><th>Kategori</th><th class="number">Transaksi</th><th class="money">Biaya admin</th><th class="money">Total pengeluaran</th></tr></thead>
        <tbody>
        <?php if (!$expenseCategories): ?><tr class="empty-row"><td colspan="5">Belum ada pengeluaran terverifikasi pada periode ini.</td></tr><?php endif; ?>
        <?php foreach ($expenseCategories as $index => $category): ?>
            <tr>
                <td class="number"><?= number_format($index + 1) ?></td>
                <td><span class="primary"><?= e($category['name']) ?></span><span class="secondary">Nilai pokok <?= e($printRupiah($category['amount'])) ?></span></td>
                <td class="number"><?= number_format((int)$category['transaction_count']) ?></td>
                <td class="money"><?= e($printRupiah($category['admin_fee'])) ?></td>
                <td class="money"><span class="primary"><?= e($printRupiah($category['total'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($expenseCategories): ?><tfoot><tr><td colspan="4">TOTAL SELURUH PENGELUARAN</td><td class="money"><?= e($printRupiah($expenseGrand)) ?></td></tr></tfoot><?php endif; ?>
    </table>

    <table class="document-foot"><tr><td>Dokumen ini merangkum transaksi terverifikasi pada periode yang dipilih. Sisa dana adalah saldo akhir akun yang masuk total.</td></tr></table>
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
        userZoom = Math.max(50, Math.min(1000, Math.round(Number(percent) || 100)));
        fitSheet();
        return userZoom;
    }
    window.SIMPPrintPreview = { getZoom:function(){return userZoom;}, setZoom:setZoom, reset:function(){return setZoom(100);} };
    fitSheet();
    window.addEventListener('resize', fitSheet);
}());
</script>
<script src="<?= e(base_url('assets/js/print-preview.js')) ?>?v=3"></script>
<?php endif; ?>
</body>
</html>
