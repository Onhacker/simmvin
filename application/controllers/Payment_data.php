<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment_data extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_model', 'finance');
    }

    public function index()
    {
        $this->require_permission('reports.income');
        try {
            $data = $this->report_data();
        } catch (InvalidArgumentException $e) {
            if ($this->wants_json()) {
                return $this->json(array('success'=>FALSE, 'message'=>$e->getMessage()), 422);
            }
            $this->session->set_flashdata('error', $e->getMessage());
            redirect('data-bayar');
            return;
        }

        if ($this->wants_json()) {
            $suffix = $data['filterQuery'] !== '' ? '?' . $data['filterQuery'] : '';
            return $this->json(array(
                'success' => TRUE,
                'html' => $this->load->view('payment_data/_results', array(
                    'report' => $data['report'],
                    'filterScope' => $data['filterScope']
                ), TRUE),
                'filter_query' => $data['filterQuery'],
                'filter_active' => !empty($data['filterActive']),
                'has_rows' => !empty($data['report']['rows']),
                'row_count' => count($data['report']['rows']),
                'filters' => array(
                    'district_id' => (string) $data['filters']['district_id'],
                    'village_id' => (string) $data['filters']['village_id']
                ),
                'preview_url' => site_url('data-bayar/cetak') . $suffix,
                'pdf_url' => site_url('data-bayar/pdf') . $suffix
            ));
        }

        $data['pageTitle'] = 'Data Bayar';
        $data['pageScripts'] = array('payment-data.js', 'finance.js');
        $this->render('payment_data/index', $data);
    }

    public function print_preview()
    {
        $this->require_permission('reports.income');
        try {
            $data = $this->report_data();
        } catch (InvalidArgumentException $e) {
            show_error($e->getMessage(), 422, 'Filter Tidak Valid');
            return;
        }

        $data['documentTitle'] = 'Data Bayar';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = FALSE;
        $html = $this->load->view('reports/payment_data', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    public function pdf()
    {
        $this->require_permission('reports.income');
        try {
            $data = $this->report_data();
        } catch (InvalidArgumentException $e) {
            show_error($e->getMessage(), 422, 'Filter Tidak Valid');
            return;
        }

        $data['documentTitle'] = 'Data Bayar';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = TRUE;
        $html = $this->load->view('reports/payment_data', $data, TRUE);

        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4_landscape($html);
            return $this->private_document_output(
                'application/pdf',
                $pdf,
                'attachment; filename="data-bayar-' . date('Ymd-His') . '.pdf"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF data bayar: ' . $e->getMessage());
            show_error('PDF data bayar belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    private function report_data()
    {
        $activeEvents = $this->finance->active_events();
        $eventIds = array_map(function ($event) { return (int) $event['id']; }, $activeEvents);
        $options = $this->finance->payment_data_filter_options($eventIds);
        $districtId = $this->strict_filter_id($this->input->get('district_id', TRUE), 'Kecamatan');
        $villageId = $this->strict_filter_id($this->input->get('village_id', TRUE), 'Desa');

        $districtMap = array();
        foreach ($options['districts'] as $district) $districtMap[(string) $district['id']] = $district;
        $villageMap = array();
        foreach ($options['villages'] as $village) $villageMap[(string) $village['id']] = $village;

        if ($districtId !== '' && !isset($districtMap[$districtId])) {
            throw new InvalidArgumentException('Kecamatan tidak tersedia pada registrasi event aktif.');
        }
        if ($villageId !== '' && !isset($villageMap[$villageId])) {
            throw new InvalidArgumentException('Desa tidak tersedia pada registrasi event aktif.');
        }
        if ($villageId !== '') {
            $villageDistrictId = (string) $villageMap[$villageId]['district_id'];
            if ($districtId === '') $districtId = $villageDistrictId;
            if ($districtId !== $villageDistrictId) {
                throw new InvalidArgumentException('Desa tidak berada pada kecamatan yang dipilih.');
            }
        }

        $filters = array(
            'event_ids' => $eventIds,
            'district_id' => $districtId,
            'village_id' => $villageId
        );
        $queryFilters = array();
        if ($districtId !== '') $queryFilters['district_id'] = $districtId;
        if ($villageId !== '') $queryFilters['village_id'] = $villageId;

        $scopeParts = array();
        if ($villageId !== '') $scopeParts[] = 'Desa ' . $villageMap[$villageId]['name'];
        if ($districtId !== '') $scopeParts[] = 'Kecamatan ' . $districtMap[$districtId]['name'];
        $filterScope = $scopeParts ? implode(' · ', $scopeParts) : 'Semua Kecamatan dan Desa';

        $organizationName = $this->finance->setting_value('organization_name', '');
        if ($organizationName === '' || strcasecmp($organizationName, 'Penyelenggara Pelatihan') === 0) {
            $organizationName = 'MEDIAVERSE INOVASI NUSANTARA';
        }

        $report = $this->finance->payment_data_report($filters);
        $reportEvents = $activeEvents;
        if ($queryFilters) {
            $rowEventIds = array();
            foreach ($report['rows'] as $row) $rowEventIds[(int) $row['event_id']] = TRUE;
            $reportEvents = array_values(array_filter($activeEvents, function ($event) use ($rowEventIds) {
                return isset($rowEventIds[(int) $event['id']]);
            }));
        }

        return array(
            'report' => $report,
            'filters' => $filters,
            'queryFilters' => $queryFilters,
            'filterQuery' => http_build_query($queryFilters),
            'filterActive' => !empty($queryFilters),
            'filterScope' => $filterScope,
            'filterOptions' => $options,
            'activeEvents' => $activeEvents,
            'reportEvents' => $reportEvents,
            'organizationName' => $organizationName
        );
    }

    private function strict_filter_id($value, $label)
    {
        if ($value === NULL || $value === '') return '';
        if (!is_scalar($value)) throw new InvalidArgumentException($label . ' tidak valid.');
        $value = trim((string) $value);
        if ($value === '') return '';
        if (strlen($value) > 30 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException($label . ' tidak valid.');
        }
        return $value;
    }

    private function wants_json()
    {
        $accept = strtolower((string) $this->input->get_request_header('Accept'));
        return $this->input->is_ajax_request() || strpos($accept, 'application/json') !== FALSE;
    }

    private function private_document_output($contentType, $body, $disposition = NULL)
    {
        $binary = $contentType === 'application/pdf';
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
