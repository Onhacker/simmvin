<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Build native XLSX reports from the same active-event datasets used by the UI.
 *
 * Database text is always written explicitly as text. Besides keeping document
 * numbers intact, this prevents a name or note beginning with =, +, - or @ from
 * being interpreted by Excel as a formula.
 */
class Excel_renderer
{
    const BLUE = '1F5FAB';
    const DARK = '111827';
    const MUTED = '5F6B7A';
    const LIGHT_BLUE = 'EAF2FC';
    const LIGHT_ROW = 'F7F9FC';
    const BORDER = 'D7DEE8';
    const GREEN = '18743D';
    const RED = 'B42318';
    const CURRENCY_FORMAT = '"Rp" #,##0.00;[Red]("Rp" #,##0.00);-';
    // MOU component columns should show an explicit zero instead of the dash
    // used by analytical report sheets.
    const MOU_CURRENCY_FORMAT = '"Rp" #,##0.00;[Red]("Rp" #,##0.00);"Rp" 0.00';

    public function render_income(array $data, array $villageReport)
    {
        $this->assert_available();

        $spreadsheet = new Spreadsheet();
        try {
            $report = isset($data['report']) && is_array($data['report']) ? $data['report'] : array();
            $view = isset($report['view']) && $report['view'] === 'participant' ? 'participant' : 'village';
            $detailName = $view === 'participant' ? 'Rincian Peserta' : 'Rincian Desa';
            $reconciliationName = $view === 'participant' ? 'Rekonsiliasi Desa' : $detailName;

            $spreadsheet->getProperties()
                ->setCreator('MVIN')
                ->setTitle('Laporan Pemasukan')
                ->setSubject('Pemasukan event aktif')
                ->setDescription('Ekspor otomatis dari MVIN.');

            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Ringkasan');

            if ($view === 'participant') {
                $detailSheet = $spreadsheet->createSheet();
                $detailSheet->setTitle($detailName);
                $detailMeta = $this->write_participant_details($detailSheet, $report, $data);

                $villageSheet = $spreadsheet->createSheet();
                $villageSheet->setTitle($reconciliationName);
                $reconciliationMeta = $this->write_village_details($villageSheet, $villageReport, $data, TRUE);
            } else {
                $detailSheet = $spreadsheet->createSheet();
                $detailSheet->setTitle($detailName);
                $reconciliationMeta = $this->write_village_details($detailSheet, $report, $data, FALSE);
                $detailMeta = $reconciliationMeta;
            }

            $this->write_income_summary($summarySheet, $data, $reconciliationName, $reconciliationMeta, $detailName, $detailMeta);
            $spreadsheet->setActiveSheetIndex(0);

            return $this->save_to_string($spreadsheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function render_expenses(array $data)
    {
        $this->assert_available();

        $spreadsheet = new Spreadsheet();
        try {
            $spreadsheet->getProperties()
                ->setCreator('MVIN')
                ->setTitle('Laporan Pengeluaran')
                ->setSubject('Pengeluaran event aktif')
                ->setDescription('Ekspor otomatis dari MVIN.');

            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Ringkasan');
            $detailSheet = $spreadsheet->createSheet();
            $detailSheet->setTitle('Pengeluaran');
            $detailMeta = $this->write_expense_details($detailSheet, $data);
            $this->write_expense_summary($summarySheet, $data, $detailMeta);
            $spreadsheet->setActiveSheetIndex(0);

            return $this->save_to_string($spreadsheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function render_accounts(array $data)
    {
        $this->assert_available();

        $spreadsheet = new Spreadsheet();
        try {
            $spreadsheet->getProperties()
                ->setCreator('MVIN')
                ->setTitle('Laporan Saldo Kas & Rekening')
                ->setSubject('Posisi saldo akun dana')
                ->setDescription('Ekspor otomatis dari MVIN.');

            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Ringkasan');
            $detailSheet = $spreadsheet->createSheet();
            $detailSheet->setTitle('Akun Dana');
            $detailMeta = $this->write_account_details($detailSheet, $data);
            $this->write_account_summary($summarySheet, $data, $detailMeta);
            $spreadsheet->setActiveSheetIndex(0);

            return $this->save_to_string($spreadsheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Export company liabilities and their payment history.
     *
     * Debt reports are intentionally kept separate from the expense export:
     * the liability is recorded when the debt is created, while the cash-basis
     * payment rows are recorded later as expenses.  Keeping both sheets in the
     * workbook makes the outstanding balance auditable without duplicating any
     * ledger data.
     */
    public function render_debts(array $data)
    {
        $this->assert_available();

        $spreadsheet = new Spreadsheet();
        try {
            $spreadsheet->getProperties()
                ->setCreator('MVIN')
                ->setTitle('Laporan Hutang Perusahaan')
                ->setSubject('Kewajiban dan pembayaran hutang perusahaan')
                ->setDescription('Ekspor otomatis dari MVIN.');

            // debt_report_data() calls this value eventScope (rather than the
            // reportScope key used by the other report builders). Normalize it
            // locally so the shared sheet layout still shows the right scope.
            $reportData = $data;
            if (!isset($reportData['reportScope']) && isset($reportData['eventScope'])) {
                $reportData['reportScope'] = $reportData['eventScope'];
            }

            $rows = isset($reportData['rows']) && is_array($reportData['rows'])
                ? $reportData['rows'] : array();

            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Ringkasan');

            $detailSheet = $spreadsheet->createSheet();
            $detailSheet->setTitle('Hutang');
            $detailMeta = $this->write_debt_details($detailSheet, $reportData, $rows);

            $paymentSheet = $spreadsheet->createSheet();
            $paymentSheet->setTitle('Pembayaran');
            $paymentRows = $this->debt_payment_rows($reportData, $rows);
            $paymentMeta = $this->write_debt_payment_details($paymentSheet, $reportData, $paymentRows);

            $this->write_debt_summary($summarySheet, $reportData, $detailMeta, $paymentMeta);
            $spreadsheet->setActiveSheetIndex(0);

            return $this->save_to_string($spreadsheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Export the active participant roster as raw mailing data.
     *
     * There is intentionally no title, banner, metadata row, or column header:
     * the first worksheet row is the first participant so it can be consumed
     * directly by the user's mailing workflow. Every value is written as text
     * so phone numbers retain their leading zeroes.
     */
    public function render_registration_mailing(array $rows)
    {
        $this->assert_available();

        $spreadsheet = new Spreadsheet();
        try {
            $spreadsheet->getProperties()
                ->setCreator('MVIN')
                ->setTitle('Peserta Mailing')
                ->setSubject('Data peserta event aktif untuk mailing')
                ->setDescription('Ekspor data peserta aktif tanpa baris header.');

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Peserta');
            $sheet->setShowGridlines(FALSE);

            $columns = array('A', 'B', 'C', 'D', 'E', 'F', 'G');
            $widths = array(30, 34, 25, 25, 27, 24, 18);
            foreach ($columns as $index => $column) {
                $sheet->getColumnDimension($column)->setWidth($widths[$index]);
            }

            foreach ($rows as $index => $row) {
                $excelRow = $index + 1;
                $values = array(
                    isset($row['full_name']) ? $row['full_name'] : '',
                    isset($row['position']) ? $row['position'] : '',
                    isset($row['village_name']) ? $row['village_name'] : '',
                    isset($row['district_name']) ? $row['district_name'] : '',
                    isset($row['regency_name']) ? $row['regency_name'] : '',
                    isset($row['province_name']) ? $row['province_name'] : '',
                    isset($row['phone']) ? $row['phone'] : ''
                );
                foreach ($values as $valueIndex => $value) {
                    $this->set_text($sheet, $columns[$valueIndex] . $excelRow, $value);
                }
                $sheet->getStyle('A' . $excelRow . ':G' . $excelRow)
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(FALSE);
                $sheet->getRowDimension($excelRow)->setRowHeight(20);
            }

            if ($rows) {
                $lastRow = count($rows);
                $sheet->getStyle('A1:G' . $lastRow)->getFont()->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::DARK));
                $sheet->getPageSetup()
                    ->setPaperSize(PageSetup::PAPERSIZE_FOLIO)
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.25)->setRight(0.25)->setBottom(0.25)->setLeft(0.25);
                $sheet->getPageSetup()->setPrintArea('A1:G' . $lastRow);
            }

            $spreadsheet->setActiveSheetIndex(0);
            return $this->save_to_string($spreadsheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Export the active village directory as a source sheet for MOU mail merge.
     * Contract values use the registration's immutable expected_amount
     * snapshot. For a package event, the village tariff is separated from the
     * additional-participant component without depending on payment status.
     */
    public function render_registration_village_mailing(array $rows)
    {
        $this->assert_available();

        $spreadsheet = new Spreadsheet();
        try {
            $spreadsheet->getProperties()
                ->setCreator('MVIN')
                ->setTitle('Data Desa')
                ->setSubject('Data desa event aktif untuk mailing MOU')
                ->setDescription('Ekspor kolom Data Desa dari MVIN.');

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Data Desa');
            $sheet->setShowGridlines(FALSE);

            // Keep the original Data Desa fields in A:C so an existing mailing
            // source remains compatible, then append the requested MOU fields.
            $columns = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H');
            $headers = array(
                'Kecamatan',
                'Desa',
                'Jumlah Peserta',
                'No MOU',
                'Jumlah Pembayaran Per Desa',
                'Jumlah Pembayaran Tambahan',
                'Total',
                'Terbilang'
            );
            $widths = array(28, 32, 16, 34, 24, 24, 22, 54);
            foreach ($columns as $index => $column) {
                $sheet->getColumnDimension($column)->setWidth($widths[$index]);
                $this->set_text($sheet, $column . '1', $headers[$index]);
            }
            $sheet->getStyle('A1:H1')->applyFromArray($this->header_style());
            $sheet->getRowDimension(1)->setRowHeight(34);
            $sheet->freezePane('A2');

            $mouSequences = array();
            foreach ($rows as $index => $row) {
                $excelRow = $index + 2;
                $amounts = $this->registration_village_contract_amounts($row);
                $this->set_text($sheet, 'A' . $excelRow, isset($row['district_name']) ? $row['district_name'] : '');
                $this->set_text($sheet, 'B' . $excelRow, isset($row['village_name']) ? $row['village_name'] : '');
                $this->set_number($sheet, 'C' . $excelRow, isset($row['participant_count']) ? $row['participant_count'] : 0);
                $this->set_text($sheet, 'D' . $excelRow, $this->registration_mou_number($row, $mouSequences));
                $this->set_number($sheet, 'E' . $excelRow, $amounts['village']);
                $this->set_number($sheet, 'F' . $excelRow, $amounts['additional']);
                $this->set_number($sheet, 'G' . $excelRow, $amounts['total']);
                $this->set_text($sheet, 'H' . $excelRow, $this->rupiah_in_words($amounts['total']));
                $this->style_detail_row($sheet, $excelRow, 'H', $index);
                $sheet->getRowDimension($excelRow)->setRowHeight(38);
                $sheet->getStyle('A' . $excelRow . ':G' . $excelRow)
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(FALSE);
                $sheet->getStyle('H' . $excelRow)
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(TRUE);
                $sheet->getStyle('C' . $excelRow . ':G' . $excelRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            $lastRow = max(1, count($rows) + 1);
            $sheet->getStyle('A1:H' . $lastRow)->getFont()->setSize(11);
            $sheet->getStyle('A1:H' . $lastRow)->getBorders()->getBottom()
                ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::BORDER);
            if (count($rows) > 0) {
                $sheet->getStyle('C2:C' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('E2:G' . $lastRow)->getNumberFormat()->setFormatCode(self::MOU_CURRENCY_FORMAT);
            }
            $sheet->setAutoFilter('A1:H' . $lastRow);
            $sheet->getPageSetup()
                ->setPaperSize(PageSetup::PAPERSIZE_FOLIO)
                ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                ->setFitToWidth(1)
                ->setFitToHeight(0);
            $sheet->getPageMargins()->setTop(0.35)->setRight(0.35)->setBottom(0.45)->setLeft(0.35);
            $sheet->getPageSetup()->setPrintArea('A1:H' . $lastRow);
            $sheet->getHeaderFooter()->setOddFooter('&LDiekspor dari MVIN&C&F&RHalaman &P / &N');

            $spreadsheet->setActiveSheetIndex(0);
            return $this->save_to_string($spreadsheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function write_income_summary(Worksheet $sheet, array $data, $sourceSheet, array $sourceMeta, $detailSheet, array $detailMeta)
    {
        $view = isset($data['report']['view']) && $data['report']['view'] === 'participant' ? 'Per Peserta' : 'Per Desa';
        $sourceLast = (int) $sourceMeta['last_data_row'];
        $sourceFirst = (int) $sourceMeta['first_data_row'];
        $sourceRef = "'" . $sourceSheet . "'!";

        $this->prepare_summary($sheet, 'Laporan Pemasukan', $data, $view);
        $this->section_title($sheet, 'A8:F8', 'Ringkasan Dana Masuk');

        $pairs = array(
            array('A9', 'Jumlah Desa', 'B9', '=COUNTA(' . $sourceRef . '$C$' . $sourceFirst . ':$C$' . $sourceLast . ')', FALSE),
            array('D9', 'Jumlah Peserta', 'E9', '=SUM(' . $sourceRef . '$G$' . $sourceFirst . ':$G$' . $sourceLast . ')', FALSE),
            array('A10', 'Total Tagihan', 'B10', '=SUM(' . $sourceRef . '$H$' . $sourceFirst . ':$H$' . $sourceLast . ')', TRUE),
            array('D10', 'Total Masuk', 'E10', '=SUM(' . $sourceRef . '$L$' . $sourceFirst . ':$L$' . $sourceLast . ')', TRUE),
            array('A11', 'Tunai', 'B11', '=SUM(' . $sourceRef . '$I$' . $sourceFirst . ':$I$' . $sourceLast . ')', TRUE),
            array('D11', 'Transfer', 'E11', '=SUM(' . $sourceRef . '$J$' . $sourceFirst . ':$J$' . $sourceLast . ')', TRUE),
            array('A12', 'QRIS', 'B12', '=SUM(' . $sourceRef . '$K$' . $sourceFirst . ':$K$' . $sourceLast . ')', TRUE),
            array('D12', 'Sisa Tagihan', 'E12', '=MAX(0,B10-E10)', TRUE)
        );
        foreach ($pairs as $pair) $this->summary_pair($sheet, $pair[0], $pair[1], $pair[2], $pair[3], $pair[4]);

        $this->section_title($sheet, 'A14:F14', 'Kontrol Rekonsiliasi');
        $this->set_text($sheet, 'A15', 'Status');
        $sheet->setCellValue('B15', '=IF(ABS(E10-SUM(B11,E11,B12))<0.01,"PASS","PERIKSA")');
        $this->set_text($sheet, 'D15', 'Selisih Metode');
        $sheet->setCellValue('E15', '=E10-SUM(B11,E11,B12)');
        $sheet->getStyle('E15')->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
        $sheet->getStyle('A15:E15')->getFont()->setBold(TRUE);
        $sheet->getStyle('B15')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::GREEN));

        $note = 'Nilai ringkasan dihitung dari rincian desa agar tagihan dan pembayaran tingkat desa tetap terekap secara utuh.';
        if ($view === 'Per Peserta') {
            $note .= ' Sheet Rincian Peserta menampilkan komponen peserta; pembayaran paket/per desa tetap direkonsiliasi pada sheet Rekonsiliasi Desa.';
        }
        $this->set_text($sheet, 'A17', 'Catatan');
        $sheet->mergeCells('B17:F18');
        $this->set_text($sheet, 'B17', $note);
        $sheet->getStyle('B17:F18')->getAlignment()->setWrapText(TRUE)->setVertical(Alignment::VERTICAL_TOP);

        $this->write_event_list($sheet, $data, 20);
        $this->finish_summary($sheet, 21 + max(1, count($this->events($data))));
    }

    private function write_expense_summary(Worksheet $sheet, array $data, array $detailMeta)
    {
        $first = (int) $detailMeta['first_data_row'];
        $last = (int) $detailMeta['last_data_row'];
        $ref = "'Pengeluaran'!";

        $this->prepare_summary($sheet, 'Laporan Pengeluaran', $data, 'Per Transaksi');
        $this->section_title($sheet, 'A8:F8', 'Ringkasan Dana Keluar');

        $pairs = array(
            array('A9', 'Jumlah Transaksi', 'B9', '=COUNTA(' . $ref . '$C$' . $first . ':$C$' . $last . ')', FALSE),
            array('D9', 'Terverifikasi', 'E9', '=COUNTIF(' . $ref . '$I$' . $first . ':$I$' . $last . ',"Terverifikasi")', FALSE),
            array('A10', 'Total Terverifikasi', 'B10', '=SUMIF(' . $ref . '$I$' . $first . ':$I$' . $last . ',"Terverifikasi",' . $ref . '$L$' . $first . ':$L$' . $last . ')', TRUE),
            array('D10', 'Total Menunggu', 'E10', '=SUMIF(' . $ref . '$I$' . $first . ':$I$' . $last . ',"Menunggu",' . $ref . '$L$' . $first . ':$L$' . $last . ')', TRUE),
            array('A11', 'Total Ditolak', 'B11', '=SUMIF(' . $ref . '$I$' . $first . ':$I$' . $last . ',"Ditolak",' . $ref . '$L$' . $first . ':$L$' . $last . ')', TRUE),
            array('D11', 'Menunggu', 'E11', '=COUNTIF(' . $ref . '$I$' . $first . ':$I$' . $last . ',"Menunggu")', FALSE),
            array('A12', 'Tunai Terverifikasi', 'B12', '=SUMPRODUCT((' . $ref . '$I$' . $first . ':$I$' . $last . '="Terverifikasi")*(' . $ref . '$H$' . $first . ':$H$' . $last . '="Tunai")*' . $ref . '$L$' . $first . ':$L$' . $last . ')', TRUE),
            array('D12', 'Transfer Terverifikasi', 'E12', '=SUMPRODUCT((' . $ref . '$I$' . $first . ':$I$' . $last . '="Terverifikasi")*(' . $ref . '$H$' . $first . ':$H$' . $last . '="Transfer")*' . $ref . '$L$' . $first . ':$L$' . $last . ')', TRUE),
            array('A13', 'QRIS Terverifikasi', 'B13', '=SUMPRODUCT((' . $ref . '$I$' . $first . ':$I$' . $last . '="Terverifikasi")*(' . $ref . '$H$' . $first . ':$H$' . $last . '="QRIS")*' . $ref . '$L$' . $first . ':$L$' . $last . ')', TRUE),
            array('D13', 'Ditolak', 'E13', '=COUNTIF(' . $ref . '$I$' . $first . ':$I$' . $last . ',"Ditolak")', FALSE)
        );
        foreach ($pairs as $pair) $this->summary_pair($sheet, $pair[0], $pair[1], $pair[2], $pair[3], $pair[4]);

        $this->section_title($sheet, 'A15:F15', 'Kontrol Rekonsiliasi');
        $this->set_text($sheet, 'A16', 'Status');
        $sheet->setCellValue('B16', '=IF(ABS(B10-SUM(B12,E12,B13))<0.01,"PASS","PERIKSA")');
        $this->set_text($sheet, 'D16', 'Selisih Metode');
        $sheet->setCellValue('E16', '=B10-SUM(B12,E12,B13)');
        $sheet->getStyle('E16')->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
        $sheet->getStyle('A16:E16')->getFont()->setBold(TRUE);
        $sheet->getStyle('B16')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::GREEN));

        $this->set_text($sheet, 'A18', 'Catatan');
        $sheet->mergeCells('B18:F19');
        $this->set_text($sheet, 'B18', 'Total per metode hanya menghitung transaksi berstatus Terverifikasi. Nilai total transaksi mencakup biaya admin.');
        $sheet->getStyle('B18:F19')->getAlignment()->setWrapText(TRUE)->setVertical(Alignment::VERTICAL_TOP);

        $this->write_event_list($sheet, $data, 21);
        $this->finish_summary($sheet, 22 + max(1, count($this->events($data))));
    }

    private function write_account_summary(Worksheet $sheet, array $data, array $detailMeta)
    {
        $first = (int)$detailMeta['first_data_row'];
        $last = (int)$detailMeta['last_data_row'];
        $ref = "'Akun Dana'!";

        $this->prepare_summary($sheet, 'Laporan Saldo Kas & Rekening', $data, 'Per Akun');
        $this->section_title($sheet, 'A8:F8', 'Ringkasan Posisi Dana');

        $pairs = array(
            array('A9', 'Total Saldo Dihitung', 'B9', '=SUMIF(' . $ref . '$J$' . $first . ':$J$' . $last . ',"Ya",' . $ref . '$I$' . $first . ':$I$' . $last . ')', TRUE),
            array('D9', 'Saldo Seluruh Akun', 'E9', '=SUM(' . $ref . '$I$' . $first . ':$I$' . $last . ')', TRUE),
            array('A10', 'Jumlah Akun', 'B10', '=COUNTA(' . $ref . '$B$' . $first . ':$B$' . $last . ')', FALSE),
            array('D10', 'Akun Aktif', 'E10', '=COUNTIF(' . $ref . '$K$' . $first . ':$K$' . $last . ',"Aktif")', FALSE),
            array('A11', 'Saldo Awal', 'B11', '=SUM(' . $ref . '$G$' . $first . ':$G$' . $last . ')', TRUE),
            array('D11', 'Mutasi Bersih', 'E11', '=SUM(' . $ref . '$H$' . $first . ':$H$' . $last . ')', TRUE),
            array('A12', 'Akun Masuk Total', 'B12', '=COUNTIF(' . $ref . '$J$' . $first . ':$J$' . $last . ',"Ya")', FALSE),
            array('D12', 'Akun Nonaktif', 'E12', '=COUNTIF(' . $ref . '$K$' . $first . ':$K$' . $last . ',"Nonaktif")', FALSE),
            array('A13', 'Saldo Dikecualikan', 'B13', '=SUMIF(' . $ref . '$J$' . $first . ':$J$' . $last . ',"Tidak",' . $ref . '$I$' . $first . ':$I$' . $last . ')', TRUE),
            array('D13', 'Akun Dikecualikan', 'E13', '=COUNTIF(' . $ref . '$J$' . $first . ':$J$' . $last . ',"Tidak")', FALSE)
        );
        foreach ($pairs as $pair) $this->summary_pair($sheet, $pair[0], $pair[1], $pair[2], $pair[3], $pair[4]);

        $this->section_title($sheet, 'A15:F15', 'Kontrol Rekonsiliasi');
        $this->set_text($sheet, 'A16', 'Status');
        $sheet->setCellValue('B16', '=IF(ABS(E9-B11-E11)<0.01,"PASS","PERIKSA")');
        $this->set_text($sheet, 'D16', 'Selisih Roll-forward');
        $sheet->setCellValue('E16', '=E9-B11-E11');
        $sheet->getStyle('E16')->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
        $sheet->getStyle('A16:E16')->getFont()->setBold(TRUE);
        $sheet->getStyle('B16')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::GREEN));

        $this->set_text($sheet, 'A18', 'Catatan');
        $sheet->mergeCells('B18:F18');
        $this->set_text($sheet, 'B18', 'Total Saldo Dihitung hanya menjumlah akun bertanda Ya. Saldo akhir = saldo awal + mutasi buku besar.');
        $sheet->getStyle('B18:F18')->getAlignment()->setWrapText(TRUE)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(18)->setRowHeight(28);

        $this->finish_summary($sheet, 18);
    }

    private function write_village_details(Worksheet $sheet, array $report, array $data, $isReconciliation)
    {
        $headers = array('No.', 'Event', 'Desa', 'Kecamatan', 'Kabupaten / Kota', 'Mode Tagihan', 'Jumlah Peserta', 'Total Tagihan', 'Tunai', 'Transfer', 'QRIS', 'Total Masuk', 'Sisa Tagihan', 'Status');
        $widths = array(7, 30, 24, 23, 25, 25, 16, 19, 17, 17, 17, 19, 19, 18);
        $title = $isReconciliation ? 'Rekonsiliasi Pemasukan Per Desa' : 'Rincian Pemasukan Per Desa';
        $rows = isset($report['rows']) && is_array($report['rows']) ? $report['rows'] : array();
        $meta = $this->prepare_detail($sheet, $title, $data, $headers, $widths, 'N');

        foreach ($rows as $index => $row) {
            $excelRow = $meta['first_data_row'] + $index;
            $this->set_number($sheet, 'A' . $excelRow, $index + 1);
            $this->set_text($sheet, 'B' . $excelRow, isset($row['event_name']) ? $row['event_name'] : '');
            $this->set_text($sheet, 'C' . $excelRow, isset($row['village_name']) ? $row['village_name'] : '');
            $this->set_text($sheet, 'D' . $excelRow, isset($row['district_name']) ? $row['district_name'] : '');
            $this->set_text($sheet, 'E' . $excelRow, isset($row['regency_name']) ? $row['regency_name'] : '');
            $this->set_text($sheet, 'F' . $excelRow, $this->billing_label(isset($row['billing_mode']) ? $row['billing_mode'] : ''));
            $this->set_number($sheet, 'G' . $excelRow, isset($row['participant_count']) ? $row['participant_count'] : 0);
            $this->set_number($sheet, 'H' . $excelRow, isset($row['due_amount']) ? $row['due_amount'] : 0);
            $this->set_number($sheet, 'I' . $excelRow, isset($row['cash_total']) ? $row['cash_total'] : 0);
            $this->set_number($sheet, 'J' . $excelRow, isset($row['transfer_total']) ? $row['transfer_total'] : 0);
            $this->set_number($sheet, 'K' . $excelRow, isset($row['qris_total']) ? $row['qris_total'] : 0);
            $sheet->setCellValue('L' . $excelRow, '=SUM(I' . $excelRow . ':K' . $excelRow . ')');
            $sheet->setCellValue('M' . $excelRow, '=MAX(0,H' . $excelRow . '-L' . $excelRow . ')');
            // Keep export status in lock-step with payment_status(): an
            // amount above the tagihan is explicitly labelled Lebih Bayar,
            // rather than being silently collapsed into Lunas.
            $sheet->setCellValue('N' . $excelRow, '=IF(H' . $excelRow . '<=0,IF(L' . $excelRow . '>0,"Lebih Bayar","Belum Ada Tagihan"),IF(L' . $excelRow . '>H' . $excelRow . ',"Lebih Bayar",IF(L' . $excelRow . '=H' . $excelRow . ',"Lunas",IF(L' . $excelRow . '>0,"Sebagian","Belum Bayar"))))');
            $this->style_detail_row($sheet, $excelRow, 'N', $index);
        }

        $meta = $this->finish_detail($sheet, $meta, count($rows), 'N', array('H', 'I', 'J', 'K', 'L', 'M'), array('G'));
        $this->style_status_column($sheet, 'N', $meta, count($rows));
        return $meta;
    }

    private function write_participant_details(Worksheet $sheet, array $report, array $data)
    {
        $headers = array('No.', 'Event', 'Nama Peserta', 'Jabatan', 'Desa', 'Kecamatan', 'Kabupaten / Kota', 'Mode Tagihan', 'Komponen Biaya', 'Tunai', 'Transfer', 'QRIS', 'Total Masuk', 'Sisa', 'Status / Keterangan');
        $widths = array(7, 28, 24, 23, 22, 22, 24, 25, 19, 17, 17, 17, 19, 19, 31);
        $rows = isset($report['rows']) && is_array($report['rows']) ? $report['rows'] : array();
        $meta = $this->prepare_detail($sheet, 'Rincian Pemasukan Per Peserta', $data, $headers, $widths, 'O');

        foreach ($rows as $index => $row) {
            $excelRow = $meta['first_data_row'] + $index;
            $mode = isset($row['billing_mode']) ? $row['billing_mode'] : '';
            $dueCents = simp_money_cents(isset($row['due_amount']) ? $row['due_amount'] : '0');
            if ($dueCents === NULL) $dueCents = 0;
            $due = simp_money_from_cents($dueCents);
            $participantBilling = $mode === 'per_participant';

            $this->set_number($sheet, 'A' . $excelRow, $index + 1);
            $this->set_text($sheet, 'B' . $excelRow, isset($row['event_name']) ? $row['event_name'] : '');
            $this->set_text($sheet, 'C' . $excelRow, isset($row['participant_name']) ? $row['participant_name'] : '');
            $this->set_text($sheet, 'D' . $excelRow, isset($row['position']) ? $row['position'] : '');
            $this->set_text($sheet, 'E' . $excelRow, isset($row['village_name']) ? $row['village_name'] : '');
            $this->set_text($sheet, 'F' . $excelRow, isset($row['district_name']) ? $row['district_name'] : '');
            $this->set_text($sheet, 'G' . $excelRow, isset($row['regency_name']) ? $row['regency_name'] : '');
            $this->set_text($sheet, 'H' . $excelRow, $this->billing_label($mode));
            $this->set_number($sheet, 'I' . $excelRow, $due);

            if ($participantBilling) {
                $this->set_number($sheet, 'J' . $excelRow, isset($row['cash_total']) ? $row['cash_total'] : 0);
                $this->set_number($sheet, 'K' . $excelRow, isset($row['transfer_total']) ? $row['transfer_total'] : 0);
                $this->set_number($sheet, 'L' . $excelRow, isset($row['qris_total']) ? $row['qris_total'] : 0);
                $sheet->setCellValue('M' . $excelRow, '=SUM(J' . $excelRow . ':L' . $excelRow . ')');
                $sheet->setCellValue('N' . $excelRow, '=MAX(0,I' . $excelRow . '-M' . $excelRow . ')');
                $sheet->setCellValue('O' . $excelRow, '=IF(I' . $excelRow . '<=0,IF(M' . $excelRow . '>0,"Lebih Bayar","Belum Ada Tagihan"),IF(M' . $excelRow . '>I' . $excelRow . ',"Lebih Bayar",IF(M' . $excelRow . '=I' . $excelRow . ',"Lunas",IF(M' . $excelRow . '>0,"Sebagian","Belum Bayar"))))');
            } else {
                if ($mode === 'per_village_extra') {
                    $this->set_text($sheet, 'O' . $excelRow, $dueCents > 0 ? 'Peserta Tambahan - pembayaran dicatat di desa' : 'Termasuk Paket Desa');
                } else {
                    $this->set_text($sheet, 'O' . $excelRow, 'Pembayaran Dicatat di Desa');
                }
            }
            $this->style_detail_row($sheet, $excelRow, 'O', $index);
        }

        $meta = $this->finish_detail($sheet, $meta, count($rows), 'O', array('I', 'J', 'K', 'L', 'M', 'N'), array());
        $this->style_status_column($sheet, 'O', $meta, count($rows));
        return $meta;
    }

    private function write_expense_details(Worksheet $sheet, array $data)
    {
        $headers = array('No.', 'Tanggal', 'Nomor', 'Event', 'Kategori', 'Tujuan Pengeluaran', 'Akun Dana', 'Metode', 'Status', 'Nilai', 'Biaya Admin', 'Total', 'Sumber Hutang', 'Catatan');
        $widths = array(7, 15, 24, 29, 22, 38, 23, 15, 16, 19, 17, 19, 28, 32);
        $rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : array();
        $meta = $this->prepare_detail($sheet, 'Rincian Pengeluaran', $data, $headers, $widths, 'N');
        $methodLabels = array('cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS');
        $statusLabels = array('verified' => 'Terverifikasi', 'pending' => 'Menunggu', 'rejected' => 'Ditolak');

        foreach ($rows as $index => $row) {
            $excelRow = $meta['first_data_row'] + $index;
            $this->set_number($sheet, 'A' . $excelRow, $index + 1);
            $this->set_date($sheet, 'B' . $excelRow, isset($row['expense_date']) ? $row['expense_date'] : '');
            $this->set_text($sheet, 'C' . $excelRow, isset($row['expense_no']) ? $row['expense_no'] : '');
            $this->set_text($sheet, 'D' . $excelRow, !empty($row['event_name']) ? $row['event_name'] : 'Pengeluaran Umum');
            $this->set_text($sheet, 'E' . $excelRow, isset($row['category_name']) ? $row['category_name'] : '');
            $this->set_text($sheet, 'F' . $excelRow, isset($row['description']) ? $row['description'] : '');
            $this->set_text($sheet, 'G' . $excelRow, isset($row['account_name']) ? $row['account_name'] : '');
            $method = isset($row['method']) ? $row['method'] : '';
            $status = isset($row['status']) ? $row['status'] : 'pending';
            $this->set_text($sheet, 'H' . $excelRow, isset($methodLabels[$method]) ? $methodLabels[$method] : ucwords(str_replace('_', ' ', $method)));
            $this->set_text($sheet, 'I' . $excelRow, isset($statusLabels[$status]) ? $statusLabels[$status] : ucwords(str_replace('_', ' ', $status)));
            $this->set_number($sheet, 'J' . $excelRow, isset($row['amount']) ? $row['amount'] : 0);
            $this->set_number($sheet, 'K' . $excelRow, isset($row['admin_fee']) ? $row['admin_fee'] : 0);
            $sheet->setCellValue('L' . $excelRow, '=SUM(J' . $excelRow . ':K' . $excelRow . ')');
            $debtSource = !empty($row['debt_id'])
                ? 'Pembayaran ' . (isset($row['debt_no']) ? $row['debt_no'] : '') . ' - ' . (isset($row['debt_creditor']) ? $row['debt_creditor'] : '')
                : '';
            $this->set_text($sheet, 'M' . $excelRow, $debtSource);
            $this->set_text($sheet, 'N' . $excelRow, isset($row['note']) ? $row['note'] : '');
            $this->style_detail_row($sheet, $excelRow, 'N', $index);
        }

        $meta = $this->finish_detail($sheet, $meta, count($rows), 'N', array('J', 'K', 'L'), array('A'), array('B'));
        $this->style_status_column($sheet, 'I', $meta, count($rows));
        return $meta;
    }

    private function write_debt_summary(Worksheet $sheet, array $data, array $detailMeta, array $paymentMeta)
    {
        $first = (int) $detailMeta['first_data_row'];
        $last = (int) $detailMeta['last_data_row'];
        $ref = "'Hutang'!";

        $this->prepare_summary($sheet, 'Laporan Hutang Perusahaan', $data, 'Per Hutang');
        $this->section_title($sheet, 'A8:F8', 'Ringkasan Kewajiban');

        $pairs = array(
            array('A9', 'Jumlah Hutang', 'B9', '=COUNTA(' . $ref . '$B$' . $first . ':$B$' . $last . ')', FALSE),
            array('D9', 'Belum Lunas', 'E9', '=COUNTIF(' . $ref . '$L$' . $first . ':$L$' . $last . ',"Belum Dibayar")+COUNTIF(' . $ref . '$L$' . $first . ':$L$' . $last . ',"Bayar Sebagian")+COUNTIF(' . $ref . '$L$' . $first . ':$L$' . $last . ',"Menunggu Verifikasi")', FALSE),
            array('A10', 'Total Pokok', 'B10', '=SUMIF(' . $ref . '$L$' . $first . ':$L$' . $last . ',"<>Dibatalkan",' . $ref . '$H$' . $first . ':$H$' . $last . ')', TRUE),
            array('D10', 'Sudah Dibayar', 'E10', '=SUMIF(' . $ref . '$L$' . $first . ':$L$' . $last . ',"<>Dibatalkan",' . $ref . '$I$' . $first . ':$I$' . $last . ')', TRUE),
            array('A11', 'Menunggu Verifikasi', 'B11', '=SUMIF(' . $ref . '$L$' . $first . ':$L$' . $last . ',"<>Dibatalkan",' . $ref . '$J$' . $first . ':$J$' . $last . ')', TRUE),
            array('D11', 'Sisa Hutang', 'E11', '=SUMIF(' . $ref . '$L$' . $first . ':$L$' . $last . ',"<>Dibatalkan",' . $ref . '$K$' . $first . ':$K$' . $last . ')', TRUE),
            array('A12', 'Lunas', 'B12', '=COUNTIF(' . $ref . '$L$' . $first . ':$L$' . $last . ',"Lunas")', FALSE),
            array('D12', 'Dibatalkan', 'E12', '=COUNTIF(' . $ref . '$L$' . $first . ':$L$' . $last . ',"Dibatalkan")', FALSE)
        );
        foreach ($pairs as $pair) {
            $this->summary_pair($sheet, $pair[0], $pair[1], $pair[2], $pair[3], $pair[4]);
        }

        $this->section_title($sheet, 'A14:F14', 'Kontrol Rekonsiliasi');
        $this->set_text($sheet, 'A15', 'Status');
        $sheet->setCellValue('B15', '=IF(ABS(B10-E10-E11)<0.01,"PASS","PERIKSA")');
        $this->set_text($sheet, 'D15', 'Selisih Roll-forward');
        $sheet->setCellValue('E15', '=B10-E10-E11');
        $sheet->getStyle('E15')->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
        $sheet->getStyle('A15:E15')->getFont()->setBold(TRUE);
        $sheet->getStyle('B15')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::GREEN));

        $paymentCount = max(0, (int) $paymentMeta['row_count']);
        $this->set_text($sheet, 'A17', 'Catatan');
        $sheet->mergeCells('B17:F18');
        $this->set_text($sheet, 'B17', 'Sisa hutang = total pokok dikurangi pembayaran terverifikasi. Pembayaran yang masih menunggu verifikasi ditampilkan terpisah dan belum mengurangi saldo akun.' . ($paymentCount ? ' Riwayat pembayaran tersedia pada sheet Pembayaran.' : ' Belum ada riwayat pembayaran.'));
        $sheet->getStyle('B17:F18')->getAlignment()->setWrapText(TRUE)->setVertical(Alignment::VERTICAL_TOP);

        $this->finish_summary($sheet, 18);
    }

    private function write_debt_details(Worksheet $sheet, array $data, array $rows)
    {
        $headers = array('No.', 'Nomor Hutang', 'Kreditur', 'Uraian', 'Kategori', 'Event', 'Tanggal Hutang', 'Nilai Pokok', 'Terverifikasi', 'Menunggu Verifikasi', 'Sisa Hutang', 'Status', 'Jumlah Pembayaran', 'Pembayaran Terakhir', 'Catatan');
        $widths = array(7, 22, 25, 38, 22, 29, 17, 19, 19, 22, 19, 21, 18, 20, 32);
        $meta = $this->prepare_detail($sheet, 'Rincian Hutang Perusahaan', $data, $headers, $widths, 'O');

        foreach ($rows as $index => $row) {
            $excelRow = $meta['first_data_row'] + $index;
            $principal = isset($row['principal_amount']) ? $row['principal_amount'] : 0;
            $paid = isset($row['paid_amount']) ? $row['paid_amount'] : 0;
            $pending = isset($row['pending_amount']) ? $row['pending_amount'] : 0;
            $paymentCount = isset($row['payment_count']) ? $row['payment_count'] : 0;
            $status = $this->debt_status_label($row);

            $this->set_number($sheet, 'A' . $excelRow, $index + 1);
            $this->set_text($sheet, 'B' . $excelRow, isset($row['debt_no']) ? $row['debt_no'] : '');
            $this->set_text($sheet, 'C' . $excelRow, isset($row['creditor']) ? $row['creditor'] : '');
            $this->set_text($sheet, 'D' . $excelRow, isset($row['description']) ? $row['description'] : '');
            $this->set_text($sheet, 'E' . $excelRow, isset($row['category_name']) ? $row['category_name'] : '');
            $this->set_text($sheet, 'F' . $excelRow, isset($row['event_name']) && $row['event_name'] !== '' ? $row['event_name'] : 'Hutang umum');
            $this->set_date($sheet, 'G' . $excelRow, $this->date_only(isset($row['debt_date']) ? $row['debt_date'] : ''));
            $this->set_number($sheet, 'H' . $excelRow, $principal);
            $this->set_number($sheet, 'I' . $excelRow, $paid);
            $this->set_number($sheet, 'J' . $excelRow, $pending);
            $sheet->setCellValue('K' . $excelRow, '=MAX(0,H' . $excelRow . '-I' . $excelRow . ')');
            $this->set_text($sheet, 'L' . $excelRow, $status);
            $this->set_number($sheet, 'M' . $excelRow, $paymentCount);
            $this->set_date($sheet, 'N' . $excelRow, $this->date_only(isset($row['last_payment_date']) ? $row['last_payment_date'] : ''));
            $this->set_text($sheet, 'O' . $excelRow, isset($row['note']) ? $row['note'] : '');
            $this->style_detail_row($sheet, $excelRow, 'O', $index);
        }

        $meta = $this->finish_detail($sheet, $meta, count($rows), 'O', array('H', 'I', 'J', 'K'), array('A', 'M'), array('G', 'N'), 'Belum ada hutang perusahaan.');
        $this->style_status_column($sheet, 'L', $meta, count($rows));
        return $meta;
    }

    private function write_debt_payment_details(Worksheet $sheet, array $data, array $rows)
    {
        $headers = array('No.', 'Nomor Hutang', 'Tanggal Bayar', 'Kreditur', 'Uraian', 'Akun Dana', 'Metode', 'Nilai', 'Biaya Admin', 'Total', 'Status', 'Dibuat Oleh', 'Diverifikasi Oleh', 'Bukti', 'Catatan');
        $widths = array(7, 22, 17, 25, 38, 24, 15, 19, 17, 19, 18, 24, 24, 12, 32);
        $meta = $this->prepare_detail($sheet, 'Riwayat Pembayaran Hutang', $data, $headers, $widths, 'O');
        $methodLabels = array('cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS');
        $statusLabels = array('verified' => 'Terverifikasi', 'pending' => 'Menunggu', 'rejected' => 'Ditolak');

        foreach ($rows as $index => $row) {
            $excelRow = $meta['first_data_row'] + $index;
            $method = isset($row['method']) ? $row['method'] : '';
            $status = isset($row['status']) ? $row['status'] : 'pending';
            $amount = isset($row['amount']) ? $row['amount'] : 0;
            $fee = isset($row['admin_fee']) ? $row['admin_fee'] : 0;

            $this->set_number($sheet, 'A' . $excelRow, $index + 1);
            $this->set_text($sheet, 'B' . $excelRow, isset($row['debt_no']) ? $row['debt_no'] : '');
            $this->set_date($sheet, 'C' . $excelRow, $this->date_only(isset($row['expense_date']) ? $row['expense_date'] : (isset($row['payment_date']) ? $row['payment_date'] : '')));
            $this->set_text($sheet, 'D' . $excelRow, isset($row['creditor']) ? $row['creditor'] : '');
            $this->set_text($sheet, 'E' . $excelRow, isset($row['description']) ? $row['description'] : '');
            $this->set_text($sheet, 'F' . $excelRow, isset($row['account_name']) ? $row['account_name'] : '');
            $this->set_text($sheet, 'G' . $excelRow, isset($methodLabels[$method]) ? $methodLabels[$method] : ucwords(str_replace('_', ' ', $method)));
            $this->set_number($sheet, 'H' . $excelRow, $amount);
            $this->set_number($sheet, 'I' . $excelRow, $fee);
            $sheet->setCellValue('J' . $excelRow, '=SUM(H' . $excelRow . ':I' . $excelRow . ')');
            $this->set_text($sheet, 'K' . $excelRow, isset($statusLabels[$status]) ? $statusLabels[$status] : ucwords(str_replace('_', ' ', $status)));
            $this->set_text($sheet, 'L' . $excelRow, isset($row['creator_name']) ? $row['creator_name'] : '');
            $this->set_text($sheet, 'M' . $excelRow, isset($row['verifier_name']) ? $row['verifier_name'] : '');
            $this->set_text($sheet, 'N' . $excelRow, !empty($row['proof_path']) ? 'Ada' : '-');
            $this->set_text($sheet, 'O' . $excelRow, isset($row['note']) ? $row['note'] : '');
            $this->style_detail_row($sheet, $excelRow, 'O', $index);
        }

        $meta = $this->finish_detail($sheet, $meta, count($rows), 'O', array('H', 'I', 'J'), array('A'), array('C'), 'Belum ada pembayaran hutang.');
        $this->style_status_column($sheet, 'K', $meta, count($rows));
        $meta['row_count'] = count($rows);
        return $meta;
    }

    private function debt_payment_rows(array $data, array $debts)
    {
        $map = isset($data['paymentsByDebt']) && is_array($data['paymentsByDebt']) ? $data['paymentsByDebt'] : array();
        $rows = array();
        foreach ($debts as $debt) {
            $debtId = isset($debt['id']) ? (int) $debt['id'] : 0;
            $payments = array_key_exists($debtId, $map) && is_array($map[$debtId])
                ? $map[$debtId]
                : (isset($debt['payments']) && is_array($debt['payments']) ? $debt['payments'] : array());
            foreach ($payments as $payment) {
                $payment['debt_no'] = isset($payment['debt_no']) ? $payment['debt_no'] : (isset($debt['debt_no']) ? $debt['debt_no'] : '');
                $payment['creditor'] = isset($payment['creditor']) ? $payment['creditor'] : (isset($debt['creditor']) ? $debt['creditor'] : '');
                $rows[] = $payment;
            }
        }
        return $rows;
    }

    private function debt_status_label(array $row)
    {
        // The aggregate payment state is authoritative.  A cached status can
        // be stale after an interrupted import/replay, so exports must match
        // the same computed state used by the debt screen and PDF report.
        $status = isset($row['computed_status']) && in_array($row['computed_status'], array('open', 'paid', 'cancelled'), TRUE)
            ? (string) $row['computed_status']
            : (isset($row['status']) ? (string) $row['status'] : 'open');
        if ($status === 'cancelled') return 'Dibatalkan';
        if ($status === 'paid') return 'Lunas';
        $pendingCents = simp_money_cents(isset($row['pending_amount']) ? $row['pending_amount'] : '0');
        $paidCents = simp_money_cents(isset($row['paid_amount']) ? $row['paid_amount'] : '0');
        if ($pendingCents !== NULL && $pendingCents > 0) return 'Menunggu Verifikasi';
        if ($paidCents !== NULL && $paidCents > 0) return 'Bayar Sebagian';
        return 'Belum Dibayar';
    }

    private function date_only($value)
    {
        $value = trim((string) $value);
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $match) ? $match[1] : $value;
    }

    private function write_account_details(Worksheet $sheet, array $data)
    {
        $headers = array('No.', 'Nama Akun', 'Jenis', 'Bank / Penyedia', 'Nomor Rekening', 'Atas Nama', 'Saldo Awal', 'Mutasi Bersih', 'Saldo Akhir', 'Hitung dalam Total', 'Status', 'Urutan');
        $widths = array(7, 28, 25, 24, 23, 24, 19, 19, 19, 19, 15, 10);
        $accounts = isset($data['accounts']) && is_array($data['accounts']) ? $data['accounts'] : array();
        $meta = $this->prepare_detail($sheet, 'Rincian Saldo Kas & Rekening', $data, $headers, $widths, 'L');
        $typeLabels = array(
            'cash'=>'Tunai', 'bank'=>'Bank', 'qris'=>'QRIS',
            'personal'=>'Rekening Pribadi / Titipan'
        );

        foreach ($accounts as $index => $account) {
            $excelRow = $meta['first_data_row'] + $index;
            $type = isset($account['type']) ? $account['type'] : '';
            $this->set_number($sheet, 'A' . $excelRow, $index + 1);
            $this->set_text($sheet, 'B' . $excelRow, isset($account['name']) ? $account['name'] : '');
            $this->set_text($sheet, 'C' . $excelRow, isset($typeLabels[$type]) ? $typeLabels[$type] : ucwords(str_replace('_', ' ', $type)));
            $this->set_text($sheet, 'D' . $excelRow, isset($account['bank_name']) ? $account['bank_name'] : '');
            $this->set_text($sheet, 'E' . $excelRow, isset($account['account_number']) ? $account['account_number'] : '');
            $this->set_text($sheet, 'F' . $excelRow, isset($account['account_holder']) ? $account['account_holder'] : '');
            $this->set_number($sheet, 'G' . $excelRow, isset($account['opening_balance']) ? $account['opening_balance'] : 0);
            $this->set_number($sheet, 'I' . $excelRow, isset($account['balance']) ? $account['balance'] : 0);
            $sheet->setCellValue('H' . $excelRow, '=I' . $excelRow . '-G' . $excelRow);
            $this->set_text($sheet, 'J' . $excelRow, !empty($account['include_in_total']) ? 'Ya' : 'Tidak');
            $this->set_text($sheet, 'K' . $excelRow, !empty($account['is_active']) ? 'Aktif' : 'Nonaktif');
            $this->set_number($sheet, 'L' . $excelRow, isset($account['sort_order']) ? $account['sort_order'] : 0);
            $this->style_detail_row($sheet, $excelRow, 'L', $index);
        }

        $meta = $this->finish_detail($sheet, $meta, count($accounts), 'L', array('G', 'H', 'I'), array('A', 'L'), array(), 'Belum ada akun dana.');
        $this->style_status_column($sheet, 'J', $meta, count($accounts));
        $this->style_status_column($sheet, 'K', $meta, count($accounts));
        return $meta;
    }

    private function prepare_summary(Worksheet $sheet, $title, array $data, $mode)
    {
        $sheet->setShowGridlines(FALSE);
        $sheet->getTabColor()->setRGB(self::BLUE);
        $sheet->mergeCells('A1:F1');
        $this->set_text($sheet, 'A1', $title);
        $sheet->mergeCells('A2:F2');
        $this->set_text($sheet, 'A2', isset($data['organizationName']) ? $data['organizationName'] : 'Penyelenggara Pelatihan');
        $sheet->getStyle('A1:F1')->applyFromArray($this->title_style());
        $sheet->getRowDimension(1)->setRowHeight(31);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getStyle('A2:F2')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::MUTED));

        $hasReportScope = isset($data['reportScope']) && trim((string)$data['reportScope']) !== '';
        $this->set_text($sheet, 'A4', $hasReportScope ? 'Lingkup Data' : 'Lingkup Event');
        $sheet->mergeCells('B4:F4');
        $this->set_text($sheet, 'B4', $hasReportScope ? $data['reportScope'] : $this->event_scope($data));
        $this->set_text($sheet, 'A5', 'Mode Rincian');
        $sheet->mergeCells('B5:F5');
        $this->set_text($sheet, 'B5', $mode);
        $this->set_text($sheet, 'A6', 'Dibuat');
        $sheet->mergeCells('B6:F6');
        $this->set_datetime($sheet, 'B6', isset($data['generatedAt']) ? $data['generatedAt'] : date('Y-m-d H:i:s'));
        $sheet->getStyle('A4:A6')->getFont()->setBold(TRUE)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::MUTED));
        $sheet->getStyle('B4:F6')->getFont()->setBold(TRUE);
    }

    private function prepare_detail(Worksheet $sheet, $title, array $data, array $headers, array $widths, $lastColumn)
    {
        $sheet->setShowGridlines(FALSE);
        $sheet->getTabColor()->setRGB(self::BLUE);
        $sheet->mergeCells('A1:' . $lastColumn . '1');
        $this->set_text($sheet, 'A1', $title);
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($this->title_style());
        $sheet->getRowDimension(1)->setRowHeight(31);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->mergeCells('A2:' . $lastColumn . '2');
        $scope = isset($data['reportScope']) && trim((string)$data['reportScope']) !== ''
            ? $data['reportScope'] : $this->event_scope($data);
        $this->set_text($sheet, 'A2', 'Lingkup: ' . $scope);
        $sheet->mergeCells('A3:' . $lastColumn . '3');
        $this->set_text($sheet, 'A3', 'Dibuat: ' . $this->display_datetime(isset($data['generatedAt']) ? $data['generatedAt'] : date('Y-m-d H:i:s')));
        $sheet->getStyle('A2:' . $lastColumn . '3')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::MUTED));

        $headerRow = 6;
        foreach ($headers as $index => $header) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $this->set_text($sheet, $column . $headerRow, $header);
            $sheet->getColumnDimension($column)->setWidth($widths[$index]);
        }
        $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->applyFromArray($this->header_style());
        $sheet->getRowDimension($headerRow)->setRowHeight(31);
        $sheet->freezePane('A7');
        $sheet->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_FOLIO)
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.45)->setLeft(0.25);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, $headerRow);

        return array('header_row' => $headerRow, 'first_data_row' => 7, 'last_data_row' => 7, 'last_column' => $lastColumn);
    }

    private function finish_detail(Worksheet $sheet, array $meta, $rowCount, $lastColumn, array $moneyColumns, array $integerColumns, array $dateColumns = array(), $emptyMessage = 'Belum ada data pada event aktif.')
    {
        $first = (int) $meta['first_data_row'];
        $last = $rowCount > 0 ? $first + $rowCount - 1 : $first;
        $meta['last_data_row'] = $last;

        if ($rowCount === 0) {
            $sheet->mergeCells('A' . $first . ':' . $lastColumn . $first);
            $this->set_text($sheet, 'A' . $first, $emptyMessage);
            $sheet->getStyle('A' . $first . ':' . $lastColumn . $first)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . $first . ':' . $lastColumn . $first)->getFont()->setItalic(TRUE)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::MUTED));
            $sheet->getRowDimension($first)->setRowHeight(30);
            $sheet->setAutoFilter('A' . $meta['header_row'] . ':' . $lastColumn . $meta['header_row']);
        } else {
            $sheet->setAutoFilter('A' . $meta['header_row'] . ':' . $lastColumn . $last);
            $sheet->getStyle('A' . $first . ':' . $lastColumn . $last)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(TRUE);
            foreach ($moneyColumns as $column) {
                $sheet->getStyle($column . $first . ':' . $column . $last)->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
                $sheet->getStyle($column . $first . ':' . $column . $last)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            foreach ($integerColumns as $column) {
                $sheet->getStyle($column . $first . ':' . $column . $last)->getNumberFormat()->setFormatCode('#,##0');
            }
            foreach ($dateColumns as $column) {
                $sheet->getStyle($column . $first . ':' . $column . $last)->getNumberFormat()->setFormatCode('dd mmm yyyy');
            }
        }
        $sheet->getStyle('A' . $meta['header_row'] . ':' . $lastColumn . $last)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::BORDER);
        $sheet->getPageSetup()->setPrintArea('A1:' . $lastColumn . $last);
        $sheet->getHeaderFooter()->setOddFooter('&LDiekspor dari MVIN&C&F&RHalaman &P / &N');

        return $meta;
    }

    private function prepare_summary_pair_style(Worksheet $sheet, $labelCell, $valueCell)
    {
        $sheet->getStyle($labelCell)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::MUTED));
        $sheet->getStyle($valueCell)->getFont()->setBold(TRUE)->setSize(12)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::DARK));
        $sheet->getStyle($labelCell . ':' . $valueCell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LIGHT_ROW);
    }

    private function summary_pair(Worksheet $sheet, $labelCell, $label, $valueCell, $formula, $money)
    {
        $this->set_text($sheet, $labelCell, $label);
        $sheet->setCellValue($valueCell, $formula);
        if ($money) $sheet->getStyle($valueCell)->getNumberFormat()->setFormatCode(self::CURRENCY_FORMAT);
        else $sheet->getStyle($valueCell)->getNumberFormat()->setFormatCode('#,##0');
        $this->prepare_summary_pair_style($sheet, $labelCell, $valueCell);
    }

    private function section_title(Worksheet $sheet, $range, $title)
    {
        $sheet->mergeCells($range);
        $coordinate = explode(':', $range)[0];
        $this->set_text($sheet, $coordinate, $title);
        $sheet->getStyle($range)->applyFromArray(array(
            'fill' => array('fillType' => Fill::FILL_SOLID, 'startColor' => array('rgb' => self::LIGHT_BLUE)),
            'font' => array('bold' => TRUE, 'color' => array('rgb' => self::BLUE)),
            'alignment' => array('vertical' => Alignment::VERTICAL_CENTER)
        ));
    }

    private function write_event_list(Worksheet $sheet, array $data, $startRow)
    {
        $events = $this->events($data);
        $this->section_title($sheet, 'A' . $startRow . ':F' . $startRow, 'Event yang Disertakan');
        $headerRow = $startRow + 1;
        $this->set_text($sheet, 'A' . $headerRow, 'Kode');
        $sheet->mergeCells('B' . $headerRow . ':D' . $headerRow);
        $this->set_text($sheet, 'B' . $headerRow, 'Nama Event');
        $this->set_text($sheet, 'E' . $headerRow, 'Mulai');
        $this->set_text($sheet, 'F' . $headerRow, 'Selesai');
        $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->getFont()->setBold(TRUE)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::MUTED));
        $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDER);
        $row = $startRow + 2;
        if (!$events) {
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $this->set_text($sheet, 'A' . $row, 'Belum ada event aktif.');
            return;
        }
        foreach ($events as $index => $event) {
            $this->set_text($sheet, 'A' . $row, isset($event['code']) ? $event['code'] : '');
            $sheet->mergeCells('B' . $row . ':D' . $row);
            $this->set_text($sheet, 'B' . $row, isset($event['name']) ? $event['name'] : '');
            $this->set_date($sheet, 'E' . $row, isset($event['start_date']) ? $event['start_date'] : '');
            $this->set_date($sheet, 'F' . $row, isset($event['end_date']) ? $event['end_date'] : '');
            $sheet->getStyle('E' . $row . ':F' . $row)->getNumberFormat()->setFormatCode('dd mmm yyyy');
            if ($index % 2 === 1) $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LIGHT_ROW);
            $row++;
        }
    }

    private function finish_summary(Worksheet $sheet, $lastRow)
    {
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(4);
        $sheet->getColumnDimension('D')->setWidth(24);
        $sheet->getColumnDimension('E')->setWidth(24);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getStyle('A1:F' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_FOLIO)->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setFitToWidth(1)->setFitToHeight(1);
        $sheet->getPageMargins()->setTop(0.45)->setRight(0.4)->setBottom(0.5)->setLeft(0.4);
        $sheet->getPageSetup()->setPrintArea('A1:F' . $lastRow);
        $sheet->getHeaderFooter()->setOddFooter('&LDiekspor dari MVIN&C&F&RHalaman &P / &N');
        $sheet->freezePane('A8');
    }

    private function style_detail_row(Worksheet $sheet, $row, $lastColumn, $index)
    {
        $sheet->getStyle('A' . $row . ':' . $lastColumn . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::DARK));
        if ($index % 2 === 1) {
            $sheet->getStyle('A' . $row . ':' . $lastColumn . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LIGHT_ROW);
        }
        $sheet->getRowDimension($row)->setRowHeight(30);
    }

    private function style_status_column(Worksheet $sheet, $column, array $meta, $rowCount)
    {
        if ($rowCount < 1) return;
        $range = $column . $meta['first_data_row'] . ':' . $column . $meta['last_data_row'];
        $sheet->getStyle($range)->getFont()->setBold(TRUE)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::BLUE));
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LIGHT_BLUE);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(TRUE);
    }

    private function title_style()
    {
        return array(
            'fill' => array('fillType' => Fill::FILL_SOLID, 'startColor' => array('rgb' => self::BLUE)),
            'font' => array('bold' => TRUE, 'size' => 18, 'color' => array('rgb' => 'FFFFFF')),
            'alignment' => array('vertical' => Alignment::VERTICAL_CENTER)
        );
    }

    private function header_style()
    {
        return array(
            'fill' => array('fillType' => Fill::FILL_SOLID, 'startColor' => array('rgb' => self::BLUE)),
            'font' => array('bold' => TRUE, 'color' => array('rgb' => 'FFFFFF')),
            'alignment' => array('vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => TRUE),
            'borders' => array('bottom' => array('borderStyle' => Border::BORDER_MEDIUM, 'color' => array('rgb' => self::BLUE)))
        );
    }

    private function billing_label($mode)
    {
        $labels = array(
            'per_village' => 'Per Desa',
            'per_participant' => 'Per Peserta',
            'per_village_extra' => 'Paket Desa + Peserta Tambahan'
        );
        return isset($labels[$mode]) ? $labels[$mode] : ucwords(str_replace('_', ' ', (string) $mode));
    }

    /** Split one village's immutable tagihan into base and additional amounts. */
    private function registration_village_contract_amounts(array $row)
    {
        $totalCents = simp_money_cents(isset($row['expected_amount']) ? $row['expected_amount'] : '0');
        if ($totalCents === NULL || $totalCents < 0) $totalCents = 0;

        $villageCents = $totalCents;
        $additionalCents = 0;
        if (isset($row['billing_mode']) && $row['billing_mode'] === 'per_village_extra') {
            $configuredVillageCents = simp_money_cents(
                isset($row['event_village_fee']) ? $row['event_village_fee'] : NULL
            );
            if ($configuredVillageCents === NULL || $configuredVillageCents < 0) {
                // Old/imported events may not have a tariff snapshot. Keep the
                // full immutable total in the base column rather than silently
                // classifying it as an additional-participant charge.
                $villageCents = $totalCents;
            } else {
                $villageCents = min($totalCents, $configuredVillageCents);
                $additionalCents = max(0, $totalCents - $villageCents);
            }
        }

        return array(
            'village' => simp_money_from_cents($villageCents),
            'additional' => simp_money_from_cents($additionalCents),
            'total' => simp_money_from_cents($totalCents)
        );
    }

    /** Build a deterministic sequence within each regency and event year. */
    private function registration_mou_number(array $row, array &$sequences)
    {
        $dateValue = isset($row['event_start_date']) ? (string) $row['event_start_date'] : '';
        $date = DateTime::createFromFormat('!Y-m-d', substr($dateValue, 0, 10));
        if (!$date || $date->format('Y-m-d') !== substr($dateValue, 0, 10)) {
            $createdAt = isset($row['created_at']) ? substr((string) $row['created_at'], 0, 10) : '';
            $date = DateTime::createFromFormat('!Y-m-d', $createdAt);
        }
        if (!$date) $date = new DateTime('today');

        $regencyCode = trim((string) (isset($row['regency_code']) ? $row['regency_code'] : ''));
        if ($regencyCode === '') $regencyCode = trim((string) (isset($row['regency_id']) ? $row['regency_id'] : ''));
        if ($regencyCode === '') {
            $regencyCode = preg_replace('/^(KABUPATEN|KOTA)\s+/iu', '', trim((string) (isset($row['regency_name']) ? $row['regency_name'] : '')));
        }
        $regencyCode = preg_replace('/[^A-Z0-9._-]+/u', '-', strtoupper($regencyCode));
        $regencyCode = trim((string) $regencyCode, '-');
        if ($regencyCode === '') $regencyCode = 'KAB';

        $year = $date->format('Y');
        $sequenceKey = $regencyCode . '|' . $year;
        $sequences[$sequenceKey] = isset($sequences[$sequenceKey]) ? $sequences[$sequenceKey] + 1 : 1;

        return sprintf(
            '%03d.RAB/%s/SPK/%s/%s',
            $sequences[$sequenceKey],
            $regencyCode,
            $this->roman_month((int) $date->format('n')),
            $year
        );
    }

    private function roman_month($month)
    {
        $months = array(1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII');
        return isset($months[(int) $month]) ? $months[(int) $month] : 'I';
    }

    private function rupiah_in_words($value)
    {
        $cents = simp_money_cents($value);
        if ($cents === NULL) $cents = 0;
        $negative = $cents < 0;
        $cents = abs($cents);
        $rupiah = intdiv($cents, 100);
        $sen = $cents % 100;

        $words = $this->indonesian_number_words($rupiah) . ' rupiah';
        if ($sen > 0) $words .= ' ' . $this->indonesian_number_words($sen) . ' sen';
        if ($negative) $words = 'minus ' . $words;

        return function_exists('mb_strtoupper') ? mb_strtoupper($words, 'UTF-8') : strtoupper($words);
    }

    private function indonesian_number_words($number)
    {
        $number = (int) $number;
        $units = array('nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas');
        if ($number < 0) return 'minus ' . $this->indonesian_number_words(abs($number));
        if ($number < 12) return $units[$number];
        if ($number < 20) return $this->indonesian_number_words($number - 10) . ' belas';
        if ($number < 100) {
            $words = $this->indonesian_number_words(intdiv($number, 10)) . ' puluh';
            return $number % 10 ? $words . ' ' . $this->indonesian_number_words($number % 10) : $words;
        }
        if ($number < 200) {
            return $number === 100 ? 'seratus' : 'seratus ' . $this->indonesian_number_words($number - 100);
        }
        if ($number < 1000) {
            $words = $this->indonesian_number_words(intdiv($number, 100)) . ' ratus';
            return $number % 100 ? $words . ' ' . $this->indonesian_number_words($number % 100) : $words;
        }
        if ($number < 2000) {
            return $number === 1000 ? 'seribu' : 'seribu ' . $this->indonesian_number_words($number - 1000);
        }

        $scales = array(
            1000000000000000 => 'kuadriliun',
            1000000000000 => 'triliun',
            1000000000 => 'miliar',
            1000000 => 'juta',
            1000 => 'ribu'
        );
        foreach ($scales as $divisor => $label) {
            if ($number < $divisor) continue;
            $words = $this->indonesian_number_words(intdiv($number, $divisor)) . ' ' . $label;
            $remainder = $number % $divisor;
            return $remainder ? $words . ' ' . $this->indonesian_number_words($remainder) : $words;
        }

        return 'nol';
    }

    private function event_scope(array $data)
    {
        $events = $this->events($data);
        if (!$events) return 'Belum ada event aktif';
        if (count($events) === 1) {
            return (isset($events[0]['name']) ? $events[0]['name'] : '') . ' (' . (isset($events[0]['code']) ? $events[0]['code'] : '') . ')';
        }
        return number_format(count($events)) . ' event aktif';
    }

    private function events(array $data)
    {
        return isset($data['activeEvents']) && is_array($data['activeEvents']) ? $data['activeEvents'] : array();
    }

    private function set_text(Worksheet $sheet, $coordinate, $value)
    {
        $sheet->setCellValueExplicit($coordinate, $value === NULL ? '' : (string) $value, DataType::TYPE_STRING);
    }

    private function set_number(Worksheet $sheet, $coordinate, $value)
    {
        $raw = is_scalar($value) ? trim((string)$value) : '';
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $raw)) {
            $sheet->setCellValueExplicit($coordinate, 0, DataType::TYPE_NUMERIC);
            return;
        }

        // Excel stores at most 15 significant decimal digits. Keep larger
        // DECIMAL(18,2) values as exact text instead of silently rounding
        // their final digits through a PHP/Excel binary float.
        $unsigned = ltrim($raw, '-');
        $digits = ltrim(str_replace('.', '', $unsigned), '0');
        $significantDigits = $digits === '' ? 1 : strlen($digits);
        if ($significantDigits > 15) {
            $sheet->setCellValueExplicit($coordinate, $raw, DataType::TYPE_STRING);
            $sheet->getStyle($coordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            return;
        }

        // Passing the canonical decimal string lets PhpSpreadsheet perform
        // the numeric conversion only inside Excel's safe precision range.
        $sheet->setCellValueExplicit($coordinate, $raw, DataType::TYPE_NUMERIC);
    }

    private function set_date(Worksheet $sheet, $coordinate, $value)
    {
        $date = DateTime::createFromFormat('!Y-m-d', (string) $value);
        if (!$date || $date->format('Y-m-d') !== (string) $value) {
            $this->set_text($sheet, $coordinate, $value);
            return;
        }
        $sheet->setCellValueExplicit($coordinate, ExcelDate::PHPToExcel($date), DataType::TYPE_NUMERIC);
        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('dd mmm yyyy');
    }

    private function set_datetime(Worksheet $sheet, $coordinate, $value)
    {
        $date = DateTime::createFromFormat('!Y-m-d H:i:s', (string) $value);
        if (!$date) {
            $this->set_text($sheet, $coordinate, $value);
            return;
        }
        $sheet->setCellValueExplicit($coordinate, ExcelDate::PHPToExcel($date), DataType::TYPE_NUMERIC);
        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('dd mmm yyyy hh:mm');
    }

    private function display_datetime($value)
    {
        $date = DateTime::createFromFormat('!Y-m-d H:i:s', (string) $value);
        return $date ? $date->format('d-m-Y H:i') : (string) $value;
    }

    private function save_to_string(Spreadsheet $spreadsheet)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'simp-xlsx-');
        if ($tempFile === FALSE) throw new RuntimeException('Folder kerja Excel tidak dapat dibuat.');
        try {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(TRUE);
            $writer->save($tempFile);
            $content = file_get_contents($tempFile);
            if ($content === FALSE || $content === '') throw new RuntimeException('File Excel kosong atau gagal dibaca.');
            return $content;
        } finally {
            if (is_file($tempFile)) @unlink($tempFile);
        }
    }

    private function assert_available()
    {
        if (!class_exists(Spreadsheet::class)) {
            throw new RuntimeException('Mesin Excel belum terpasang. Jalankan composer install pada folder aplikasi.');
        }
    }
}
