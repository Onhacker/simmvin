<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Training_model extends CI_Model
{
    private $regionDb;

    public function __construct()
    {
        parent::__construct();
        $this->regionDb = $this->load->database('wilayah', TRUE);
    }

    public function get_all($filters = array())
    {
        $this->db->select('e.*, COUNT(DISTINCT er.id) AS regency_count, COUNT(DISTINCT r.id) AS registration_count', FALSE)
            ->from('training_events e')
            ->join('event_regencies er', 'er.event_id=e.id', 'left')
            ->join('registrations r', 'r.event_id=e.id AND r.status="active"', 'left', FALSE)
            ->group_by('e.id');

        if (!empty($filters['status']) && in_array($filters['status'], array('draft', 'open', 'closed', 'cancelled'), TRUE)) {
            $this->db->where('e.status', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $this->db->group_start()
                ->like('e.name', $filters['q'])
                ->or_like('e.code', $filters['q'])
                ->or_like('e.location', $filters['q'])
                ->or_like('er.regency_name', $filters['q'])
                ->group_end();
        }

        return $this->db->order_by('e.start_date', 'DESC')->order_by('e.id', 'DESC')->get()->result_array();
    }

    public function get($id)
    {
        $event = $this->db->where('id', (int) $id)->get('training_events')->row_array();
        if (!$event) return NULL;
        $rows = $this->db->where('event_id', (int) $id)
            ->order_by('province_name')->order_by('regency_name')->get('event_regencies')->result_array();
        $event['regencies'] = simp_resolve_event_regencies($this->regionDb, $rows);
        $event['summary'] = $this->db->select(
            'COUNT(DISTINCT CASE WHEN r.status="active" THEN r.id END) AS village_count,
             COUNT(DISTINCT r.id) AS archive_village_count,
             COUNT(DISTINCT CASE WHEN r.status="active" AND p.is_active=1 AND p.deleted_at IS NULL THEN p.id END) AS participant_count',
            FALSE
        )->from('registrations r')->join('participants p', 'p.registration_id=r.id', 'left')
            ->where('r.event_id', (int) $id)->get()->row_array();
        $incomeRaw = $this->db->select_sum('amount')
            ->where(array('event_id' => (int) $id, 'status' => 'verified'))->get('payments')->row()->amount;
        $incomeCents = simp_money_cents($incomeRaw ?: '0');
        $event['summary']['verified_income'] = $incomeCents === NULL
            ? '0.00'
            : simp_money_from_cents($incomeCents);
        return $event;
    }

    public function code_exists($code, $excludeId = 0)
    {
        $this->db->from('training_events')->where('code', $code);
        if ($excludeId > 0) $this->db->where('id !=', (int) $excludeId);
        return $this->db->count_all_results() > 0;
    }

    public function validate_regencies($selections, $legacyRegencyIds = NULL)
    {
        if ($legacyRegencyIds !== NULL) {
            $legacy = array();
            foreach ($this->normalize_ids($legacyRegencyIds) as $regencyId) {
                $legacy[] = array('province_id' => (string) $selections, 'regency_id' => $regencyId);
            }
            $selections = $legacy;
        }
        $pairs = array();
        foreach ((array) $selections as $selection) {
            if (is_string($selection)) $selection = json_decode($selection, TRUE);
            $provinceId = isset($selection['province_id']) ? trim((string) $selection['province_id']) : '';
            $regencyId = isset($selection['regency_id']) ? trim((string) $selection['regency_id']) : '';
            if (!$this->valid_region_id($provinceId) || !$this->valid_region_id($regencyId)) continue;
            $pairs[$regencyId] = array('province_id' => $provinceId, 'regency_id' => $regencyId);
        }
        if (!$pairs) throw new InvalidArgumentException('Pilih minimal satu kabupaten/kota cakupan event.');

        $result = array();
        foreach ($pairs as $pair) {
            $row = $this->regionDb->select('k.id AS regency_id,k.kota AS regency_name,p.id AS province_id,p.provinsi AS province_name')
                ->from('data_kota k')->join('data_provinsi p', 'p.id=k.id_provinsi')
                ->where('k.id', $pair['regency_id'])->where('p.id', $pair['province_id'])->get()->row_array();
            if (!$row) throw new InvalidArgumentException('Kabupaten/kota tidak sesuai dengan provinsi asalnya.');
            $result[] = $row;
        }
        return $result;
    }

    public function create_event($data, $regions)
    {
        $this->db->trans_begin();
        $this->db->insert('training_events', $data);
        $eventId = (int) $this->db->insert_id();
        foreach ($regions as $region) {
            $region['event_id'] = $eventId;
            $this->db->insert('event_regencies', $region);
        }
        if ($this->db->trans_status() === FALSE) {
            $error = $this->db->error();
            $this->db->trans_rollback();
            throw new RuntimeException(!empty($error['message']) ? $error['message'] : 'Event gagal disimpan.');
        }
        if (!$this->db->trans_commit()) {
            $this->db->trans_rollback();
            throw new RuntimeException('Transaksi event gagal diselesaikan.');
        }
        return $eventId;
    }

    public function update_event($id, $data, $regions)
    {
        $id = (int) $id;
        $this->db->trans_begin();
        try {
            $existing = $this->db->query('SELECT * FROM training_events WHERE id=? FOR UPDATE', array($id))->row_array();
            if (!$existing) throw new InvalidArgumentException('Event tidak ditemukan.');

            $registrationCount = $this->db->where('event_id', $id)->count_all_results('registrations');
            $registeredRegencies = $this->db->select('province_id,province_name,regency_id,regency_name')
                ->where(array('event_id'=>$id,'status'=>'active'))
                ->group_by(array('province_id','province_name','regency_id','regency_name'))
                ->get('registrations')->result_array();
            $registeredRegencies = simp_resolve_event_regencies($this->regionDb, $registeredRegencies);
            if ($registrationCount > 0) {
                if ($existing['billing_mode'] !== $data['billing_mode']) throw new InvalidArgumentException('Mode pembayaran tidak dapat diubah setelah ada registrasi.');
                $existingVillageCents = simp_money_cents($existing['village_fee']);
                $newVillageCents = simp_money_cents(isset($data['village_fee']) ? $data['village_fee'] : '0');
                $existingParticipantCents = simp_money_cents($existing['participant_fee']);
                $newParticipantCents = simp_money_cents(isset($data['participant_fee']) ? $data['participant_fee'] : '0');
                if ($existingVillageCents === NULL || $newVillageCents === NULL ||
                    $existingParticipantCents === NULL || $newParticipantCents === NULL) {
                    throw new InvalidArgumentException('Nominal event tidak valid.');
                }
                if (in_array($existing['billing_mode'], array('per_village', 'per_village_extra'), TRUE)
                    && $existingVillageCents !== $newVillageCents) {
                    throw new InvalidArgumentException('Biaya paket desa tidak dapat diubah setelah ada registrasi.');
                }
                if (in_array($existing['billing_mode'], array('per_participant', 'per_village_extra'), TRUE)
                    && $existingParticipantCents !== $newParticipantCents) {
                    throw new InvalidArgumentException('Biaya peserta tidak dapat diubah setelah ada registrasi.');
                }
                if ($existing['billing_mode'] === 'per_village_extra'
                    && (int) $existing['included_participant_count'] !== (int) $data['included_participant_count']) {
                    throw new InvalidArgumentException('Jumlah peserta dalam paket desa tidak dapat diubah setelah ada registrasi.');
                }
            }
            if ($registeredRegencies) {
                $newIds = array(); foreach ($regions as $r) { $key=simp_regency_identity_key($r); if($key!=='')$newIds[$key]=TRUE; }
                foreach ($registeredRegencies as $r) if (empty($newIds[simp_regency_identity_key($r)])) throw new InvalidArgumentException('Kabupaten yang sudah memiliki registrasi tidak dapat dihapus dari event.');
            }

            $this->db->where('id', $id)->update('training_events', $data);
            $this->db->where('event_id', $id)->delete('event_regencies');
            foreach ($regions as $region) {
                $region['event_id'] = $id;
                $this->db->insert('event_regencies', $region);
            }
            if ($this->db->trans_status() === FALSE) {
                $error = $this->db->error();
                throw new RuntimeException(!empty($error['message']) ? $error['message'] : 'Perubahan event gagal disimpan.');
            }
            if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi perubahan event gagal diselesaikan.'); }
            return TRUE;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    public function activate_event($id)
    {
        return $this->transition_event((int) $id, array('draft', 'closed'), 'open');
    }

    public function close_event($id)
    {
        return $this->transition_event((int) $id, array('open'), 'closed');
    }

    private function transition_event($id, $allowedFrom, $to)
    {
        if ($id <= 0) throw new InvalidArgumentException('Event tidak ditemukan.');

        $this->db->trans_begin();
        $event = $this->db->query(
            'SELECT id,status,start_date,end_date,billing_mode,village_fee,participant_fee,included_participant_count FROM training_events WHERE id=? FOR UPDATE',
            array($id)
        )->row_array();

        if (!$event) {
            $this->db->trans_rollback();
            throw new InvalidArgumentException('Event tidak ditemukan.');
        }
        if ($event['status'] === $to) {
            if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi status event gagal diselesaikan.'); }
            return array('changed' => FALSE, 'from' => $to, 'to' => $to);
        }
        if (!in_array($event['status'], $allowedFrom, TRUE)) {
            $this->db->trans_rollback();
            $message = $to === 'open'
                ? 'Hanya event Draft atau Ditutup yang dapat diaktifkan kembali.'
                : 'Hanya event aktif yang dapat ditutup.';
            throw new InvalidArgumentException($message);
        }
        if ($to === 'open') {
            $regionCount = $this->db->where('event_id', $id)->count_all_results('event_regencies');
            if ($regionCount < 1) {
                $this->db->trans_rollback();
                throw new InvalidArgumentException('Event belum memiliki kabupaten/kota sasaran.');
            }
            if (!$event['start_date'] || !$event['end_date'] || strtotime($event['end_date']) < strtotime($event['start_date'])) {
                $this->db->trans_rollback();
                throw new InvalidArgumentException('Rentang tanggal event tidak valid.');
            }
            try {
                $this->assert_valid_billing_scheme($event);
            } catch (InvalidArgumentException $e) {
                $this->db->trans_rollback();
                throw $e;
            }
        }
        if ($to === 'closed') {
            // Jangan sembunyikan antrean persetujuan ketika event ditutup.
            // Operator harus menyelesaikan transaksi pending terlebih dahulu;
            // histori terverifikasi tetap dapat dibaca dari detail/laporan.
            $pendingPayments = (int) $this->db->where(array('event_id'=>$id, 'status'=>'pending'))
                ->count_all_results('payments');
            $pendingExpenses = (int) $this->db->where(array('event_id'=>$id, 'status'=>'pending'))
                ->count_all_results('expenses');
            if ($pendingPayments > 0 || $pendingExpenses > 0) {
                $this->db->trans_rollback();
                $parts = array();
                if ($pendingPayments > 0) $parts[] = $pendingPayments.' pembayaran';
                if ($pendingExpenses > 0) $parts[] = $pendingExpenses.' pengeluaran';
                throw new InvalidArgumentException(
                    'Selesaikan '.implode(' dan ', $parts).' yang menunggu verifikasi sebelum event ditutup.'
                );
            }
        }

        $from = $event['status'];
        $this->db->where(array('id' => $id, 'status' => $from))->update('training_events', array(
            'status' => $to,
            'updated_at' => date('Y-m-d H:i:s')
        ));
        if ($this->db->affected_rows() !== 1 || $this->db->trans_status() === FALSE) {
            $error = $this->db->error();
            $this->db->trans_rollback();
            throw new RuntimeException(!empty($error['message']) ? $error['message'] : 'Status event gagal diperbarui.');
        }
        if (!$this->db->trans_commit()) { $this->db->trans_rollback(); throw new RuntimeException('Transaksi status event gagal diselesaikan.'); }
        return array('changed' => TRUE, 'from' => $from, 'to' => $to);
    }

    private function assert_valid_billing_scheme(array $event)
    {
        $mode = isset($event['billing_mode']) ? (string) $event['billing_mode'] : '';
        $villageFee = simp_money_cents(isset($event['village_fee']) ? $event['village_fee'] : '0');
        $participantFee = simp_money_cents(isset($event['participant_fee']) ? $event['participant_fee'] : '0');
        $includedCount = isset($event['included_participant_count']) ? (int) $event['included_participant_count'] : 0;

        if ($mode === 'per_village' && $villageFee !== NULL && $villageFee > 0) return;
        if ($mode === 'per_participant' && $participantFee !== NULL && $participantFee > 0) return;
        if ($mode === 'per_village_extra' && $villageFee !== NULL && $participantFee !== NULL &&
            $villageFee > 0 && $participantFee > 0 && $includedCount > 0) return;

        if ($mode === 'per_village_extra') {
            throw new InvalidArgumentException('Skema paket desa belum lengkap. Isi biaya paket, jumlah peserta dalam paket, dan biaya peserta tambahan.');
        }
        if ($mode === 'per_village') {
            throw new InvalidArgumentException('Biaya per desa harus lebih besar dari nol sebelum event diaktifkan.');
        }
        if ($mode === 'per_participant') {
            throw new InvalidArgumentException('Biaya per peserta harus lebih besar dari nol sebelum event diaktifkan.');
        }
        throw new InvalidArgumentException('Mode pembayaran event tidak valid.');
    }

    private function normalize_ids($ids)
    {
        if (!is_array($ids)) $ids = array($ids);
        $clean = array();
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($this->valid_region_id($id)) $clean[$id] = $id;
        }
        return array_values($clean);
    }

    private function valid_region_id($id)
    {
        return $id !== '' && (bool) preg_match('/^[A-Za-z0-9_.-]+$/', $id);
    }
}
