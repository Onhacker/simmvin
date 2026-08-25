<?php
/**
 * MVIN - Sistem Informasi Manajemen Pelatihan
 * Front controller CodeIgniter 3.1.13.
 */

if (!function_exists('simp_load_env')) {
    function simp_load_env($path)
    {
        if (!is_readable($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

simp_load_env(__DIR__ . '/.env');
// A missing environment file must fail closed.  Local development remains
// unchanged because the checked-out .env explicitly sets development.
define('ENVIRONMENT', strtolower(trim(getenv('APP_ENV') ?: 'production')));
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Makassar');

if (ENVIRONMENT === 'development') {
    error_reporting(-1);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

$system_path = 'system';
$application_folder = 'application';
if (defined('STDIN')) {
    chdir(dirname(__FILE__));
}
if (($resolved = realpath($system_path)) !== false) {
    $system_path = $resolved . DIRECTORY_SEPARATOR;
} else {
    $system_path = rtrim($system_path, '/\\') . DIRECTORY_SEPARATOR;
}
if (!is_dir($system_path)) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    exit('System directory tidak ditemukan.');
}

define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', $system_path);
define('FCPATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('SYSDIR', basename(BASEPATH));
define('APPPATH', realpath($application_folder) . DIRECTORY_SEPARATOR);
define('VIEWPATH', APPPATH . 'views' . DIRECTORY_SEPARATOR);
require_once BASEPATH . 'core/CodeIgniter.php';
