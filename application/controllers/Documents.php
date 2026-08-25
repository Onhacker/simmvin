<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Documents extends App_Controller
{
    public function payment($id)
    {
        $this->require_permission('registrations.view');
        $row = $this->db->select('proof_path')->where('id', (int) $id)->get('payments')->row_array();
        $this->serve(isset($row['proof_path']) ? $row['proof_path'] : NULL);
    }

    public function expense($id)
    {
        $this->require_permission('expenses.view');
        $row = $this->db->select('proof_path,debt_id')->where('id', (int) $id)->get('expenses')->row_array();
        // Debt-payment evidence is also a debt resource.  Return a generic
        // 404 when the caller lacks debt visibility so the endpoint cannot
        // be used to discover whether an expense is tied to a debt.
        $debtId = isset($row['debt_id']) ? (int) $row['debt_id'] : 0;
        if ($debtId > 0 && !$this->Auth_model->can('debts.view')) show_404();
        $this->serve(isset($row['proof_path']) ? $row['proof_path'] : NULL);
    }

    public function debt($id)
    {
        $this->require_permission('debts.view');
        $row = $this->db->select('proof_path')->where('id', (int) $id)
            ->where('debt_id IS NOT NULL', NULL, FALSE)->get('expenses')->row_array();
        $this->serve(isset($row['proof_path']) ? $row['proof_path'] : NULL);
    }

    public function transfer($id)
    {
        $this->require_permission('transfers.view');
        $row = $this->db->select('proof_path')->where('id', (int) $id)->get('fund_transfers')->row_array();
        $this->serve(isset($row['proof_path']) ? $row['proof_path'] : NULL);
    }

    private function serve($relativePath)
    {
        if (!$relativePath || strpos($relativePath, 'uploads/') !== 0) show_404();
        $uploadsRoot = realpath(FCPATH . 'uploads');
        $filePath = realpath(FCPATH . $relativePath);
        if (!$uploadsRoot || !$filePath || !is_file($filePath) || strpos($filePath, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0) show_404();

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $types = array('jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf');
        if (!isset($types[$extension])) show_404();

        $this->output
            ->set_content_type($types[$extension])
            ->set_header('Content-Disposition: inline; filename="bukti-transaksi.' . $extension . '"')
            ->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output(file_get_contents($filePath));
    }
}
