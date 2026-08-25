<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth_model extends CI_Model
{
    private $permissionCache = NULL;

    public function attempt($identity, $password)
    {
        $identity = trim((string) $identity);
        $this->db->select('u.*, r.name AS role_name, r.slug AS role_slug');
        $this->db->from('users u');
        $this->db->join('roles r', 'r.id = u.role_id');
        $this->db->where('u.is_active', 1);
        $this->db->group_start()->where('u.username', $identity)->or_where('u.email', $identity)->group_end();
        $user = $this->db->get()->row_array();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return FALSE;
        }
        $this->session->sess_regenerate(TRUE);
        $this->session->set_userdata(array('simp_logged_in' => TRUE, 'simp_user_id' => (int) $user['id']));
        $this->db->where('id', $user['id'])->update('users', array('last_login_at' => date('Y-m-d H:i:s')));
        return $user;
    }

    public function login_is_blocked($identity, $ipAddress)
    {
        if (!$this->db->table_exists('login_failures')) return FALSE;
        $since = date('Y-m-d H:i:s', time() - 600);
        return $this->db->from('login_failures')->where('attempted_at >=', $since)
            ->group_start()->where('identity_hash', $this->identity_hash($identity))->or_where('ip_address', (string) $ipAddress)->group_end()
            ->count_all_results() >= 10;
    }

    public function record_login_failure($identity, $ipAddress)
    {
        if (!$this->db->table_exists('login_failures')) return FALSE;
        // Opportunistic cleanup keeps the throttle table small without a scheduler.
        $this->db->where('attempted_at <', date('Y-m-d H:i:s', time() - 86400))->delete('login_failures');
        return $this->db->insert('login_failures', array(
            'identity_hash' => $this->identity_hash($identity),
            'ip_address' => substr((string) $ipAddress, 0, 45),
            'attempted_at' => date('Y-m-d H:i:s')
        ));
    }

    public function clear_login_failures($identity, $ipAddress)
    {
        if (!$this->db->table_exists('login_failures')) return FALSE;
        return $this->db->group_start()->where('identity_hash', $this->identity_hash($identity))
            ->or_where('ip_address', (string) $ipAddress)->group_end()->delete('login_failures');
    }

    private function identity_hash($identity)
    {
        return hash('sha256', strtolower(trim((string) $identity)));
    }

    public function logout()
    {
        $this->session->unset_userdata(array('simp_logged_in', 'simp_user_id', 'intended_url'));
        $this->session->sess_regenerate(TRUE);
    }

    public function current_user()
    {
        if (!$this->session->userdata('simp_logged_in') || !$this->session->userdata('simp_user_id')) return NULL;
        return $this->db
            ->select('u.id,u.role_id,u.name,u.username,u.email,u.phone,u.is_active,u.last_login_at,r.name AS role_name,r.slug AS role_slug')
            ->from('users u')->join('roles r', 'r.id=u.role_id')
            ->where(array('u.id' => (int) $this->session->userdata('simp_user_id'), 'u.is_active' => 1))
            ->get()->row_array();
    }

    public function can($code)
    {
        $user = $this->current_user();
        if (!$user) return FALSE;
        if ($user['role_slug'] === 'super-admin') return TRUE;
        if ($this->permissionCache === NULL) $this->permissionCache = $this->effective_permissions($user['id'], $user['role_id']);
        return in_array($code, $this->permissionCache, TRUE);
    }

    public function effective_permissions($userId, $roleId)
    {
        $rows = $this->db->select('p.id,p.code')->from('permissions p')
            ->join('role_permissions rp', 'rp.permission_id=p.id')->where('rp.role_id', (int) $roleId)->get()->result_array();
        $byId = array();
        foreach ($rows as $row) $byId[(int) $row['id']] = $row['code'];
        $overrides = $this->db->select('up.permission_id,up.allowed,p.code')->from('user_permissions up')
            ->join('permissions p', 'p.id=up.permission_id')->where('up.user_id', (int) $userId)->get()->result_array();
        foreach ($overrides as $override) {
            if ((int) $override['allowed'] === 1) $byId[(int) $override['permission_id']] = $override['code'];
            else unset($byId[(int) $override['permission_id']]);
        }
        return array_values($byId);
    }
}
