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

        $this->db->select('a.*,u.name AS user_name,u.username')
            ->from('audit_logs a')->join('users u', 'u.id=a.user_id', 'left');
        if ($action !== '') $this->db->like('a.action', $action);
        if ($dateFrom !== '') $this->db->where('a.created_at >=', $dateFrom . ' 00:00:00');
        if ($dateTo !== '') $this->db->where('a.created_at <=', $dateTo . ' 23:59:59');
        $logs = $this->db->order_by('a.created_at', 'DESC')->limit(500)->get()->result_array();

        $this->render('audit/index', array(
            'pageTitle' => 'Audit Trail',
            'logs' => $logs,
            'filters' => array('action' => $action, 'date_from' => $dateFrom, 'date_to' => $dateTo)
        ));
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
