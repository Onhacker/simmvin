<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Render a standalone HTML document as an Indonesian F4 (folio) PDF.
 *
 * The renderer deliberately disables remote assets, JavaScript, and embedded
 * PHP. Report views therefore stay deterministic and cannot make network
 * requests while a PDF is being generated.
 */
class Pdf_renderer
{
    const F4_WIDTH_POINTS = 595.276;
    const F4_HEIGHT_POINTS = 935.433;

    public function render_f4($html)
    {
        return $this->render_with_paper($html, FALSE);
    }

    /** Render an Indonesian F4/folio page in landscape orientation. */
    public function render_f4_landscape($html)
    {
        return $this->render_with_paper($html, TRUE);
    }

    private function render_with_paper($html, $landscape)
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('Mesin PDF belum terpasang. Jalankan composer install pada folder aplikasi.');
        }

        $cachePath = $this->writable_cache_path();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('defaultMediaType', 'print');
        $options->set('isRemoteEnabled', FALSE);
        $options->set('isJavascriptEnabled', FALSE);
        $options->set('isPhpEnabled', FALSE);
        $options->set('isFontSubsettingEnabled', TRUE);
        $options->set('tempDir', $cachePath);
        $options->set('fontDir', $cachePath);
        $options->set('fontCache', $cachePath);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml((string) $html, 'UTF-8');
        $orientation = $landscape ? 'landscape' : 'portrait';
        $dompdf->setPaper(array(0, 0, self::F4_WIDTH_POINTS, self::F4_HEIGHT_POINTS), $orientation);
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        if ($font) {
            if ($landscape) {
                $canvas->page_text(self::F4_HEIGHT_POINTS - 130, self::F4_WIDTH_POINTS - 20, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, 7.5, array(0.35, 0.35, 0.35));
            } else {
                $canvas->page_text(466, 916, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, 7.5, array(0.35, 0.35, 0.35));
            }
        }

        return $dompdf->output();
    }

    private function writable_cache_path()
    {
        $candidates = array(
            APPPATH . 'cache/dompdf',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'simp-dompdf-cache'
        );
        foreach ($candidates as $path) {
            if (!is_dir($path)) {
                $parent = dirname($path);
                if (!is_dir($parent) || !is_writable($parent)) continue;
                if (!mkdir($path, 0755, TRUE) && !is_dir($path)) continue;
            }
            if (is_writable($path)) return $path;
        }
        throw new RuntimeException('Folder kerja PDF tidak dapat dibuat atau tidak dapat ditulis.');
    }
}
