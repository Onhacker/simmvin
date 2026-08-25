<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Debts extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Debt_model', 'debt');
        $this->load->model('Finance_model', 'finance');
    }

    public function index()
    {
        $this->require_permission('debts.view');
        $filters = array(
            'status' => $this->input->get('status', TRUE),
            'search' => $this->input->get('search', TRUE),
            'event_id' => $this->input->get('event_id', TRUE)
        );
        $debts = $this->debt->debts($filters);
        $ids = array(); foreach ($debts as $row) $ids[] = (int)$row['id'];
        $this->render('debts/index', array(
            'pageTitle' => 'Hutang Perusahaan',
            'debts' => $debts,
            'summary' => $this->debt->summary($filters),
            'categories' => $this->debt->categories(TRUE),
            'events' => $this->debt->events(),
            // Debt repayments follow the cash-drawer policy.  Keep the
            // backend payload restricted as well as the modal UI; a forged
            // account_id is still rejected inside Debt_model.
            'accounts' => $this->cash_accounts(),
            'paymentsByDebt' => $this->debt->payments_by_debt($ids),
            'filters' => $filters,
            'canCreate' => $this->Auth_model->can('debts.create'),
            'canPay' => $this->Auth_model->can('debts.pay'),
            'canVerify' => $this->Auth_model->can('debts.verify'),
            'canManage' => $this->Auth_model->can('debts.manage'),
            'pageScripts' => array('debt.js', 'finance.js')
        ));
    }

    public function print_preview()
    {
        $this->require_permission('debts.view');
        $data = $this->debt_report_data();
        $data['reportKind'] = 'debt';
        $data['documentTitle'] = 'Laporan Hutang Perusahaan';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = FALSE;
        $html = $this->load->view('reports/print_document', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    public function pdf()
    {
        $this->require_permission('debts.view');
        $data = $this->debt_report_data();
        $data['reportKind'] = 'debt';
        $data['documentTitle'] = 'Laporan Hutang Perusahaan';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = TRUE;
        $html = $this->load->view('reports/print_document', $data, TRUE);

        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4($html);
            return $this->private_document_output(
                'application/pdf',
                $pdf,
                'attachment; filename="laporan-hutang-perusahaan-' . date('Ymd-His') . '.pdf"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF hutang: ' . $e->getMessage());
            show_error('PDF hutang belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    public function excel()
    {
        $this->require_permission('debts.view');
        $data = $this->debt_report_data();

        try {
            $this->load->library('Excel_renderer');
            $excel = $this->excel_renderer->render_debts($data);
            return $this->private_document_output(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $excel,
                'attachment; filename="laporan-hutang-perusahaan-' . date('Ymd-His') . '.xlsx"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat Excel hutang: ' . $e->getMessage());
            show_error('Excel hutang belum dapat dibuat. Silakan coba kembali.', 500, 'Excel Gagal Dibuat');
        }
    }

    public function create()
    {
        $this->require_permission('debts.create');
        if ($this->input->method(TRUE) === 'POST') return $this->create_ajax();
        // The primary workflow is the AppKit modal on /hutang. Keep the
        // legacy URL safe even when an installation has no standalone form.
        if (!is_file(APPPATH.'views/debts/form.php')) return redirect('hutang');
        $this->render('debts/form', array(
            'pageTitle' => 'Tambah Hutang', 'categories' => $this->debt->categories(TRUE),
            'events' => $this->debt->events(), 'pageScript' => 'debt.js'
        ));
    }

    public function create_ajax()
    {
        $this->require_permission('debts.create');
        $this->require_post();
        try {
            $data = array(
                'creditor' => $this->scalar_post('creditor', 'Nama kreditur wajib diisi.'),
                'description' => $this->scalar_post('description', 'Uraian hutang wajib diisi.'),
                'category_id' => $this->optional_scalar_post('category_id'),
                'event_id' => $this->optional_scalar_post('event_id'),
                'debt_date' => $this->scalar_post('debt_date', 'Tanggal hutang wajib diisi.'),
                'principal_amount' => $this->scalar_post('principal_amount', 'Nominal hutang wajib diisi.'),
                'note' => $this->optional_scalar_post('note')
            );
            $id = $this->debt->create_debt($data, $this->currentUser['id']);
            try { $this->Audit_model->log('debt_created','company_debt',$id,$data); } catch (Throwable $ignored) {}
            return $this->respond_success('Hutang berhasil dicatat.', array('debt_id'=>(int)$id));
        } catch (InvalidArgumentException $e) {
            return $this->respond_error($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message('error', 'Hutang gagal dibuat: '.$e->getMessage());
            return $this->respond_error('Hutang belum dapat disimpan. Silakan coba kembali.', 500);
        }
    }

    public function update_ajax($id)
    {
        $this->require_permission('debts.manage');
        $this->require_post();
        try {
            $data = array(
                'creditor' => $this->scalar_post('creditor', 'Nama kreditur wajib diisi.'),
                'description' => $this->scalar_post('description', 'Uraian hutang wajib diisi.'),
                'category_id' => $this->optional_scalar_post('category_id'),
                'event_id' => $this->optional_scalar_post('event_id'),
                'debt_date' => $this->scalar_post('debt_date', 'Tanggal hutang wajib diisi.'),
                'principal_amount' => $this->scalar_post('principal_amount', 'Nominal hutang wajib diisi.'),
                'note' => $this->optional_scalar_post('note')
            );
            $this->debt->update_debt((int)$id, $data, $this->currentUser['id']);
            try { $this->Audit_model->log('debt_updated','company_debt',(int)$id,$data); } catch (Throwable $ignored) {}
            return $this->respond_success('Hutang berhasil diperbarui.', array('debt_id'=>(int)$id));
        } catch (InvalidArgumentException $e) {
            return $this->respond_error($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message('error', 'Hutang #'.(int)$id.' gagal diperbarui: '.$e->getMessage());
            return $this->respond_error('Hutang belum dapat diperbarui. Silakan coba kembali.', 500);
        }
    }

    public function pay($id)
    {
        $this->require_permission('debts.pay');
        $debt = $this->debt->debt((int)$id, TRUE);
        if (!$debt) show_404();
        if ($this->input->method(TRUE) === 'POST') return $this->pay_ajax($id);
        if (!is_file(APPPATH.'views/debts/payment.php')) return redirect('hutang');
        $this->render('debts/payment', array(
            'pageTitle' => 'Bayar Hutang', 'debt' => $debt,
            'accounts' => $this->cash_accounts(),
            'canVerify' => $this->Auth_model->can('debts.verify'), 'pageScript' => 'debt.js'
        ));
    }

    public function pay_ajax($id)
    {
        $this->require_permission('debts.pay');
        $this->require_post();
        $proof = NULL; $expenseId = NULL;
        $originalDbDebug = $this->db->db_debug; $this->db->db_debug = FALSE;
        try {
            $method = $this->scalar_post('method', 'Metode pembayaran tidak valid.');
            if ($method !== 'cash') throw new InvalidArgumentException('Pembayaran hutang hanya dapat menggunakan metode Tunai.');
            $date = $this->scalar_post('expense_date', 'Tanggal pembayaran wajib diisi.');
            $parsed = DateTime::createFromFormat('Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Tanggal pembayaran tidak valid.');
            $accountId = $this->positive_id($this->optional_scalar_post('account_id'), 'Akun dana tidak valid.');
            $amount = $this->money_post('amount', TRUE, 'Nominal pembayaran tidak valid.');
            $fee = $this->money_post('admin_fee', FALSE, 'Biaya admin tidak valid.');
            $requestedStatus = $this->optional_scalar_post('status');
            if ($requestedStatus === NULL || $requestedStatus === '') $requestedStatus = 'pending';
            if (!in_array($requestedStatus, array('pending','verified'), TRUE)) throw new InvalidArgumentException('Status pembayaran tidak valid.');
            $status = ($requestedStatus === 'verified' && $this->Auth_model->can('debts.verify')) ? 'verified' : 'pending';
            $note = $this->optional_scalar_post('note');
            $description = $this->optional_scalar_post('description');
            if ($note !== NULL && strlen(trim($note)) > 2000) throw new InvalidArgumentException('Catatan maksimal 2.000 karakter.');
            if ($description !== NULL && strlen(trim($description)) > 3000) throw new InvalidArgumentException('Uraian maksimal 3.000 karakter.');

            $folder = 'debts'; $uploadPath = FCPATH.'uploads/'.$folder;
            if (!is_dir($uploadPath) && !mkdir($uploadPath,0755,TRUE) && !is_dir($uploadPath)) throw new RuntimeException('Folder bukti hutang tidak dapat dibuat.');
            try { $proof = $this->upload_document('proof',$folder,$method !== 'cash'); }
            catch (RuntimeException $uploadError) { throw $this->safe_upload_exception($uploadError); }
            $payment = $this->debt->create_payment((int)$id, array(
                'payment_date'=>$date, 'method'=>$method, 'account_id'=>$accountId,
                'amount'=>$amount, 'admin_fee'=>$fee, 'proof_path'=>$proof,
                'status'=>$status, 'note'=>$note, 'description'=>$description
            ), $this->currentUser['id']);
            $expenseId = $payment && !empty($payment['expense_id']) ? (int)$payment['expense_id'] : NULL;
            if (!$expenseId) throw new RuntimeException('Pembayaran hutang gagal disimpan.');
            try { $this->Audit_model->log('debt_payment_created','expense',$expenseId,array('debt_id'=>(int)$id,'status'=>$status,'amount'=>$amount)); } catch (Throwable $ignored) {}
            return $this->respond_success('Pembayaran hutang berhasil dicatat'.($status==='pending'?' dan menunggu verifikasi.':'.'), array('expense_id'=>$expenseId,'debt_id'=>(int)$id,'status'=>$status));
        } catch (InvalidArgumentException $e) {
            if (!$expenseId && $proof) $this->cleanup_upload($proof);
            return $this->respond_error($e->getMessage(), 422);
        } catch (Throwable $e) {
            if (!$expenseId && $proof) $this->cleanup_upload($proof);
            log_message('error', 'Pembayaran hutang gagal: '.$e->getMessage());
            return $this->respond_error('Pembayaran hutang belum dapat disimpan. Silakan coba kembali.', 500);
        } finally { $this->db->db_debug = $originalDbDebug; }
    }

    public function payment_status($expenseId)
    {
        $this->require_permission('debts.verify');
        $this->require_post();
        try {
            $payment = $this->debt->payment((int)$expenseId);
            if (!$payment || empty($payment['debt_id'])) throw new InvalidArgumentException('Pembayaran hutang tidak ditemukan.');
            $status = $this->scalar_post('status', 'Status pembayaran tidak valid.');
            if (!in_array($status, array('pending','verified','rejected'), TRUE)) throw new InvalidArgumentException('Status pembayaran tidak valid.');
            $allowedTransitions = array(
                'pending' => array('pending','verified','rejected'),
                'verified' => array('verified','rejected'),
                'rejected' => array('rejected')
            );
            $currentStatus = isset($payment['status']) ? (string)$payment['status'] : '';
            if (!isset($allowedTransitions[$currentStatus]) || !in_array($status, $allowedTransitions[$currentStatus], TRUE)) {
                throw new InvalidArgumentException('Perubahan status pembayaran hutang tidak diizinkan.');
            }
            $result = $this->debt->set_payment_status((int)$expenseId, $status, $this->currentUser['id']);
            if (!$result) throw new RuntimeException('Status pembayaran hutang gagal diperbarui.');
            try { $this->Audit_model->log('debt_payment_status_changed','expense',(int)$expenseId,array('status'=>$status)); } catch (Throwable $ignored) {}
            return $this->respond_success('Status pembayaran hutang diperbarui.', array('expense_id'=>(int)$expenseId,'status'=>$status));
        } catch (InvalidArgumentException $e) {
            return $this->respond_error($e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message('error', 'Status pembayaran hutang gagal: '.$e->getMessage());
            return $this->respond_error('Status pembayaran hutang belum dapat diperbarui.', 500);
        }
    }

    public function cancel($id)
    {
        $this->require_permission('debts.manage');
        $this->require_post();
        try {
            $this->debt->cancel_debt((int)$id, $this->currentUser['id']);
            try { $this->Audit_model->log('debt_cancelled','company_debt',(int)$id); } catch (Throwable $ignored) {}
            return $this->redirect_with('hutang','success','Hutang berhasil dibatalkan.');
        } catch (InvalidArgumentException $e) { return $this->redirect_with('hutang','error',$e->getMessage()); }
        catch (Throwable $e) { log_message('error','Pembatalan hutang gagal: '.$e->getMessage()); return $this->redirect_with('hutang','error','Hutang belum dapat dibatalkan.'); }
    }

    public function cancel_ajax($id)
    {
        $this->require_permission('debts.manage');
        $this->require_post();
        try {
            $this->debt->cancel_debt((int)$id, $this->currentUser['id']);
            try { $this->Audit_model->log('debt_cancelled','company_debt',(int)$id); } catch (Throwable $ignored) {}
            return $this->respond_success('Hutang berhasil dibatalkan.', array('debt_id'=>(int)$id,'status'=>'cancelled'));
        } catch (InvalidArgumentException $e) { return $this->respond_error($e->getMessage(),422); }
        catch (Throwable $e) { log_message('error','Pembatalan hutang AJAX gagal: '.$e->getMessage()); return $this->respond_error('Hutang belum dapat dibatalkan.',500); }
    }

    private function debt_report_data()
    {
        $filters = array(
            'status' => $this->input->get('status', TRUE),
            'search' => $this->input->get('search', TRUE),
            'event_id' => $this->input->get('event_id', TRUE)
        );
        $rows = $this->debt->debts($filters);
        $ids = array();
        foreach ($rows as $row) $ids[] = (int) $row['id'];
        $paymentsByDebt = $this->debt->payments_by_debt($ids);
        foreach ($rows as &$row) {
            $row['payments'] = isset($paymentsByDebt[(int) $row['id']])
                ? $paymentsByDebt[(int) $row['id']] : array();
        }
        unset($row);

        $eventScope = 'Seluruh hutang perusahaan';
        if (!empty($filters['event_id']) && ctype_digit((string) $filters['event_id'])) {
            $event = $this->db->select('name,code')->where('id', (int) $filters['event_id'])
                ->get('training_events')->row_array();
            if ($event) $eventScope = $event['name'] . ' (' . $event['code'] . ')';
        }

        return array(
            'rows' => $rows,
            'summary' => $this->debt->summary($filters),
            'paymentsByDebt' => $paymentsByDebt,
            'activeEvents' => array(),
            'eventScope' => $eventScope,
            'reportScope' => $eventScope,
            'organizationName' => $this->finance->setting_value('organization_name', 'Penyelenggara Pelatihan')
        );
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

    private function scalar_post($key,$message)
    {
        $value=$this->input->post($key,TRUE);if(!is_scalar($value)||trim((string)$value)==='')throw new InvalidArgumentException($message);return trim((string)$value);
    }

    private function optional_scalar_post($key)
    {
        $value=$this->input->post($key,TRUE);if($value===NULL)return NULL;if(!is_scalar($value))throw new InvalidArgumentException('Data hutang tidak valid.');return trim((string)$value);
    }

    private function positive_id($value,$message)
    {
        if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new InvalidArgumentException($message);return (int)$value;
    }

    private function money_post($key,$positive,$message)
    {
        $value=$this->scalar_post($key,$message);
        $normalized=simp_money_decimal($value,!$positive);
        if($normalized===NULL)throw new InvalidArgumentException($message);
        return $normalized;
    }

    private function cash_accounts()
    {
        return array_values(array_filter($this->finance->accounts(TRUE), function ($account) {
            return isset($account['type']) && $account['type'] === 'cash';
        }));
    }

    private function respond_success($message,array $extra=array())
    {
        $payload=array_merge(array('success'=>TRUE,'message'=>$message),$extra);
        if($this->wants_json())return $this->json($payload);
        return $this->redirect_with('hutang','success',$message);
    }

    private function respond_error($message,$status=422)
    {
        if($this->wants_json())return $this->json(array('success'=>FALSE,'message'=>$message),$status);
        return $this->redirect_with('hutang','error',$message);
    }

    private function wants_json()
    {
        $accept=strtolower((string)$this->input->get_request_header('Accept'));
        return $this->input->is_ajax_request()||strpos($accept,'application/json')!==FALSE;
    }

    private function cleanup_upload($relativePath)
    {
        $relativePath=ltrim(str_replace('\\','/',(string)$relativePath),'/');if($relativePath===''||strpos($relativePath,'..')!==FALSE)return;$absolute=FCPATH.$relativePath;if(is_file($absolute))@unlink($absolute);
    }

    private function safe_upload_exception(RuntimeException $exception)
    {
        $message=preg_replace('/\s+/',' ',trim(strip_tags((string)$exception->getMessage())));
        $fragments=array('wajib diunggah','tidak diizinkan','lebih besar','ukuran maksimum','hanya diupload sebagian','tidak memilih file','not allowed','larger than','maximum allowed size','partially uploaded','did not select a file');
        foreach($fragments as $fragment)if(stripos($message,$fragment)!==FALSE)return new InvalidArgumentException($message,0,$exception);
        return new RuntimeException('Bukti pembayaran hutang tidak dapat diunggah.',0,$exception);
    }
}
