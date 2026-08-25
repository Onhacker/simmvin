<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Regions extends App_Controller
{
    private $regionDb;

    public function __construct()
    {
        parent::__construct();
        $this->regionDb = $this->load->database('wilayah', TRUE);
    }

    public function provinces()
    {
        $rows = $this->regionDb->select('id,provinsi AS name')->from('data_provinsi')
            ->where("id <> ''", NULL, FALSE)->order_by('provinsi')->get()->result_array();
        return $this->json(array('success' => TRUE, 'data' => $rows));
    }

    public function regencies()
    {
        $provinceId = trim((string) $this->input->get('province_id', TRUE));
        if ($provinceId === '') return $this->json(array('success' => FALSE, 'message' => 'Provinsi wajib dipilih.'), 422);
        $rows = $this->regionDb->select('id,kota AS name')->from('data_kota')
            ->where('id_provinsi', $provinceId)->where("id <> ''", NULL, FALSE)->order_by('kota')->get()->result_array();
        return $this->json(array('success' => TRUE, 'data' => $rows));
    }

    public function districts()
    {
        $regencyIds = $this->input->get('regency_ids');
        if (!is_array($regencyIds)) $regencyIds = array_filter(explode(',', (string) $this->input->get('regency_id', TRUE)));
        $regencyIds = array_values(array_filter(array_map('trim', $regencyIds)));
        if (!$regencyIds) return $this->json(array('success' => FALSE, 'message' => 'Kabupaten wajib dipilih.'), 422);
        $rows = $this->regionDb->select('k.id,k.kecamatan AS name,k.id_kota AS regency_id,kt.kota AS regency_name')
            ->from('data_kecamatan k')->join('data_kota kt', 'kt.id=k.id_kota')->where_in('k.id_kota', $regencyIds)
            ->where("k.id <> ''", NULL, FALSE)->order_by('kt.kota,k.kecamatan')->get()->result_array();
        return $this->json(array('success' => TRUE, 'data' => $rows));
    }

    public function villages()
    {
        $districtIds = $this->input->get('district_ids');
        if (!is_array($districtIds)) $districtIds = array_filter(explode(',', (string) $this->input->get('district_id', TRUE)));
        $districtIds = array_values(array_filter(array_map('trim', $districtIds)));
        if (!$districtIds) return $this->json(array('success' => FALSE, 'message' => 'Kecamatan wajib dipilih.'), 422);
        $rows = $this->regionDb->select('d.id,d.desa AS name,d.id_kecamatan AS district_id,k.kecamatan AS district_name,k.id_kota AS regency_id')
            ->from('data_desa d')->join('data_kecamatan k', 'k.id=d.id_kecamatan')->where_in('d.id_kecamatan', $districtIds)
            ->where("d.id <> ''", NULL, FALSE)->order_by('k.kecamatan,d.desa')->get()->result_array();
        return $this->json(array('success' => TRUE, 'data' => $rows));
    }
}

