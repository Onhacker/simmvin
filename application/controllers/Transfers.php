<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transfers extends App_Controller
{
    public function __construct(){parent::__construct();$this->load->model('Finance_model','finance');}
    public function index()
    {
        $this->require_permission('transfers.view');
        try {
            $filters=array_merge($this->date_filters(),array('status'=>$this->input->get('status',TRUE)));
        } catch (InvalidArgumentException $e) {
            $this->session->set_flashdata('error',$e->getMessage());
            $filters=array('date_from'=>'','date_to'=>'','status'=>$this->input->get('status',TRUE));
        }
        $canCreate=$this->Auth_model->can('transfers.create');
        $this->render('transfers/index',array(
            'pageTitle'=>'Transfer Dana',
            'rows'=>$this->finance->transfers($filters),
            'filters'=>$filters,
            'accounts'=>$canCreate?$this->finance->accounts(TRUE):array(),
            'canCreate'=>$canCreate,
            'canVerify'=>$this->Auth_model->can('transfers.verify'),
            'pageScript'=>'transfers.js'
        ));
    }
    public function create()
    {
        $this->require_permission('transfers.create');
        $canVerify=$this->Auth_model->can('transfers.verify');
        if ($this->input->method(TRUE)==='POST') {
            $this->form_validation->set_message('valid_date','Tanggal transfer tidak valid.');
            $this->form_validation->set_rules('transfer_date','Tanggal','required|callback_valid_date');
            $this->form_validation->set_rules('from_account_id','Akun sumber','required|integer');
            $this->form_validation->set_rules('to_account_id','Akun tujuan','required|integer|differs[from_account_id]');
            $this->form_validation->set_rules('amount','Jumlah','required');
            $this->form_validation->set_rules('admin_fee','Biaya admin','required');
            $this->form_validation->set_rules('status','Status','required|in_list[pending,verified]');
            if ($this->form_validation->run()) {
                $proof = NULL;
                $id = NULL;
                try {
                    $amount = simp_money_decimal($this->input->post('amount', TRUE), FALSE);
                    $fee = simp_money_decimal($this->input->post('admin_fee', TRUE), TRUE);
                    if ($amount === NULL) throw new InvalidArgumentException('Jumlah transfer harus lebih dari nol, maksimal 16 digit, dan maksimal 2 angka desimal.');
                    if ($fee === NULL) throw new InvalidArgumentException('Biaya transfer harus nol atau lebih, maksimal 16 digit, dan maksimal 2 angka desimal.');
                    // An internal transfer is an auditable bank/cash movement;
                    // require its proof in the legacy form as well as AJAX.
                    try { $proof=$this->upload_document('proof','transfers',TRUE); }
                    catch (RuntimeException $uploadError) { throw $this->transfer_upload_exception($uploadError); }
                    $data=array('transfer_date'=>$this->input->post('transfer_date',TRUE),'from_account_id'=>(int)$this->input->post('from_account_id'),
                        'to_account_id'=>(int)$this->input->post('to_account_id'),'amount'=>$amount,
                        'admin_fee'=>$fee,'proof_path'=>$proof,
                        'status'=>($canVerify&&$this->input->post('status',TRUE)==='verified')?'verified':'pending','note'=>$this->input->post('note',TRUE) ?: NULL);
                    $id=$this->finance->create_transfer($data,$this->currentUser['id']);
                    if (!$id) throw new RuntimeException('Transfer gagal disimpan. Pastikan akun sumber dan tujuan berbeda.');
                    $this->Audit_model->log('transfer_created','fund_transfer',$id,$data);
                    return $this->redirect_with('transfer-dana','success','Transfer dana berhasil dicatat.');
                } catch(InvalidArgumentException $e){if(!isset($id)&&!empty($proof))$this->cleanup_transfer_upload($proof);$this->session->set_flashdata('error',$e->getMessage());}
                catch(RuntimeException $e){if(!isset($id)&&!empty($proof))$this->cleanup_transfer_upload($proof);$this->session->set_flashdata('error',$e->getMessage());}
            }
        }
        $this->render('transfers/form',array('pageTitle'=>'Tambah Transfer Dana','accounts'=>$this->finance->accounts(TRUE),'canVerify'=>$canVerify,'pageScript'=>'finance.js'));
    }

    /** Compact AppKit modal endpoint. The model re-locks both accounts. */
    public function create_ajax()
    {
        $this->require_permission('transfers.create');
        $this->require_post();
        $proof=NULL;$transferId=NULL;
        $originalDbDebug=$this->db->db_debug;
        $this->db->db_debug=FALSE;
        try {
            $dateRaw=$this->input->post('transfer_date',TRUE);
            if(!is_scalar($dateRaw))throw new InvalidArgumentException('Tanggal transfer tidak valid.');
            $date=(string)$dateRaw;
            $parsed=DateTime::createFromFormat('Y-m-d',$date);
            if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Tanggal transfer tidak valid.');

            $from=$this->positive_integer_input('from_account_id','Akun sumber tidak valid.');
            $to=$this->positive_integer_input('to_account_id','Akun tujuan tidak valid.');
            if($from===$to)throw new InvalidArgumentException('Akun sumber dan tujuan harus berbeda.');
            $amount=$this->money_input('amount',TRUE,'Jumlah transfer tidak valid.');
            $fee=$this->money_input('admin_fee',FALSE,'Biaya admin tidak valid.');
            $statusRaw=$this->input->post('status',TRUE);
            if($statusRaw!==NULL&&!is_scalar($statusRaw))throw new InvalidArgumentException('Status transfer tidak valid.');
            $requestedStatus=$statusRaw===NULL?'pending':(string)$statusRaw;
            if(!in_array($requestedStatus,array('pending','verified'),TRUE))throw new InvalidArgumentException('Status transfer tidak valid.');
            $status=$this->Auth_model->can('transfers.verify')&&$requestedStatus==='verified'?'verified':'pending';
            $noteRaw=$this->input->post('note',TRUE);
            if(!is_scalar($noteRaw)&&$noteRaw!==NULL)throw new InvalidArgumentException('Catatan tidak valid.');
            $note=trim((string)$noteRaw);
            if(strlen($note)>2000)throw new InvalidArgumentException('Catatan maksimal 2.000 karakter.');

            $uploadPath=FCPATH.'uploads/transfers';
            if(!is_dir($uploadPath)&&!mkdir($uploadPath,0755,TRUE)&&!is_dir($uploadPath))throw new RuntimeException('Folder bukti transfer tidak dapat dibuat.');
            try{$proof=$this->upload_document('proof','transfers',TRUE);}
            catch(RuntimeException $uploadError){throw $this->transfer_upload_exception($uploadError);}
            $data=array(
                'transfer_date'=>$date,'from_account_id'=>$from,'to_account_id'=>$to,
                'amount'=>$amount,'admin_fee'=>$fee,'proof_path'=>$proof,'status'=>$status,
                'note'=>$note!==''?$note:NULL
            );
            $transferId=$this->finance->create_transfer($data,$this->currentUser['id']);
            if(!$transferId)throw new RuntimeException('Transfer gagal disimpan. Periksa akun dan saldo sumber.');
            try{$this->Audit_model->log('transfer_created','fund_transfer',$transferId,$data);}catch(Throwable $ignored){}
            return $this->json(array('success'=>TRUE,'message'=>'Transfer dana berhasil dicatat.','transfer_id'=>(int)$transferId));
        } catch(InvalidArgumentException $e) {
            if(!$transferId&&$proof)$this->cleanup_transfer_upload($proof);
            return $this->json(array('success'=>FALSE,'message'=>$e->getMessage()),422);
        } catch(Throwable $e) {
            if(!$transferId&&$proof)$this->cleanup_transfer_upload($proof);
            log_message('error','Transfer AJAX gagal: '.$e->getMessage());
            return $this->json(array('success'=>FALSE,'message'=>'Transfer belum dapat disimpan. Silakan coba kembali.'),500);
        } finally {
            $this->db->db_debug=$originalDbDebug;
        }
    }
    public function status($id)
    {
        $this->require_permission('transfers.verify');
        $this->require_post();
        $wantsJson=$this->wants_json();
        $originalDbDebug=$this->db->db_debug;
        $this->db->db_debug=FALSE;
        try{
            $statusRaw=$this->input->post('status',TRUE);
            if(!is_scalar($statusRaw)||!in_array((string)$statusRaw,array('pending','verified','rejected'),TRUE)){
                throw new InvalidArgumentException('Status transfer tidak valid.');
            }
            $status=(string)$statusRaw;
            $transfer=$this->finance->transfer((int)$id);
            if(!$transfer)throw new InvalidArgumentException('Transfer tidak ditemukan.');
            $allowedTransitions=array(
                'pending'=>array('pending','verified','rejected'),
                'verified'=>array('verified','rejected'),
                'rejected'=>array('rejected')
            );
            $currentStatus=isset($transfer['status'])?(string)$transfer['status']:'';
            if(!isset($allowedTransitions[$currentStatus])||!in_array($status,$allowedTransitions[$currentStatus],TRUE)){
                throw new InvalidArgumentException('Perubahan status transfer tidak diizinkan.');
            }
            $ok=$this->finance->set_transfer_status((int)$id,$status,$this->currentUser['id']);
            if(!$ok)throw new RuntimeException('Model gagal memperbarui status transfer.');
            try{$this->Audit_model->log('transfer_status_changed','fund_transfer',(int)$id,array('status'=>$status));}catch(Throwable $ignored){}
            $message='Status transfer diperbarui.';
            if($wantsJson)return $this->json(array('success'=>TRUE,'message'=>$message,'transfer_id'=>(int)$id,'status'=>$status));
            return $this->redirect_with('transfer-dana','success',$message);
        }catch(InvalidArgumentException $e){
            if($wantsJson)return $this->json(array('success'=>FALSE,'message'=>$e->getMessage()),422);
            return $this->redirect_with('transfer-dana','error',$e->getMessage());
        }catch(Throwable $e){
            log_message('error','Status transfer #'.(int)$id.' gagal: '.$e->getMessage());
            $message='Status transfer belum dapat diperbarui karena terjadi gangguan sistem.';
            if($wantsJson)return $this->json(array('success'=>FALSE,'message'=>$message),500);
            return $this->redirect_with('transfer-dana','error',$message);
        }finally{
            $this->db->db_debug=$originalDbDebug;
        }
    }

    private function positive_integer_input($field,$message)
    {
        $value=$this->input->post($field,TRUE);
        if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new InvalidArgumentException($message);
        return (int)$value;
    }

    private function money_input($field,$mustBePositive,$message)
    {
        $value=$this->input->post($field,TRUE);
        if(!is_scalar($value)||!preg_match('/^\d{1,16}(?:\.\d{1,2})?$/',(string)$value))throw new InvalidArgumentException($message);
        $normalized = simp_money_decimal($value, !$mustBePositive);
        if ($normalized === NULL) throw new InvalidArgumentException($message);
        return $normalized;
    }

    public function valid_date($value)
    {
        if (!is_scalar($value)) return FALSE;
        $date = DateTime::createFromFormat('Y-m-d', (string)$value);
        return $date && $date->format('Y-m-d') === (string)$value;
    }

    private function date_filters()
    {
        $from=$this->strict_date_filter($this->input->get('date_from',TRUE),'Dari tanggal');
        $to=$this->strict_date_filter($this->input->get('date_to',TRUE),'Sampai tanggal');
        if($from!==''&&$to!==''&&$from>$to)throw new InvalidArgumentException('Dari tanggal tidak boleh setelah sampai tanggal.');
        return array('date_from'=>$from,'date_to'=>$to);
    }

    private function strict_date_filter($value,$label)
    {
        if($value===NULL||$value==='')return '';
        if(!is_scalar($value))throw new InvalidArgumentException($label.' tidak valid.');
        $value=trim((string)$value);$date=DateTime::createFromFormat('Y-m-d',$value);
        if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException($label.' tidak valid.');
        return $value;
    }

    private function cleanup_transfer_upload($relativePath)
    {
        $relativePath=ltrim(str_replace('\\','/',(string)$relativePath),'/');
        if($relativePath===''||strpos($relativePath,'..')!==FALSE)return;
        $absolute=FCPATH.$relativePath;
        if(is_file($absolute))@unlink($absolute);
    }

    /** Preserve safe file validation messages while hiding filesystem details. */
    private function transfer_upload_exception(RuntimeException $exception)
    {
        $message=preg_replace('/\s+/',' ',trim(strip_tags((string)$exception->getMessage())));
        $safeFragments=array(
            'wajib diunggah',
            'tidak diizinkan','lebih besar','ukuran maksimum','hanya diupload sebagian',
            'tidak memilih file','not allowed','larger than','maximum allowed size',
            'partially uploaded','did not select a file'
        );
        foreach($safeFragments as $fragment){
            if(stripos($message,$fragment)!==FALSE)return new InvalidArgumentException($message,0,$exception);
        }
        return new RuntimeException('Bukti transfer tidak dapat diunggah.',0,$exception);
    }

    private function wants_json()
    {
        $accept=strtolower((string)$this->input->get_request_header('Accept'));
        return $this->input->is_ajax_request()||strpos($accept,'application/json')!==FALSE;
    }
}
