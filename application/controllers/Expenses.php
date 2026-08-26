<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Expenses extends App_Controller
{
    private $activeExpenseEvents = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_model','finance');
    }

    public function index()
    {
        $this->require_permission('expenses.view');
        $activeEvents = $this->finance->active_events();
        $activeEventIds = array_map(function ($event) { return (int)$event['id']; }, $activeEvents);
        $perPage = 20;
        $filters = array(
            'q' => $this->expense_query_string($this->input->get('q', TRUE)),
            'category_id' => $this->expense_query_integer($this->input->get('category_id', TRUE)),
            'page' => max(1, $this->expense_query_integer($this->input->get('page', TRUE)))
        );
        $filterCategories = $this->finance->expense_categories_in_use($activeEventIds, 'rejected');
        $availableCategoryIds = array_map(function ($category) { return (int)$category['id']; }, $filterCategories);
        if ($filters['category_id'] > 0 && !in_array($filters['category_id'], $availableCategoryIds, TRUE)) {
            $filters['category_id'] = 0;
        }
        // Ditolak tidak ikut daftar aktif, jumlah data, maupun total keuangan.
        $queryFilters = array('event_ids' => $activeEventIds, 'exclude_status' => 'rejected');
        if ($filters['q'] !== '') $queryFilters['search'] = $filters['q'];
        if ($filters['category_id'] > 0) $queryFilters['category_id'] = $filters['category_id'];
        $summaryFilters = $queryFilters;
        $totalRows = $activeEventIds ? $this->finance->expenses_count($queryFilters) : 0;
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        if ($filters['page'] > $totalPages) $filters['page'] = $totalPages;
        $queryFilters['limit'] = $perPage;
        $queryFilters['offset'] = ($filters['page'] - 1) * $perPage;
        $rows = $activeEventIds ? $this->finance->expenses($queryFilters) : array();
        $summary = $activeEventIds ? $this->finance->expenses_summary($summaryFilters) : array('total_count'=>0,'verified_total'=>'0.00');
        $verifiedTotal = simp_money_from_cents((int)(simp_money_cents(isset($summary['verified_total']) ? $summary['verified_total'] : '0') ?: 0));
        $this->render('expenses/index',array(
            'pageTitle'=>'Pengeluaran', 'rows'=>$rows, 'activeEvents'=>$activeEvents,
            'categories'=>$this->finance->categories(), 'filterCategories'=>$filterCategories,
            'accounts'=>$this->finance->accounts(TRUE), 'canVerify'=>$this->Auth_model->can('expenses.verify'),
            'filters'=>$filters, 'totalRows'=>$totalRows, 'totalPages'=>$totalPages, 'perPage'=>$perPage,
            'verifiedTotal'=>$verifiedTotal, 'pageScript'=>'finance.js'
        ));
    }

    public function print_preview()
    {
        $this->require_permission('expenses.view');
        $data = $this->expense_report_data();
        $data['reportKind'] = 'expense';
        $data['documentTitle'] = 'Laporan Pengeluaran';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = FALSE;
        $html = $this->load->view('reports/print_document', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    public function pdf()
    {
        $this->require_permission('expenses.view');
        $data = $this->expense_report_data();
        $data['reportKind'] = 'expense';
        $data['documentTitle'] = 'Laporan Pengeluaran';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = TRUE;
        $html = $this->load->view('reports/print_document', $data, TRUE);

        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4($html);
            return $this->private_document_output(
                'application/pdf',
                $pdf,
                'attachment; filename="laporan-pengeluaran-' . date('Ymd-His') . '.pdf"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF pengeluaran: ' . $e->getMessage());
            show_error('PDF pengeluaran belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    public function excel()
    {
        $this->require_permission('expenses.view');
        $data = $this->expense_report_data();
        $data['generatedAt'] = date('Y-m-d H:i:s');

        try {
            $this->load->library('Excel_renderer');
            $excel = $this->excel_renderer->render_expenses($data);
            return $this->private_document_output(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $excel,
                'attachment; filename="laporan-pengeluaran-' . date('Ymd-His') . '.xlsx"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat Excel pengeluaran: ' . $e->getMessage());
            show_error('Excel pengeluaran belum dapat dibuat. Silakan coba kembali.', 500, 'Excel Gagal Dibuat');
        }
    }

    public function create()
    {
        $this->require_permission('expenses.create');
        $canVerify = $this->Auth_model->can('expenses.verify');
        $activeEvents = $this->finance->active_events();
        $this->activeExpenseEvents = $activeEvents;
        if (!$activeEvents) {
            return $this->redirect_with('pengeluaran','error','Aktifkan event terlebih dahulu sebelum mencatat pengeluaran.');
        }

        $selectedEvent = NULL;
        $isPost = $this->input->method(TRUE) === 'POST';
        $requestedEventId = $isPost
            ? (int) $this->input->post('event_id')
            : (int) $this->input->get('event_id', TRUE);
        if (!$isPost && count($activeEvents) === 1) {
            $selectedEvent = $activeEvents[0];
        } else {
            foreach ($activeEvents as $activeEvent) {
                if ((int) $activeEvent['id'] === $requestedEventId) {
                    $selectedEvent = $activeEvent;
                    break;
                }
            }
        }

        if ($this->input->method(TRUE)==='POST') {
            $this->form_validation->set_rules('event_id','Event','required|integer|callback_active_expense_event');
            $this->form_validation->set_message('active_expense_event','Event pada form sudah tidak aktif. Periksa event aktif lalu kirim ulang.');
            $this->form_validation->set_rules('category_id','Kategori','required|integer');
            $this->form_validation->set_rules('expense_date','Tanggal','required|callback_valid_date');
            $this->form_validation->set_message('valid_date','Tanggal pengeluaran tidak valid.');
            // Penerima tidak lagi diminta pada alur baru. Kolom legacy tetap
            // diisi dengan label standar agar histori lama tetap kompatibel.
            $this->form_validation->set_rules('payee','Penerima','trim|max_length[160]');
            $this->form_validation->set_rules('description','Tujuan Pengeluaran','trim|max_length[3000]');
            // CI's numeric rule accepts exponent notation (for example
            // `1e3`) and can silently coerce values through a float.  Keep
            // the legacy page on the same strict DECIMAL(18,2) path as the
            // modal/AJAX endpoint.
            $this->form_validation->set_rules('amount','Jumlah','required|callback_valid_money');
            $this->form_validation->set_message('valid_money','Jumlah pengeluaran harus lebih dari nol, maksimal 16 digit, dan maksimal 2 angka desimal.');
            $this->form_validation->set_rules('method','Metode','required|in_list[cash,transfer,qris]');
            $this->form_validation->set_rules('account_id','Akun dana','required|integer');
            $this->form_validation->set_rules('admin_fee','Biaya admin','required|callback_valid_money_zero');
            $this->form_validation->set_message('valid_money_zero','Biaya admin harus nol atau lebih, maksimal 16 digit, dan maksimal 2 angka desimal.');
            $this->form_validation->set_rules('status','Status','required|in_list[pending,verified]');
            if ($this->form_validation->run()) {
                $proof = NULL;
                $id = NULL;
                try {
                    $amount = $this->normalize_expense_decimal($this->input->post('amount', TRUE), FALSE);
                    $adminFee = $this->normalize_expense_decimal($this->input->post('admin_fee', TRUE), TRUE);
                    if ($amount === NULL) throw new InvalidArgumentException('Jumlah pengeluaran harus lebih dari nol, maksimal 16 digit, dan maksimal 2 angka desimal.');
                    if ($adminFee === NULL) throw new InvalidArgumentException('Biaya admin harus nol atau lebih, maksimal 16 digit, dan maksimal 2 angka desimal.');
                    $method = $this->input->post('method', TRUE);
                    if ($method !== 'transfer' && simp_money_cents($adminFee) > 0) throw new InvalidArgumentException('Biaya admin hanya dapat diisi untuk metode Transfer.');
                    try { $proof=$this->upload_document('proof','expenses',$method !== 'cash'); }
                    catch (RuntimeException $uploadError) { throw $this->expense_upload_exception($uploadError); }
                    $data=array(
                        'event_id'=>(int)$selectedEvent['id'],
                        'category_id'=>(int)$this->input->post('category_id'),'expense_date'=>$this->input->post('expense_date',TRUE),
                        'payee'=>trim((string)$this->input->post('payee',TRUE)) ?: 'Pengeluaran Event',
                        'description'=>trim((string)$this->input->post('description',TRUE)) ?: 'Pengeluaran Event',
                        'amount'=>$amount,'method'=>$method,
                        'account_id'=>(int)$this->input->post('account_id'),'admin_fee'=>$adminFee,
                        'proof_path'=>$proof,'status'=>($canVerify && $this->input->post('status',TRUE)==='verified')?'verified':'pending',
                        'note'=>$this->input->post('note',TRUE) ?: NULL
                    );
                    $id=$this->finance->create_expense($data,$this->currentUser['id']);
                    if (!$id) throw new RuntimeException('Transaksi gagal disimpan.');
                    try { $this->Audit_model->log('expense_created','expense',$id,$data); } catch (Throwable $ignored) {}
                    return $this->redirect_with('pengeluaran','success','Pengeluaran berhasil dicatat.');
                } catch (InvalidArgumentException $e) {
                    if (!$id && $proof) $this->cleanup_expense_upload($proof);
                    $this->session->set_flashdata('error',$e->getMessage());
                } catch (RuntimeException $e) {
                    if (!$id && $proof) $this->cleanup_expense_upload($proof);
                    $this->session->set_flashdata('error',$e->getMessage());
                } catch (Throwable $e) {
                    if (!$id && $proof) $this->cleanup_expense_upload($proof);
                    log_message('error', 'Gagal membuat pengeluaran: ' . $e->getMessage());
                    $this->session->set_flashdata('error','Pengeluaran gagal disimpan karena terjadi gangguan sistem.');
                }
            }
        }
        $this->render('expenses/form',array('pageTitle'=>'Tambah Pengeluaran','categories'=>$this->finance->categories(),
            'accounts'=>$this->finance->accounts(TRUE),'activeEvents'=>$activeEvents,'selectedEvent'=>$selectedEvent,
            'canVerify'=>$canVerify,'pageScript'=>'finance.js'));
    }

    /**
     * Compact AppKit modal endpoint. New expenses are always tied to one of
     * the currently open events; the model locks and re-checks that event.
     */
    public function create_ajax()
    {
        $this->require_permission('expenses.create');
        $this->require_post();
        $proof = NULL;
        $expenseId = NULL;
        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        try {
            $eventId = $this->input->post('event_id', TRUE);
            if (!is_scalar($eventId) || !ctype_digit((string)$eventId) || (int)$eventId < 1) {
                throw new InvalidArgumentException('Event aktif tidak valid.');
            }
            $event = $this->db->where(array('id'=>(int)$eventId,'status'=>'open'))->get('training_events')->row_array();
            if (!$event) throw new InvalidArgumentException('Event sudah tidak aktif. Muat ulang halaman.');

            $categoryId = $this->input->post('category_id', TRUE);
            if (!is_scalar($categoryId) || !ctype_digit((string)$categoryId) || (int)$categoryId < 1) {
                throw new InvalidArgumentException('Kategori pengeluaran tidak valid.');
            }
            $categoryQuery = $this->db->select('id,name')->where(array('id'=>(int)$categoryId,'is_active'=>1))->get('expense_categories');
            if ($categoryQuery === FALSE) throw new RuntimeException('Pemeriksaan kategori pengeluaran gagal.');
            $category = $categoryQuery->row_array();
            if (!$category) throw new InvalidArgumentException('Kategori pengeluaran tidak valid.');
            $dateRaw = $this->input->post('expense_date', TRUE);
            if (!is_scalar($dateRaw)) throw new InvalidArgumentException('Tanggal pengeluaran tidak valid.');
            $date = (string)$dateRaw;
            $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) throw new InvalidArgumentException('Tanggal pengeluaran tidak valid.');
            $descriptionRaw = $this->input->post('description', TRUE);
            if ($descriptionRaw !== NULL && !is_scalar($descriptionRaw)) throw new InvalidArgumentException('Tujuan pengeluaran tidak valid.');
            $description = trim((string)$descriptionRaw);
            if (strlen($description) > 3000) throw new InvalidArgumentException('Tujuan pengeluaran maksimal 3.000 karakter.');
            if ($description === '') $description = substr('Pengeluaran '.$category['name'], 0, 3000);
            $amount = $this->normalize_expense_decimal($this->input->post('amount', TRUE), FALSE);
            $adminFee = $this->normalize_expense_decimal($this->input->post('admin_fee', TRUE), TRUE);
            if ($amount === NULL) throw new InvalidArgumentException('Jumlah pengeluaran harus lebih dari nol, maksimal 16 digit, dan maksimal 2 angka desimal.');
            if ($adminFee === NULL) throw new InvalidArgumentException('Biaya admin harus nol atau lebih, maksimal 16 digit, dan maksimal 2 angka desimal.');
            $methodRaw = $this->input->post('method', TRUE);
            if (!is_scalar($methodRaw)) throw new InvalidArgumentException('Metode pembayaran tidak valid.');
            $method = (string)$methodRaw;
            if (!in_array($method, array('cash','transfer','qris'), TRUE)) throw new InvalidArgumentException('Metode pembayaran tidak valid.');
            if ($method !== 'transfer' && simp_money_cents($adminFee) > 0) throw new InvalidArgumentException('Biaya admin hanya dapat diisi untuk metode Transfer.');
            $accountId = $this->input->post('account_id', TRUE);
            if (!is_scalar($accountId) || !ctype_digit((string)$accountId) || (int)$accountId < 1) throw new InvalidArgumentException('Akun dana tidak valid.');
            $statusRaw = $this->input->post('status', TRUE);
            if ($statusRaw !== NULL && !is_scalar($statusRaw)) throw new InvalidArgumentException('Status pengeluaran tidak valid.');
            $requestedStatus = $statusRaw === NULL ? 'pending' : (string)$statusRaw;
            if (!in_array($requestedStatus, array('pending','verified'), TRUE)) throw new InvalidArgumentException('Status pengeluaran tidak valid.');
            $status = $this->Auth_model->can('expenses.verify') && $requestedStatus === 'verified' ? 'verified' : 'pending';
            $noteRaw = $this->input->post('note', TRUE);
            if ($noteRaw !== NULL && !is_scalar($noteRaw)) throw new InvalidArgumentException('Catatan pengeluaran tidak valid.');
            $note = trim((string)$noteRaw);
            if (strlen($note) > 2000) throw new InvalidArgumentException('Catatan maksimal 2.000 karakter.');
            $folder = 'expenses';
            $uploadPath = FCPATH . 'uploads/' . $folder;
            if (!is_dir($uploadPath) && !mkdir($uploadPath, 0755, TRUE) && !is_dir($uploadPath)) throw new RuntimeException('Folder bukti pengeluaran tidak dapat dibuat.');
            try {
                $proof = $this->upload_document('proof', $folder, $method !== 'cash');
            } catch (RuntimeException $e) {
                throw $this->expense_upload_exception($e);
            }
            $data = array(
                'event_id'=>(int)$eventId,
                'category_id'=>(int)$categoryId,
                'expense_date'=>$date,
                'payee'=>'Pengeluaran Event',
                'description'=>$description,
                'amount'=>$amount,
                'method'=>$method,
                'account_id'=>(int)$accountId,
                'admin_fee'=>$adminFee,
                'proof_path'=>$proof,
                'status'=>$status,
                'note'=>$note !== '' ? $note : NULL
            );
            $expenseId = $this->finance->create_expense($data, $this->currentUser['id']);
            if (!$expenseId) throw new RuntimeException('Pengeluaran gagal disimpan.');
            try { $this->Audit_model->log('expense_created','expense',$expenseId,$data); } catch (Throwable $ignored) {}
            return $this->json(array('success'=>TRUE,'message'=>'Pengeluaran berhasil dicatat.','expense_id'=>(int)$expenseId,'event_id'=>(int)$eventId));
        } catch (InvalidArgumentException $e) {
            if (!$expenseId && $proof) $this->cleanup_expense_upload($proof);
            return $this->json(array('success'=>FALSE,'message'=>$e->getMessage()), 422);
        } catch (Throwable $e) {
            if (!$expenseId && $proof) $this->cleanup_expense_upload($proof);
            $detail = $e->getMessage();
            if ($e->getPrevious()) $detail .= ' | Penyebab: ' . $e->getPrevious()->getMessage();
            log_message('error', 'Gagal membuat pengeluaran melalui AJAX: ' . $detail);
            return $this->json(array('success'=>FALSE,'message'=>'Pengeluaran gagal disimpan karena terjadi gangguan sistem.'), 500);
        } finally {
            $this->db->db_debug = $originalDbDebug;
        }
    }

    /** Update a regular expense from the shared create/edit modal. */
    public function update_ajax($id)
    {
        $this->require_permission('expenses.create');
        $this->require_post();
        $id = (int)$id;
        $expense = $this->finance->expense($id);
        if (!$expense) return $this->json(array('success'=>FALSE,'message'=>'Pengeluaran tidak ditemukan.'), 404);
        if (!empty($expense['debt_id'])) return $this->json(array('success'=>FALSE,'message'=>'Pembayaran hutang hanya dapat dikelola dari modul Hutang.'), 422);
        $canEditVerified = $this->Auth_model->can('expenses.verify');
        if ($expense['status'] === 'verified' && !$canEditVerified) {
            return $this->json(array('success'=>FALSE,'message'=>'Pengeluaran terverifikasi hanya dapat diubah oleh pengguna yang berhak memverifikasi.'), 403);
        }

        $newProof = NULL;
        $oldProof = !empty($expense['proof_path']) ? (string)$expense['proof_path'] : NULL;
        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        try {
            $eventId = $this->input->post('event_id', TRUE);
            if (!is_scalar($eventId) || !ctype_digit((string)$eventId) || (int)$eventId < 1) throw new InvalidArgumentException('Event aktif tidak valid.');
            $categoryId = $this->input->post('category_id', TRUE);
            if (!is_scalar($categoryId) || !ctype_digit((string)$categoryId) || (int)$categoryId < 1) throw new InvalidArgumentException('Kategori pengeluaran tidak valid.');
            $category = $this->db->select('id,name')->where(array('id'=>(int)$categoryId,'is_active'=>1))->get('expense_categories')->row_array();
            if (!$category) throw new InvalidArgumentException('Kategori pengeluaran tidak valid.');

            $dateRaw = $this->input->post('expense_date', TRUE);
            if (!is_scalar($dateRaw)) throw new InvalidArgumentException('Tanggal pengeluaran tidak valid.');
            $date = (string)$dateRaw;
            $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) throw new InvalidArgumentException('Tanggal pengeluaran tidak valid.');

            $descriptionRaw = $this->input->post('description', TRUE);
            if ($descriptionRaw !== NULL && !is_scalar($descriptionRaw)) throw new InvalidArgumentException('Deskripsi pengeluaran tidak valid.');
            $description = trim((string)$descriptionRaw);
            if (strlen($description) > 3000) throw new InvalidArgumentException('Deskripsi pengeluaran maksimal 3.000 karakter.');
            if ($description === '') $description = substr('Pengeluaran '.$category['name'], 0, 3000);

            $amount = $this->normalize_expense_decimal($this->input->post('amount', TRUE), FALSE);
            $adminFee = $this->normalize_expense_decimal($this->input->post('admin_fee', TRUE), TRUE);
            if ($amount === NULL) throw new InvalidArgumentException('Jumlah pengeluaran harus lebih dari nol, maksimal 16 digit, dan maksimal 2 angka desimal.');
            if ($adminFee === NULL) throw new InvalidArgumentException('Biaya admin harus nol atau lebih, maksimal 16 digit, dan maksimal 2 angka desimal.');

            $methodRaw = $this->input->post('method', TRUE);
            if (!is_scalar($methodRaw) || !in_array((string)$methodRaw, array('cash','transfer','qris'), TRUE)) throw new InvalidArgumentException('Metode pembayaran tidak valid.');
            $method = (string)$methodRaw;
            if ($method !== 'transfer' && simp_money_cents($adminFee) > 0) throw new InvalidArgumentException('Biaya admin hanya dapat diisi untuk metode Transfer.');

            $accountId = $this->input->post('account_id', TRUE);
            if (!is_scalar($accountId) || !ctype_digit((string)$accountId) || (int)$accountId < 1) throw new InvalidArgumentException('Akun dana tidak valid.');
            $noteRaw = $this->input->post('note', TRUE);
            if ($noteRaw !== NULL && !is_scalar($noteRaw)) throw new InvalidArgumentException('Catatan pengeluaran tidak valid.');
            $note = trim((string)$noteRaw);
            if (strlen($note) > 2000) throw new InvalidArgumentException('Catatan maksimal 2.000 karakter.');
            $expectedUpdatedAt = $this->input->post('expected_updated_at', TRUE);
            if ($expectedUpdatedAt !== NULL && !is_scalar($expectedUpdatedAt)) throw new InvalidArgumentException('Versi data pengeluaran tidak valid.');
            $expectedUpdatedAt = trim((string)$expectedUpdatedAt);
            if (strlen($expectedUpdatedAt) > 32) throw new InvalidArgumentException('Versi data pengeluaran tidak valid.');

            $oldProofUsable = $oldProof && strpos(str_replace('\\','/',$oldProof), 'uploads/expenses/') === 0 && is_file(FCPATH.$oldProof);
            $hasUpload = !empty($_FILES['proof']['name']);
            if ($hasUpload) {
                $uploadPath = FCPATH . 'uploads/expenses';
                if (!is_dir($uploadPath) && !mkdir($uploadPath, 0755, TRUE) && !is_dir($uploadPath)) {
                    throw new RuntimeException('Folder bukti pengeluaran tidak dapat dibuat.');
                }
                try { $newProof = $this->upload_document('proof', 'expenses', FALSE); }
                catch (RuntimeException $uploadError) { throw $this->expense_upload_exception($uploadError); }
            }
            $proof = $newProof ?: ($oldProofUsable ? $oldProof : NULL);
            if ($method !== 'cash' && !$proof) throw new InvalidArgumentException('Bukti pembayaran wajib diunggah untuk metode Transfer atau QRIS.');

            $data = array(
                'event_id'=>(int)$eventId, 'category_id'=>(int)$categoryId,
                'expense_date'=>$date, 'description'=>$description,
                'amount'=>$amount, 'method'=>$method, 'account_id'=>(int)$accountId,
                'admin_fee'=>$adminFee, 'proof_path'=>$proof,
                'note'=>$note !== '' ? $note : NULL
            );
            $this->finance->update_expense($id, $data, $this->currentUser['id'], $canEditVerified, $expectedUpdatedAt !== '' ? $expectedUpdatedAt : NULL);
            if ($newProof && $oldProofUsable && $oldProof !== $newProof) $this->cleanup_expense_upload($oldProof);
            try { $this->Audit_model->log('expense_updated','expense',$id,$data); } catch (Throwable $ignored) {}
            return $this->json(array('success'=>TRUE,'message'=>'Pengeluaran berhasil diperbarui.','expense_id'=>$id));
        } catch (InvalidArgumentException $e) {
            if ($newProof) $this->cleanup_expense_upload($newProof);
            return $this->json(array('success'=>FALSE,'message'=>$e->getMessage()), 422);
        } catch (Throwable $e) {
            if ($newProof) $this->cleanup_expense_upload($newProof);
            log_message('error', 'Gagal memperbarui pengeluaran #'.$id.': '.$e->getMessage());
            return $this->json(array('success'=>FALSE,'message'=>'Pengeluaran gagal diperbarui karena terjadi gangguan sistem.'), 500);
        } finally {
            $this->db->db_debug = $originalDbDebug;
        }
    }

    /**
     * Normalize a value for MySQL DECIMAL(18,2). DECIMAL(18,2) has at most
     * sixteen integer digits and two fractional digits. Returning a string
     * also avoids changing the submitted monetary value through a float cast.
     */
    private function normalize_expense_decimal($raw, $allowZero)
    {
        if (!is_scalar($raw)) return NULL;
        $value = trim((string)$raw);
        if (!preg_match('/^\d+(?:\.(\d{1,2}))?$/D', $value, $matches)) return NULL;

        $parts = explode('.', $value, 2);
        $integer = ltrim($parts[0], '0');
        if ($integer === '') $integer = '0';
        if (strlen($integer) > 16) return NULL;

        $fraction = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';
        if (!$allowZero && $integer === '0' && $fraction === '00') return NULL;
        return $integer . '.' . $fraction;
    }

    public function valid_date($value)
    {
        if (!is_scalar($value)) return FALSE;
        $date = DateTime::createFromFormat('Y-m-d', (string)$value);
        return $date && $date->format('Y-m-d') === (string)$value;
    }

    /** Strict legacy form callbacks; never allow float/exponent coercion. */
    public function valid_money($value)
    {
        return simp_money_decimal($value, FALSE) !== NULL;
    }

    public function valid_money_zero($value)
    {
        return simp_money_decimal($value, TRUE) !== NULL;
    }

    private function cleanup_expense_upload($relativePath)
    {
        $relativePath = ltrim(str_replace('\\','/',(string)$relativePath), '/');
        if ($relativePath === '' || strpos($relativePath, '..') !== FALSE || strpos($relativePath, 'uploads/expenses/') !== 0) return;
        $root = realpath(FCPATH . 'uploads/expenses');
        $absolute = realpath(FCPATH . $relativePath);
        if ($root !== FALSE && $absolute !== FALSE && is_file($absolute) && strpos($absolute, $root . DIRECTORY_SEPARATOR) === 0) @unlink($absolute);
    }

    /** Keep file validation useful without exposing upload paths/runtime details. */
    private function expense_upload_exception(RuntimeException $exception)
    {
        $message = preg_replace('/\s+/', ' ', trim(strip_tags((string)$exception->getMessage())));
        $safeFragments = array(
            'wajib diunggah',
            'tidak diizinkan', 'lebih besar', 'ukuran maksimum', 'hanya diupload sebagian',
            'tidak memilih file', 'not allowed', 'larger than', 'maximum allowed size',
            'partially uploaded', 'did not select a file'
        );
        foreach ($safeFragments as $fragment) {
            if (stripos($message, $fragment) !== FALSE) return new InvalidArgumentException($message, 0, $exception);
        }
        return new RuntimeException('Bukti pengeluaran tidak dapat diunggah.', 0, $exception);
    }

    public function active_expense_event($eventId)
    {
        if (!is_scalar($eventId)) return FALSE;
        $eventId = (int) $eventId;
        foreach ($this->activeExpenseEvents as $event) {
            if ((int) $event['id'] === $eventId) return TRUE;
        }
        return FALSE;
    }

    public function category_create()
    {
        $this->require_permission('expenses.verify'); $this->require_post();
        $result=$this->finance->add_category($this->input->post('category_name',TRUE));
        if ($result['success']) $this->Audit_model->log('expense_category_created','expense_category',$result['id']);
        return $this->redirect_with('pengeluaran/tambah',$result['success']?'success':'error',$result['message']);
    }

    public function status($id)
    {
        $this->require_permission('expenses.verify'); $this->require_post();
        $expense = $this->finance->expense((int)$id);
        if (!$expense) show_404();
        if (!empty($expense['debt_id'])) $this->require_permission('debts.verify');
        $isAjax = $this->input->is_ajax_request() || strpos(strtolower((string)$this->input->get_request_header('Accept')), 'application/json') !== FALSE;
        $statusRaw=$this->input->post('status',TRUE);
        if (!is_scalar($statusRaw) || !in_array((string)$statusRaw, array('pending','verified','rejected'), TRUE)) {
            if ($isAjax) return $this->json(array('success'=>FALSE,'message'=>'Status pengeluaran tidak valid.'), 422);
            return $this->redirect_with('pengeluaran','error','Status pengeluaran tidak valid.');
        }
        $status=(string)$statusRaw;
        $allowedTransitions = array(
            'pending' => array('pending','verified','rejected'),
            'verified' => array('verified','rejected'),
            'rejected' => array('rejected')
        );
        $currentStatus = isset($expense['status']) ? (string)$expense['status'] : '';
        if (!isset($allowedTransitions[$currentStatus]) || !in_array($status, $allowedTransitions[$currentStatus], TRUE)) {
            $message = 'Perubahan status pengeluaran tidak diizinkan.';
            if ($isAjax) return $this->json(array('success'=>FALSE,'message'=>$message), 422);
            return $this->redirect_with('pengeluaran','error',$message);
        }

        try {
            $originalDbDebug = $this->db->db_debug;
            $this->db->db_debug = FALSE;
            $ok=$this->finance->set_expense_status((int)$id,$status,$this->currentUser['id']);
            if (!$ok) throw new RuntimeException('Model gagal memperbarui status pengeluaran.');
            try { $this->Audit_model->log('expense_status_changed','expense',(int)$id,array('status'=>$status)); } catch (Throwable $ignored) {}
            $message = 'Status pengeluaran diperbarui.';
            if ($isAjax) return $this->json(array('success'=>TRUE,'message'=>$message,'expense_id'=>(int)$id,'status'=>$status));
            return $this->redirect_with('pengeluaran','success',$message);
        } catch (InvalidArgumentException $e) {
            if ($isAjax) return $this->json(array('success'=>FALSE,'message'=>$e->getMessage()), 422);
            return $this->redirect_with('pengeluaran','error',$e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Gagal memperbarui status pengeluaran #' . (int)$id . ': ' . $e->getMessage());
            if ($isAjax) return $this->json(array('success'=>FALSE,'message'=>'Status pengeluaran gagal diperbarui karena terjadi gangguan sistem.'), 500);
            return $this->redirect_with('pengeluaran','error','Status pengeluaran gagal diperbarui karena terjadi gangguan sistem.');
        } finally {
            if (isset($originalDbDebug)) $this->db->db_debug = $originalDbDebug;
        }
    }

    private function expense_report_data()
    {
        $activeEvents = $this->finance->active_events();
        $activeEventIds = array_map(function ($event) { return (int) $event['id']; }, $activeEvents);
        return array(
            'activeEvents' => $activeEvents,
            // A rejected expense is excluded from operational reports and
            // totals, just like it is excluded from the active list.
            'rows' => $activeEventIds ? $this->finance->expenses(array('event_ids' => $activeEventIds, 'exclude_status' => 'rejected')) : array(),
            'organizationName' => $this->finance->setting_value('organization_name', 'Penyelenggara Pelatihan')
        );
    }

    private function expense_query_string($value)
    {
        if (!is_scalar($value)) return '';
        $value = trim((string)$value);
        return function_exists('mb_substr') ? mb_substr($value, 0, 120, 'UTF-8') : substr($value, 0, 120);
    }

    private function expense_query_integer($value)
    {
        if (!is_scalar($value) || !preg_match('/^\d+$/', (string)$value)) return 0;
        return max(0, (int)$value);
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
