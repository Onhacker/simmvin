<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = TRUE;

if (!function_exists('simp_require_db_connection_env')) {
    /**
     * Production must name both databases explicitly. This prevents the
     * location connection from silently falling back to a local database
     * when it is meant to live on another server.
     */
    function simp_require_db_connection_env($prefix, $label)
    {
        if (ENVIRONMENT !== 'production') return;
        $missing = array();
        foreach (array('DB_HOST', 'DB_PORT', 'DB_USER', 'DB_NAME') as $key) {
            $value = getenv($prefix . $key);
            if ($value === FALSE || trim((string) $value) === '') $missing[] = $prefix . $key;
        }
        if ($missing) {
            throw new RuntimeException('Konfigurasi koneksi database '.$label.' belum lengkap: '.implode(', ', $missing));
        }
    }
}

if (!function_exists('simp_db_config')) {
    function simp_db_config($prefix, $defaultName)
    {
        return array(
            'dsn' => '',
            'hostname' => getenv($prefix . 'DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv($prefix . 'DB_PORT') ?: 3306),
            'username' => getenv($prefix . 'DB_USER') ?: 'root',
            'password' => getenv($prefix . 'DB_PASS') !== false ? getenv($prefix . 'DB_PASS') : '',
            'database' => getenv($prefix . 'DB_NAME') ?: $defaultName,
            'dbdriver' => 'mysqli',
            'dbprefix' => '',
            'pconnect' => FALSE,
            'db_debug' => ENVIRONMENT !== 'production',
            'cache_on' => FALSE,
            'cachedir' => '',
            'char_set' => 'utf8mb4',
            'dbcollat' => 'utf8mb4_unicode_ci',
            'swap_pre' => '',
            'encrypt' => FALSE,
            'compress' => FALSE,
            'stricton' => TRUE,
            'failover' => array(),
            'save_queries' => ENVIRONMENT === 'development'
        );
    }
}

if (!function_exists('simp_assert_separate_location_database')) {
    /**
     * Location master data must not silently live in the transaction schema.
     * A hard failure is safer than allowing the application to reintroduce a
     * dependency on the old RAB database through an incomplete .env file.
     */
    function simp_assert_separate_location_database()
    {
        $mainName = strtolower(trim((string) (getenv('DB_NAME') ?: 'simp')));
        $locationName = strtolower(trim((string) (getenv('REGIONAL_DB_NAME') ?: 'simp_wilayah')));
        if ($mainName !== '' && $mainName === $locationName) {
            throw new RuntimeException('DB_NAME dan REGIONAL_DB_NAME harus menunjuk database yang berbeda.');
        }
    }
}

simp_require_db_connection_env('', 'utama');
simp_require_db_connection_env('REGIONAL_', 'wilayah');
simp_assert_separate_location_database();

$db['default'] = simp_db_config('', 'simp');
$db['wilayah'] = simp_db_config('REGIONAL_', 'simp_wilayah');
