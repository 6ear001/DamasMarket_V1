<?php
declare(strict_types=1);

/**
 * Gemeinsamer Start für alle Seiten (öffentlich + Admin).
 * PUBLIC_DIR und APP_DIR werden vorher in public/_boot.php gesetzt.
 */

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');

if (!defined('APP_DIR')) define('APP_DIR', __DIR__);
if (!defined('PUBLIC_DIR')) define('PUBLIC_DIR', dirname(APP_DIR) . '/public');
define('DATA_DIR', getenv('DAMAS_DATA_DIR') ?: APP_DIR . '/data');
define('UPLOAD_DIR', PUBLIC_DIR . '/uploads');

// Basis-URL (falls die Seite in einem Unterordner läuft)
$__script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$__dir = rtrim(dirname($__script), '/');
if (defined('IN_ADMIN')) $__dir = rtrim(dirname($__dir), '/');
define('BASE_URL', $__dir === '.' ? '' : $__dir);
unset($__script, $__dir);

define('IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));

require_once APP_DIR . '/lib/helpers.php';
require_once APP_DIR . '/lib/db.php';
require_once APP_DIR . '/lib/auth.php';
require_once APP_DIR . '/lib/upload.php';
require_once APP_DIR . '/lib/layout.php';
require_once APP_DIR . '/lib/demo.php';

if (PHP_SAPI !== 'cli') {
    define('CSP_NONCE', base64_encode(random_bytes(16)));
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; font-src 'self'; "
        . "style-src 'self' 'nonce-" . CSP_NONCE . "'; style-src-attr 'unsafe-inline'; "
        . "script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    if (IS_HTTPS) header('Strict-Transport-Security: max-age=15552000');
} else {
    define('CSP_NONCE', 'cli');
}
