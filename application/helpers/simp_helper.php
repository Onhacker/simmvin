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

if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        $CI =& get_instance();
        return '<input type="hidden" name="' . $CI->security->get_csrf_token_name() . '" value="' . $CI->security->get_csrf_hash() . '">';
    }
}
