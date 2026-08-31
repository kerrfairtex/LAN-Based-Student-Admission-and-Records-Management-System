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

require_once __DIR__ . '/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    // Keep session storage inside the project dir (no leaks to /tmp).
    $savePath = APP_ROOT . '/.sessions';
    if (!is_dir($savePath)) {
        mkdir($savePath, 0750, true);
    }
    session_save_path($savePath);

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
