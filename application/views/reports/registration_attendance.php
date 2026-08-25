<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$activeEvents = isset($activeEvents) && is_array($activeEvents) ? $activeEvents : array();
$rows = isset($rows) && is_array($rows) ? $rows : array();
$generatedAt = isset($generatedAt) ? $generatedAt : date('Y-m-d H:i:s');
$isPdf = !empty($isPdf);
$isArchive = !empty($isArchive);

$logoDataUri = '';
$logoPath = defined('FCPATH') ? FCPATH . 'assets/images/mvin-logo.jpg' : '';
if ($logoPath !== '' && is_file($logoPath) && is_readable($logoPath)) {
    $logoData = @file_get_contents($logoPath);
    if ($logoData !== FALSE) $logoDataUri = 'data:image/jpeg;base64,' . base64_encode($logoData);
}

$upper = function ($value) {
    $value = trim((string) $value);
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
};

$dateParts = function ($value) {
    $value = trim((string) $value);
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) return NULL;
    $months = array(1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember');
    return array('day'=>(int)$date->format('j'), 'month'=>(int)$date->format('n'), 'year'=>(int)$date->format('Y'), 'month_name'=>$months[(int)$date->format('n')]);
};

$dateRange = function (array $event) use ($dateParts) {
    $startRaw = isset($event['start_date']) ? $event['start_date'] : '';
    $endRaw = isset($event['end_date']) ? $event['end_date'] : '';
    $start = $dateParts($startRaw);
    $end = $dateParts($endRaw);
    if (!$start && !$end) return 'tanggal belum diatur';
    if (!$start) return $end['day'] . ' ' . $end['month_name'] . ' ' . $end['year'];
    if (!$end || $startRaw === $endRaw) return $start['day'] . ' ' . $start['month_name'] . ' ' . $start['year'];
    if ($start['year'] === $end['year'] && $start['month'] === $end['month']) {
        return $start['day'] . ' - ' . $end['day'] . ' ' . $end['month_name'] . ' ' . $end['year'];
    }
    if ($start['year'] === $end['year']) {
        return $start['day'] . ' ' . $start['month_name'] . ' - ' . $end['day'] . ' ' . $end['month_name'] . ' ' . $end['year'];
    }
    return $start['day'] . ' ' . $start['month_name'] . ' ' . $start['year'] . ' - ' . $end['day'] . ' ' . $end['month_name'] . ' ' . $end['year'];
};

/* Preserve active-event order, then group every roster by district. */
$eventGroups = array();
foreach ($activeEvents as $event) {
    $eventId = (int) (isset($event['id']) ? $event['id'] : 0);
    if ($eventId < 1) continue;
    $eventGroups[(string) $eventId] = array('event'=>$event, 'rows'=>array());
}
foreach ($rows as $row) {
    $eventId = (string) (int) (isset($row['event_id']) ? $row['event_id'] : 0);
    if (!isset($eventGroups[$eventId])) {
        $eventGroups[$eventId] = array('event'=>array(
            'id'=>(int)$eventId,
            'name'=>isset($row['event_name']) ? $row['event_name'] : 'Event Pelatihan',
            'start_date'=>isset($row['start_date']) ? $row['start_date'] : '',
            'end_date'=>isset($row['end_date']) ? $row['end_date'] : '',
            'location'=>isset($row['location']) ? $row['location'] : ''
        ), 'rows'=>array());
    }
    $eventGroups[$eventId]['rows'][] = $row;
}

$pages = array();
foreach ($eventGroups as $group) {
    $event = $group['event'];
    $districtGroups = array();
    foreach ($group['rows'] as $row) {
        $districtName = trim((string) (isset($row['district_name']) ? $row['district_name'] : ''));
        if ($districtName === '') $districtName = 'KECAMATAN BELUM DIATUR';
        if (!isset($districtGroups[$districtName])) $districtGroups[$districtName] = array();
        $districtGroups[$districtName][] = $row;
    }

    if (!$districtGroups) {
        $pages[] = array('event'=>$event, 'district'=>'', 'rows'=>array(), 'offset'=>0, 'district_page'=>1, 'district_pages'=>1, 'district_total'=>0);
        continue;
    }

    uksort($districtGroups, 'strnatcasecmp');
    foreach ($districtGroups as $districtName => $districtRows) {
        usort($districtRows, function ($left, $right) {
            foreach (array('village_name', 'full_name') as $field) {
                $comparison = strnatcasecmp(trim((string) (isset($left[$field]) ? $left[$field] : '')), trim((string) (isset($right[$field]) ? $right[$field] : '')));
                if ($comparison !== 0) return $comparison;
            }
            return ((int) (isset($left['participant_id']) ? $left['participant_id'] : 0)) <=> ((int) (isset($right['participant_id']) ? $right['participant_id'] : 0));
        });

        $chunks = array_chunk($districtRows, 10);
        $districtPageCount = count($chunks);
        foreach ($chunks as $chunkIndex => $chunkRows) {
            $pages[] = array(
                'event'=>$event,
                'district'=>$districtName,
                'rows'=>$chunkRows,
                'offset'=>$chunkIndex * 10,
                'district_page'=>$chunkIndex + 1,
                'district_pages'=>$districtPageCount,
                'district_total'=>count($districtRows)
            );
        }
    }
}

if (!$pages) {
    $pages[] = array('event'=>array('name'=>'Event Aktif','start_date'=>'','end_date'=>'','location'=>''), 'district'=>'', 'rows'=>array(), 'offset'=>0, 'district_page'=>1, 'district_pages'=>1, 'district_total'=>0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Registrasi | MVIN</title>
    <style>
        @page { size: 330mm 210mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; color: #000; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9px; line-height: 1.3; }
        body { background: #e9eef5; }
        .sheet-stage { width: 330mm; min-height: 210mm; margin: 14px auto 24px; }
        .sheet { width: 330mm; margin: 0; transform-origin: top left; }
        .print-page { width: 330mm; margin: 0 0 8mm; padding: 5mm 7mm 6mm; background: #fff; box-shadow: 0 10px 34px rgba(15, 23, 42, .14); page-break-after: always; break-after: page; }
        .print-page:last-child { margin-bottom: 0; page-break-after: auto; break-after: auto; }
        .attendance-head { width: 100%; height: 33mm; border-collapse: collapse; table-layout: fixed; border-bottom: 1.2px solid #111; }
        .attendance-head td { padding: 0 0 3mm; vertical-align: middle; }
        .attendance-head .logo-cell, .attendance-head .balance-cell { width: 9%; }
        .attendance-head .logo-cell { text-align: center; }
        .attendance-head .logo-cell img { display: block; width: 27mm; height: auto; margin: 0 auto; }
        .attendance-head .title-cell { padding-left: 2mm; padding-right: 2mm; text-align: center; }
        .attendance-head h1 { margin: 0; color: #000; font-size: 14px; font-weight: 800; line-height: 1.15; white-space: nowrap; }
        .attendance-head p { margin: 2mm 0 0; color: #000; font-size: 12.5px; font-weight: 800; line-height: 1.2; }
        .attendance-head .organizer-name { margin-top: 1.4mm; color: #000; font-size: 14px; font-weight: 800; letter-spacing: .65px; line-height: 1.15; }
        .district-bar { display: table; width: 100%; height: 9mm; margin: 2.5mm 0 2mm; padding: 1.8mm 2.5mm; border: 1px solid #6b7280; background: #f1f1f1; }
        .district-bar strong, .district-bar span { display: table-cell; vertical-align: middle; }
        .district-bar strong { color: #000; font-size: 10px; letter-spacing: .25px; }
        .district-bar span { width: 38%; color: #111; font-size: 8.4px; font-weight: 700; text-align: right; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table .col-number { width: 4%; }
        .report-table .col-district { width: 14%; }
        .report-table .col-village { width: 14%; }
        .report-table .col-name { width: 19%; }
        .report-table .col-position { width: 14%; }
        .report-table .col-contact { width: 11%; }
        .report-table .col-signature { width: 13%; }
        .report-table .col-room { width: 6%; }
        .report-table .col-note { width: 5%; }
        .report-table th { height: 5.6mm; padding: 1.1mm .8mm; border: 1px solid #111; background: #e8e8e8; color: #000; font-size: 9.1px; line-height: 1.12; text-align: center; text-transform: uppercase; }
        .report-table td { height: 10.5mm; padding: 1mm 1.2mm; border: 1px solid #111; vertical-align: middle; color: #000; font-size: 10.3px; line-height: 1.17; overflow-wrap: anywhere; }
        .report-table tr { page-break-inside: avoid; }
        .report-table .number { text-align: center; }
        .report-table .contact { white-space: nowrap; }
        .report-table .signature, .report-table .room, .report-table .note { background: #fff; }
        .report-table .blank-line { display: block; height: 6mm; }
        .empty-row td { height: 20mm; color: #4b5563; text-align: center; }
        .document-foot { width: 100%; margin-top: 2.2mm; border-collapse: collapse; table-layout: fixed; color: #111; font-size: 7.3px; }
        .document-foot td { padding: 0; vertical-align: top; }
        .document-foot td:last-child { width: 34%; padding-right: 1mm; text-align: right; white-space: nowrap; }
        @media print {
            html, body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet-stage { width: auto !important; height: auto !important; min-height: 0 !important; margin: 0 !important; }
            .sheet { width: auto; margin: 0; transform: none !important; }
            .print-page { width: auto; margin: 0; box-shadow: none; }
        }
        @media screen {
            .print-page { height: 210mm; overflow: hidden; }
        }
        @media screen and (max-width: 1100px) {
            .sheet-stage, .sheet { width: 330mm; }
        }
    </style>
</head>
<body>
<div class="sheet-stage" data-sheet-stage>
    <article class="sheet" data-print-sheet>
        <?php foreach ($pages as $pageIndex => $page):
            $event = $page['event'];
            $eventName = trim((string) (isset($event['name']) ? $event['name'] : 'Event Pelatihan'));
            if ($eventName === '') $eventName = 'Event Pelatihan';
            $location = trim((string) (isset($event['location']) ? $event['location'] : ''));
            if ($location === '') $location = 'lokasi belum diatur';
        ?>
            <section class="print-page">
                <table class="attendance-head" role="presentation">
                    <colgroup><col style="width:9%"><col style="width:82%"><col style="width:9%"></colgroup>
                    <tr>
                        <td class="logo-cell"><?php if ($logoDataUri !== ''): ?><img src="<?= e($logoDataUri) ?>" alt="Logo MVIN"><?php endif; ?></td>
                        <td class="title-cell">
                            <h1>DATA REGISTRASI PESERTA <?= e($upper($eventName)) ?></h1>
                            <p>Tanggal <?= e($dateRange($event)) ?> di <?= e($location) ?></p>
                            <div class="organizer-name">MEDIAVERSE INOVASI NUSANTARA</div>
                        </td>
                        <td class="balance-cell"></td>
                    </tr>
                </table>

                <div class="district-bar">
                    <strong><?= $page['district'] !== '' ? 'KECAMATAN: ' . e($upper($page['district'])) : 'DAFTAR PESERTA' ?></strong>
                    <span><?= number_format((int)$page['district_total'], 0, ',', '.') ?> peserta · Halaman <?= (int)$page['district_page'] ?> dari <?= (int)$page['district_pages'] ?></span>
                </div>

                <table class="report-table">
                    <colgroup><col width="4%"><col width="14%"><col width="14%"><col width="19%"><col width="14%"><col width="11%"><col width="13%"><col width="6%"><col width="5%"></colgroup>
                    <thead><tr><th class="col-number" width="4%">No.</th><th class="col-district" width="14%">Kecamatan</th><th class="col-village" width="14%">Desa</th><th class="col-name" width="19%">Nama Lengkap</th><th class="col-position" width="14%">Jabatan</th><th class="col-contact" width="11%">Kontak</th><th class="col-signature" width="13%">TTD</th><th class="col-room" width="6%">No. Kamar</th><th class="col-note" width="5%">Ket.</th></tr></thead>
                    <tbody>
                    <?php if (!$page['rows']): ?>
                        <tr class="empty-row"><td colspan="9"><?= $isArchive ? 'Belum ada peserta aktif pada event ini.' : 'Belum ada peserta terdaftar pada event aktif.' ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($page['rows'] as $index => $row): ?>
                            <tr>
                                <td class="number col-number" width="4%"><?= (int)$page['offset'] + $index + 1 ?></td>
                                <td class="col-district" width="14%"><?= e(isset($row['district_name']) ? $row['district_name'] : '-') ?></td>
                                <td class="col-village" width="14%"><?= e(isset($row['village_name']) ? $row['village_name'] : '-') ?></td>
                                <td class="col-name" width="19%"><strong><?= e(isset($row['full_name']) ? $row['full_name'] : '-') ?></strong></td>
                                <td class="col-position" width="14%"><?= e(isset($row['position']) && trim((string)$row['position']) !== '' ? $row['position'] : '-') ?></td>
                                <td class="contact col-contact" width="11%"><?= e(isset($row['phone']) && trim((string)$row['phone']) !== '' ? $row['phone'] : '-') ?></td>
                                <td class="signature col-signature" width="13%"><span class="blank-line"></span></td>
                                <td class="room col-room" width="6%"><span class="blank-line"></span></td>
                                <td class="note col-note" width="5%"><span class="blank-line"></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>

                <table class="document-foot" role="presentation"><tr><td>TTD diisi peserta saat hadir. No. Kamar dan Ket. diisi petugas.</td><td>Dicetak <?= e(tanggal_id(substr((string)$generatedAt, 0, 10))) ?></td></tr></table>
            </section>
        <?php endforeach; ?>
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
        userZoom = Math.max(50, Math.min(200, Math.round(Number(percent) || 100)));
        fitSheet();
        return userZoom;
    }
    window.SIMPPrintPreview = { getZoom: function () { return userZoom; }, setZoom: setZoom, reset: function () { return setZoom(100); } };
    fitSheet();
    window.addEventListener('resize', fitSheet);
}());
</script>
<?php endif; ?>
</body>
</html>
