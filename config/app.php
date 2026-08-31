<?php

declare(strict_types=1);

define('APP_NAME', 'TRAC JHS SARMS');
define('APP_SCHOOL', 'Tawi-Tawi Regional Agricultural College Junior High School');
define('APP_LOCATION', 'Bongao, Tawi-Tawi');
define('APP_REGION', 'BARMM');
define('APP_TIMEZONE', 'Asia/Manila');
define('SESSION_TIMEOUT', 1800);
define('APP_ROOT', dirname(__DIR__));

/**
 * Detect URL base path for XAMPP subdirectory installs
 * (e.g. /trac-jhs-sarms when document root is htdocs).
 */
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$appRootReal = realpath(APP_ROOT) ?: APP_ROOT;
$basePath = '';

if ($documentRoot !== '' && str_starts_with($appRootReal, $documentRoot)) {
    $basePath = str_replace('\\', '/', substr($appRootReal, strlen($documentRoot)));
}

define('APP_BASE_PATH', rtrim($basePath, '/'));

date_default_timezone_set(APP_TIMEZONE);

/**
 * Emit baseline security headers from PHP for every page. Apache's mod_headers
 * is not always available (Render's stock php image doesn't enable it), so we
 * set these in PHP before any output is sent. The .htaccess also sets them via
 * "Header always set" for environments that DO have mod_headers — duplicates are
 * harmless; the latest value wins.
 */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header_remove('X-Powered-By');
}

/**
 * Ensure a writable directory exists at $path. Handles three cases:
 *   1. Path is a real directory — do nothing.
 *   2. Path is a symlink (Render persistent-disk pattern) — mkdir the target if missing.
 *   3. Path is missing — mkdir recursively.
 * Suppresses the "File exists" warning when the path is a dangling symlink.
 *
 * Defined here (not in functions.php) because config/app.php is the first file
 * loaded by every page, including before session storage needs to be set up.
 */
function ensure_dir(string $path, int $mode = 0750): void
{
    if (is_dir($path)) {
        return;
    }
    if (is_link($path)) {
        $target = readlink($path);
        if ($target !== false && !is_dir($target)) {
            @mkdir($target, $mode, true);
        }
        return;
    }
    @mkdir($path, $mode, true);
}

require_once __DIR__ . '/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    // Keep session storage inside the project dir (no leaks to /tmp).
    // On Render this is a symlink into the persistent disk; on local dev it's
    // a real directory. ensure_dir() handles both (including the dangling-symlink
    // case that the persistent disk hits on first deploy).
    ensure_dir(APP_ROOT . '/.sessions');

    session_save_path(APP_ROOT . '/.sessions');

    session_name('TRAC_JHS_SARMS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_BASE_PATH === '' ? '/' : APP_BASE_PATH . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // Treat the cookie as Secure when the request was HTTPS, either by direct
        // TLS termination (HTTPS server var) or by a trusted proxy that forwards the
        // original scheme via X-Forwarded-Proto (Render behind Cloudflare).
        'secure' => (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ),
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';
require_once APP_ROOT . '/includes/functions.php';
