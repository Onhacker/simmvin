<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$validReportKinds = array('income', 'expense', 'accounts', 'debt');
$reportKind = isset($reportKind) && in_array($reportKind, $validReportKinds, TRUE) ? $reportKind : 'income';
$documentTitle = isset($documentTitle) ? (string) $documentTitle : 'Laporan';
$organizationName = isset($organizationName) ? (string) $organizationName : 'Penyelenggara Pelatihan';
$activeEvents = isset($activeEvents) && is_array($activeEvents) ? $activeEvents : array();
$rows = isset($rows) && is_array($rows) ? $rows : array();
$debtSummary = isset($summary) && is_array($summary) ? $summary : array();
$accounts = isset($accounts) && is_array($accounts) ? $accounts : array();
$accountSummary = isset($accountSummary) && is_array($accountSummary) ? $accountSummary : array();
$generatedAt = isset($generatedAt) ? $generatedAt : date('Y-m-d H:i:s');
$isPdf = !empty($isPdf);
$methodLabels = array('cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS');
$expenseStatusLabels = array('verified' => 'Terverifikasi', 'pending' => 'Menunggu', 'rejected' => 'Ditolak');
$printRupiah = function ($value, $withPrefix = TRUE) {
    return rupiah($value, $withPrefix);
};
$moneyCents = function ($value) {
    $cents = simp_money_cents($value);
    return $cents === NULL ? 0 : $cents;
};

$hasEventScope = isset($eventScope) && trim((string) $eventScope) !== '';
$eventScope = $hasEventScope ? (string) $eventScope : 'Belum ada event aktif';
if (count($activeEvents) === 1 && !$hasEventScope) {
    $eventScope = $activeEvents[0]['name'] . ' (' . $activeEvents[0]['code'] . ')';
} elseif (count($activeEvents) > 1 && !$hasEventScope) {
    $eventScope = number_format(count($activeEvents)) . ' event aktif';
}

$expenseSummary = array(
    'verified_count' => 0, 'pending_count' => 0, 'rejected_count' => 0,
    'verified_total' => 0, 'pending_total' => 0, 'rejected_total' => 0,
    'cash_total' => 0, 'transfer_total' => 0, 'qris_total' => 0
);
$expenseGroups = array();
if ($reportKind === 'expense') {
    foreach ($rows as $expenseRow) {
        $status = isset($expenseRow['status']) ? $expenseRow['status'] : 'pending';
        $total = $moneyCents($expenseRow['amount']) + $moneyCents($expenseRow['admin_fee']);
        if (isset($expenseSummary[$status . '_count'])) {
            $expenseSummary[$status . '_count']++;
            $expenseSummary[$status . '_total'] += $total;
        }
        if ($status === 'verified' && isset($expenseSummary[$expenseRow['method'] . '_total'])) {
            $expenseSummary[$expenseRow['method'] . '_total'] += $total;
        }

        // Keep the printout in the same order as the master category list,
        // with uncategorised transactions collected at the end.
        $categoryId = isset($expenseRow['category_id']) ? (int) $expenseRow['category_id'] : 0;
        $categoryName = trim((string) (isset($expenseRow['category_name']) ? $expenseRow['category_name'] : ''));
        if ($categoryName === '') {
            $categoryName = 'Tanpa Kategori';
        }
        $groupKey = $categoryId > 0
            ? sprintf('%010d', $categoryId)
            : '9999999999-' . strtolower($categoryName);
        if (!isset($expenseGroups[$groupKey])) {
            $expenseGroups[$groupKey] = array(
                'category_id' => $categoryId,
                'name' => $categoryName,
                'rows' => array(),
                'total_cents' => 0
            );
        }
        $expenseGroups[$groupKey]['rows'][] = $expenseRow;
        $expenseGroups[$groupKey]['total_cents'] += $total;
    }
    ksort($expenseGroups, SORT_STRING);
    foreach (array('verified_total','pending_total','rejected_total','cash_total','transfer_total','qris_total') as $moneyKey) {
        $expenseSummary[$moneyKey] = simp_money_from_signed_cents($expenseSummary[$moneyKey]);
    }
}
$accountSummary = array_merge(array(
    'account_count'=>count($accounts), 'active_count'=>0, 'inactive_count'=>0,
    'included_count'=>0, 'excluded_count'=>0, 'included_balance'=>0,
    'excluded_balance'=>0, 'all_balance'=>0, 'opening_balance'=>0,
    'net_movement'=>0
), $accountSummary);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=10, user-scalable=yes">
    <title><?= e($documentTitle) ?> | MVIN</title>
    <style>
        @page { size: 210mm 330mm; margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; color: #111827; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9px; line-height: 1.45; touch-action: pan-x pan-y; }
        body { background: #e9eef5; }
        .screen-note { width: 210mm; max-width: calc(100% - 24px); margin: 14px auto 0; padding: 9px 12px; border: 1px solid #bfd2ee; border-radius: 8px; background: #eef5ff; color: #174b8b; font-size: 12px; text-align: center; }
        .sheet-stage { width: 210mm; min-height: 330mm; margin: 14px auto 24px; }
        .sheet { width: 210mm; min-height: 330mm; margin: 0; padding: 12mm 10mm 14mm; background: #fff; box-shadow: 0 10px 34px rgba(15, 23, 42, .14); transform-origin: top left; }
        .document-head, .meta-table, .summary-grid, .report-table, .document-foot { width: 100%; border-collapse: collapse; }
        .document-head td { vertical-align: top; padding: 0 0 7px; border-bottom: 2px solid #1f5fab; }
        .brand-code { color: #1f5fab; font-size: 23px; font-weight: 800; letter-spacing: 1px; line-height: 1; }
        .organization { margin-top: 4px; color: #374151; font-size: 10px; font-weight: 700; }
        .title-block { text-align: right; }
        .title-block h1 { margin: 0; color: #111827; font-size: 19px; line-height: 1.15; }
        .title-block p { margin: 4px 0 0; color: #4b5563; font-size: 9px; }
        .meta-table { margin: 8px 0 9px; }
        .meta-table td { padding: 2px 0; vertical-align: top; }
        .meta-label { width: 82px; color: #6b7280; }
        .meta-separator { width: 10px; color: #6b7280; }
        .meta-value { font-weight: 700; }
        .event-list { margin: 0 0 9px; padding: 6px 8px; border-left: 3px solid #1f5fab; background: #f3f7fc; }
        .event-list strong { display: block; margin-bottom: 2px; color: #1f5fab; }
        .event-list span { display: block; }
        .section-title { margin: 10px 0 5px; color: #1f5fab; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .35px; }
        .summary-grid { table-layout: fixed; margin-bottom: 8px; }
        .summary-grid td { width: 25%; padding: 6px 7px; border: 1px solid #d7dee8; vertical-align: top; }
        .summary-label { display: block; color: #6b7280; font-size: 7.5px; text-transform: uppercase; letter-spacing: .2px; }
        .summary-value { display: block; margin-top: 2px; color: #111827; font-size: 11px; font-weight: 800; }
        .summary-value.positive { color: #18743d; }
        .summary-value.negative { color: #b42318; }
        .report-table { table-layout: fixed; }
        .report-table thead { display: table-header-group; }
        .report-table tr { page-break-inside: avoid; }
        .report-table th { padding: 5px 4px; border: 1px solid #9eacbd; background: #1f5fab; color: #fff; font-size: 7.3px; line-height: 1.25; text-align: left; text-transform: uppercase; }
        .report-table td { padding: 5px 4px; border: 1px solid #cfd7e2; vertical-align: top; overflow-wrap: break-word; }
        .report-table tbody tr:nth-child(even) td { background: #f7f9fc; }
        .report-table .number { text-align: center; }
        .report-table .money { text-align: right; white-space: nowrap; }
        .report-table .primary { display: block; font-weight: 700; }
        .report-table .secondary { display: block; margin-top: 1px; color: #5f6b7a; font-size: 7.2px; line-height: 1.3; }
        .report-note { margin: -1px 0 6px; padding: 5px 7px; border-left: 3px solid #1f5fab; background: #f3f7fc; color: #4b5563; font-size: 7.8px; }
        .expense-category-heading { margin: 12px 0 4px; padding: 6px 8px; border-left: 4px solid #1f5fab; background: #eaf2fc; color: #174b8b; page-break-after: avoid; break-after: avoid; }
        .expense-category-heading span { display: block; color: #6b7280; font-size: 7px; font-weight: 700; letter-spacing: .25px; line-height: 1.1; text-transform: uppercase; }
        .expense-category-heading strong { display: block; margin-top: 2px; color: #174b8b; font-size: 12px; line-height: 1.2; }
        .expense-category-table { margin-bottom: 7px; }
        .expense-category-table .category-total-row td { background: #eef5ff !important; border-top: 2px solid #1f5fab; color: #174b8b; font-weight: 800; }
        .expense-category-table .category-total-row .money-label,
        .expense-grand-total .money-label { text-align: right; }
        .expense-grand-total { margin-top: 12px; }
        .expense-grand-total td { background: #dcecff !important; border-top: 2px solid #174b8b; color: #111827; font-size: 10px; font-weight: 800; }
        .status { display: inline-block; padding: 2px 4px; border-radius: 3px; color: #fff; font-size: 6.8px; font-weight: 700; line-height: 1.25; text-align: center; }
        .status.green { background: #1f7a45; }
        .status.yellow { background: #a76608; }
        .status.red { background: #b42318; }
        .status.blue { background: #1f5fab; }
        .empty-row td { padding: 20px 8px; color: #6b7280; text-align: center; }
        .document-foot { margin-top: 10px; border-top: 1px solid #cfd7e2; }
        .document-foot td { padding-top: 5px; color: #6b7280; font-size: 7.5px; vertical-align: top; }
        .document-foot .right { text-align: right; }
        @media print {
            html, body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .screen-note { display: none !important; }
            .sheet-stage { width: auto !important; height: auto !important; min-height: 0 !important; margin: 0 !important; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; transform: none !important; }
            .report-table th { background: #e8eef7 !important; color: #111827 !important; border-color: #6b7280 !important; }
            .status { background: #fff !important; color: #111827 !important; border: 1px solid #6b7280 !important; }
        }
        @media screen and (max-width: 820px) {
            .screen-note { margin-left: 12px; margin-right: 12px; }
            .sheet-stage { margin-top: 12px; margin-bottom: 12px; }
        }
    </style>
</head>
<body>
<?php if (!$isPdf): ?>
    <div class="screen-note">Pratinjau dokumen pada kertas F4 210 × 330 mm.</div>
<?php endif; ?>
<div class="sheet-stage" data-sheet-stage>
<article class="sheet" data-print-sheet>
    <table class="document-head">
        <tr>
            <td>
                <div class="brand-code">MVIN</div>
                <div class="organization"><?= e($organizationName) ?></div>
            </td>
            <td class="title-block">
                <h1><?= e($documentTitle) ?></h1>
                <p><?php
                    if ($reportKind === 'income') echo 'Rekap dana masuk event pelatihan';
                    elseif ($reportKind === 'expense') echo 'Rekap dana keluar event pelatihan';
                    elseif ($reportKind === 'debt') echo 'Rekap kewajiban dan pembayaran perusahaan';
                    else echo 'Posisi saldo kas dan rekening';
                ?></p>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr><td class="meta-label">Lingkup data</td><td class="meta-separator">:</td><td class="meta-value"><?= e($reportKind === 'accounts' ? 'Seluruh akun dana' : $eventScope) ?></td></tr>
        <?php if ($reportKind === 'income'): ?>
            <tr><td class="meta-label">Rincian</td><td class="meta-separator">:</td><td class="meta-value"><?= $report['view'] === 'participant' ? 'Per peserta' : 'Per desa' ?></td></tr>
        <?php elseif ($reportKind === 'accounts'): ?>
            <tr><td class="meta-label">Dasar saldo</td><td class="meta-separator">:</td><td class="meta-value">Saldo awal + mutasi buku besar</td></tr>
        <?php elseif ($reportKind === 'debt'): ?>
            <tr><td class="meta-label">Rincian</td><td class="meta-separator">:</td><td class="meta-value">Hutang dan riwayat pembayaran</td></tr>
        <?php endif; ?>
        <tr><td class="meta-label">Dibuat</td><td class="meta-separator">:</td><td class="meta-value"><?= e(tanggal_id($generatedAt, TRUE)) ?></td></tr>
    </table>

    <?php if (in_array($reportKind, array('income', 'expense'), TRUE) && count($activeEvents) > 1): ?>
        <div class="event-list">
            <strong>Event yang disertakan</strong>
            <?php foreach ($activeEvents as $event): ?>
                <span><?= e($event['code']) ?> · <?= e($event['name']) ?> (<?= e(tanggal_id($event['start_date'])) ?> s.d. <?= e(tanggal_id($event['end_date'])) ?>)</span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-title">Ringkasan</div>
    <?php if ($reportKind === 'income'): ?>
        <?php $summary = $report['summary']; ?>
        <table class="summary-grid">
            <tr>
                <td><span class="summary-label">Jumlah Desa</span><span class="summary-value"><?= number_format((int) $summary['villages']) ?></span></td>
                <td><span class="summary-label">Jumlah Peserta</span><span class="summary-value"><?= number_format((int) $summary['participants']) ?></span></td>
                <td><span class="summary-label">Total Tagihan</span><span class="summary-value"><?= e($printRupiah($summary['total_due'])) ?></span></td>
                <td><span class="summary-label">Total Masuk</span><span class="summary-value positive"><?= e($printRupiah($summary['income'])) ?></span></td>
            </tr>
            <tr>
                <td><span class="summary-label">Tunai</span><span class="summary-value"><?= e($printRupiah($summary['cash_total'])) ?></span></td>
                <td><span class="summary-label">Transfer</span><span class="summary-value"><?= e($printRupiah($summary['transfer_total'])) ?></span></td>
                <td><span class="summary-label">QRIS</span><span class="summary-value"><?= e($printRupiah($summary['qris_total'])) ?></span></td>
                <td><span class="summary-label">Sisa Tagihan</span><span class="summary-value <?= $summary['outstanding'] > 0 ? 'negative' : 'positive' ?>"><?= e($printRupiah($summary['outstanding'])) ?></span></td>
            </tr>
        </table>
    <?php elseif ($reportKind === 'expense'): ?>
        <table class="summary-grid">
            <tr>
                <td><span class="summary-label">Jumlah Transaksi</span><span class="summary-value"><?= number_format(count($rows)) ?></span></td>
                <td><span class="summary-label">Terverifikasi</span><span class="summary-value positive"><?= e($printRupiah($expenseSummary['verified_total'])) ?></span></td>
                <td><span class="summary-label">Menunggu</span><span class="summary-value"><?= e($printRupiah($expenseSummary['pending_total'])) ?></span></td>
                <td><span class="summary-label">Ditolak</span><span class="summary-value negative"><?= e($printRupiah($expenseSummary['rejected_total'])) ?></span></td>
            </tr>
            <tr>
                <td><span class="summary-label">Tunai Terverifikasi</span><span class="summary-value"><?= e($printRupiah($expenseSummary['cash_total'])) ?></span></td>
                <td><span class="summary-label">Transfer Terverifikasi</span><span class="summary-value"><?= e($printRupiah($expenseSummary['transfer_total'])) ?></span></td>
                <td><span class="summary-label">QRIS Terverifikasi</span><span class="summary-value"><?= e($printRupiah($expenseSummary['qris_total'])) ?></span></td>
                <td><span class="summary-label">Data Status</span><span class="summary-value"><?= number_format($expenseSummary['verified_count']) ?> / <?= number_format($expenseSummary['pending_count']) ?> / <?= number_format($expenseSummary['rejected_count']) ?></span></td>
            </tr>
        </table>
    <?php elseif ($reportKind === 'debt'): ?>
        <?php $debtSummary = array_merge(array(
            'count' => 0, 'total_principal' => 0, 'total_paid' => 0,
            'total_pending' => 0, 'total_outstanding' => 0
        ), $debtSummary); ?>
        <table class="summary-grid">
            <tr>
                <td><span class="summary-label">Jumlah Hutang</span><span class="summary-value"><?= number_format((int) $debtSummary['count']) ?></span></td>
                <td><span class="summary-label">Total Hutang</span><span class="summary-value"><?= e($printRupiah($debtSummary['total_principal'])) ?></span></td>
                <td><span class="summary-label">Sudah Dibayar</span><span class="summary-value positive"><?= e($printRupiah($debtSummary['total_paid'])) ?></span></td>
                <td><span class="summary-label">Sisa Hutang</span><span class="summary-value <?= $moneyCents($debtSummary['total_outstanding']) > 0 ? 'negative' : 'positive' ?>"><?= e($printRupiah($debtSummary['total_outstanding'])) ?></span></td>
            </tr>
            <tr>
                <td><span class="summary-label">Menunggu Verifikasi</span><span class="summary-value"><?= e($printRupiah($debtSummary['total_pending'])) ?></span></td>
                <td><span class="summary-label">Belum Dibayar</span><span class="summary-value"><?= number_format((int) ($debtSummary['open_count'] ?? 0)) ?></span></td>
                <td><span class="summary-label">Lunas</span><span class="summary-value positive"><?= number_format((int) ($debtSummary['paid_count'] ?? 0)) ?></span></td>
                <td><span class="summary-label">Dibatalkan</span><span class="summary-value"><?= number_format((int) ($debtSummary['cancelled_count'] ?? 0)) ?></span></td>
            </tr>
        </table>
    <?php else: ?>
        <table class="summary-grid">
            <tr>
                <td><span class="summary-label">Total Saldo Dihitung</span><span class="summary-value positive"><?= e($printRupiah($accountSummary['included_balance'])) ?></span></td>
                <td><span class="summary-label">Saldo Seluruh Akun</span><span class="summary-value"><?= e($printRupiah($accountSummary['all_balance'])) ?></span></td>
                <td><span class="summary-label">Saldo Awal</span><span class="summary-value"><?= e($printRupiah($accountSummary['opening_balance'])) ?></span></td>
                <td><span class="summary-label">Mutasi Bersih</span><span class="summary-value <?= $accountSummary['net_movement'] < 0 ? 'negative' : 'positive' ?>"><?= e($printRupiah($accountSummary['net_movement'])) ?></span></td>
            </tr>
            <tr>
                <td><span class="summary-label">Jumlah Akun</span><span class="summary-value"><?= number_format((int)$accountSummary['account_count']) ?></span></td>
                <td><span class="summary-label">Akun Aktif</span><span class="summary-value positive"><?= number_format((int)$accountSummary['active_count']) ?></span></td>
                <td><span class="summary-label">Akun Nonaktif</span><span class="summary-value"><?= number_format((int)$accountSummary['inactive_count']) ?></span></td>
                <td><span class="summary-label">Masuk Total / Dikecualikan</span><span class="summary-value"><?= number_format((int)$accountSummary['included_count']) ?> / <?= number_format((int)$accountSummary['excluded_count']) ?></span></td>
            </tr>
        </table>
    <?php endif; ?>

    <div class="section-title"><?= $reportKind === 'accounts' ? 'Rincian Akun Dana' : ($reportKind === 'debt' ? 'Rincian Hutang' : 'Rincian Transaksi') ?></div>
    <?php if ($reportKind === 'income'): ?>
        <?php
        $hasVillageLevelParticipantRows = FALSE;
        if ($report['view'] === 'participant') {
            foreach ($report['rows'] as $participantReportRow) {
                if ($participantReportRow['billing_mode'] !== 'per_participant') {
                    $hasVillageLevelParticipantRows = TRUE;
                    break;
                }
            }
        }
        ?>
        <?php if ($hasVillageLevelParticipantRows): ?>
            <p class="report-note">Ringkasan tetap mencakup tagihan dan pembayaran tingkat desa. Pada rincian peserta, event per desa menampilkan penanda pencatatan desa, sedangkan skema paket hanya menampilkan komponen peserta yang termasuk paket atau peserta tambahan.</p>
        <?php endif; ?>
        <table class="report-table">
            <colgroup><col style="width:4%"><col style="width:29%"><col style="width:18%"><col style="width:13%"><col style="width:16%"><col style="width:11%"><col style="width:9%"></colgroup>
            <thead><tr><th>No.</th><th><?= $report['view'] === 'participant' ? 'Peserta / Desa' : 'Desa / Event' ?></th><th><?= $report['view'] === 'participant' ? 'Jabatan / Wilayah' : 'Wilayah / Peserta' ?></th><th class="money">Tagihan</th><th class="money">Dana Masuk (Terverifikasi)</th><th class="money">Sisa Tagihan</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$report['rows']): ?><tr class="empty-row"><td colspan="7">Belum ada data registrasi pada event aktif.</td></tr><?php endif; ?>
            <?php foreach ($report['rows'] as $index => $row): ?>
                <?php
                $participantView = $report['view'] === 'participant';
                $pureVillageParticipant = $participantView && $row['billing_mode'] === 'per_village';
                $hybridParticipant = $participantView && $row['billing_mode'] === 'per_village_extra';
                $villageBillingParticipant = $pureVillageParticipant || $hybridParticipant;
                $dueCents = $moneyCents($row['due_amount']);
                $paidCents = $moneyCents($row['paid']);
                $extraParticipant = $hybridParticipant && $dueCents > 0;
                $remainingCents = max(0, $dueCents - $paidCents);
                $due = simp_money_from_cents($dueCents);
                $paid = simp_money_from_cents($paidCents);
                $remaining = simp_money_from_cents($remainingCents);
                $paymentState = payment_status($paid, $due);
                $statusClass = $paymentState === 'lunas' ? 'green' : ($paymentState === 'lebih_bayar' ? 'blue' : ($paymentState === 'sebagian' ? 'yellow' : 'red'));
                $statusLabel = $paymentState === 'lunas' ? 'Lunas' : ($paymentState === 'lebih_bayar' ? 'Lebih Bayar' : ($paymentState === 'sebagian' ? 'Sebagian' : 'Belum Bayar'));
                if ($villageBillingParticipant) {
                    $statusClass = 'blue';
                    $statusLabel = $pureVillageParticipant ? 'Dicatat di Desa' : ($extraParticipant ? 'Peserta Tambahan' : 'Termasuk Paket Desa');
                }
                ?>
                <tr>
                    <td class="number"><?= number_format($index + 1) ?></td>
                    <td><span class="primary"><?= e($participantView ? $row['participant_name'] : $row['village_name']) ?></span><span class="secondary"><?= $participantView ? e($row['village_name']) . ' · ' : '' ?><?= e($row['event_name']) ?></span></td>
                    <td><span class="primary"><?= e($participantView ? $row['position'] : $row['district_name']) ?></span><span class="secondary"><?= $participantView ? e($row['district_name'] . ', ' . $row['regency_name']) : e($row['regency_name']) . ' · ' . number_format((int) $row['participant_count']) . ' peserta' ?></span></td>
                    <?php if ($villageBillingParticipant): ?>
                        <td class="money"><?= $pureVillageParticipant ? '-' : ($extraParticipant ? e($printRupiah($due)) : 'Termasuk paket') ?></td>
                        <td class="money"><span class="secondary">Dicatat di desa</span></td>
                        <td class="money">-</td>
                    <?php else: ?>
                        <td class="money"><?= e($printRupiah($due)) ?></td>
                        <td class="money"><span class="primary"><?= e($printRupiah($paid)) ?></span><span class="secondary">T <?= e($printRupiah($row['cash_total'], FALSE)) ?> · TF <?= e($printRupiah($row['transfer_total'], FALSE)) ?> · Q <?= e($printRupiah($row['qris_total'], FALSE)) ?></span></td>
                        <td class="money"><?= e($printRupiah($remaining)) ?></td>
                    <?php endif; ?>
                    <td><span class="status <?= $statusClass ?>"><?= e($statusLabel) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($reportKind === 'expense'): ?>
        <?php if (!$expenseGroups): ?>
            <table class="report-table">
                <colgroup><col style="width:4%"><col style="width:14%"><col style="width:31%"><col style="width:17%"><col style="width:10%"><col style="width:12%"><col style="width:12%"></colgroup>
                <thead><tr><th>No.</th><th>Tanggal / Nomor</th><th>Tujuan / Event</th><th>Akun / Metode</th><th>Status</th><th class="money">Nilai</th><th class="money">Total</th></tr></thead>
                <tbody><tr class="empty-row"><td colspan="7">Belum ada transaksi pengeluaran pada event aktif.</td></tr></tbody>
            </table>
        <?php else: ?>
            <?php $expenseNo = 0; $grandExpenseCents = 0; ?>
            <?php foreach ($expenseGroups as $expenseGroup): ?>
                <?php
                $categoryTotalCents = (int) $expenseGroup['total_cents'];
                $grandExpenseCents += $categoryTotalCents;
                $categoryTotal = simp_money_from_cents($categoryTotalCents);
                ?>
                <div class="expense-category-heading"><span>Kategori</span><strong><?= e($expenseGroup['name']) ?></strong></div>
                <table class="report-table expense-category-table">
                    <colgroup><col style="width:4%"><col style="width:14%"><col style="width:34%"><col style="width:17%"><col style="width:10%"><col style="width:10%"><col style="width:11%"></colgroup>
                    <thead><tr><th>No.</th><th>Tanggal / Nomor</th><th>Tujuan / Event</th><th>Akun / Metode</th><th>Status</th><th class="money">Nilai</th><th class="money">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($expenseGroup['rows'] as $row): ?>
                        <?php
                        $expenseNo++;
                        $amountCents = $moneyCents($row['amount']);
                        $adminFeeCents = $moneyCents($row['admin_fee']);
                        $amount = simp_money_from_cents($amountCents);
                        $adminFee = simp_money_from_cents($adminFeeCents);
                        $total = simp_money_from_cents($amountCents + $adminFeeCents);
                        $status = isset($row['status']) ? $row['status'] : 'pending';
                        $statusClass = $status === 'verified' ? 'green' : ($status === 'rejected' ? 'red' : 'yellow');
                        $eventName = !empty($row['event_name']) ? $row['event_name'] : 'Pengeluaran umum';
                        ?>
                        <tr>
                            <td class="number"><?= number_format($expenseNo) ?></td>
                            <td><span class="primary"><?= e(tanggal_id($row['expense_date'])) ?></span><span class="secondary"><?= e($row['expense_no']) ?></span></td>
                            <td><span class="primary"><?= e($row['description']) ?></span><span class="secondary"><?= e($eventName) ?><?php if (!empty($row['debt_id'])): ?> · Pembayaran <?= e($row['debt_no']) ?> (<?= e($row['debt_creditor']) ?>)<?php endif; ?></span></td>
                            <td><span class="primary"><?= e($row['account_name']) ?></span><span class="secondary"><?= e(isset($methodLabels[$row['method']]) ? $methodLabels[$row['method']] : ucfirst((string) $row['method'])) ?></span></td>
                            <td><span class="status <?= $statusClass ?>"><?= e(isset($expenseStatusLabels[$status]) ? $expenseStatusLabels[$status] : ucwords(str_replace('_', ' ', $status))) ?></span></td>
                            <td class="money"><?= e($printRupiah($amount)) ?></td>
                            <td class="money"><span class="primary"><?= e($printRupiah($total)) ?></span><?php if ($adminFeeCents > 0): ?><span class="secondary">Admin <?= e($printRupiah($adminFee, FALSE)) ?></span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="category-total-row"><td colspan="6" class="money-label">Total <?= e($expenseGroup['name']) ?></td><td class="money"><?= e($printRupiah($categoryTotal)) ?></td></tr></tfoot>
                </table>
            <?php endforeach; ?>
            <?php $grandExpense = simp_money_from_cents($grandExpenseCents); ?>
            <table class="report-table expense-grand-total">
                <colgroup><col style="width:4%"><col style="width:14%"><col style="width:34%"><col style="width:17%"><col style="width:10%"><col style="width:10%"><col style="width:11%"></colgroup>
                <tfoot><tr><td colspan="6" class="money-label">TOTAL SELURUH PENGELUARAN</td><td class="money"><?= e($printRupiah($grandExpense)) ?></td></tr></tfoot>
            </table>
        <?php endif; ?>
    <?php elseif ($reportKind === 'debt'): ?>
        <p class="report-note">Pembayaran terverifikasi mengurangi saldo hutang. Pembayaran yang masih menunggu verifikasi tetap tercantum sebagai komitmen.</p>
        <table class="report-table">
            <colgroup><col style="width:4%"><col style="width:14%"><col style="width:24%"><col style="width:15%"><col style="width:12%"><col style="width:12%"><col style="width:11%"><col style="width:8%"></colgroup>
            <thead><tr><th>No.</th><th>Nomor / Tanggal</th><th>Kreditur / Uraian</th><th>Event / Kategori</th><th class="money">Nilai Pokok</th><th class="money">Terbayar</th><th class="money">Sisa</th><th>Status</th></tr></thead>
            <tbody>
            <?php $debtPaymentRows = array(); ?>
            <?php if (!$rows): ?><tr class="empty-row"><td colspan="8">Belum ada hutang perusahaan.</td></tr><?php endif; ?>
            <?php foreach ($rows as $index => $row): ?>
                <?php
                $principalCents = $moneyCents($row['principal_amount'] ?? 0);
                $paidCents = $moneyCents($row['paid_amount'] ?? 0);
                $pendingCents = $moneyCents($row['pending_amount'] ?? 0);
                $remainingCents = max(0, $principalCents - $paidCents);
                $principal = simp_money_from_cents($principalCents);
                $paid = simp_money_from_cents($paidCents);
                $pending = simp_money_from_cents($pendingCents);
                $remaining = simp_money_from_cents($remainingCents);
                $status = isset($row['computed_status']) ? $row['computed_status'] : (isset($row['status']) ? $row['status'] : 'open');
                if ($status === 'cancelled') {
                    $statusLabel = 'Dibatalkan'; $statusClass = 'red';
                } elseif ($status === 'paid' || $remainingCents <= 0) {
                    $statusLabel = 'Lunas'; $statusClass = 'green';
                } elseif ($pendingCents > 0) {
                    $statusLabel = 'Menunggu Verifikasi'; $statusClass = 'yellow';
                } elseif ($paidCents > 0) {
                    $statusLabel = 'Bayar Sebagian'; $statusClass = 'blue';
                } else {
                    $statusLabel = 'Belum Dibayar'; $statusClass = 'red';
                }
                $payments = isset($row['payments']) && is_array($row['payments']) ? $row['payments'] : array();
                foreach ($payments as $payment) {
                    $payment['debt_no'] = isset($row['debt_no']) ? $row['debt_no'] : '';
                    $payment['creditor'] = isset($row['creditor']) ? $row['creditor'] : '';
                    $debtPaymentRows[] = $payment;
                }
                ?>
                <tr>
                    <td class="number"><?= number_format($index + 1) ?></td>
                    <td><span class="primary"><?= e($row['debt_no'] ?? '-') ?></span><span class="secondary"><?= e(tanggal_id($row['debt_date'] ?? '')) ?></span></td>
                    <td><span class="primary"><?= e($row['creditor'] ?? '-') ?></span><span class="secondary"><?= e($row['description'] ?? '-') ?></span></td>
                    <td><span class="primary"><?= e($row['event_name'] ?: 'Hutang umum') ?></span><span class="secondary"><?= e($row['category_name'] ?: 'Tanpa kategori') ?></span></td>
                    <td class="money"><?= e($printRupiah($principal)) ?></td>
                    <td class="money"><span class="primary"><?= e($printRupiah($paid)) ?></span><span class="secondary"><?= $pendingCents > 0 ? 'Menunggu ' . e($printRupiah($pending, FALSE)) : number_format(count($payments)) . ' pembayaran' ?></span></td>
                    <td class="money"><?= e($printRupiah($remaining)) ?></td>
                    <td><span class="status <?= $statusClass ?>"><?= e($statusLabel) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($debtPaymentRows): ?>
            <div class="section-title">Riwayat Pembayaran Hutang</div>
            <table class="report-table">
                <colgroup><col style="width:4%"><col style="width:23%"><col style="width:13%"><col style="width:20%"><col style="width:13%"><col style="width:12%"><col style="width:15%"></colgroup>
                <thead><tr><th>No.</th><th>Hutang / Kreditur</th><th>Tanggal</th><th>Akun / Metode</th><th class="money">Nominal</th><th class="money">Biaya Admin</th><th>Status / Catatan</th></tr></thead>
                <tbody>
                <?php foreach ($debtPaymentRows as $paymentIndex => $payment): ?>
                    <?php
                    $paymentStatus = isset($payment['status']) ? $payment['status'] : 'pending';
                    $paymentStatusClass = $paymentStatus === 'verified' ? 'green' : ($paymentStatus === 'rejected' ? 'red' : 'yellow');
                    $paymentStatusLabel = $paymentStatus === 'verified' ? 'Terverifikasi' : ($paymentStatus === 'rejected' ? 'Ditolak' : 'Menunggu');
                    $paymentMethod = isset($methodLabels[$payment['method'] ?? '']) ? $methodLabels[$payment['method']] : ucfirst((string) ($payment['method'] ?? '-'));
                    ?>
                    <tr>
                        <td class="number"><?= number_format($paymentIndex + 1) ?></td>
                        <td><span class="primary"><?= e($payment['debt_no'] ?? '-') ?></span><span class="secondary"><?= e($payment['creditor'] ?? '-') ?></span></td>
                        <td><?= e(tanggal_id($payment['expense_date'] ?? ($payment['payment_date'] ?? ''))) ?></td>
                        <td><span class="primary"><?= e($payment['account_name'] ?? '-') ?></span><span class="secondary"><?= e($paymentMethod) ?></span></td>
                        <td class="money"><?= e($printRupiah($payment['amount'] ?? 0)) ?></td>
                        <td class="money"><?= e($printRupiah($payment['admin_fee'] ?? 0)) ?></td>
                        <td><span class="status <?= $paymentStatusClass ?>"><?= e($paymentStatusLabel) ?></span><?php if (!empty($payment['note'])): ?><span class="secondary"><?= e($payment['note']) ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <p class="report-note">Total Saldo Dihitung hanya menjumlah akun bertanda “Ya”. Saldo akhir setiap akun berasal dari saldo awal ditambah seluruh mutasi buku besar.</p>
        <table class="report-table">
            <colgroup><col style="width:4%"><col style="width:18%"><col style="width:12%"><col style="width:22%"><col style="width:11%"><col style="width:11%"><col style="width:12%"><col style="width:10%"></colgroup>
            <thead><tr><th>No.</th><th>Akun</th><th>Jenis</th><th>Bank / Nomor / Pemilik</th><th class="money">Saldo Awal</th><th class="money">Mutasi Bersih</th><th class="money">Saldo Akhir</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$accounts): ?><tr class="empty-row"><td colspan="8">Belum ada akun dana.</td></tr><?php endif; ?>
            <?php foreach ($accounts as $index => $account): ?>
                <?php
                $typeLabels = array('cash'=>'Tunai', 'bank'=>'Bank', 'qris'=>'QRIS', 'personal'=>'Rekening Pribadi / Titipan');
                $type = isset($account['type']) ? $account['type'] : '';
                $openingCents = $moneyCents($account['opening_balance']);
                $balanceCents = $moneyCents($account['balance']);
                $opening = simp_money_from_cents($openingCents);
                $balance = simp_money_from_cents($balanceCents);
                $movement = simp_money_from_signed_cents($balanceCents - $openingCents);
                $identity = array_filter(array($account['bank_name'], $account['account_number'], $account['account_holder']), function ($value) {
                    return trim((string)$value) !== '';
                });
                ?>
                <tr>
                    <td class="number"><?= number_format($index + 1) ?></td>
                    <td><span class="primary"><?= e($account['name']) ?></span><span class="secondary">Urutan <?= number_format((int)$account['sort_order']) ?></span></td>
                    <td><span class="primary"><?= e(isset($typeLabels[$type]) ? $typeLabels[$type] : ucfirst((string)$type)) ?></span><span class="secondary"><?= (int)$account['include_in_total'] === 1 ? 'Masuk total' : 'Tidak masuk total' ?></span></td>
                    <td><?= e($identity ? implode(' · ', $identity) : '-') ?></td>
                    <td class="money"><?= e($printRupiah($opening)) ?></td>
                    <td class="money"><span class="primary"><?= e($printRupiah($movement)) ?></span></td>
                    <td class="money"><span class="primary"><?= e($printRupiah($balance)) ?></span></td>
                    <td><span class="status <?= (int)$account['is_active'] === 1 ? 'green' : 'red' ?>"><?= (int)$account['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span><span class="secondary"><?= (int)$account['include_in_total'] === 1 ? 'Dihitung' : 'Dikecualikan' ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <table class="document-foot">
        <tr>
            <td><?php
                if ($reportKind === 'accounts') echo 'Data otomatis dari MVIN. Saldo berasal dari saldo awal dan mutasi buku besar.';
                elseif ($reportKind === 'debt') echo 'Data otomatis dari MVIN. Pembayaran hutang terverifikasi mengurangi kewajiban.';
                else echo 'Data otomatis dari MVIN. Nilai pemasukan dan pengeluaran terverifikasi memengaruhi saldo.';
            ?></td>
            <td class="right">Format: F4 · 210 × 330 mm</td>
        </tr>
    </table>
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
    window.SIMPPrintPreview = {
        getZoom: function () { return userZoom; },
        setZoom: setZoom,
        reset: function () { return setZoom(100); }
    };
    fitSheet();
    window.addEventListener('resize', fitSheet);
}());
</script>
<script src="<?= e(base_url('assets/js/print-preview.js')) ?>?v=3"></script>
<?php endif; ?>
</body>
</html>
