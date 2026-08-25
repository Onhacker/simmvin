<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Audit_model extends CI_Model
{
    public function log($action, $entityType = NULL, $entityId = NULL, $details = array())
    {
        if (!$this->db->table_exists('audit_logs')) return FALSE;
        return $this->db->insert('audit_logs', array(
            'user_id' => $this->session->userdata('simp_user_id') ?: NULL,
            'action' => substr((string) $action, 0, 80),
            'entity_type' => $entityType ? substr((string) $entityType, 0, 80) : NULL,
            'entity_id' => $entityId !== NULL ? (string) $entityId : NULL,
            'details_json' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : NULL,
            'ip_address' => $this->input->ip_address(),
            'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
            'created_at' => date('Y-m-d H:i:s')
        ));
    }
}

