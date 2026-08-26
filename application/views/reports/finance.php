<?php defined('BASEPATH') OR exit('No direct script access allowed');
$financeQuery = http_build_query(array_filter(array(
    'date_from' => isset($filters['date_from']) ? $filters['date_from'] : '',
    'date_to' => isset($filters['date_to']) ? $filters['date_to'] : ''
), function ($value) { return $value !== ''; }));
$financeQuerySuffix = $financeQuery !== '' ? '?' . $financeQuery : '';
?>

<div class="card card-style d-print-none">
    <div class="content mb-0">
        <div class="mb-3"><p class="font-600 color-highlight mb-n1">Arus dana terverifikasi</p><h2 class="mb-0">Periode Laporan</h2></div>

        <form method="get" action="<?= site_url('laporan/keuangan') ?>" data-finance-filter-form>
            <div class="row mb-0">
                <div class="col-md-6">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="finance-filter-from" type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                        <label for="finance-filter-from" class="color-highlight font-12 font-500">Dari Tanggal</label>
                        <i class="fa fa-check disabled valid me-4 pe-3 font-12 color-green-dark"></i>
                        <i class="fa fa-times disabled invalid me-4 pe-3 font-12 color-red-dark"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-style input-style-always-active has-borders no-icon mb-4">
                        <input class="form-control" id="finance-filter-to" type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                        <label for="finance-filter-to" class="color-highlight font-12 font-500">Sampai Tanggal</label>
                        <i class="fa fa-check disabled valid me-4 pe-3 font-12 color-green-dark"></i>
                        <i class="fa fa-times disabled invalid me-4 pe-3 font-12 color-red-dark"></i>
                    </div>
                </div>
                <div class="col-6 pe-1">
                    <button class="btn btn-full btn-s font-13 font-600 gradient-highlight rounded-s mb-4" type="submit" data-finance-apply>
                        <i class="fas fa-filter me-1"></i><span>Terapkan</span>
                    </button>
                </div>
                <div class="col-6 ps-1">
                    <a class="btn btn-full btn-s font-13 font-600 bg-theme color-theme border rounded-s mb-4" href="<?= e(site_url('laporan/keuangan/cetak') . $financeQuerySuffix) ?>" data-finance-print-trigger data-report-preview-open="finance-print-modal">
                        <i class="fas fa-print me-1 color-highlight"></i>Cetak
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php $this->load->view('reports/print_modal', array(
    'printModalId' => 'finance-print-modal',
    'printModalTitle' => 'Cetak Laporan Keuangan',
    'printPreviewUrl' => site_url('laporan/keuangan/cetak') . $financeQuerySuffix,
    'printPdfUrl' => site_url('laporan/keuangan/pdf') . $financeQuerySuffix
)); ?>

<?php $this->load->view('reports/finance_content', array('report'=>$report,'breakdown'=>$breakdown,'filters'=>$filters)); ?>
