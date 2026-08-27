<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registrations extends App_Controller
{
    public function __construct(){parent::__construct();$this->load->model('Registration_model','registration');$this->load->model('Position_model','positions');}

    public function index()
    {
        $this->require_permission('registrations.view');
        $activeEvents = $this->registration->events_for_registration();
        $activeEventIds = array_map(function ($event) {
            return (int) $event['id'];
        }, $activeEvents);
        $perPage = 20;
        $pageRaw = $this->input->get('page', TRUE);
        $page = is_scalar($pageRaw) && ctype_digit((string) $pageRaw) ? max(1, (int) $pageRaw) : 1;
        $registrationFilters = array('event_ids' => $activeEventIds, 'active_only' => TRUE);
        $summary = $activeEventIds ? $this->registration->get_all_summary($registrationFilters) : array('registration_count' => 0, 'participant_count' => 0);
        $totalRows = isset($summary['registration_count']) ? (int) $summary['registration_count'] : 0;
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $registrationFilters['limit'] = $perPage;
        $registrationFilters['offset'] = ($page - 1) * $perPage;
        $registrations = $activeEventIds ? $this->registration->get_all($registrationFilters) : array();
        $totalParticipants = isset($summary['participant_count']) ? (int) $summary['participant_count'] : 0;
        $canRecordPayment = $this->Auth_model->can('payments.create');
        $this->render('registrations/index',array('pageTitle'=>'Registrasi',
            'registrations'=>$registrations,'activeEvents'=>$activeEvents,
            'totalRows'=>$totalRows,'totalPages'=>$totalPages,'perPage'=>$perPage,'currentPage'=>$page,
            'totalParticipants'=>$totalParticipants,
            'positions'=>$this->positions->active(),
            'accounts'=>$canRecordPayment ? $this->registration->accounts() : array(),
            'canRecordPayment'=>$canRecordPayment,
            'pageScripts'=>array('registrations.js','finance.js')));
    }

    /**
     * Read-only registration archive scoped to one event.
     *
     * Unlike the operational registration index, this endpoint intentionally
     * accepts draft/open/closed events so historical data never has to be
     * exposed by reopening registration intake.
     */
    public function event_archive($eventId)
    {
        $this->require_permission('registrations.view');
        $event = $this->registration->get_event_archive((int) $eventId);
        if (!$event) show_404();

        $this->render('registrations/archive', array(
            'pageTitle' => 'Arsip Registrasi',
            'event' => $event,
            'registrations' => $this->registration->get_all(array('event_id' => (int) $event['id'])),
            'positions' => $this->positions->active(),
            'isArchive' => TRUE,
            'isReadOnly' => $event['status'] !== 'open',
            'pageScripts' => array('registrations.js', 'finance.js')
        ));
    }

    /** HTML preview for the participant attendance roster. */
    public function print_preview()
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_print_data($this->attendance_date_query());
        $data['isPdf'] = FALSE;
        $data['reportMode'] = 'attendance';
        $html = $this->load->view('reports/registration_attendance', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    /** Download the participant attendance roster as Landscape F4 PDF. */
    public function pdf()
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_print_data($this->attendance_date_query());
        $data['isPdf'] = TRUE;
        $data['reportMode'] = 'attendance';
        $html = $this->load->view('reports/registration_attendance', $data, TRUE);

        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4_landscape($html);
            return $this->private_document_output(
                'application/pdf',
                $pdf,
                'attachment; filename="daftar-registrasi-' . date('Ymd-His') . '.pdf"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF daftar registrasi: ' . $e->getMessage());
            show_error('PDF daftar registrasi belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    /** HTML preview for the participant directory (without attendance columns). */
    public function participant_print_preview()
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_participant_print_data();
        $data['isPdf'] = FALSE;
        $data['reportMode'] = 'participants';
        $html = $this->load->view('reports/registration_attendance', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    /** Download the participant directory as portrait F4 PDF. */
    public function participant_pdf()
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_participant_print_data();
        $data['isPdf'] = TRUE;
        $data['reportMode'] = 'participants';
        $html = $this->load->view('reports/registration_attendance', $data, TRUE);
        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4($html);
            return $this->private_document_output('application/pdf', $pdf, 'attachment; filename="data-peserta-' . date('Ymd-His') . '.pdf"');
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF data peserta: ' . $e->getMessage());
            show_error('PDF data peserta belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    /** HTML preview for the village registration summary. */
    public function village_print_preview()
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_village_print_data();
        $data['isPdf'] = FALSE;
        $data['reportMode'] = 'villages';
        $html = $this->load->view('reports/registration_attendance', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    /** Download the village registration summary as portrait F4 PDF. */
    public function village_pdf()
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_village_print_data();
        $data['isPdf'] = TRUE;
        $data['reportMode'] = 'villages';
        $html = $this->load->view('reports/registration_attendance', $data, TRUE);
        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4($html);
            return $this->private_document_output('application/pdf', $pdf, 'attachment; filename="data-desa-' . date('Ymd-His') . '.pdf"');
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF data desa: ' . $e->getMessage());
            show_error('PDF data desa belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    /** Download the active village directory as a Data Desa/MOU workbook. */
    public function village_excel()
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_village_print_data();

        try {
            $this->load->library('Excel_renderer');
            $excel = $this->excel_renderer->render_registration_village_mailing($data['rows']);
            return $this->private_document_output(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $excel,
                'attachment; filename="data-desa-mou-' . date('Ymd-His') . '.xlsx"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat Excel data desa untuk mailing MOU: ' . $e->getMessage());
            show_error('Excel data desa belum dapat dibuat. Silakan coba kembali.', 500, 'Excel Gagal Dibuat');
        }
    }

    /** Download the active participant roster as a clean mailing workbook. */
    public function excel()
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_print_data();

        try {
            $this->load->library('Excel_renderer');
            $excel = $this->excel_renderer->render_registration_mailing($data['rows']);
            return $this->private_document_output(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $excel,
                'attachment; filename="peserta-mailing-' . date('Ymd-His') . '.xlsx"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat Excel peserta mailing: ' . $e->getMessage());
            show_error('Excel peserta belum dapat dibuat. Silakan coba kembali.', 500, 'Excel Gagal Dibuat');
        }
    }

    /** HTML preview for a single open/draft/closed event archive. */
    public function event_print_preview($eventId)
    {
        $this->require_permission('registrations.view');
        $data = $this->event_registration_print_data((int) $eventId);
        $data['attendanceDate'] = $this->attendance_date_query();
        $data['isPdf'] = FALSE;
        $data['reportMode'] = 'attendance';
        $html = $this->load->view('reports/registration_attendance', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    /** Download a single event participant archive as Landscape F4 PDF. */
    public function event_pdf($eventId)
    {
        $this->require_permission('registrations.view');
        $data = $this->event_registration_print_data((int) $eventId);
        $data['attendanceDate'] = $this->attendance_date_query();
        $data['isPdf'] = TRUE;
        $data['reportMode'] = 'attendance';
        $html = $this->load->view('reports/registration_attendance', $data, TRUE);

        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4_landscape($html);
            return $this->private_document_output(
                'application/pdf',
                $pdf,
                'attachment; filename="daftar-registrasi-' . $this->event_export_slug($data['event']) . '-' . date('Ymd-His') . '.pdf"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF arsip registrasi event #' . (int) $eventId . ': ' . $e->getMessage());
            show_error('PDF daftar registrasi belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    /** Download a single event participant archive as a clean mailing workbook. */
    public function event_excel($eventId)
    {
        $this->require_permission('registrations.view');
        $data = $this->event_registration_print_data((int) $eventId);

        try {
            $this->load->library('Excel_renderer');
            $excel = $this->excel_renderer->render_registration_mailing($data['rows']);
            return $this->private_document_output(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $excel,
                'attachment; filename="peserta-mailing-' . $this->event_export_slug($data['event']) . '-' . date('Ymd-His') . '.xlsx"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat Excel arsip peserta event #' . (int) $eventId . ': ' . $e->getMessage());
            show_error('Excel peserta belum dapat dibuat. Silakan coba kembali.', 500, 'Excel Gagal Dibuat');
        }
    }

    /** HTML preview containing only one village registration and its payments. */
    public function detail_print_preview($registrationId)
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_detail_print_data((int) $registrationId);
        $data['isPdf'] = FALSE;
        $html = $this->load->view('reports/registration_detail', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    /** Download one village registration as an operational landscape F4 report. */
    public function detail_pdf($registrationId)
    {
        $this->require_permission('registrations.view');
        $data = $this->registration_detail_print_data((int) $registrationId);
        $data['isPdf'] = TRUE;
        $html = $this->load->view('reports/registration_detail', $data, TRUE);

        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4_landscape($html);
            return $this->private_document_output(
                'application/pdf',
                $pdf,
                'attachment; filename="detail-registrasi-' . (int) $registrationId . '-' . date('Ymd-His') . '.pdf"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF detail registrasi #' . (int) $registrationId . ': ' . $e->getMessage());
            show_error('PDF detail registrasi belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    /** AJAX batch registration used by the compact add modal on the index page. */
    public function create_ajax()
    {
        $this->require_permission('registrations.create');
        $this->require_post();
        $uploadedFiles = array();
        $result = NULL;
        try {
            $eventId = $this->input->post('event_id', TRUE);
            if (!is_scalar($eventId) || !ctype_digit((string) $eventId)) throw new InvalidArgumentException('Event aktif tidak valid.');
            $event = $this->registration->get_event((int) $eventId, TRUE);
            if (!$event) throw new InvalidArgumentException('Event sudah tidak aktif. Muat ulang daftar event.');
            $villageId = $this->input->post('village_id', TRUE);
            if (!is_scalar($villageId) || trim((string) $villageId) === '') throw new InvalidArgumentException('Pilih desa.');
            $villages = $this->registration->validate_villages($event, array((string) $villageId));
            $participants = $this->input->post('participants');
            if (!is_array($participants)) throw new InvalidArgumentException('Isi minimal satu peserta.');
            $groups = array((string) $villageId => $participants);
            $postedPayments = $this->input->post('payments');
            if ($postedPayments !== NULL && !is_array($postedPayments)) throw new InvalidArgumentException('Data pembayaran awal tidak valid.');
            $paymentGroups = $this->prepare_inline_payments(
                is_array($postedPayments) ? $postedPayments : array(),
                $this->Auth_model->can('payments.create'),
                $uploadedFiles,
                $villages,
                $groups,
                (string) $event['billing_mode']
            );
            $result = $this->registration->create_batch($event, $villages, $groups, array(), $paymentGroups,
                (int) $this->currentUser['id'], $this->Auth_model->can('payments.verify'));
            try { $this->Audit_model->log('registrations_created', 'training_event', $event['id'], array('registration_ids'=>$result['registration_ids'],'payment_ids'=>$result['payment_ids'])); } catch (Throwable $ignored) {}
            $message = 'Registrasi berhasil ditambahkan.';
            if ($result['payment_ids']) $message .= ' '.count($result['payment_ids']).' pembayaran berhasil dicatat'.($this->Auth_model->can('payments.verify') ? '.' : ' dan menunggu verifikasi.');
            return $this->json(array('success'=>TRUE,'message'=>$message,'registration_ids'=>$result['registration_ids'],'payment_ids'=>$result['payment_ids']));
        } catch (Throwable $e) {
            if ($result === NULL) $this->cleanup_uploaded_files($uploadedFiles);
            return $this->ajax_exception_response($e, 'Registrasi belum dapat disimpan. Silakan coba kembali.', 'create');
        }
    }

    public function create()
    {
        $this->require_permission('registrations.create');
        $activeEvents=$this->registration->events_for_registration();
        if(!$activeEvents) return $this->redirect_with('registrasi','error','Aktifkan event terlebih dahulu sebelum menambahkan registrasi.');

        $event=NULL;
        $isPost=$this->input->method(TRUE)==='POST';
        $requestedEventId=$isPost
            ? (int)$this->input->post('event_id')
            : (int)$this->input->get('event_id',TRUE);
        if(!$isPost&&count($activeEvents)===1){
            $event=$activeEvents[0];
        }else{
            foreach($activeEvents as $activeEvent){
                if((int)$activeEvent['id']===$requestedEventId){$event=$activeEvent;break;}
            }
        }
        $canRecordPayment=$this->Auth_model->can('payments.create');
        $data=array('pageTitle'=>'Registrasi','events'=>$activeEvents,'activeEvents'=>$activeEvents,'selectedEvent'=>$event,
            'positions'=>$this->positions->active(),'accounts'=>$canRecordPayment?$this->registration->accounts():array(),'canRecordPayment'=>$canRecordPayment,
            'oldVillages'=>array(),'oldParticipantGroups'=>array(),'oldNotes'=>array(),'oldPaymentGroups'=>array(),'pageScript'=>'registrations.js');
        if($this->input->method(TRUE)==='POST'){
            $data['oldVillages']=(array)$this->input->post('village_ids');
            $data['oldParticipantGroups']=(array)$this->input->post('participants');
            $data['oldNotes']=(array)$this->input->post('notes');
            $data['oldPaymentGroups']=(array)$this->input->post('payments');
            $uploadedFiles=array();$result=NULL;
            try{
                if(!$event)throw new InvalidArgumentException('Event pada form sudah tidak aktif. Muat ulang dan periksa event aktif.');
                $villages=$this->registration->validate_villages($event,$data['oldVillages']);
                $data['oldVillages']=$villages;
                $paymentGroups=$this->prepare_inline_payments(
                    $data['oldPaymentGroups'],$canRecordPayment,$uploadedFiles,$villages,
                    $data['oldParticipantGroups'],(string)$event['billing_mode']
                );
                $result=$this->registration->create_batch(
                    $event,$villages,$data['oldParticipantGroups'],$data['oldNotes'],$paymentGroups,
                    (int)$this->currentUser['id'],$this->Auth_model->can('payments.verify')
                );
                try{$this->Audit_model->log('registrations_created','training_event',$event['id'],array(
                    'registration_ids'=>$result['registration_ids'],'payment_ids'=>$result['payment_ids']
                ));}catch(Throwable $auditError){}
                $message=count($result['registration_ids']).' desa berhasil diregistrasikan.';
                if($result['payment_ids'])$message.=' '.count($result['payment_ids']).' pembayaran awal berhasil dicatat'.($this->Auth_model->can('payments.verify')?'.':' dan menunggu verifikasi.');
                return $this->redirect_with('registrasi','success',$message);
            }catch(Throwable $e){
                if($result===NULL)$this->cleanup_uploaded_files($uploadedFiles);
                $data['error']=$this->registration_exception_message($e,'Registrasi belum dapat disimpan. Silakan periksa data lalu coba kembali.');
            }
        }
        $this->render('registrations/create',$data);
    }

    public function show($id)
    {
        $this->require_permission('registrations.view');$row=$this->registration->get($id);if(!$row)show_404();
        $canRecordPayment = $this->Auth_model->can('payments.create') && $row['event_status'] === 'open';
        $this->render('registrations/show',array(
            'pageTitle'=>'Detail Registrasi',
            'registration'=>$row,
            'event'=>$this->registration->get_event($row['event_id']),
            'positions'=>$this->positions->active(),
            'accounts'=>$canRecordPayment ? $this->registration->accounts() : array(),
            'canRecordPayment'=>$canRecordPayment,
            'isReadOnly'=>$row['event_status'] !== 'open' || $row['status'] !== 'active',
            'pageScripts'=>array('registrations.js', 'finance.js')
        ));
    }

    public function add_participant($id)
    {
        $this->require_permission('registrations.edit');$this->require_post();$row=$this->registration->get($id);if(!$row)show_404();if($row['status']!=='active'||$row['event_status']!=='open')return $this->redirect_with('registrasi/'.(int)$id,'error','Peserta hanya dapat ditambah pada registrasi aktif dan event yang masih aktif.');
        try{$participantId=$this->registration->add_participant($row,array('full_name'=>$this->input->post('full_name',TRUE),'position_id'=>$this->input->post('position_id',TRUE),'phone'=>$this->input->post('phone',TRUE)),(int)$this->currentUser['id']);$this->Audit_model->log('participant_added','participant',$participantId,array('registration_id'=>(int)$id));$this->redirect_with('registrasi/'.(int)$id,'success','Peserta berhasil ditambahkan.');}catch(Throwable $e){$this->redirect_with('registrasi/'.(int)$id,'error',$this->registration_exception_message($e,'Peserta belum dapat ditambahkan. Silakan coba kembali.'));}
    }

    /** AJAX multi-participant endpoint used by the detail modal. */
    public function add_participants_ajax($id)
    {
        $this->require_permission('registrations.edit');
        $this->require_post();
        $uploadedFiles = array();
        $result = NULL;
        try {
            $row = $this->registration->get($id);
            if (!$row) throw new InvalidArgumentException('Registrasi tidak ditemukan.');
            $villageId = $this->input->post('village_id', TRUE);
            if ($villageId !== NULL && !is_scalar($villageId)) throw new InvalidArgumentException('Desa registrasi tidak valid.');
            if ($villageId !== NULL && (string)$villageId !== (string)$row['village_id']) throw new InvalidArgumentException('Desa tidak sesuai dengan registrasi.');
            $participants = $this->input->post('participants');
            if (!is_array($participants)) throw new InvalidArgumentException('Isi minimal satu peserta.');
            $postedPayments = $this->input->post('payments');
            if ($postedPayments !== NULL && !is_array($postedPayments)) throw new InvalidArgumentException('Data pembayaran tidak valid.');
            $villageKey = (string) $row['village_id'];
            $paymentGroups = $this->prepare_inline_payments(
                is_array($postedPayments) ? $postedPayments : array(),
                $this->Auth_model->can('payments.create'),
                $uploadedFiles,
                array(array('village_id'=>$villageKey)),
                array($villageKey=>$participants),
                (string) $row['billing_mode']
            );
            $result = $this->registration->add_participants(
                $row,
                $participants,
                isset($paymentGroups[$villageKey]) ? $paymentGroups[$villageKey] : array(),
                (int) $this->currentUser['id'],
                $this->Auth_model->can('payments.verify')
            );
            $ids = $result['participant_ids'];
            foreach ($ids as $participantId) {
                try { $this->Audit_model->log('participant_added','participant',$participantId,array('registration_id'=>(int)$id)); } catch (Throwable $ignored) {}
            }
            $message = count($ids).' peserta berhasil ditambahkan.';
            if ($result['payment_ids']) $message .= ' '.count($result['payment_ids']).' pembayaran berhasil dicatat'.($this->Auth_model->can('payments.verify') ? '.' : ' dan menunggu verifikasi.');
            return $this->json(array('success'=>TRUE,'message'=>$message,'participant_ids'=>$ids,'payment_ids'=>$result['payment_ids']));
        } catch (Throwable $e) {
            if ($result === NULL) $this->cleanup_uploaded_files($uploadedFiles);
            return $this->ajax_exception_response($e, 'Peserta belum dapat ditambahkan. Silakan coba kembali.', 'add participants #'.(int)$id);
        }
    }

    /** AJAX identity correction; billing snapshots remain unchanged. */
    public function update_participant_ajax($registrationId, $participantId)
    {
        $this->require_permission('registrations.edit');
        $this->require_post();
        $uploadedFiles = array();
        $result = NULL;
        try {
            $fullName = $this->input->post('full_name', TRUE);
            $positionId = $this->input->post('position_id', TRUE);
            $phone = $this->input->post('phone', TRUE);
            if (!is_scalar($fullName) || !is_scalar($positionId) || ($phone !== NULL && !is_scalar($phone))) {
                throw new InvalidArgumentException('Data peserta tidak valid.');
            }
            $row = $this->registration->get((int) $registrationId);
            if (!$row || (int) $row['id'] !== (int) $registrationId) throw new InvalidArgumentException('Registrasi tidak ditemukan.');
            $participantInput = array(
                'full_name' => $fullName,
                'position_id' => $positionId,
                'phone' => $phone === NULL ? '' : $phone
            );
            $postedPayments = $this->input->post('payments');
            if ($postedPayments !== NULL && !is_array($postedPayments)) throw new InvalidArgumentException('Data pembayaran tidak valid.');
            $villageKey = (string) $row['village_id'];
            $participantKey = (string) (int) $participantId;
            $paymentGroups = $this->prepare_inline_payments(
                is_array($postedPayments) ? $postedPayments : array(),
                $this->Auth_model->can('payments.create'),
                $uploadedFiles,
                array(array('village_id'=>$villageKey)),
                array($villageKey=>array($participantKey=>$participantInput)),
                (string) $row['billing_mode']
            );
            $result = $this->registration->update_participant(
                (int) $registrationId,
                (int) $participantId,
                $participantInput,
                isset($paymentGroups[$villageKey]) ? $paymentGroups[$villageKey] : array(),
                (int) $this->currentUser['id'],
                $this->Auth_model->can('payments.verify')
            );
            try {
                $this->Audit_model->log('participant_updated', 'participant', (int) $participantId, array(
                    'registration_id' => (int) $registrationId,
                    'before' => $result['before'],
                    'after' => $result['after'],
                    'payment_ids' => $result['payment_ids']
                ));
            } catch (Throwable $ignored) {}
            $message = 'Data peserta berhasil diperbarui.';
            if ($result['payment_ids']) $message .= ' Pembayaran berhasil dicatat'.($this->Auth_model->can('payments.verify') ? '.' : ' dan menunggu verifikasi.');
            return $this->json(array('success' => TRUE, 'message' => $message, 'participant_id' => (int) $participantId, 'payment_ids'=>$result['payment_ids']));
        } catch (Throwable $e) {
            if ($result === NULL) $this->cleanup_uploaded_files($uploadedFiles);
            return $this->ajax_exception_response($e, 'Data peserta belum dapat diperbarui. Silakan coba kembali.', 'update participant #'.(int)$participantId);
        }
    }

    /** AJAX replacement keeps the old row and links the new row to it. */
    public function replace_participant_ajax($registrationId, $participantId)
    {
        $this->require_permission('registrations.edit');
        $this->require_post();
        try {
            $fullName = $this->input->post('full_name', TRUE);
            $positionId = $this->input->post('position_id', TRUE);
            $phone = $this->input->post('phone', TRUE);
            $reason = $this->input->post('reason', TRUE);
            if (!is_scalar($fullName) || !is_scalar($positionId) || ($phone !== NULL && !is_scalar($phone)) || !is_scalar($reason)) {
                throw new InvalidArgumentException('Data peserta pengganti tidak valid.');
            }
            $result = $this->registration->replace_participant((int) $registrationId, (int) $participantId, array(
                'full_name' => $fullName,
                'position_id' => $positionId,
                'phone' => $phone === NULL ? '' : $phone
            ), $reason, (int) $this->currentUser['id']);
            try {
                $this->Audit_model->log('participant_replaced', 'participant', (int) $result['participant_id'], array(
                    'registration_id' => (int) $registrationId,
                    'old_participant_id' => (int) $participantId,
                    'reason' => $result['reason'],
                    'before' => $result['before'],
                    'after' => $result['after']
                ));
            } catch (Throwable $ignored) {}
            return $this->json(array('success' => TRUE, 'message' => 'Peserta berhasil diganti.', 'participant_id' => (int) $result['participant_id'], 'old_participant_id' => (int) $participantId));
        } catch (Throwable $e) {
            return $this->ajax_exception_response($e, 'Peserta belum dapat diganti. Silakan coba kembali.', 'replace participant #'.(int)$participantId);
        }
    }

    /** AJAX soft-deactivation; the participant row remains in history. */
    public function deactivate_participant_ajax($registrationId, $participantId)
    {
        $this->require_permission('registrations.edit');
        $this->require_post();
        try {
            $reason = $this->input->post('reason', TRUE);
            if (!is_scalar($reason)) throw new InvalidArgumentException('Alasan menonaktifkan wajib diisi.');
            $result = $this->registration->deactivate_participant((int) $registrationId, (int) $participantId, $reason, (int) $this->currentUser['id']);
            try {
                $this->Audit_model->log('participant_deactivated', 'participant', (int) $participantId, array(
                    'registration_id' => (int) $registrationId,
                    'reason' => $result['reason'],
                    'before' => $result['before'],
                    'after' => $result['after'],
                    'expected_amount' => $result['expected_amount']
                ));
            } catch (Throwable $ignored) {}
            return $this->json(array('success' => TRUE, 'message' => 'Peserta dinonaktifkan dan disimpan dalam riwayat.', 'participant_id' => (int) $participantId));
        } catch (Throwable $e) {
            return $this->ajax_exception_response($e, 'Peserta belum dapat dinonaktifkan. Silakan coba kembali.', 'deactivate participant #'.(int)$participantId);
        }
    }

    /** AJAX cancellation of one village registration. */
    public function cancel_ajax($id)
    {
        $this->require_permission('registrations.edit');
        $this->require_post();
        try {
            $reason = $this->input->post('reason', TRUE);
            if (!is_scalar($reason)) throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
            $result = $this->registration->cancel_registration((int) $id, $reason, (int) $this->currentUser['id']);
            try {
                $this->Audit_model->log('registration_cancelled', 'registration', (int) $id, array(
                    'event_id' => $result['event_id'],
                    'reason' => $result['reason'],
                    'old_status' => $result['old_status'],
                    'new_status' => $result['new_status']
                ));
            } catch (Throwable $ignored) {}
            return $this->json(array('success' => TRUE, 'message' => 'Registrasi desa berhasil dibatalkan.', 'registration_id' => (int) $id));
        } catch (Throwable $e) {
            return $this->ajax_exception_response($e, 'Registrasi belum dapat dibatalkan. Silakan coba kembali.', 'cancel registration #'.(int)$id);
        }
    }

    /** AJAX recovery for an accidentally cancelled village registration. */
    public function restore_ajax($id)
    {
        $this->require_permission('registrations.edit');
        $this->require_post();
        try {
            $reason = $this->input->post('reason', TRUE);
            if (!is_scalar($reason)) throw new InvalidArgumentException('Alasan pemulihan wajib diisi.');
            $result = $this->registration->restore_registration((int) $id, $reason, (int) $this->currentUser['id']);
            try {
                $this->Audit_model->log('registration_restored', 'registration', (int) $id, array(
                    'event_id' => $result['event_id'],
                    'reason' => $result['reason'],
                    'restored_participant_count' => $result['restored_participant_count'],
                    'old_status' => $result['old_status'],
                    'new_status' => $result['new_status']
                ));
            } catch (Throwable $ignored) {}
            return $this->json(array(
                'success' => TRUE,
                'message' => 'Registrasi desa dan '.$result['restored_participant_count'].' peserta berhasil dipulihkan.',
                'registration_id' => (int) $id
            ));
        } catch (Throwable $e) {
            return $this->ajax_exception_response($e, 'Registrasi belum dapat dipulihkan. Silakan coba kembali.', 'restore registration #'.(int)$id);
        }
    }

    public function payment($id)
    {
        $this->require_permission('payments.create');$row=$this->registration->get($id);if(!$row)show_404();if($row['status']!=='active')show_error('Registrasi sudah dibatalkan.',422);if($row['event_status']!=='open')show_error('Event sudah ditutup. Buka kembali event sebelum mencatat pembayaran baru.',422);$data=array('pageTitle'=>'Catat Pembayaran','registration'=>$row,'accounts'=>$this->registration->accounts(),'pageScript'=>'registrations.js');
        if($this->input->method(TRUE)==='POST'){
            $this->form_validation->set_message('valid_date','Tanggal pembayaran tidak valid.');
            $this->form_validation->set_rules('payment_date','Tanggal pembayaran','trim|required|callback_valid_date');$this->form_validation->set_rules('method','Metode','trim|required|in_list[cash,transfer,qris]');$this->form_validation->set_rules('account_id','Akun dana','required|integer');$this->form_validation->set_rules('amount','Nominal','required|callback_valid_money');$this->form_validation->set_rules('note','Catatan','trim|max_length[2000]');
            $this->form_validation->set_message('valid_money','Nominal harus lebih dari nol, maksimal 16 digit, dan maksimal 2 angka desimal.');
            if($this->form_validation->run()){
                $proof=NULL;$paymentId=NULL;
                try{
                    $target=$this->registration->payment_target($row,$this->input->post('participant_id'));
                    $accountId=(int)$this->input->post('account_id');
                    $account=$this->db->where(array('id'=>$accountId,'is_active'=>1))->get('fund_accounts')->row_array();
                    if(!$account)throw new InvalidArgumentException('Akun dana tidak valid.');
                    $amount=$this->money_value($this->input->post('amount'),FALSE,'Nominal pembayaran tidak valid.');
                    $targetCents=simp_money_cents($target['expected']);
                    $committedCents=simp_money_cents($this->registration->committed_payment($row['id'],$target['participant_id']));
                    $amountCents=simp_money_cents($amount);
                    if($targetCents===NULL||$committedCents===NULL||$amountCents===NULL)throw new InvalidArgumentException('Nominal pembayaran tidak valid.');
                    if($targetCents<=$committedCents)throw new InvalidArgumentException('Tagihan tujuan ini sudah lunas atau seluruh sisanya sedang menunggu verifikasi.');
                    if($amountCents>$targetCents-$committedCents)throw new InvalidArgumentException('Nominal melebihi sisa tagihan '.rupiah(simp_money_from_cents($targetCents-$committedCents)).'.');
                    $method=$this->input->post('method',TRUE);
                    $validTypes=array('cash'=>array('cash'),'transfer'=>array('bank','personal'),'qris'=>array('qris'));
                    if(!in_array($account['type'],$validTypes[$method],TRUE))throw new InvalidArgumentException('Jenis akun dana tidak sesuai dengan metode pembayaran.');
                    $folder='payments/'.date('Y/m');$path=FCPATH.'uploads/'.$folder;
                    if(!is_dir($path)&&!mkdir($path,0755,TRUE)&&!is_dir($path))throw new RuntimeException('Folder bukti pembayaran tidak dapat dibuat.');
                    try{$proof=$this->upload_document('proof',$folder,$method!=='cash');}
                    catch(RuntimeException $uploadError){throw $this->registration_upload_exception($uploadError);}
                    $verified=$this->Auth_model->can('payments.verify');$now=date('Y-m-d H:i:s');$receipt=$this->receipt_no();
                    $payment=array('receipt_no'=>$receipt,'event_id'=>$row['event_id'],'registration_id'=>$row['id'],'participant_id'=>$target['participant_id'],'account_id'=>$accountId,'payment_date'=>$this->input->post('payment_date',TRUE),'method'=>$method,'amount'=>$amount,'status'=>$verified?'verified':'pending','proof_path'=>$proof,'note'=>trim((string)$this->input->post('note',TRUE))?:NULL,'created_by'=>(int)$this->currentUser['id'],'verified_by'=>$verified?(int)$this->currentUser['id']:NULL,'verified_at'=>$verified?$now:NULL,'created_at'=>$now,'updated_at'=>$now);
                    $paymentId=$this->registration->create_payment($payment,$verified,$target['expected']);
                    try{$this->Audit_model->log('payment_created','payment',$paymentId,array('status'=>$payment['status'],'amount'=>$amount));}catch(Throwable $auditError){}
                    return $this->redirect_with('registrasi/'.$row['id'],'success','Pembayaran '.$receipt.' berhasil dicatat'.($verified?'.':' dan menunggu verifikasi.'));
                }catch(Throwable $e){
                    // A failed model call may return FALSE rather than throw;
                    // in either case the uploaded proof is not referenced and
                    // must be removed so retries cannot accumulate orphan files.
                    if(!$paymentId&&$proof)$this->cleanup_uploaded_files(array($proof));
                    $data['error']=$this->registration_exception_message($e,'Pembayaran belum dapat disimpan. Silakan periksa data lalu coba kembali.');
                }
            }
        }
        $this->render('registrations/payment',$data);
    }

    /** AJAX payment endpoint for the AppKit modal; the legacy page remains available for compatibility. */
    public function payment_ajax($id)
    {
        $this->require_permission('payments.create');
        $this->require_post();
        $proof = NULL; $paymentId = NULL;
        try {
            $row = $this->registration->get($id);
            if (!$row) throw new InvalidArgumentException('Registrasi tidak ditemukan.');
            if ($row['status'] !== 'active') throw new InvalidArgumentException('Registrasi sudah tidak aktif.');
            if ($row['event_status'] !== 'open') throw new InvalidArgumentException('Event sudah ditutup. Buka kembali event sebelum mencatat pembayaran baru.');
            $participantId = $this->input->post('participant_id', TRUE);
            if ($participantId !== NULL && !is_scalar($participantId)) throw new InvalidArgumentException('Peserta tujuan pembayaran tidak valid.');
            $target = $this->registration->payment_target($row, $participantId);
            $dateRaw = $this->input->post('payment_date', TRUE);
            if (!is_scalar($dateRaw)) throw new InvalidArgumentException('Tanggal pembayaran tidak valid.');
            $date = (string)$dateRaw;
            if (!$this->valid_date($date)) throw new InvalidArgumentException('Tanggal pembayaran tidak valid.');
            $methodRaw = $this->input->post('method', TRUE);
            if (!is_scalar($methodRaw)) throw new InvalidArgumentException('Metode pembayaran tidak valid.');
            $method = (string)$methodRaw;
            if (!in_array($method, array('cash','transfer','qris'), TRUE)) throw new InvalidArgumentException('Metode pembayaran tidak valid.');
            $accountId = $this->input->post('account_id', TRUE);
            if (!is_scalar($accountId) || !ctype_digit((string)$accountId) || (int)$accountId < 1) throw new InvalidArgumentException('Akun penerima tidak valid.');
            $account = $this->db->where(array('id'=>(int)$accountId,'is_active'=>1))->get('fund_accounts')->row_array();
            if (!$account) throw new InvalidArgumentException('Akun dana tidak valid.');
            $types = array('cash'=>array('cash'),'transfer'=>array('bank','personal'),'qris'=>array('qris'));
            if (!in_array($account['type'], $types[$method], TRUE)) throw new InvalidArgumentException('Jenis akun tidak sesuai dengan metode pembayaran.');
            $amountRaw = $this->input->post('amount', TRUE);
            $amount = $this->money_value($amountRaw, FALSE, 'Nominal pembayaran harus lebih dari nol, maksimal 16 digit, dan maksimal 2 angka desimal.');
            $targetCents = simp_money_cents($target['expected']);
            $committedCents = simp_money_cents($this->registration->committed_payment($row['id'], $target['participant_id']));
            $amountCents = simp_money_cents($amount);
            if ($targetCents === NULL || $committedCents === NULL || $amountCents === NULL) throw new InvalidArgumentException('Nominal pembayaran tidak valid.');
            $remainingCents = $targetCents - $committedCents;
            if ($remainingCents <= 0) throw new InvalidArgumentException('Tagihan tujuan ini sudah lunas.');
            if ($amountCents > $remainingCents) throw new InvalidArgumentException('Nominal melebihi sisa tagihan '.rupiah(simp_money_from_cents($remainingCents)).'.');
            $noteRaw = $this->input->post('note', TRUE);
            if ($noteRaw !== NULL && !is_scalar($noteRaw)) throw new InvalidArgumentException('Catatan pembayaran tidak valid.');
            $note = trim((string)$noteRaw);
            if (strlen($note) > 2000) throw new InvalidArgumentException('Catatan pembayaran maksimal 2.000 karakter.');
            $folder='payments/'.date('Y/m'); $path=FCPATH.'uploads/'.$folder;
            if (!is_dir($path) && !mkdir($path,0755,TRUE) && !is_dir($path)) throw new RuntimeException('Folder bukti pembayaran tidak dapat dibuat.');
            try {
                $proof = $this->upload_document('proof',$folder,$method!=='cash');
            } catch (RuntimeException $uploadError) {
                // Pesan validasi ukuran/jenis berkas tetap ditampilkan; kegagalan
                // filesystem tidak pernah dikirim ke browser.
                throw $this->registration_upload_exception($uploadError);
            }
            $verified = $this->Auth_model->can('payments.verify'); $now=date('Y-m-d H:i:s');
            $receipt = $this->receipt_no();
            $payment = array('receipt_no'=>$receipt,'event_id'=>$row['event_id'],'registration_id'=>$row['id'],
                'participant_id'=>$target['participant_id'],'account_id'=>(int)$accountId,'payment_date'=>$date,
                'method'=>$method,'amount'=>$amount,'status'=>$verified?'verified':'pending','proof_path'=>$proof,
                'note'=>$note!==''?$note:NULL,'created_by'=>(int)$this->currentUser['id'],
                'verified_by'=>$verified?(int)$this->currentUser['id']:NULL,'verified_at'=>$verified?$now:NULL,
                'created_at'=>$now,'updated_at'=>$now);
            $paymentId = $this->registration->create_payment($payment,$verified,$target['expected']);
            try { $this->Audit_model->log('payment_created','payment',$paymentId,array('status'=>$payment['status'],'amount'=>$amount)); } catch (Throwable $ignored) {}
            return $this->json(array('success'=>TRUE,'message'=>'Pembayaran '.$receipt.' berhasil dicatat'.($verified?'.':' dan menunggu verifikasi.'),'payment_id'=>$paymentId,'receipt_no'=>$receipt,'verified'=>$verified,'remaining'=>simp_money_from_cents(max(0,$remainingCents-$amountCents))));
        } catch (Throwable $e) {
            if (!$paymentId && $proof) $this->cleanup_uploaded_files(array($proof));
            return $this->ajax_exception_response($e, 'Pembayaran belum dapat disimpan. Silakan coba kembali.', 'payment #'.(int)$id);
        }
    }

    /** Return validation details without exposing database/runtime internals. */
    private function ajax_exception_response(Throwable $exception, $fallbackMessage, $context)
    {
        if ($exception instanceof InvalidArgumentException) {
            return $this->json(array('success'=>FALSE,'message'=>$exception->getMessage()), 422);
        }

        log_message('error', 'Registrations AJAX '.$context.' failed: '.get_class($exception).': '.$exception->getMessage());
        return $this->json(array('success'=>FALSE,'message'=>$fallbackMessage), 500);
    }

    /** Keep validation guidance visible while hiding database/runtime details. */
    private function registration_exception_message(Throwable $exception, $fallbackMessage)
    {
        return $exception instanceof InvalidArgumentException ? $exception->getMessage() : $fallbackMessage;
    }

    private function registration_upload_exception(RuntimeException $exception)
    {
        $message=preg_replace('/\s+/',' ',trim(strip_tags((string)$exception->getMessage())));
        $safe=array(
            'Bukti transaksi wajib diunggah.',
            'File yang diupload melebihi ukuran maksimum yang diizinkan di file konfigurasi PHP Anda.',
            'File yang diupload melebihi ukuran maksimum yang diizinkan oleh formulir pengajuan.',
            'File hanya diupload sebagian.',
            'Anda tidak memilih file untuk diupload.',
            'Tipe file yang Anda coba upload tidak diizinkan.',
            'File yang Anda coba upload lebih besar dari ukuran yang diizinkan.',
            'Gambar yang Anda coba upload tidak sesuai dengan dimensi yang diizinkan.',
            'The uploaded file exceeds the maximum allowed size in your PHP configuration file.',
            'The file was only partially uploaded.',
            'You did not select a file to upload.',
            'The filetype you are attempting to upload is not allowed.',
            'The file you are attempting to upload is larger than the permitted size.'
        );
        if(in_array($message,$safe,TRUE)) return new InvalidArgumentException($message,0,$exception);
        return new RuntimeException('Bukti transaksi tidak dapat diunggah.',0,$exception);
    }

    private function registration_print_data($attendanceDate = NULL)
    {
        $activeEvents = $this->registration->events_for_registration();
        $eventIds = array_map(function ($event) {
            return (int) $event['id'];
        }, $activeEvents);

        return array(
            'activeEvents' => $activeEvents,
            'rows' => $this->registration->participants_for_print($eventIds),
            'generatedAt' => date('Y-m-d H:i:s'),
            'attendanceDate' => $attendanceDate
        );
    }

    private function registration_participant_print_data()
    {
        $activeEvents = $this->registration->events_for_registration();
        $eventIds = array_map(function ($event) { return (int) $event['id']; }, $activeEvents);
        return array(
            'activeEvents' => $activeEvents,
            'rows' => $this->registration->participants_for_print($eventIds),
            'generatedAt' => date('Y-m-d H:i:s')
        );
    }

    private function registration_village_print_data()
    {
        $activeEvents = $this->registration->events_for_registration();
        $eventIds = array_map(function ($event) { return (int) $event['id']; }, $activeEvents);
        $rows = $eventIds ? $this->registration->get_all(array('event_ids' => $eventIds, 'active_only' => TRUE)) : array();
        $rows = $this->registration->with_regency_codes($rows);
        // Complete any legacy rows that were created before persistent MOU
        // numbering was deployed.  The resolver above supplies the official
        // kode_kota even when the old registration snapshot used a different
        // ID spelling; once assigned, the value is never recomputed.
        $rows = $this->registration->ensure_mou_numbers($rows);
        $eventOrder = array();
        foreach ($activeEvents as $eventIndex => $event) {
            $eventOrder[(int) (isset($event['id']) ? $event['id'] : 0)] = $eventIndex;
        }
        /* Keep the Excel export in the exact order used by the Data Desa
         * portrait preview: active-event order, district, then village. */
        usort($rows, function ($left, $right) use ($eventOrder) {
            $leftEventId = (int) (isset($left['event_id']) ? $left['event_id'] : 0);
            $rightEventId = (int) (isset($right['event_id']) ? $right['event_id'] : 0);
            $leftEventOrder = isset($eventOrder[$leftEventId]) ? $eventOrder[$leftEventId] : PHP_INT_MAX;
            $rightEventOrder = isset($eventOrder[$rightEventId]) ? $eventOrder[$rightEventId] : PHP_INT_MAX;
            if ($leftEventOrder !== $rightEventOrder) return $leftEventOrder <=> $rightEventOrder;
            foreach (array('district_name', 'village_name') as $field) {
                $comparison = strnatcasecmp(
                    trim((string) (isset($left[$field]) ? $left[$field] : '')),
                    trim((string) (isset($right[$field]) ? $right[$field] : ''))
                );
                if ($comparison !== 0) return $comparison;
            }
            return $leftEventId <=> $rightEventId;
        });
        return array(
            'activeEvents' => $activeEvents,
            'rows' => $rows,
            'generatedAt' => date('Y-m-d H:i:s')
        );
    }

    private function attendance_date_query()
    {
        $raw = $this->input->get('attendance_date', TRUE);
        if (!is_scalar($raw) || trim((string) $raw) === '') return NULL;
        $value = trim((string) $raw);
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : NULL;
    }

    /** Build print data for exactly one historical event. */
    private function event_registration_print_data($eventId)
    {
        $event = $this->registration->get_event_archive((int) $eventId);
        if (!$event) show_404();

        return array(
            // The report view keeps this legacy key to preserve the existing
            // active-event print endpoints.  Here it contains one archive
            // event, regardless of whether its current status is open/closed.
            'activeEvents' => array($event),
            'event' => $event,
            'rows' => $this->registration->participants_for_print(array((int) $event['id']), TRUE),
            'generatedAt' => date('Y-m-d H:i:s'),
            'isArchive' => TRUE
        );
    }

    /** Build an immutable print snapshot for exactly one registration. */
    private function registration_detail_print_data($registrationId)
    {
        $registration = $this->registration->get((int) $registrationId);
        if (!$registration) show_404();
        $event = $this->registration->get_event((int) $registration['event_id'], FALSE);
        if (!$event) show_404();

        return array(
            'registration' => $registration,
            'event' => $event,
            'generatedAt' => date('Y-m-d H:i:s')
        );
    }

    private function event_export_slug(array $event)
    {
        $fallback = 'event-' . (isset($event['id']) ? (int) $event['id'] : 0);
        $value = isset($event['code']) ? trim((string) $event['code']) : $fallback;
        $value = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $value), '-.');
        return $value !== '' ? $value : $fallback;
    }

    private function private_document_output($contentType, $body, $disposition = NULL)
    {
        $binary = in_array($contentType, array(
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ), TRUE);
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

    public function valid_date($value){if(!is_scalar($value))return FALSE;$value=(string)$value;$d=DateTime::createFromFormat('Y-m-d',$value);return $d&&$d->format('Y-m-d')===$value;}

    public function valid_money($value){return simp_money_decimal($value,FALSE)!==NULL;}

    private function money_value($value,$allowZero,$message)
    {
        $normalized=simp_money_decimal($value,$allowZero);
        if($normalized===NULL)throw new InvalidArgumentException($message);
        return $normalized;
    }

    private function prepare_inline_payments(array $postedGroups, $canRecordPayment, array &$uploadedFiles, array $villages, array $participantGroups, $billingMode)
    {
        $prepared=array();$seenTokens=array();$allowedVillages=array();
        foreach($villages as $village)$allowedVillages[(string)$village['village_id']]=TRUE;

        // Validate every enabled target before accepting any upload, so a forged
        // village/participant key cannot leave an unrelated proof file behind.
        foreach($postedGroups as $villageId=>$targets){
            if(!is_array($targets))continue;
            foreach($targets as $targetKey=>$payment){
                if(!is_array($payment)||empty($payment['enabled']))continue;
                if(!$canRecordPayment)throw new InvalidArgumentException('Akun Anda tidak memiliki izin untuk mencatat pembayaran awal.');
                $villageId=(string)$villageId;$targetKey=(string)$targetKey;
                if(empty($allowedVillages[$villageId]))throw new InvalidArgumentException('Tujuan desa pembayaran awal tidak valid.');
                if($billingMode==='per_participant'){
                    $participant=isset($participantGroups[$villageId][$targetKey])?$participantGroups[$villageId][$targetKey]:NULL;
                    if($targetKey==='village'||!is_array($participant)||trim((string)(isset($participant['full_name'])?$participant['full_name']:''))==='')throw new InvalidArgumentException('Tujuan peserta pembayaran awal tidak valid.');
                }elseif($targetKey!=='village'){
                    throw new InvalidArgumentException('Pembayaran event ini harus dicatat pada tingkat desa.');
                }
                if(!isset($payment['token'])||!is_scalar($payment['token']))throw new InvalidArgumentException('Identitas bukti pembayaran tidak valid.');
                $token=(string)$payment['token'];
                if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/',$token)||isset($seenTokens[$token]))throw new InvalidArgumentException('Identitas bukti pembayaran tidak valid.');
                $seenTokens[$token]=TRUE;
                if(!isset($payment['method'])||!is_scalar($payment['method']))throw new InvalidArgumentException('Metode pembayaran awal tidak valid.');
                $method=(string)$payment['method'];
                if(!in_array($method,array('cash','transfer','qris'),TRUE))throw new InvalidArgumentException('Metode pembayaran awal tidak valid.');
                $accountId=isset($payment['account_id'])&&is_scalar($payment['account_id'])?(string)$payment['account_id']:'';
                if(!ctype_digit($accountId)||(int)$accountId<1)throw new InvalidArgumentException('Akun penerima pembayaran awal tidak valid.');
                $rawAmount=isset($payment['amount'])&&is_scalar($payment['amount'])?(string)$payment['amount']:'';
                $normalizedAmount=$this->money_value($rawAmount,FALSE,'Nominal pembayaran awal harus lebih dari nol, maksimal 16 digit, dan maksimal 2 angka desimal.');
                $payment['amount']=$normalizedAmount;
                $paymentDate=isset($payment['payment_date'])&&is_scalar($payment['payment_date'])?(string)$payment['payment_date']:'';
                $date=DateTime::createFromFormat('Y-m-d',$paymentDate);
                if(!$date||$date->format('Y-m-d')!==$paymentDate)throw new InvalidArgumentException('Tanggal pembayaran awal tidak valid.');
                if(isset($payment['note'])&&!is_scalar($payment['note']))throw new InvalidArgumentException('Catatan pembayaran awal tidak valid.');
                if(strlen(trim((string)(isset($payment['note'])?$payment['note']:'')))>2000)throw new InvalidArgumentException('Catatan pembayaran maksimal 2.000 karakter.');
            }
        }
        foreach($postedGroups as $villageId=>$targets){
            if(!is_array($targets))continue;
            foreach($targets as $targetKey=>$payment){
                if(!is_array($payment)||empty($payment['enabled']))continue;
                $token=(string)$payment['token'];$method=(string)$payment['method'];
                $folder='payments/'.date('Y/m');$path=FCPATH.'uploads/'.$folder;
                if(!is_dir($path)&&!mkdir($path,0755,TRUE)&&!is_dir($path))throw new RuntimeException('Folder bukti pembayaran tidak dapat dibuat.');
                try{$proof=$this->upload_document('payment_proof_'.$token,$folder,$method!=='cash');}
                catch(RuntimeException $uploadError){throw $this->registration_upload_exception($uploadError);}
                if($proof)$uploadedFiles[]=$proof;
                $payment['amount']=$this->money_value(isset($payment['amount'])?$payment['amount']:NULL,FALSE,'Nominal pembayaran awal tidak valid.');
                $payment['proof_path']=$proof;
                $prepared[(string)$villageId][(string)$targetKey]=$payment;
            }
        }
        return $prepared;
    }

    private function cleanup_uploaded_files(array $files)
    {
        foreach($files as $relativePath){
            $relativePath=ltrim(str_replace('\\','/',(string)$relativePath),'/');
            if(strpos($relativePath,'uploads/payments/')!==0)continue;
            $fullPath=FCPATH.$relativePath;
            if(is_file($fullPath))@unlink($fullPath);
        }
    }

    public function verify_payment($paymentId)
    {
        $this->require_permission('payments.verify');$this->require_post();$decision=$this->input->post('decision',TRUE);
        $wantsJson=$this->input->is_ajax_request()||strpos(strtolower((string)$this->input->get_request_header('Accept')),'application/json')!==FALSE;
        try{
            // A closed/draft event is historical and must remain view-only.
            // Check this before the model review so a forged POST cannot
            // mutate a payment from an archive page.
            $paymentContext = $this->db->select('py.registration_id,e.status AS event_status')
                ->from('payments py')->join('training_events e', 'e.id=py.event_id')
                ->where('py.id', (int) $paymentId)->get()->row_array();
            if (!$paymentContext) throw new InvalidArgumentException('Pembayaran tidak ditemukan.');
            if ((string) $paymentContext['event_status'] !== 'open') {
                throw new InvalidArgumentException('Event sudah ditutup. Arsip registrasi bersifat hanya-baca.');
            }
            $result=$this->registration->review_payment($paymentId,$decision,(int)$this->currentUser['id']);
            try{$this->Audit_model->log('payment_'.$result['new_status'],'payment',$paymentId,array('from'=>$result['old_status'],'to'=>$result['new_status']));}catch(Throwable $ignored){}
            $message=$result['new_status']==='verified'?'Pembayaran '.$result['receipt_no'].' berhasil diverifikasi.':'Pembayaran '.$result['receipt_no'].' ditolak.';
            if($wantsJson)return $this->json(array('success'=>TRUE,'message'=>$message,'payment_id'=>(int)$paymentId,'registration_id'=>(int)$result['registration_id'],'status'=>$result['new_status']));
            return $this->redirect_with('registrasi/'.$result['registration_id'],'success',$message);
        }catch(Throwable $e){
            $payment=NULL;
            try{$payment=$this->db->select('registration_id')->where('id',(int)$paymentId)->get('payments')->row_array();}catch(Throwable $ignored){}
            $message=$this->registration_exception_message($e,'Status pembayaran belum dapat diperbarui. Silakan coba kembali.');
            if($wantsJson){if($e instanceof InvalidArgumentException)return $this->json(array('success'=>FALSE,'message'=>$message),422);log_message('error','Payment review AJAX failed: '.$e->getMessage());return $this->json(array('success'=>FALSE,'message'=>'Status pembayaran belum dapat diperbarui.'),500);}
            return $this->redirect_with($payment?'registrasi/'.$payment['registration_id']:'registrasi','error',$message);
        }
    }

    private function receipt_no(){for($i=0;$i<5;$i++){$no='PAY-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));if(!$this->db->where('receipt_no',$no)->count_all_results('payments'))return $no;}throw new RuntimeException('Nomor kuitansi gagal dibuat.');}
}
