<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Position_model extends CI_Model
{
    private $categoryLabels = array(
        'pemerintah_desa' => 'Pemerintah Desa',
        'bpd' => 'BPD',
        'rt_rw' => 'RT / RW',
        'lpmd' => 'LPM / LPMD',
        'pkk' => 'TP-PKK',
        'karang_taruna' => 'Karang Taruna',
        'bumdes' => 'BUM Desa',
        'kemasyarakatan' => 'Kemasyarakatan',
        'kesehatan' => 'Kesehatan Desa',
        'keamanan' => 'Keamanan Desa',
        'pendamping' => 'Pendamping',
        'lainnya' => 'Lainnya'
    );

    public function categories()
    {
        return $this->categoryLabels;
    }

    /**
     * Keep the role label independent from the village context.  Mailing
     * templates append the word "Desa" themselves, so it must not be stored
     * in the master position name (or in new participant snapshots).
     */
    public function normalize_name($value)
    {
        $name = preg_replace('/\bdesa\b/iu', '', (string) $value);
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));
        $name = preg_replace('/\s+([\/,])/u', '$1', (string) $name);
        $name = preg_replace('/([\/,])\s+/u', '$1 ', (string) $name);
        return trim((string) $name, " \t\n\r\0\x0B/");
    }

    public function active()
    {
        $rows = $this->db
            ->select('id,code,name,category,sort_order')
            ->from('village_positions')
            ->where('is_active', 1)
            ->order_by('sort_order', 'ASC')
            ->order_by('name', 'ASC')
            ->get()
            ->result_array();

        $rank = array_flip(array_keys($this->categoryLabels));
        usort($rows, function ($left, $right) use ($rank) {
            $leftRank = isset($rank[$left['category']]) ? $rank[$left['category']] : count($rank);
            $rightRank = isset($rank[$right['category']]) ? $rank[$right['category']] : count($rank);
            if ($leftRank !== $rightRank) return $leftRank < $rightRank ? -1 : 1;
            if ((int) $left['sort_order'] !== (int) $right['sort_order']) {
                return (int) $left['sort_order'] < (int) $right['sort_order'] ? -1 : 1;
            }
            return strcasecmp($left['name'], $right['name']);
        });

        foreach ($rows as &$row) {
            $row['category_label'] = isset($this->categoryLabels[$row['category']])
                ? $this->categoryLabels[$row['category']]
                : $this->categoryLabels['lainnya'];
            unset($row['sort_order']);
        }
        unset($row);
        return $rows;
    }

    public function all(array $filters = array())
    {
        $this->db
            ->select('p.*,u.name AS created_by_name')
            ->from('village_positions p')
            ->join('users u', 'u.id=p.created_by', 'left');

        if (!empty($filters['q'])) {
            $this->db->group_start()
                ->like('p.name', $filters['q'])
                ->or_like('p.code', $filters['q'])
                ->or_like('p.description', $filters['q'])
                ->group_end();
        }
        if (!empty($filters['category']) && isset($this->categoryLabels[$filters['category']])) {
            $this->db->where('p.category', $filters['category']);
        }
        if (isset($filters['status']) && $filters['status'] === 'active') {
            $this->db->where('p.is_active', 1);
        } elseif (isset($filters['status']) && $filters['status'] === 'inactive') {
            $this->db->where('p.is_active', 0);
        }

        $rows = $this->db
            ->order_by('p.sort_order', 'ASC')
            ->order_by('p.name', 'ASC')
            ->get()
            ->result_array();

        $rank = array_flip(array_keys($this->categoryLabels));
        usort($rows, function ($left, $right) use ($rank) {
            $leftRank = isset($rank[$left['category']]) ? $rank[$left['category']] : count($rank);
            $rightRank = isset($rank[$right['category']]) ? $rank[$right['category']] : count($rank);
            if ($leftRank !== $rightRank) return $leftRank < $rightRank ? -1 : 1;
            if ((int) $left['sort_order'] !== (int) $right['sort_order']) {
                return (int) $left['sort_order'] < (int) $right['sort_order'] ? -1 : 1;
            }
            return strcasecmp($left['name'], $right['name']);
        });

        return $rows;
    }

    public function stats()
    {
        $row = $this->db
            ->select('COUNT(*) AS total,COALESCE(SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END),0) AS active_count', FALSE)
            ->get('village_positions')
            ->row_array();

        $total = $row ? (int) $row['total'] : 0;
        $active = $row ? (int) $row['active_count'] : 0;
        return array('total' => $total, 'active' => $active, 'inactive' => max(0, $total - $active));
    }

    public function find($id)
    {
        return $this->db->where('id', (int) $id)->get('village_positions')->row_array();
    }

    public function name_exists($name, $exceptId = 0)
    {
        $this->db->where('name', $this->normalize_name($name));
        if ((int) $exceptId > 0) $this->db->where('id !=', (int) $exceptId);
        return $this->db->count_all_results('village_positions') > 0;
    }

    public function code_exists($code, $exceptId = 0)
    {
        $this->db->where('code', trim((string) $code));
        if ((int) $exceptId > 0) $this->db->where('id !=', (int) $exceptId);
        return $this->db->count_all_results('village_positions') > 0;
    }

    public function slug($value)
    {
        $slug = url_title(trim((string) $value), 'dash', TRUE);
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = trim(preg_replace('/-+/', '-', $slug), '-');
        return substr($slug, 0, 80);
    }

    public function unique_code($name, $exceptId = 0)
    {
        $base = $this->slug($this->normalize_name($name));
        if ($base === '') $base = 'jabatan';
        $base = substr($base, 0, 72);
        $candidate = $base;
        $suffix = 2;
        while ($this->code_exists($candidate, $exceptId)) {
            $ending = '-' . $suffix++;
            $candidate = substr($base, 0, 80 - strlen($ending)) . $ending;
        }
        return $candidate;
    }

    public function save(array $data, $id, $creatorId)
    {
        $data['name'] = $this->normalize_name(isset($data['name']) ? $data['name'] : '');
        if ($data['name'] === '') throw new InvalidArgumentException('Nama jabatan harus diisi tanpa hanya menggunakan kata Desa.');
        $now = date('Y-m-d H:i:s');
        if ((int) $id > 0) {
            $data['updated_at'] = $now;
            if (!$this->db->where('id', (int) $id)->update('village_positions', $data)) {
                throw new RuntimeException('Jabatan gagal diperbarui.');
            }
            return (int) $id;
        }

        $data['created_by'] = (int) $creatorId;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        if (!$this->db->insert('village_positions', $data)) {
            throw new RuntimeException('Jabatan gagal ditambahkan.');
        }
        return (int) $this->db->insert_id();
    }

    public function set_active($id, $isActive)
    {
        return $this->db
            ->where('id', (int) $id)
            ->update('village_positions', array(
                'is_active' => $isActive ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ));
    }
}
