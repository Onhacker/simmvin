<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_model');
    }

    public function index()
    {
        $this->require_permission('users.view');
        $users = $this->User_model->all();
        // Pengelolaan identitas dan hak individual hanya boleh dilakukan oleh
        // Super Admin. Izin users.manage yang keliru terpasang pada akun biasa
        // tidak boleh menjadi jalur eskalasi hak akses.
        $canManage = $this->Auth_model->can('users.manage') && $this->is_current_super_admin();
        $roles = $canManage ? $this->User_model->roles() : array();
        $permissions = $canManage ? $this->User_model->permissions() : array();
        $rolePermissionMap = $canManage ? $this->User_model->role_permission_map() : array();
        $userPermissionMap = array();
        if ($canManage) {
            foreach ($users as $row) {
                $state = $this->User_model->permission_state($row['id']);
                if ($state) {
                    $selected = array();
                    foreach ($state as $permissionId => $allowed) {
                        if ((int) $allowed === 1) $selected[] = (int) $permissionId;
                    }
                    $userPermissionMap[(int) $row['id']] = $selected;
                } else {
                    $roleId = (int) $row['role_id'];
                    $userPermissionMap[(int) $row['id']] = isset($rolePermissionMap[$roleId]) ? array_map('intval', $rolePermissionMap[$roleId]) : array();
                }
            }
        }
        $this->render('users/index', array(
            'pageTitle' => 'Pengguna',
            'users' => $users,
            'canManage' => $canManage,
            'roles' => $roles,
            'permissions' => $permissions,
            'rolePermissionMap' => $rolePermissionMap,
            'userPermissionMap' => $userPermissionMap,
            'pageScript' => 'users.js'
        ));
    }

    public function create()
    {
        $this->require_permission('users.manage');
        $this->require_super_admin_user_management();
        $wantsJson = $this->wants_json();
        $roles = $this->User_model->roles();
        if (!$roles) {
            if ($wantsJson) return $this->json(array('success' => FALSE, 'message' => 'Peran pengguna belum tersedia.'), 500);
            show_error('Belum ada peran yang dapat dipilih.', 500);
        }

        $permissions = $this->User_model->permissions();
        $rolePermissionMap = $this->User_model->role_permission_map();
        $defaultRoleId = isset($roles[0]) ? (int) $roles[0]['id'] : 0;
        $defaultPermissions = isset($rolePermissionMap[$defaultRoleId]) ? $rolePermissionMap[$defaultRoleId] : array();
        $selected = $this->permission_selection($permissions, $rolePermissionMap, $defaultPermissions);

        if ($this->input->method(TRUE) === 'POST') {
            $runtimeFailure = FALSE;
            $this->set_rules(TRUE);
            $roleRaw = $this->input->post('role_id', TRUE);
            $roleId = is_scalar($roleRaw) && ctype_digit((string) $roleRaw) ? (int) $roleRaw : 0;
            $invalidRole = !$this->User_model->role_exists($roleId);

            $username = trim($this->scalar_post('username'));
            $email = $this->normalise_email($this->scalar_post('email'));
            if ($this->User_model->username_exists($username)) {
                $this->form_validation->set_rules('username', 'Username', 'callback_duplicate_username');
            }
            if ($this->User_model->email_exists($email)) {
                $this->form_validation->set_rules('email', 'Email', 'callback_duplicate_email');
            }
            $selected = $this->posted_permissions($permissions);
            if ($this->is_super_admin_role($roles, $roleId)) {
                $selected = array_map('intval', array_column($permissions, 'id'));
            }
            $formValid = $this->form_validation->run();
            if ($invalidRole) $dataError = 'Peran yang dipilih tidak valid.';

            if ($formValid && !isset($dataError)) {
                try {
                    $userId = $this->User_model->create(array(
                        'role_id' => $roleId,
                        'name' => trim($this->scalar_post('name')),
                        'username' => $username,
                        'email' => $email,
                        'phone' => $this->nullable($this->scalar_post('phone')),
                        'password_hash' => password_hash($this->scalar_post('password', FALSE), PASSWORD_DEFAULT),
                        'is_active' => $this->input->post('is_active') ? 1 : 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ), $selected);
                    try {
                        $this->Audit_model->log('user_created', 'user', $userId, array('username' => $username, 'role_id' => $roleId));
                    } catch (Throwable $ignored) {}
                    if ($wantsJson) {
                        return $this->json(array(
                            'success' => TRUE,
                            'message' => 'Pengguna berhasil ditambahkan.',
                            'user_id' => (int) $userId
                        ));
                    }
                    $this->redirect_with('pengguna', 'success', 'Pengguna berhasil ditambahkan.');
                    return;
                } catch (Throwable $exception) {
                    $dataError = $this->user_error_message($exception);
                    $runtimeFailure = !($exception instanceof InvalidArgumentException);
                }
            }
            if ($wantsJson) {
                return $this->json(array(
                    'success' => FALSE,
                    'message' => $this->ajax_validation_message('Periksa kembali data pengguna.', isset($dataError) ? $dataError : NULL)
                ), !empty($runtimeFailure) ? 500 : 422);
            }
        }

        $this->render('users/form', array(
            'pageTitle' => 'Tambah Pengguna',
            'user' => NULL,
            'roles' => $roles,
            'permissions' => $permissions,
            'rolePermissionMap' => $rolePermissionMap,
            'selectedPermissionIds' => $selected,
            'errorMessage' => isset($dataError) ? $dataError : NULL
        ));
    }

    public function edit($id)
    {
        $this->require_permission('users.manage');
        $this->require_super_admin_user_management();
        $wantsJson = $this->wants_json();
        $user = $this->User_model->find($id);
        if (!$user) {
            if ($wantsJson) return $this->json(array('success' => FALSE, 'message' => 'Pengguna tidak ditemukan.'), 404);
            show_404();
        }

        $roles = $this->User_model->roles();
        $permissions = $this->User_model->permissions();
        $rolePermissionMap = $this->User_model->role_permission_map();
        $state = $this->User_model->permission_state($user['id']);
        $selected = array();
        foreach ($state as $permissionId => $allowed) if ($allowed === 1) $selected[] = $permissionId;
        if (!$state) $selected = isset($rolePermissionMap[(int) $user['role_id']]) ? $rolePermissionMap[(int) $user['role_id']] : array();

        if ($this->input->method(TRUE) === 'POST') {
            $runtimeFailure = FALSE;
            $this->set_rules(FALSE);
            $roleRaw = $this->input->post('role_id', TRUE);
            $roleId = is_scalar($roleRaw) && ctype_digit((string) $roleRaw) ? (int) $roleRaw : 0;
            $invalidRole = !$this->User_model->role_exists($roleId);
            $username = trim($this->scalar_post('username'));
            $email = $this->normalise_email($this->scalar_post('email'));
            if ($this->User_model->username_exists($username, $user['id'])) {
                $this->form_validation->set_rules('username', 'Username', 'callback_duplicate_username');
            }
            if ($this->User_model->email_exists($email, $user['id'])) {
                $this->form_validation->set_rules('email', 'Email', 'callback_duplicate_email');
            }
            $selected = $this->posted_permissions($permissions);
            if ($this->is_super_admin_role($roles, $roleId)) {
                $selected = array_map('intval', array_column($permissions, 'id'));
            }
            $requestedActive = $this->input->post('is_active') ? 1 : 0;
            if ((int) $user['id'] === (int) $this->currentUser['id'] && !$requestedActive) {
                $requestedActive = 1;
                $dataError = 'Akun yang sedang digunakan tidak dapat dinonaktifkan.';
            }
            $removesSuperAdmin = $user['role_slug'] === 'super-admin'
                && (!$this->is_super_admin_role($roles, $roleId) || !$requestedActive);
            if ($removesSuperAdmin && $this->active_super_admin_count() <= 1) {
                $dataError = 'Minimal satu Super Admin aktif harus tetap tersedia.';
            }

            $formValid = $this->form_validation->run();
            if ($invalidRole) $dataError = 'Peran yang dipilih tidak valid.';

            if ($formValid && !isset($dataError)) {
                try {
                    $data = array(
                        'role_id' => $roleId,
                        'name' => trim($this->scalar_post('name')),
                        'username' => $username,
                        'email' => $email,
                        'phone' => $this->nullable($this->scalar_post('phone')),
                        'is_active' => $requestedActive,
                        'updated_at' => date('Y-m-d H:i:s')
                    );
                    $password = $this->scalar_post('password', FALSE);
                    if ($password !== '') $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $this->User_model->update_user($user['id'], $data, $selected);
                    try {
                        $this->Audit_model->log('user_updated', 'user', $user['id'], array('username' => $username, 'role_id' => $roleId, 'is_active' => $requestedActive));
                    } catch (Throwable $ignored) {}
                    if ($wantsJson) {
                        return $this->json(array(
                            'success' => TRUE,
                            'message' => 'Perubahan pengguna berhasil disimpan.',
                            'user_id' => (int) $user['id']
                        ));
                    }
                    $this->redirect_with('pengguna', 'success', 'Perubahan pengguna berhasil disimpan.');
                    return;
                } catch (Throwable $exception) {
                    $dataError = $this->user_error_message($exception);
                    $runtimeFailure = !($exception instanceof InvalidArgumentException);
                }
            }
            if ($wantsJson) {
                return $this->json(array(
                    'success' => FALSE,
                    'message' => $this->ajax_validation_message('Periksa kembali data pengguna.', isset($dataError) ? $dataError : NULL)
                ), !empty($runtimeFailure) ? 500 : 422);
            }
        }

        $this->render('users/form', array(
            'pageTitle' => 'Ubah Pengguna',
            'user' => $user,
            'roles' => $roles,
            'permissions' => $permissions,
            'rolePermissionMap' => $rolePermissionMap,
            'selectedPermissionIds' => $selected,
            'errorMessage' => isset($dataError) ? $dataError : NULL
        ));
    }

    public function invalid_role($value) { return FALSE; }
    public function duplicate_username($value) { return FALSE; }
    public function duplicate_email($value) { return FALSE; }

    private function set_rules($passwordRequired)
    {
        $this->form_validation->set_rules('name', 'Nama lengkap', 'trim|required|max_length[120]');
        $this->form_validation->set_rules('role_id', 'Peran', 'required|integer');
        $this->form_validation->set_rules('username', 'Username', 'trim|required|min_length[3]|max_length[80]|alpha_dash');
        $this->form_validation->set_rules('email', 'Email', 'trim|max_length[160]|valid_email');
        $this->form_validation->set_rules('phone', 'Nomor HP', 'trim|max_length[30]');
        $passwordRules = $passwordRequired ? 'required|min_length[8]|max_length[200]' : 'min_length[8]|max_length[200]';
        $this->form_validation->set_rules('password', 'Kata sandi', $passwordRules);
        $this->form_validation->set_rules('password_confirmation', 'Konfirmasi kata sandi', ($passwordRequired ? 'required|' : '') . 'matches[password]');
        $this->form_validation->set_message('invalid_role', 'Peran yang dipilih tidak valid.');
        $this->form_validation->set_message('duplicate_username', 'Username sudah digunakan.');
        $this->form_validation->set_message('duplicate_email', 'Email sudah digunakan.');
    }

    private function posted_permissions(array $permissions)
    {
        $posted = (array) $this->input->post('permissions');
        $selected = array();
        foreach ($posted as $permissionId) {
            if (is_scalar($permissionId) && ctype_digit((string) $permissionId)) {
                $selected[(int) $permissionId] = TRUE;
            }
        }
        $valid = array();
        foreach ($permissions as $permission) {
            $id = (int) $permission['id'];
            if (isset($selected[$id])) $valid[] = $id;
        }
        return $valid;
    }

    private function permission_selection(array $permissions, array $rolePermissionMap, $fallback)
    {
        if ($this->input->method(TRUE) === 'POST') return $this->posted_permissions($permissions);
        if ($fallback !== NULL) return $fallback;
        $roleRaw = $this->input->post('role_id', TRUE);
        $roleId = is_scalar($roleRaw) && ctype_digit((string) $roleRaw) ? (int) $roleRaw : 0;
        return isset($rolePermissionMap[$roleId]) ? $rolePermissionMap[$roleId] : array();
    }

    private function normalise_email($email)
    {
        $email = strtolower(trim((string) $email));
        return $email === '' ? NULL : $email;
    }

    private function nullable($value)
    {
        $value = trim((string) $value);
        return $value === '' ? NULL : $value;
    }

    private function scalar_post($field, $xssClean = TRUE)
    {
        $value = $this->input->post($field, $xssClean);
        return is_scalar($value) ? (string) $value : '';
    }

    private function is_current_super_admin()
    {
        return $this->currentUser && isset($this->currentUser['role_slug'])
            && $this->currentUser['role_slug'] === 'super-admin';
    }

    private function require_super_admin_user_management()
    {
        if ($this->is_current_super_admin()) return;
        try {
            $this->Audit_model->log('access_denied', 'permission', NULL, array(
                'permission' => 'users.manage.super_admin', 'uri' => uri_string()
            ));
        } catch (Throwable $ignored) {}
        if ($this->wants_json()) {
            $this->json_exit(array('success'=>FALSE,'message'=>'Hanya Super Admin yang dapat mengelola pengguna.'), 403);
        }
        show_error('Hanya Super Admin yang dapat mengelola pengguna.', 403, 'Akses ditolak');
    }

    private function active_super_admin_count()
    {
        return (int) $this->db->from('users u')->join('roles r', 'r.id=u.role_id')
            ->where(array('u.is_active'=>1, 'r.slug'=>'super-admin'))->count_all_results();
    }

    private function is_super_admin_role(array $roles, $roleId)
    {
        foreach ($roles as $role) {
            if ((int) $role['id'] === (int) $roleId) return $role['slug'] === 'super-admin';
        }
        return FALSE;
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

    private function ajax_validation_message($fallback, $extra)
    {
        $message = $this->validation_message($fallback);
        if ($extra && $message !== $extra && strpos($message, $extra) === FALSE) {
            $message = ($message === $fallback ? '' : $message . "\n") . $extra;
        }
        return $message;
    }

    private function user_error_message(Throwable $exception)
    {
        if ($exception instanceof InvalidArgumentException) return $exception->getMessage();
        log_message('error', 'Pengguna AJAX gagal: ' . $exception->getMessage());
        return 'Pengguna belum dapat disimpan karena terjadi gangguan sistem.';
    }
}
