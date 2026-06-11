<?php
/**
 * Global app config + bootstrap.
 * Every page must require this first.
 */

// Suppress error output in production (log to file instead)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Harden session cookie before session_start()
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// App identity
define('APP_NAME',     'Nourish U Biotech');
define('APP_TAGLINE',  'Med Distribution Management System');
define('APP_VERSION',  '1.4.0');
define('APP_CURRENCY', 'KES');
define('APP_TZ',       'Africa/Nairobi');
date_default_timezone_set(APP_TZ);

// Path / URL helpers
define('ROOT_PATH',  realpath(__DIR__ . '/..'));
define('UPLOAD_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');

// Resolve BASE_URL automatically (works in subfolders too)
$proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir= str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
// strip module sub-path so BASE_URL always points at the project root
$basePath = preg_replace('#/(admin|clients|sales|samples|feedback|inventory|commissions|reports|expenses|api)(/.*)?$#', '', $scriptDir);
define('BASE_URL', rtrim($proto . '://' . $host . $basePath, '/'));

require_once __DIR__ . '/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/softdelete.php';
require_once ROOT_PATH . '/includes/migrate.php';
