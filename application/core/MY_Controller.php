<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    protected $currentUser = NULL;

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('Auth_model', 'Audit_model'));
        $this->currentUser = $this->Auth_model->current_user();
    }

    protected function render($view, array $data = array())
    {
        $data['currentUser'] = $this->currentUser;
        $data['pageTitle'] = isset($data['pageTitle']) ? $data['pageTitle'] : 'MVIN';
        $data['contentView'] = $view;
        $this->load->view('layouts/app', $data);
    }

    protected function json($payload, $status = 200)
    {
        $payload['csrf'] = array(
            'name' => $this->security->get_csrf_token_name(),
            'hash' => $this->security->get_csrf_hash()
        );
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Send a JSON response immediately and terminate the request.
     *
     * CI normally flushes the output object after the controller method
     * returns.  Authentication/permission guards run in a constructor, so
     * they must flush explicitly before exiting; otherwise AJAX callers get
     * a 401/403 with an empty body.
     */
    protected function json_exit($payload, $status = 200)
    {
        $this->json($payload, $status);
        $this->output->_display();
        exit;
    }

    protected function require_post()
    {
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            $accept = strtolower((string) $this->input->get_request_header('Accept'));
            if ($this->input->is_ajax_request() || strpos($accept, 'application/json') !== FALSE) {
                $this->json_exit(array(
                    'success' => FALSE,
                    'message' => 'Metode permintaan tidak diizinkan.'
                ), 405);
            }
            show_error('Metode permintaan tidak diizinkan.', 405);
        }
    }

    protected function redirect_with($url, $type, $message)
    {
        $this->session->set_flashdata($type, $message);
        redirect($url);
    }

   protected function upload_document($field, $folder, $required = FALSE)
{
    if (empty($_FILES[$field]['name'])) {
        if ($required) {
            throw new RuntimeException('Bukti transaksi wajib diunggah.');
        }

        return NULL;
    }

    $uploadPath = FCPATH . 'uploads/' . $folder . '/';

    if (!is_dir($uploadPath)) {
        if (!mkdir($uploadPath, 0755, TRUE) && !is_dir($uploadPath)) {
            throw new RuntimeException('Folder upload tidak dapat dibuat.');
        }
    }

    $config = array(
        'upload_path'      => $uploadPath,
        'allowed_types'    => 'jpg|jpeg|png|pdf',
        'max_size'         => 5120,
        'encrypt_name'     => TRUE,
        'remove_spaces'    => TRUE
    );

    // Jangan kirim config saat load.
    $this->load->library('upload');

    // Paksa reset config setiap kali upload.
    $this->upload->initialize($config, TRUE);

    log_message('error', 'UPLOAD CONFIG DEBUG: '.json_encode(array(
        'allowed_types' => $this->upload->allowed_types,
        'upload_path'   => $this->upload->upload_path,
        'filename'      => $_FILES[$field]['name'],
        'browser_mime'  => $_FILES[$field]['type'],
        'real_mime'     => mime_content_type($_FILES[$field]['tmp_name'])
    )));

    $tmp = $_FILES[$field]['tmp_name'];
$runtimeMimes = get_mimes();

log_message('error', 'UPLOAD DEEP DEBUG: ' . json_encode(array(
    'environment' => ENVIRONMENT,
    'tmp_exists' => file_exists($tmp),
    'tmp_readable' => is_readable($tmp),

    'mime_content_type' => mime_content_type($tmp),

    'getimagesize' => @getimagesize($tmp),

    'runtime_jpg_mimes' => isset($runtimeMimes['jpg'])
        ? $runtimeMimes['jpg']
        : 'TIDAK ADA',

    'ci_jpg_mimes' => $this->upload->mimes_types('jpg')
)));

    if (!$this->upload->do_upload($field)) {

        log_message('error', 'UPLOAD CI DEBUG: '.json_encode(array(
            'file_name' => $this->upload->file_name,
            'file_type' => $this->upload->file_type,
            'file_ext'  => $this->upload->file_ext,
            'file_size' => $this->upload->file_size,
            'error'     => strip_tags(
                $this->upload->display_errors('', '')
            )
        )));

        throw new RuntimeException(
            strip_tags($this->upload->display_errors('', ''))
        );
    }

    $file = $this->upload->data();

    return 'uploads/' . $folder . '/' . $file['file_name'];
}
}

class Public_Controller extends MY_Controller
{
}

class App_Controller extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->currentUser) {
            $accept = strtolower((string) $this->input->get_request_header('Accept'));
            if ($this->input->is_ajax_request() || strpos($accept, 'application/json') !== FALSE) {
                $this->json_exit(array('success'=>FALSE,'message'=>'Sesi Anda telah berakhir. Silakan masuk kembali.'), 401);
            }
            $this->session->set_userdata('intended_url', current_url());
            redirect('login');
        }
    }

    protected function require_permission($code)
    {
        if (!$this->Auth_model->can($code)) {
            $this->Audit_model->log('access_denied', 'permission', NULL, array('permission' => $code, 'uri' => uri_string()));
            $accept = strtolower((string) $this->input->get_request_header('Accept'));
            if ($this->input->is_ajax_request() || strpos($accept, 'application/json') !== FALSE) {
                $this->json_exit(array('success'=>FALSE,'message'=>'Anda tidak memiliki izin untuk tindakan ini.'), 403);
            }
            show_error('Anda tidak memiliki hak akses untuk membuka modul ini.', 403, 'Akses ditolak');
        }
    }
}
