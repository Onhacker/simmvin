<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Audit extends App_Controller
{
    public function index()
    {
        $this->require_permission('audit.view');
        $actionRaw = $this->input->get('action', TRUE);
        $action = is_scalar($actionRaw) ? trim((string) $actionRaw) : '';
        if ($actionRaw !== NULL && !is_scalar($actionRaw)) {
            $this->session->set_flashdata('error', 'Filter tindakan tidak valid.');
        }
        try {
            $dateFrom = $this->strict_date_filter($this->input->get('date_from', TRUE), 'Dari tanggal');
            $dateTo = $this->strict_date_filter($this->input->get('date_to', TRUE), 'Sampai tanggal');
            if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
                throw new InvalidArgumentException('Dari tanggal tidak boleh setelah sampai tanggal.');
            }
        } catch (InvalidArgumentException $e) {
            // An invalid filter must not be passed into a datetime comparison
            // as an arbitrary SQL fragment.  Show the alert and fall back to
            // an unfiltered audit list, matching the other report screens.
            $this->session->set_flashdata('error', $e->getMessage());
            $dateFrom = '';
            $dateTo = '';
        }

        // Keep the audit trail lightweight on mobile: only ten rows are
        // loaded for each page.  The total is calculated separately so the
        // pager remains accurate even when the log table contains thousands
        // of entries.
        $perPage = 10;
        $pageRaw = $this->input->get('page', TRUE);
        $page = is_scalar($pageRaw) && ctype_digit((string) $pageRaw)
            ? max(1, (int) $pageRaw)
            : 1;

        $this->apply_log_filters($action, $dateFrom, $dateTo);
        $countRow = $this->db->select('COUNT(*) AS total', FALSE)->get()->row_array();
        $totalRows = isset($countRow['total']) ? (int) $countRow['total'] : 0;
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages) $page = $totalPages;

        $this->apply_log_filters($action, $dateFrom, $dateTo);
        $logs = $this->db->select('a.*,u.name AS user_name,u.username')
            ->order_by('a.created_at', 'DESC')
            ->order_by('a.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->result_array();

        $viewData = array(
            'pageTitle' => 'Audit Trail',
            'logs' => $logs,
            'filters' => array('action' => $action, 'date_from' => $dateFrom, 'date_to' => $dateTo),
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'currentPage' => $page,
            'pageScript' => 'audit.js'
        );

        // Pagination requests replace only the activity fragment. Returning
        // JSON keeps the shell, filters and open menus untouched while still
        // allowing normal links to work when JavaScript is unavailable.
        if ($this->input->is_ajax_request()) {
            return $this->json(array(
                'success' => TRUE,
                'html' => $this->load->view('audit/index', array_merge($viewData, array('ajaxPartial' => TRUE)), TRUE),
                'page' => $page,
                'total_pages' => $totalPages,
                'total_rows' => $totalRows
            ));
        }

        $this->render('audit/index', $viewData);
    }

    /** Apply the shared audit filters to the current Query Builder instance. */
    private function apply_log_filters($action, $dateFrom, $dateTo)
    {
        $this->db->from('audit_logs a')->join('users u', 'u.id=a.user_id', 'left');
        if ($action !== '') $this->db->like('a.action', $action);
        if ($dateFrom !== '') $this->db->where('a.created_at >=', $dateFrom . ' 00:00:00');
        if ($dateTo !== '') $this->db->where('a.created_at <=', $dateTo . ' 23:59:59');
    }

    private function strict_date_filter($value, $label)
    {
        if ($value === NULL || $value === '') return '';
        if (!is_scalar($value)) throw new InvalidArgumentException($label . ' tidak valid.');
        $value = trim((string) $value);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($label . ' tidak valid.');
        }
        return $value;
    }
}
