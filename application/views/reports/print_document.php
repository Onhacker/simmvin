<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$validReportKinds = array('income', 'expense', 'accounts', 'debt');
$reportKind = isset($reportKind) && in_array($reportKind, $validReportKinds, TRUE) ? $reportKind : 'income';
$documentTitle = isset($documentTitle) ? (string) $documentTitle : 'Laporan';
$organizationName = isset($organizationName) ? (string) $organizationName : 'Penyelenggara Pelatihan';
$displayOrganization = trim($organizationName) !== '';
if (in_array($reportKind, array('income', 'expense'), TRUE) && strcasecmp(trim($organizationName), 'Penyelenggara Pelatihan') === 0) {
    $displayOrganization = FALSE;
}
$incomeLandscape = $reportKind === 'income';
$activeEvents = isset($activeEvents) && is_array($activeEvents) ? $activeEvents : array();
$activeRegencies = isset($activeRegencies) && is_array($activeRegencies) ? $activeRegencies : array();
$rows = isset($rows) && is_array($rows) ? $rows : array();
$debtSummary = isset($summary) && is_array($summary) ? $summary : array();
$accounts = isset($accounts) && is_array($accounts) ? $accounts : array();
$accountSummary = isset($accountSummary) && is_array($accountSummary) ? $accountSummary : array();
$generatedAt = isset($generatedAt) ? $generatedAt : date('Y-m-d H:i:s');
$isPdf = !empty($isPdf);
$methodLabels = array('cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS');
$printRupiah = function ($value, $withPrefix = TRUE) {
    return rupiah($value, $withPrefix);
};
$printDate = function ($value) {
    $value = trim((string) $value);
    if ($value === '') return '-';
    $timestamp = strtotime($value);
    return $timestamp !== FALSE ? date('d/m/Y', $timestamp) : $value;
};
$printCategory = function ($value) {
    $value = trim((string) $value);
    if ($value === '') return 'TANPA KATEGORI';
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
};
$printCategoryTitle = function ($value) {
    $value = trim((string) $value);
    if ($value === '') return 'Tanpa Kategori';
    $lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return function_exists('mb_convert_case')
        ? mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8')
        : ucwords($lower);
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

$expenseGroups = array();
$expenseMethodTotals = array();
if ($reportKind === 'expense') {
    // Rejected transactions are kept in the operational list for audit
    // purposes, but they must never be part of a printed financial report.
    // Filter before calculating summaries and category totals so a rejected
    // row cannot affect any amount, count, or grand total shown below.
    $rows = array_values(array_filter($rows, function ($expenseRow) {
        $status = isset($expenseRow['status']) ? strtolower(trim((string) $expenseRow['status'])) : 'pending';
        return $status !== 'rejected';
    }));

    foreach ($rows as $expenseRow) {
        $total = $moneyCents($expenseRow['amount']) + $moneyCents($expenseRow['admin_fee']);

        $methodKey = strtolower(trim((string) (isset($expenseRow['method']) ? $expenseRow['method'] : '')));
        if ($methodKey === '') $methodKey = 'other';
        if (!isset($expenseMethodTotals[$methodKey])) {
            $expenseMethodTotals[$methodKey] = array(
                'label' => isset($methodLabels[$methodKey])
                    ? $methodLabels[$methodKey]
                    : ucwords(str_replace('_', ' ', $methodKey)),
                'total_cents' => 0,
                'count' => 0
            );
        }
        $expenseMethodTotals[$methodKey]['total_cents'] += $total;
        $expenseMethodTotals[$methodKey]['count']++;

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

    // Keep the familiar payment-method order, then append any future/custom
    // method values after the supported methods.
    $orderedExpenseMethodTotals = array();
    foreach (array('cash', 'transfer', 'qris') as $methodKey) {
        if (isset($expenseMethodTotals[$methodKey])) {
            $orderedExpenseMethodTotals[$methodKey] = $expenseMethodTotals[$methodKey];
        }
    }
    foreach ($expenseMethodTotals as $methodKey => $methodTotal) {
        if (!isset($orderedExpenseMethodTotals[$methodKey])) {
            $orderedExpenseMethodTotals[$methodKey] = $methodTotal;
        }
    }
    $expenseMethodTotals = $orderedExpenseMethodTotals;
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
        @page { size: <?= $incomeLandscape ? '330mm 210mm' : '210mm 330mm' ?>; margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        html { padding: 0; color: #111827; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 12px; line-height: 1.3; touch-action: pan-x pan-y; }
        body { margin: 0; padding: 0; color: #111827; font-family: inherit; font-size: inherit; line-height: inherit; touch-action: inherit; }
        body { background: #e9eef5; }
        .sheet-stage { width: <?= $incomeLandscape ? '330mm' : '210mm' ?>; min-height: <?= $incomeLandscape ? '210mm' : '330mm' ?>; margin: 14px auto 24px; }
        .sheet { width: <?= $incomeLandscape ? '330mm' : '210mm' ?>; min-height: <?= $incomeLandscape ? '210mm' : '330mm' ?>; margin: 0; padding: 12mm 10mm 14mm; background: #fff; box-shadow: 0 10px 34px rgba(15, 23, 42, .14); transform-origin: top left; }
        .document-head, .meta-table, .summary-grid, .report-table, .document-foot { width: 100%; border-collapse: collapse; }
        .document-head td { vertical-align: top; padding: 0 0 7px; border-bottom: 2px solid #1f5fab; }
        .brand-code { color: #1f5fab; font-size: 23px; font-weight: 800; letter-spacing: 1px; line-height: 1; }
        .organization { margin-top: 4px; color: #374151; font-size: 12px; font-weight: 700; }
        .title-block { text-align: right; }
        .title-block h1 { margin: 0; color: #111827; font-size: 19px; line-height: 1.15; }
        .title-block p { margin: 4px 0 0; color: #4b5563; font-size: 12px; }
        .meta-table { margin: 8px 0 9px; }
        .meta-table td { padding: 2px 0; vertical-align: top; }
        .meta-label { width: 100px; color: #6b7280; white-space: nowrap; }
        .meta-separator { width: 10px; color: #6b7280; }
        .meta-value { font-weight: 700; }
        .event-list { margin: 0 0 9px; padding: 6px 8px; border-left: 3px solid #1f5fab; background: #f3f7fc; }
        .event-list strong { display: block; margin-bottom: 2px; color: #1f5fab; }
        .event-list span { display: block; }
        .section-title { margin: 10px 0 5px; color: #1f5fab; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .35px; }
        .summary-grid { table-layout: fixed; margin-bottom: 8px; }
        .summary-grid td { width: 25%; padding: 6px 7px; border: 1px solid #d7dee8; vertical-align: top; }
        .summary-label { display: block; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: .2px; }
        .summary-value { display: block; margin-top: 2px; color: #111827; font-size: 12px; font-weight: 800; }
        .summary-value.positive { color: #18743d; }
        .summary-value.negative { color: #b42318; }
        .expense-summary-table { width: 100%; margin-bottom: 8px; border-collapse: collapse; table-layout: fixed; }
        .expense-summary-table th, .expense-summary-table td { padding: 6px 8px; border: 1px solid #d7dee8; vertical-align: middle; }
        .expense-summary-table th { background: #1f5fab; color: #fff; font-size: 12px; line-height: 1.15; text-align: left; text-transform: uppercase; }
        .expense-summary-table th:first-child, .expense-summary-table td:first-child { width: 70%; }
        .expense-summary-table th:last-child, .expense-summary-table td:last-child { width: 30%; }
        .expense-summary-table td:last-child { text-align: right; white-space: nowrap; }
        .expense-summary-table tbody tr:nth-child(even) td { background: #f7f9fc; }
        .expense-summary-table tfoot td { background: #dcecff !important; border-top: 2px solid #174b8b; color: #111827; font-size: 12px; font-weight: 800; }
        .expense-summary-table .empty-summary { color: #6b7280; text-align: center; }
        .expense-method-table { width: 100%; margin: 4px 0 4px; border-collapse: collapse; table-layout: fixed; }
        .expense-method-table td { padding: 3px 8px; border: 1px solid #cfd7e2; background: #f3f7fc; font-size: 12px; line-height: 1.15; }
        .expense-method-table td:first-child { width: 86%; color: #174b8b; font-weight: 700; text-align: right; }
        .expense-method-table td:last-child { width: 14%; color: #111827; font-weight: 800; text-align: right; white-space: nowrap; }
        .report-table { table-layout: fixed; }
        .report-table thead { display: table-header-group; }
        .report-table tr { page-break-inside: avoid; }
        .report-table th { padding: 5px 4px; border: 1px solid #9eacbd; background: #1f5fab; color: #fff; font-size: 12px; line-height: 1.15; text-align: left; text-transform: uppercase; }
        .report-table td { padding: 5px 4px; border: 1px solid #cfd7e2; font-size: 12px; line-height: 1.2; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        .report-table tbody tr:nth-child(even) td { background: #f7f9fc; }
        .report-table .number { text-align: center; }
        /* Amounts may wrap inside their fixed column at 12px; this prevents
         * long currency values and headers from overlapping adjacent cells. */
        .report-table .money { text-align: right; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
        .report-table .primary { display: block; font-weight: 700; }
        .report-table .secondary { display: block; margin-top: 1px; color: #5f6b7a; font-size: 12px; line-height: 1.2; }
        .report-table .phone-link { color: #174b8b; text-decoration: underline; overflow-wrap: anywhere; word-break: break-word; }
        .report-note { margin: -1px 0 6px; padding: 5px 7px; border-left: 3px solid #1f5fab; background: #f3f7fc; color: #4b5563; font-size: 12px; }
        .expense-category-heading { margin: 8px 0 3px; padding: 4px 8px; border-left: 4px solid #1f5fab; background: #eaf2fc; color: #174b8b; page-break-after: avoid; break-after: avoid; }
        .expense-category-heading strong { display: block; margin: 0; color: #174b8b; font-size: 12px; line-height: 1.2; }
        .expense-category-group { page-break-inside: avoid; break-inside: avoid; }
        .expense-category-table { margin-bottom: 4px; page-break-before: avoid; break-before: avoid; }
        .expense-category-table .category-total-row td { background: #eef5ff !important; border-top: 2px solid #1f5fab; color: #174b8b; font-weight: 800; }
        .expense-category-table .category-total-row .money-label,
        .expense-grand-total .money-label { text-align: right; }
        .expense-category-table .category-total-row .money,
        .expense-grand-total .money { white-space: nowrap; overflow: visible; }
        .expense-category-table tbody td,
        .expense-category-table tbody td .primary { font-weight: 400; }
        /* Expense detail rows are the most information-dense part of the
         * report.  Keep the rest of the report at the standard 12px scale,
         * but give the Rincian Transaksi table one readable step up.  The
         * fixed F4 columns (and the dedicated 28px number column) keep the
         * larger text from changing the table geometry or clipping ordinals. */
        .expense-category-table th,
        .expense-category-table td { font-size: 13px; line-height: 1.25; }
        .expense-category-table .secondary { font-size: 12px; line-height: 1.2; }
        .expense-category-table th.number-col,
        .expense-category-table td.number-col { font-size: 12px; line-height: 1.2; }

        /* Each expense category gets its own restrained accent.  Keeping the
         * palette on the category wrapper makes the HTML preview and printed
         * PDF visually consistent while preserving high-contrast white text
         * in the table headers. */
        .expense-category-group.expense-tone-blue .expense-category-heading { border-left-color: #1f5fab; background: #eaf2fc; color: #174b8b; }
        .expense-category-group.expense-tone-blue .expense-category-heading strong { color: #174b8b; }
        .expense-category-group.expense-tone-blue .expense-category-table th { background: #1f5fab; border-color: #174b8b; }
        .expense-category-group.expense-tone-blue .expense-category-table td { border-color: #c7d8ed; }
        .expense-category-group.expense-tone-blue .expense-category-table tbody tr:nth-child(even) td,
        .expense-category-group.expense-tone-blue .expense-category-table .category-total-row td { background: #eef5ff !important; }
        .expense-category-group.expense-tone-blue .expense-category-table .category-total-row td { border-top-color: #1f5fab; color: #174b8b; }

        .expense-category-group.expense-tone-teal .expense-category-heading { border-left-color: #0f766e; background: #e6fffb; color: #0f5f59; }
        .expense-category-group.expense-tone-teal .expense-category-heading strong { color: #0f5f59; }
        .expense-category-group.expense-tone-teal .expense-category-table th { background: #0f766e; border-color: #0b5f59; }
        .expense-category-group.expense-tone-teal .expense-category-table td { border-color: #b9e3de; }
        .expense-category-group.expense-tone-teal .expense-category-table tbody tr:nth-child(even) td,
        .expense-category-group.expense-tone-teal .expense-category-table .category-total-row td { background: #effcfb !important; }
        .expense-category-group.expense-tone-teal .expense-category-table .category-total-row td { border-top-color: #0f766e; color: #0f5f59; }

        .expense-category-group.expense-tone-amber .expense-category-heading { border-left-color: #a16207; background: #fff7db; color: #854d0e; }
        .expense-category-group.expense-tone-amber .expense-category-heading strong { color: #854d0e; }
        .expense-category-group.expense-tone-amber .expense-category-table th { background: #a16207; border-color: #854d0e; }
        .expense-category-group.expense-tone-amber .expense-category-table td { border-color: #ead9a4; }
        .expense-category-group.expense-tone-amber .expense-category-table tbody tr:nth-child(even) td,
        .expense-category-group.expense-tone-amber .expense-category-table .category-total-row td { background: #fff9e8 !important; }
        .expense-category-group.expense-tone-amber .expense-category-table .category-total-row td { border-top-color: #a16207; color: #854d0e; }

        .expense-category-group.expense-tone-purple .expense-category-heading { border-left-color: #6d28d9; background: #f3e8ff; color: #5b21b6; }
        .expense-category-group.expense-tone-purple .expense-category-heading strong { color: #5b21b6; }
        .expense-category-group.expense-tone-purple .expense-category-table th { background: #6d28d9; border-color: #5b21b6; }
        .expense-category-group.expense-tone-purple .expense-category-table td { border-color: #d9c3f5; }
        .expense-category-group.expense-tone-purple .expense-category-table tbody tr:nth-child(even) td,
        .expense-category-group.expense-tone-purple .expense-category-table .category-total-row td { background: #faf5ff !important; }
        .expense-category-group.expense-tone-purple .expense-category-table .category-total-row td { border-top-color: #6d28d9; color: #5b21b6; }

        .expense-category-group.expense-tone-rose .expense-category-heading { border-left-color: #be123c; background: #fff1f2; color: #9f1239; }
        .expense-category-group.expense-tone-rose .expense-category-heading strong { color: #9f1239; }
        .expense-category-group.expense-tone-rose .expense-category-table th { background: #be123c; border-color: #9f1239; }
        .expense-category-group.expense-tone-rose .expense-category-table td { border-color: #f2c4cf; }
        .expense-category-group.expense-tone-rose .expense-category-table tbody tr:nth-child(even) td,
        .expense-category-group.expense-tone-rose .expense-category-table .category-total-row td { background: #fff7f8 !important; }
        .expense-category-group.expense-tone-rose .expense-category-table .category-total-row td { border-top-color: #be123c; color: #9f1239; }

        .expense-category-group.expense-tone-slate .expense-category-heading { border-left-color: #475569; background: #f1f5f9; color: #334155; }
        .expense-category-group.expense-tone-slate .expense-category-heading strong { color: #334155; }
        .expense-category-group.expense-tone-slate .expense-category-table th { background: #475569; border-color: #334155; }
        .expense-category-group.expense-tone-slate .expense-category-table td { border-color: #cbd5e1; }
        .expense-category-group.expense-tone-slate .expense-category-table tbody tr:nth-child(even) td,
        .expense-category-group.expense-tone-slate .expense-category-table .category-total-row td { background: #f8fafc !important; }
        .expense-category-group.expense-tone-slate .expense-category-table .category-total-row td { border-top-color: #475569; color: #334155; }

        .expense-grand-total { margin-top: 4px; page-break-before: avoid; break-before: avoid; }
        .expense-grand-total td { background: #dcecff !important; border-top: 2px solid #174b8b; color: #111827; font-size: 12px; font-weight: 800; }
        .status { display: block; width: 100%; max-width: 100%; padding: 2px 4px; border-radius: 3px; color: #fff; font-size: 12px; font-weight: 700; line-height: 1.15; text-align: center; white-space: normal; overflow-wrap: normal; word-break: normal; }
        .status.green { background: #1f7a45; }
        .status.yellow { background: #a76608; }
        .status.red { background: #b42318; }
        .status.blue { background: #1f5fab; }
        .status-icon { display: inline-block; width: auto; min-width: 16px; padding: 2px 4px; font-size: 12px; line-height: 1.1; }
        .empty-row td { padding: 20px 8px; color: #6b7280; text-align: center; }
        .document-foot { margin-top: 6px; border-top: 1px solid #cfd7e2; }
        .document-foot td { padding-top: 5px; color: #6b7280; font-size: 12px; vertical-align: top; }
        @media print {
            html, body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet-stage { width: auto !important; height: auto !important; min-height: 0 !important; margin: 0 !important; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; transform: none !important; }
        }
        @media screen and (max-width: 820px) {
            .sheet-stage { margin-top: 12px; margin-bottom: 12px; }
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
                <?php if ($displayOrganization): ?><div class="organization"><?= e($organizationName) ?></div><?php endif; ?>
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
        <?php if ($reportKind !== 'expense'): ?><tr><td class="meta-label">Lingkup data</td><td class="meta-separator">:</td><td class="meta-value"><?= e($reportKind === 'accounts' ? 'Seluruh akun dana' : $eventScope) ?></td></tr><?php endif; ?>
        <?php if ($reportKind === 'income'): ?>
            <tr><td class="meta-label">Rincian</td><td class="meta-separator">:</td><td class="meta-value"><?= $report['view'] === 'participant' ? 'Per peserta' : 'Per desa' ?></td></tr>
        <?php elseif ($reportKind === 'accounts'): ?>
            <tr><td class="meta-label">Dasar saldo</td><td class="meta-separator">:</td><td class="meta-value">Saldo awal + mutasi buku besar</td></tr>
        <?php elseif ($reportKind === 'debt'): ?>
            <tr><td class="meta-label">Rincian</td><td class="meta-separator">:</td><td class="meta-value">Hutang dan riwayat pembayaran</td></tr>
        <?php endif; ?>
        <tr><td class="meta-label">Dibuat</td><td class="meta-separator">:</td><td class="meta-value"><?= e(tanggal_id($generatedAt, TRUE)) ?></td></tr>
        <?php if ($reportKind === 'expense' && $activeRegencies): ?>
            <tr><td class="meta-label">Kabupaten aktif</td><td class="meta-separator">:</td><td class="meta-value"><?= e(implode(' · ', $activeRegencies)) ?></td></tr>
        <?php endif; ?>
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
        <?php $expenseSummaryTotalCents = 0; ?>
        <table class="expense-summary-table">
            <thead><tr><th>Kategori</th><th>Jumlah</th></tr></thead>
            <tbody>
            <?php if (!$expenseGroups): ?>
                <tr><td colspan="2" class="empty-summary">Belum ada transaksi pengeluaran pada event aktif.</td></tr>
            <?php else: ?>
                <?php foreach ($expenseGroups as $expenseSummaryGroup): ?>
                    <?php $expenseSummaryTotalCents += (int) $expenseSummaryGroup['total_cents']; ?>
                    <tr><td><?= e($printCategory($expenseSummaryGroup['name'])) ?></td><td><?= e($printRupiah(simp_money_from_cents((int) $expenseSummaryGroup['total_cents']))) ?></td></tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <tfoot><tr><td>Total</td><td><?= e($printRupiah(simp_money_from_cents($expenseSummaryTotalCents))) ?></td></tr></tfoot>
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
        <table class="report-table">
            <?php if ($report['view'] === 'participant'): ?>
            <colgroup><col width="28%" style="width:28%"><col width="28%" style="width:28%"><col width="18%" style="width:18%"><col width="14%" style="width:14%"><col width="12%" style="width:12%"></colgroup>
            <thead><tr><th width="28%">Kecamatan/Desa</th><th width="28%">Nama Peserta/Jabatan</th><th width="18%">No. HP</th><th width="14%">Status Bayar (BB/Lunas)</th><th width="12%">Status</th></tr></thead>
            <?php else: ?>
            <colgroup><col width="4%" style="width:4%"><col width="39%" style="width:39%"><col width="16%" style="width:16%"><col width="21%" style="width:21%"><col width="12%" style="width:12%"><col width="8%" style="width:8%"></colgroup>
            <thead><tr><th width="4%">No.</th><th width="39%">Kecamatan/Desa</th><th width="16%" class="money">Tagihan</th><th width="21%" class="money">Dana Masuk (Terverifikasi)</th><th width="12%" class="money">Sisa Tagihan</th><th width="8%">Status</th></tr></thead>
            <?php endif; ?>
            <tbody>
            <?php if (!$report['rows']): ?><tr class="empty-row"><td colspan="<?= $report['view'] === 'participant' ? '5' : '6' ?>">Belum ada data registrasi pada event aktif.</td></tr><?php endif; ?>
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
                $registrationStatus = isset($row['registration_status']) ? strtolower(trim((string) $row['registration_status'])) : 'active';
                $registrationStatusLabel = $registrationStatus === 'active' ? 'Aktif' : ($registrationStatus === 'cancelled' ? 'Dibatalkan' : ucfirst($registrationStatus));
                $registrationStatusClass = $registrationStatus === 'active' ? 'green' : 'red';
                ?>
                <tr>
                    <?php if ($participantView): ?>
                    <?php
                    $phone = trim((string) (isset($row['phone']) ? $row['phone'] : ''));
                    $phoneDigits = preg_replace('/\D+/', '', $phone);
                    if ($phoneDigits !== '' && strpos($phoneDigits, '0') === 0) $phoneDigits = '62' . substr($phoneDigits, 1);
                    elseif ($phoneDigits !== '' && strpos($phoneDigits, '8') === 0) $phoneDigits = '62' . $phoneDigits;
                    $statusDueCents = $villageBillingParticipant ? $moneyCents(isset($row['village_due']) ? $row['village_due'] : 0) : $dueCents;
                    $statusPaidCents = $villageBillingParticipant ? $moneyCents(isset($row['village_paid']) ? $row['village_paid'] : 0) : $paidCents;
                    if ($statusDueCents === NULL) $statusDueCents = 0;
                    if ($statusPaidCents === NULL) $statusPaidCents = 0;
                    if ($statusDueCents < 0) $statusDueCents = 0;
                    if ($statusPaidCents < 0) $statusPaidCents = 0;
                    $isParticipantPaid = $statusDueCents > 0 && $statusPaidCents >= $statusDueCents;
                    if ($statusDueCents <= 0 && $statusPaidCents > 0) $isParticipantPaid = TRUE;
                    ?>
                    <td><span class="primary"><?= e($row['district_name']) ?></span><span class="secondary"><?= e($row['village_name']) ?></span></td>
                    <td><span class="primary"><?= e($row['participant_name']) ?></span><span class="secondary"><?= e($row['position']) ?></span></td>
                    <td><?php if ($phone !== '' && $phoneDigits !== ''): ?><a class="phone-link" href="https://wa.me/<?= e($phoneDigits) ?>" target="_blank" rel="noopener"><?= e($phone) ?></a><?php else: ?>-<?php endif; ?></td>
                    <td><span class="status <?= $isParticipantPaid ? 'green' : 'red' ?>"><?= $isParticipantPaid ? 'Lunas' : 'BB' ?></span></td>
                    <td><span class="status <?= $registrationStatusClass ?>"><?= e($registrationStatusLabel) ?></span></td>
                    <?php else: ?>
                    <td class="number"><?= number_format($index + 1) ?></td>
                    <td><span class="primary"><?= e($row['district_name']) ?></span><span class="secondary"><?= e($row['village_name']) ?></span><span class="secondary"><?= number_format((int) $row['participant_count']) ?> peserta</span></td>
                    <td class="money"><?= e($printRupiah($due)) ?></td>
                    <td class="money"><span class="primary"><?= e($printRupiah($paid)) ?></span><span class="secondary"><?= $villageBillingParticipant ? 'Dicatat di desa' : 'T ' . e($printRupiah($row['cash_total'], FALSE)) . ' · TF ' . e($printRupiah($row['transfer_total'], FALSE)) . ' · Q ' . e($printRupiah($row['qris_total'], FALSE)) ?></span></td>
                    <td class="money"><?= e($printRupiah($remaining)) ?></td>
                    <td><span class="status <?= $statusClass ?>"><?= e($statusLabel) ?></span></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($reportKind === 'expense'): ?>
        <?php if (!$expenseGroups): ?>
            <table class="report-table">
                <colgroup><col width="4%" style="width:4%"><col width="14%" style="width:14%"><col width="50%" style="width:50%"><col width="18%" style="width:18%"><col width="14%" style="width:14%"></colgroup>
                <thead><tr><th class="number-col" width="4%">No.</th><th width="14%">Tanggal</th><th width="50%">Deskripsi</th><th width="18%">Akun / Metode</th><th width="14%" class="money">Total</th></tr></thead>
                <tbody><tr class="empty-row"><td colspan="5">Belum ada transaksi pengeluaran pada event aktif.</td></tr></tbody>
            </table>
        <?php else: ?>
            <?php
            $expenseNo = 0;
            $grandExpenseCents = 0;
            $expenseCategoryIndex = 0;
            $expenseToneNames = array('blue', 'teal', 'amber', 'purple', 'rose', 'slate');
            ?>
            <?php foreach ($expenseGroups as $expenseGroup): ?>
                <?php
                $categoryTotalCents = (int) $expenseGroup['total_cents'];
                $grandExpenseCents += $categoryTotalCents;
                $categoryTotal = simp_money_from_cents($categoryTotalCents);
                $expenseTone = $expenseToneNames[$expenseCategoryIndex % count($expenseToneNames)];
                $expenseCategoryIndex++;
                ?>
                <div class="expense-category-group expense-tone-<?= e($expenseTone) ?>">
                <div class="expense-category-heading"><strong><?= e($printCategory($expenseGroup['name'])) ?></strong></div>
                <table class="report-table expense-category-table">
                    <colgroup><col width="4%" style="width:4%"><col width="14%" style="width:14%"><col width="50%" style="width:50%"><col width="18%" style="width:18%"><col width="14%" style="width:14%"></colgroup>
                    <thead><tr><th class="number-col" width="4%">No.</th><th width="14%">Tanggal</th><th width="50%">Deskripsi</th><th width="18%">Akun / Metode</th><th width="14%" class="money">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($expenseGroup['rows'] as $row): ?>
                        <?php
                        $expenseNo++;
                        $amountCents = $moneyCents($row['amount']);
                        $adminFeeCents = $moneyCents($row['admin_fee']);
                        $adminFee = simp_money_from_cents($adminFeeCents);
                        $total = simp_money_from_cents($amountCents + $adminFeeCents);
                        $expenseMethodKey = strtolower(trim((string) (isset($row['method']) ? $row['method'] : '')));
                        $expenseMethodLabel = isset($methodLabels[$expenseMethodKey])
                            ? $methodLabels[$expenseMethodKey]
                            : ucfirst((string) (isset($row['method']) ? $row['method'] : ''));
                        ?>
                        <tr>
                            <td class="number number-col"><?= number_format($expenseNo) ?></td>
                            <td><span class="primary"><?= e($printDate($row['expense_date'])) ?></span></td>
                            <td><span class="primary"><?= e($row['description']) ?></span><?php if (!empty($row['debt_id'])): ?><span class="secondary">Pembayaran hutang <?= e($row['debt_no']) ?> · <?= e($row['debt_creditor']) ?></span><?php endif; ?></td>
                            <td><?php if ($expenseMethodKey === 'cash'): ?><span class="primary"><?= e($expenseMethodLabel) ?></span><?php else: ?><span class="primary"><?= e($row['account_name']) ?></span><span class="secondary"><?= e($expenseMethodLabel) ?></span><?php endif; ?></td>
                            <td class="money"><span class="primary"><?= e($printRupiah($total)) ?></span><?php if ($adminFeeCents > 0): ?><span class="secondary">Admin <?= e($printRupiah($adminFee, FALSE)) ?></span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="category-total-row"><td colspan="4" class="money-label">Total <?= e($printCategory($expenseGroup['name'])) ?></td><td class="money"><?= e($printRupiah($categoryTotal)) ?></td></tr></tfoot>
                </table>
                </div>
            <?php endforeach; ?>
            <?php $grandExpense = simp_money_from_cents($grandExpenseCents); ?>
            <?php if ($expenseMethodTotals): ?>
                <table class="expense-method-table">
                    <tbody>
                    <?php foreach ($expenseMethodTotals as $expenseMethodTotal): ?>
                        <tr><td>Total berdasarkan Metode · <?= e($expenseMethodTotal['label']) ?></td><td><?= e($printRupiah(simp_money_from_cents((int) $expenseMethodTotal['total_cents']))) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <table class="report-table expense-grand-total">
                <colgroup><col width="4%" style="width:4%"><col width="14%" style="width:14%"><col width="50%" style="width:50%"><col width="18%" style="width:18%"><col width="14%" style="width:14%"></colgroup>
                <tfoot><tr><td width="86%" colspan="4" class="money-label">TOTAL SELURUH PENGELUARAN</td><td width="14%" class="money"><?= e($printRupiah($grandExpense)) ?></td></tr></tfoot>
            </table>
        <?php endif; ?>
    <?php elseif ($reportKind === 'debt'): ?>
        <p class="report-note">Pembayaran terverifikasi mengurangi saldo hutang. Pembayaran yang masih menunggu verifikasi tetap tercantum sebagai komitmen.</p>
        <table class="report-table">
            <colgroup><col width="4%" style="width:4%"><col width="14%" style="width:14%"><col width="24%" style="width:24%"><col width="15%" style="width:15%"><col width="12%" style="width:12%"><col width="12%" style="width:12%"><col width="11%" style="width:11%"><col width="8%" style="width:8%"></colgroup>
            <thead><tr><th width="4%">No.</th><th width="14%">Nomor / Tanggal</th><th width="24%">Kreditur / Uraian</th><th width="15%">Event / Kategori</th><th width="12%" class="money">Nilai Pokok</th><th width="12%" class="money">Terbayar</th><th width="11%" class="money">Sisa</th><th width="8%">Status</th></tr></thead>
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
                <colgroup><col width="4%" style="width:4%"><col width="23%" style="width:23%"><col width="13%" style="width:13%"><col width="20%" style="width:20%"><col width="13%" style="width:13%"><col width="12%" style="width:12%"><col width="15%" style="width:15%"></colgroup>
                <thead><tr><th width="4%">No.</th><th width="23%">Hutang / Kreditur</th><th width="13%">Tanggal</th><th width="20%">Akun / Metode</th><th width="13%" class="money">Nominal</th><th width="12%" class="money">Biaya Admin</th><th width="15%">Status / Catatan</th></tr></thead>
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
            <colgroup><col width="4%" style="width:4%"><col width="18%" style="width:18%"><col width="12%" style="width:12%"><col width="22%" style="width:22%"><col width="11%" style="width:11%"><col width="11%" style="width:11%"><col width="12%" style="width:12%"><col width="10%" style="width:10%"></colgroup>
            <thead><tr><th width="4%">No.</th><th width="18%">Akun</th><th width="12%">Jenis</th><th width="22%">Bank / Nomor / Pemilik</th><th width="11%" class="money">Saldo Awal</th><th width="11%" class="money">Mutasi Bersih</th><th width="12%" class="money">Saldo Akhir</th><th width="10%">Status</th></tr></thead>
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
            <td colspan="2"><?php
                if ($reportKind === 'accounts') echo 'Data otomatis dari MVIN. Saldo berasal dari saldo awal dan mutasi buku besar.';
                elseif ($reportKind === 'debt') echo 'Data otomatis dari MVIN. Pembayaran hutang terverifikasi mengurangi kewajiban.';
                else echo 'Data otomatis dari MVIN. Nilai pemasukan dan pengeluaran terverifikasi memengaruhi saldo.';
            ?></td>
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
        stage.style.width = '<?= $incomeLandscape ? '330mm' : '210mm' ?>';
        stage.style.height = 'auto';
        stage.style.minHeight = '<?= $incomeLandscape ? '210mm' : '330mm' ?>';
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
<script src="<?= e(base_url('assets/js/print-preview.min.js')) ?>?v=4"></script>
<?php endif; ?>
</body>
</html>
