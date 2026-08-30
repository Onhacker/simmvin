<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Registration_model extends CI_Model
{
    private $regionDb;

    public function __construct()
    {
        parent::__construct();
        $this->regionDb = $this->load->database('wilayah', TRUE);
    }

    public function events_for_registration()
    {
        $events = $this->db->where('status', 'open')->order_by('start_date', 'DESC')->get('training_events')->result_array();
        foreach ($events as &$event) {
            $rows = $this->db->select('province_id,province_name,regency_id,regency_name')->where('event_id', $event['id'])->order_by('regency_name')->get('event_regencies')->result_array();
            $event['regencies'] = simp_resolve_event_regencies($this->regionDb, $rows);
        }
        unset($event);
        return $events;
    }

    public function events_for_filter()
    {
        return $this->db->select('id,name,code,status,start_date,end_date')
            ->order_by('start_date', 'DESC')->order_by('id', 'DESC')
            ->get('training_events')->result_array();
    }

    public function get_event($id, $onlyOpen = FALSE)
    {
        $this->db->where('id', (int) $id);
        if ($onlyOpen) $this->db->where('status', 'open');
        $event = $this->db->get('training_events')->row_array();
        if (!$event) return NULL;
        $rows = $this->db->where('event_id', (int) $id)->get('event_regencies')->result_array();
        $event['regencies'] = simp_resolve_event_regencies($this->regionDb, $rows);
        return $event;
    }

    /**
     * Return an event that is safe to expose through the historical
     * registration archive.
     *
     * Registration entry remains restricted to open events, but a closed (or
     * draft/cancelled) event with existing data must remain readable without
     * reopening it. Cancelled registrations are still excluded from the
     * attendance roster itself, while their archive card remains available.
     */
    public function get_event_archive($id)
    {
        $event = $this->get_event((int) $id, FALSE);
        if (!$event || !in_array((string) $event['status'], array('draft', 'open', 'closed', 'cancelled'), TRUE)) {
            return NULL;
        }

        $summary = $this->db->select(
            'COUNT(DISTINCT r.id) AS village_count,
             COUNT(DISTINCT CASE WHEN p.is_active=1 AND p.deleted_at IS NULL THEN p.id END) AS participant_count',
            FALSE
        )->from('registrations r')
            ->join('participants p', 'p.registration_id=r.id', 'left')
            ->where('r.event_id', (int) $event['id'])
            ->where('r.status', 'active')
            ->get()->row_array();

        // Keep money aggregation independent from the participant join.  A
        // registration may contain several participants, which would multiply
        // payment rows if both relations were joined in one summary query.
        $money = $this->db->select(
            'COALESCE(SUM(CASE WHEN status="verified" THEN amount ELSE 0 END),0) AS verified_income,
             COALESCE(SUM(CASE WHEN status="pending" THEN amount ELSE 0 END),0) AS pending_income',
            FALSE
        )->where('event_id', (int) $event['id'])->get('payments')->row_array();

        $event['summary'] = array(
            'village_count' => isset($summary['village_count']) ? (int) $summary['village_count'] : 0,
            'participant_count' => isset($summary['participant_count']) ? (int) $summary['participant_count'] : 0,
            'verified_income' => simp_money_decimal(isset($money['verified_income']) ? $money['verified_income'] : '0', TRUE) ?: '0.00',
            'pending_income' => simp_money_decimal(isset($money['pending_income']) ? $money['pending_income'] : '0', TRUE) ?: '0.00'
        );

        return $event;
    }

    public function get_all($filters = array())
    {
        $this->db->select('r.*,e.name AS event_name,e.code AS event_code,e.status AS event_status,
            e.start_date AS event_start_date,e.end_date AS event_end_date,e.location AS event_location,e.billing_mode,
            e.village_fee AS event_village_fee,e.participant_fee AS event_participant_fee,
            e.included_participant_count AS event_included_participant_count,
            canceller.name AS canceller_name,
            (SELECT COUNT(*) FROM participants p WHERE p.registration_id=r.id AND p.is_active=1 AND p.deleted_at IS NULL) AS participant_count,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status="verified") AS paid_amount,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status="pending") AS pending_amount,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status IN ("pending","verified")) AS committed_amount', FALSE)
            ->from('registrations r')->join('training_events e', 'e.id=r.event_id')
            ->join('users canceller', 'canceller.id=r.cancelled_by', 'left');
        $this->apply_registration_filters($filters);
        $this->db->order_by('r.created_at', 'DESC')->order_by('r.id', 'DESC');
        if (array_key_exists('limit', $filters)) {
            $limit = max(1, min(200, (int) $filters['limit']));
            $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
            $this->db->limit($limit, $offset);
        }
        return $this->db->get()->result_array();
    }

    /**
     * Pick one default signatory for every registration.
     *
     * The MOU export is grouped at village level, while participants are
     * stored as separate rows.  A lower authority rank means a higher
     * position (Kepala, Sekretaris, Kaur, Kasi, Ketua BPD, and so on).  The
     * rank is derived from the participant's position snapshot first, so
     * editing or retiring a master jabatan cannot silently change a historic
     * signatory.  Registration time and participant id are deterministic tie
     * breakers, so adding or reordering other villages cannot change an
     * existing village's default signatory.
     *
     * The returned value contains both the person's name and selected
     * position.  The MOU renderer exports them as Penandatangan and Jabatan
     * Penandatangan columns so a Word mail merge can use either field.
     */
    public function signatories_for_registrations(array $registrationIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $registrationIds), function ($id) {
            return $id > 0;
        })));
        if (!$ids) return array();

        $rows = $this->db
            ->select("p.registration_id,p.id,p.full_name,
                COALESCE(NULLIF(TRIM(p.position),''),NULLIF(TRIM(vp.name),''),'') AS position,
                COALESCE(vp.code,'') AS position_code,
                COALESCE(vp.category,'') AS position_category,
                COALESCE(vp.sort_order,2147483647) AS position_sort_order,
                p.created_at", FALSE)
            ->from('participants p')
            ->join('village_positions vp', 'vp.id=p.position_id', 'left')
            ->where_in('p.registration_id', $ids)
            ->where('p.is_active', 1)
            ->where('p.deleted_at IS NULL', NULL, FALSE)
            ->get()->result_array();

        usort($rows, function ($left, $right) {
            $leftRank = $this->signatory_position_rank($left);
            $rightRank = $this->signatory_position_rank($right);
            if ($leftRank !== $rightRank) return $leftRank < $rightRank ? -1 : 1;

            $leftCreated = trim((string) (isset($left['created_at']) ? $left['created_at'] : ''));
            $rightCreated = trim((string) (isset($right['created_at']) ? $right['created_at'] : ''));
            // A legacy/imported row may not have a timestamp.  Keep it after
            // timestamped rows instead of treating an empty value as oldest.
            if ($leftCreated === '') $leftCreated = '9999-12-31 23:59:59';
            if ($rightCreated === '') $rightCreated = '9999-12-31 23:59:59';
            if ($leftCreated !== $rightCreated) return strcmp($leftCreated, $rightCreated);

            $leftId = (int) (isset($left['id']) ? $left['id'] : 0);
            $rightId = (int) (isset($right['id']) ? $right['id'] : 0);
            if ($leftId !== $rightId) return $leftId < $rightId ? -1 : 1;
            return strcasecmp((string) (isset($left['full_name']) ? $left['full_name'] : ''), (string) (isset($right['full_name']) ? $right['full_name'] : ''));
        });

        $signatories = array();
        foreach ($rows as $row) {
            $registrationId = (int) (isset($row['registration_id']) ? $row['registration_id'] : 0);
            if ($registrationId < 1 || isset($signatories[$registrationId])) continue;
            $name = trim((string) (isset($row['full_name']) ? $row['full_name'] : ''));
            if ($name === '') continue;
            $signatories[$registrationId] = array(
                'name' => $name,
                'position' => trim((string) (isset($row['position']) ? $row['position'] : ''))
            );
        }

        return $signatories;
    }

    /**
     * Resolve a participant's authority level for the default MOU signer.
     *
     * Master Jabatan's sort_order is intentionally a category-local display
     * order (each category starts at 10), not a cross-category hierarchy.
     * Therefore an Operator in Pemerintah Desa must not outrank a Ketua BPD
     * merely because that category appears first in the menu.  Prefer stable
     * code/name patterns for the standard roles and retain category/sort as a
     * deterministic fallback for custom roles.
     */
    private function signatory_position_rank(array $row)
    {
        $code = strtolower(trim((string) (isset($row['position_code']) ? $row['position_code'] : '')));
        $name = strtolower(trim((string) (isset($row['position']) ? $row['position'] : '')));
        $name = preg_replace('/\\s+/u', ' ', $name);
        $category = strtolower(trim((string) (isset($row['position_category']) ? $row['position_category'] : '')));

        // Resolve from the immutable participant snapshot first.  This keeps
        // an existing signer stable even if an administrator later edits the
        // linked master label/category.
        if ($name === 'kepala' || $name === 'kepala desa' || $name === 'lurah' ||
            preg_match('/^(penjabat|pelaksana tugas|pj|plt)\\s+kepala(?:\\s+desa)?$/u', $name)) return 10;
        if ($name === 'sekretaris' || $name === 'sekretaris desa') return 20;
        if (preg_match('/\\b(kepala urusan|kaur)\\b/u', $name)) return 30;
        if (preg_match('/\\b(kepala seksi|kasi)\\b/u', $name)) return 40;
        if ($name === 'ketua bpd') return 50;
        if ($name === 'anggota bpd') return 60;
        if (preg_match('/\\b(kepala dusun|kepala kewilayahan)\\b/u', $name)) return 70;
        if (strpos($name, 'staf') !== FALSE) return 80;
        if (strpos($name, 'operator') !== FALSE) return 90;

        // Known non-government organisations still get a sensible hierarchy
        // based on the stored label before consulting the live master.
        if (strpos($name, 'ketua') === 0) return 100;
        if (strpos($name, 'wakil ketua') === 0) return 110;
        if (strpos($name, 'sekretaris') === 0) return 120;
        if (strpos($name, 'bendahara') === 0) return 130;
        if (strpos($name, 'anggota') === 0) return 140;

        // The normalised master keeps stable codes even after the word
        // "Desa" is removed from the visible label.  Use them only when a
        // legacy/custom snapshot did not match a standard role above.
        if ($code === 'kepala-desa' || $code === 'penjabat-kepala-desa' || $code === 'pelaksana-tugas-kepala-desa') return 10;
        if ($code === 'sekretaris-desa') return 20;
        if (strpos($code, 'kaur-') === 0) return 30;
        if (strpos($code, 'kasi-') === 0) return 40;
        if ($code === 'ketua-bpd' || ($category === 'bpd' && strpos($name, 'ketua') !== FALSE && strpos($name, 'wakil') === FALSE)) return 50;
        if ($code === 'anggota-bpd' || ($category === 'bpd' && strpos($name, 'anggota') !== FALSE)) return 60;
        if ($code === 'kepala-dusun') return 70;
        if (strpos($code, 'staf-') === 0) return 80;
        if (strpos($code, 'operator-') === 0) return 90;

        // Finally resolve standard organisational codes whose historic label
        // is custom, then retain category/sort for any remaining role.
        if (strpos($code, 'ketua-') === 0) return 100;
        if (strpos($code, 'wakil-ketua-') === 0) return 110;
        if (strpos($code, 'sekretaris-') === 0) return 120;
        if (strpos($code, 'bendahara-') === 0) return 130;
        if (strpos($code, 'anggota-') === 0) return 140;

        $categoryOrder = array(
            'pemerintah_desa', 'bpd', 'rt_rw', 'lpmd', 'pkk',
            'karang_taruna', 'bumdes', 'kemasyarakatan', 'kesehatan',
            'keamanan', 'pendamping', 'lainnya'
        );
        $categoryRank = array_search($category, $categoryOrder, TRUE);
        if ($categoryRank === FALSE) $categoryRank = count($categoryOrder);
        $sortOrder = (int) (isset($row['position_sort_order']) ? $row['position_sort_order'] : 2147483647);
        return 500 + ((int) $categoryRank * 1000) + min(max($sortOrder, 0), 999);
    }

    /**
     * Add the official regency code used by the village MOU export.
     *
     * Registration rows keep region names/IDs as immutable snapshots. We only
     * attach the current master code here and deliberately leave every snapshot
     * field untouched, so an update to the regional catalog cannot rewrite a
     * historical registration name.
     */
    public function with_regency_codes(array $rows)
    {
        if (!$rows) return $rows;

        $lookupRows = array();
        foreach ($rows as $key => $row) {
            $lookupRows[$key] = array(
                'province_id' => isset($row['province_id']) ? $row['province_id'] : '',
                'province_name' => isset($row['province_name']) ? $row['province_name'] : '',
                'regency_id' => isset($row['regency_id']) ? $row['regency_id'] : '',
                'regency_name' => isset($row['regency_name']) ? $row['regency_name'] : ''
            );
        }

        $resolvedRows = simp_resolve_event_regencies($this->regionDb, $lookupRows);
        foreach ($rows as $key => &$row) {
            $resolvedCode = isset($resolvedRows[$key]['regency_code'])
                ? trim((string) $resolvedRows[$key]['regency_code']) : '';
            $row['regency_code'] = $resolvedCode !== ''
                ? $resolvedCode
                : trim((string) (isset($row['regency_id']) ? $row['regency_id'] : ''));
        }
        unset($row);

        return $rows;
    }

    /**
     * Fill MOU numbers for legacy rows exactly once.
     *
     * Deployments that predate the MOU columns can still have registrations
     * without a number after the SQL patch (for example when the old regional
     * ID did not match the new catalog).  Resolve those rows with the regional
     * code already attached by with_regency_codes(), allocate under the same
     * locked counter used by create_batch(), and persist the result.  This is
     * intentionally called by the export path only; normal reads remain
     * side-effect free.
     */
    public function ensure_mou_numbers(array $rows)
    {
        if (!$rows) return $rows;
        foreach ($rows as $row) {
            if (!array_key_exists('mou_no', $row)) {
                throw new RuntimeException('Kolom nomor MOU belum tersedia. Jalankan patch_registration_mou_numbers.sql pada database MVIN.');
            }
        }

        $missing = FALSE;
        foreach ($rows as $row) {
            if (trim((string) (isset($row['mou_no']) ? $row['mou_no'] : '')) === '') {
                $missing = TRUE;
                break;
            }
        }
        if (!$missing) return $rows;

        // Match the print/export grouping so the first assignment is pleasant
        // to read, while preserving the original array keys for the caller.
        $keys = array_keys($rows);
        usort($keys, function ($leftKey, $rightKey) use ($rows) {
            $left = $rows[$leftKey];
            $right = $rows[$rightKey];
            $leftCode = $this->mou_code_from_row(is_array($left) ? $left : array());
            $rightCode = $this->mou_code_from_row(is_array($right) ? $right : array());
            if ($leftCode !== $rightCode) return strnatcasecmp($leftCode, $rightCode);
            $leftDate = substr((string) (isset($left['event_start_date']) ? $left['event_start_date'] : ''), 0, 10);
            $rightDate = substr((string) (isset($right['event_start_date']) ? $right['event_start_date'] : ''), 0, 10);
            if ($leftDate !== $rightDate) return strcmp($rightDate, $leftDate);
            $leftEvent = (int) (isset($left['event_id']) ? $left['event_id'] : 0);
            $rightEvent = (int) (isset($right['event_id']) ? $right['event_id'] : 0);
            if ($leftEvent !== $rightEvent) return $rightEvent <=> $leftEvent;
            foreach (array('district_name', 'village_name') as $field) {
                $comparison = strnatcasecmp(
                    trim((string) (isset($left[$field]) ? $left[$field] : '')),
                    trim((string) (isset($right[$field]) ? $right[$field] : ''))
                );
                if ($comparison !== 0) return $comparison;
            }
            return ((int) $leftKey) <=> ((int) $rightKey);
        });

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            foreach ($keys as $key) {
                $row =& $rows[$key];
                if (trim((string) (isset($row['mou_no']) ? $row['mou_no'] : '')) !== '') {
                    unset($row);
                    continue;
                }
                $registrationId = (int) (isset($row['id']) ? $row['id'] : 0);
                if ($registrationId < 1) throw new RuntimeException('Registrasi legacy tidak memiliki ID yang valid untuk nomor MOU.');

                $locked = $this->db->query(
                    'SELECT r.id,r.mou_no,r.mou_sequence,r.mou_regency_code,r.mou_year,r.regency_id,r.regency_name,e.start_date FROM registrations r JOIN training_events e ON e.id=r.event_id WHERE r.id=? FOR UPDATE',
                    array($registrationId)
                )->row_array();
                if (!$locked) throw new RuntimeException('Registrasi tidak ditemukan saat menetapkan nomor MOU.');
                if (trim((string) $locked['mou_no']) !== '') {
                    $row['mou_no'] = $locked['mou_no'];
                    if (isset($locked['mou_sequence'])) $row['mou_sequence'] = $locked['mou_sequence'];
                    if (isset($locked['mou_regency_code'])) $row['mou_regency_code'] = $locked['mou_regency_code'];
                    if (isset($locked['mou_year'])) $row['mou_year'] = $locked['mou_year'];
                    unset($row);
                    continue;
                }

                $code = $this->mou_code_from_row(array_merge($locked, array(
                    'regency_code' => isset($row['regency_code']) ? $row['regency_code'] : ''
                )));
                $mou = $this->allocate_registration_mou($code, $locked['start_date']);
                $updated = $this->db->query(
                    "UPDATE registrations SET mou_no=?,mou_sequence=?,mou_regency_code=?,mou_year=? WHERE id=? AND (mou_no IS NULL OR TRIM(mou_no)='')",
                    array($mou['mou_no'], $mou['sequence'], $mou['regency_code'], $mou['year'], $registrationId)
                );
                if (!$updated || $this->db->affected_rows() !== 1) {
                    throw new RuntimeException('Nomor MOU legacy gagal disimpan.');
                }
                $row['mou_no'] = $mou['mou_no'];
                $row['mou_sequence'] = $mou['sequence'];
                $row['mou_regency_code'] = $mou['regency_code'];
                $row['mou_year'] = $mou['year'];
                unset($row);
            }
            if ($this->db->trans_status() === FALSE) throw new RuntimeException('Transaksi nomor MOU gagal.');
            if (!$this->db->trans_commit()) throw new RuntimeException('Transaksi nomor MOU gagal diselesaikan.');
            $this->db->db_debug = $originalDbDebug;
            return $rows;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $originalDbDebug;
            throw $e;
        }
    }

    /** Summary for the registration index, independent of the current page. */
    public function get_all_summary($filters = array())
    {
        $this->db->select(
            'COUNT(*) AS registration_count,
             COALESCE(SUM((SELECT COUNT(*) FROM participants p WHERE p.registration_id=r.id AND p.is_active=1 AND p.deleted_at IS NULL)),0) AS participant_count',
            FALSE
        )->from('registrations r')->join('training_events e', 'e.id=r.event_id');
        $this->apply_registration_filters($filters);
        $row = $this->db->get()->row_array();
        return $row ?: array('registration_count' => 0, 'participant_count' => 0);
    }

    /** Shared filters keep paginated rows and their summary in sync. */
    private function apply_registration_filters($filters = array())
    {
        if (!empty($filters['active_only'])) {
            $this->db->where('e.status', 'open')->where('r.status', 'active');
        }
        if (array_key_exists('event_ids', $filters) && is_array($filters['event_ids'])) {
            $eventIds = array_values(array_unique(array_filter(array_map('intval', $filters['event_ids']))));
            if ($eventIds) $this->db->where_in('r.event_id', $eventIds);
            else $this->db->where('1=0', NULL, FALSE);
        } elseif (!empty($filters['event_id'])) {
            $this->db->where('r.event_id', (int) $filters['event_id']);
        }
        if (!empty($filters['q'])) {
            $this->db->group_start()
                ->like('r.village_name', $filters['q'])
                ->or_like('r.district_name', $filters['q'])
                ->or_like('r.regency_name', $filters['q'])
                ->or_like('e.name', $filters['q'])
                ->group_end();
        }
    }

    /**
     * Return the participant roster used by the attendance printout.
     * One row is returned per participant so the printed sheet can be signed
     * individually.  By default only open events are included (the original
     * active-event behavior); archive callers pass $includeHistorical=TRUE to
     * include a validated draft/closed event. Region names are stored on the
     * registration snapshot so historical output stays stable when the master
     * wilayah data changes later.
     */
    public function participants_for_print(array $eventIds = array(), $includeHistorical = FALSE)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $eventIds), function ($id) {
            return $id > 0;
        })));
        if (!$ids) return array();

        $query = $this->db
            ->select('e.id AS event_id,e.code AS event_code,e.name AS event_name,e.start_date,e.end_date,e.location,
                r.id AS registration_id,r.province_name,r.regency_name,r.district_name,r.village_name,
                p.id AS participant_id,p.full_name,COALESCE(NULLIF(p.position,\'\'),NULLIF(vp.name,\'\'),\'-\') AS position,
                COALESCE(p.phone,\'\') AS phone', FALSE)
            ->from('participants p')
            ->join('registrations r', 'r.id=p.registration_id')
            ->join('training_events e', 'e.id=r.event_id')
            ->join('village_positions vp', 'vp.id=p.position_id', 'left')
            ->where_in('e.id', $ids)
            ->where('r.status', 'active')
            ->where('p.is_active', 1)
            ->where('p.deleted_at IS NULL', NULL, FALSE);

        if (!$includeHistorical) $query->where('e.status', 'open');

        return $query->order_by('e.start_date', 'DESC')
            ->order_by('e.id', 'DESC')
            ->order_by('r.district_name', 'ASC')
            ->order_by('r.village_name', 'ASC')
            ->order_by('p.full_name', 'ASC')
            ->get()->result_array();
    }

    public function get($id)
    {
        $row = $this->db->select('r.*,e.name AS event_name,e.code AS event_code,e.billing_mode,e.village_fee,e.participant_fee,e.included_participant_count,e.status AS event_status,
            canceller.name AS canceller_name,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status IN ("pending","verified")) AS committed_amount,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.registration_id=r.id AND py.status="pending") AS pending_amount', FALSE)
            ->from('registrations r')->join('training_events e', 'e.id=r.event_id')
            ->join('users canceller', 'canceller.id=r.cancelled_by', 'left')
            ->where('r.id', (int) $id)->get()->row_array();
        if (!$row) return NULL;
        $row['participants'] = $this->db->select('p.*,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.participant_id=p.id AND py.status="verified") AS paid_amount,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.participant_id=p.id AND py.status IN ("pending","verified")) AS committed_amount,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.participant_id=p.id AND py.status="pending") AS pending_amount', FALSE)
            ->from('participants p')->where(array('p.registration_id'=>(int)$id,'p.is_active'=>1))
            ->where('p.deleted_at IS NULL', NULL, FALSE)->order_by('p.id')->get()->result_array();
        // Keep inactive rows available for audit and historical event review.
        // Operational cards use `participants`; the archive/detail view can
        // show this immutable history without making a cancelled participant
        // appear as an active attendee.
        $row['participants_history'] = $this->db->select('p.*,
            COALESCE(NULLIF(p.position,\'\'),NULLIF(vp.name,\'\'),\'-\') AS position_label,
            deactivator.name AS deactivator_name,
            (SELECT np.id FROM participants np WHERE np.replacement_for_id=p.id ORDER BY np.id DESC LIMIT 1) AS replacement_id,
            (SELECT np.full_name FROM participants np WHERE np.replacement_for_id=p.id ORDER BY np.id DESC LIMIT 1) AS replacement_name,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.participant_id=p.id AND py.status="verified") AS paid_amount,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.participant_id=p.id AND py.status IN ("pending","verified")) AS committed_amount,
            (SELECT COALESCE(SUM(py.amount),0) FROM payments py WHERE py.participant_id=p.id AND py.status="pending") AS pending_amount', FALSE)
            ->from('participants p')->join('village_positions vp', 'vp.id=p.position_id', 'left')
            ->join('users deactivator', 'deactivator.id=p.deactivated_by', 'left')
            ->where('p.registration_id', (int) $id)
            ->group_start()->where('p.is_active', 0)->or_where('p.deleted_at IS NOT NULL')->group_end()
            ->order_by('p.id', 'DESC')->get()->result_array();
        $row['payments'] = $this->db->select('py.*,a.name AS account_name,p.full_name AS participant_name,u.name AS creator_name,v.name AS verifier_name,x.name AS rejector_name')
            ->from('payments py')->join('fund_accounts a', 'a.id=py.account_id')->join('participants p', 'p.id=py.participant_id', 'left')
            ->join('users u', 'u.id=py.created_by', 'left')->join('users v', 'v.id=py.verified_by', 'left')
            ->join('users x', 'x.id=py.rejected_by', 'left')
            ->where('py.registration_id', (int) $id)->order_by('py.payment_date', 'DESC')->order_by('py.id', 'DESC')->get()->result_array();
        $statusHistory = $this->db->select('h.*,COALESCE(NULLIF(h.actor_name,\'\'),u.name,\'-\') AS actor_label', FALSE)
            ->from('payment_status_history h')
            ->join('payments py', 'py.id=h.payment_id')
            ->join('users u', 'u.id=h.actor_id', 'left')
            ->where('py.registration_id', (int) $id)
            ->order_by('h.occurred_at', 'ASC')->order_by('h.id', 'ASC')->get()->result_array();
        $historyByPayment = array();
        foreach ($statusHistory as $historyRow) $historyByPayment[(int) $historyRow['payment_id']][] = $historyRow;
        foreach ($row['payments'] as &$paymentRow) {
            $paymentRow['status_history'] = isset($historyByPayment[(int) $paymentRow['id']]) ? $historyByPayment[(int) $paymentRow['id']] : array();
        }
        unset($paymentRow);
        $row['participant_revisions'] = $this->db->select('pr.*,COALESCE(NULLIF(pr.actor_name,\'\'),u.name,\'-\') AS actor_label', FALSE)
            ->from('participant_revisions pr')
            ->join('users u', 'u.id=pr.actor_id', 'left')
            ->where('pr.registration_id', (int) $id)
            ->order_by('pr.occurred_at', 'DESC')->order_by('pr.id', 'DESC')->get()->result_array();
        $row['paid_amount'] = simp_money_decimal($this->db->select_sum('amount')->where(array('registration_id'=>(int)$id,'status'=>'verified'))->get('payments')->row()->amount ?: '0',TRUE);
        return $row;
    }

    public function validate_villages($event, $villageIds)
    {
        $ids = $this->normalize_ids($villageIds);
        if (!$ids) throw new InvalidArgumentException('Pilih minimal satu desa.');
        $allowed = array(); foreach ($event['regencies'] as $r) { $key=simp_regency_identity_key($r); if($key!=='')$allowed[$key]=TRUE; }
        $rows = $this->regionDb->select('d.id AS village_id,d.desa AS village_name,k.id AS district_id,k.kecamatan AS district_name,kt.id AS regency_id,kt.kota AS regency_name,kt.kode_kota AS regency_code,p.id AS province_id,p.provinsi AS province_name')
            ->from('data_desa d')->join('data_kecamatan k', 'k.id=d.id_kecamatan')->join('data_kota kt', 'kt.id=k.id_kota')->join('data_provinsi p', 'p.id=kt.id_provinsi')
            ->where_in('d.id', $ids)->get()->result_array();
        $indexed = array(); foreach ($rows as $row) $indexed[(string)$row['village_id']] = $row;
        if (count($indexed) !== count($ids)) throw new InvalidArgumentException('Salah satu desa tidak ditemukan pada master wilayah.');
        $ordered = array();
        foreach ($ids as $id) {
            if (empty($allowed[simp_regency_identity_key($indexed[$id])])) throw new InvalidArgumentException('Desa ' . $indexed[$id]['village_name'] . ' berada di luar cakupan event.');
            if ($this->registration_exists_for_village((int) $event['id'], $indexed[$id])) {
                throw new InvalidArgumentException('Desa ' . $indexed[$id]['village_name'] . ' sudah terdaftar pada event ini.');
            }
            $ordered[] = $indexed[$id];
        }
        return $ordered;
    }

    public function create_batch($event, $villages, $participantGroups, $notesByVillage, $paymentGroups, $creatorId, $autoVerifyPayments)
    {
        $positionMap = $this->active_position_map();
        if (!$positionMap) throw new InvalidArgumentException('Belum ada jabatan aktif. Tambahkan jabatan pada menu Master Jabatan terlebih dahulu.');
        if (count($villages) > 20) throw new InvalidArgumentException('Maksimal 20 desa dapat diregistrasikan dalam satu kali simpan.');
        $prepared = array();
        foreach ($villages as $village) {
            $villageId=(string)$village['village_id'];
            $participantRows=isset($participantGroups[$villageId])?$participantGroups[$villageId]:array();
            if(count((array)$participantRows)>20)throw new InvalidArgumentException('Maksimal 20 peserta untuk Desa '.$village['village_name'].' dalam satu kali registrasi.');
            $participants=$this->normalize_participants($participantRows, $positionMap);
            if(!$participants) throw new InvalidArgumentException('Isi minimal satu peserta untuk Desa '.$village['village_name'].'.');
            $prepared[]=array(
                'village'=>$village,
                'participants'=>$participants,
                'payments'=>isset($paymentGroups[$villageId]) && is_array($paymentGroups[$villageId]) ? $paymentGroups[$villageId] : array()
            );
        }
        // Allocate a batch in the same Kecamatan/desa order used by the
        // mailing export.  This keeps newly issued numbers intuitive without
        // ever changing numbers that were assigned in an earlier batch.
        usort($prepared, function ($left, $right) {
            $leftCode = $this->mou_code_from_row(isset($left['village']) && is_array($left['village']) ? $left['village'] : array());
            $rightCode = $this->mou_code_from_row(isset($right['village']) && is_array($right['village']) ? $right['village'] : array());
            if ($leftCode !== $rightCode) return strnatcasecmp($leftCode, $rightCode);
            foreach (array('district_name', 'village_name') as $field) {
                $comparison = strnatcasecmp(
                    trim((string) (isset($left['village'][$field]) ? $left['village'][$field] : '')),
                    trim((string) (isset($right['village'][$field]) ? $right['village'][$field] : ''))
                );
                if ($comparison !== 0) return $comparison;
            }
            return strnatcasecmp(
                (string) (isset($left['village']['village_id']) ? $left['village']['village_id'] : ''),
                (string) (isset($right['village']['village_id']) ? $right['village']['village_id'] : '')
            );
        });
        $created = array(); $createdPayments = array(); $now = date('Y-m-d H:i:s');
        $originalDbDebug=$this->db->db_debug;$this->db->db_debug=FALSE;
        $this->db->trans_begin();
        try {
            $lockedEvent = $this->db->query(
                'SELECT id,status,start_date,billing_mode,village_fee,participant_fee,included_participant_count FROM training_events WHERE id=? FOR UPDATE',
                array((int)$event['id'])
            )->row_array();
            if(!$lockedEvent||$lockedEvent['status']!=='open'){
            throw new InvalidArgumentException('Event sudah tidak aktif. Registrasi tidak dapat disimpan.');
            }

            $lockedPositionMap = $this->active_position_map(TRUE);
            foreach ($prepared as $preparedItem) {
                foreach ($preparedItem['participants'] as $preparedParticipant) {
                    if (!isset($lockedPositionMap[(int) $preparedParticipant['position_id']])) {
                        throw new InvalidArgumentException('Salah satu jabatan peserta baru saja dinonaktifkan. Muat ulang form registrasi.');
                    }
                }
            }

            $currentRegencies=$this->db->select('province_id,province_name,regency_id,regency_name')->where('event_id',(int)$lockedEvent['id'])->get('event_regencies')->result_array();
            $currentRegencies=simp_resolve_event_regencies($this->regionDb,$currentRegencies);
            $allowedRegencies=array();foreach($currentRegencies as $regency){$key=simp_regency_identity_key($regency);if($key!=='')$allowedRegencies[$key]=TRUE;}
            foreach($prepared as $item){
                if(empty($allowedRegencies[simp_regency_identity_key($item['village'])])){
                    throw new InvalidArgumentException('Cakupan event telah berubah. Muat ulang form registrasi dan pilih desa kembali.');
                }
                // Lock event di atas menserialkan registrasi untuk event yang
                // sama. Ulangi pemeriksaan setelah lock agar format legacy dan
                // current tidak dapat membuat desa fisik yang sama dua kali.
                if ($this->registration_exists_for_village((int) $lockedEvent['id'], $item['village'])) {
                    throw new InvalidArgumentException('Desa '.$item['village']['village_name'].' sudah terdaftar pada event ini.');
                }
            }

            foreach ($prepared as $item) {
                $village=$item['village'];
                $villageId = (string) $village['village_id'];
                $participants=$item['participants'];
                $expected = $this->expected_amount($lockedEvent, count($participants));
                // Allocate the MOU number while the registration transaction
                // is open.  The counter row is locked, so concurrent events
                // cannot receive the same number; a rollback also releases
                // the number instead of leaving a misleading gap.
                $mou = $this->allocate_registration_mou($this->mou_code_from_row($village), $lockedEvent['start_date']);
                $registration = $village;
                $registration['event_id'] = (int)$lockedEvent['id'];
                unset($registration['village_id'], $registration['regency_code']);
                $registration['village_id'] = $villageId;
                $registration['mou_no'] = $mou['mou_no'];
                $registration['mou_sequence'] = $mou['sequence'];
                $registration['mou_regency_code'] = $mou['regency_code'];
                $registration['mou_year'] = $mou['year'];
                $registration['expected_amount'] = $expected;
                if(isset($notesByVillage[$villageId])&&!is_scalar($notesByVillage[$villageId]))throw new InvalidArgumentException('Catatan Desa '.$village['village_name'].' tidak valid.');
                $registrationNote=isset($notesByVillage[$villageId])?trim((string)$notesByVillage[$villageId]):'';
                if(strlen($registrationNote)>2000)throw new InvalidArgumentException('Catatan Desa '.$village['village_name'].' maksimal 2.000 karakter.');
                $registration['notes'] = $registrationNote!==''?$registrationNote:NULL;
                $registration['status'] = 'active'; $registration['created_by'] = $creatorId; $registration['created_at']=$now; $registration['updated_at']=$now;
                if (!$this->db->insert('registrations', $registration)) {
                    $error=$this->db->error();
                    if (!empty($error['code']) && (int)$error['code']===1062) throw new InvalidArgumentException('Desa '.$village['village_name'].' sudah terdaftar pada event ini.');
                    throw new RuntimeException(!empty($error['message'])?$error['message']:'Registrasi gagal disimpan.');
                }
                $registrationId=(int)$this->db->insert_id(); $created[]=$registrationId;
                $participantIds=array(); $participantExpected=array(); $participantIndex=0;
                foreach ($participants as $participantKey => $participant) {
                    $participant['registration_id']=$registrationId;
                    $participant['expected_amount']=$this->participant_expected_amount($lockedEvent,$participantIndex);
                    $participant['is_active']=1; $participant['created_by']=(int)$creatorId; $participant['updated_by']=(int)$creatorId;
                    $participant['created_at']=$now; $participant['updated_at']=$now;
                    if (!$this->db->insert('participants',$participant)) throw new RuntimeException('Peserta gagal disimpan.');
                    $participantIds[(string)$participantKey]=(int)$this->db->insert_id();
                    $participantExpected[(string)$participantKey]=simp_money_decimal($participant['expected_amount'],TRUE);
                    $participantIndex++;
                }

                foreach ($item['payments'] as $targetKey => $payment) {
                    $targetKey=(string)$targetKey;
                    if($lockedEvent['billing_mode']==='per_participant'){
                        if($targetKey==='village'||!isset($participantIds[$targetKey])) throw new InvalidArgumentException('Tujuan pembayaran peserta tidak valid.');
                        $participantId=$participantIds[$targetKey];
                        $targetExpected=$participantExpected[$targetKey];
                    }else{
                        if($targetKey!=='village') throw new InvalidArgumentException('Pembayaran event ini harus dicatat pada tingkat desa.');
                        $participantId=NULL;
                        $targetExpected=$expected;
                    }
                    $rawAmount=isset($payment['amount'])&&is_scalar($payment['amount'])?(string)$payment['amount']:'';
                    $amount=simp_money_decimal($rawAmount,FALSE);
                    $targetCents=simp_money_cents($targetExpected);$amountCents=simp_money_cents($amount);
                    if($amount===NULL||$targetCents===NULL||$amountCents===NULL||$amountCents>$targetCents) throw new InvalidArgumentException('Nominal pembayaran awal untuk Desa '.$village['village_name'].' tidak valid atau melebihi tagihan.');
                    $method=isset($payment['method'])&&is_scalar($payment['method'])?(string)$payment['method']:'';
                    $allowedTypes=array('cash'=>array('cash'),'transfer'=>array('bank','personal'),'qris'=>array('qris'));
                    if(!isset($allowedTypes[$method])) throw new InvalidArgumentException('Metode pembayaran awal tidak valid.');
                    $rawAccountId=isset($payment['account_id'])&&is_scalar($payment['account_id'])?(string)$payment['account_id']:'';
                    if(!ctype_digit($rawAccountId)||(int)$rawAccountId<1)throw new InvalidArgumentException('Akun penerima pembayaran awal tidak valid.');
                    $accountId=(int)$rawAccountId;
                    $account=$this->db->query('SELECT id,type,is_active FROM fund_accounts WHERE id=? FOR UPDATE',array($accountId))->row_array();
                    if(!$account||!(int)$account['is_active']||!in_array($account['type'],$allowedTypes[$method],TRUE)) throw new InvalidArgumentException('Akun penerima tidak aktif atau tidak sesuai dengan metode pembayaran.');
                    $paymentDate=isset($payment['payment_date'])&&is_scalar($payment['payment_date'])?(string)$payment['payment_date']:'';
                    $date=DateTime::createFromFormat('Y-m-d',$paymentDate);
                    if(!$date||$date->format('Y-m-d')!==$paymentDate) throw new InvalidArgumentException('Tanggal pembayaran awal tidak valid.');
                    $proofPath=isset($payment['proof_path'])&&is_scalar($payment['proof_path'])&&$payment['proof_path']!==''?(string)$payment['proof_path']:NULL;
                    if($proofPath!==NULL&&!$this->valid_upload_path($proofPath,'payments')) throw new InvalidArgumentException('Bukti pembayaran tidak valid.');
                    if($proofPath!==NULL&&$this->db->where('proof_path',$proofPath)->count_all_results('payments')) throw new InvalidArgumentException('Bukti pembayaran sudah digunakan oleh transaksi lain.');
                    if($method!=='cash'&&!$proofPath) throw new InvalidArgumentException('Bukti transfer atau QRIS wajib diunggah.');
                    if(isset($payment['note'])&&!is_scalar($payment['note']))throw new InvalidArgumentException('Catatan pembayaran tidak valid.');
                    $paymentNote=isset($payment['note'])?trim((string)$payment['note']):'';
                    if(strlen($paymentNote)>2000)throw new InvalidArgumentException('Catatan pembayaran maksimal 2.000 karakter.');
                    $paymentRow=array(
                        'receipt_no'=>$this->receipt_no(),'event_id'=>(int)$lockedEvent['id'],'registration_id'=>$registrationId,
                        'participant_id'=>$participantId,'account_id'=>$accountId,'payment_date'=>$paymentDate,'method'=>$method,
                        'amount'=>$amount,'status'=>$autoVerifyPayments?'verified':'pending','proof_path'=>$proofPath,
                        'note'=>$paymentNote!==''?$paymentNote:NULL,
                        'created_by'=>$creatorId,'verified_by'=>$autoVerifyPayments?$creatorId:NULL,
                        'verified_at'=>$autoVerifyPayments?$now:NULL,'created_at'=>$now,'updated_at'=>$now
                    );
                    if(!$this->db->insert('payments',$paymentRow)) throw new RuntimeException('Pembayaran awal gagal disimpan.');
                    $paymentId=(int)$this->db->insert_id();$createdPayments[]=$paymentId;
                    $this->record_payment_status_history(
                        $paymentId,
                        NULL,
                        $paymentRow['status'],
                        (int)$creatorId,
                        $now,
                        'Pembayaran awal dicatat.'
                    );
                    if($autoVerifyPayments){
                        if(!$this->db->insert('ledger_entries',array(
                            'account_id'=>$accountId,'entry_date'=>$paymentDate,'direction'=>'in','amount'=>$amount,
                            'source_type'=>'payment','source_id'=>$paymentId,'description'=>'Penerimaan '.$paymentRow['receipt_no'],
                            'created_by'=>$creatorId,'created_at'=>$now
                        ))) throw new RuntimeException('Buku besar pembayaran awal gagal disimpan.');
                    }
                }
            }
            if ($this->db->trans_status()===FALSE) { $error=$this->db->error(); throw new RuntimeException(!empty($error['message'])?$error['message']:'Registrasi gagal disimpan.'); }
            if(!$this->db->trans_commit())throw new RuntimeException('Transaksi registrasi gagal diselesaikan.');
            $this->db->db_debug=$originalDbDebug;
            return array('registration_ids'=>$created,'payment_ids'=>$createdPayments);
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug=$originalDbDebug;
            throw $e;
        }
    }

    public function add_participant($registration, $participant, $actorId)
    {
        $originalDbDebug=$this->db->db_debug; $this->db->db_debug=FALSE; $this->db->trans_begin();
        try {
            $lockedEvent=$this->db->query('SELECT id,status,billing_mode,participant_fee,included_participant_count FROM training_events WHERE id=? FOR UPDATE',array((int)$registration['event_id']))->row_array();
            $lockedRegistration=$this->db->query('SELECT id,event_id,status FROM registrations WHERE id=? FOR UPDATE',array((int)$registration['id']))->row_array();
            if(!$lockedEvent||!$lockedRegistration||(int)$lockedRegistration['event_id']!==(int)$lockedEvent['id']||$lockedRegistration['status']!=='active'||$lockedEvent['status']!=='open') throw new InvalidArgumentException('Peserta hanya dapat ditambah pada registrasi aktif dan event yang masih aktif.');
            $positionMap = $this->active_position_map(TRUE);
            if (!$positionMap) throw new InvalidArgumentException('Belum ada jabatan aktif. Tambahkan jabatan pada menu Master Jabatan terlebih dahulu.');
            $items=$this->normalize_participants(array($participant), $positionMap); if(!$items) throw new InvalidArgumentException('Nama peserta wajib diisi.');
            $this->assert_unique_active_participant_names((int)$lockedRegistration['id'], $items);
            $data=$items[0]; $data['registration_id']=(int)$registration['id']; $data['is_active']=1;
            $data['created_by']=(int)$actorId; $data['updated_by']=(int)$actorId;
            $data['created_at']=date('Y-m-d H:i:s'); $data['updated_at']=$data['created_at'];
            $participantCount=(int)$this->db->where(array('registration_id'=>$lockedRegistration['id'],'is_active'=>1))->where('deleted_at IS NULL',NULL,FALSE)->count_all_results('participants');
            if($participantCount>=20) throw new InvalidArgumentException('Maksimal 20 peserta untuk satu desa.');
            $data['expected_amount']=$lockedEvent['billing_mode']==='per_participant'
                ?simp_money_decimal($lockedEvent['participant_fee'],TRUE)
                :($lockedEvent['billing_mode']==='per_village_extra'&&$participantCount>=(int)$lockedEvent['included_participant_count']?simp_money_decimal($lockedEvent['participant_fee'],TRUE):'0.00');
            if(!$this->db->insert('participants',$data)) throw new RuntimeException('Peserta gagal ditambahkan.');
            $id=(int)$this->db->insert_id();
            if(simp_money_cents($data['expected_amount'])>0 && !$this->db->set('expected_amount','expected_amount+'. $this->db->escape($data['expected_amount']),FALSE)->where('id',$lockedRegistration['id'])->update('registrations')) throw new RuntimeException('Tagihan registrasi gagal diperbarui.');
            if($this->db->trans_status()===FALSE) throw new RuntimeException('Peserta gagal ditambahkan.');
            if(!$this->db->trans_commit()) throw new RuntimeException('Transaksi peserta gagal diselesaikan.');
            $this->db->db_debug=$originalDbDebug; return $id;
        } catch(Throwable $e) {
            $this->db->trans_rollback(); $this->db->db_debug=$originalDbDebug; throw $e;
        }
    }

    /** Insert participants and their optional first payments in one transaction. */
    public function add_participants($registration, array $participants, $paymentGroups = array(), $actorId = 0, $autoVerifyPayments = FALSE)
    {
        /* Keep the old three-argument model call compatible for any legacy
         * controller or CLI job that still adds participants without payment. */
        if (!is_array($paymentGroups)) {
            $legacyActor = (int) $paymentGroups;
            $paymentGroups = array();
            $actorId = $legacyActor;
            $autoVerifyPayments = FALSE;
        }
        if (count($participants) > 20) throw new InvalidArgumentException('Maksimal 20 peserta dapat ditambahkan sekaligus.');
        $originalDbDebug=$this->db->db_debug; $this->db->db_debug=FALSE; $this->db->trans_begin();
        try {
            $lockedEvent = $this->db->query('SELECT id,status,billing_mode,participant_fee,included_participant_count FROM training_events WHERE id=? FOR UPDATE', array((int)$registration['event_id']))->row_array();
            $locked = $this->db->query('SELECT id,event_id,status,expected_amount,village_name FROM registrations WHERE id=? FOR UPDATE', array((int)$registration['id']))->row_array();
            if (!$lockedEvent || !$locked || (int)$locked['event_id'] !== (int)$lockedEvent['id'] || $locked['status'] !== 'active' || $lockedEvent['status'] !== 'open') throw new InvalidArgumentException('Peserta hanya dapat ditambah pada registrasi aktif dan event yang masih aktif.');
            $positionMap = $this->active_position_map(TRUE);
            if (!$positionMap) throw new InvalidArgumentException('Belum ada jabatan aktif. Tambahkan jabatan pada menu Master Jabatan terlebih dahulu.');
            $items = $this->normalize_participants($participants, $positionMap);
            if (!$items) throw new InvalidArgumentException('Isi minimal satu peserta.');
            if (count($items) > 20) throw new InvalidArgumentException('Maksimal 20 peserta dapat ditambahkan sekaligus.');
            $this->assert_unique_active_participant_names((int)$locked['id'], $items);
            $count = (int)$this->db->where(array('registration_id'=>$locked['id'],'is_active'=>1))->where('deleted_at IS NULL',NULL,FALSE)->count_all_results('participants');
            if ($count + count($items) > 20) throw new InvalidArgumentException('Maksimal 20 peserta untuk satu desa.');
            $now=date('Y-m-d H:i:s'); $ids=array(); $idMap=array(); $expectedMap=array();
            foreach($items as $itemKey=>$item){
                $item['registration_id']=(int)$locked['id'];
                $item['expected_amount']=$lockedEvent['billing_mode']==='per_participant'
                    ?simp_money_decimal($lockedEvent['participant_fee'],TRUE)
                    :($lockedEvent['billing_mode']==='per_village_extra'&&$count >= (int)$lockedEvent['included_participant_count']?simp_money_decimal($lockedEvent['participant_fee'],TRUE):'0.00');
                $item['is_active']=1; $item['created_by']=(int)$actorId; $item['updated_by']=(int)$actorId;
                $item['created_at']=$now; $item['updated_at']=$now;
                if(!$this->db->insert('participants',$item))throw new RuntimeException('Peserta gagal ditambahkan.');
                $participantId=(int)$this->db->insert_id();
                $ids[]=$participantId;
                $idMap[(string)$itemKey]=$participantId;
                $expectedMap[(string)$itemKey]=simp_money_decimal($item['expected_amount'],TRUE);
                if(simp_money_cents($item['expected_amount'])>0){
                    $this->db->set('expected_amount','expected_amount+'. $this->db->escape($item['expected_amount']),FALSE)->where('id',$locked['id'])->update('registrations');
                }
                $count++;
            }
            $lockedAfter=$this->db->query('SELECT expected_amount FROM registrations WHERE id=? FOR UPDATE',array((int)$locked['id']))->row_array();
            if(!$lockedAfter)throw new RuntimeException('Tagihan registrasi tidak tersedia.');
            $paymentIds=array();
            foreach($paymentGroups as $targetKey=>$payment){
                if(!is_array($payment))throw new InvalidArgumentException('Data pembayaran tidak valid.');
                $targetKey=(string)$targetKey;
                if($lockedEvent['billing_mode']==='per_participant'){
                    if($targetKey==='village'||!isset($idMap[$targetKey]))throw new InvalidArgumentException('Tujuan pembayaran peserta tidak valid.');
                    $targetParticipantId=$idMap[$targetKey];
                    $targetExpected=$expectedMap[$targetKey];
                    $targetLabel='peserta baru';
                }else{
                    if($targetKey!=='village')throw new InvalidArgumentException('Pembayaran event ini harus dicatat pada tingkat desa.');
                    $targetParticipantId=NULL;
                    $targetExpected=simp_money_decimal($lockedAfter['expected_amount'],TRUE);
                    $targetLabel=!empty($locked['village_name'])?$locked['village_name']:'desa';
                }
                $paymentIds[]=$this->insert_inline_payment_locked(
                    $lockedEvent,(int)$locked['id'],$targetParticipantId,$targetExpected,$payment,
                    (int)$actorId,(bool)$autoVerifyPayments,$now,$targetLabel
                );
            }
            if($this->db->trans_status()===FALSE)throw new RuntimeException('Peserta gagal ditambahkan.');
            if(!$this->db->trans_commit())throw new RuntimeException('Transaksi peserta gagal diselesaikan.');
            $this->db->db_debug=$originalDbDebug;
            return array('participant_ids'=>$ids,'payment_ids'=>$paymentIds);
        }catch(Throwable $e){$this->db->trans_rollback();$this->db->db_debug=$originalDbDebug;throw $e;}
    }

    /**
     * Update participant identity and optional first payment atomically.
     * Billing snapshots remain untouched so a typo correction can never alter
     * the amount already committed to the event.
     */
    public function update_participant($registrationId, $participantId, array $participant, $paymentGroups = array(), $actorId = 0, $autoVerifyPayments = FALSE)
    {
        /* Backward-compatible identity-only signature: (id, participant,
         * data, actorId). */
        if (!is_array($paymentGroups)) {
            $legacyActor = (int) $paymentGroups;
            $paymentGroups = array();
            $actorId = $legacyActor;
            $autoVerifyPayments = FALSE;
        }
        $registrationId = (int) $registrationId;
        $participantId = (int) $participantId;
        if ($registrationId < 1 || $participantId < 1) throw new InvalidArgumentException('Identitas peserta tidak valid.');

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $context = $this->lock_mutation_context($registrationId);
            $event = $context['event'];
            $registration = $context['registration'];
            if ($event['status'] !== 'open' || $registration['status'] !== 'active') {
                throw new InvalidArgumentException('Peserta hanya dapat diubah pada event dan registrasi yang aktif.');
            }
            $before = $this->db->query(
                'SELECT * FROM participants WHERE id=? AND registration_id=? AND is_active=1 AND deleted_at IS NULL FOR UPDATE',
                array($participantId, $registrationId)
            )->row_array();
            if (!$before) throw new InvalidArgumentException('Peserta aktif tidak ditemukan.');

            // A master jabatan may have been retired after this participant
            // was registered. A name/phone correction must still be possible
            // without silently changing the historical position snapshot;
            // only a newly selected position must be active.
            $positionMap = $this->active_position_map(TRUE);
            $requestedPosition = isset($participant['position_id']) && is_scalar($participant['position_id']) ? (int) $participant['position_id'] : 0;
            if ($requestedPosition > 0 && !isset($positionMap[$requestedPosition]) && (int) $before['position_id'] === $requestedPosition) {
                $positionMap[$requestedPosition] = (string) ($before['position'] ?: '');
            }
            if (!$positionMap) throw new InvalidArgumentException('Belum ada jabatan aktif.');
            $items = $this->normalize_participants(array('edit' => $participant), $positionMap);
            if (!$items) throw new InvalidArgumentException('Isi data peserta terlebih dahulu.');
            $data = $items['edit'];
            $this->assert_unique_active_participant_names($registrationId, $items, array($participantId));

            $now = date('Y-m-d H:i:s');
            if (!$this->db->where(array('id' => $participantId, 'registration_id' => $registrationId))->update('participants', array(
                'full_name' => $data['full_name'],
                'position_id' => $data['position_id'],
                'position' => $data['position'],
                'phone' => $data['phone'],
                'updated_by' => (int) $actorId,
                'updated_at' => $now
            ))) throw new RuntimeException('Data peserta gagal diperbarui.');
            $after = $this->db->where('id', $participantId)->get('participants')->row_array();
            $this->record_participant_revision(
                $participantId,
                $registrationId,
                'update',
                $before,
                $after,
                NULL,
                (int) $actorId,
                $now
            );
            $paymentIds=array();
            foreach($paymentGroups as $targetKey=>$payment){
                if(!is_array($payment))throw new InvalidArgumentException('Data pembayaran tidak valid.');
                $targetKey=(string)$targetKey;
                if($event['billing_mode']==='per_participant'){
                    if($targetKey!==(string)$participantId)throw new InvalidArgumentException('Tujuan pembayaran peserta tidak valid.');
                    $targetParticipantId=$participantId;
                    $targetExpected=simp_money_decimal($before['expected_amount'],TRUE);
                    $targetLabel=$after['full_name'];
                }else{
                    if($targetKey!=='village')throw new InvalidArgumentException('Pembayaran event ini harus dicatat pada tingkat desa.');
                    $targetParticipantId=NULL;
                    $targetExpected=simp_money_decimal($registration['expected_amount'],TRUE);
                    $targetLabel=!empty($registration['village_name'])?$registration['village_name']:'desa';
                }
                $paymentIds[]=$this->insert_inline_payment_locked(
                    $event,$registrationId,$targetParticipantId,$targetExpected,$payment,
                    (int)$actorId,(bool)$autoVerifyPayments,$now,$targetLabel
                );
            }
            $this->assert_transaction_ok('Data peserta gagal diperbarui.');
            $this->commit_transaction_or_throw('Perubahan peserta gagal diselesaikan.');
            $this->db->db_debug = $originalDbDebug;
            return array('before' => $before, 'after' => $after, 'registration_id' => $registrationId, 'participant_id' => $participantId, 'payment_ids'=>$paymentIds);
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $originalDbDebug;
            throw $e;
        }
    }

    /**
     * Replace a participant while retaining the old billing slot.  This is
     * safer than deleting/re-adding because the original row and its audit
     * trail remain available for attendance and accounting review.
     */
    public function replace_participant($registrationId, $participantId, array $replacement, $reason, $actorId)
    {
        $reason = trim((string) $reason);
        if (strlen($reason) < 3 || strlen($reason) > 500) throw new InvalidArgumentException('Alasan penggantian wajib diisi (3–500 karakter).');
        $registrationId = (int) $registrationId;
        $participantId = (int) $participantId;
        if ($registrationId < 1 || $participantId < 1) throw new InvalidArgumentException('Identitas peserta tidak valid.');

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $context = $this->lock_mutation_context($registrationId);
            $event = $context['event'];
            $registration = $context['registration'];
            if ($event['status'] !== 'open' || $registration['status'] !== 'active') {
                throw new InvalidArgumentException('Peserta hanya dapat diganti pada event dan registrasi yang aktif.');
            }
            $positionMap = $this->active_position_map(TRUE);
            if (!$positionMap) throw new InvalidArgumentException('Belum ada jabatan aktif.');
            $items = $this->normalize_participants(array('replacement' => $replacement), $positionMap);
            if (!$items) throw new InvalidArgumentException('Isi data peserta pengganti terlebih dahulu.');
            $data = $items['replacement'];
            $old = $this->db->query(
                'SELECT * FROM participants WHERE id=? AND registration_id=? AND is_active=1 AND deleted_at IS NULL FOR UPDATE',
                array($participantId, $registrationId)
            )->row_array();
            if (!$old) throw new InvalidArgumentException('Peserta aktif tidak ditemukan.');
            $this->assert_unique_active_participant_names($registrationId, $items, array($participantId));
            $participantCommittedCents = simp_money_cents($this->committed_participant_amount($participantId));
            if ($participantCommittedCents === NULL) throw new RuntimeException('Nominal pembayaran peserta tidak valid.');
            if ($participantCommittedCents > 0) {
                throw new InvalidArgumentException('Peserta yang sudah memiliki pembayaran menunggu verifikasi atau terverifikasi tidak dapat diganti. Gunakan ubah data atau koreksi pembayaran terlebih dahulu.');
            }

            $now = date('Y-m-d H:i:s');
            if (!$this->db->where('id', $participantId)->update('participants', array(
                'is_active' => 0,
                'deactivation_reason' => 'Diganti: ' . $reason,
                'deactivated_by' => (int) $actorId,
                'updated_by' => (int) $actorId,
                'deleted_at' => $now,
                'updated_at' => $now
            ))) throw new RuntimeException('Peserta lama gagal dinonaktifkan.');

            // Preserve the old expected amount; replacement is an identity
            // correction, never a way to change the event tariff.
            $newRow = array(
                'registration_id' => $registrationId,
                'full_name' => $data['full_name'],
                'position_id' => $data['position_id'],
                'position' => $data['position'],
                'phone' => $data['phone'],
                'expected_amount' => $old['expected_amount'],
                'is_active' => 1,
                'replacement_for_id' => $participantId,
                'created_by' => (int) $actorId,
                'updated_by' => (int) $actorId,
                'created_at' => $now,
                'updated_at' => $now
            );
            if (!$this->db->insert('participants', $newRow)) throw new RuntimeException('Peserta pengganti gagal disimpan.');
            $newId = (int) $this->db->insert_id();
            $after = $this->db->where('id', $newId)->get('participants')->row_array();
            $this->record_participant_revision(
                $participantId,
                $registrationId,
                'replace',
                $old,
                $after,
                $reason,
                (int) $actorId,
                $now
            );
            $this->assert_transaction_ok('Penggantian peserta gagal disimpan.');
            $this->commit_transaction_or_throw('Penggantian peserta gagal diselesaikan.');
            $this->db->db_debug = $originalDbDebug;
            return array('before' => $old, 'after' => $after, 'old_participant_id' => $participantId, 'participant_id' => $newId, 'registration_id' => $registrationId, 'reason' => $reason);
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $originalDbDebug;
            throw $e;
        }
    }

    /** Deactivate a participant without deleting the immutable source row. */
    public function deactivate_participant($registrationId, $participantId, $reason, $actorId)
    {
        $reason = trim((string) $reason);
        if (strlen($reason) < 3 || strlen($reason) > 500) throw new InvalidArgumentException('Alasan menonaktifkan wajib diisi (3–500 karakter).');
        $registrationId = (int) $registrationId;
        $participantId = (int) $participantId;
        if ($registrationId < 1 || $participantId < 1) throw new InvalidArgumentException('Identitas peserta tidak valid.');

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $context = $this->lock_mutation_context($registrationId);
            $event = $context['event'];
            $registration = $context['registration'];
            if ($event['status'] !== 'open' || $registration['status'] !== 'active') {
                throw new InvalidArgumentException('Peserta hanya dapat dinonaktifkan pada event dan registrasi yang aktif.');
            }
            $participant = $this->db->query(
                'SELECT * FROM participants WHERE id=? AND registration_id=? AND is_active=1 AND deleted_at IS NULL FOR UPDATE',
                array($participantId, $registrationId)
            )->row_array();
            if (!$participant) throw new InvalidArgumentException('Peserta aktif tidak ditemukan.');
            $activeCount = (int) $this->db->where(array('registration_id' => $registrationId, 'is_active' => 1))->where('deleted_at IS NULL', NULL, FALSE)->count_all_results('participants');
            if ($activeCount <= 1) throw new InvalidArgumentException('Registrasi harus memiliki minimal satu peserta. Untuk membatalkan seluruh desa gunakan Batalkan Registrasi.');
            $participantCommittedCents = simp_money_cents($this->committed_participant_amount($participantId));
            if ($participantCommittedCents === NULL) throw new RuntimeException('Nominal pembayaran peserta tidak valid.');
            if ($participantCommittedCents > 0) {
                throw new InvalidArgumentException('Peserta yang sudah memiliki pembayaran menunggu verifikasi atau terverifikasi tidak dapat dinonaktifkan.');
            }

            $newCount = $activeCount - 1;
            $newExpected = $this->expected_amount($event, $newCount);
            $committed = $this->committed_registration_amount($registrationId);
            $newExpectedCents = simp_money_cents($newExpected);
            $committedCents = simp_money_cents($committed);
            if ($newExpectedCents === NULL || $committedCents === NULL || $committedCents > $newExpectedCents) {
                throw new InvalidArgumentException('Peserta tidak dapat dinonaktifkan karena tagihan baru lebih kecil daripada pembayaran yang sudah tercatat.');
            }
            // In the hybrid package the participant fee is a positional slot
            // (included participants first, extras afterwards). Once a
            // village payment exists, shifting those slots would change the
            // historical fee allocation, so require a replacement/edit.
            if ($event['billing_mode'] === 'per_village_extra' && $committedCents > 0) {
                throw new InvalidArgumentException('Peserta pada paket desa + tambahan tidak dapat dinonaktifkan setelah pembayaran desa tercatat. Gunakan Ganti Peserta agar slot tagihan tetap aman.');
            }

            $now = date('Y-m-d H:i:s');
            if (!$this->db->where('id', $participantId)->update('participants', array(
                'is_active' => 0,
                'deactivation_reason' => $reason,
                'deactivated_by' => (int) $actorId,
                'updated_by' => (int) $actorId,
                'deleted_at' => $now,
                'updated_at' => $now
            ))) throw new RuntimeException('Peserta gagal dinonaktifkan.');
            $this->rebalance_registration_amount_locked($event, $registrationId, $newExpected, $participantId);
            $after = $this->db->where('id', $participantId)->get('participants')->row_array();
            $this->record_participant_revision(
                $participantId,
                $registrationId,
                'deactivate',
                $participant,
                $after,
                $reason,
                (int) $actorId,
                $now
            );
            $this->assert_transaction_ok('Penonaktifan peserta gagal disimpan.');
            $this->commit_transaction_or_throw('Penonaktifan peserta gagal diselesaikan.');
            $this->db->db_debug = $originalDbDebug;
            return array('before' => $participant, 'after' => $after, 'registration_id' => $registrationId, 'participant_id' => $participantId, 'reason' => $reason, 'expected_amount' => $newExpected);
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $originalDbDebug;
            throw $e;
        }
    }

    /**
     * Permanently remove one active participant and every payment it owns.
     *
     * Verified payment journals are reversed inside the same transaction.
     * The deletion is refused when that money has already been spent, when
     * the new bill would be lower than remaining payment commitments, or when
     * this is the registration's final active participant.
     */
    public function delete_participant($registrationId, $participantId, $canDeleteVerifiedPayments = FALSE)
    {
        $registrationId = (int) $registrationId;
        $participantId = (int) $participantId;
        if ($registrationId < 1 || $participantId < 1) throw new InvalidArgumentException('Identitas peserta tidak valid.');

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $context = $this->lock_mutation_context($registrationId);
            $event = $context['event'];
            $registration = $context['registration'];
            if ($event['status'] !== 'open' || $registration['status'] !== 'active') {
                throw new InvalidArgumentException('Peserta hanya dapat dihapus pada event dan registrasi yang aktif.');
            }

            $participant = $this->db->query(
                'SELECT * FROM participants WHERE id=? AND registration_id=? AND is_active=1 AND deleted_at IS NULL FOR UPDATE',
                array($participantId, $registrationId)
            )->row_array();
            if (!$participant) throw new InvalidArgumentException('Peserta aktif tidak ditemukan.');

            $activeCount = (int) $this->db->where(array('registration_id' => $registrationId, 'is_active' => 1))
                ->where('deleted_at IS NULL', NULL, FALSE)->count_all_results('participants');
            if ($activeCount <= 1) {
                throw new InvalidArgumentException('Peserta terakhir tidak dapat dihapus sendiri. Gunakan tombol Hapus pada daftar registrasi untuk menghapus seluruh registrasi desa.');
            }

            $paymentRows = $this->db->query(
                'SELECT * FROM payments WHERE registration_id=? AND participant_id=? ORDER BY id FOR UPDATE',
                array($registrationId, $participantId)
            )->result_array();
            $paymentIds = array();
            $verifiedAccountIds = array();
            $proofPaths = array();
            $participantCommittedCents = 0;
            foreach ($paymentRows as $paymentRow) {
                $paymentId = (int) $paymentRow['id'];
                $paymentIds[] = $paymentId;
                if ((int) $paymentRow['event_id'] !== (int) $event['id'] || !in_array($paymentRow['status'], array('pending', 'verified', 'rejected'), TRUE)) {
                    throw new RuntimeException('Data pembayaran peserta tidak konsisten.');
                }
                $amountCents = simp_money_cents($paymentRow['amount']);
                if ($amountCents === NULL || $amountCents <= 0) throw new RuntimeException('Nominal pembayaran peserta tidak valid.');
                if (in_array($paymentRow['status'], array('pending', 'verified'), TRUE)) {
                    if ($participantCommittedCents > PHP_INT_MAX - $amountCents) throw new RuntimeException('Total pembayaran peserta terlalu besar.');
                    $participantCommittedCents += $amountCents;
                }
                if ($paymentRow['status'] === 'verified') {
                    if (!$canDeleteVerifiedPayments) {
                        throw new InvalidArgumentException('Peserta memiliki pembayaran terverifikasi. Penghapusan hanya dapat dilakukan oleh pengguna yang berhak memverifikasi pembayaran.');
                    }
                    $verifiedAccountIds[(int) $paymentRow['account_id']] = (int) $paymentRow['account_id'];
                }
                if (!empty($paymentRow['proof_path'])) $proofPaths[(string) $paymentRow['proof_path']] = (string) $paymentRow['proof_path'];
            }

            $accounts = array();
            if ($verifiedAccountIds) {
                sort($verifiedAccountIds, SORT_NUMERIC);
                $placeholders = implode(',', array_fill(0, count($verifiedAccountIds), '?'));
                $lockedAccounts = $this->db->query(
                    'SELECT * FROM fund_accounts WHERE id IN ('.$placeholders.') ORDER BY id FOR UPDATE',
                    array_values($verifiedAccountIds)
                )->result_array();
                foreach ($lockedAccounts as $account) $accounts[(int) $account['id']] = $account;
                if (count($accounts) !== count($verifiedAccountIds)) throw new RuntimeException('Akun penerima pembayaran tidak lengkap.');
            }

            $ledgerRows = array();
            if ($paymentIds) {
                $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
                $ledgerRows = $this->db->query(
                    'SELECT * FROM ledger_entries WHERE source_type=? AND source_id IN ('.$placeholders.') ORDER BY account_id,id FOR UPDATE',
                    array_merge(array('payment'), $paymentIds)
                )->result_array();
            }
            $ledgerByPayment = array();
            foreach ($ledgerRows as $ledgerRow) $ledgerByPayment[(int) $ledgerRow['source_id']][] = $ledgerRow;

            $verifiedPaymentCents = 0;
            $reversalByAccount = array();
            foreach ($paymentRows as $paymentRow) {
                $paymentId = (int) $paymentRow['id'];
                $relatedLedger = isset($ledgerByPayment[$paymentId]) ? $ledgerByPayment[$paymentId] : array();
                if ($paymentRow['status'] !== 'verified') {
                    if ($relatedLedger) throw new RuntimeException('Pembayaran yang belum terverifikasi memiliki jurnal yang tidak semestinya.');
                    continue;
                }
                $amountCents = simp_money_cents($paymentRow['amount']);
                if ($amountCents === NULL || $amountCents <= 0 || count($relatedLedger) !== 1) {
                    throw new RuntimeException('Jurnal pembayaran tidak konsisten sehingga peserta belum dapat dihapus.');
                }
                $ledger = $relatedLedger[0];
                if ((int) $ledger['account_id'] !== (int) $paymentRow['account_id'] ||
                    $ledger['direction'] !== 'in' || simp_money_cents($ledger['amount']) !== $amountCents) {
                    throw new RuntimeException('Jurnal pembayaran tidak konsisten sehingga peserta belum dapat dihapus.');
                }
                $accountId = (int) $paymentRow['account_id'];
                if ($verifiedPaymentCents > PHP_INT_MAX - $amountCents) throw new RuntimeException('Total pembayaran peserta terlalu besar.');
                $verifiedPaymentCents += $amountCents;
                if (!isset($reversalByAccount[$accountId])) $reversalByAccount[$accountId] = 0;
                if ($reversalByAccount[$accountId] > PHP_INT_MAX - $amountCents) throw new RuntimeException('Total pembayaran akun terlalu besar.');
                $reversalByAccount[$accountId] += $amountCents;
            }

            foreach ($reversalByAccount as $accountId => $reversalCents) {
                if (!isset($accounts[$accountId])) throw new RuntimeException('Akun penerima pembayaran tidak ditemukan.');
                $movement = $this->db->select(
                    "COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) incoming, COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) outgoing",
                    FALSE
                )->where('account_id', (int) $accountId)->get('ledger_entries')->row_array();
                $openingCents = $this->signed_money_cents($accounts[$accountId]['opening_balance']);
                $incomingCents = simp_money_cents(isset($movement['incoming']) ? $movement['incoming'] : NULL);
                $outgoingCents = simp_money_cents(isset($movement['outgoing']) ? $movement['outgoing'] : NULL);
                if ($openingCents === NULL || $incomingCents === NULL || $outgoingCents === NULL) {
                    throw new RuntimeException('Saldo akun penerima pembayaran tidak valid.');
                }
                if ($openingCents + $incomingCents - $outgoingCents < $reversalCents) {
                    throw new InvalidArgumentException('Peserta tidak dapat dihapus karena dana pembayarannya sudah terpakai dan saldo akun '.$accounts[$accountId]['name'].' tidak mencukupi.');
                }
            }

            $newExpected = $this->expected_amount($event, $activeCount - 1);
            $newExpectedCents = simp_money_cents($newExpected);
            $committedBeforeCents = simp_money_cents($this->committed_registration_amount($registrationId));
            if ($newExpectedCents === NULL || $committedBeforeCents === NULL || $committedBeforeCents < $participantCommittedCents) {
                throw new RuntimeException('Perhitungan tagihan registrasi tidak konsisten.');
            }
            $committedAfterCents = $committedBeforeCents - $participantCommittedCents;
            if ($committedAfterCents > $newExpectedCents) {
                throw new InvalidArgumentException('Peserta tidak dapat dihapus karena tagihan baru lebih kecil daripada pembayaran registrasi yang tetap tersimpan.');
            }
            if ($event['billing_mode'] === 'per_village_extra' && $committedAfterCents > 0) {
                throw new InvalidArgumentException('Peserta pada paket desa + tambahan tidak dapat dihapus setelah pembayaran desa tercatat. Gunakan Ganti Peserta agar slot tagihan tetap aman.');
            }

            $historyRows = array();
            if ($paymentIds) {
                $historyRows = $this->db->where_in('payment_id', $paymentIds)->get('payment_status_history')->result_array();
                if ($ledgerRows && (!$this->db->where('source_type', 'payment')->where_in('source_id', $paymentIds)->delete('ledger_entries') || $this->db->affected_rows() !== count($ledgerRows))) {
                    throw new RuntimeException('Jurnal pembayaran peserta gagal dibatalkan.');
                }
                if ($historyRows && (!$this->db->where_in('payment_id', $paymentIds)->delete('payment_status_history') || $this->db->affected_rows() !== count($historyRows))) {
                    throw new RuntimeException('Riwayat pembayaran peserta gagal dihapus.');
                }
            }

            $revisionRows = $this->db->where('participant_id', $participantId)->get('participant_revisions')->result_array();
            if ($revisionRows && (!$this->db->where('participant_id', $participantId)->delete('participant_revisions') || $this->db->affected_rows() !== count($revisionRows))) {
                throw new RuntimeException('Riwayat perubahan peserta gagal dihapus.');
            }
            if ($paymentRows && (!$this->db->where(array('registration_id' => $registrationId, 'participant_id' => $participantId))->delete('payments') || $this->db->affected_rows() !== count($paymentRows))) {
                throw new RuntimeException('Pembayaran peserta gagal dihapus.');
            }
            if (!$this->db->where(array('id' => $participantId, 'registration_id' => $registrationId))->delete('participants') || $this->db->affected_rows() !== 1) {
                throw new RuntimeException('Peserta gagal dihapus.');
            }
            $this->rebalance_registration_amount_locked($event, $registrationId, $newExpected, $participantId);

            $this->assert_transaction_ok('Penghapusan peserta gagal disimpan.');
            $this->commit_transaction_or_throw('Penghapusan peserta gagal diselesaikan.');
            $this->db->db_debug = $originalDbDebug;
            return array(
                'registration_id' => $registrationId,
                'event_id' => (int) $registration['event_id'],
                'district_name' => (string) $registration['district_name'],
                'village_name' => (string) $registration['village_name'],
                'participant_name' => (string) $participant['full_name'],
                'position' => (string) $participant['position'],
                'payment_count' => count($paymentRows),
                'verified_payment_amount' => simp_money_from_cents($verifiedPaymentCents),
                'expected_amount' => $newExpected,
                'proof_paths' => array_values($proofPaths)
            );
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $originalDbDebug;
            throw $e;
        }
    }

    /** Cancel a whole registration while preserving its rows for archive use. */
    public function cancel_registration($registrationId, $reason, $actorId)
    {
        $reason = trim((string) $reason);
        if (strlen($reason) < 3 || strlen($reason) > 500) throw new InvalidArgumentException('Alasan pembatalan wajib diisi (3–500 karakter).');
        $registrationId = (int) $registrationId;
        if ($registrationId < 1) throw new InvalidArgumentException('Identitas registrasi tidak valid.');

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $context = $this->lock_mutation_context($registrationId);
            $event = $context['event'];
            $registration = $context['registration'];
            if ($event['status'] !== 'open' || $registration['status'] !== 'active') {
                throw new InvalidArgumentException('Hanya registrasi aktif pada event aktif yang dapat dibatalkan.');
            }
            $registrationCommittedCents = simp_money_cents($this->committed_registration_amount($registrationId));
            if ($registrationCommittedCents === NULL) throw new RuntimeException('Nominal pembayaran registrasi tidak valid.');
            if ($registrationCommittedCents > 0) {
                throw new InvalidArgumentException('Registrasi memiliki pembayaran menunggu verifikasi atau terverifikasi. Koreksi pembayaran terlebih dahulu sebelum membatalkan.');
            }
            $participantRows = $this->db->query(
                'SELECT * FROM participants WHERE registration_id=? AND is_active=1 AND deleted_at IS NULL FOR UPDATE',
                array($registrationId)
            )->result_array();
            $now = date('Y-m-d H:i:s');
            if (!$this->db->where('id', $registrationId)->update('registrations', array(
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_by' => (int) $actorId,
                'cancelled_at' => $now,
                'updated_at' => $now
            ))) throw new RuntimeException('Registrasi gagal dibatalkan.');
            if (!$this->db->where(array('registration_id' => $registrationId, 'is_active' => 1))->where('deleted_at IS NULL', NULL, FALSE)->update('participants', array(
                'is_active' => 0,
                'deactivation_reason' => 'Registrasi dibatalkan: ' . $reason,
                'deactivated_by' => (int) $actorId,
                'updated_by' => (int) $actorId,
                'deleted_at' => $now,
                'updated_at' => $now
            ))) throw new RuntimeException('Peserta registrasi gagal diarsipkan.');
            foreach ($participantRows as $participantRow) {
                $participantAfter = $this->db->where('id', (int) $participantRow['id'])->get('participants')->row_array();
                $this->record_participant_revision(
                    (int) $participantRow['id'],
                    $registrationId,
                    'registration_cancel',
                    $participantRow,
                    $participantAfter,
                    $reason,
                    (int) $actorId,
                    $now
                );
            }
            $this->assert_transaction_ok('Pembatalan registrasi gagal disimpan.');
            $this->commit_transaction_or_throw('Pembatalan registrasi gagal diselesaikan.');
            $this->db->db_debug = $originalDbDebug;
            return array('registration_id' => $registrationId, 'event_id' => (int) $event['id'], 'reason' => $reason, 'old_status' => 'active', 'new_status' => 'cancelled');
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $originalDbDebug;
            throw $e;
        }
    }

    /** Restore only participants archived by the latest registration cancellation. */
    public function restore_registration($registrationId, $reason, $actorId)
    {
        $reason = trim((string) $reason);
        if (strlen($reason) < 3 || strlen($reason) > 500) throw new InvalidArgumentException('Alasan pemulihan wajib diisi (3–500 karakter).');
        $registrationId = (int) $registrationId;
        if ($registrationId < 1) throw new InvalidArgumentException('Identitas registrasi tidak valid.');

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $context = $this->lock_mutation_context($registrationId);
            $event = $context['event'];
            $registration = $context['registration'];
            if ($event['status'] !== 'open') throw new InvalidArgumentException('Registrasi hanya dapat dipulihkan ketika event sedang aktif.');
            if ($registration['status'] !== 'cancelled') throw new InvalidArgumentException('Registrasi ini tidak dalam status dibatalkan.');

            $registrationCommittedCents = simp_money_cents($this->committed_registration_amount($registrationId));
            if ($registrationCommittedCents === NULL) throw new RuntimeException('Nominal pembayaran registrasi tidak valid.');
            if ($registrationCommittedCents > 0) throw new InvalidArgumentException('Registrasi tidak dapat dipulihkan karena memiliki pembayaran menunggu verifikasi atau terverifikasi.');
            if (empty($registration['cancelled_at'])) throw new InvalidArgumentException('Waktu pembatalan tidak tersedia sehingga peserta tidak dapat dipulihkan otomatis.');

            // Match the cancellation timestamp so participants that had been
            // inactive before the registration was cancelled remain archived.
            $participantRows = $this->db->query(
                "SELECT * FROM participants WHERE registration_id=? AND is_active=0 AND deleted_at=? AND deactivation_reason LIKE 'Registrasi dibatalkan:%' FOR UPDATE",
                array($registrationId, $registration['cancelled_at'])
            )->result_array();
            if (!$participantRows) throw new InvalidArgumentException('Tidak ada peserta dari pembatalan terakhir yang dapat dipulihkan otomatis.');

            $now = date('Y-m-d H:i:s');
            if (!$this->db->where('id', $registrationId)->update('registrations', array(
                'status' => 'active',
                'cancellation_reason' => NULL,
                'cancelled_by' => NULL,
                'cancelled_at' => NULL,
                'updated_at' => $now
            ))) throw new RuntimeException('Status registrasi gagal dipulihkan.');

            foreach ($participantRows as $participantBefore) {
                $participantId = (int) $participantBefore['id'];
                if (!$this->db->where('id', $participantId)->update('participants', array(
                    'is_active' => 1,
                    'deactivation_reason' => NULL,
                    'deactivated_by' => NULL,
                    'deleted_at' => NULL,
                    'updated_by' => (int) $actorId,
                    'updated_at' => $now
                ))) throw new RuntimeException('Peserta registrasi gagal dipulihkan.');
                $participantAfter = $this->db->where('id', $participantId)->get('participants')->row_array();
                $this->record_participant_revision(
                    $participantId,
                    $registrationId,
                    'registration_restore',
                    $participantBefore,
                    $participantAfter,
                    $reason,
                    (int) $actorId,
                    $now
                );
            }

            $this->assert_transaction_ok('Pemulihan registrasi gagal disimpan.');
            $this->commit_transaction_or_throw('Pemulihan registrasi gagal diselesaikan.');
            $this->db->db_debug = $originalDbDebug;
            return array(
                'registration_id' => $registrationId,
                'event_id' => (int) $event['id'],
                'reason' => $reason,
                'restored_participant_count' => count($participantRows),
                'old_status' => 'cancelled',
                'new_status' => 'active'
            );
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $originalDbDebug;
            throw $e;
        }
    }

    /**
     * Permanently remove one active registration and every row it owns.
     *
     * Payment journals are reversed in the same transaction.  A verified
     * payment can only be removed by a payment verifier, and only while the
     * receiving account still contains enough money to remove that credit.
     * MOU counters are deliberately left untouched so a deleted number is
     * never issued again.
     */
    public function delete_registration($registrationId, $canDeleteVerifiedPayments = FALSE)
    {
        $registrationId = (int) $registrationId;
        if ($registrationId < 1) throw new InvalidArgumentException('Identitas registrasi tidak valid.');

        $originalDbDebug = $this->db->db_debug;
        $this->db->db_debug = FALSE;
        $this->db->trans_begin();
        try {
            $context = $this->lock_mutation_context($registrationId);
            $event = $context['event'];
            $registration = $context['registration'];
            if ($event['status'] !== 'open' || $registration['status'] !== 'active') {
                throw new InvalidArgumentException('Hanya registrasi aktif pada event aktif yang dapat dihapus permanen.');
            }

            $participantRows = $this->db->query(
                'SELECT * FROM participants WHERE registration_id=? ORDER BY id FOR UPDATE',
                array($registrationId)
            )->result_array();
            $paymentRows = $this->db->query(
                'SELECT * FROM payments WHERE registration_id=? ORDER BY id FOR UPDATE',
                array($registrationId)
            )->result_array();

            $paymentIds = array();
            $verifiedAccountIds = array();
            $proofPaths = array();
            foreach ($paymentRows as $paymentRow) {
                $paymentId = (int) $paymentRow['id'];
                $paymentIds[] = $paymentId;
                if (!in_array($paymentRow['status'], array('pending', 'verified', 'rejected'), TRUE)) {
                    throw new RuntimeException('Status pembayaran registrasi tidak konsisten.');
                }
                if ($paymentRow['status'] === 'verified') {
                    if (!$canDeleteVerifiedPayments) {
                        throw new InvalidArgumentException('Registrasi memiliki pembayaran terverifikasi. Penghapusan hanya dapat dilakukan oleh pengguna yang berhak memverifikasi pembayaran.');
                    }
                    $verifiedAccountIds[(int) $paymentRow['account_id']] = (int) $paymentRow['account_id'];
                }
                if (!empty($paymentRow['proof_path'])) $proofPaths[(string) $paymentRow['proof_path']] = (string) $paymentRow['proof_path'];
            }

            $accounts = array();
            if ($verifiedAccountIds) {
                sort($verifiedAccountIds, SORT_NUMERIC);
                $placeholders = implode(',', array_fill(0, count($verifiedAccountIds), '?'));
                $lockedAccounts = $this->db->query(
                    'SELECT * FROM fund_accounts WHERE id IN ('.$placeholders.') ORDER BY id FOR UPDATE',
                    array_values($verifiedAccountIds)
                )->result_array();
                foreach ($lockedAccounts as $account) $accounts[(int) $account['id']] = $account;
                if (count($accounts) !== count($verifiedAccountIds)) throw new RuntimeException('Akun penerima pembayaran tidak lengkap.');
            }

            $ledgerRows = array();
            if ($paymentIds) {
                $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
                $ledgerRows = $this->db->query(
                    'SELECT * FROM ledger_entries WHERE source_type=? AND source_id IN ('.$placeholders.') ORDER BY account_id,id FOR UPDATE',
                    array_merge(array('payment'), $paymentIds)
                )->result_array();
            }
            $ledgerByPayment = array();
            foreach ($ledgerRows as $ledgerRow) $ledgerByPayment[(int) $ledgerRow['source_id']][] = $ledgerRow;

            $verifiedPaymentCents = 0;
            $reversalByAccount = array();
            foreach ($paymentRows as $paymentRow) {
                $paymentId = (int) $paymentRow['id'];
                $relatedLedger = isset($ledgerByPayment[$paymentId]) ? $ledgerByPayment[$paymentId] : array();
                if ($paymentRow['status'] !== 'verified') {
                    if ($relatedLedger) throw new RuntimeException('Pembayaran yang belum terverifikasi memiliki jurnal yang tidak semestinya.');
                    continue;
                }

                $amountCents = simp_money_cents($paymentRow['amount']);
                if ($amountCents === NULL || $amountCents <= 0 || count($relatedLedger) !== 1) {
                    throw new RuntimeException('Jurnal pembayaran tidak konsisten sehingga registrasi belum dapat dihapus.');
                }
                $ledger = $relatedLedger[0];
                if ((int) $ledger['account_id'] !== (int) $paymentRow['account_id'] ||
                    $ledger['direction'] !== 'in' || simp_money_cents($ledger['amount']) !== $amountCents) {
                    throw new RuntimeException('Jurnal pembayaran tidak konsisten sehingga registrasi belum dapat dihapus.');
                }
                $accountId = (int) $paymentRow['account_id'];
                if ($verifiedPaymentCents > PHP_INT_MAX - $amountCents) throw new RuntimeException('Total pembayaran registrasi terlalu besar.');
                $verifiedPaymentCents += $amountCents;
                if (!isset($reversalByAccount[$accountId])) $reversalByAccount[$accountId] = 0;
                if ($reversalByAccount[$accountId] > PHP_INT_MAX - $amountCents) throw new RuntimeException('Total pembayaran akun terlalu besar.');
                $reversalByAccount[$accountId] += $amountCents;
            }

            foreach ($reversalByAccount as $accountId => $reversalCents) {
                if (!isset($accounts[$accountId])) throw new RuntimeException('Akun penerima pembayaran tidak ditemukan.');
                $movement = $this->db->select(
                    "COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) incoming, COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) outgoing",
                    FALSE
                )->where('account_id', (int) $accountId)->get('ledger_entries')->row_array();
                $openingCents = $this->signed_money_cents($accounts[$accountId]['opening_balance']);
                $incomingCents = simp_money_cents(isset($movement['incoming']) ? $movement['incoming'] : NULL);
                $outgoingCents = simp_money_cents(isset($movement['outgoing']) ? $movement['outgoing'] : NULL);
                if ($openingCents === NULL || $incomingCents === NULL || $outgoingCents === NULL) {
                    throw new RuntimeException('Saldo akun penerima pembayaran tidak valid.');
                }
                $currentBalanceCents = $openingCents + $incomingCents - $outgoingCents;
                if ($currentBalanceCents < $reversalCents) {
                    throw new InvalidArgumentException('Registrasi tidak dapat dihapus karena dana pembayarannya sudah terpakai dan saldo akun '.$accounts[$accountId]['name'].' tidak mencukupi.');
                }
            }

            $historyRows = array();
            if ($paymentIds) {
                $historyRows = $this->db->where_in('payment_id', $paymentIds)->get('payment_status_history')->result_array();
                if ($ledgerRows && (!$this->db->where('source_type', 'payment')->where_in('source_id', $paymentIds)->delete('ledger_entries') || $this->db->affected_rows() !== count($ledgerRows))) {
                    throw new RuntimeException('Jurnal pembayaran registrasi gagal dibatalkan.');
                }
                if ($historyRows && (!$this->db->where_in('payment_id', $paymentIds)->delete('payment_status_history') || $this->db->affected_rows() !== count($historyRows))) {
                    throw new RuntimeException('Riwayat pembayaran registrasi gagal dihapus.');
                }
            }

            $revisionRows = $this->db->where('registration_id', $registrationId)->get('participant_revisions')->result_array();
            if ($revisionRows && (!$this->db->where('registration_id', $registrationId)->delete('participant_revisions') || $this->db->affected_rows() !== count($revisionRows))) {
                throw new RuntimeException('Riwayat peserta registrasi gagal dihapus.');
            }
            if ($paymentRows && (!$this->db->where('registration_id', $registrationId)->delete('payments') || $this->db->affected_rows() !== count($paymentRows))) {
                throw new RuntimeException('Pembayaran registrasi gagal dihapus.');
            }
            if ($participantRows && (!$this->db->where('registration_id', $registrationId)->delete('participants') || $this->db->affected_rows() !== count($participantRows))) {
                throw new RuntimeException('Peserta registrasi gagal dihapus.');
            }
            if (!$this->db->where('id', $registrationId)->delete('registrations') || $this->db->affected_rows() !== 1) {
                throw new RuntimeException('Registrasi gagal dihapus.');
            }

            $this->assert_transaction_ok('Penghapusan registrasi gagal disimpan.');
            $this->commit_transaction_or_throw('Penghapusan registrasi gagal diselesaikan.');
            $this->db->db_debug = $originalDbDebug;
            return array(
                'registration_id' => $registrationId,
                'event_id' => (int) $registration['event_id'],
                'mou_no' => (string) $registration['mou_no'],
                'regency_name' => (string) $registration['regency_name'],
                'district_name' => (string) $registration['district_name'],
                'village_name' => (string) $registration['village_name'],
                'participant_count' => count($participantRows),
                'payment_count' => count($paymentRows),
                'verified_payment_amount' => simp_money_from_cents($verifiedPaymentCents),
                'proof_paths' => array_values($proofPaths)
            );
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $originalDbDebug;
            throw $e;
        }
    }

    public function accounts()
    {
        return $this->db->where('is_active',1)->order_by('sort_order')->order_by('name')->get('fund_accounts')->result_array();
    }

    public function payment_target($registration, $participantId)
    {
        if ($registration['billing_mode']!=='per_participant') return array('participant_id'=>NULL,'expected'=>simp_money_decimal($registration['expected_amount'],TRUE),'label'=>$registration['village_name']);
        if (!is_scalar($participantId) || !ctype_digit((string)$participantId) || (int)$participantId < 1) throw new InvalidArgumentException('Peserta tujuan pembayaran tidak valid.');
        $participant=$this->db->where(array('id'=>(int)$participantId,'registration_id'=>$registration['id'],'is_active'=>1))->where('deleted_at IS NULL',NULL,FALSE)->get('participants')->row_array();
        if(!$participant) throw new InvalidArgumentException('Peserta tujuan pembayaran tidak valid.');
        return array('participant_id'=>(int)$participant['id'],'expected'=>simp_money_decimal($participant['expected_amount'],TRUE),'label'=>$participant['full_name']);
    }

    public function committed_payment($registrationId, $participantId)
    {
        $this->db->select_sum('amount')->where('registration_id',(int)$registrationId)->where_in('status',array('pending','verified'));
        $participantId===NULL ? $this->db->where('participant_id IS NULL',NULL,FALSE) : $this->db->where('participant_id',(int)$participantId);
        return simp_money_decimal($this->db->get('payments')->row()->amount ?: '0',TRUE);
    }

    /** Insert one payment while the caller already holds the event/target locks. */
    private function insert_inline_payment_locked(array $event, $registrationId, $participantId, $expectedAmount, array $payment, $creatorId, $autoVerify, $now, $targetLabel)
    {
        $registrationId=(int)$registrationId;
        $creatorId=(int)$creatorId;
        $participantId=$participantId===NULL?NULL:(int)$participantId;
        if(empty($event['id'])||empty($event['status'])||$event['status']!=='open')throw new InvalidArgumentException('Event sudah tidak aktif. Pembayaran tidak dapat dicatat.');
        if(($event['billing_mode']==='per_participant')!==($participantId!==NULL)){
            throw new InvalidArgumentException($event['billing_mode']==='per_participant'?'Pembayaran wajib ditujukan kepada peserta.':'Pembayaran wajib ditujukan kepada desa.');
        }

        $amount=isset($payment['amount'])&&is_scalar($payment['amount'])?simp_money_decimal((string)$payment['amount'],FALSE):NULL;
        $expected=simp_money_decimal($expectedAmount,TRUE);
        $committed=$this->committed_payment($registrationId,$participantId);
        $amountCents=simp_money_cents($amount);
        $expectedCents=simp_money_cents($expected);
        $committedCents=simp_money_cents($committed);
        if($amount===NULL||$amountCents===NULL||$expectedCents===NULL||$committedCents===NULL||$amountCents>$expectedCents-$committedCents){
            throw new InvalidArgumentException('Nominal pembayaran untuk '.$targetLabel.' tidak valid atau melebihi sisa tagihan.');
        }

        $method=isset($payment['method'])&&is_scalar($payment['method'])?(string)$payment['method']:'';
        $allowedTypes=array('cash'=>array('cash'),'transfer'=>array('bank','personal'),'qris'=>array('qris'));
        if(!isset($allowedTypes[$method]))throw new InvalidArgumentException('Metode pembayaran tidak valid.');
        $rawAccountId=isset($payment['account_id'])&&is_scalar($payment['account_id'])?(string)$payment['account_id']:'';
        if(!ctype_digit($rawAccountId)||(int)$rawAccountId<1)throw new InvalidArgumentException('Akun penerima pembayaran tidak valid.');
        $accountId=(int)$rawAccountId;
        $account=$this->db->query('SELECT id,type,is_active FROM fund_accounts WHERE id=? FOR UPDATE',array($accountId))->row_array();
        if(!$account||!(int)$account['is_active']||!in_array($account['type'],$allowedTypes[$method],TRUE)){
            throw new InvalidArgumentException('Akun penerima tidak aktif atau tidak sesuai dengan metode pembayaran.');
        }

        $paymentDate=isset($payment['payment_date'])&&is_scalar($payment['payment_date'])?(string)$payment['payment_date']:'';
        if(!$this->valid_date($paymentDate))throw new InvalidArgumentException('Tanggal pembayaran tidak valid.');
        $proofPath=isset($payment['proof_path'])&&is_scalar($payment['proof_path'])&&trim((string)$payment['proof_path'])!==''?(string)$payment['proof_path']:NULL;
        if($proofPath!==NULL&&!$this->valid_upload_path($proofPath,'payments'))throw new InvalidArgumentException('Bukti pembayaran tidak valid.');
        if($proofPath!==NULL&&$this->db->where('proof_path',$proofPath)->count_all_results('payments'))throw new InvalidArgumentException('Bukti pembayaran sudah digunakan oleh transaksi lain.');
        if($method!=='cash'&&$proofPath===NULL)throw new InvalidArgumentException('Bukti transfer atau QRIS wajib diunggah.');
        if(isset($payment['note'])&&!is_scalar($payment['note']))throw new InvalidArgumentException('Catatan pembayaran tidak valid.');
        $note=isset($payment['note'])?trim((string)$payment['note']):'';
        if(strlen($note)>2000)throw new InvalidArgumentException('Catatan pembayaran maksimal 2.000 karakter.');

        $paymentRow=array(
            'receipt_no'=>$this->receipt_no(),'event_id'=>(int)$event['id'],'registration_id'=>$registrationId,
            'participant_id'=>$participantId,'account_id'=>$accountId,'payment_date'=>$paymentDate,'method'=>$method,
            'amount'=>$amount,'status'=>$autoVerify?'verified':'pending','proof_path'=>$proofPath,'note'=>$note!==''?$note:NULL,
            'created_by'=>$creatorId,'verified_by'=>$autoVerify?$creatorId:NULL,'verified_at'=>$autoVerify?$now:NULL,
            'created_at'=>$now,'updated_at'=>$now
        );
        if(!$this->db->insert('payments',$paymentRow))throw new RuntimeException('Pembayaran gagal disimpan.');
        $paymentId=(int)$this->db->insert_id();
        $this->record_payment_status_history($paymentId,NULL,$paymentRow['status'],$creatorId,$now,'Pembayaran dicatat bersama data peserta.');
        if($autoVerify&&!$this->db->insert('ledger_entries',array(
            'account_id'=>$accountId,'entry_date'=>$paymentDate,'direction'=>'in','amount'=>$amount,
            'source_type'=>'payment','source_id'=>$paymentId,'description'=>'Penerimaan '.$paymentRow['receipt_no'],
            'created_by'=>$creatorId,'created_at'=>$now
        )))throw new RuntimeException('Buku besar pembayaran gagal disimpan.');
        return $paymentId;
    }

    public function create_payment($data, $isVerified, $expectedAmount)
    {
        $isVerified = (bool) $isVerified;
        $requiredStatus = $isVerified ? 'verified' : 'pending';
        if (!isset($data['status']) || (string) $data['status'] !== $requiredStatus) {
            throw new InvalidArgumentException('Status pembayaran tidak sesuai dengan proses verifikasi.');
        }
        $originalDbDebug=$this->db->db_debug;$this->db->db_debug=FALSE;
        $this->db->trans_begin();
        try{
            // Lock event first, matching the event-close transaction order.
            // Whichever request wins determines the safe outcome: either the
            // payment is created and closing sees it, or closing wins and this
            // request is rejected because the event is no longer open.
            $event=$this->db->query('SELECT id,status,billing_mode FROM training_events WHERE id=? FOR UPDATE',array((int)$data['event_id']))->row_array();
            if(!$event||$event['status']!=='open')throw new InvalidArgumentException('Event sudah ditutup. Buka kembali event sebelum mencatat pembayaran baru.');
            if ($data['participant_id'] === NULL) $target=$this->db->query('SELECT id,event_id,status,expected_amount FROM registrations WHERE id=? FOR UPDATE',array((int)$data['registration_id']))->row_array();
            else $target=$this->db->query('SELECT p.id,r.event_id,r.status,p.expected_amount FROM participants p JOIN registrations r ON r.id=p.registration_id WHERE p.id=? AND p.registration_id=? AND p.is_active=1 AND p.deleted_at IS NULL FOR UPDATE',array((int)$data['participant_id'],(int)$data['registration_id']))->row_array();
            if(!$target)throw new InvalidArgumentException('Target pembayaran tidak ditemukan.');
            if($target['status']!=='active')throw new InvalidArgumentException('Registrasi sudah tidak aktif.');
            if((int)$target['event_id']!==(int)$data['event_id'])throw new InvalidArgumentException('Event pembayaran tidak sesuai dengan registrasi.');
            if (($event['billing_mode'] === 'per_participant') !== ($data['participant_id'] !== NULL)) {
                throw new InvalidArgumentException($event['billing_mode'] === 'per_participant' ? 'Event ini wajib menggunakan target pembayaran peserta.' : 'Event ini wajib menggunakan target pembayaran desa.');
            }
            $allowedTypes=array('cash'=>array('cash'),'transfer'=>array('bank','personal'),'qris'=>array('qris'));
            if(empty($allowedTypes[$data['method']]))throw new InvalidArgumentException('Metode pembayaran tidak valid.');
            $account=$this->db->query('SELECT id,type,is_active FROM fund_accounts WHERE id=? FOR UPDATE',array((int)$data['account_id']))->row_array();
            if(!$account||!(int)$account['is_active']||!in_array($account['type'],$allowedTypes[$data['method']],TRUE))throw new InvalidArgumentException('Akun penerima tidak aktif atau tidak sesuai dengan metode pembayaran.');
            if(!empty($data['proof_path'])&&!$this->valid_upload_path($data['proof_path'],'payments'))throw new InvalidArgumentException('Bukti pembayaran tidak valid.');
            if(!empty($data['proof_path'])&&$this->db->where('proof_path',$data['proof_path'])->count_all_results('payments'))throw new InvalidArgumentException('Bukti pembayaran sudah digunakan oleh transaksi lain.');
            if($data['method']!=='cash'&&empty($data['proof_path']))throw new InvalidArgumentException('Bukti transfer atau QRIS wajib diunggah.');
            $committed=$this->committed_payment($data['registration_id'],$data['participant_id']);
            $amount=simp_money_decimal($data['amount'],FALSE);
            $expectedCents=simp_money_cents($target['expected_amount']);
            $requestedExpectedCents=simp_money_cents($expectedAmount);
            $committedCents=simp_money_cents($committed);
            $amountCents=simp_money_cents($amount);
            if($amount===NULL||$expectedCents===NULL||$requestedExpectedCents===NULL||$expectedCents!==$requestedExpectedCents||$committedCents===NULL||$amountCents===NULL||$amountCents>$expectedCents-$committedCents)throw new InvalidArgumentException('Nominal atau snapshot tagihan pembayaran tidak valid. Muat ulang data registrasi.');
            $data['amount']=$amount;
            if(!$this->db->insert('payments',$data))throw new RuntimeException('Pembayaran gagal disimpan.');
            $id=(int)$this->db->insert_id();
            $occurredAt = isset($data['created_at']) && $this->valid_datetime($data['created_at']) ? (string) $data['created_at'] : date('Y-m-d H:i:s');
            $this->record_payment_status_history($id, NULL, $requiredStatus, (int)$data['created_by'], $occurredAt, 'Pembayaran dicatat.');
            if($isVerified&&!$this->db->insert('ledger_entries',array('account_id'=>$data['account_id'],'entry_date'=>$data['payment_date'],'direction'=>'in','amount'=>$data['amount'],'source_type'=>'payment','source_id'=>$id,'description'=>'Penerimaan '.$data['receipt_no'],'created_by'=>$data['created_by'],'created_at'=>date('Y-m-d H:i:s'))))throw new RuntimeException('Buku besar pembayaran gagal disimpan.');
            if($this->db->trans_status()===FALSE){$error=$this->db->error();throw new RuntimeException(!empty($error['message'])?$error['message']:'Pembayaran gagal disimpan.');}
            if(!$this->db->trans_commit())throw new RuntimeException('Transaksi pembayaran gagal diselesaikan.');
            $this->db->db_debug=$originalDbDebug;return $id;
        }catch(Throwable $e){
            $this->db->trans_rollback();
            $this->db->db_debug=$originalDbDebug;
            throw $e;
        }
    }

    public function review_payment($paymentId, $decision, $verifierId)
    {
        $paymentId=(int)$paymentId; $decision=(string)$decision; $now=date('Y-m-d H:i:s');
        if(!in_array($decision,array('verify','reject'),TRUE)) throw new InvalidArgumentException('Keputusan verifikasi tidak valid.');
        $originalDbDebug=$this->db->db_debug; $this->db->db_debug=FALSE; $this->db->trans_begin();
        try {
            $locked=$this->db->query('SELECT py.*,e.billing_mode,e.status AS event_status FROM payments py JOIN training_events e ON e.id=py.event_id WHERE py.id=? FOR UPDATE',array($paymentId))->row_array();
            if(!$locked) throw new InvalidArgumentException('Pembayaran tidak ditemukan.');
            if ((string) $locked['event_status'] !== 'open') throw new InvalidArgumentException('Event sudah ditutup. Arsip registrasi bersifat hanya-baca.');
            if($decision==='verify'){
                if($locked['status']==='verified') throw new InvalidArgumentException('Pembayaran sudah terverifikasi.');
                if($locked['status']==='rejected') throw new InvalidArgumentException('Pembayaran yang ditolak tidak dapat diverifikasi kembali. Catat transaksi baru.');
                if($locked['participant_id']===NULL){
                    $target=$this->db->query('SELECT expected_amount,status AS registration_status,event_id FROM registrations WHERE id=? FOR UPDATE',array((int)$locked['registration_id']))->row_array();
                } else {
                    $target=$this->db->query('SELECT p.expected_amount,p.is_active,p.deleted_at,r.status AS registration_status,r.event_id FROM participants p JOIN registrations r ON r.id=p.registration_id WHERE p.id=? AND p.registration_id=? FOR UPDATE',array((int)$locked['participant_id'],(int)$locked['registration_id']))->row_array();
                }
                if(!$target) throw new RuntimeException('Target tagihan pembayaran tidak tersedia.');
                if (($locked['billing_mode'] === 'per_participant') !== ($locked['participant_id'] !== NULL)) {
                    throw new InvalidArgumentException($locked['billing_mode'] === 'per_participant' ? 'Pembayaran peserta tidak memiliki target peserta yang valid.' : 'Pembayaran event desa tidak boleh memiliki target peserta.');
                }
                if($target['registration_status']!=='active'||(int)$target['event_id']!==(int)$locked['event_id']||
                    ($locked['participant_id']!==NULL&&(!(int)$target['is_active']||$target['deleted_at']!==NULL))) {
                    throw new InvalidArgumentException('Pembayaran tidak dapat diverifikasi karena registrasi atau pesertanya sudah tidak aktif.');
                }
                if(!$this->valid_date($locked['payment_date'])) throw new InvalidArgumentException('Tanggal pembayaran tidak valid.');
                $proofPath=!empty($locked['proof_path'])?(string)$locked['proof_path']:NULL;
                if($proofPath!==NULL&&!$this->valid_upload_path($proofPath,'payments')) throw new InvalidArgumentException('Bukti pembayaran tidak valid atau sudah tidak tersedia.');
                if($locked['method']!=='cash'&&!$this->valid_upload_path($proofPath,'payments')) throw new InvalidArgumentException('Bukti transfer atau QRIS wajib tersedia sebelum verifikasi.');
                $account=$this->db->query('SELECT id,type,is_active FROM fund_accounts WHERE id=? FOR UPDATE',array((int)$locked['account_id']))->row_array();
                $allowedTypes=array('cash'=>array('cash'),'transfer'=>array('bank','personal'),'qris'=>array('qris'));
                if(!$account||!(int)$account['is_active']||empty($allowedTypes[$locked['method']])||!in_array($account['type'],$allowedTypes[$locked['method']],TRUE)) throw new InvalidArgumentException('Akun penerima tidak aktif atau tidak sesuai dengan metode pembayaran.');
                $this->db->select_sum('amount')->where('registration_id',$locked['registration_id'])->where_in('status',array('pending','verified'))->where('id !=',$paymentId);
                $locked['participant_id']===NULL ? $this->db->where('participant_id IS NULL',NULL,FALSE) : $this->db->where('participant_id',$locked['participant_id']);
                $paid=simp_money_decimal($this->db->get('payments')->row()->amount?:'0',TRUE);
                $lockedAmountCents=simp_money_cents($locked['amount']);
                $targetCents=simp_money_cents($target['expected_amount']);
                $paidCents=simp_money_cents($paid);
                if($lockedAmountCents===NULL||$targetCents===NULL||$paidCents===NULL||$lockedAmountCents>$targetCents-$paidCents) throw new InvalidArgumentException('Pembayaran tidak dapat diverifikasi karena melebihi sisa tagihan.');
                if(!$this->db->where('id',$paymentId)->update('payments',array('status'=>'verified','verified_by'=>$verifierId,'verified_at'=>$now,'rejected_by'=>NULL,'rejected_at'=>NULL,'updated_at'=>$now))) throw new RuntimeException('Status pembayaran gagal diperbarui.');
                if(!$this->db->where(array('source_type'=>'payment','source_id'=>$paymentId))->count_all_results('ledger_entries')){
                    if(!$this->db->insert('ledger_entries',array('account_id'=>$locked['account_id'],'entry_date'=>$locked['payment_date'],'direction'=>'in','amount'=>$locked['amount'],'source_type'=>'payment','source_id'=>$paymentId,'description'=>'Penerimaan '.$locked['receipt_no'],'created_by'=>$verifierId,'created_at'=>$now))) throw new RuntimeException('Buku besar pembayaran gagal diperbarui.');
                }
                $newStatus='verified';
            }else{
                if($locked['status']==='rejected') throw new InvalidArgumentException('Pembayaran sudah ditolak.');
                if($locked['status']==='verified'){
                    // Every ledger mutation in the application locks the fund
                    // account first.  Holding the same lock here makes the
                    // balance check and the reversal one atomic operation.
                    $account=$this->db->query(
                        'SELECT id,opening_balance FROM fund_accounts WHERE id=? FOR UPDATE',
                        array((int)$locked['account_id'])
                    )->row_array();
                    if(!$account) throw new RuntimeException('Akun penerima pembayaran tidak tersedia.');

                    // The current balance still includes this payment's credit.
                    // Rejecting it must never leave the receiving account below
                    // zero after that credit is removed.
                    $movement=$this->db->select("COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0) movement",FALSE)
                        ->where('account_id',(int)$account['id'])->get('ledger_entries')->row_array();
                    $openingCents=$this->signed_money_cents($account['opening_balance']);
                    $movementCents=$this->signed_money_cents(isset($movement['movement'])?$movement['movement']:'0');
                    $paymentCents=simp_money_cents($locked['amount']);
                    if($openingCents===NULL||$movementCents===NULL||$paymentCents===NULL){
                        throw new RuntimeException('Saldo akun atau nominal pembayaran tidak valid.');
                    }
                    $currentBalanceCents=$openingCents+$movementCents;
                    if($currentBalanceCents<$paymentCents){
                        throw new InvalidArgumentException('Pembayaran tidak dapat dibatalkan karena dananya sudah terpakai dan saldo akun penerima tidak mencukupi.');
                    }
                }
                if(!$this->db->where('id',$paymentId)->update('payments',array('status'=>'rejected','rejected_by'=>$verifierId,'rejected_at'=>$now,'updated_at'=>$now))) throw new RuntimeException('Status pembayaran gagal diperbarui.');
                if(!$this->db->where(array('source_type'=>'payment','source_id'=>$paymentId))->delete('ledger_entries')) throw new RuntimeException('Koreksi buku besar pembayaran gagal diperbarui.');
                $newStatus='rejected';
            }
            $this->record_payment_status_history(
                $paymentId,
                (string) $locked['status'],
                $newStatus,
                (int) $verifierId,
                $now,
                $newStatus === 'verified' ? 'Pembayaran diverifikasi.' : 'Pembayaran ditolak.'
            );
            if($this->db->trans_status()===FALSE){$error=$this->db->error();throw new RuntimeException(!empty($error['message'])?$error['message']:'Status pembayaran gagal diperbarui.');}
            if(!$this->db->trans_commit()) throw new RuntimeException('Transaksi status pembayaran gagal diselesaikan.');
            $this->db->db_debug=$originalDbDebug;
            return array('payment_id'=>$paymentId,'registration_id'=>(int)$locked['registration_id'],'old_status'=>$locked['status'],'new_status'=>$newStatus,'receipt_no'=>$locked['receipt_no']);
        } catch(Throwable $e) {
            $this->db->trans_rollback(); $this->db->db_debug=$originalDbDebug; throw $e;
        }
    }

    /** Lock event before registration so lifecycle mutations share one order. */
    private function lock_mutation_context($registrationId)
    {
        $registrationId = (int) $registrationId;
        $pointer = $this->db->select('event_id')->where('id', $registrationId)->get('registrations')->row_array();
        if (!$pointer) throw new InvalidArgumentException('Registrasi tidak ditemukan.');
        $event = $this->db->query('SELECT * FROM training_events WHERE id=? FOR UPDATE', array((int) $pointer['event_id']))->row_array();
        $registration = $this->db->query('SELECT * FROM registrations WHERE id=? FOR UPDATE', array($registrationId))->row_array();
        if (!$event || !$registration || (int) $registration['event_id'] !== (int) $event['id']) throw new InvalidArgumentException('Registrasi tidak ditemukan.');
        return array('event' => $event, 'registration' => $registration);
    }

    /** Amounts in pending/verified state are commitments; rejected rows are not. */
    private function committed_registration_amount($registrationId)
    {
        $row = $this->db->select_sum('amount')->where('registration_id', (int) $registrationId)->where_in('status', array('pending', 'verified'))->get('payments')->row_array();
        $raw = !array_key_exists('amount', $row) || $row['amount'] === NULL ? '0' : $row['amount'];
        $amount = simp_money_decimal($raw, TRUE);
        if ($amount === NULL) throw new RuntimeException('Nominal pembayaran registrasi tidak valid.');
        return $amount;
    }

    private function committed_participant_amount($participantId)
    {
        $row = $this->db->select_sum('amount')->where('participant_id', (int) $participantId)->where_in('status', array('pending', 'verified'))->get('payments')->row_array();
        $raw = !array_key_exists('amount', $row) || $row['amount'] === NULL ? '0' : $row['amount'];
        $amount = simp_money_decimal($raw, TRUE);
        if ($amount === NULL) throw new RuntimeException('Nominal pembayaran peserta tidak valid.');
        return $amount;
    }

    /** Store immutable participant state inside the same transaction as its mutation. */
    private function record_participant_revision($participantId, $registrationId, $action, array $before, array $after = NULL, $reason = NULL, $actorId = NULL, $occurredAt = NULL)
    {
        $allowedActions = array('update', 'replace', 'deactivate', 'registration_cancel', 'registration_restore');
        if (!in_array((string) $action, $allowedActions, TRUE)) throw new InvalidArgumentException('Jenis riwayat peserta tidak valid.');
        $beforeJson = $this->participant_snapshot_json($before);
        $afterJson = $after === NULL ? NULL : $this->participant_snapshot_json($after);
        $actorId = (int) $actorId > 0 ? (int) $actorId : NULL;
        if (!$this->db->insert('participant_revisions', array(
            'participant_id' => (int) $participantId,
            'registration_id' => (int) $registrationId,
            'action' => (string) $action,
            'before_json' => $beforeJson,
            'after_json' => $afterJson,
            'reason' => $reason === NULL || trim((string) $reason) === '' ? NULL : trim((string) $reason),
            'actor_id' => $actorId,
            'actor_name' => $this->actor_name($actorId),
            'occurred_at' => $this->valid_datetime($occurredAt) ? (string) $occurredAt : date('Y-m-d H:i:s')
        ))) throw new RuntimeException('Riwayat perubahan peserta gagal disimpan.');
    }

    /** Store every payment transition without overwriting the previous reviewer. */
    private function record_payment_status_history($paymentId, $fromStatus, $toStatus, $actorId = NULL, $occurredAt = NULL, $note = NULL)
    {
        $allowedStatuses = array('pending', 'verified', 'rejected');
        if (($fromStatus !== NULL && !in_array((string) $fromStatus, $allowedStatuses, TRUE)) || !in_array((string) $toStatus, $allowedStatuses, TRUE)) {
            throw new InvalidArgumentException('Riwayat status pembayaran tidak valid.');
        }
        $actorId = (int) $actorId > 0 ? (int) $actorId : NULL;
        if (!$this->db->insert('payment_status_history', array(
            'payment_id' => (int) $paymentId,
            'from_status' => $fromStatus === NULL ? NULL : (string) $fromStatus,
            'to_status' => (string) $toStatus,
            'actor_id' => $actorId,
            'actor_name' => $this->actor_name($actorId),
            'occurred_at' => $this->valid_datetime($occurredAt) ? (string) $occurredAt : date('Y-m-d H:i:s'),
            'note' => $note === NULL || trim((string) $note) === '' ? NULL : trim((string) $note)
        ))) throw new RuntimeException('Riwayat status pembayaran gagal disimpan.');
    }

    private function participant_snapshot_json(array $row)
    {
        $fields = array('id','registration_id','full_name','position_id','position','phone','expected_amount','is_active','deactivation_reason','deactivated_by','replacement_for_id','created_by','updated_by','created_at','updated_at','deleted_at');
        $snapshot = array();
        foreach ($fields as $field) $snapshot[$field] = array_key_exists($field, $row) ? $row[$field] : NULL;
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === FALSE) throw new RuntimeException('Snapshot perubahan peserta gagal dibuat.');
        return $json;
    }

    private function actor_name($actorId)
    {
        if ($actorId === NULL) return NULL;
        $row = $this->db->select('name')->where('id', (int) $actorId)->get('users')->row_array();
        return $row && isset($row['name']) ? (string) $row['name'] : NULL;
    }

    /**
     * Recompute the registration snapshot after a participant is removed.
     * Existing participant rows retain their position in the fee schedule;
     * the removed row is excluded by its inactive flag.
     */
    private function rebalance_registration_amount_locked(array $event, $registrationId, $newExpected, $removedParticipantId = NULL)
    {
        $active = $this->db->select('id')->where(array('registration_id' => (int) $registrationId, 'is_active' => 1))->where('deleted_at IS NULL', NULL, FALSE)->order_by('id', 'ASC')->get('participants')->result_array();
        $index = 0;
        foreach ($active as $row) {
            $amount = $this->participant_expected_amount($event, $index++);
            if (!$this->db->where('id', (int) $row['id'])->update('participants', array('expected_amount' => $amount, 'updated_at' => date('Y-m-d H:i:s')))) {
                throw new RuntimeException('Snapshot tagihan peserta gagal diperbarui.');
            }
        }
        if (!$this->db->where('id', (int) $registrationId)->update('registrations', array('expected_amount' => $newExpected, 'updated_at' => date('Y-m-d H:i:s')))) {
            throw new RuntimeException('Snapshot tagihan registrasi gagal diperbarui.');
        }
    }

    private function assert_transaction_ok($fallback)
    {
        if ($this->db->trans_status() === FALSE) {
            $error = $this->db->error();
            throw new RuntimeException(!empty($error['message']) ? $error['message'] : $fallback);
        }
    }

    private function commit_transaction_or_throw($fallback)
    {
        if (!$this->db->trans_commit()) throw new RuntimeException($fallback);
    }

    private function normalize_ids($ids)
    {
        $out=array();
        foreach((array)$ids as $id){
            if(!is_scalar($id))continue;
            $id=trim((string)$id);
            if($id!==''&&preg_match('/^[A-Za-z0-9_.-]+$/D',$id))$out[$id]=$id;
        }
        return array_values($out);
    }

    private function registration_exists_for_village($eventId, array $village)
    {
        $villageId = isset($village['village_id']) ? trim((string) $village['village_id']) : '';
        if ($villageId !== '' && $this->db->where('event_id', (int) $eventId)
            ->where('village_id', $villageId)
            ->count_all_results('registrations')) {
            return TRUE;
        }

        // Beberapa katalog RAB lama memakai nomor urut kabupaten, bukan kode
        // resmi (mis. Kutai Timur 64_8 vs 64.04). Snapshot nama menjadi
        // identitas cadangan agar desa yang sama tidak dapat didaftarkan ulang.
        foreach (array('province_id', 'regency_name', 'district_name', 'village_name') as $field) {
            if (!isset($village[$field]) || trim((string) $village[$field]) === '') return FALSE;
        }
        return $this->db->where('event_id', (int) $eventId)
            ->where('province_id', trim((string) $village['province_id']))
            ->where('regency_name', trim((string) $village['regency_name']))
            ->where('district_name', trim((string) $village['district_name']))
            ->where('village_name', trim((string) $village['village_name']))
            ->count_all_results('registrations') > 0;
    }

    private function active_position_map($forUpdate = FALSE)
    {
        $rows = $forUpdate
            ? $this->db->query('SELECT id,name FROM village_positions WHERE is_active=1 FOR UPDATE')->result_array()
            : $this->db->select('id,name')->where('is_active', 1)->get('village_positions')->result_array();
        $map = array();
        foreach ($rows as $row) $map[(int) $row['id']] = $row['name'];
        return $map;
    }

    private function normalize_participants($rows, array $positionMap)
    {
        $out = array();
        $seenNames = array();
        foreach ((array) $rows as $key => $row) {
            if(!is_array($row))throw new InvalidArgumentException('Data peserta tidak valid.');
            $key=(string)$key;
            if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/',$key))throw new InvalidArgumentException('Identitas baris peserta tidak valid.');
            foreach(array('full_name','position_id','phone') as $field){
                if(isset($row[$field])&&!is_scalar($row[$field]))throw new InvalidArgumentException('Data peserta tidak valid.');
            }
            $name = $this->normalize_participant_name(isset($row['full_name']) ? $row['full_name'] : '');
            $rawPosition=isset($row['position_id'])?trim((string)$row['position_id']):'';
            $rawPhone=isset($row['phone'])?trim((string)$row['phone']):'';
            if ($name === '') {
                if($rawPosition!==''||$rawPhone!=='')throw new InvalidArgumentException('Nama peserta wajib diisi.');
                continue;
            }
            if (strlen($name) > 160) throw new InvalidArgumentException('Nama peserta terlalu panjang.');
            $nameKey = $this->participant_name_key($name);
            if (isset($seenNames[$nameKey])) {
                throw new InvalidArgumentException('Nama peserta "'.$name.'" diinput lebih dari satu kali.');
            }
            $seenNames[$nameKey] = TRUE;

            if($rawPosition===''||!ctype_digit($rawPosition)||(int)$rawPosition<1)throw new InvalidArgumentException('Pilih jabatan untuk setiap peserta.');
            $positionId = (int)$rawPosition;
            if (!isset($positionMap[$positionId])) throw new InvalidArgumentException('Jabatan peserta tidak valid atau sudah dinonaktifkan.');

            $phone = trim((string) (isset($row['phone']) ? $row['phone'] : ''));
            if (strlen($phone) > 30 || ($phone !== '' && !preg_match('/^[0-9+(). -]+$/', $phone))) {
                throw new InvalidArgumentException('Nomor HP peserta tidak valid.');
            }

            $out[$key] = array(
                'full_name' => $name,
                'position_id' => $positionId,
                'position' => $positionMap[$positionId],
                'phone' => $phone ?: NULL
            );
        }
        return $out;
    }

    /** Normalize display names before storage and duplicate comparison. */
    private function normalize_participant_name($value)
    {
        $name = (string)$value;
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($name, Normalizer::FORM_KC);
            if ($normalized === FALSE) throw new InvalidArgumentException('Nama peserta tidak valid.');
            $name = $normalized;
        }
        $name = preg_replace('/[\p{Z}\s]+/u', ' ', $name);
        if ($name === NULL) throw new InvalidArgumentException('Nama peserta tidak valid.');
        return trim($name);
    }

    /** Case-insensitive key shared by submitted and persisted participant names. */
    private function participant_name_key($value)
    {
        return mb_strtolower($this->normalize_participant_name($value), 'UTF-8');
    }

    /**
     * Prevent two active participants with the same normalized name inside one
     * registration. Registrations are unique per event/village, so this keeps
     * the rule local to that event and village while allowing archived names
     * and matching names in other registrations.
     */
    private function assert_unique_active_participant_names($registrationId, array $participants, array $excludedParticipantIds = array())
    {
        $registrationId = (int)$registrationId;
        if ($registrationId < 1) throw new InvalidArgumentException('Registrasi peserta tidak valid.');

        $submittedNames = array();
        foreach ($participants as $participant) {
            if (!is_array($participant) || !isset($participant['full_name'])) {
                throw new InvalidArgumentException('Data peserta tidak valid.');
            }
            $name = $this->normalize_participant_name($participant['full_name']);
            if ($name !== '') $submittedNames[$this->participant_name_key($name)] = $name;
        }
        if (!$submittedNames) return;

        $excludedIds = array();
        foreach ($excludedParticipantIds as $participantId) {
            $participantId = (int)$participantId;
            if ($participantId > 0) $excludedIds[$participantId] = $participantId;
        }

        $this->db->select('id,full_name')
            ->where('registration_id', $registrationId)
            ->where('is_active', 1)
            ->where('deleted_at IS NULL', NULL, FALSE);
        if ($excludedIds) $this->db->where_not_in('id', array_values($excludedIds));
        $activeParticipants = $this->db->get('participants')->result_array();

        foreach ($activeParticipants as $activeParticipant) {
            $nameKey = $this->participant_name_key($activeParticipant['full_name']);
            if (isset($submittedNames[$nameKey])) {
                throw new InvalidArgumentException(
                    'Nama peserta "'.$submittedNames[$nameKey].'" sudah terdaftar aktif pada desa ini untuk event tersebut.'
                );
            }
        }
    }

    private function expected_amount(array $event, $participantCount)
    {
        $participantCount=(int)$participantCount;
        $villageCents=simp_money_cents($event['village_fee']);
        $participantCents=simp_money_cents($event['participant_fee']);
        if($villageCents===NULL||$participantCents===NULL)throw new InvalidArgumentException('Tarif event tidak valid.');
        if($event['billing_mode']==='per_village') return simp_money_from_cents($villageCents);
        if($event['billing_mode']==='per_participant'){
            if($participantCount>0&&$participantCents>intdiv(PHP_INT_MAX,$participantCount))throw new InvalidArgumentException('Total tagihan event terlalu besar.');
            return simp_money_from_cents($participantCount*$participantCents);
        }
        if($event['billing_mode']==='per_village_extra'){
            $extra=max(0,$participantCount-(int)$event['included_participant_count']);
            if($extra>0&&$participantCents>intdiv(PHP_INT_MAX,$extra))throw new InvalidArgumentException('Total tagihan event terlalu besar.');
            $extraCents=$extra*$participantCents;
            if($villageCents>PHP_INT_MAX-$extraCents)throw new InvalidArgumentException('Total tagihan event terlalu besar.');
            return simp_money_from_cents($villageCents+$extraCents);
        }
        throw new InvalidArgumentException('Mode pembayaran event tidak valid.');
    }

    private function participant_expected_amount(array $event, $participantIndex)
    {
        if($event['billing_mode']==='per_participant') return simp_money_decimal($event['participant_fee'],TRUE);
        if($event['billing_mode']==='per_village_extra'&&(int)$participantIndex>=(int)$event['included_participant_count']) return simp_money_decimal($event['participant_fee'],TRUE);
        return '0.00';
    }

    private function valid_upload_path($path,$folder)
    {
        if(!is_scalar($path))return FALSE;
        $path=ltrim(str_replace('\\','/',trim((string)$path)),'/');
        $folder=trim(str_replace('\\','/',(string)$folder),'/');
        $prefix='uploads/'.$folder.'/';
        if($folder===''||$path===''||strpos($path,"\0")!==FALSE||strpos($path,'..')!==FALSE||strpos($path,$prefix)!==0)return FALSE;
        $root=realpath(FCPATH.'uploads/'.$folder);$candidate=realpath(FCPATH.$path);
        return $root!==FALSE&&$candidate!==FALSE&&is_file($candidate)&&strpos($candidate,$root.DIRECTORY_SEPARATOR)===0;
    }

    private function valid_date($value)
    {
        if(!is_scalar($value))return FALSE;
        $value=(string)$value;$date=DateTime::createFromFormat('!Y-m-d',$value);
        $errors=DateTime::getLastErrors();
        return $date instanceof DateTime&&($errors===FALSE||(empty($errors['warning_count'])&&empty($errors['error_count'])))&&$date->format('Y-m-d')===$value;
    }

    private function valid_datetime($value)
    {
        if (!is_scalar($value)) return FALSE;
        $value = (string) $value;
        $date = DateTime::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = DateTime::getLastErrors();
        return $date instanceof DateTime && ($errors === FALSE || (empty($errors['warning_count']) && empty($errors['error_count']))) && $date->format('Y-m-d H:i:s') === $value;
    }

    private function signed_money_cents($value)
    {
        if (!is_scalar($value)) return NULL;
        $value = trim((string)$value);
        $negative = isset($value[0]) && $value[0] === '-';
        if ($negative) $value = substr($value, 1);
        $cents = simp_money_cents($value);
        if ($cents === NULL) return NULL;
        return $negative ? -$cents : $cents;
    }

    /**
     * Allocate one immutable MOU number for a registration.
     *
     * The counter is keyed by the official kabupaten code and the year of the
     * event start date.  It is deliberately separate from the registration
     * table because an event row lock only serializes registrations for one
     * event; two events can otherwise allocate the same number concurrently.
     */
    private function allocate_registration_mou($regencyCode, $eventDate)
    {
        $code = $this->normalize_mou_code($regencyCode);
        if (!$this->valid_date($eventDate)) {
            throw new InvalidArgumentException('Tanggal mulai event tidak valid untuk nomor MOU.');
        }
        $date = DateTime::createFromFormat('!Y-m-d', (string) $eventDate);
        if (!$date) throw new InvalidArgumentException('Tanggal mulai event tidak valid untuk nomor MOU.');
        $year = (int) $date->format('Y');

        // INSERT first so SELECT ... FOR UPDATE always has a row to lock.
        // The no-op duplicate clause is intentional: it does not increment
        // the counter until the locked value has been read and advanced.
        $inserted = $this->db->query(
            'INSERT INTO registration_mou_counters (regency_code,mou_year,last_sequence) VALUES (?,?,0) ON DUPLICATE KEY UPDATE last_sequence=last_sequence',
            array($code, $year)
        );
        if (!$inserted) {
            throw new RuntimeException('Penomoran MOU belum siap. Jalankan patch_registration_mou_numbers.sql pada database MVIN.');
        }

        $counter = $this->db->query(
            'SELECT last_sequence FROM registration_mou_counters WHERE regency_code=? AND mou_year=? FOR UPDATE',
            array($code, $year)
        )->row_array();
        if (!$counter) throw new RuntimeException('Counter nomor MOU tidak dapat dikunci.');

        $sequence = (int) $counter['last_sequence'] + 1;
        if ($sequence < 1 || $sequence > 4294967295) {
            throw new RuntimeException('Urutan nomor MOU sudah mencapai batas maksimum.');
        }
        if (!$this->db->where(array('regency_code' => $code, 'mou_year' => $year))
            ->update('registration_mou_counters', array('last_sequence' => $sequence))) {
            throw new RuntimeException('Counter nomor MOU gagal diperbarui.');
        }

        return array(
            'mou_no' => sprintf('%03d.RAB/%s/SPK/%s/%04d', $sequence, $code, $this->roman_month((int) $date->format('n')), $year),
            'sequence' => $sequence,
            'regency_code' => $code,
            'year' => $year
        );
    }

    /** Keep the code stored in the MOU safe, stable, and within schema size. */
    private function mou_code_from_row(array $row)
    {
        foreach (array('regency_code', 'mou_regency_code', 'regency_id', 'regency_name') as $field) {
            if (isset($row[$field]) && trim((string) $row[$field]) !== '') {
                return $this->normalize_mou_code($row[$field]);
            }
        }
        throw new InvalidArgumentException('Kode kabupaten tidak tersedia untuk nomor MOU. Periksa master wilayah dan snapshot registrasi.');
    }

    private function normalize_mou_code($value)
    {
        $code = trim((string) $value);
        $code = preg_replace('/[^A-Z0-9._-]+/u', '-', strtoupper($code));
        $code = trim((string) $code, '-');
        if ($code === '') $code = 'KAB';
        return substr($code, 0, 30);
    }

    private function roman_month($month)
    {
        $months = array(1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII');
        return isset($months[(int) $month]) ? $months[(int) $month] : 'I';
    }

    private function receipt_no()
    {
        for($i=0;$i<8;$i++){
            $number='PAY-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
            if(!$this->db->where('receipt_no',$number)->count_all_results('payments')) return $number;
        }
        throw new RuntimeException('Nomor kuitansi pembayaran gagal dibuat.');
    }
}
