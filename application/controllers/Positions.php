<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Positions extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Position_model', 'position');
    }

    public function index()
    {
        $this->require_permission('positions.view');
        $filters = array(
            'q' => trim((string) $this->input->get('q', TRUE)),
            'category' => trim((string) $this->input->get('category', TRUE)),
            'status' => trim((string) $this->input->get('status', TRUE))
        );
        $categories = $this->position->categories();
        if ($filters['category'] !== '' && !isset($categories[$filters['category']])) $filters['category'] = '';
        if (!in_array($filters['status'], array('', 'active', 'inactive'), TRUE)) $filters['status'] = '';

        $this->render('positions/index', array(
            'pageTitle' => 'Master Jabatan',
            'positions' => $this->position->all($filters),
            'categories' => $categories,
            'stats' => $this->position->stats(),
            'filters' => $filters,
            'canManage' => $this->Auth_model->can('positions.manage'),
            'pageScript' => 'masters.js'
        ));
    }

    public function create()
    {
        $this->require_permission('positions.manage');
        $this->position_form(0);
    }

    public function edit($id)
    {
        $this->require_permission('positions.manage');
        $this->position_form((int) $id);
    }

    public function status($id)
    {
        $this->require_permission('positions.manage');
        $this->require_post();
        $wantsJson = $this->wants_json();
        $row = $this->position->find((int) $id);
        if (!$row) {
            if ($wantsJson) return $this->json(array('success'=>FALSE,'message'=>'Jabatan tidak ditemukan.'), 404);
            show_404();
        }

        $requestedStatusRaw = $this->input->post('is_active', TRUE);
        $requestedStatus = is_scalar($requestedStatusRaw) ? (string) $requestedStatusRaw : '';
        if (!in_array($requestedStatus, array('0', '1'), TRUE)) {
            if ($wantsJson) return $this->json(array('success'=>FALSE,'message'=>'Status jabatan tidak valid.'), 422);
            return $this->redirect_with('jabatan', 'error', 'Status jabatan tidak valid.');
        }
        $isActive = $requestedStatus === '1';
        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        try {
            if (!$this->position->set_active($row['id'], $isActive)) {
                throw new RuntimeException('Model gagal memperbarui status jabatan.');
            }
            try {
                $this->Audit_model->log(
                    $isActive ? 'position_activated' : 'position_deactivated',
                    'village_position',
                    $row['id'],
                    array('name' => $row['name'], 'is_active' => $isActive ? 1 : 0)
                );
            } catch (Throwable $ignored) {}
            $message = 'Jabatan berhasil ' . ($isActive ? 'diaktifkan.' : 'dinonaktifkan.');
            if ($wantsJson) return $this->json(array('success'=>TRUE,'message'=>$message,'position_id'=>(int)$row['id'],'is_active'=>$isActive ? 1 : 0));
            return $this->redirect_with('jabatan', 'success', $message);
        } catch (Throwable $exception) {
            log_message('error', 'Status jabatan #' . (int)$row['id'] . ' gagal: ' . $exception->getMessage());
            $message = 'Status jabatan belum dapat diperbarui karena terjadi gangguan sistem.';
            if ($wantsJson) return $this->json(array('success'=>FALSE,'message'=>$message), 500);
            return $this->redirect_with('jabatan', 'error', $message);
        } finally {
            $this->db->db_debug = $originalDbDebug;
        }
    }

    private function position_form($id)
    {
        $wantsJson = $this->wants_json();
        $position = $id ? $this->position->find($id) : NULL;
        if ($id && !$position) {
            if ($wantsJson) return $this->json(array('success'=>FALSE,'message'=>'Jabatan tidak ditemukan.'), 404);
            show_404();
        }
        $categories = $this->position->categories();
        $errorMessage = NULL;

        if ($this->input->method(TRUE) === 'POST') {
            $this->form_validation->set_rules('name', 'Nama jabatan', 'trim|required|max_length[120]');
            $this->form_validation->set_rules('code', 'Kode jabatan', 'trim|max_length[80]');
            $this->form_validation->set_rules('category', 'Kategori', 'trim|required|in_list[' . implode(',', array_keys($categories)) . ']');
            $this->form_validation->set_rules('description', 'Deskripsi', 'trim|max_length[255]');
            $this->form_validation->set_rules('sort_order', 'Urutan', 'trim|required|integer|greater_than_equal_to[0]');

            $raw = array();
            $invalidShape = FALSE;
            foreach (array('name','code','category','description','sort_order','is_active') as $field) {
                $raw[$field] = $this->input->post($field, TRUE);
                if ($raw[$field] !== NULL && !is_scalar($raw[$field])) $invalidShape = TRUE;
            }
            if ($invalidShape) $errorMessage = 'Data jabatan tidak valid.';

            $name = is_scalar($raw['name']) ? $this->position->normalize_name($raw['name']) : '';
            $postedCode = is_scalar($raw['code']) ? trim((string)$raw['code']) : '';
            $code = $postedCode === ''
                ? $this->position->unique_code($name, $id)
                : $this->position->slug($postedCode);

            if ($name === '') {
                $errorMessage = 'Nama jabatan harus diisi tanpa hanya menggunakan kata Desa.';
            }

            if ($this->form_validation->run() && !$invalidShape && $errorMessage === NULL) {
                if ($code === '') {
                    $errorMessage = 'Kode jabatan tidak dapat dibuat. Gunakan nama atau kode yang memuat huruf atau angka.';
                } elseif ($this->position->name_exists($name, $id)) {
                    $errorMessage = 'Nama jabatan sudah terdaftar.';
                } elseif ($this->position->code_exists($code, $id)) {
                    $errorMessage = 'Kode jabatan sudah digunakan.';
                } else {
                    $data = array(
                        'code' => $code,
                        'name' => $name,
                        'category' => (string)$raw['category'],
                        'description' => $this->nullable($raw['description']),
                        'sort_order' => (int)$raw['sort_order'],
                        'is_active' => !empty($raw['is_active']) ? 1 : 0
                    );
                    $originalDbDebug = $this->db->db_debug;
                    $this->db->db_debug = FALSE;
                    try {
                        $savedId = $this->position->save($data, $id, $this->currentUser['id']);
                        try {
                            $this->Audit_model->log(
                                $id ? 'position_updated' : 'position_created',
                                'village_position',
                                $savedId,
                                array('code' => $code, 'name' => $name, 'category' => $data['category'], 'is_active' => $data['is_active'])
                            );
                        } catch (Throwable $ignored) {}
                        if ($wantsJson) {
                            return $this->json(array(
                                'success'=>TRUE,
                                'message'=>'Jabatan berhasil '.($id ? 'diperbarui.' : 'ditambahkan.'),
                                'position_id'=>(int)$savedId
                            ));
                        }
                        return $this->redirect_with('jabatan', 'success', 'Jabatan berhasil ' . ($id ? 'diperbarui.' : 'ditambahkan.'));
                    } catch (InvalidArgumentException $exception) {
                        $errorMessage = $exception->getMessage();
                        $errorStatus = 422;
                    } catch (Throwable $exception) {
                        log_message('error', 'Jabatan gagal disimpan: ' . $exception->getMessage());
                        $errorMessage = 'Jabatan belum dapat disimpan karena terjadi gangguan sistem.';
                        $errorStatus = 500;
                    } finally {
                        $this->db->db_debug = $originalDbDebug;
                    }
                }
            }
            if ($wantsJson) {
                return $this->json(array(
                    'success'=>FALSE,
                    'message'=>$errorMessage ?: $this->validation_message('Periksa kembali data jabatan.')
                ), isset($errorStatus) ? (int)$errorStatus : 422);
            }
        }

        $this->render('positions/form', array(
            'pageTitle' => $id ? 'Ubah Jabatan' : 'Tambah Jabatan',
            'position' => $position,
            'categories' => $categories,
            'errorMessage' => $errorMessage
        ));
    }

    private function nullable($value)
    {
        if ($value !== NULL && !is_scalar($value)) return NULL;
        $value = trim((string) $value);
        return $value === '' ? NULL : $value;
    }

    private function wants_json()
    {
        $accept = strtolower((string)$this->input->get_request_header('Accept'));
        return $this->input->is_ajax_request() || strpos($accept, 'application/json') !== FALSE;
    }

    private function validation_message($fallback)
    {
        $message = trim(strip_tags(validation_errors('', "\n")));
        return $message !== '' ? $message : $fallback;
    }
}
