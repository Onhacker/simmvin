<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = TRUE;

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

$db['default'] = simp_db_config('', 'simp');
$db['wilayah'] = simp_db_config('REGIONAL_', 'rab_new');

