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
                <span id="payment-data-count" class="badge bg-blue-dark color-white ms-auto flex-shrink-0"><?= number_format(count($rows)) ?> data</span>
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
                    <div class="col-7 pe-1"><button type="submit" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-payment-filter-submit><i class="fa fa-filter me-1"></i>Terapkan</button></div>
                    <div class="col-5 ps-1"><a href="<?= site_url('data-bayar') ?>" class="btn btn-full btn-m bg-theme color-theme border rounded-s font-600" data-payment-filter-reset><i class="fa fa-redo me-1 color-highlight"></i>Reset</a></div>
                </div>
            </form>

            <?php if ($hasAnyData): ?>
                <div class="divider mt-4 mb-3"></div>
                <div id="payment-data-print-actions" class="row mb-0">
                    <div id="payment-data-all-print-column" class="<?= $filterActive && $rows ? 'col-6 pe-1' : 'col-12' ?>">
                        <a href="<?= site_url('data-bayar/cetak') ?>" class="btn btn-full btn-m bg-theme color-theme border rounded-s font-600" data-report-preview-open="payment-data-all-print-modal"><i class="fa fa-print me-1 color-highlight"></i>Cetak Semua</a>
                    </div>
                    <div id="payment-data-filter-print-column" class="col-6 ps-1<?= $filterActive && $rows ? '' : ' d-none' ?>"><a href="<?= site_url('data-bayar/cetak') . $filteredSuffix ?>" class="btn btn-full btn-m gradient-highlight rounded-s font-600" data-payment-filter-print-trigger data-report-preview-open="payment-data-filter-print-modal"><i class="fa fa-print me-1"></i>Cetak Filter</a></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php $this->load->view('payment_data/_results', array('report'=>$report, 'filterScope'=>$filterScope)); ?>

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
