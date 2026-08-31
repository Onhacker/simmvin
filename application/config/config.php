<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$configuredUrl = getenv('APP_URL');
if ($configuredUrl) {
    $config['base_url'] = rtrim($configuredUrl, '/') . '/';
} else {
    // is_https() also understands the HTTPS headers sent by common reverse
    // proxies. Falling back to SERVER/HTTP keeps this config compatible with
    // older CodeIgniter bootstrap variants.
    $scheme = (function_exists('is_https') && is_https())
        ? 'https'
        : ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http');
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '/simp';
    $config['base_url'] = $scheme . '://' . $host . rtrim(str_replace('\\', '/', $script), '/') . '/';
}
$config['index_page'] = '';
$config['uri_protocol'] = 'REQUEST_URI';
$config['url_suffix'] = '';
$config['language'] = 'english';
$config['charset'] = 'UTF-8';
$config['enable_hooks'] = FALSE;
$config['subclass_prefix'] = 'MY_';
$config['composer_autoload'] = FCPATH . 'vendor/autoload.php';
$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-';
$config['enable_query_strings'] = FALSE;
$config['allow_get_array'] = TRUE;
$config['log_threshold'] = ENVIRONMENT === 'development' ? 1 : 0;
$config['log_path'] = '';
$config['log_file_extension'] = '';
$config['log_file_permissions'] = 0644;
$config['log_date_format'] = 'Y-m-d H:i:s';
$config['error_views_path'] = '';
$config['cache_path'] = '';
$config['cache_query_string'] = FALSE;
$appKey = trim((string) (getenv('APP_KEY') ?: ''));
// Never boot a public installation with a predictable session/CSRF key.  The
// checked-in local .env intentionally remains a development configuration;
// production must provide a long, randomly generated APP_KEY through its
// environment (or a private .env file that is not committed).
if (ENVIRONMENT === 'production' &&
    ($appKey === '' || strlen($appKey) < 32 ||
     stripos($appKey, 'change-in-production') !== FALSE ||
     stripos($appKey, 'ganti-dengan') !== FALSE)) {
    header('HTTP/1.1 503 Service Unavailable', TRUE, 503);
    exit('Konfigurasi aplikasi belum lengkap. APP_KEY produksi wajib diisi dengan kunci acak.');
}
$config['encryption_key'] = $appKey !== '' ? $appKey : hash('sha256', FCPATH . '|simp');
$config['sess_driver'] = 'files';
$config['sess_cookie_name'] = 'simp_session';
// Keep authenticated users signed in for one year. CodeIgniter uses this
// value for both the browser cookie and PHP's file-session garbage collection
// lifetime, so the two expiration policies stay in sync.
$config['sess_expiration'] = 31536000;

// File sessions must live in a persistent, writable directory. Deployments
// that use release directories can set SESSION_SAVE_PATH to a shared absolute
// path outside the release; the application directory remains the safe local
// default for the normal public_html/git-pull layout.
$sessionSavePath = trim((string) (getenv('SESSION_SAVE_PATH') ?: ''));
if ($sessionSavePath === '') {
    $sessionSavePath = APPPATH . 'sessions';
}
$config['sess_save_path'] = $sessionSavePath;
$config['sess_match_ip'] = FALSE;
// Automatic ID rotation during normal requests used to delete the old file
// while another page/AJAX request could still be reading it. That race made a
// valid user appear logged out. Keep the ID stable during the session instead;
// login and logout still explicitly regenerate and destroy the ID, while CI3
// refreshes the one-year cookie on every request.
$config['sess_time_to_update'] = 0;
$config['sess_regenerate_destroy'] = TRUE;
$config['sess_samesite'] = 'Lax';
$config['cookie_prefix'] = 'simp_';
$config['cookie_domain'] = '';
$config['cookie_path'] = '/';
$config['cookie_secure'] = function_exists('is_https')
    ? is_https()
    : (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
$config['cookie_samesite'] = 'Lax';
$config['cookie_httponly'] = TRUE;
$config['standardize_newlines'] = FALSE;
$config['global_xss_filtering'] = FALSE;
$config['csrf_protection'] = TRUE;
$config['csrf_token_name'] = 'simp_csrf_token';
$config['csrf_cookie_name'] = 'simp_csrf_cookie';
$config['csrf_expire'] = 7200;
$config['csrf_regenerate'] = FALSE;
$config['csrf_exclude_uris'] = array();
$config['compress_output'] = FALSE;
$config['time_reference'] = 'local';
$config['rewrite_short_tags'] = FALSE;
$config['proxy_ips'] = '';
