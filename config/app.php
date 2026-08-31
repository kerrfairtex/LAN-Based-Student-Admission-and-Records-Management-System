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
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';
require_once APP_ROOT . '/includes/functions.php';
