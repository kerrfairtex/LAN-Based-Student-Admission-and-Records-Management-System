<?php

declare(strict_types=1);

/**
 * Render / load-balancer healthcheck endpoint.
 *
 * Two-tier probe:
 *
 *  - Liveness (HTTP status, used by Render's load balancer): returns 200 as
 *    long as PHP can serve a request. Does NOT take the service out of LB
 *    rotation when the database hiccups — that would cascade the DB outage
 *    into a full-app outage.
 *
 *  - Readiness (response body, used by humans + monitoring): probes the DB
 *    via SELECT 1 and reports `db: reachable` or `db: unreachable`. A 200
 *    response with `db: unreachable` means "PHP up, Postgres down" — the
 *    service still serves cached/static content, but every login, inquiry,
 *    enrollment write, etc. will fail.
 *
 * Required by render.yaml → healthCheckPath: /healthcheck.php
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

// db() exits with HTML 503 on connection failure (designed for app pages
// that should never render with a dead DB). For the healthcheck we want the
// OPPOSITE — probe, then return JSON regardless. Connect directly here
// without going through db() so the exit-on-fail path is never taken.
$dbState = 'unreachable';

try {
    $sslmode = getenv('DB_SSLMODE');
    if ($sslmode === false || $sslmode === '') {
        $sslmode = (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1') ? 'disable' : 'require';
    }
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        DB_HOST,
        getenv('DB_PORT') ?: '6543',
        DB_NAME,
        $sslmode
    );
    $probe = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $probe->query('SELECT 1');
    $dbState = 'reachable';
} catch (Throwable $e) {
    // Do NOT echo the exception message — it can include DSN fragments or
    // stack frames. Log it server-side; the operator reads Render logs.
    error_log('healthcheck db probe failed: ' . $e->getMessage());
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'status'  => 'ok',
    'service' => 'trac-jhs-sarms',
    'time'    => gmdate('c'),
    'db'      => $dbState,
], JSON_UNESCAPED_SLASHES) . "\n";