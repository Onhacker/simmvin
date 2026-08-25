<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$printModalId = isset($printModalId) ? preg_replace('/[^a-z0-9_-]/i', '', $printModalId) : 'report-print-modal';
$printModalTitle = isset($printModalTitle) ? (string) $printModalTitle : 'Pratinjau Laporan';
$printPreviewUrl = isset($printPreviewUrl) ? (string) $printPreviewUrl : '#';
$printPdfUrl = isset($printPdfUrl) ? (string) $printPdfUrl : '#';
$printExcelUrl = isset($printExcelUrl) ? trim((string) $printExcelUrl) : '';
$printExcelLabel = isset($printExcelLabel) && trim((string) $printExcelLabel) !== '' ? (string) $printExcelLabel : 'Excel';
$printPaperNote = isset($printPaperNote) ? (string) $printPaperNote : 'Gunakan ukuran kertas F4/Folio 210 × 330 mm pada pengaturan printer.';
$printFormatLabel = isset($printFormatLabel) ? (string) $printFormatLabel : 'Dokumen F4';
$printIconOnly = !empty($printIconOnly);
$printFrameId = $printModalId . '-frame';
?>
<a id="<?= e($printModalId) ?>-opener" href="#" class="d-none" data-menu="<?= e($printModalId) ?>" aria-hidden="true" tabindex="-1"></a>
<div id="<?= e($printModalId) ?>" class="menu menu-box-modal rounded-m simp-print-modal" data-menu-width="980" data-menu-height="820" role="dialog" aria-modal="true" aria-labelledby="<?= e($printModalId) ?>-title">
    <div class="content mb-0">
        <div class="d-flex align-items-start simp-print-modal-header">
            <div class="min-width-zero pe-3">
                <?php if (trim($printFormatLabel) !== ''): ?><p class="font-600 color-highlight mb-n1"><?= e($printFormatLabel) ?></p><?php endif; ?>
                <h3 id="<?= e($printModalId) ?>-title" class="font-20 mb-0"><?= e($printModalTitle) ?></h3>
            </div>
            <button type="button" class="close-menu btn btn-xxs bg-theme color-theme border rounded-s ms-auto flex-shrink-0" aria-label="Tutup pratinjau"><i class="fa fa-times"></i></button>
        </div>
        <div class="simp-print-zoom-toolbar" data-report-zoom-controls>
            <span class="simp-print-zoom-title"><i class="fa fa-search me-1 color-highlight"></i>Zoom pratinjau</span>
            <button type="button" class="btn btn-xxs bg-theme color-theme border rounded-s" data-report-zoom="-10" data-report-zoom-frame="<?= e($printFrameId) ?>" aria-label="Perkecil pratinjau" disabled>
                <i class="fa fa-search-minus"></i>
            </button>
            <span class="simp-print-zoom-value" data-report-zoom-value aria-live="polite">100%</span>
            <button type="button" class="btn btn-xxs bg-theme color-theme border rounded-s" data-report-zoom="10" data-report-zoom-frame="<?= e($printFrameId) ?>" aria-label="Perbesar pratinjau" disabled>
                <i class="fa fa-search-plus"></i>
            </button>
        </div>
        <div class="simp-print-frame-shell">
            <div class="simp-print-frame-loading" data-report-preview-loading>
                <i class="fa fa-spinner fa-spin color-highlight"></i>
                <span>Menyiapkan pratinjau...</span>
            </div>
            <iframe id="<?= e($printFrameId) ?>" class="simp-print-frame" title="<?= e($printModalTitle) ?>" data-src="<?= e($printPreviewUrl) ?>" data-report-preview-frame></iframe>
        </div>
        <div class="simp-print-modal-actions<?= $printIconOnly ? ' is-icon-only' : '' ?>">
            <?php if ($printExcelUrl !== ''): ?>
                <a href="<?= e($printExcelUrl) ?>" class="btn btn-m rounded-s font-600 border-green-dark color-green-dark" data-report-file-download data-report-file-label="<?= e($printExcelLabel) ?>" aria-label="Unduh <?= e($printExcelLabel) ?>" title="Unduh <?= e($printExcelLabel) ?>">
                    <i class="fa fa-file-excel<?= $printIconOnly ? '' : ' me-1' ?>"></i><?php if ($printIconOnly): ?><span class="visually-hidden">Unduh <?= e($printExcelLabel) ?></span><?php else: ?> <?= e($printExcelLabel) ?><?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="<?= e($printPdfUrl) ?>" class="btn btn-m rounded-s font-600 border-blue-dark color-blue-dark" target="_blank" rel="noopener" data-report-file-download data-report-file-label="PDF" aria-label="Unduh PDF" title="Unduh PDF">
                <i class="fa fa-file-pdf<?= $printIconOnly ? '' : ' me-1' ?>"></i><?php if ($printIconOnly): ?><span class="visually-hidden">Unduh PDF</span><?php else: ?> PDF<?php endif; ?>
            </a>
            <button type="button" class="btn btn-m rounded-s font-600 gradient-highlight" data-report-print="<?= e($printFrameId) ?>" aria-label="Cetak dokumen" title="Cetak dokumen">
                <i class="fa fa-print<?= $printIconOnly ? '' : ' me-1' ?>"></i><?php if ($printIconOnly): ?><span class="visually-hidden">Cetak dokumen</span><?php else: ?> Cetak<?php endif; ?>
            </button>
        </div>
        <?php if (trim($printPaperNote) !== ''): ?><p class="simp-print-paper-note mb-0"><?= e($printPaperNote) ?></p><?php endif; ?>
    </div>
</div>
