<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cash-basis debt bookkeeping.
 *
 * A row in company_debts represents a liability and does not touch a fund
 * account.  Each repayment is stored as an expenses row with debt_id.  Once
 * that expense is verified, the normal expense ledger entry is posted, so
 * account balances, cash reports, and audit trails continue to have one
 * source of truth.
 */
class Debt_model extends CI_Model
{
    public function debts(array $filters = array())
    {
        $this->db->select(
            'd.*,cat.name AS category_name,e.name AS event_name,u.name AS creator_name,' .
            'COALESCE(SUM(CASE WHEN x.status="verified" THEN x.amount ELSE 0 END),0) AS paid_amount,' .
            'COALESCE(SUM(CASE WHEN x.status="pending" THEN x.amount ELSE 0 END),0) AS pending_amount,' .
            // Only pending/verified rows are a live payment commitment.  A
            // rejected row remains audit history and must not lock debt
            // metadata or principal editing.
            'SUM(CASE WHEN x.status IN ("pending","verified") THEN 1 ELSE 0 END) AS payment_count,' .
            'MAX(CASE WHEN x.status="verified" THEN x.expense_date ELSE NULL END) AS last_payment_date',
            FALSE
        )->from('company_debts d')
            ->join('expense_categories cat', 'cat.id=d.category_id', 'left')
            ->join('training_events e', 'e.id=d.event_id', 'left')
            ->join('users u', 'u.id=d.created_by', 'left')
            ->join('expenses x', 'x.debt_id=d.id', 'left');

        if (isset($filters['status']) && is_scalar($filters['status']) && in_array((string) $filters['status'], array('open','paid','cancelled'), TRUE)) {
            $this->db->where('d.status', (string) $filters['status']);
        }
        if (!empty($filters['id']) && is_scalar($filters['id']) && ctype_digit((string) $filters['id'])) {
            $this->db->where('d.id', (int) $filters['id']);
        }
        if (!empty($filters['event_id']) && is_scalar($filters['event_id']) && ctype_digit((string) $filters['event_id'])) {
            $this->db->where('d.event_id', (int) $filters['event_id']);
        }
        if (!empty($filters['search']) && is_scalar($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $this->db->group_start()->like('d.debt_no', $search)->or_like('d.creditor', $search)
                    ->or_like('d.description', $search)->group_end();
            }
        }
        $this->db->group_by('d.id')->order_by('d.status', 'ASC')
            ->order_by('d.debt_date', 'DESC')->order_by('d.id', 'DESC');
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$row) {
            $principalCents = $this->safe_money_cents(isset($row['principal_amount']) ? $row['principal_amount'] : NULL);
            $paidCents = $this->safe_money_cents(isset($row['paid_amount']) ? $row['paid_amount'] : NULL);
            $pendingCents = $this->safe_money_cents(isset($row['pending_amount']) ? $row['pending_amount'] : NULL);
            $row['paid_amount'] = $this->cents_to_decimal($paidCents);
            $row['pending_amount'] = $this->cents_to_decimal($pendingCents);
            $row['remaining_amount'] = $this->cents_to_decimal(max(0,$principalCents-$paidCents));
            $row['committed_amount'] = $this->cents_to_decimal($paidCents+$pendingCents);
            $row['computed_status'] = $this->computed_status($row);
        }
        unset($row);
        return $rows;
    }

    public function debt($id, $withPayments = TRUE)
    {
        // Use a direct lookup so an invalid ID cannot accidentally return a
        // different row and so the detail query can include its aggregates.
        $this->db->select(
            'd.*,cat.name AS category_name,e.name AS event_name,u.name AS creator_name,' .
            'COALESCE(SUM(CASE WHEN x.status="verified" THEN x.amount ELSE 0 END),0) AS paid_amount,' .
            'COALESCE(SUM(CASE WHEN x.status="pending" THEN x.amount ELSE 0 END),0) AS pending_amount,' .
            'SUM(CASE WHEN x.status IN ("pending","verified") THEN 1 ELSE 0 END) AS payment_count,' .
            'MAX(CASE WHEN x.status="verified" THEN x.expense_date ELSE NULL END) AS last_payment_date',
            FALSE
        )->from('company_debts d')->join('expense_categories cat','cat.id=d.category_id','left')
            ->join('training_events e','e.id=d.event_id','left')->join('users u','u.id=d.created_by','left')
            ->join('expenses x','x.debt_id=d.id','left')->where('d.id',(int) $id)->group_by('d.id');
        $row = $this->db->get()->row_array();
        if (!$row) return NULL;
        $principalCents = $this->safe_money_cents(isset($row['principal_amount']) ? $row['principal_amount'] : NULL);
        $paidCents = $this->safe_money_cents(isset($row['paid_amount']) ? $row['paid_amount'] : NULL);
        $pendingCents = $this->safe_money_cents(isset($row['pending_amount']) ? $row['pending_amount'] : NULL);
        $row['paid_amount'] = $this->cents_to_decimal($paidCents);
        $row['pending_amount'] = $this->cents_to_decimal($pendingCents);
        $row['remaining_amount'] = $this->cents_to_decimal(max(0,$principalCents-$paidCents));
        $row['committed_amount'] = $this->cents_to_decimal($paidCents+$pendingCents);
        $row['computed_status'] = $this->computed_status($row);
        if ($withPayments) {
            $row['payments'] = $this->db->select('x.*,a.name AS account_name,u.name AS creator_name,v.name AS verifier_name')
                ->from('expenses x')->join('fund_accounts a','a.id=x.account_id','left')
                ->join('users u','u.id=x.created_by','left')->join('users v','v.id=x.verified_by','left')
                ->where('x.debt_id',(int) $id)->order_by('x.expense_date','DESC')->order_by('x.id','DESC')
                ->get()->result_array();
        }
        return $row;
    }

    public function summary(array $filters = array())
    {
        $rows = $this->debts($filters);
        $summary = array(
            'count' => count($rows), 'open_count' => 0, 'paid_count' => 0,
            'cancelled_count' => 0, 'principal' => '0.00', 'paid' => '0.00',
            'pending' => '0.00', 'outstanding' => '0.00'
        );
        $principal = 0; $paid = 0; $pending = 0;
        foreach ($rows as $row) {
            $computed = $this->computed_status($row);
            if ($computed === 'paid') $summary['paid_count']++;
            elseif ($computed === 'cancelled') $summary['cancelled_count']++;
            else $summary['open_count']++;
            // Cancelled liabilities are kept for audit history but do not
            // contribute to the current outstanding total.
            if ($computed !== 'cancelled') {
                $principal += $this->safe_money_cents(isset($row['principal_amount']) ? $row['principal_amount'] : NULL);
                $paid += $this->safe_money_cents(isset($row['paid_amount']) ? $row['paid_amount'] : NULL);
                $pending += $this->safe_money_cents(isset($row['pending_amount']) ? $row['pending_amount'] : NULL);
            }
        }
        $summary['principal'] = $this->cents_to_decimal($principal);
        $summary['paid'] = $this->cents_to_decimal($paid);
        $summary['pending'] = $this->cents_to_decimal($pending);
        $summary['outstanding'] = $this->cents_to_decimal(max(0, $principal - $paid));
        // Aliases used by the AppKit debt cards; keep the concise names above
        // for API consumers that prefer the generic summary vocabulary.
        $summary['total_principal'] = $summary['principal'];
        $summary['total_paid'] = $summary['paid'];
        $summary['total_pending'] = $summary['pending'];
        $summary['total_outstanding'] = $summary['outstanding'];
        return $summary;
    }

    public function categories($activeOnly = TRUE)
    {
        $this->db->from('expense_categories')->order_by('name','ASC');
        if ($activeOnly) $this->db->where('is_active',1);
        return $this->db->get()->result_array();
    }

    public function accounts($activeOnly = TRUE)
    {
        $this->db->from('fund_accounts')->order_by('sort_order','ASC')->order_by('name','ASC');
        if ($activeOnly) $this->db->where('is_active',1);
        return $this->db->get()->result_array();
    }

    public function payments_by_debt(array $debtIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $debtIds), function ($id) {
            return $id > 0;
        })));
        $result = array();
        foreach ($ids as $id) $result[$id] = array();
        if (!$ids) return $result;
        $rows = $this->db->select('x.*,a.name AS account_name,u.name AS creator_name,v.name AS verifier_name')
            ->from('expenses x')->join('fund_accounts a','a.id=x.account_id','left')
            ->join('users u','u.id=x.created_by','left')->join('users v','v.id=x.verified_by','left')
            ->where_in('x.debt_id',$ids)->order_by('x.expense_date','DESC')->order_by('x.id','DESC')
            ->get()->result_array();
        foreach ($rows as $row) $result[(int)$row['debt_id']][] = $row;
        return $result;
    }

    public function events()
    {
        return $this->db->select('id,code,name,start_date,end_date,status')->from('training_events')
            ->order_by('start_date','DESC')->order_by('id','DESC')->get()->result_array();
    }

    public function create_debt(array $data, $userId)
    {
        $creditor = $this->text($data, 'creditor', 160);
        $description = $this->text($data, 'description', 5000);
        if ($creditor === '') throw new InvalidArgumentException('Nama kreditur wajib diisi.');
        if ($description === '') throw new InvalidArgumentException('Uraian hutang wajib diisi.');
        $principalCents = $this->money_input(isset($data['principal_amount']) ? $data['principal_amount'] : NULL, FALSE, 'Nominal hutang tidak valid.');
        $debtDate = $this->date_input(isset($data['debt_date']) ? $data['debt_date'] : NULL, 'Tanggal hutang tidak valid.');
        $eventId = $this->nullable_id(isset($data['event_id']) ? $data['event_id'] : NULL, 'Event tidak valid.');
        $categoryId = $this->nullable_id(isset($data['category_id']) ? $data['category_id'] : NULL, 'Kategori hutang tidak valid.');
        $note = $this->text($data, 'note', 2000);
        $now = date('Y-m-d H:i:s');
        $originalDbDebug = $this->db->db_debug; $this->db->db_debug = FALSE; $this->db->trans_begin();
        try {
            if ($eventId !== NULL && !$this->db->query('SELECT id FROM training_events WHERE id=? FOR UPDATE', array($eventId))->row_array()) {
                throw new InvalidArgumentException('Event tidak ditemukan.');
            }
            $categoryId = $this->valid_category_id($categoryId);
            $debtNo = $this->document_number('debt_prefix', 'DEBT');
            $insert = array(
                'debt_no'=>$debtNo, 'event_id'=>$eventId, 'category_id'=>$categoryId,
                'creditor'=>$creditor, 'description'=>$description, 'debt_date'=>$debtDate,
                'principal_amount'=>$this->cents_to_decimal($principalCents),
                'status'=>'open', 'note'=>$note !== '' ? $note : NULL,
                'created_by'=>(int)$userId, 'updated_by'=>(int)$userId,
                'created_at'=>$now, 'updated_at'=>$now
            );
            if (!$this->db->insert('company_debts',$insert)) throw new RuntimeException('Hutang gagal disimpan.');
            $id = (int) $this->db->insert_id();
            $this->assert_transaction();
            if (!$this->db->trans_commit()) throw new RuntimeException('Transaksi hutang gagal diselesaikan.');
            return $id;
        } catch (Throwable $e) {
            $this->db->trans_rollback(); throw $e;
        } finally { $this->db->db_debug = $originalDbDebug; }
    }

    public function update_debt($id, array $data, $userId)
    {
        $id = (int) $id; if ($id < 1) throw new InvalidArgumentException('Hutang tidak valid.');
        $fields = array();
        foreach (array('creditor','description','note') as $field) {
            if (array_key_exists($field,$data)) $fields[$field] = $this->text($data,$field,$field==='description'?5000:($field==='note'?2000:160));
        }
        if (array_key_exists('debt_date',$data)) $fields['debt_date'] = $this->date_input($data['debt_date'],'Tanggal hutang tidak valid.');
        if (array_key_exists('event_id',$data)) $fields['event_id'] = $this->nullable_id($data['event_id'],'Event tidak valid.');
        if (array_key_exists('category_id',$data)) $fields['category_id'] = $this->nullable_id($data['category_id'],'Kategori hutang tidak valid.');
        $principalCents = NULL;
        if (array_key_exists('principal_amount',$data)) $principalCents = $this->money_input($data['principal_amount'],FALSE,'Nominal hutang tidak valid.');
        if (!$fields && $principalCents === NULL) throw new InvalidArgumentException('Tidak ada perubahan hutang.');
        $originalDbDebug = $this->db->db_debug; $this->db->db_debug = FALSE; $this->db->trans_begin();
        try {
            $row = $this->db->query('SELECT * FROM company_debts WHERE id=? FOR UPDATE',array($id))->row_array();
            if (!$row) throw new InvalidArgumentException('Hutang tidak ditemukan.');
            if ($row['status'] !== 'open') throw new InvalidArgumentException('Hutang yang sudah lunas atau dibatalkan tidak dapat diubah.');
            $count = (int)$this->db->where('debt_id',$id)->where_in('status',array('pending','verified'))->count_all_results('expenses');
            if ($principalCents !== NULL && $count > 0 && $principalCents !== $this->money_cents($row['principal_amount'])) {
                throw new InvalidArgumentException('Nominal hutang tidak dapat diubah setelah memiliki riwayat pembayaran.');
            }
            if ($count > 0) {
                // Repayment expenses snapshot the debt metadata. Once a live
                // payment exists, changing any identifying field would make
                // debt and expense reports disagree about what was paid.
                $lockedFields = array('creditor', 'description', 'debt_date', 'event_id', 'category_id');
                foreach ($lockedFields as $lockedField) {
                    if (!array_key_exists($lockedField, $fields)) continue;
                    $newValue = $fields[$lockedField] === NULL ? '' : (string) $fields[$lockedField];
                    $oldValue = isset($row[$lockedField]) && $row[$lockedField] !== NULL ? (string) $row[$lockedField] : '';
                    if ($newValue === $oldValue) continue;
                    $labels = array(
                        'creditor' => 'Kreditur', 'description' => 'Uraian',
                        'debt_date' => 'Tanggal hutang', 'event_id' => 'Event',
                        'category_id' => 'Kategori'
                    );
                    throw new InvalidArgumentException(($labels[$lockedField] ?: 'Data') . ' hutang tidak dapat diubah setelah memiliki pembayaran aktif.');
                }
            }
            if (isset($fields['event_id']) && $fields['event_id'] !== NULL && !$this->db->query('SELECT id FROM training_events WHERE id=? FOR UPDATE',array($fields['event_id']))->row_array()) throw new InvalidArgumentException('Event tidak ditemukan.');
            if (array_key_exists('category_id',$fields)) $fields['category_id'] = $this->valid_category_id($fields['category_id']);
            if ($principalCents !== NULL) $fields['principal_amount'] = $this->cents_to_decimal($principalCents);
            $fields['updated_by'] = (int)$userId; $fields['updated_at'] = date('Y-m-d H:i:s');
            if (!$this->db->where('id',$id)->update('company_debts',$fields)) throw new RuntimeException('Perubahan hutang gagal disimpan.');
            $this->assert_transaction();
            if (!$this->db->trans_commit()) throw new RuntimeException('Transaksi perubahan hutang gagal diselesaikan.');
            return TRUE;
        } catch (Throwable $e) { $this->db->trans_rollback(); throw $e; }
        finally { $this->db->db_debug = $originalDbDebug; }
    }

    public function cancel_debt($id, $userId)
    {
        $id=(int)$id; if ($id<1) throw new InvalidArgumentException('Hutang tidak valid.');
        $originalDbDebug=$this->db->db_debug; $this->db->db_debug=FALSE; $this->db->trans_begin();
        try {
            $row=$this->db->query('SELECT * FROM company_debts WHERE id=? FOR UPDATE',array($id))->row_array();
            if(!$row)throw new InvalidArgumentException('Hutang tidak ditemukan.');
            if($row['status']==='cancelled'){
                if(!$this->db->trans_commit())throw new RuntimeException('Transaksi pembatalan hutang gagal diselesaikan.');
                return TRUE;
            }
            if($row['status']==='paid')throw new InvalidArgumentException('Hutang yang sudah lunas tidak dapat dibatalkan.');
            $pending=(int)$this->db->where('debt_id',$id)->where_in('status',array('pending','verified'))->count_all_results('expenses');
            if($pending>0)throw new InvalidArgumentException('Hutang tidak dapat dibatalkan karena sudah memiliki pembayaran.');
            if(!$this->db->where('id',$id)->update('company_debts',array('status'=>'cancelled','cancelled_at'=>date('Y-m-d H:i:s'),'updated_by'=>(int)$userId,'updated_at'=>date('Y-m-d H:i:s'))))throw new RuntimeException('Hutang gagal dibatalkan.');
            $this->assert_transaction();
            if(!$this->db->trans_commit())throw new RuntimeException('Transaksi pembatalan hutang gagal diselesaikan.');
            return TRUE;
        }catch(Throwable $e){$this->db->trans_rollback();throw $e;}
        finally{$this->db->db_debug=$originalDbDebug;}
    }

    public function create_payment($debtId, array $data, $userId)
    {
        $debtId=(int)$debtId; if($debtId<1)throw new InvalidArgumentException('Hutang tidak valid.');
        $paymentDate=$this->date_input(isset($data['payment_date'])?$data['payment_date']:NULL,'Tanggal pembayaran tidak valid.');
        $method=isset($data['method'])&&is_scalar($data['method'])?(string)$data['method']:'';
        // Company debt repayments are deliberately paid from the cash drawer
        // only.  Bank/QRIS transfers belong to the general expense workflow.
        if($method!=='cash')throw new InvalidArgumentException('Pembayaran hutang hanya dapat menggunakan metode Tunai.');
        $accountId=$this->required_id(isset($data['account_id'])?$data['account_id']:NULL,'Akun dana tidak valid.');
        $amountCents=$this->money_input(isset($data['amount'])?$data['amount']:NULL,FALSE,'Nominal pembayaran tidak valid.');
        $feeCents=$this->money_input(isset($data['admin_fee'])?$data['admin_fee']:'0',TRUE,'Biaya admin tidak valid.');
        if($feeCents>0)throw new InvalidArgumentException('Biaya admin hanya dapat diterapkan pada pembayaran Transfer. Pembayaran hutang memakai Kas Tunai.');
        $status=isset($data['status'])?(string)$data['status']:'pending';
        if(!in_array($status,array('pending','verified'),TRUE))throw new InvalidArgumentException('Status pembayaran tidak valid.');
        $proof=isset($data['proof_path'])&&is_scalar($data['proof_path'])?trim((string)$data['proof_path']):'';
        if($proof!==''&&!$this->valid_upload_path($proof,'debts'))throw new InvalidArgumentException('Bukti pembayaran hutang tidak valid.');
        if($proof!==''&&$this->db->where('proof_path',$proof)->count_all_results('expenses'))throw new InvalidArgumentException('Bukti pembayaran hutang sudah digunakan oleh transaksi lain.');
        if($method!=='cash'&&$proof==='')throw new InvalidArgumentException('Bukti transfer atau QRIS wajib diunggah.');
        $note=$this->text($data,'note',2000); $description=$this->text($data,'description',3000);
        $originalDbDebug=$this->db->db_debug;$this->db->db_debug=FALSE;$this->db->trans_begin();
        try{
            $debt=$this->db->query('SELECT * FROM company_debts WHERE id=? FOR UPDATE',array($debtId))->row_array();
            if(!$debt)throw new InvalidArgumentException('Hutang tidak ditemukan.');
            if($debt['status']!=='open')throw new InvalidArgumentException('Hutang sudah lunas atau dibatalkan.');
            if(!empty($debt['event_id'])){
                $event=$this->db->query('SELECT id FROM training_events WHERE id=? FOR UPDATE',array((int)$debt['event_id']))->row_array();
                if(!$event)throw new InvalidArgumentException('Event hutang tidak ditemukan.');
            }
            $account=$this->db->query('SELECT * FROM fund_accounts WHERE id=? FOR UPDATE',array($accountId))->row_array();
            if(!$account||!(int)$account['is_active']||$account['type']!=='cash')throw new InvalidArgumentException('Akun pembayaran hutang harus berupa Kas Tunai yang aktif.');
            $committed=$this->db->select('COALESCE(SUM(amount),0) total',FALSE)->where('debt_id',$debtId)->where_in('status',array('pending','verified'))->get('expenses')->row_array();
            $committedCents=$this->money_cents(isset($committed['total'])?$committed['total']:'0');
            $principalCents=$this->money_cents($debt['principal_amount']);
            if($committedCents===NULL||$principalCents===NULL)throw new RuntimeException('Nominal hutang atau riwayat pembayarannya tidak valid.');
            if($amountCents<=0||$amountCents>$principalCents-$committedCents)throw new InvalidArgumentException('Nominal melebihi sisa hutang yang dapat dibayar.');
            $accountBalance = $this->locked_account_balance_cents($account);
            if($status==='verified'&&($accountBalance===NULL||$accountBalance<$amountCents+$feeCents))throw new InvalidArgumentException('Saldo akun tidak mencukupi untuk pembayaran dan biaya admin.');
            $category=$this->db->where(array('code'=>'pembayaran-hutang','is_active'=>1))->get('expense_categories')->row_array();
            if(!$category)throw new RuntimeException('Kategori pembayaran hutang belum tersedia. Jalankan patch database hutang.');
            $now=date('Y-m-d H:i:s'); $expenseNo=$this->document_number('expense_prefix','OUT');
            $expense=array(
                'expense_no'=>$expenseNo,'event_id'=>!empty($debt['event_id'])?(int)$debt['event_id']:NULL,'debt_id'=>$debtId,
                'category_id'=>(int)$category['id'],'expense_date'=>$paymentDate,'payee'=>substr($debt['creditor'],0,160),
                'description'=>$description!==''?$description:substr('Pembayaran hutang '.$debt['debt_no'],0,3000),
                'amount'=>$this->cents_to_decimal($amountCents),'method'=>$method,'account_id'=>$accountId,
                'admin_fee'=>$this->cents_to_decimal($feeCents),'proof_path'=>$proof!==''?$proof:NULL,
                'status'=>$status,'note'=>$note!==''?$note:NULL,'created_by'=>(int)$userId,
                'verified_by'=>$status==='verified'?(int)$userId:NULL,'verified_at'=>$status==='verified'?$now:NULL,
                'created_at'=>$now,'updated_at'=>$now
            );
            if(!$this->db->insert('expenses',$expense))throw new RuntimeException('Pembayaran hutang gagal disimpan.');
            $expenseId=(int)$this->db->insert_id();
            if($status==='verified'&&!$this->post_expense_ledger($expenseId,$expense,$userId))throw new RuntimeException('Buku besar pembayaran hutang gagal disimpan.');
            $this->sync_debt_status($debtId,$userId);
            $this->assert_transaction();
            if(!$this->db->trans_commit())throw new RuntimeException('Transaksi pembayaran hutang gagal diselesaikan.');
            $updated=$this->debt($debtId,FALSE);
            return array('expense_id'=>$expenseId,'debt_id'=>$debtId,'status'=>$status,'debt_status'=>$updated?$updated['computed_status']:'open','remaining_amount'=>$updated?$updated['remaining_amount']:$this->cents_to_decimal($principalCents-$committedCents-$amountCents));
        }catch(Throwable $e){$this->db->trans_rollback();throw $e;}
        finally{$this->db->db_debug=$originalDbDebug;}
    }

    public function payment($expenseId)
    {
        return $this->db->select('x.*,d.debt_no,d.creditor,d.principal_amount,d.status AS debt_status,a.name AS account_name,u.name AS creator_name,v.name AS verifier_name')
            ->from('expenses x')->join('company_debts d','d.id=x.debt_id')->join('fund_accounts a','a.id=x.account_id','left')
            ->join('users u','u.id=x.created_by','left')->join('users v','v.id=x.verified_by','left')
            ->where('x.id',(int)$expenseId)->where('x.debt_id IS NOT NULL',NULL,FALSE)->get()->row_array();
    }

    public function set_payment_status($expenseId,$status,$userId)
    {
        $expenseId=(int)$expenseId;$status=(string)$status;
        if($expenseId<1||!in_array($status,array('pending','verified','rejected'),TRUE))throw new InvalidArgumentException('Status pembayaran hutang tidak valid.');
        $originalDbDebug=$this->db->db_debug;$this->db->db_debug=FALSE;$this->db->trans_begin();
        try{
            $expense=$this->db->query('SELECT * FROM expenses WHERE id=? AND debt_id IS NOT NULL FOR UPDATE',array($expenseId))->row_array();
            if(!$expense)throw new InvalidArgumentException('Pembayaran hutang tidak ditemukan.');
            if($expense['status']===$status){if(!$this->db->trans_commit())throw new RuntimeException('Transaksi status pembayaran gagal diselesaikan.');return array('expense_id'=>$expenseId,'debt_id'=>(int)$expense['debt_id'],'status'=>$status);}
            if($expense['status']==='rejected')throw new InvalidArgumentException('Pembayaran hutang yang ditolak bersifat final. Catat pembayaran baru jika diperlukan.');
            if($expense['status']==='verified'&&$status==='pending')throw new InvalidArgumentException('Pembayaran hutang terverifikasi tidak dapat dikembalikan ke status menunggu. Gunakan status Ditolak untuk membalik transaksi.');
            $debt=$this->db->query('SELECT * FROM company_debts WHERE id=? FOR UPDATE',array((int)$expense['debt_id']))->row_array();
            if(!$debt)throw new RuntimeException('Hutang pembayaran tidak tersedia.');
            $account=$this->db->query('SELECT * FROM fund_accounts WHERE id=? FOR UPDATE',array((int)$expense['account_id']))->row_array();
            if(!$account)throw new RuntimeException('Akun dana pembayaran tidak tersedia.');
            if($status==='verified'){
                if($expense['status']==='rejected')throw new InvalidArgumentException('Pembayaran yang ditolak tidak dapat diverifikasi kembali. Catat transaksi baru.');
                if($debt['status']!=='open')throw new InvalidArgumentException('Hutang sudah lunas atau dibatalkan.');
                if(!(int)$account['is_active']||$account['type']!=='cash'||$expense['method']!=='cash')throw new InvalidArgumentException('Akun pembayaran hutang harus berupa Kas Tunai yang aktif.');
                $this->date_input($expense['expense_date'],'Tanggal pembayaran hutang tidak valid.');
                if(!empty($expense['proof_path'])&&!$this->valid_upload_path($expense['proof_path'],'debts'))throw new InvalidArgumentException('Bukti pembayaran hutang tidak valid atau sudah tidak tersedia.');
                $other=$this->db->select('COALESCE(SUM(amount),0) total',FALSE)->where('debt_id',(int)$expense['debt_id'])->where_in('status',array('pending','verified'))->where('id !=',$expenseId)->get('expenses')->row_array();
                $principal = $this->money_cents($debt['principal_amount']);
                $otherCents = $this->money_cents(isset($other['total'])?$other['total']:'0');
                $expenseCents = $this->money_cents($expense['amount']);
                $feeCents = $this->money_cents($expense['admin_fee']);
                $accountBalance = $this->locked_account_balance_cents($account);
                if($principal===NULL||$otherCents===NULL||$expenseCents===NULL||$feeCents===NULL||$accountBalance===NULL)throw new RuntimeException('Nominal pembayaran hutang tidak valid.');
                if($feeCents>0)throw new InvalidArgumentException('Pembayaran hutang melalui Kas Tunai tidak boleh memiliki biaya admin.');
                $remaining=$principal-$otherCents;
                if($expenseCents>$remaining)throw new InvalidArgumentException('Pembayaran tidak dapat diverifikasi karena melebihi sisa hutang.');
                if($accountBalance<$expenseCents+$feeCents)throw new InvalidArgumentException('Saldo akun tidak mencukupi untuk pembayaran dan biaya admin.');
                $now=date('Y-m-d H:i:s');
                if(!$this->db->where('id',$expenseId)->update('expenses',array('status'=>'verified','verified_by'=>(int)$userId,'verified_at'=>$now,'updated_at'=>$now)))throw new RuntimeException('Status pembayaran gagal diperbarui.');
                if(!(int)$this->db->where(array('source_type'=>'expense','source_id'=>$expenseId))->count_all_results('ledger_entries')&&!$this->post_expense_ledger($expenseId,$expense,$userId))throw new RuntimeException('Buku besar pembayaran gagal diperbarui.');
            }else{
                if($expense['status']==='verified'){
                    if(!(int)$account['is_active'])throw new InvalidArgumentException('Akun sumber nonaktif. Aktifkan kembali akun tersebut sebelum membatalkan pembayaran.');
                    $currentBalance = $this->locked_account_balance_cents($account);
                    // Removing the journal reverses only this payment's signed
                    // movement.  Using the whole account movement here would
                    // accidentally calculate the opening balance instead of
                    // the balance after this one payment is removed.
                    $ledgerNet = $this->source_ledger_net_cents('expense',$expenseId,(int)$account['id']);
                    if ($currentBalance === NULL || $ledgerNet === NULL || $currentBalance - $ledgerNet < 0) {
                        throw new InvalidArgumentException('Pembayaran tidak dapat dibatalkan karena saldo Kas Tunai setelah pembalikan menjadi negatif.');
                    }
                }
                if(!$this->db->where(array('source_type'=>'expense','source_id'=>$expenseId))->delete('ledger_entries'))throw new RuntimeException('Koreksi buku besar pembayaran gagal diperbarui.');
                $now=date('Y-m-d H:i:s');
                $update=array('status'=>$status,'verified_by'=>NULL,'verified_at'=>NULL,'updated_at'=>$now);
                if(!$this->db->where('id',$expenseId)->update('expenses',$update))throw new RuntimeException('Status pembayaran gagal diperbarui.');
            }
            $this->sync_debt_status((int)$expense['debt_id'],$userId);$this->assert_transaction();
            if(!$this->db->trans_commit())throw new RuntimeException('Transaksi status pembayaran gagal diselesaikan.');
            $updated=$this->debt((int)$expense['debt_id'],FALSE);
            return array('expense_id'=>$expenseId,'debt_id'=>(int)$expense['debt_id'],'status'=>$status,'debt_status'=>$updated?$updated['computed_status']:'open');
        }catch(Throwable $e){$this->db->trans_rollback();throw $e;}
        finally{$this->db->db_debug=$originalDbDebug;}
    }

    private function sync_debt_status($debtId,$userId)
    {
        $debt=$this->db->query('SELECT id,principal_amount,status,settled_at FROM company_debts WHERE id=? FOR UPDATE',array((int)$debtId))->row_array();
        if(!$debt||$debt['status']==='cancelled')return;
        $paid=$this->db->select('COALESCE(SUM(amount),0) total',FALSE)->where(array('debt_id'=>(int)$debtId,'status'=>'verified'))->get('expenses')->row_array();
        $paidCents=$this->money_cents(isset($paid['total'])?$paid['total']:'0');$principalCents=$this->money_cents($debt['principal_amount']);
        if($paidCents===NULL||$principalCents===NULL)throw new RuntimeException('Nominal hutang atau pembayarannya tidak valid.');
        $status=$paidCents>=$principalCents?'paid':'open';
        $update=array('status'=>$status,'updated_by'=>(int)$userId,'updated_at'=>date('Y-m-d H:i:s'),'settled_at'=>$status==='paid'?date('Y-m-d H:i:s'):NULL);
        if($debt['status']!==$status||($status==='paid'&&empty($debt['settled_at'])))$this->db->where('id',(int)$debtId)->update('company_debts',$update);
    }

    private function post_expense_ledger($id,array $row,$userId)
    {
        $amount=$this->money_cents($row['amount']);$fee=$this->money_cents($row['admin_fee']);if($amount===NULL||$fee===NULL||$amount<=0)return FALSE;
        return (bool)$this->db->insert('ledger_entries',array(
            'account_id'=>(int)$row['account_id'],'entry_date'=>$row['expense_date'],'direction'=>'out',
            'amount'=>$this->cents_to_decimal($amount+$fee),'source_type'=>'expense','source_id'=>(int)$id,
            'description'=>substr('Pembayaran hutang '.(isset($row['expense_no'])?$row['expense_no']:'').' - '.$row['description'],0,255),
            'created_by'=>(int)$userId,'created_at'=>date('Y-m-d H:i:s')
        ));
    }

    private function locked_account_balance_cents(array $account)
    {
        $movement=$this->db->select("COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0) movement",FALSE)
            ->where('account_id',(int)$account['id'])->get('ledger_entries')->row_array();
        $opening = $this->signed_money_cents($account['opening_balance']);
        $net = $this->signed_money_cents(isset($movement['movement']) ? $movement['movement'] : '0');
        if ($opening === NULL || $net === NULL) return NULL;
        return $opening + $net;
    }

    /** Return one source's signed movement without passing money through floats. */
    private function source_ledger_net_cents($sourceType,$sourceId,$accountId)
    {
        $rows=$this->db->select('direction,amount')->from('ledger_entries')
            ->where(array('source_type'=>(string)$sourceType,'source_id'=>(int)$sourceId,'account_id'=>(int)$accountId))
            ->get()->result_array();
        $net=0;
        foreach($rows as $entry){
            $amount=$this->money_cents(isset($entry['amount'])?$entry['amount']:NULL);
            if($amount===NULL||$amount<=0||!in_array($entry['direction'],array('in','out'),TRUE))return NULL;
            $net += $entry['direction']==='in' ? $amount : -$amount;
        }
        return $net;
    }

    /** Keep list/report aggregation safe if historical database data is malformed. */
    private function safe_money_cents($value)
    {
        $cents=$this->money_cents($value);
        return $cents===NULL?0:$cents;
    }

    private function account_matches_method($type,$method)
    {
        $allowed=array('cash'=>array('cash'),'transfer'=>array('bank','personal'),'qris'=>array('qris'));
        return isset($allowed[$method])&&in_array($type,$allowed[$method],TRUE);
    }

    private function valid_category_id($id)
    {
        if($id===NULL)return NULL;
        $row=$this->db->where(array('id'=>(int)$id,'is_active'=>1))->get('expense_categories')->row_array();
        if(!$row)throw new InvalidArgumentException('Kategori hutang tidak valid.');return (int)$id;
    }

    private function computed_status(array $row)
    {
        if(isset($row['status'])&&$row['status']==='cancelled')return 'cancelled';
        $paid = $this->money_cents(isset($row['paid_amount'])?$row['paid_amount']:'0');
        $principal = $this->money_cents($row['principal_amount']);
        return ($paid !== NULL && $principal !== NULL && $paid >= $principal) ? 'paid' : 'open';
    }

    private function document_number($settingKey,$fallback)
    {
        $row=$this->db->select('setting_value')->where('setting_key',$settingKey)->get('settings')->row_array();
        $prefix=$row&&trim((string)$row['setting_value'])!==''?strtoupper(trim((string)$row['setting_value'])):$fallback;
        $prefix=preg_replace('/[^A-Z0-9]/','',$prefix);if($prefix==='')$prefix=$fallback;
        for($i=0;$i<6;$i++){
            try{$random=strtoupper(bin2hex(random_bytes(2)));}catch(Exception $e){$random=strtoupper(substr(sha1(uniqid('',TRUE)),0,4));}
            $number=substr($prefix,0,8).'-'.date('Ymd-His').'-'.$random;
            if(!$this->db->where('debt_no',$number)->count_all_results('company_debts')&&!$this->db->where('expense_no',$number)->count_all_results('expenses'))return $number;
        }
        return substr($prefix,0,8).'-'.date('YmdHis').'-'.strtoupper(substr(sha1(uniqid('',TRUE)),0,8));
    }

    private function text(array $data,$key,$max)
    {
        if(!array_key_exists($key,$data)||$data[$key]===NULL)return '';
        if(!is_scalar($data[$key]))throw new InvalidArgumentException('Data hutang tidak valid.');
        $value=trim((string)$data[$key]);if(strlen($value)>$max)throw new InvalidArgumentException('Field '.str_replace('_',' ',$key).' terlalu panjang.');return $value;
    }

    private function date_input($value,$message)
    {
        if(!is_scalar($value))throw new InvalidArgumentException($message);$value=(string)$value;
        $d=DateTime::createFromFormat('Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new InvalidArgumentException($message);return $value;
    }

    private function nullable_date($value,$message)
    {
        if($value===NULL||$value==='')return NULL;return $this->date_input($value,$message);
    }

    private function required_id($value,$message)
    {
        if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new InvalidArgumentException($message);return (int)$value;
    }

    private function nullable_id($value,$message)
    {
        if($value===NULL||$value==='')return NULL;return $this->required_id($value,$message);
    }

    private function money_input($value,$allowZero,$message)
    {
        if(!is_scalar($value)||!preg_match('/^\d{1,16}(?:\.\d{1,2})?$/D',(string)$value))throw new InvalidArgumentException($message);
        $c=$this->money_cents((string)$value);if($c===NULL||$c<0||(!$allowZero&&$c===0))throw new InvalidArgumentException($message);return $c;
    }

    private function money_cents($value)
    {
        if(!is_scalar($value))return NULL;$value=trim((string)$value);
        if(!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D',$value,$m))return NULL;
        $major=ltrim($m[1],'0');if($major==='')$major='0';if(strlen($major)>16)return NULL;
        $majorInt=(int)$major;if($majorInt>intdiv(PHP_INT_MAX,100))return NULL;
        $minor=isset($m[2])?str_pad($m[2],2,'0'): '00';return $majorInt*100+(int)$minor;
    }

    private function signed_money_cents($value)
    {
        if(!is_scalar($value))return NULL;
        $value=trim((string)$value);$negative=isset($value[0])&&$value[0]==='-';
        if($negative)$value=substr($value,1);
        $cents=$this->money_cents($value);return $cents === NULL ? NULL : ($negative?-abs($cents):$cents);
    }

    private function valid_upload_path($path,$folder)
    {
        if(!is_scalar($path))return FALSE;
        $path=ltrim(str_replace('\\','/',trim((string)$path)),'/');
        $folder=trim(str_replace('\\','/',(string)$folder),'/');$prefix='uploads/'.$folder.'/';
        if($folder===''||$path===''||strpos($path,"\0")!==FALSE||strpos($path,'..')!==FALSE||strpos($path,$prefix)!==0)return FALSE;
        $root=realpath(FCPATH.'uploads/'.$folder);$candidate=realpath(FCPATH.$path);
        return $root!==FALSE&&$candidate!==FALSE&&is_file($candidate)&&strpos($candidate,$root.DIRECTORY_SEPARATOR)===0;
    }

    private function cents_to_decimal($cents)
    {
        $cents=(int)$cents;if($cents<0)$cents=0;return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);
    }

    private function assert_transaction()
    {
        if($this->db->trans_status()===FALSE){$error=$this->db->error();throw new RuntimeException(!empty($error['message'])?$error['message']:'Transaksi hutang gagal disimpan.');}
    }
}
