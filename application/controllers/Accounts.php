<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Accounts extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_model', 'finance');
    }

    public function index()
    {
        $this->require_permission('accounts.view');
        $reportData = $this->account_report_data();
        $accounts = $reportData['accounts'];
        $selectedRaw = $this->input->get('account_id', TRUE);
        $selectedId = is_scalar($selectedRaw) && ctype_digit((string) $selectedRaw)
            ? (int) $selectedRaw
            : 0;
        $availableIds = array_map(function ($account) {
            return (int) $account['id'];
        }, $accounts);
        if ($selectedId < 1 || !in_array($selectedId, $availableIds, TRUE)) $selectedId = 0;

        $perPage = 10;
        $pageRaw = $this->input->get('page', TRUE);
        $page = is_scalar($pageRaw) && ctype_digit((string) $pageRaw)
            ? max(1, (int) $pageRaw)
            : 1;
        $ledgerTotal = $selectedId ? $this->finance->ledger_count($selectedId) : 0;
        $ledgerTotalPages = max(1, (int) ceil($ledgerTotal / $perPage));
        if ($page > $ledgerTotalPages) $page = $ledgerTotalPages;
        $ledger = $selectedId
            ? $this->finance->ledger($selectedId, $perPage, ($page - 1) * $perPage)
            : array();

        $this->render('accounts/index', array('pageTitle'=>'Kas & Rekening','accounts'=>$accounts,
            'total'=>$reportData['accountSummary']['included_balance'],'selectedId'=>$selectedId,
            'ledger'=>$ledger,'ledgerTotal'=>$ledgerTotal,'ledgerPage'=>$page,
            'ledgerPerPage'=>$perPage,'ledgerTotalPages'=>$ledgerTotalPages,
            'canManage'=>$this->Auth_model->can('accounts.manage'),
            'pageScripts'=>array('masters.js','finance.js')));
    }

    public function print_preview()
    {
        $this->require_permission('accounts.view');
        $data = $this->account_report_data();
        $data['reportKind'] = 'accounts';
        $data['documentTitle'] = 'Laporan Saldo Kas & Rekening';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = FALSE;
        $html = $this->load->view('reports/print_document', $data, TRUE);
        return $this->private_document_output('text/html', $html);
    }

    public function pdf()
    {
        $this->require_permission('accounts.view');
        $data = $this->account_report_data();
        $data['reportKind'] = 'accounts';
        $data['documentTitle'] = 'Laporan Saldo Kas & Rekening';
        $data['generatedAt'] = date('Y-m-d H:i:s');
        $data['isPdf'] = TRUE;
        $html = $this->load->view('reports/print_document', $data, TRUE);

        try {
            $this->load->library('Pdf_renderer');
            $pdf = $this->pdf_renderer->render_f4($html);
            return $this->private_document_output(
                'application/pdf',
                $pdf,
                'attachment; filename="laporan-saldo-akun-dana-' . date('Ymd-His') . '.pdf"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat PDF akun dana: ' . $e->getMessage());
            show_error('PDF akun dana belum dapat dibuat. Silakan coba kembali.', 500, 'PDF Gagal Dibuat');
        }
    }

    public function excel()
    {
        $this->require_permission('accounts.view');
        $data = $this->account_report_data();
        $data['generatedAt'] = date('Y-m-d H:i:s');

        try {
            $this->load->library('Excel_renderer');
            $excel = $this->excel_renderer->render_accounts($data);
            return $this->private_document_output(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $excel,
                'attachment; filename="laporan-saldo-akun-dana-' . date('Ymd-His') . '.xlsx"'
            );
        } catch (Throwable $e) {
            log_message('error', 'Gagal membuat Excel akun dana: ' . $e->getMessage());
            show_error('Excel akun dana belum dapat dibuat. Silakan coba kembali.', 500, 'Excel Gagal Dibuat');
        }
    }

    public function create()
    {
        $this->require_permission('accounts.manage');
        $this->account_form(0);
    }

    public function edit($id)
    {
        $this->require_permission('accounts.manage');
        $this->account_form((int) $id);
    }

    private function account_form($id)
    {
        $wantsJson = $this->wants_json();
        $account = $id ? $this->finance->account($id) : NULL;
        if ($id && !$account) {
            if ($wantsJson) return $this->json(array('success'=>FALSE,'message'=>'Akun dana tidak ditemukan.'), 404);
            show_404();
        }
        if ($this->input->method(TRUE) === 'POST') {
            $this->form_validation->set_rules('name','Nama akun','trim|required|max_length[120]');
            $this->form_validation->set_rules('type','Jenis akun','trim|required|in_list[cash,bank,qris,personal]');
            // `numeric` accepts exponent notation and negative values.  The
            // database stores DECIMAL(18,2), so validate the exact format
            // server-side before any float conversion.
            $this->form_validation->set_rules('opening_balance','Saldo awal','trim|required');
            $this->form_validation->set_rules('sort_order','Urutan','trim|required|integer');
            $raw = array();
            $invalidShape = FALSE;
            foreach (array('name','type','bank_name','account_number','account_holder','opening_balance','sort_order','include_in_total','is_active') as $field) {
                $raw[$field] = $this->input->post($field, TRUE);
                if ($raw[$field] !== NULL && !is_scalar($raw[$field])) $invalidShape = TRUE;
            }
            if ($invalidShape) $dataError = 'Data akun dana tidak valid.';

            $validationPassed = $this->form_validation->run();
            if ($validationPassed && !$invalidShape) {
                $openingBalance = simp_money_decimal($raw['opening_balance'], TRUE);
                if ($openingBalance === NULL) {
                    $dataError = 'Saldo awal harus berupa nominal nonnegatif dengan maksimal 16 digit dan 2 angka desimal.';
                }
            }

            if ($validationPassed && !$invalidShape && empty($dataError)) {
                $data = array(
                    'name'=>(string)$raw['name'],'type'=>(string)$raw['type'],
                    'bank_name'=>$this->nullable($raw['bank_name']),
                    'account_number'=>$this->nullable($raw['account_number']),
                    'account_holder'=>$this->nullable($raw['account_holder']),
                    'opening_balance'=>$openingBalance,
                    'include_in_total'=>!empty($raw['include_in_total']) ? 1 : 0,
                    'is_active'=>!empty($raw['is_active']) ? 1 : 0,
                    'sort_order'=>(int)$raw['sort_order']
                );
                $originalDbDebug = $this->db->db_debug;
                $this->db->db_debug = FALSE;
                try {
                    $saved = $this->finance->save_account($data,$id,$this->currentUser['id']);
                    if (!$saved) throw new RuntimeException('Model gagal menyimpan akun dana.');
                    try {
                        $this->Audit_model->log($id?'account_updated':'account_created','fund_account',$saved,$data);
                    } catch (Throwable $ignored) {}
                    if ($wantsJson) {
                        return $this->json(array(
                            'success'=>TRUE,
                            'message'=>'Akun dana berhasil '.($id ? 'diperbarui.' : 'ditambahkan.'),
                            'account_id'=>(int)$saved
                        ));
                    }
                    return $this->redirect_with('akun-dana','success','Akun dana berhasil disimpan.');
                } catch (InvalidArgumentException $exception) {
                    $dataError = $exception->getMessage();
                    $errorStatus = 422;
                } catch (Throwable $exception) {
                    log_message('error', 'Akun dana gagal disimpan: ' . $exception->getMessage());
                    $dataError = 'Akun dana belum dapat disimpan karena terjadi gangguan sistem.';
                    $errorStatus = 500;
                } finally {
                    $this->db->db_debug = $originalDbDebug;
                }
                $this->session->set_flashdata('error',$dataError);
            }
            if ($wantsJson) {
                return $this->json(array(
                    'success'=>FALSE,
                    'message'=>isset($dataError) ? $dataError : $this->validation_message('Periksa kembali data akun dana.')
                ), isset($errorStatus) ? (int)$errorStatus : 422);
            }
        }
        $this->render('accounts/form',array('pageTitle'=>$id?'Ubah Akun Dana':'Tambah Akun Dana','account'=>$account,'error'=>isset($dataError)?$dataError:NULL,'pageScript'=>'finance.js'));
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

    private function nullable($value)
    {
        if ($value === NULL) return NULL;
        $value = trim((string)$value);
        return $value === '' ? NULL : $value;
    }

    private function account_report_data()
    {
        $accounts = $this->finance->accounts(FALSE);
        $summary = array(
            'account_count'=>count($accounts), 'active_count'=>0, 'inactive_count'=>0,
            'included_count'=>0, 'excluded_count'=>0, 'included_balance'=>0,
            'excluded_balance'=>0, 'all_balance'=>0, 'opening_balance'=>0,
            'net_movement'=>0
        );
        $includedCents = 0; $excludedCents = 0; $allCents = 0; $openingCents = 0;
        foreach ($accounts as $account) {
            $balanceCents = simp_money_cents($account['balance']);
            $openingValueCents = simp_money_cents($account['opening_balance']);
            if ($balanceCents === NULL) $balanceCents = 0;
            if ($openingValueCents === NULL) $openingValueCents = 0;
            $summary[(int)$account['is_active'] === 1 ? 'active_count' : 'inactive_count']++;
            if ((int)$account['include_in_total'] === 1) {
                $summary['included_count']++;
                $includedCents += $balanceCents;
            } else {
                $summary['excluded_count']++;
                $excludedCents += $balanceCents;
            }
            $allCents += $balanceCents;
            $openingCents += $openingValueCents;
        }
        $summary['included_balance'] = simp_money_from_signed_cents($includedCents);
        $summary['excluded_balance'] = simp_money_from_signed_cents($excludedCents);
        $summary['all_balance'] = simp_money_from_signed_cents($allCents);
        $summary['opening_balance'] = simp_money_from_signed_cents($openingCents);
        $summary['net_movement'] = simp_money_from_signed_cents($allCents - $openingCents);
        return array(
            'accounts'=>$accounts,
            'accountSummary'=>$summary,
            'organizationName'=>$this->finance->setting_value('organization_name', 'Penyelenggara Pelatihan'),
            'reportScope'=>'Seluruh akun dana'
        );
    }

    private function private_document_output($contentType, $body, $disposition = NULL)
    {
        $binary = in_array($contentType, array('application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), TRUE);
        if ($binary) $this->output->set_header('Content-Type: ' . $contentType);
        else $this->output->set_content_type($contentType, 'UTF-8');
        $this->output
            ->set_header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_header('X-Content-Type-Options: nosniff');
        if ($disposition !== NULL) $this->output->set_header('Content-Disposition: ' . $disposition);
        if ($binary) $this->output->set_header('Content-Length: ' . strlen($body));
        return $this->output->set_output($body);
    }
}
