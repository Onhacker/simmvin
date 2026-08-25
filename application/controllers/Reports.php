<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reports extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_model', 'finance');
    }

    public function income()
    {
        $this->require_permission('reports.income');
        $data = $this->income_report_data();
        $data['pageTitle'] = 'Laporan Pemasukan';
        $data['pageScript'] = 'finance.js';
        $this->render('reports/income', $data);
    }

    public function income_print()
    {
        $this->require_permission('reports.income');
        $data = $this->income_report_data();
        $data['reportKind'] = 'income';
        $data['documentTitle'] = 'Laporan Pemasukan';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = FALSE;
        $html = $this->load->view('reports/print_document', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    public function income_pdf()
    {
        $this->require_permission('reports.income');
        $data = $this->income_report_data();
        $data['reportKind'] = 'income';
        $data['documentTitle'] = 'Laporan Pemasukan';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = TRUE;
        $html = $this->load->view('reports/print_document', $data, TRUE);

        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4($html);
            $mode = $data['report']['view'] === 'participant' ? 'peserta' : 'desa';
            return $this->private_document_output(
                'application/pdf',
                $pdf,
                'attachment; filename="laporan-pemasukan-' . $mode . '-' . date('Ymd-His') . '.pdf"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF pemasukan: ' . $e->getMessage());
            show_error('PDF pemasukan belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    public function income_excel()
    {
        $this->require_permission('reports.income');
        $data = $this->income_report_data();
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $villageReport = $data['report']['view'] === 'participant'
            ? $this->finance->income_report(array('event_ids' => $data['filters']['event_ids'], 'view' => 'village'))
            : $data['report'];

        try {
            $this->load->library('Excel_renderer');
            $excel = $this->excel_renderer->render_income($data, $villageReport);
            $mode = $data['report']['view'] === 'participant' ? 'peserta' : 'desa';
            return $this->private_document_output(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $excel,
                'attachment; filename="laporan-pemasukan-' . $mode . '-' . date('Ymd-His') . '.xlsx"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat Excel pemasukan: ' . $e->getMessage());
            show_error('Excel pemasukan belum dapat dibuat. Silakan coba kembali.', 500, 'Excel Gagal Dibuat');
        }
    }

    public function finance()
    {
        $this->require_permission('reports.finance');
        try {
            $filters = $this->date_filters();
        } catch (InvalidArgumentException $e) {
            $this->session->set_flashdata('error', $e->getMessage());
            $filters = array('date_from'=>'','date_to'=>'');
        }
        $this->render('reports/finance',array('pageTitle'=>'Laporan Keuangan','report'=>$this->finance->finance_report($filters),'filters'=>$filters,'pageScript'=>'finance.js'));
    }

    private function income_report_data()
    {
        $activeEvents = $this->finance->active_events();
        $filters = array(
            'event_ids' => array_map(function ($event) { return (int) $event['id']; }, $activeEvents),
            'view' => $this->input->get('view', TRUE) === 'participant' ? 'participant' : 'village'
        );
        return array(
            'report' => $this->finance->income_report($filters),
            'filters' => $filters,
            'activeEvents' => $activeEvents,
            'organizationName' => $this->finance->setting_value('organization_name', 'Penyelenggara Pelatihan')
        );
    }

    private function date_filters()
    {
        $from = $this->strict_date_filter($this->input->get('date_from', TRUE), 'Dari tanggal');
        $to = $this->strict_date_filter($this->input->get('date_to', TRUE), 'Sampai tanggal');
        if ($from !== '' && $to !== '' && $from > $to) throw new InvalidArgumentException('Dari tanggal tidak boleh setelah sampai tanggal.');
        return array('date_from'=>$from,'date_to'=>$to);
    }

    private function strict_date_filter($value, $label)
    {
        if ($value === NULL || $value === '') return '';
        if (!is_scalar($value)) throw new InvalidArgumentException($label.' tidak valid.');
        $value = trim((string)$value);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException($label.' tidak valid.');
        return $value;
    }

    private function private_document_output($contentType, $body, $disposition = NULL)
    {
        $binary = in_array($contentType, array('application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), TRUE);
        if ($binary) $this->output->set_header('Content-Type: ' . $contentType);
        else $this->output->set_content_type($contentType, 'UTF-8');
        $this->output
            ->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_header('X-Content-Type-Options: nosniff');
        if ($disposition !== NULL) $this->output->set_header('Content-Disposition: ' . $disposition);
        if ($binary) $this->output->set_header('Content-Length: ' . strlen($body));
        return $this->output->set_output($body);
    }
}
