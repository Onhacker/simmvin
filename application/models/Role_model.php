<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Role_model extends CI_Model
{
    public function all()
    {
        return $this->db
            ->select('r.id,r.name,r.slug,r.description,r.is_system,r.created_at,r.updated_at,COUNT(DISTINCT u.id) AS user_count,COUNT(DISTINCT rp.permission_id) AS permission_count', FALSE)
            ->from('roles r')
            ->join('users u', 'u.role_id=r.id', 'left')
            ->join('role_permissions rp', 'rp.role_id=r.id', 'left')
            ->group_by(array('r.id','r.name','r.slug','r.description','r.is_system','r.created_at','r.updated_at'))
            ->order_by('r.is_system', 'DESC')
            ->order_by('r.name', 'ASC')
            ->get()
            ->result_array();
    }

    public function find($id)
    {
        return $this->db->where('id', (int) $id)->get('roles')->row_array();
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

    public function permission_ids($roleId)
    {
        $rows = $this->db
            ->select('permission_id')
            ->from('role_permissions')
            ->where('role_id', (int) $roleId)
            ->get()
            ->result_array();
        return array_map('intval', array_column($rows, 'permission_id'));
    }

    public function name_exists($name, $exceptId)
    {
        return $this->db
            ->where('name', $name)
            ->where('id !=', (int) $exceptId)
            ->count_all_results('roles') > 0;
    }

    public function slug_exists($slug, $exceptId)
    {
        return $this->db
            ->where('slug', $slug)
            ->where('id !=', (int) $exceptId)
            ->count_all_results('roles') > 0;
    }

    public function update_role($id, array $data, array $permissionIds)
    {
        $validRows = $this->db->select('id')->from('permissions')->get()->result_array();
        $valid = array_fill_keys(array_map('intval', array_column($validRows, 'id')), TRUE);
        $selected = array();
        foreach ($permissionIds as $permissionId) {
            $permissionId = (int) $permissionId;
            if (isset($valid[$permissionId])) $selected[$permissionId] = $permissionId;
        }

        $this->db->trans_begin();
        $this->db->where('id', (int) $id)->update('roles', $data);
        $this->db->where('role_id', (int) $id)->delete('role_permissions');
        if ($selected) {
            $rows = array();
            foreach ($selected as $permissionId) {
                $rows[] = array('role_id' => (int) $id, 'permission_id' => $permissionId);
            }
            $this->db->insert_batch('role_permissions', $rows);
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            throw new RuntimeException('Hak akses peran gagal disimpan.');
        }
        if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi hak akses peran gagal diselesaikan.'); }
        return TRUE;
    }
}
