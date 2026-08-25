<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    public function all()
    {
        return $this->db
            ->select('u.id,u.role_id,u.name,u.username,u.email,u.phone,u.is_active,u.last_login_at,u.created_at,r.name AS role_name,r.slug AS role_slug')
            ->from('users u')
            ->join('roles r', 'r.id=u.role_id')
            ->order_by('u.name', 'ASC')
            ->get()
            ->result_array();
    }

    public function find($id)
    {
        return $this->db
            ->select('u.id,u.role_id,u.name,u.username,u.email,u.phone,u.is_active,u.last_login_at,u.created_at,u.updated_at,r.name AS role_name,r.slug AS role_slug')
            ->from('users u')
            ->join('roles r', 'r.id=u.role_id')
            ->where('u.id', (int) $id)
            ->get()
            ->row_array();
    }

    public function roles()
    {
        return $this->db
            ->select('id,name,slug,description,is_system')
            ->from('roles')
            ->order_by('is_system', 'DESC')
            ->order_by('name', 'ASC')
            ->get()
            ->result_array();
    }

    public function permissions()
    {
        return $this->db
            ->select('id,module,code,name,description')
            ->from('permissions')
            ->order_by('module', 'ASC')
            ->order_by('name', 'ASC')
            ->get()
            ->result_array();
    }

    public function role_permission_map()
    {
        $rows = $this->db
            ->select('role_id,permission_id')
            ->from('role_permissions')
            ->get()
            ->result_array();
        $map = array();
        foreach ($rows as $row) {
            $roleId = (int) $row['role_id'];
            if (!isset($map[$roleId])) $map[$roleId] = array();
            $map[$roleId][] = (int) $row['permission_id'];
        }
        return $map;
    }

    public function permission_state($userId)
    {
        $rows = $this->db
            ->select('permission_id,allowed')
            ->from('user_permissions')
            ->where('user_id', (int) $userId)
            ->get()
            ->result_array();
        $state = array();
        foreach ($rows as $row) {
            $state[(int) $row['permission_id']] = (int) $row['allowed'];
        }
        return $state;
    }

    public function role_exists($id)
    {
        return $this->db->where('id', (int) $id)->count_all_results('roles') > 0;
    }

    public function username_exists($username, $exceptId = NULL)
    {
        $this->db->where('username', $username);
        if ($exceptId !== NULL) $this->db->where('id !=', (int) $exceptId);
        return $this->db->count_all_results('users') > 0;
    }

    public function email_exists($email, $exceptId = NULL)
    {
        if ($email === NULL || $email === '') return FALSE;
        $this->db->where('email', $email);
        if ($exceptId !== NULL) $this->db->where('id !=', (int) $exceptId);
        return $this->db->count_all_results('users') > 0;
    }

    public function create(array $data, array $allowedPermissionIds)
    {
        $this->db->trans_begin();
        $this->db->insert('users', $data);
        $userId = (int) $this->db->insert_id();
        $this->replace_permissions($userId, $allowedPermissionIds);
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            throw new RuntimeException('Pengguna gagal disimpan.');
        }
        if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi pengguna gagal diselesaikan.'); }
        return $userId;
    }

    public function update_user($id, array $data, array $allowedPermissionIds)
    {
        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $current = $this->db->query(
                'SELECT u.id,u.is_active,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? FOR UPDATE',
                array((int) $id)
            )->row_array();
            $nextRole = $this->db->query('SELECT id,slug FROM roles WHERE id=? FOR UPDATE', array((int) $data['role_id']))->row_array();
            if (!$current || !$nextRole) throw new InvalidArgumentException('Pengguna atau peran tidak ditemukan.');

            // Lock every active Super Admin so two concurrent requests cannot
            // demote the last administrators at the same time.
            $activeSuperAdmins = $this->db->query(
                "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.slug='super-admin' ORDER BY u.id FOR UPDATE"
            )->result_array();
            $removesActiveSuperAdmin = $current['role_slug'] === 'super-admin' && (int) $current['is_active'] === 1
                && ($nextRole['slug'] !== 'super-admin' || empty($data['is_active']));
            if ($removesActiveSuperAdmin && count($activeSuperAdmins) <= 1) {
                throw new InvalidArgumentException('Minimal satu Super Admin aktif harus tetap tersedia.');
            }

            if (!$this->db->where('id', (int) $id)->update('users', $data)) {
                throw new RuntimeException('Perubahan pengguna gagal disimpan.');
            }
            $this->replace_permissions((int) $id, $allowedPermissionIds);
            if ($this->db->trans_status() === FALSE) throw new RuntimeException('Perubahan pengguna gagal disimpan.');
            if (!$this->db->trans_commit()) throw new RuntimeException('Transaksi pengguna gagal diselesaikan.');
            $this->db->db_debug = $originalDbDebug;
            return TRUE;
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            $this->db->db_debug = $originalDbDebug;
            throw $exception;
        }
    }

    private function replace_permissions($userId, array $allowedPermissionIds)
    {
        $allowed = array_fill_keys(array_map('intval', $allowedPermissionIds), TRUE);
        $permissions = $this->db->select('id')->from('permissions')->get()->result_array();
        $this->db->where('user_id', (int) $userId)->delete('user_permissions');
        if (!$permissions) return;

        $rows = array();
        foreach ($permissions as $permission) {
            $permissionId = (int) $permission['id'];
            $rows[] = array(
                'user_id' => (int) $userId,
                'permission_id' => $permissionId,
                'allowed' => isset($allowed[$permissionId]) ? 1 : 0
            );
        }
        $this->db->insert_batch('user_permissions', $rows);
    }
}
