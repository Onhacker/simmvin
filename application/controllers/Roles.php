<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Roles extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Role_model');
    }

    public function index()
    {
        $this->require_permission('roles.manage');
        $this->require_super_admin_role_management();
        $roles = $this->Role_model->all();
        $permissions = $this->Role_model->permissions();
        $rolePermissionMap = array();
        foreach ($roles as $row) {
            $rolePermissionMap[(int) $row['id']] = $this->Role_model->permission_ids($row['id']);
        }
        $this->render('roles/index', array(
            'pageTitle' => 'Peran & Hak Akses',
            'roles' => $roles,
            'permissions' => $permissions,
            'rolePermissionMap' => $rolePermissionMap,
            'pageScript' => 'roles.js'
        ));
    }

    public function edit($id)
    {
        $this->require_permission('roles.manage');
        $this->require_super_admin_role_management();
        $wantsJson = $this->wants_json();
        $role = $this->Role_model->find($id);
        if (!$role) {
            if ($wantsJson) return $this->json(array('success' => FALSE, 'message' => 'Peran tidak ditemukan.'), 404);
            show_404();
        }

        $permissions = $this->Role_model->permissions();
        $selected = $this->Role_model->permission_ids($role['id']);
        if ($role['slug'] === 'super-admin') {
            $selected = array_map('intval', array_column($permissions, 'id'));
        }

        if ($this->input->method(TRUE) === 'POST') {
            $this->form_validation->set_rules('name', 'Nama peran', 'trim|required|max_length[80]');
            $this->form_validation->set_rules('slug', 'Kode peran', 'trim|required|max_length[80]|alpha_dash');
            $this->form_validation->set_rules('description', 'Deskripsi', 'trim|max_length[255]');
            $nameRaw = $this->input->post('name', TRUE);
            $slugRaw = $this->input->post('slug', TRUE);
            $descriptionRaw = $this->input->post('description', TRUE);
            $permissionsRaw = $this->input->post('permissions');
            $invalidShape = ($nameRaw !== NULL && !is_scalar($nameRaw))
                || ($slugRaw !== NULL && !is_scalar($slugRaw))
                || ($descriptionRaw !== NULL && !is_scalar($descriptionRaw))
                || ($permissionsRaw !== NULL && !is_array($permissionsRaw));
            if (is_array($permissionsRaw)) {
                foreach ($permissionsRaw as $permissionId) {
                    if (!is_scalar($permissionId)) { $invalidShape = TRUE; break; }
                }
            }
            if ($invalidShape) $dataError = 'Data peran dan hak akses tidak valid.';

            $name = is_scalar($nameRaw) ? trim((string)$nameRaw) : '';
            $slug = is_scalar($slugRaw) ? strtolower(trim((string)$slugRaw)) : '';

            if ((int) $role['is_system'] === 1) {
                $name = $role['name'];
                $slug = $role['slug'];
            } else {
                if ($this->Role_model->name_exists($name, $role['id'])) {
                    $this->form_validation->set_rules('name', 'Nama peran', 'callback_duplicate_name');
                }
                if ($this->Role_model->slug_exists($slug, $role['id'])) {
                    $this->form_validation->set_rules('slug', 'Kode peran', 'callback_duplicate_slug');
                }
            }

            $selected = $this->posted_permissions($permissions);
            if ($role['slug'] === 'super-admin') {
                $selected = array_map('intval', array_column($permissions, 'id'));
            }
            if ($this->form_validation->run() && !$invalidShape) {
                $originalDbDebug = $this->db->db_debug;
                $this->db->db_debug = FALSE;
                try {
                    $this->Role_model->update_role($role['id'], array(
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $this->nullable($descriptionRaw),
                        'updated_at' => date('Y-m-d H:i:s')
                    ), $selected);
                    try {
                        $this->Audit_model->log('role_updated', 'role', $role['id'], array('name' => $name, 'permission_count' => count($selected)));
                    } catch (Throwable $ignored) {}
                    if ($wantsJson) {
                        return $this->json(array(
                            'success' => TRUE,
                            'message' => 'Peran dan hak akses berhasil disimpan.',
                            'role_id' => (int) $role['id']
                        ));
                    }
                    $this->redirect_with('peran', 'success', 'Peran dan hak akses berhasil disimpan.');
                    return;
                } catch (Throwable $exception) {
                    $dataError = $this->role_error_message($exception);
                    $errorStatus = $exception instanceof InvalidArgumentException ? 422 : 500;
                } finally {
                    $this->db->db_debug = $originalDbDebug;
                }
            }
            if ($wantsJson) {
                return $this->json(array(
                    'success' => FALSE,
                    'message' => isset($dataError) ? $dataError : $this->validation_message('Periksa kembali data peran.')
                ), isset($errorStatus) ? (int)$errorStatus : 422);
            }
        }

        $this->render('roles/form', array(
            'pageTitle' => 'Ubah Peran',
            'role' => $role,
            'permissions' => $permissions,
            'selectedPermissionIds' => $selected,
            'errorMessage' => isset($dataError) ? $dataError : NULL
        ));
    }

    public function duplicate_name($value) { return FALSE; }
    public function duplicate_slug($value) { return FALSE; }

    private function posted_permissions(array $permissions)
    {
        $posted = array();
        foreach ((array)$this->input->post('permissions') as $permissionId) {
            if (!is_scalar($permissionId)) continue;
            $permissionId = trim((string)$permissionId);
            if (ctype_digit($permissionId) && (int)$permissionId > 0) $posted[(int)$permissionId] = TRUE;
        }
        $selected = array();
        foreach ($permissions as $permission) {
            $id = (int) $permission['id'];
            if (isset($posted[$id])) $selected[] = $id;
        }
        return $selected;
    }

    private function nullable($value)
    {
        if ($value !== NULL && !is_scalar($value)) return NULL;
        $value = trim((string) $value);
        return $value === '' ? NULL : $value;
    }

    private function wants_json()
    {
        $accept = strtolower((string) $this->input->get_request_header('Accept'));
        return $this->input->is_ajax_request() || strpos($accept, 'application/json') !== FALSE;
    }

    private function require_super_admin_role_management()
    {
        if ($this->currentUser && isset($this->currentUser['role_slug']) &&
            $this->currentUser['role_slug'] === 'super-admin') return;
        try {
            $this->Audit_model->log('access_denied', 'permission', NULL, array(
                'permission' => 'roles.manage.super_admin', 'uri' => uri_string()
            ));
        } catch (Throwable $ignored) {}
        if ($this->wants_json()) {
            $this->json_exit(array('success'=>FALSE,'message'=>'Hanya Super Admin yang dapat mengelola peran dan hak akses.'), 403);
        }
        show_error('Hanya Super Admin yang dapat mengelola peran dan hak akses.', 403, 'Akses ditolak');
    }

    private function validation_message($fallback)
    {
        $message = trim(strip_tags(validation_errors('', "\n")));
        return $message !== '' ? $message : $fallback;
    }

    private function role_error_message(Throwable $exception)
    {
        if ($exception instanceof InvalidArgumentException) return $exception->getMessage();
        log_message('error', 'Peran AJAX gagal: ' . $exception->getMessage());
        return 'Peran belum dapat disimpan karena terjadi gangguan sistem.';
    }
}
