<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$summary = isset($report['summary']) ? $report['summary'] : array();
$rows = isset($report['rows']) && is_array($report['rows']) ? $report['rows'] : array();
$districts = isset($filterOptions['districts']) ? $filterOptions['districts'] : array();
$villages = isset($filterOptions['villages']) ? $filterOptions['villages'] : array();
$selectedDistrict = isset($filters['district_id']) ? (string) $filters['district_id'] : '';
$selectedVillage = isset($filters['village_id']) ? (string) $filters['village_id'] : '';
$hasAnyData = !empty($villages) || (int)($summary['registrations'] ?? 0) > 0;
$filteredSuffix = $filterQuery !== '' ? '?' . $filterQuery : '';
$stateLabels = array(
    'paid' => array('Lunas', 'bg-green-dark'),
    'overpaid' => array('Lebih Bayar', 'bg-blue-dark'),
    'partial_pending' => array('Sebagian + Menunggu', 'bg-yellow-dark'),
    'partial' => array('Bayar Sebagian', 'bg-yellow-dark'),
    'pending' => array('Menunggu Verifikasi', 'bg-orange-dark'),
    'unpaid' => array('Belum Bayar', 'bg-red-dark'),
    'no_charge' => array('Tidak Ditagih', 'bg-gray-dark')
);
$billingLabels = array(
    'per_village' => 'Per Desa',
    'per_participant' => 'Per Peserta',
    'per_village_extra' => 'Per Desa + Peserta Tambahan'
);
?>

<?php if (!$activeEvents): ?>
    <div class="card card-style">
        <div class="content text-center py-4">
            <span class="icon icon-l rounded-xl bg-blue-light color-blue-dark mb-3"><i class="fa fa-calendar-times"></i></span>
            <h3>Belum Ada Event Aktif</h3>
            <p class="mb-0 color-theme">Aktifkan event untuk menampilkan data pembayaran.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card card-style">
        <div class="content mb-3">
            <div class="d-flex align-items-center mb-3">
                <div class="min-width-zero pe-3">
                    <h2 class="mb-1">Filter Data Bayar</h2>
                    <p class="mb-0 color-theme">Pilih kecamatan, kemudian desa.</p>
                </div>
                <span class="badge bg-blue-dark color-white ms-auto flex-shrink-0"><?= number_format(count($rows)) ?> data</span>
            </div>

            <form method="get" action="<?= site_url('data-bayar') ?>" data-payment-data-filter data-villages="<?= e(json_encode($villages, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)) ?>">
                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="payment-data-district" class="color-highlight">Kecamatan</label>
                    <select id="payment-data-district" name="district_id" data-payment-district>
                        <option value="">Semua kecamatan</option>
                        <?php foreach ($districts as $district): ?>
                            <option value="<?= e($district['id']) ?>" <?= $selectedDistrict === (string) $district['id'] ? 'selected' : '' ?>><?= e($district['name']) ?> · <?= e($district['regency_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em>
                </div>
                <div class="input-style has-borders no-icon input-style-always-active mb-3">
                    <label for="payment-data-village" class="color-highlight">Desa</label>
                    <select id="payment-data-village" name="village_id" data-payment-village data-selected="<?= e($selectedVillage) ?>" <?= $selectedDistrict === '' ? 'disabled' : '' ?>>
                        <option value=""><?= $selectedDistrict === '' ? 'Pilih kecamatan dahulu' : 'Semua desa' ?></option>
                        <?php if ($selectedDistrict !== ''): ?>
                            <?php foreach ($villages as $village): ?>
                                <?php if ((string) $village['district_id'] !== $selectedDistrict) continue; ?>
                                <option value="<?= e($village['id']) ?>" <?= $selectedVillage === (string) $village['id'] ? 'selected' : '' ?>><?= e($village['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <span><i class="fa fa-chevron-down"></i></span><i class="fa fa-check disabled valid color-green-dark"></i><i class="fa fa-times disabled invalid color-red-dark"></i><em></em>
                </div>
                <div class="row mb-0">
                    <div class="col-7 pe-1"><button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600"><i class="fa fa-filter me-1"></i>Terapkan</button></div>
                    <div class="col-5 ps-1"><a href="<?= site_url('data-bayar') ?>" class="btn btn-full btn-m bg-theme color-theme border rounded-s font-600"><i class="fa fa-redo me-1 color-highlight"></i>Reset</a></div>
                </div>
            </form>

            <?php if ($hasAnyData): ?>
                <div class="divider mt-4 mb-3"></div>
                <div class="row mb-0">
                    <div class="<?= $filterActive && $rows ? 'col-6 pe-1' : 'col-12' ?>">
                        <a href="<?= site_url('data-bayar/cetak') ?>" class="btn btn-full btn-m bg-theme color-theme border rounded-s font-600" data-report-preview-open="payment-data-all-print-modal"><i class="fa fa-print me-1 color-highlight"></i>Cetak Semua</a>
                    </div>
                    <?php if ($filterActive && $rows): ?>
                        <div class="col-6 ps-1"><a href="<?= site_url('data-bayar/cetak') . $filteredSuffix ?>" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-report-preview-open="payment-data-filter-print-modal"><i class="fa fa-print me-1"></i>Cetak Filter</a></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-style">
        <div class="content mb-2">
            <h2 class="mb-0">Ringkasan Pembayaran</h2>
            <p class="font-12 color-blue-dark font-600 mb-2 text-break"><?= e($filterScope) ?></p>
            <?php
            $summaryItems = array(
                array('building', 'Jumlah Desa', number_format((int)($summary['villages'] ?? $summary['registrations'] ?? 0)), 'color-blue-dark'),
                array('users', 'Jumlah Peserta', number_format((int)($summary['participants'] ?? 0)) . ' orang', 'color-magenta-dark'),
                array('file-invoice-dollar', 'Total Tagihan', rupiah($summary['total_due'] ?? 0), 'color-brown-dark'),
                array('check-circle', 'Terverifikasi', rupiah($summary['verified'] ?? 0), 'color-green-dark'),
                array('clock', 'Menunggu Verifikasi', rupiah($summary['pending'] ?? 0), 'color-orange-dark'),
                array('hourglass-half', 'Sisa Tagihan', rupiah($summary['outstanding'] ?? 0), simp_money_cents($summary['outstanding'] ?? 0) > 0 ? 'color-red-dark' : 'color-green-dark')
            );
            ?>
            <?php foreach ($summaryItems as $index => $item): ?>
                <div class="d-flex align-items-center py-3 <?= $index + 1 < count($summaryItems) ? 'border-bottom' : '' ?>">
                    <span class="icon icon-s rounded-xl bg-blue-light color-blue-dark me-3"><i class="fa fa-<?= $item[0] ?>"></i></span>
                    <span class="font-13 color-theme"><?= e($item[1]) ?></span>
                    <strong class="font-15 <?= $item[3] ?> ms-auto text-end simp-balance-value"><?= $item[2] ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card-style">
        <div class="content mb-2">
            <h2 class="mb-2">Metode Terverifikasi</h2>
            <?php $methods = array(array('money-bill-wave','Tunai',$summary['cash_total'] ?? 0,'color-green-dark'),array('university','Transfer',$summary['transfer_total'] ?? 0,'color-blue-dark'),array('qrcode','QRIS',$summary['qris_total'] ?? 0,'color-magenta-dark')); ?>
            <?php foreach ($methods as $index => $method): ?>
                <div class="d-flex align-items-center py-3 <?= $index + 1 < count($methods) ? 'border-bottom' : '' ?>"><i class="fa fa-<?= $method[0] ?> color-highlight icon-30"></i><span class="font-13 color-theme"><?= e($method[1]) ?></span><strong class="font-15 <?= $method[3] ?> ms-auto simp-balance-value"><?= rupiah($method[2]) ?></strong></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="content mb-0">
        <div class="d-flex align-items-center mb-3"><h2 class="mb-0">Daftar Data Bayar</h2><span class="badge bg-blue-dark color-white ms-auto"><?= number_format(count($rows)) ?> data</span></div>
        <?php if (!$rows): ?>
            <div class="card card-style mx-0"><div class="content text-center py-4"><span class="icon icon-l rounded-xl bg-blue-light color-blue-dark mb-3"><i class="fa fa-search"></i></span><h4>Data Tidak Ditemukan</h4><p class="mb-0 color-theme">Belum ada registrasi pada filter yang dipilih.</p></div></div>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <?php $state = isset($stateLabels[$row['payment_state']]) ? $stateLabels[$row['payment_state']] : $stateLabels['unpaid']; ?>
            <div class="card card-style mx-0">
                <div class="content mb-2">
                    <div class="d-flex align-items-start mb-3">
                        <div class="min-width-zero pe-2"><h3 class="mb-0 text-break"><?= e($row['village_name']) ?></h3><p class="font-13 color-theme mb-0"><?= e($row['district_name']) ?> · <?= e($row['regency_name']) ?></p></div>
                        <span class="badge <?= $state[1] ?> color-white ms-auto flex-shrink-0"><?= e($state[0]) ?></span>
                    </div>
                    <div class="rounded-s bg-blue-light px-3 py-2 mb-2"><p class="font-13 font-600 color-blue-dark mb-0"><?= e($row['event_name']) ?></p><p class="font-12 color-theme mb-0"><?= e(isset($billingLabels[$row['billing_mode']]) ? $billingLabels[$row['billing_mode']] : $row['billing_mode']) ?> · <?= number_format((int)$row['participant_count']) ?> peserta</p></div>
                    <?php
                    $facts = array(
                        array('Tagihan', rupiah($row['due_amount']), ''),
                        array('Terverifikasi', rupiah($row['verified_amount']), 'color-green-dark'),
                        array('Menunggu Verifikasi', rupiah($row['pending_amount']), 'color-orange-dark'),
                        array('Sisa Tagihan', rupiah($row['outstanding_amount']), simp_money_cents($row['outstanding_amount']) > 0 ? 'color-red-dark' : 'color-green-dark')
                    );
                    ?>
                    <?php foreach ($facts as $index => $fact): ?><div class="d-flex align-items-center py-2 <?= $index + 1 < count($facts) ? 'border-bottom' : '' ?>"><span class="font-13 color-theme"><?= e($fact[0]) ?></span><strong class="font-14 <?= $fact[2] ?> ms-auto text-end simp-balance-value"><?= $fact[1] ?></strong></div><?php endforeach; ?>
                    <p class="font-12 color-theme mt-3 mb-0">Tunai <?= rupiah($row['cash_total']) ?> · Transfer <?= rupiah($row['transfer_total']) ?> · QRIS <?= rupiah($row['qris_total']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($hasAnyData): ?>
        <?php $this->load->view('reports/print_modal', array(
            'printModalId' => 'payment-data-all-print-modal',
            'printModalTitle' => 'Data Bayar Semua Wilayah',
            'printPreviewUrl' => site_url('data-bayar/cetak'),
            'printPdfUrl' => site_url('data-bayar/pdf'),
            'printFormatLabel' => '',
            'printPaperNote' => '',
            'printIconOnly' => TRUE
        )); ?>
        <?php if ($filterActive && $rows): ?>
            <?php $this->load->view('reports/print_modal', array(
                'printModalId' => 'payment-data-filter-print-modal',
                'printModalTitle' => 'Data Bayar Hasil Filter',
                'printPreviewUrl' => site_url('data-bayar/cetak') . $filteredSuffix,
                'printPdfUrl' => site_url('data-bayar/pdf') . $filteredSuffix,
                'printFormatLabel' => '',
                'printPaperNote' => '',
                'printIconOnly' => TRUE
            )); ?>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
