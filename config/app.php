<?php

declare(strict_types=1);

define('APP_NAME', 'TRAC JHS SARMS');
define('APP_SCHOOL', 'Tawi-Tawi Regional Agricultural College Junior High School');
define('APP_TIMEZONE', 'Asia/Manila');
define('SESSION_TIMEOUT', 1800);

date_default_timezone_set(APP_TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
