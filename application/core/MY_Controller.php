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
    // =========================================================
    // 1. CEK FILE
    // =========================================================
    if (
        !isset($_FILES[$field]) ||
        empty($_FILES[$field]['name'])
    ) {
        if ($required) {
            throw new RuntimeException(
                'Bukti transaksi wajib diunggah.'
            );
        }

        return NULL;
    }


    // =========================================================
    // 2. SIAPKAN FOLDER UPLOAD
    // =========================================================
    $uploadPath = FCPATH . 'uploads/' . $folder . '/';

    if (!is_dir($uploadPath)) {
        if (
            !mkdir($uploadPath, 0755, TRUE) &&
            !is_dir($uploadPath)
        ) {
            throw new RuntimeException(
                'Folder upload tidak dapat dibuat.'
            );
        }
    }


    // =========================================================
    // 3. CONFIG UPLOAD
    // =========================================================
    $config = array(
        'upload_path'   => $uploadPath,

        // File yang diperbolehkan
        'allowed_types' => 'jpg|jpeg|png|pdf',

        // 5 MB
        'max_size'      => 5120,

        // Nama file dibuat random
        'encrypt_name'  => TRUE,

        // Hilangkan spasi
        'remove_spaces' => TRUE
    );


    // =========================================================
    // 4. LOAD & RESET UPLOAD LIBRARY
    // =========================================================
    $this->load->library('upload');

    // TRUE = reset config lama
    $this->upload->initialize($config, TRUE);


    // =========================================================
    // 5. DEBUG SEBELUM UPLOAD
    // =========================================================
    $tmp = isset($_FILES[$field]['tmp_name'])
        ? $_FILES[$field]['tmp_name']
        : NULL;

    $runtimeMimes = get_mimes();

    $realMime = NULL;

    if (
        $tmp &&
        file_exists($tmp) &&
        function_exists('mime_content_type')
    ) {
        $realMime = @mime_content_type($tmp);
    }

    $imageInfo = FALSE;

    if (
        $tmp &&
        file_exists($tmp) &&
        function_exists('getimagesize')
    ) {
        $imageInfo = @getimagesize($tmp);
    }


    log_message(
        'error',
        'UPLOAD DEEP DEBUG: ' . json_encode(array(

            'environment' => ENVIRONMENT,

            'filename' =>
                isset($_FILES[$field]['name'])
                    ? $_FILES[$field]['name']
                    : NULL,

            'browser_mime' =>
                isset($_FILES[$field]['type'])
                    ? $_FILES[$field]['type']
                    : NULL,

            'real_mime' =>
                $realMime,

            'size' =>
                isset($_FILES[$field]['size'])
                    ? $_FILES[$field]['size']
                    : NULL,

            'tmp_exists' =>
                $tmp ? file_exists($tmp) : FALSE,

            'tmp_readable' =>
                $tmp ? is_readable($tmp) : FALSE,

            'getimagesize' =>
                $imageInfo,

            'allowed_types' =>
                $this->upload->allowed_types,

            'runtime_jpg_mimes' =>
                isset($runtimeMimes['jpg'])
                    ? $runtimeMimes['jpg']
                    : 'TIDAK ADA',

            'runtime_jpeg_mimes' =>
                isset($runtimeMimes['jpeg'])
                    ? $runtimeMimes['jpeg']
                    : 'TIDAK ADA',

            'runtime_png_mimes' =>
                isset($runtimeMimes['png'])
                    ? $runtimeMimes['png']
                    : 'TIDAK ADA',

            'runtime_pdf_mimes' =>
                isset($runtimeMimes['pdf'])
                    ? $runtimeMimes['pdf']
                    : 'TIDAK ADA',

            'production_mimes_exists' =>
                file_exists(
                    APPPATH .
                    'config/' .
                    ENVIRONMENT .
                    '/mimes.php'
                )
        ))
    );


    // =========================================================
    // 6. PROSES UPLOAD
    // =========================================================
    if (!$this->upload->do_upload($field)) {

        // Debug kondisi internal CI setelah gagal upload
        log_message(
            'error',
            'UPLOAD CI DEBUG: ' . json_encode(array(

                'file_name' =>
                    $this->upload->file_name,

                'file_type' =>
                    $this->upload->file_type,

                'file_ext' =>
                    $this->upload->file_ext,

                'file_size' =>
                    $this->upload->file_size,

                // Abaikan MIME mapping,
                // tetapi tetap cek extension/gambar
                'allowed_ignore_mime' =>
                    $this->upload->is_allowed_filetype(TRUE),

                // Pemeriksaan lengkap CI
                'allowed_full' =>
                    $this->upload->is_allowed_filetype(FALSE),

                'error' =>
                    strip_tags(
                        $this->upload->display_errors('', '')
                    )
            ))
        );


        throw new RuntimeException(
            strip_tags(
                $this->upload->display_errors('', '')
            )
        );
    }


    // =========================================================
    // 7. FILE BERHASIL DIUPLOAD
    // =========================================================
    $file = $this->upload->data();


    log_message(
        'error',
        'UPLOAD SUCCESS DEBUG: ' . json_encode(array(
            'file_name' => $file['file_name'],
            'file_type' => $file['file_type'],
            'file_ext'  => $file['file_ext'],
            'file_size' => $file['file_size']
        ))
    );


    // =========================================================
    // 8. RETURN PATH
    // =========================================================
    return 'uploads/' .
        $folder .
        '/' .
        $file['file_name'];
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
