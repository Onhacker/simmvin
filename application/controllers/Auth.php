<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends Public_Controller
{
    public function login()
    {
        if ($this->currentUser) redirect('dashboard');
        $data = array('pageTitle' => 'Masuk - MVIN');
        $accept = strtolower((string) $this->input->get_request_header('Accept'));
        $isAjax = $this->input->is_ajax_request() || strpos($accept, 'application/json') !== FALSE;
        if ($this->input->method(TRUE) === 'POST') {
            $this->form_validation->set_rules('identity', 'Username atau email', 'trim|required|max_length[120]');
            $this->form_validation->set_rules('password', 'Kata sandi', 'required|max_length[200]');
            if ($this->form_validation->run()) {
                $identity = $this->input->post('identity', TRUE);
                $ipAddress = $this->input->ip_address();
                if ($this->Auth_model->login_is_blocked($identity, $ipAddress)) {
                    $data['error'] = 'Terlalu banyak percobaan masuk. Silakan coba kembali dalam 10 menit.';
                    if ($isAjax) return $this->json(array('success' => FALSE, 'message' => $data['error']), 429);
                    $this->load->view('auth/login', $data);
                    return;
                }
                $user = $this->Auth_model->attempt($identity, (string) $this->input->post('password'));
                if ($user) {
                    $this->Auth_model->clear_login_failures($identity, $ipAddress);
                    $this->Audit_model->log('login_success', 'user', $user['id']);
                    $intended = $this->session->userdata('intended_url');
                    $this->session->unset_userdata('intended_url');
                    $redirectUrl = $intended ?: site_url('dashboard');
                    if ($isAjax) return $this->json(array('success' => TRUE, 'message' => 'Berhasil masuk.', 'redirect' => $redirectUrl));
                    redirect($intended ?: 'dashboard');
                }
                $this->Auth_model->record_login_failure($identity, $ipAddress);
                $data['error'] = 'Username/email atau kata sandi tidak sesuai.';
                $this->Audit_model->log('login_failed', 'auth', NULL, array('identity' => $identity));
                if ($isAjax) return $this->json(array('success' => FALSE, 'message' => $data['error']), 422);
            }
            elseif ($isAjax) {
                return $this->json(array('success' => FALSE, 'message' => trim(strip_tags(validation_errors())) ?: 'Form login belum lengkap.'), 422);
            }
        }
        $this->load->view('auth/login', $data);
    }

    public function logout()
    {
        // Logging out mutates the session and audit trail.  Require an
        // explicit CSRF-protected POST so a third-party image/link cannot
        // silently log an authenticated user out.
        $this->require_post();
        if ($this->currentUser) $this->Audit_model->log('logout', 'user', $this->currentUser['id']);
        $this->Auth_model->logout();
        redirect('login');
    }
}
