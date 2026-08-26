<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('e')) {
    function e($value) { return html_escape((string) $value); }
}

if (!function_exists('rupiah')) {
    function rupiah($value, $withPrefix = TRUE)
    {
        // Format DECIMAL values as strings so a large valid amount (up to
        // DECIMAL(18,2)) is not rounded through a binary float merely for
        // display.  The application intentionally shows whole rupiah.
        $raw = is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $raw, $match)) {
            $negative = $match[1] === '-';
            $integer = ltrim($match[2], '0');
            if ($integer === '') $integer = '0';
            $fraction = isset($match[3]) ? $match[3] : '';
            if ($fraction !== '' && (int) $fraction[0] >= 5) {
                $carry = 1;
                for ($i = strlen($integer) - 1; $i >= 0 && $carry; $i--) {
                    $digit = ord($integer[$i]) - 48 + $carry;
                    if ($digit >= 10) { $integer[$i] = '0'; }
                    else { $integer[$i] = chr(48 + $digit); $carry = 0; }
                }
                if ($carry) $integer = '1' . $integer;
            }
            $groups = array();
            while (strlen($integer) > 3) {
                array_unshift($groups, substr($integer, -3));
                $integer = substr($integer, 0, -3);
            }
            array_unshift($groups, $integer);
            $formatted = implode('.', $groups);
            if ($negative && $formatted !== '0') $formatted = '-' . $formatted;
            return ($withPrefix ? 'Rp ' : '') . $formatted;
        }
        // Invalid/non-canonical values must never be coerced through a float;
        // doing so can silently turn malformed data into a plausible amount.
        return ($withPrefix ? 'Rp ' : '') . '0';
    }
}

/**
 * Normalize user supplied monetary values for DECIMAL(18,2) columns.
 *
 * Monetary input must never pass through a binary float before it reaches the
 * database.  This helper deliberately accepts only plain decimal notation
 * (no signs, exponent notation, thousands separators, or more than two
 * fractional digits) and returns a canonical string with exactly two decimal
 * places.  NULL means invalid input.
 */
if (!function_exists('simp_money_decimal')) {
    function simp_money_decimal($value, $allowZero = TRUE)
    {
        if (!is_scalar($value)) return NULL;
        $value = trim((string) $value);
        if (!preg_match('/^\d+(?:\.(\d{1,2}))?$/D', $value, $matches)) return NULL;

        $parts = explode('.', $value, 2);
        $integer = ltrim($parts[0], '0');
        if ($integer === '') $integer = '0';
        // DECIMAL(18,2) allows at most sixteen digits before the decimal.
        if (strlen($integer) > 16) return NULL;
        $fraction = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';
        if (!$allowZero && $integer === '0' && $fraction === '00') return NULL;
        return $integer . '.' . $fraction;
    }
}

if (!function_exists('simp_money_cents')) {
    function simp_money_cents($value)
    {
        $decimal = simp_money_decimal($value, TRUE);
        if ($decimal === NULL) return NULL;
        $parts = explode('.', $decimal, 2);
        // Keep this as a string calculation until the final integer cast; the
        // schema's 16-digit major part is within the 64-bit PHP range here.
        return ((int) $parts[0] * 100) + (int) $parts[1];
    }
}

if (!function_exists('simp_money_from_cents')) {
    function simp_money_from_cents($cents)
    {
        $cents = (int) $cents;
        if ($cents < 0) $cents = 0;
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('simp_money_from_signed_cents')) {
    /**
     * Convert a signed integer-cent value to a canonical DECIMAL string.
     * This is kept separate from simp_money_from_cents(), whose historical
     * contract clamps negative values for tagihan/sisa calculations.
     */
    function simp_money_from_signed_cents($cents)
    {
        $cents = (int) $cents;
        $negative = $cents < 0;
        if ($negative) $cents = abs($cents);
        $value = intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
        return $negative && $value !== '0.00' ? '-' . $value : $value;
    }
}

if (!function_exists('tanggal_id')) {
    function tanggal_id($date, $withTime = FALSE)
    {
        if (!$date) return '-';
        $months = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
        $timestamp = strtotime($date);
        if (!$timestamp) return '-';
        $result = date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
        return $withTime ? $result . ' ' . date('H:i', $timestamp) : $result;
    }
}

if (!function_exists('payment_status')) {
    function payment_status($paid, $expected)
    {
        $paidCents = simp_money_cents($paid);
        $expectedCents = simp_money_cents($expected);
        if ($paidCents === NULL || $expectedCents === NULL) return 'belum_bayar';
        if ($paidCents <= 0) return 'belum_bayar';
        if ($expectedCents > 0 && $paidCents > $expectedCents) return 'lebih_bayar';
        if ($expectedCents > 0 && $paidCents >= $expectedCents) return 'lunas';
        return 'sebagian';
    }
}

if (!function_exists('status_badge')) {
    function status_badge($status)
    {
        $map = array(
            'lunas' => array('bg-green-dark', 'Lunas'),
            'lebih_bayar' => array('bg-blue-dark', 'Lebih Bayar'),
            'sebagian' => array('bg-yellow-dark', 'Bayar Sebagian'),
            'belum_bayar' => array('bg-red-dark', 'Belum Bayar'),
            'open' => array('bg-green-dark', 'Aktif'),
            'draft' => array('bg-gray-dark', 'Draft'),
            'closed' => array('bg-dark-dark', 'Ditutup'),
            'cancelled' => array('bg-red-dark', 'Dibatalkan'),
            'active' => array('bg-green-dark', 'Aktif'),
            'inactive' => array('bg-gray-dark', 'Nonaktif'),
            'verified' => array('bg-green-dark', 'Terverifikasi'),
            'pending' => array('bg-yellow-dark', 'Menunggu'),
            'rejected' => array('bg-red-dark', 'Ditolak')
        );
        $item = isset($map[$status]) ? $map[$status] : array('bg-gray-dark', ucwords(str_replace('_', ' ', $status)));
        return '<span class="badge ' . $item[0] . ' color-white">' . e($item[1]) . '</span>';
    }
}

if (!function_exists('old')) {
    function old($key, $default = '')
    {
        $CI =& get_instance();
        $value = $CI->input->post($key);
        return $value !== NULL ? $value : $default;
    }
}

if (!function_exists('nav_active')) {
    function nav_active($controller)
    {
        $CI =& get_instance();
        return strtolower($CI->router->fetch_class()) === strtolower($controller) ? 'active' : '';
    }
}

if (!function_exists('nav_is')) {
    function nav_is($controllers)
    {
        $CI =& get_instance();
        $controllers = is_array($controllers) ? $controllers : array($controllers);
        return in_array(strtolower($CI->router->fetch_class()), array_map('strtolower', $controllers), TRUE);
    }
}

if (!function_exists('simp_region_id_key')) {
    /**
     * Return a comparison key for Indonesian region identifiers.
     *
     * Older RAB datasets use underscores without zero padding (64_1), while
     * the current central dataset uses official dotted codes (64.01).  The
     * original value remains the stored/displayed ID; this key is only used
     * when values from the two catalog versions must be compared safely.
     */
    function simp_region_id_key($value)
    {
        if (!is_scalar($value)) return '';
        $value = trim((string) $value);
        if ($value === '') return '';
        $segments = preg_split('/[._]/', $value);
        if (!$segments || count($segments) < 2) return $value;
        foreach ($segments as $index => &$segment) {
            if ($segment === '' || !ctype_digit($segment)) return $value;
            $segment = (string) (int) $segment;
            if ($index > 0 && $index < 3) $segment = str_pad($segment, 2, '0', STR_PAD_LEFT);
            elseif ($index === 3) $segment = str_pad($segment, 4, '0', STR_PAD_LEFT);
        }
        unset($segment);
        return implode('.', $segments);
    }
}

if (!function_exists('simp_region_id_variants')) {
    /** Return likely legacy/current spellings of one region identifier. */
    function simp_region_id_variants($value)
    {
        if (!is_scalar($value)) return array();
        $value = trim((string) $value);
        if ($value === '') return array();
        $variants = array($value => TRUE);
        $key = simp_region_id_key($value);
        if ($key !== '') {
            $variants[$key] = TRUE;
            $segments = explode('.', $key);
            foreach ($segments as $index => &$segment) {
                if ($index > 0 && ctype_digit($segment)) $segment = (string) (int) $segment;
            }
            unset($segment);
            $variants[implode('_', $segments)] = TRUE;
        }
        return array_keys($variants);
    }
}

if (!function_exists('simp_region_name_key')) {
    function simp_region_name_key($value)
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }
}

if (!function_exists('simp_regency_identity_key')) {
    /** Build a fail-closed identity from the stable province and snapshot name. */
    function simp_regency_identity_key(array $row)
    {
        $provinceId = isset($row['province_id']) ? trim((string) $row['province_id']) : '';
        $regencyName = simp_region_name_key(isset($row['regency_name']) ? $row['regency_name'] : '');
        if ($provinceId === '' || $regencyName === '') return '';
        return simp_region_id_key($provinceId) . '|' . $regencyName;
    }
}

if (!function_exists('simp_resolve_event_regencies')) {
    /**
     * Reconcile event-region snapshots against the configured regional DB.
     *
     * This keeps migrated events usable when their snapshot came from a RAB
     * catalog with a different ID separator, padding, or historical ordinal.
     * Candidate IDs are accepted only when their names also match; the name
     * guard prevents an old 64_3 (Berau) being mistaken for official 64.03
     * (Kutai Kartanegara).
     */
    function simp_resolve_event_regencies($regionDb, array $rows)
    {
        if (!$rows || !$regionDb) return $rows;

        $candidateIds = array();
        $provinceIds = array();
        foreach ($rows as $row) {
            foreach (simp_region_id_variants(isset($row['regency_id']) ? $row['regency_id'] : '') as $variant) {
                $candidateIds[$variant] = TRUE;
            }
            $provinceId = isset($row['province_id']) ? trim((string) $row['province_id']) : '';
            if ($provinceId !== '') $provinceIds[$provinceId] = TRUE;
        }

        $catalog = array();
        if ($candidateIds) {
            $matches = $regionDb->select('k.id AS regency_id,k.kota AS regency_name,k.kode_kota AS regency_code,p.id AS province_id,p.provinsi AS province_name')
                ->from('data_kota k')->join('data_provinsi p', 'p.id=k.id_provinsi')
                ->where_in('k.id', array_keys($candidateIds))->get()->result_array();
            foreach ($matches as $match) $catalog[(string) $match['regency_id']] = $match;
        }

        $nameCatalog = array();
        if ($provinceIds) {
            $matches = $regionDb->select('k.id AS regency_id,k.kota AS regency_name,k.kode_kota AS regency_code,p.id AS province_id,p.provinsi AS province_name')
                ->from('data_kota k')->join('data_provinsi p', 'p.id=k.id_provinsi')
                ->where_in('p.id', array_keys($provinceIds))->get()->result_array();
            foreach ($matches as $match) {
                $nameCatalog[(string) $match['province_id'] . '|' . simp_region_name_key($match['regency_name'])] = $match;
            }
        }

        foreach ($rows as &$row) {
            $resolved = NULL;
            $sourceProvinceId = isset($row['province_id']) ? trim((string) $row['province_id']) : '';
            $sourceName = simp_region_name_key(isset($row['regency_name']) ? $row['regency_name'] : '');
            foreach (simp_region_id_variants(isset($row['regency_id']) ? $row['regency_id'] : '') as $variant) {
                if (!isset($catalog[$variant])) continue;
                $candidate = $catalog[$variant];
                if ($sourceProvinceId !== '' && (string) $candidate['province_id'] !== $sourceProvinceId) continue;
                if ($sourceName !== '' && simp_region_name_key($candidate['regency_name']) !== $sourceName) continue;
                $resolved = $candidate;
                break;
            }
            if (!$resolved) {
                $nameKey = $sourceProvinceId . '|' . $sourceName;
                if (isset($nameCatalog[$nameKey])) $resolved = $nameCatalog[$nameKey];
            }
            if ($resolved) $row = array_merge($row, $resolved);
        }
        unset($row);
        return $rows;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        $CI =& get_instance();
        return '<input type="hidden" name="' . $CI->security->get_csrf_token_name() . '" value="' . $CI->security->get_csrf_hash() . '">';
    }
}
