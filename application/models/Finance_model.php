<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance_model extends CI_Model
{
    public function accounts($activeOnly = FALSE, $asOf = NULL)
    {
        $dateSql = $asOf ? ' AND l.entry_date <= ' . $this->db->escape($asOf) : '';
        $balanceSql = "a.opening_balance + COALESCE((SELECT SUM(CASE WHEN l.direction='in' THEN l.amount ELSE -l.amount END) FROM ledger_entries l WHERE l.account_id=a.id{$dateSql}),0) AS balance";
        $this->db->select('a.*')->select($balanceSql, FALSE)->from('fund_accounts a')
            ->order_by('a.sort_order', 'ASC')->order_by('a.name', 'ASC');
        if ($activeOnly) $this->db->where('a.is_active', 1);
        return $this->db->get()->result_array();
    }

    public function account($id)
    {
        return $this->db->where('id', (int) $id)->get('fund_accounts')->row_array();
    }

    public function save_account(array $data, $id, $userId)
    {
        $now = date('Y-m-d H:i:s');
        if ((int) $id > 0) {
            $accountId = (int) $id;
            $originalDbDebug = $this->db->db_debug;
            $this->db->db_debug = FALSE;
            $this->db->trans_begin();
            try {
                // All application ledger writers lock this same account row.
                // The account definition and its balance therefore cannot move
                // between validation and update.
                $existing = $this->db->query(
                    'SELECT * FROM fund_accounts WHERE id=? FOR UPDATE',
                    array($accountId)
                )->row_array();
                if (!$existing) throw new InvalidArgumentException('Akun dana tidak ditemukan.');

                $ledger = $this->db->select(
                    "COUNT(*) ledger_count, COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) incoming, COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) outgoing",
                    FALSE
                )->where('account_id', $accountId)->get('ledger_entries')->row_array();
                $ledgerCount = (int) $ledger['ledger_count'];
                $incomingCents = $this->money_cents($ledger['incoming']);
                $outgoingCents = $this->money_cents($ledger['outgoing']);
                if ($incomingCents === NULL || $outgoingCents === NULL) {
                    throw new RuntimeException('Saldo akun memiliki nominal transaksi yang tidak valid.');
                }
                $movementCents = $incomingCents - $outgoingCents;

                $requestedType = array_key_exists('type', $data) ? (string) $data['type'] : (string) $existing['type'];
                if (!in_array($requestedType, array('cash', 'bank', 'qris', 'personal'), TRUE)) {
                    throw new InvalidArgumentException('Jenis akun dana tidak valid.');
                }
                $requestedOpening = array_key_exists('opening_balance', $data)
                    ? $this->money_cents($data['opening_balance'])
                    : $this->money_cents($existing['opening_balance']);
                if ($requestedOpening === NULL) {
                    throw new InvalidArgumentException('Saldo awal harus berupa nominal non-negatif dengan maksimal 2 angka desimal.');
                }
                $existingOpening = $this->money_cents($existing['opening_balance']);
                if ($existingOpening === NULL) {
                    throw new RuntimeException('Saldo awal akun memiliki nominal yang tidak valid.');
                }
                $pendingExpenses = (int) $this->db->where(array('account_id' => $accountId, 'status' => 'pending'))
                    ->count_all_results('expenses');
                $this->db->group_start()->where('from_account_id', $accountId)->or_where('to_account_id', $accountId)->group_end();
                $pendingTransfers = (int) $this->db->where('status', 'pending')->count_all_results('fund_transfers');
                $pendingPayments = (int) $this->db->where(array('account_id' => $accountId, 'status' => 'pending'))
                    ->count_all_results('payments');
                $pendingReferences = $pendingExpenses + $pendingTransfers + $pendingPayments;
                $typeChanged = $requestedType !== (string) $existing['type'];
                $openingChanged = $requestedOpening !== $existingOpening;
                if ($ledgerCount > 0 && $requestedType !== (string) $existing['type']) {
                    throw new InvalidArgumentException('Jenis akun tidak dapat diubah karena akun sudah memiliki riwayat transaksi.');
                }
                if ($ledgerCount > 0 && $openingChanged) {
                    throw new InvalidArgumentException('Saldo awal tidak dapat diubah karena akun sudah memiliki riwayat transaksi. Gunakan jurnal penyesuaian untuk koreksi saldo.');
                }

                $requestedActive = array_key_exists('is_active', $data)
                    ? (int) $data['is_active']
                    : (int) $existing['is_active'];
                if (!in_array($requestedActive, array(0, 1), TRUE)) {
                    throw new InvalidArgumentException('Status akun dana tidak valid.');
                }
                if ($pendingReferences > 0 && ($typeChanged || $openingChanged || ($requestedActive === 0 && (int) $existing['is_active'] === 1))) {
                    throw new InvalidArgumentException('Jenis, saldo awal, atau status aktif akun tidak dapat diubah karena masih direferensikan transaksi yang menunggu verifikasi.');
                }
                $endingBalanceCents = $requestedOpening + $movementCents;
                if ($requestedActive !== 1 && $endingBalanceCents !== 0) {
                    throw new InvalidArgumentException('Akun tidak dapat dinonaktifkan karena saldo akhirnya masih belum nol. Pindahkan atau koreksi saldo terlebih dahulu.');
                }

                $data['opening_balance'] = $this->cents_to_decimal($requestedOpening);
                $data['updated_at'] = $now;
                if (!$this->db->where('id', $accountId)->update('fund_accounts', $data)) {
                    throw new RuntimeException('Perubahan akun dana gagal disimpan.');
                }
                if ($this->db->trans_status() === FALSE) {
                    $error = $this->db->error();
                    throw new RuntimeException(!empty($error['message']) ? $error['message'] : 'Perubahan akun dana gagal disimpan.');
                }
                if (!$this->db->trans_commit()) throw new RuntimeException('Transaksi perubahan akun dana gagal diselesaikan.');
                $this->db->db_debug = $originalDbDebug;
                return $accountId;
            } catch (Throwable $e) {
                $this->db->trans_rollback();
                $this->db->db_debug = $originalDbDebug;
                throw $e;
            }
        }
        if (!isset($data['type']) || !in_array((string) $data['type'], array('cash', 'bank', 'qris', 'personal'), TRUE)) {
            throw new InvalidArgumentException('Jenis akun dana tidak valid.');
        }
        $openingCents = $this->money_cents(isset($data['opening_balance']) ? $data['opening_balance'] : NULL);
        if ($openingCents === NULL) {
            throw new InvalidArgumentException('Saldo awal harus berupa nominal non-negatif dengan maksimal 2 angka desimal.');
        }
        $requestedActive = array_key_exists('is_active', $data) ? (int) $data['is_active'] : 1;
        if (!in_array($requestedActive, array(0, 1), TRUE)) {
            throw new InvalidArgumentException('Status akun dana tidak valid.');
        }
        if ($requestedActive !== 1 && $openingCents !== 0) {
            throw new InvalidArgumentException('Akun baru yang nonaktif harus memiliki saldo awal nol.');
        }
        $data['opening_balance'] = $this->cents_to_decimal($openingCents);
        $data['created_by'] = (int) $userId;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        if (!$this->db->insert('fund_accounts', $data)) return FALSE;
        return (int) $this->db->insert_id();
    }

    public function events()
    {
        return $this->db->select('id,code,name,start_date,end_date,status')
            ->from('training_events')->order_by('start_date', 'DESC')->get()->result_array();
    }

    public function active_events()
    {
        return $this->db->select('id,code,name,start_date,end_date,location,status,updated_at')
            ->from('training_events')->where('status', 'open')
            ->order_by('updated_at', 'DESC')->order_by('id', 'DESC')->get()->result_array();
    }

    public function setting_value($key, $fallback = '')
    {
        $row = $this->db->select('setting_value')->where('setting_key', (string) $key)->get('settings')->row_array();
        if (!$row || trim((string) $row['setting_value']) === '') return (string) $fallback;
        return trim((string) $row['setting_value']);
    }

    public function categories($activeOnly = TRUE)
    {
        $this->db->from('expense_categories')->order_by('name', 'ASC');
        if ($activeOnly) $this->db->where('is_active', 1);
        return $this->db->get()->result_array();
    }

    public function add_category($name)
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));
        if ($name === '') return array('success' => FALSE, 'message' => 'Nama kategori wajib diisi.');
        $code = strtolower($name);
        $code = preg_replace('/[^a-z0-9]+/i', '-', $code);
        $code = trim($code, '-');
        if ($code === '') $code = 'kategori-' . date('His');
        $base = substr($code, 0, 52);
        $candidate = $base;
        $suffix = 2;
        while ($this->db->where('code', $candidate)->count_all_results('expense_categories') > 0) {
            $candidate = substr($base, 0, 52) . '-' . $suffix++;
        }
        $ok = $this->db->insert('expense_categories', array(
            'name' => substr($name, 0, 100), 'code' => substr($candidate, 0, 60), 'is_active' => 1
        ));
        return array('success' => (bool) $ok, 'id' => $ok ? (int) $this->db->insert_id() : 0,
            'message' => $ok ? 'Kategori pengeluaran berhasil ditambahkan.' : 'Kategori gagal ditambahkan.');
    }

    private function document_number($settingKey, $fallback)
    {
        $row = $this->db->select('setting_value')->where('setting_key', $settingKey)->get('settings')->row_array();
        $prefix = $row && trim($row['setting_value']) !== '' ? strtoupper(trim($row['setting_value'])) : $fallback;
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix);
        if ($prefix === '') $prefix = $fallback;
        $targets = array(
            'expense_prefix' => array('expenses', 'expense_no'),
            'transfer_prefix' => array('fund_transfers', 'transfer_no')
        );
        $target = isset($targets[$settingKey]) ? $targets[$settingKey] : NULL;
        for ($attempt = 0; $attempt < 6; $attempt++) {
            try { $random = strtoupper(bin2hex(random_bytes(3))); }
            catch (Exception $e) { $random = strtoupper(substr(sha1(uniqid('', TRUE)), 0, 6)); }
            $number = substr($prefix, 0, 8) . '-' . date('Ymd-His') . '-' . $random;
            if ($target === NULL || !$this->db->where($target[1], $number)->count_all_results($target[0])) return $number;
        }
        throw new RuntimeException('Nomor transaksi unik belum dapat dibuat. Silakan coba kembali.');
    }

    public function expenses(array $filters = array())
    {
        $this->db->select('x.*,c.name category_name,e.name event_name,d.debt_no,d.creditor debt_creditor,a.name account_name,u.name created_by_name')
            ->from('expenses x')->join('expense_categories c', 'c.id=x.category_id')
            ->join('training_events e', 'e.id=x.event_id', 'left')->join('company_debts d', 'd.id=x.debt_id', 'left')->join('fund_accounts a', 'a.id=x.account_id')
            ->join('users u', 'u.id=x.created_by', 'left')->order_by('x.expense_date', 'DESC')->order_by('x.id', 'DESC');
        $this->apply_transaction_filters('x', $filters);
        if (array_key_exists('limit', $filters)) {
            $limit = max(1, min(200, (int)$filters['limit']));
            $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
            $this->db->limit($limit, $offset);
        }
        return $this->db->get()->result_array();
    }

    /** Count expense rows using the same searchable fields as expenses(). */
    public function expenses_count(array $filters = array())
    {
        $this->db->select('COUNT(*) AS total', FALSE)
            ->from('expenses x')->join('expense_categories c', 'c.id=x.category_id')
            ->join('training_events e', 'e.id=x.event_id', 'left')
            ->join('company_debts d', 'd.id=x.debt_id', 'left')
            ->join('fund_accounts a', 'a.id=x.account_id');
        $this->apply_transaction_filters('x', $filters);
        $row = $this->db->get()->row_array();
        return $row ? (int)$row['total'] : 0;
    }

    /** Totals for the full filtered result, independent of the current page. */
    public function expenses_summary(array $filters = array())
    {
        $this->db->select(
            "COUNT(*) AS total_count, COALESCE(SUM(CASE WHEN x.status='verified' THEN COALESCE(x.amount,0)+COALESCE(x.admin_fee,0) ELSE 0 END),0) AS verified_total",
            FALSE
        )->from('expenses x')->join('expense_categories c', 'c.id=x.category_id')
            ->join('training_events e', 'e.id=x.event_id', 'left')
            ->join('company_debts d', 'd.id=x.debt_id', 'left')
            ->join('fund_accounts a', 'a.id=x.account_id');
        $this->apply_transaction_filters('x', $filters);
        $row = $this->db->get()->row_array();
        return $row ?: array('total_count' => 0, 'verified_total' => '0.00');
    }

    /** Only categories that actually have expenses in the selected events. */
    public function expense_categories_in_use(array $eventIds, $excludeStatus = NULL)
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
        if (!$eventIds) return array();
        $this->db->select('c.id,c.name,COUNT(x.id) AS expense_count', FALSE)
            ->from('expense_categories c')->join('expenses x', 'x.category_id=c.id')
            ->where_in('x.event_id', $eventIds);
        if ($excludeStatus !== NULL && $excludeStatus !== '') $this->db->where('x.status !=', (string)$excludeStatus);
        return $this->db->group_by(array('c.id', 'c.name'))->order_by('c.name', 'ASC')->get()->result_array();
    }

    public function expense($id)
    {
        return $this->db->where('id', (int) $id)->get('expenses')->row_array();
    }

    public function create_expense(array $data, $userId)
    {
        $amountCents = $this->money_cents(isset($data['amount']) ? $data['amount'] : NULL);
        $feeCents = $this->money_cents(isset($data['admin_fee']) ? $data['admin_fee'] : '0');
        if ($amountCents === NULL || $amountCents <= 0) {
            throw new InvalidArgumentException('Nominal pengeluaran harus lebih dari nol dengan maksimal 2 angka desimal.');
        }
        if ($feeCents === NULL) {
            throw new InvalidArgumentException('Biaya admin tidak valid.');
        }
        if (empty($data['method']) || !in_array((string) $data['method'], array('cash', 'transfer', 'qris'), TRUE)) {
            throw new InvalidArgumentException('Metode pembayaran tidak valid.');
        }
        if ((string) $data['method'] !== 'transfer' && $feeCents > 0) {
            throw new InvalidArgumentException('Biaya admin hanya dapat diisi untuk metode Transfer.');
        }
        if (!$this->valid_date(isset($data['expense_date']) ? $data['expense_date'] : NULL)) {
            throw new InvalidArgumentException('Tanggal pengeluaran tidak valid.');
        }
        $proofPath = isset($data['proof_path']) ? $data['proof_path'] : NULL;
        if ($proofPath !== NULL && !$this->valid_upload_path($proofPath, 'expenses')) {
            throw new InvalidArgumentException('Bukti pengeluaran tidak valid.');
        }
        if ($proofPath !== NULL && $this->db->where('proof_path', $proofPath)->count_all_results('expenses')) {
            throw new InvalidArgumentException('Bukti pengeluaran sudah digunakan oleh transaksi lain.');
        }
        if ((string) $data['method'] !== 'cash' && !$this->valid_upload_path($proofPath, 'expenses')) {
            throw new InvalidArgumentException('Bukti pembayaran wajib diunggah untuk metode Transfer atau QRIS.');
        }
        if (empty($data['status']) || !in_array((string) $data['status'], array('pending', 'verified', 'rejected'), TRUE)) {
            throw new InvalidArgumentException('Status pengeluaran tidak valid.');
        }
        $data['amount'] = $this->cents_to_decimal($amountCents);
        $data['admin_fee'] = $this->cents_to_decimal($feeCents);
        $data['expense_no'] = $this->document_number('expense_prefix', 'OUT');
        $data['created_by'] = (int) $userId;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = $data['created_at'];
        if ($data['status'] === 'verified') {
            $data['verified_by'] = (int) $userId;
            $data['verified_at'] = $data['created_at'];
        }
        $this->db->trans_begin();
        $eventId = isset($data['event_id']) ? (int) $data['event_id'] : 0;
        $event = $eventId > 0
            ? $this->db->query('SELECT id,status FROM training_events WHERE id=? FOR UPDATE', array($eventId))->row_array()
            : NULL;
        if (!$event || $event['status'] !== 'open') {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Event sudah tidak aktif. Pengeluaran tidak dapat disimpan.');
        }
        $accountId = isset($data['account_id']) ? (int) $data['account_id'] : 0;
        if ($accountId < 1) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Akun dana tidak valid.');
        }
        $data['account_id'] = $accountId;
        $account = $this->lock_account($accountId);
        if (!$account || !(int) $account['is_active'] || !$this->account_matches_method($account['type'], $data['method'])) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Akun dana tidak aktif atau tidak sesuai dengan metode pembayaran.');
        }
        if ($data['status'] === 'verified') {
            $balanceCents = $this->locked_account_balance_cents($account);
            if ($balanceCents === NULL || $balanceCents < $amountCents + $feeCents) {
                $this->db->trans_rollback();
                throw new InvalidArgumentException('Saldo akun tidak mencukupi untuk pengeluaran beserta biaya admin.');
            }
        }
        if (!$this->db->insert('expenses', $data)) { $this->db->trans_rollback(); return FALSE; }
        $id = (int) $this->db->insert_id();
        if ($data['status'] === 'verified' && !$this->post_expense_ledger($id, $data, $userId)) {
            $this->db->trans_rollback(); return FALSE;
        }
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); return FALSE; }
        if (!$this->db->trans_commit()) {
            $this->db->trans_rollback();
            throw new RuntimeException('Transaksi pengeluaran gagal diselesaikan.');
        }
        return $id;
    }

    /**
     * Update a regular event expense while keeping its verified ledger entry
     * and the source-account balance in one transaction. Debt repayments are
     * owned by Debt_model and deliberately cannot be edited through here.
     */
    public function update_expense($id, array $data, $userId, $canEditVerified = FALSE, $expectedUpdatedAt = NULL)
    {
        $id = (int) $id;
        if ($id < 1) throw new InvalidArgumentException('Pengeluaran tidak valid.');

        $amountCents = $this->money_cents(isset($data['amount']) ? $data['amount'] : NULL);
        $feeCents = $this->money_cents(isset($data['admin_fee']) ? $data['admin_fee'] : '0');
        $method = isset($data['method']) && is_scalar($data['method']) ? (string) $data['method'] : '';
        if ($amountCents === NULL || $amountCents <= 0) throw new InvalidArgumentException('Nominal pengeluaran harus lebih dari nol dengan maksimal 2 angka desimal.');
        if ($feeCents === NULL) throw new InvalidArgumentException('Biaya admin tidak valid.');
        if (!in_array($method, array('cash','transfer','qris'), TRUE)) throw new InvalidArgumentException('Metode pembayaran tidak valid.');
        if ($method !== 'transfer' && $feeCents > 0) throw new InvalidArgumentException('Biaya admin hanya dapat diisi untuk metode Transfer.');
        if (!$this->valid_date(isset($data['expense_date']) ? $data['expense_date'] : NULL)) throw new InvalidArgumentException('Tanggal pengeluaran tidak valid.');

        $eventId = isset($data['event_id']) ? (int) $data['event_id'] : 0;
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : 0;
        $accountId = isset($data['account_id']) ? (int) $data['account_id'] : 0;
        if ($eventId < 1) throw new InvalidArgumentException('Event aktif tidak valid.');
        if ($categoryId < 1) throw new InvalidArgumentException('Kategori pengeluaran tidak valid.');
        if ($accountId < 1) throw new InvalidArgumentException('Akun dana tidak valid.');

        $description = isset($data['description']) && is_scalar($data['description']) ? trim((string)$data['description']) : '';
        $note = isset($data['note']) && is_scalar($data['note']) ? trim((string)$data['note']) : '';
        if ($description === '' || strlen($description) > 3000) throw new InvalidArgumentException('Deskripsi pengeluaran wajib diisi dan maksimal 3.000 karakter.');
        if (strlen($note) > 2000) throw new InvalidArgumentException('Catatan maksimal 2.000 karakter.');

        $proofPath = array_key_exists('proof_path', $data) ? $data['proof_path'] : NULL;
        if ($proofPath !== NULL && !$this->valid_upload_path($proofPath, 'expenses')) throw new InvalidArgumentException('Bukti pengeluaran tidak valid.');
        if ($method !== 'cash' && !$this->valid_upload_path($proofPath, 'expenses')) throw new InvalidArgumentException('Bukti pembayaran wajib diunggah untuk metode Transfer atau QRIS.');

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $row = $this->db->query('SELECT * FROM expenses WHERE id=? FOR UPDATE', array($id))->row_array();
            if (!$row) throw new InvalidArgumentException('Pengeluaran tidak ditemukan.');
            if (!empty($row['debt_id'])) throw new InvalidArgumentException('Pembayaran hutang hanya dapat dikelola dari modul Hutang.');
            if ($row['status'] === 'rejected') throw new InvalidArgumentException('Pengeluaran yang ditolak bersifat final dan tidak dapat diubah.');
            if (!in_array($row['status'], array('pending','verified'), TRUE)) throw new RuntimeException('Status pengeluaran tidak konsisten.');
            if ($row['status'] === 'verified' && !$canEditVerified) {
                throw new InvalidArgumentException('Pengeluaran terverifikasi hanya dapat diubah oleh pengguna yang berhak memverifikasi.');
            }
            if ($expectedUpdatedAt !== NULL && (string)$expectedUpdatedAt !== (string)$row['updated_at']) {
                throw new InvalidArgumentException('Data pengeluaran sudah berubah. Tutup modal, muat ulang daftar, lalu buka Edit kembali.');
            }
            // Lock event rows in deterministic order so two concurrent edits
            // moving expenses between events cannot deadlock each other.
            $eventIdsToLock = array_values(array_unique(array((int)$row['event_id'], $eventId)));
            sort($eventIdsToLock, SORT_NUMERIC);
            $lockedEvents = array();
            foreach ($eventIdsToLock as $lockedEventId) {
                $lockedEvents[$lockedEventId] = $this->db->query('SELECT id,status FROM training_events WHERE id=? FOR UPDATE', array($lockedEventId))->row_array();
            }
            $originalEvent = isset($lockedEvents[(int)$row['event_id']]) ? $lockedEvents[(int)$row['event_id']] : NULL;
            if (!$originalEvent || $originalEvent['status'] !== 'open') throw new InvalidArgumentException('Pengeluaran pada event yang sudah ditutup tidak dapat diubah.');
            $event = isset($lockedEvents[$eventId]) ? $lockedEvents[$eventId] : NULL;
            if (!$event || $event['status'] !== 'open') throw new InvalidArgumentException('Event sudah tidak aktif. Pengeluaran tidak dapat diubah.');
            $category = $this->db->query('SELECT id,is_active FROM expense_categories WHERE id=? FOR UPDATE', array($categoryId))->row_array();
            if (!$category || !(int)$category['is_active']) throw new InvalidArgumentException('Kategori pengeluaran tidak aktif.');

            if ($proofPath !== NULL) {
                $duplicateProof = $this->db->where('proof_path', $proofPath)->where('id !=', $id)->count_all_results('expenses');
                if ($duplicateProof) throw new InvalidArgumentException('Bukti pengeluaran sudah digunakan oleh transaksi lain.');
            }

            $oldAccountId = (int) $row['account_id'];
            $accounts = $this->lock_transfer_accounts($oldAccountId, $accountId);
            if (!isset($accounts[$oldAccountId]) || !isset($accounts[$accountId])) throw new InvalidArgumentException('Akun dana tidak ditemukan.');
            $newAccount = $accounts[$accountId];
            if (!(int)$newAccount['is_active'] || !$this->account_matches_method($newAccount['type'], $method)) {
                throw new InvalidArgumentException('Akun dana tidak aktif atau tidak sesuai dengan metode pembayaran.');
            }

            $ledgerRows = $this->db->where(array('source_type'=>'expense','source_id'=>$id))->get('ledger_entries')->result_array();
            if ($row['status'] === 'verified') {
                $oldAmountCents = $this->money_cents($row['amount']);
                $oldFeeCents = $this->money_cents($row['admin_fee']);
                if ($oldAmountCents === NULL || $oldFeeCents === NULL || count($ledgerRows) !== 1 ||
                    (int)$ledgerRows[0]['account_id'] !== $oldAccountId || $ledgerRows[0]['direction'] !== 'out' ||
                    $this->money_cents($ledgerRows[0]['amount']) !== $oldAmountCents + $oldFeeCents) {
                    throw new RuntimeException('Jurnal pengeluaran tidak konsisten sehingga data belum dapat diubah.');
                }
                $availableCents = $this->locked_account_balance_cents($newAccount);
                if ($availableCents === NULL) throw new RuntimeException('Saldo akun dana tidak valid.');
                if ($oldAccountId === $accountId) $availableCents += $oldAmountCents + $oldFeeCents;
                if ($availableCents < $amountCents + $feeCents) throw new InvalidArgumentException('Saldo akun tidak mencukupi untuk perubahan pengeluaran beserta biaya admin.');
            } elseif ($ledgerRows) {
                throw new RuntimeException('Pengeluaran yang menunggu verifikasi memiliki jurnal yang tidak semestinya.');
            }

            if (!$this->db->where(array('source_type'=>'expense','source_id'=>$id))->delete('ledger_entries')) throw new RuntimeException('Jurnal lama pengeluaran gagal diperbarui.');
            $update = array(
                'event_id'=>$eventId, 'category_id'=>$categoryId,
                'expense_date'=>$data['expense_date'], 'payee'=>'Pengeluaran Event',
                'description'=>$description, 'amount'=>$this->cents_to_decimal($amountCents),
                'method'=>$method, 'account_id'=>$accountId,
                'admin_fee'=>$this->cents_to_decimal($feeCents), 'proof_path'=>$proofPath,
                'note'=>$note !== '' ? $note : NULL, 'updated_at'=>date('Y-m-d H:i:s')
            );
            if (!$this->db->where('id', $id)->update('expenses', $update)) throw new RuntimeException('Perubahan pengeluaran gagal disimpan.');

            if ($row['status'] === 'verified') {
                $update['verified_by'] = (int)$userId;
                $update['verified_at'] = date('Y-m-d H:i:s');
                if (!$this->db->where('id', $id)->update('expenses', array('verified_by'=>$update['verified_by'], 'verified_at'=>$update['verified_at']))) throw new RuntimeException('Informasi verifikasi pengeluaran gagal diperbarui.');
                $ledgerData = array_merge($row, $update, array('status'=>'verified'));
                if (!$this->post_expense_ledger($id, $ledgerData, $userId)) throw new RuntimeException('Jurnal perubahan pengeluaran gagal disimpan.');
            }
            if ($this->db->trans_status() === FALSE) throw new RuntimeException('Transaksi perubahan pengeluaran gagal.');
            if (!$this->db->trans_commit()) throw new RuntimeException('Transaksi perubahan pengeluaran gagal diselesaikan.');
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        } finally {
            $this->db->db_debug = $originalDbDebug;
        }
    }

    /**
     * Delete a regular event expense and its journal atomically. Verified
     * expenses may only be deleted by a verifier; debt repayments remain
     * owned by Debt_model and cannot enter this path.
     */
    public function delete_expense($id, $canDeleteVerified = FALSE)
    {
        $id = (int)$id;
        if ($id < 1) throw new InvalidArgumentException('Pengeluaran tidak valid.');

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $row = $this->db->query('SELECT * FROM expenses WHERE id=? FOR UPDATE', array($id))->row_array();
            if (!$row) throw new InvalidArgumentException('Pengeluaran tidak ditemukan.');
            if (!empty($row['debt_id'])) throw new InvalidArgumentException('Pembayaran hutang hanya dapat dikelola dari modul Hutang.');
            if ($row['status'] === 'rejected') throw new InvalidArgumentException('Pengeluaran yang ditolak bersifat final dan tidak dapat dihapus.');
            if (!in_array($row['status'], array('pending','verified'), TRUE)) throw new RuntimeException('Status pengeluaran tidak konsisten.');
            if ($row['status'] === 'verified' && !$canDeleteVerified) {
                throw new InvalidArgumentException('Pengeluaran terverifikasi hanya dapat dihapus oleh pengguna yang berhak memverifikasi.');
            }

            $event = $this->db->query('SELECT id,status FROM training_events WHERE id=? FOR UPDATE', array((int)$row['event_id']))->row_array();
            if (!$event || $event['status'] !== 'open') throw new InvalidArgumentException('Pengeluaran pada event yang sudah ditutup tidak dapat dihapus.');

            // Lock the source account before reversing a verified journal so
            // concurrent balance writers cannot observe a partial deletion.
            $account = $this->lock_account((int)$row['account_id']);
            if (!$account) throw new RuntimeException('Akun dana pengeluaran tidak ditemukan.');
            if ($row['status'] === 'verified' && !(int)$account['is_active']) {
                throw new InvalidArgumentException('Pengeluaran tidak dapat dihapus saat akun sumber nonaktif. Aktifkan kembali akun tersebut terlebih dahulu.');
            }

            $ledgerRows = $this->db->query(
                'SELECT * FROM ledger_entries WHERE source_type=? AND source_id=? FOR UPDATE',
                array('expense', $id)
            )->result_array();
            if ($row['status'] === 'verified') {
                $amountCents = $this->money_cents($row['amount']);
                $feeCents = $this->money_cents($row['admin_fee']);
                if ($amountCents === NULL || $feeCents === NULL || count($ledgerRows) !== 1 ||
                    (int)$ledgerRows[0]['account_id'] !== (int)$row['account_id'] ||
                    $ledgerRows[0]['direction'] !== 'out' ||
                    $this->money_cents($ledgerRows[0]['amount']) !== $amountCents + $feeCents) {
                    throw new RuntimeException('Jurnal pengeluaran tidak konsisten sehingga data belum dapat dihapus.');
                }
            } elseif ($ledgerRows) {
                throw new RuntimeException('Pengeluaran yang menunggu verifikasi memiliki jurnal yang tidak semestinya.');
            }

            if ($ledgerRows) {
                if (!$this->db->where(array('source_type'=>'expense','source_id'=>$id))->delete('ledger_entries') ||
                    $this->db->affected_rows() !== count($ledgerRows)) {
                    throw new RuntimeException('Jurnal pengeluaran gagal dibatalkan.');
                }
            }
            if (!$this->db->where('id', $id)->delete('expenses') || $this->db->affected_rows() !== 1) {
                throw new RuntimeException('Pengeluaran gagal dihapus.');
            }
            if ($this->db->trans_status() === FALSE) throw new RuntimeException('Transaksi penghapusan pengeluaran gagal.');
            if (!$this->db->trans_commit()) throw new RuntimeException('Transaksi penghapusan pengeluaran gagal diselesaikan.');
            return $row;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        } finally {
            $this->db->db_debug = $originalDbDebug;
        }
    }

    /**
     * @deprecated Debt repayments are owned by Debt_model. Keep this shim for
     * older integrations, but do not maintain a second accounting path here.
     */
    public function create_debt_payment(array $data, $userId)
    {
        $debtId = isset($data['debt_id']) && is_scalar($data['debt_id']) ? (int) $data['debt_id'] : 0;
        if ($debtId < 1) throw new InvalidArgumentException('Hutang yang dibayar tidak valid.');
        // The retired Finance_model API called this field expense_date;
        // translate it for Debt_model so older integrations fail safely
        // without maintaining a second bookkeeping implementation.
        if (!isset($data['payment_date']) && isset($data['expense_date'])) {
            $data['payment_date'] = $data['expense_date'];
        }
        $this->load->model('Debt_model', 'debt');
        $result = $this->debt->create_payment($debtId, $data, $userId);
        return is_array($result) && array_key_exists('expense_id', $result)
            ? (int) $result['expense_id']
            : $result;
    }

    public function set_expense_status($id, $status, $userId)
    {
        $id = (int) $id;
        $status = (string) $status;
        $this->db->trans_begin();
        $row = $this->db->query('SELECT * FROM expenses WHERE id=? FOR UPDATE', array($id))->row_array();
        if (!$row || !in_array($status, array('pending','verified','rejected'), TRUE)) {
            $this->db->trans_rollback(); return FALSE;
        }
        if ($row['status'] === $status) {
            if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi status pengeluaran gagal diselesaikan.'); }
            return TRUE;
        }
        if ($row['status'] === 'rejected') {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Pengeluaran yang ditolak bersifat final. Catat pengeluaran baru jika diperlukan.');
        }
        if ($row['status'] === 'verified' && $status === 'pending') {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Pengeluaran terverifikasi tidak dapat dikembalikan ke status menunggu. Gunakan status Ditolak untuk membalik transaksi.');
        }
        // Debt repayments share the debt lock with the payment endpoint. This
        // keeps the derived debt status and remaining amount consistent while
        // a pending/verified payment is being reviewed.
        if (!empty($row['debt_id'])) {
            $debtLock = $this->db->query('SELECT id,status FROM company_debts WHERE id=? FOR UPDATE', array((int)$row['debt_id']))->row_array();
            if (!$debtLock) { $this->db->trans_rollback(); return FALSE; }
            if ($debtLock['status'] === 'cancelled' && $status === 'verified') {
                $this->db->trans_rollback();
                throw new InvalidArgumentException('Hutang sudah dibatalkan sehingga pembayarannya tidak dapat diverifikasi.');
            }
            if ($row['status'] === 'rejected' && $status !== 'rejected') {
                $this->db->trans_rollback();
                throw new InvalidArgumentException('Pembayaran hutang yang ditolak tidak dapat dibuka kembali. Catat pembayaran baru.');
            }
        }
        // Lock before touching the ledger so account edits and every other
        // balance mutation serialize with this status transition.
        $account = $this->lock_account((int) $row['account_id']);
        if (!$account) {
            $this->db->trans_rollback(); return FALSE;
        }
        $amountCents = $this->money_cents($row['amount']);
        $feeCents = $this->money_cents($row['admin_fee']);
        $proofFolder = !empty($row['debt_id']) ? 'debts' : 'expenses';
        if ($status === 'verified' && (!$this->valid_date($row['expense_date']) ||
            !in_array((string)$row['method'], array('cash','transfer','qris'), TRUE) ||
            ((string)$row['method'] !== 'transfer' && $feeCents !== NULL && $feeCents > 0) ||
            (!empty($row['proof_path']) && !$this->valid_upload_path($row['proof_path'], $proofFolder)) ||
            ((string)$row['method'] !== 'cash' && !$this->valid_upload_path($row['proof_path'], $proofFolder)) ||
            (!empty($row['debt_id']) && ((string)$row['method'] !== 'cash' || ($feeCents !== NULL && $feeCents > 0))))) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Data metode, tanggal, biaya, atau bukti pengeluaran tidak valid.');
        }
        $balanceCents = $this->locked_account_balance_cents($account);
        $ledgerNetCents = $this->ledger_net_cents('expense', $id, (int) $account['id']);
        if ($amountCents === NULL || $feeCents === NULL || $balanceCents === NULL || $ledgerNetCents === NULL) {
            $this->db->trans_rollback();
            throw new RuntimeException('Nominal pengeluaran atau jurnal tidak valid.');
        }
        // Removing the old journal is equivalent to subtracting its signed
        // net movement from the current balance.  Calculate this before the
        // delete so a failed transition cannot leave an account overdrawn.
        $balanceAfterLedgerRemoval = $balanceCents - $ledgerNetCents;
        if ($row['status'] === 'verified' && $status !== 'verified' && !(int) $account['is_active']) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Pengeluaran tidak dapat dibatalkan saat akun sumber nonaktif. Aktifkan kembali akun tersebut terlebih dahulu.');
        }
        if ($row['status'] === 'verified' && $status !== 'verified' && $balanceAfterLedgerRemoval < 0) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Pengeluaran tidak dapat dibatalkan karena saldo akun setelah pembalikan menjadi negatif.');
        }
        if ($status === 'verified' && (!(int) $account['is_active'] ||
            !$this->account_matches_method($account['type'], $row['method']) ||
            $balanceAfterLedgerRemoval < $amountCents + $feeCents)) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Akun dana tidak aktif, tidak sesuai metode, atau saldonya tidak mencukupi untuk pengeluaran beserta biaya admin.');
        }
        if (!$this->db->where(array('source_type' => 'expense', 'source_id' => (int) $id))->delete('ledger_entries')) {
            $this->db->trans_rollback(); return FALSE;
        }
        $update = array('status' => $status, 'updated_at' => date('Y-m-d H:i:s'),
            'verified_by' => NULL, 'verified_at' => NULL);
        if ($status === 'verified') {
            $update['verified_by'] = (int) $userId;
            $update['verified_at'] = date('Y-m-d H:i:s');
        }
        if (!$this->db->where('id', $id)->update('expenses', $update)) {
            $this->db->trans_rollback(); return FALSE;
        }
        if ($status === 'verified' && !$this->post_expense_ledger($id, $row, $userId)) {
            $this->db->trans_rollback(); return FALSE;
        }
        if (!empty($row['debt_id'])) $this->refresh_debt_status_locked((int)$row['debt_id']);
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); return FALSE; }
        if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi status pengeluaran gagal diselesaikan.'); }
        return TRUE;
    }

    /** Recalculate cached debt status while the caller owns the debt row lock. */
    private function refresh_debt_status_locked($debtId)
    {
        $debt = $this->db->query('SELECT id,status,principal_amount,settled_at FROM company_debts WHERE id=? FOR UPDATE', array((int)$debtId))->row_array();
        if (!$debt) return FALSE;
        if ($debt['status'] === 'cancelled') return TRUE;
        $paid = $this->db->select("COALESCE(SUM(amount),0) amount", FALSE)
            ->where('debt_id',(int)$debtId)->where('status','verified')->get('expenses')->row_array();
        $paidCents = $this->money_cents(isset($paid['amount']) ? $paid['amount'] : '0');
        $principalCents = $this->money_cents($debt['principal_amount']);
        if ($paidCents === NULL || $principalCents === NULL) return FALSE;
        $newStatus = $paidCents >= $principalCents ? 'paid' : 'open';
        $update = array('status'=>$newStatus,'updated_at'=>date('Y-m-d H:i:s'));
        if ($newStatus === 'paid') $update['settled_at'] = !empty($debt['settled_at']) ? $debt['settled_at'] : date('Y-m-d H:i:s');
        else $update['settled_at'] = NULL;
        return (bool)$this->db->where('id',(int)$debtId)->update('company_debts',$update);
    }

    private function post_expense_ledger($id, array $row, $userId)
    {
        $amountCents = $this->money_cents(isset($row['amount']) ? $row['amount'] : NULL);
        $feeCents = $this->money_cents(isset($row['admin_fee']) ? $row['admin_fee'] : '0');
        if ($amountCents === NULL || $feeCents === NULL || $amountCents <= 0) return FALSE;
        $total = $this->cents_to_decimal($amountCents + $feeCents);
        return $this->db->insert('ledger_entries', array(
            'account_id' => (int) $row['account_id'], 'entry_date' => $row['expense_date'],
            'direction' => 'out', 'amount' => $total, 'source_type' => 'expense', 'source_id' => (int) $id,
            'description' => substr('Pengeluaran ' . (isset($row['expense_no']) ? $row['expense_no'] : '') . ' - ' . $row['description'], 0, 255),
            'created_by' => (int) $userId, 'created_at' => date('Y-m-d H:i:s')
        ));
    }

    public function transfers(array $filters = array())
    {
        $this->db->select('t.*,fa.name from_account_name,ta.name to_account_name,u.name created_by_name')
            ->from('fund_transfers t')->join('fund_accounts fa', 'fa.id=t.from_account_id')
            ->join('fund_accounts ta', 'ta.id=t.to_account_id')->join('users u', 'u.id=t.created_by', 'left')
            ->order_by('t.transfer_date', 'DESC')->order_by('t.id', 'DESC');
        if (!empty($filters['date_from'])) $this->db->where('t.transfer_date >=', $filters['date_from']);
        if (!empty($filters['date_to'])) $this->db->where('t.transfer_date <=', $filters['date_to']);
        if (!empty($filters['status'])) $this->db->where('t.status', $filters['status']);
        return $this->db->get()->result_array();
    }

    public function transfer($id)
    {
        return $this->db->where('id', (int) $id)->get('fund_transfers')->row_array();
    }

    public function create_transfer(array $data, $userId)
    {
        $fromId = isset($data['from_account_id']) ? (int) $data['from_account_id'] : 0;
        $toId = isset($data['to_account_id']) ? (int) $data['to_account_id'] : 0;
        if ($fromId < 1 || $toId < 1 || $fromId === $toId) return FALSE;
        $amountCents = $this->money_cents(isset($data['amount']) ? $data['amount'] : NULL);
        $feeCents = $this->money_cents(isset($data['admin_fee']) ? $data['admin_fee'] : '0');
        if ($amountCents === NULL || $amountCents <= 0) {
            throw new InvalidArgumentException('Nominal transfer harus lebih dari nol dengan maksimal 2 angka desimal.');
        }
        if ($feeCents === NULL) throw new InvalidArgumentException('Biaya admin transfer tidak valid.');
        if (!$this->valid_date(isset($data['transfer_date']) ? $data['transfer_date'] : NULL)) {
            throw new InvalidArgumentException('Tanggal transfer tidak valid.');
        }
        if (!$this->valid_upload_path(isset($data['proof_path']) ? $data['proof_path'] : NULL, 'transfers')) {
            throw new InvalidArgumentException('Bukti transfer wajib diunggah.');
        }
        if ($this->db->where('proof_path', $data['proof_path'])->count_all_results('fund_transfers')) {
            throw new InvalidArgumentException('Bukti transfer sudah digunakan oleh transaksi lain.');
        }
        if (empty($data['status']) || !in_array((string) $data['status'], array('pending', 'verified', 'rejected'), TRUE)) {
            throw new InvalidArgumentException('Status transfer tidak valid.');
        }
        $data['from_account_id'] = $fromId;
        $data['to_account_id'] = $toId;
        $data['amount'] = $this->cents_to_decimal($amountCents);
        $data['admin_fee'] = $this->cents_to_decimal($feeCents);
        $data['transfer_no'] = $this->document_number('transfer_prefix', 'TRF');
        $data['created_by'] = (int) $userId;
        $data['created_at'] = date('Y-m-d H:i:s'); $data['updated_at'] = $data['created_at'];
        if ($data['status'] === 'verified') { $data['verified_by'] = (int) $userId; $data['verified_at'] = $data['created_at']; }
        $this->db->trans_begin();
        $accounts = $this->lock_transfer_accounts($fromId, $toId);
        if (count($accounts) !== 2 || !(int) $accounts[$fromId]['is_active'] || !(int) $accounts[$toId]['is_active']) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Akun sumber atau tujuan tidak aktif.');
        }
        $balanceCents = $this->locked_account_balance_cents($accounts[$fromId]);
        if ($data['status'] === 'verified' && ($balanceCents === NULL || $balanceCents < $amountCents + $feeCents)) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Saldo akun sumber tidak mencukupi untuk transfer beserta biaya admin.');
        }
        if (!$this->db->insert('fund_transfers', $data)) { $this->db->trans_rollback(); return FALSE; }
        $id = (int) $this->db->insert_id();
        if ($data['status'] === 'verified' && !$this->post_transfer_ledger($id, $data, $userId)) {
            $this->db->trans_rollback(); return FALSE;
        }
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); return FALSE; }
        if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi transfer gagal diselesaikan.'); }
        return $id;
    }

    public function set_transfer_status($id, $status, $userId)
    {
        $id = (int) $id;
        $status = (string) $status;
        $this->db->trans_begin();
        $row = $this->db->query('SELECT * FROM fund_transfers WHERE id=? FOR UPDATE', array($id))->row_array();
        if (!$row || !in_array($status, array('pending','verified','rejected'), TRUE)) {
            $this->db->trans_rollback(); return FALSE;
        }
        // A repeated submission is a successful no-op. In particular, never
        // tear down and recreate the ledger for an unchanged verified status.
        if ($row['status'] === $status) {
            if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi status transfer gagal diselesaikan.'); }
            return TRUE;
        }
        if ($row['status'] === 'rejected') {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Transfer yang ditolak bersifat final. Catat transfer baru jika diperlukan.');
        }
        if ($row['status'] === 'verified' && $status === 'pending') {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Transfer terverifikasi tidak dapat dikembalikan ke status menunggu. Gunakan status Ditolak untuk membalik transaksi.');
        }

        // Lock both accounts in deterministic id order before reading balances
        // or deleting ledger rows. This serializes the reversal with payments,
        // expenses, and other transfers that touch either account.
        $fromId = (int) $row['from_account_id'];
        $toId = (int) $row['to_account_id'];
        $amountCents = $this->money_cents($row['amount']);
        $feeCents = $this->money_cents($row['admin_fee']);
        if ($status === 'verified' && (!$this->valid_date($row['transfer_date']) ||
            !$this->valid_upload_path(isset($row['proof_path']) ? $row['proof_path'] : NULL, 'transfers'))) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Tanggal atau bukti transfer tidak valid.');
        }
        if ($amountCents === NULL || $feeCents === NULL || $amountCents <= 0) {
            $this->db->trans_rollback();
            throw new RuntimeException('Nominal transfer tidak valid.');
        }
        $accounts = $this->lock_transfer_accounts($fromId, $toId);
        if (count($accounts) !== 2) {
            $this->db->trans_rollback(); return FALSE;
        }

        $source = $accounts[$fromId];
        $destination = $accounts[$toId];
        $sourceBalanceCents = $this->locked_account_balance_cents($source);
        $destinationBalanceCents = $this->locked_account_balance_cents($destination);
        $sourceLedgerNetCents = $this->ledger_net_cents('transfer', $id, $fromId);
        $destinationLedgerNetCents = $this->ledger_net_cents('transfer', $id, $toId);
        if ($sourceBalanceCents === NULL || $destinationBalanceCents === NULL ||
            $sourceLedgerNetCents === NULL || $destinationLedgerNetCents === NULL) {
            $this->db->trans_rollback();
            throw new RuntimeException('Saldo akun atau jurnal transfer tidak valid.');
        }
        $sourceAfterLedgerRemoval = $sourceBalanceCents - $sourceLedgerNetCents;
        $destinationAfterLedgerRemoval = $destinationBalanceCents - $destinationLedgerNetCents;

        if ($row['status'] === 'verified' && $status !== 'verified') {
            // The destination balance still contains the transfer credit. It
            // must be able to return the principal before those ledger rows are
            // removed, otherwise the reversal would create a negative balance.
            if (!(int) $source['is_active']) {
                $this->db->trans_rollback();
                throw new InvalidArgumentException('Transfer tidak dapat dibatalkan saat akun sumber nonaktif. Aktifkan kembali akun tersebut terlebih dahulu.');
            }
            if ($destinationBalanceCents < $amountCents || $destinationAfterLedgerRemoval < 0) {
                $this->db->trans_rollback();
                throw new InvalidArgumentException('Transfer tidak dapat dibatalkan karena dana pada akun tujuan sudah terpakai dan saldonya tidak mencukupi.');
            }
        }

        if (!$this->db->where(array('source_type' => 'transfer', 'source_id' => $id))->delete('ledger_entries')) {
            $this->db->trans_rollback(); return FALSE;
        }
        $required = $amountCents + $feeCents;
        if ($status === 'verified' &&
            (!(int) $source['is_active'] || !(int) $destination['is_active'] || $sourceAfterLedgerRemoval < $required)) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Akun transfer tidak aktif atau saldo akun sumber tidak mencukupi.');
        }
        $update = array('status' => $status, 'updated_at' => date('Y-m-d H:i:s'), 'verified_by' => NULL, 'verified_at' => NULL);
        if ($status === 'verified') { $update['verified_by'] = (int) $userId; $update['verified_at'] = date('Y-m-d H:i:s'); }
        if (!$this->db->where('id', $id)->update('fund_transfers', $update)) { $this->db->trans_rollback(); return FALSE; }
        if ($status === 'verified' && !$this->post_transfer_ledger($id, $row, $userId)) { $this->db->trans_rollback(); return FALSE; }
        if ($this->db->trans_status() === FALSE) { $this->db->trans_rollback(); return FALSE; }
        if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi status transfer gagal diselesaikan.'); }
        return TRUE;
    }

    private function post_transfer_ledger($id, array $row, $userId)
    {
        $amountCents = $this->money_cents(isset($row['amount']) ? $row['amount'] : NULL);
        $feeCents = $this->money_cents(isset($row['admin_fee']) ? $row['admin_fee'] : '0');
        if ($amountCents === NULL || $feeCents === NULL || $amountCents <= 0 ||
            (int) $row['from_account_id'] === (int) $row['to_account_id']) return FALSE;
        $amount = $this->cents_to_decimal($amountCents);
        $total = $this->cents_to_decimal($amountCents + $feeCents);
        $description = 'Transfer ' . (isset($row['transfer_no']) ? $row['transfer_no'] : '') . (empty($row['note']) ? '' : ' - ' . $row['note']);
        $out = $this->db->insert('ledger_entries', array(
            'account_id' => (int) $row['from_account_id'], 'entry_date' => $row['transfer_date'], 'direction' => 'out',
            'amount' => $total, 'source_type' => 'transfer', 'source_id' => (int) $id,
            'description' => substr($description . ($feeCents > 0 ? ' (termasuk biaya admin)' : ''), 0, 255),
            'created_by' => (int) $userId, 'created_at' => date('Y-m-d H:i:s')
        ));
        $in = $this->db->insert('ledger_entries', array(
            'account_id' => (int) $row['to_account_id'], 'entry_date' => $row['transfer_date'], 'direction' => 'in',
            'amount' => $amount, 'source_type' => 'transfer', 'source_id' => (int) $id,
            'description' => substr($description, 0, 255), 'created_by' => (int) $userId, 'created_at' => date('Y-m-d H:i:s')
        ));
        return $out && $in;
    }

    private function lock_account($accountId)
    {
        return $this->db->query('SELECT * FROM fund_accounts WHERE id=? FOR UPDATE', array((int) $accountId))->row_array();
    }

    private function lock_transfer_accounts($fromId, $toId)
    {
        $rows = $this->db->query(
            'SELECT * FROM fund_accounts WHERE id IN (?,?) ORDER BY id FOR UPDATE',
            array((int) $fromId, (int) $toId)
        )->result_array();
        $indexed = array();
        foreach ($rows as $row) $indexed[(int) $row['id']] = $row;
        return $indexed;
    }

    /**
     * Return an account balance in integer cents.  Database DECIMAL values
     * are parsed as strings; no floating point arithmetic is used for any
     * balance decision.
     */
    private function locked_account_balance_cents(array $account)
    {
        $opening = $this->signed_money_cents(isset($account['opening_balance']) ? $account['opening_balance'] : NULL);
        if ($opening === NULL) return NULL;
        $movement = $this->db->select(
            "COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) incoming, COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) outgoing",
            FALSE
        )->where('account_id', (int) $account['id'])->get('ledger_entries')->row_array();
        $incoming = $this->money_cents(isset($movement['incoming']) ? $movement['incoming'] : NULL);
        $outgoing = $this->money_cents(isset($movement['outgoing']) ? $movement['outgoing'] : NULL);
        if ($incoming === NULL || $outgoing === NULL) return NULL;
        return $opening + $incoming - $outgoing;
    }

    /** Return signed ledger movement for one source/account in integer cents. */
    private function ledger_net_cents($sourceType, $sourceId, $accountId = NULL)
    {
        $this->db->select('direction,amount')->from('ledger_entries')
            ->where(array('source_type' => (string) $sourceType, 'source_id' => (int) $sourceId));
        if ($accountId !== NULL) $this->db->where('account_id', (int) $accountId);
        $rows = $this->db->get()->result_array();
        $net = 0;
        foreach ($rows as $entry) {
            $amount = $this->money_cents($entry['amount']);
            if ($amount === NULL || $amount <= 0) return NULL;
            $net += $entry['direction'] === 'in' ? $amount : -$amount;
        }
        return $net;
    }

    private function account_matches_method($accountType, $method)
    {
        $allowed = array('cash' => array('cash'), 'transfer' => array('bank','personal'), 'qris' => array('qris'));
        return isset($allowed[$method]) && in_array($accountType, $allowed[$method], TRUE);
    }

    /**
     * Accept only paths created by the private upload controller.  Keeping
     * this check in the model prevents a direct integration call from storing
     * an arbitrary filesystem path or URL in a transaction row.
     */
    private function valid_upload_path($path, $folder)
    {
        if (!is_scalar($path)) return FALSE;
        $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        $folder = trim(str_replace('\\', '/', (string)$folder), '/');
        $prefix = 'uploads/' . $folder . '/';
        if ($folder === '' || $path === '' || strpos($path, "\0") !== FALSE ||
            strpos($path, '..') !== FALSE || strpos($path, $prefix) !== 0) return FALSE;
        $root = realpath(FCPATH . 'uploads/' . $folder);
        $candidate = realpath(FCPATH . $path);
        return $root !== FALSE && $candidate !== FALSE && is_file($candidate) &&
            strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0;
    }

    private function valid_date($value)
    {
        if (!is_scalar($value)) return FALSE;
        $value = (string) $value;
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();
        return $date instanceof DateTime &&
            ($errors === FALSE || (empty($errors['warning_count']) && empty($errors['error_count']))) &&
            $date->format('Y-m-d') === $value;
    }

    /**
     * Parse a non-negative DECIMAL(18,2) value without using a float.
     * Returning NULL distinguishes malformed input from the valid value 0.
     */
    private function money_cents($value)
    {
        if (!is_scalar($value)) return NULL;
        $value = trim((string) $value);
        if (!preg_match('/^\d{1,16}(?:\.\d{1,2})?$/D', $value)) return NULL;
        $parts = explode('.', $value, 2);
        $major = ltrim($parts[0], '0');
        if ($major === '') $major = '0';
        // DECIMAL(18,2) has at most 16 integer digits.  On supported PHP
        // builds this remains safely representable after multiplying by 100.
        if (strlen($major) > 16 || (int) $major > intdiv(PHP_INT_MAX, 100)) return NULL;
        $minor = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';
        return ((int) $major * 100) + (int) $minor;
    }

    /** Parse an account opening balance, allowing an existing negative value to be read. */
    private function signed_money_cents($value)
    {
        if (!is_scalar($value)) return NULL;
        $value = trim((string) $value);
        $negative = FALSE;
        if (isset($value[0]) && $value[0] === '-') {
            $negative = TRUE;
            $value = substr($value, 1);
        }
        $cents = $this->money_cents($value);
        if ($cents === NULL) return NULL;
        return $negative ? -$cents : $cents;
    }

    private function cents_to_decimal($cents)
    {
        $cents = (int) $cents;
        $negative = $cents < 0;
        $cents = abs($cents);
        return ($negative ? '-' : '') . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Return the district/village choices that actually have an active
     * registration on one of the supplied open events.  Region names are read
     * from the registration snapshot, so the report remains independent from
     * the master-region database connection.
     */
    public function payment_data_filter_options(array $eventIds)
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), function ($id) {
            return $id > 0;
        })));
        if (!$eventIds) return array('districts' => array(), 'villages' => array());

        $rows = $this->db
            ->distinct()
            ->select('r.district_id,r.district_name,r.village_id,r.village_name,r.regency_name')
            ->from('registrations r')
            ->join('training_events e', 'e.id=r.event_id')
            ->where_in('r.event_id', $eventIds)
            ->where('r.status', 'active')
            ->where('e.status', 'open')
            ->order_by('r.district_name', 'ASC')
            ->order_by('r.village_name', 'ASC')
            ->get()->result_array();

        $districts = array();
        $villages = array();
        foreach ($rows as $row) {
            $districtId = trim((string) $row['district_id']);
            $villageId = trim((string) $row['village_id']);
            if ($districtId !== '' && !isset($districts[$districtId])) {
                $districts[$districtId] = array(
                    'id' => $districtId,
                    'name' => (string) $row['district_name'],
                    'regency_name' => (string) $row['regency_name']
                );
            }
            if ($districtId !== '' && $villageId !== '' && !isset($villages[$villageId])) {
                $villages[$villageId] = array(
                    'id' => $villageId,
                    'name' => (string) $row['village_name'],
                    'district_id' => $districtId,
                    'district_name' => (string) $row['district_name'],
                    'regency_name' => (string) $row['regency_name']
                );
            }
        }

        return array('districts' => array_values($districts), 'villages' => array_values($villages));
    }

    /**
     * Payment-status report, one row per active village registration.
     * Verified and pending funds are kept separate: only verified payments
     * reduce the outstanding invoice, while pending payments remain visible
     * for follow-up and verification.
     */
    public function payment_data_report(array $filters)
    {
        $eventIds = isset($filters['event_ids']) && is_array($filters['event_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $filters['event_ids']), function ($id) { return $id > 0; })))
            : array();
        $districtId = isset($filters['district_id']) && is_scalar($filters['district_id'])
            ? trim((string) $filters['district_id']) : '';
        $villageId = isset($filters['village_id']) && is_scalar($filters['village_id'])
            ? trim((string) $filters['village_id']) : '';

        $select = "r.id AS registration_id,r.event_id,r.province_id,r.province_name,r.regency_id,r.regency_name,
            r.district_id,r.district_name,r.village_id,r.village_name,r.expected_amount AS due_amount,
            e.code AS event_code,e.name AS event_name,e.start_date,e.end_date,e.location,e.billing_mode,
            (SELECT COUNT(*) FROM payments py WHERE py.registration_id=r.id AND py.status='verified') AS verified_payment_count,
            (SELECT COUNT(*) FROM payments py WHERE py.registration_id=r.id AND py.status='pending') AS pending_payment_count,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status='verified') AS verified_amount,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status='pending') AS pending_amount,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status='verified' AND py.method='cash') AS cash_total,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status='verified' AND py.method='transfer') AS transfer_total,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status='verified' AND py.method='qris') AS qris_total";

        $this->db->select($select, FALSE)
            ->from('registrations r')
            ->join('training_events e', 'e.id=r.event_id')
            ->where('r.status', 'active')
            ->where('e.status', 'open');
        $this->apply_event_scope('r.event_id', $eventIds, TRUE);
        if ($districtId !== '') $this->db->where('r.district_id', $districtId);
        if ($villageId !== '') $this->db->where('r.village_id', $villageId);

        $rows = $this->db
            ->order_by('r.village_name', 'ASC')
            ->order_by('r.district_name', 'ASC')
            ->order_by('e.start_date', 'DESC')
            ->order_by('e.id', 'DESC')
            ->get()->result_array();

        // Load participant names in one additional query. Joining participants
        // into the payment query would multiply invoice/payment aggregates,
        // while GROUP_CONCAT can silently truncate longer village rosters.
        $participantNames = array();
        $registrationIds = array_values(array_unique(array_map(function ($row) {
            return (int) $row['registration_id'];
        }, $rows)));
        if ($registrationIds) {
            $participants = $this->db
                ->select('registration_id,id,full_name')
                ->from('participants')
                ->where_in('registration_id', $registrationIds)
                ->where('is_active', 1)
                ->where('deleted_at IS NULL', NULL, FALSE)
                ->order_by('registration_id', 'ASC')
                ->order_by('full_name', 'ASC')
                ->order_by('id', 'ASC')
                ->get()->result_array();
            foreach ($participants as $participant) {
                $registrationId = (int) $participant['registration_id'];
                if (!isset($participantNames[$registrationId])) $participantNames[$registrationId] = array();
                $participantNames[$registrationId][] = (string) $participant['full_name'];
            }
        }

        $summaryCents = array(
            'total_due' => 0, 'verified' => 0, 'pending' => 0, 'outstanding' => 0,
            'cash_total' => 0, 'transfer_total' => 0, 'qris_total' => 0
        );
        $statusCounts = array('paid' => 0, 'overpaid' => 0, 'partial_pending' => 0, 'partial' => 0, 'pending' => 0, 'unpaid' => 0, 'no_charge' => 0);
        $participantCount = 0;
        $villageKeys = array();

        foreach ($rows as &$row) {
            $registrationId = (int) $row['registration_id'];
            $row['participant_names'] = isset($participantNames[$registrationId])
                ? $participantNames[$registrationId] : array();
            $row['participant_count'] = count($row['participant_names']);
            $dueCents = $this->money_cents(isset($row['due_amount']) ? $row['due_amount'] : '0');
            $verifiedCents = $this->money_cents(isset($row['verified_amount']) ? $row['verified_amount'] : '0');
            $pendingCents = $this->money_cents(isset($row['pending_amount']) ? $row['pending_amount'] : '0');
            $cashCents = $this->money_cents(isset($row['cash_total']) ? $row['cash_total'] : '0');
            $transferCents = $this->money_cents(isset($row['transfer_total']) ? $row['transfer_total'] : '0');
            $qrisCents = $this->money_cents(isset($row['qris_total']) ? $row['qris_total'] : '0');
            $dueCents = $dueCents === NULL ? 0 : $dueCents;
            $verifiedCents = $verifiedCents === NULL ? 0 : $verifiedCents;
            $pendingCents = $pendingCents === NULL ? 0 : $pendingCents;
            $cashCents = $cashCents === NULL ? 0 : $cashCents;
            $transferCents = $transferCents === NULL ? 0 : $transferCents;
            $qrisCents = $qrisCents === NULL ? 0 : $qrisCents;
            $outstandingCents = max(0, $dueCents - $verifiedCents);

            if ($dueCents <= 0) $state = 'no_charge';
            elseif ($verifiedCents > $dueCents) $state = 'overpaid';
            elseif ($verifiedCents === $dueCents) $state = 'paid';
            elseif ($verifiedCents > 0 && $pendingCents > 0) $state = 'partial_pending';
            elseif ($verifiedCents > 0) $state = 'partial';
            elseif ($pendingCents > 0) $state = 'pending';
            else $state = 'unpaid';

            $row['outstanding_amount'] = $this->cents_to_decimal($outstandingCents);
            $row['payment_state'] = $state;
            $statusCounts[$state]++;
            $participantCount += (int) $row['participant_count'];
            $villageKey = trim((string) $row['village_id']);
            if ($villageKey !== '') $villageKeys[$villageKey] = TRUE;
            $summaryCents['total_due'] += $dueCents;
            $summaryCents['verified'] += $verifiedCents;
            $summaryCents['pending'] += $pendingCents;
            $summaryCents['outstanding'] += $outstandingCents;
            $summaryCents['cash_total'] += $cashCents;
            $summaryCents['transfer_total'] += $transferCents;
            $summaryCents['qris_total'] += $qrisCents;
        }
        unset($row);

        $summary = array(
            'registrations' => count($rows),
            'villages' => count($villageKeys),
            'participants' => $participantCount,
            'total_due' => $this->cents_to_decimal($summaryCents['total_due']),
            'verified' => $this->cents_to_decimal($summaryCents['verified']),
            'pending' => $this->cents_to_decimal($summaryCents['pending']),
            // Sum each registration's remaining balance. An overpayment on one
            // village must never hide an unpaid balance on another village.
            'outstanding' => $this->cents_to_decimal($summaryCents['outstanding']),
            'cash_total' => $this->cents_to_decimal($summaryCents['cash_total']),
            'transfer_total' => $this->cents_to_decimal($summaryCents['transfer_total']),
            'qris_total' => $this->cents_to_decimal($summaryCents['qris_total']),
            'status_counts' => $statusCounts
        );

        return array('summary' => $summary, 'rows' => $rows);
    }

    public function income_report(array $filters)
    {
        $view = isset($filters['view']) && $filters['view'] === 'participant' ? 'participant' : 'village';
        $eventId = !empty($filters['event_id']) ? (int) $filters['event_id'] : 0;
        $eventIds = isset($filters['event_ids']) && is_array($filters['event_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $filters['event_ids']))))
            : ($eventId ? array($eventId) : array());
        $restrictEvents = array_key_exists('event_ids', $filters) || $eventId > 0;

        // Registration totals deliberately start from registrations, so unpaid villages
        // and participants remain visible in the report.
        $this->db->select('COUNT(r.id) villages,COALESCE(SUM(r.expected_amount),0) total_due', FALSE)
            ->from('registrations r')->where('r.status', 'active');
        $this->apply_event_scope('r.event_id', $eventIds, $restrictEvents);
        $registrationTotals = $this->db->get()->row_array();

        $this->db->select('COUNT(pt.id) participants', FALSE)->from('participants pt')
            ->join('registrations r', 'r.id=pt.registration_id')
            ->where(array('r.status' => 'active', 'pt.is_active' => 1))->where('pt.deleted_at IS NULL', NULL, FALSE);
        $this->apply_event_scope('r.event_id', $eventIds, $restrictEvents);
        $participantTotals = $this->db->get()->row_array();

        $this->db->select("COALESCE(SUM(p.amount),0) income,COALESCE(SUM(CASE WHEN p.method='cash' THEN p.amount ELSE 0 END),0) cash_total,COALESCE(SUM(CASE WHEN p.method='transfer' THEN p.amount ELSE 0 END),0) transfer_total,COALESCE(SUM(CASE WHEN p.method='qris' THEN p.amount ELSE 0 END),0) qris_total", FALSE)
            ->from('payments p')->where('p.status', 'verified');
        $this->apply_event_scope('p.event_id', $eventIds, $restrictEvents);
        if (!empty($filters['date_from'])) $this->db->where('p.payment_date >=', $filters['date_from']);
        if (!empty($filters['date_to'])) $this->db->where('p.payment_date <=', $filters['date_to']);
        $money = $this->db->get()->row_array();

        $totalDueCents = $this->money_cents(isset($registrationTotals['total_due']) ? $registrationTotals['total_due'] : '0');
        $incomeCents = $this->money_cents(isset($money['income']) ? $money['income'] : '0');
        $cashCents = $this->money_cents(isset($money['cash_total']) ? $money['cash_total'] : '0');
        $transferCents = $this->money_cents(isset($money['transfer_total']) ? $money['transfer_total'] : '0');
        $qrisCents = $this->money_cents(isset($money['qris_total']) ? $money['qris_total'] : '0');
        if ($totalDueCents === NULL) $totalDueCents = 0;
        if ($incomeCents === NULL) $incomeCents = 0;
        if ($cashCents === NULL) $cashCents = 0;
        if ($transferCents === NULL) $transferCents = 0;
        if ($qrisCents === NULL) $qrisCents = 0;
        $summary = array(
            'villages' => (int) $registrationTotals['villages'],
            'participants' => (int) $participantTotals['participants'],
            'total_due' => $this->cents_to_decimal($totalDueCents),
            'income' => $this->cents_to_decimal($incomeCents),
            'cash_total' => $this->cents_to_decimal($cashCents),
            'transfer_total' => $this->cents_to_decimal($transferCents),
            'qris_total' => $this->cents_to_decimal($qrisCents)
        );
        $summary['outstanding'] = $this->cents_to_decimal(max(0, $totalDueCents - $incomeCents));

        $paymentConditions = "py.status='verified'";
        if (!empty($filters['date_from'])) $paymentConditions .= ' AND py.payment_date >= ' . $this->db->escape($filters['date_from']);
        if (!empty($filters['date_to'])) $paymentConditions .= ' AND py.payment_date <= ' . $this->db->escape($filters['date_to']);

        if ($view === 'participant') {
            $participantPayment = $paymentConditions . ' AND py.participant_id=pt.id';
            $select = "pt.id participant_id,r.id registration_id,r.village_name,r.district_name,r.regency_name,e.name event_name,e.billing_mode,e.included_participant_count,e.participant_fee,pt.full_name participant_name,COALESCE(pt.position,'-') position,pt.expected_amount due_amount,r.expected_amount village_due," .
                "(SELECT COUNT(*) FROM payments py WHERE {$participantPayment}) payment_count," .
                "(SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE {$participantPayment}) paid," .
                "(SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE {$participantPayment} AND py.method='cash') cash_total," .
                "(SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE {$participantPayment} AND py.method='transfer') transfer_total," .
                "(SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE {$participantPayment} AND py.method='qris') qris_total";
            $this->db->select($select, FALSE)->from('participants pt')
                ->join('registrations r', 'r.id=pt.registration_id')->join('training_events e', 'e.id=r.event_id')
                ->where(array('r.status' => 'active', 'pt.is_active' => 1))->where('pt.deleted_at IS NULL', NULL, FALSE)
                ->order_by('e.start_date', 'DESC')->order_by('r.village_name')->order_by('pt.full_name');
            $this->apply_event_scope('r.event_id', $eventIds, $restrictEvents);
        } else {
            $villagePayment = $paymentConditions . ' AND py.registration_id=r.id';
            $select = "r.id registration_id,r.village_name,r.district_name,r.regency_name,e.name event_name,e.billing_mode,r.expected_amount due_amount," .
                "(SELECT COUNT(*) FROM participants pt WHERE pt.registration_id=r.id AND pt.is_active=1 AND pt.deleted_at IS NULL) participant_count," .
                "(SELECT COUNT(*) FROM payments py WHERE {$villagePayment}) payment_count," .
                "(SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE {$villagePayment}) paid," .
                "(SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE {$villagePayment} AND py.method='cash') cash_total," .
                "(SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE {$villagePayment} AND py.method='transfer') transfer_total," .
                "(SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE {$villagePayment} AND py.method='qris') qris_total";
            $this->db->select($select, FALSE)->from('registrations r')->join('training_events e', 'e.id=r.event_id')
                ->where('r.status', 'active')->order_by('e.start_date', 'DESC')->order_by('r.village_name');
            $this->apply_event_scope('r.event_id', $eventIds, $restrictEvents);
        }

        return array('summary' => $summary, 'rows' => $this->db->get()->result_array(), 'view' => $view);
    }

    /**
     * Return the verified movement that belongs to accounts marked
     * include_in_total=1.
     *
     * A transfer is not an expense simply because it leaves one account:
     * only the principal which crosses the included/excluded boundary changes
     * the included pool.  The transfer fee is an outflow whenever the source
     * account is included.  This gives the following invariant (in cents):
     *
     *   ending included balance = period opening balance + net movement
     *
     * The *_cents fields are the canonical values for arithmetic.  The
     * decimal string fields are kept for the existing dashboard/report views.
     */
    public function included_fund_flow(array $filters = array())
    {
        $from = isset($filters['date_from']) && is_scalar($filters['date_from'])
            ? trim((string) $filters['date_from']) : '';
        $to = isset($filters['date_to']) && is_scalar($filters['date_to'])
            ? trim((string) $filters['date_to']) : '';
        if ($from !== '' && !$this->valid_date($from)) {
            throw new InvalidArgumentException('Dari tanggal tidak valid.');
        }
        if ($to !== '' && !$this->valid_date($to)) {
            throw new InvalidArgumentException('Sampai tanggal tidak valid.');
        }
        if ($from !== '' && $to !== '' && $from > $to) {
            throw new InvalidArgumentException('Dari tanggal tidak boleh setelah sampai tanggal.');
        }

        $this->db->select('COALESCE(SUM(p.amount),0) total', FALSE)
            ->from('payments p')->join('fund_accounts a', 'a.id=p.account_id')
            ->where(array('p.status' => 'verified', 'a.include_in_total' => 1));
        if ($from !== '') $this->db->where('p.payment_date >=', $from);
        if ($to !== '') $this->db->where('p.payment_date <=', $to);
        $incomeCents = $this->money_cents($this->db->get()->row()->total);

        $this->db->select('COALESCE(SUM(x.amount + x.admin_fee),0) total', FALSE)
            ->from('expenses x')->join('fund_accounts a', 'a.id=x.account_id')
            ->where(array('x.status' => 'verified', 'a.include_in_total' => 1));
        if ($from !== '') $this->db->where('x.expense_date >=', $from);
        if ($to !== '') $this->db->where('x.expense_date <=', $to);
        $expenseCents = $this->money_cents($this->db->get()->row()->total);

        // Principal crossing into/out of the included pool.  Included to
        // included is an internal move (principal nets to zero), while
        // excluded to excluded is outside the pool entirely.
        $this->db->select(
            "COALESCE(SUM(CASE WHEN fa.include_in_total=1 AND ta.include_in_total=0 THEN t.amount ELSE 0 END),0) principal_out,
             COALESCE(SUM(CASE WHEN fa.include_in_total=0 AND ta.include_in_total=1 THEN t.amount ELSE 0 END),0) principal_in,
             COALESCE(SUM(CASE WHEN fa.include_in_total=1 THEN t.admin_fee ELSE 0 END),0) fees_out",
            FALSE
        )->from('fund_transfers t')
            ->join('fund_accounts fa', 'fa.id=t.from_account_id')
            ->join('fund_accounts ta', 'ta.id=t.to_account_id')
            ->where('t.status', 'verified');
        if ($from !== '') $this->db->where('t.transfer_date >=', $from);
        if ($to !== '') $this->db->where('t.transfer_date <=', $to);
        $transfer = $this->db->get()->row_array();

        $principalOutCents = $this->money_cents(isset($transfer['principal_out']) ? $transfer['principal_out'] : '0');
        $principalInCents = $this->money_cents(isset($transfer['principal_in']) ? $transfer['principal_in'] : '0');
        $feeCents = $this->money_cents(isset($transfer['fees_out']) ? $transfer['fees_out'] : '0');
        if ($incomeCents === NULL) $incomeCents = 0;
        if ($expenseCents === NULL) $expenseCents = 0;
        if ($principalOutCents === NULL) $principalOutCents = 0;
        if ($principalInCents === NULL) $principalInCents = 0;
        if ($feeCents === NULL) $feeCents = 0;

        $endingAccounts = $this->accounts(FALSE, $to !== '' ? $to : NULL);
        $endingIncludedCents = 0;
        foreach ($endingAccounts as $account) {
            if ((int) $account['include_in_total'] !== 1) continue;
            $balanceCents = $this->signed_money_cents(isset($account['balance']) ? $account['balance'] : NULL);
            if ($balanceCents !== NULL) $endingIncludedCents += $balanceCents;
        }

        // With no lower date bound the opening side is the static opening
        // balance.  For a bounded period, read the book immediately before
        // the period starts so the report can show a meaningful opening and
        // reconcile to the ending balance.
        $openingIncludedCents = 0;
        if ($from !== '') {
            $before = new DateTime($from);
            $before->modify('-1 day');
            $openingAccounts = $this->accounts(FALSE, $before->format('Y-m-d'));
            foreach ($openingAccounts as $account) {
                if ((int) $account['include_in_total'] !== 1) continue;
                $balanceCents = $this->signed_money_cents(isset($account['balance']) ? $account['balance'] : NULL);
                if ($balanceCents !== NULL) $openingIncludedCents += $balanceCents;
            }
        } else {
            foreach ($endingAccounts as $account) {
                if ((int) $account['include_in_total'] !== 1) continue;
                $balanceCents = $this->signed_money_cents(isset($account['opening_balance']) ? $account['opening_balance'] : NULL);
                if ($balanceCents !== NULL) $openingIncludedCents += $balanceCents;
            }
        }

        $outgoingCents = $expenseCents + $principalOutCents + $feeCents;
        $netCents = $incomeCents + $principalInCents - $expenseCents - $principalOutCents - $feeCents;
        $reconciledCents = $endingIncludedCents - $openingIncludedCents;

        // Keep the account list used by the existing report unchanged (active
        // accounts only), while the aggregate includes every flagged account
        // so historical/inactive accounts cannot silently break the total.
        $visibleAccounts = $this->accounts(TRUE, $to !== '' ? $to : NULL);
        return array(
            'income_cents' => $incomeCents,
            'expenses_cents' => $expenseCents,
            'transfer_fees_cents' => $feeCents,
            'transfer_in_cents' => $principalInCents,
            'transfer_out_cents' => $principalOutCents,
            'outgoing_cents' => $outgoingCents,
            'remaining_cents' => $netCents,
            'net_cents' => $netCents,
            'opening_balance_cents' => $openingIncludedCents,
            'period_opening_balance_cents' => $openingIncludedCents,
            'included_balance_cents' => $endingIncludedCents,
            'period_ending_balance_cents' => $endingIncludedCents,
            'reconciled_net_cents' => $reconciledCents,
            'income' => $this->cents_to_decimal($incomeCents),
            'expenses' => $this->cents_to_decimal($expenseCents),
            'transfer_fees' => $this->cents_to_decimal($feeCents),
            'transfer_in' => $this->cents_to_decimal($principalInCents),
            'transfer_out' => $this->cents_to_decimal($principalOutCents),
            'outgoing' => $this->cents_to_decimal($outgoingCents),
            'remaining' => $this->cents_to_decimal($netCents),
            'net' => $this->cents_to_decimal($netCents),
            'opening_balance' => $this->cents_to_decimal($openingIncludedCents),
            'period_opening_balance' => $this->cents_to_decimal($openingIncludedCents),
            'included_balance' => $this->cents_to_decimal($endingIncludedCents),
            'period_ending_balance' => $this->cents_to_decimal($endingIncludedCents),
            'reconciled_net' => $this->cents_to_decimal($reconciledCents),
            'accounts' => $visibleAccounts
        );
    }

    private function apply_event_scope($column, array $eventIds, $restrict)
    {
        if (!$restrict) return;
        if ($eventIds) $this->db->where_in($column, $eventIds);
        else $this->db->where('1=0', NULL, FALSE);
    }

    public function finance_report(array $filters)
    {
        $report = $this->included_fund_flow($filters);
        // The report template historically reads these keys.  Keep them as
        // decimal strings while exposing cents and the period reconciliation
        // fields for callers that need exact arithmetic.
        return $report;
    }

    public function ledger($accountId, $limit = 100)
    {
        return $this->db->where('account_id',(int)$accountId)->order_by('entry_date','DESC')->order_by('id','DESC')
            ->limit((int)$limit)->get('ledger_entries')->result_array();
    }

    private function apply_transaction_filters($alias, array $filters)
    {
        if (array_key_exists('event_ids', $filters) && is_array($filters['event_ids'])) {
            $eventIds = array_values(array_unique(array_filter(array_map('intval', $filters['event_ids']))));
            if ($eventIds) $this->db->where_in($alias.'.event_id', $eventIds);
            else $this->db->where('1=0', NULL, FALSE);
        } elseif (!empty($filters['event_id'])) {
            $this->db->where($alias.'.event_id',(int)$filters['event_id']);
        }
        if (!empty($filters['date_from'])) $this->db->where($alias.'.expense_date >=',$filters['date_from']);
        if (!empty($filters['date_to'])) $this->db->where($alias.'.expense_date <=',$filters['date_to']);
        if (!empty($filters['status'])) $this->db->where($alias.'.status',$filters['status']);
        if (!empty($filters['exclude_status'])) $this->db->where($alias.'.status !=',(string)$filters['exclude_status']);
        if (!empty($filters['category_id'])) $this->db->where($alias.'.category_id',(int)$filters['category_id']);
        if (isset($filters['search']) && is_scalar($filters['search'])) {
            $search = trim((string)$filters['search']);
            if ($search !== '') {
                // Search the fields people can see on an expense card.  The
                // joins in expenses() already expose each related label, so
                // this remains one database query even when the list is
                // paginated by the caller.
                $this->db->group_start()
                    ->like($alias.'.expense_no', $search)
                    ->or_like($alias.'.description', $search)
                    ->or_like($alias.'.payee', $search)
                    ->or_like($alias.'.method', $search)
                    ->or_like($alias.'.note', $search)
                    ->or_like('c.name', $search)
                    ->or_like('e.name', $search)
                    ->or_like('a.name', $search)
                    ->or_like('d.debt_no', $search)
                    ->or_like('d.creditor', $search)
                    ->group_end();
            }
        }
    }

}
