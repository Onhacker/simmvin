<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Events extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Training_model', 'training');
    }

    public function index()
    {
        $this->require_permission('events.view');
        $filters = array(
            'q' => trim((string) $this->input->get('q', TRUE)),
            'status' => trim((string) $this->input->get('status', TRUE))
        );
        $this->render('events/index', array(
            'pageTitle' => 'Event Pelatihan',
            'events' => $this->training->get_all($filters),
            'filters' => $filters
        ));
    }

    public function show($id)
    {
        $this->require_permission('events.view');
        $event = $this->training->get($id);
        if (!$event) show_404();
        $this->render('events/show', array('pageTitle' => 'Detail Event', 'event' => $event, 'pageScript' => 'events.js'));
    }

    public function create()
    {
        $this->require_permission('events.create');
        $wantsJson = $this->wants_json();
        $data = array('pageTitle' => 'Tambah Event', 'event' => NULL, 'selectedRegions' => array());
        if ($this->input->method(TRUE) === 'POST') {
            if ($this->validate_form()) {
                try {
                    $regions = $this->training->validate_regencies($this->input->post('region_pairs'));
                    $eventId = $this->training->create_event($this->event_payload(TRUE), $regions);
                    try {
                        $this->Audit_model->log('event_created', 'training_event', $eventId, array('regencies' => array_column($regions, 'regency_id')));
                    } catch (Throwable $ignored) {}
                    if ($wantsJson) {
                        return $this->json(array('success' => TRUE, 'message' => 'Event berhasil dibuat.', 'event_id' => (int) $eventId));
                    }
                    return $this->redirect_with('event/' . $eventId, 'success', 'Event berhasil dibuat.');
                } catch (Throwable $e) {
                    $data['error'] = $this->event_error_message($e);
                    $data['errorStatus'] = $e instanceof InvalidArgumentException ? 422 : 500;
                }
            }
            if ($wantsJson) {
                return $this->json(
                    array('success' => FALSE, 'message' => !empty($data['error']) ? $data['error'] : $this->validation_message('Periksa kembali data event.')),
                    isset($data['errorStatus']) ? (int) $data['errorStatus'] : 422
                );
            }
            $data['selectedRegions'] = (array) $this->input->post('region_pairs');
        }
        $data['pageScript'] = 'events.js';
        $this->render('events/form', $data);
    }

    public function edit($id)
    {
        $this->require_permission('events.edit');
        $wantsJson = $this->wants_json();
        $event = $this->training->get($id);
        if (!$event) {
            if ($wantsJson) return $this->json(array('success' => FALSE, 'message' => 'Event tidak ditemukan.'), 404);
            show_404();
        }
        $data = array('pageTitle' => 'Ubah Event', 'event' => $event, 'selectedRegions' => $event['regencies']);
        if ($this->input->method(TRUE) === 'POST') {
            if ($this->validate_form((int) $id)) {
                try {
                    $regions = $this->training->validate_regencies($this->input->post('region_pairs'));
                    $this->training->update_event($id, $this->event_payload(FALSE), $regions);
                    try {
                        $this->Audit_model->log('event_updated', 'training_event', $id, array('regencies' => array_column($regions, 'regency_id')));
                    } catch (Throwable $ignored) {}
                    if ($wantsJson) {
                        return $this->json(array('success' => TRUE, 'message' => 'Perubahan event berhasil disimpan.', 'event_id' => (int) $id));
                    }
                    return $this->redirect_with('event/' . (int) $id, 'success', 'Perubahan event berhasil disimpan.');
                } catch (Throwable $e) {
                    $data['error'] = $this->event_error_message($e);
                    $data['errorStatus'] = $e instanceof InvalidArgumentException ? 422 : 500;
                }
            }
            if ($wantsJson) {
                return $this->json(
                    array('success' => FALSE, 'message' => !empty($data['error']) ? $data['error'] : $this->validation_message('Periksa kembali data event.')),
                    isset($data['errorStatus']) ? (int) $data['errorStatus'] : 422
                );
            }
            $data['selectedRegions'] = (array) $this->input->post('region_pairs');
        }
        $data['pageScript'] = 'events.js';
        $this->render('events/form', $data);
    }

    public function activate($id)
    {
        $this->require_permission('events.activate');
        $this->require_post();
        $wantsJson = $this->wants_json();
        try {
            $result = $this->training->activate_event((int) $id);
            $message = 'Event sudah aktif.';
            if ($result['changed']) {
                $reopened = $result['from'] === 'closed';
                $this->Audit_model->log($reopened ? 'event_reopened' : 'event_activated', 'training_event', (int) $id, array(
                    'from' => $result['from'],
                    'to' => $result['to']
                ));
                $message = $reopened
                    ? 'Event berhasil dibuka kembali. Registrasi dan penambahan peserta kembali aktif.'
                    : 'Event berhasil diaktifkan. Registrasi peserta sekarang dibuka.';
            }
            if ($wantsJson) return $this->json(array('success'=>TRUE,'message'=>$message,'event_id'=>(int)$id,'status'=>'open'));
            return $this->redirect_with('event/' . (int) $id, 'success', $message);
        } catch (Throwable $e) {
            $message = $this->event_error_message($e, 'Event belum dapat diaktifkan.');
            if ($wantsJson) {
                return $this->json(
                    array('success'=>FALSE,'message'=>$message),
                    $e instanceof InvalidArgumentException ? 422 : 500
                );
            }
            return $this->redirect_with('event/' . (int) $id, 'error', $message);
        }
    }

    public function close($id)
    {
        $this->require_permission('events.activate');
        $this->require_post();
        $wantsJson = $this->wants_json();
        try {
            $result = $this->training->close_event((int) $id);
            $message = 'Event sudah ditutup.';
            if ($result['changed']) {
                $this->Audit_model->log('event_closed', 'training_event', (int) $id, array(
                    'from' => $result['from'],
                    'to' => $result['to']
                ));
                $message = 'Event berhasil ditutup. Registrasi baru tidak dapat ditambahkan.';
            }
            if ($wantsJson) return $this->json(array('success'=>TRUE,'message'=>$message,'event_id'=>(int)$id,'status'=>'closed'));
            return $this->redirect_with('event/' . (int) $id, 'success', $message);
        } catch (Throwable $e) {
            $message = $this->event_error_message($e, 'Event belum dapat ditutup.');
            if ($wantsJson) {
                return $this->json(
                    array('success'=>FALSE,'message'=>$message),
                    $e instanceof InvalidArgumentException ? 422 : 500
                );
            }
            return $this->redirect_with('event/' . (int) $id, 'error', $message);
        }
    }

    private function wants_json()
    {
        $accept = strtolower((string) $this->input->get_request_header('Accept'));
        return $this->input->is_ajax_request() || strpos($accept, 'application/json') !== FALSE;
    }

    private function validation_message($fallback)
    {
        $message = trim(strip_tags(validation_errors('', "\n")));
        return $message !== '' ? $message : $fallback;
    }

    private function event_error_message(Throwable $exception, $fallback = 'Event belum dapat disimpan. Periksa data lalu coba lagi.')
    {
        if ($exception instanceof InvalidArgumentException) {
            return $exception->getMessage();
        }
        log_message('error', 'Event AJAX gagal: ' . $exception->getMessage());
        return $fallback;
    }

    private function validate_form($excludeId = 0)
    {
        $this->form_validation->set_rules('code', 'Kode event', 'trim|required|max_length[40]|regex_match[/^[A-Za-z0-9._-]+$/]|callback_code_unique[' . (int) $excludeId . ']');
        $this->form_validation->set_rules('name', 'Nama event', 'trim|required|max_length[180]');
        $this->form_validation->set_rules('start_date', 'Tanggal mulai', 'trim|required|callback_valid_date');
        $this->form_validation->set_rules('end_date', 'Tanggal selesai', 'trim|required|callback_valid_date|callback_end_after_start');
        $this->form_validation->set_rules('billing_mode', 'Mode pembayaran', 'trim|required|in_list[per_village,per_participant,per_village_extra]');
        $billingModeRaw = $this->input->post('billing_mode', TRUE);
        $billingMode = is_scalar($billingModeRaw) ? (string) $billingModeRaw : '';
        if (in_array($billingMode, array('per_village', 'per_village_extra'), TRUE)) {
            $this->form_validation->set_rules('village_fee', 'Biaya paket desa', 'trim|required|callback_valid_money');
        }
        if (in_array($billingMode, array('per_participant', 'per_village_extra'), TRUE)) {
            $participantLabel = $billingMode === 'per_village_extra' ? 'Biaya peserta tambahan' : 'Biaya per peserta';
            $this->form_validation->set_rules('participant_fee', $participantLabel, 'trim|required|callback_valid_money');
        }
        if ($billingMode === 'per_village_extra') {
            $this->form_validation->set_rules('included_participant_count', 'Jumlah peserta dalam paket', 'trim|required|integer|greater_than[0]|less_than_equal_to[65535]');
        }
        $this->form_validation->set_rules('location', 'Lokasi', 'trim|required|max_length[180]');
        $this->form_validation->set_rules('address', 'Alamat', 'trim|max_length[2000]');
        $this->form_validation->set_rules('notes', 'Catatan', 'trim|max_length[4000]');
        $this->form_validation->set_message('code_unique', 'Kode event sudah digunakan.');
        $this->form_validation->set_message('valid_date', 'Tanggal harus menggunakan format yang valid.');
        $this->form_validation->set_message('end_after_start', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        $this->form_validation->set_message('valid_money', 'Nominal harus lebih dari nol, maksimal 16 digit, dan maksimal 2 angka desimal.');
        return $this->form_validation->run();
    }

    public function valid_date($value)
    {
        if (!is_scalar($value)) return FALSE;
        $date = DateTime::createFromFormat('Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === (string) $value;
    }

    public function valid_money($value)
    {
        return simp_money_decimal($value, FALSE) !== NULL;
    }

    public function code_unique($value, $excludeId)
    {
        if (!is_scalar($value)) return FALSE;
        return !$this->training->code_exists(strtoupper(trim((string) $value)), (int) $excludeId);
    }

    public function end_after_start($value)
    {
        $startDate = $this->input->post('start_date', TRUE);
        if (!is_scalar($value) || !is_scalar($startDate)) return FALSE;
        return strtotime((string) $value) >= strtotime((string) $startDate);
    }

    private function event_payload($isCreate)
    {
        $mode = (string) $this->input->post('billing_mode', TRUE);
        $usesVillageFee = in_array($mode, array('per_village', 'per_village_extra'), TRUE);
        $usesParticipantFee = in_array($mode, array('per_participant', 'per_village_extra'), TRUE);
        $payload = array(
            'code' => strtoupper(trim((string) $this->input->post('code', TRUE))),
            'name' => trim((string) $this->input->post('name', TRUE)),
            'start_date' => $this->input->post('start_date', TRUE),
            'end_date' => $this->input->post('end_date', TRUE),
            'billing_mode' => $mode,
            'village_fee' => $usesVillageFee ? (simp_money_decimal($this->input->post('village_fee', TRUE), FALSE) ?: '0.00') : '0.00',
            'participant_fee' => $usesParticipantFee ? (simp_money_decimal($this->input->post('participant_fee', TRUE), FALSE) ?: '0.00') : '0.00',
            'included_participant_count' => $mode === 'per_village_extra' ? (int) $this->input->post('included_participant_count') : 0,
            'location' => trim((string) $this->input->post('location', TRUE)),
            'address' => trim((string) $this->input->post('address', TRUE)) ?: NULL,
            'notes' => trim((string) $this->input->post('notes', TRUE)) ?: NULL,
            'updated_at' => date('Y-m-d H:i:s')
        );
        if ($isCreate) {
            $payload['status'] = 'draft';
            $payload['created_by'] = isset($this->currentUser['id']) ? (int) $this->currentUser['id'] : NULL;
        }
        return $payload;
    }
}
