<?php

declare(strict_types=1);

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_SCHEMA', getenv('DB_SCHEMA') ?: 'trac_jhs_sarms');

/**
 * Shared PDO connection (singleton).
 *
 * @throws PDOException when the database is unreachable
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // sslmode: local Postgres (incl. the in-project .pgdata instance) does not need TLS,
    // but Render's managed Postgres requires sslmode=require. DB_SSLMODE overrides.
    $sslmode = getenv('DB_SSLMODE');
    if ($sslmode === false || $sslmode === '') {
        // Heuristic: Supabase / Render / non-localhost defaults to require.
        $sslmode = (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1') ? 'disable' : 'require';
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        DB_HOST,
        getenv('DB_PORT') ?: '6543',
        DB_NAME,
        $sslmode
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('SET search_path TO ' . DB_SCHEMA . ', public');
    } catch (PDOException $e) {
        if (PHP_SAPI === 'cli') {
            throw $e;
        }

        http_response_code(503);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Database Unavailable</title>';
        echo '<style>body{font-family:system-ui,sans-serif;background:#06401c;color:#f4f0e4;display:grid;place-items:center;min-height:100vh;margin:0}';
        echo '.box{max-width:420px;padding:1.5rem;border:1px solid rgba(240,196,25,.35);border-radius:12px;background:rgba(8,42,22,.8)}</style></head><body>';
        echo '<div class="box"><h1 style="margin-top:0;font-size:1.25rem">Database unavailable</h1>';
        echo '<p>TRAC JHS SARMS cannot reach Postgres. Check Supabase connection settings.</p>';
        echo '<p style="opacity:.75;font-size:.9rem">Host: ' . htmlspecialchars(DB_HOST, ENT_QUOTES, 'UTF-8');
        echo ' · Database: ' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '</p></div></body></html>';
        exit;
    }

    return $pdo;
}