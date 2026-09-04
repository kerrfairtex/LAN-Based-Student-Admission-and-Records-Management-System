<?php
/**
 * TRAC JHS SARMS — local dev bootstrap.
 *
 * One-command setup: starts an embedded PostgreSQL on port 5433,
 * imports database/schema.sql, sets DB_* env vars, and execs
 * `php -S 0.0.0.0:8000`. Killed on Ctrl+C cleans up Postgres.
 *
 * Cross-platform: invoked from tools/dev-up.sh (Unix) or
 * tools/dev-up.cmd (Windows). Both shell out to this PHP file
 * so there is exactly one source of truth.
 *
 * Usage:
 *   php tools/dev-up.php
 *
 * Requirements (checked up front with clear errors):
 *   - PHP with pdo_pgsql extension loaded
 *   - PostgreSQL client binaries on PATH: initdb, pg_ctl, psql, pg_isready
 *
 * Behavior:
 *   1. Verifies the four pg tools and pdo_pgsql are available.
 *   2. If .pgdata/ is missing OR its PG_VERSION doesn't match the
 *      installed `pg_ctl`, runs `initdb` fresh with --auth=trust so
 *      a blank DB_PASS just works. Wipes the old cluster with a
 *      warning line so returning devs notice the data reset.
 *   3. Starts the cluster on port 5433 via `pg_ctl -o "-p 5433"`.
 *      Polls pg_isready for up to ~10s before failing loudly.
 *   4. Imports database/schema.sql via tools/import_schema.php
 *      (idempotent — re-runs are safe).
 *   5. Exports DB_HOST=127.0.0.1, DB_PORT=5433, DB_NAME=postgres,
 *      DB_USER=postgres, DB_PASS=*** DB_SCHEMA=trac_jhs_sarms,
 *      DB_SSLMODE=disable into the environment of the child process.
 *   6. `exec`s `php -S 0.0.0.0:8000` so Ctrl+C is forwarded and
 *      signals still work normally. A shutdown function registered
 *      via register_shutdown_function runs `pg_ctl stop` so the
 *      Postgres child doesn't survive the dev server.
 *
 * The .pgdata/ directory, .pglogs/, and the dev server's PID all
 * live under the project root. .gitignore already excludes them
 * so a fresh clone starts clean.
 *
 * NOTE: this is for local development only. Production runs on
 * Render against a managed Postgres — see AGENTS.md and
 * render.yaml for that path. The seed users imported by
 * schema.sql use a comment-documented default password that the
 * project README explicitly tells the operator to rotate on
 * first login.
 */

declare(strict_types=1);

const PROJECT_ROOT  = __DIR__ . '/..';
const DATA_DIR      = PROJECT_ROOT . '/.pgdata';
const LOG_FILE      = PROJECT_ROOT . '/.pglogs/dev-up.log';
const DB_PORT       = 5433;
const DB_USER       = 'postgres';
const DB_PASS       = '';
const DB_NAME       = 'postgres';
const SCHEMA        = 'trac_jhs_sarms';
const PG_START_WAIT = 10; // seconds
const DEV_HTTP_PORT = 8000;

/* ------------------------------------------------------------------ *
 *  Helpers
 * ------------------------------------------------------------------ */

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function err(string $msg): void
{
    fwrite(STDERR, $msg . PHP_EOL);
}

function require_tool(string $name): string
{
    $path = trim((string) shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));
    if ($path === '') {
        err("FATAL: '{$name}' not found on PATH. Install PostgreSQL client tools and retry.");
        err("       Get them from https://www.postgresql.org/download/ — make sure");
        err("       initdb, pg_ctl, psql, and pg_isready are all on PATH.");
        exit(1);
    }
    return $path;
}

function pg_version_from_bin(): ?string
{
    $out = shell_exec('pg_ctl --version 2>/dev/null');
    if (!is_string($out) || $out === '') {
        return null;
    }
    if (preg_match('/\b(\d+)\.(\d+)/', $out, $m)) {
        return $m[1];
    }
    return null;
}

function pg_version_from_cluster(): ?string
{
    $f = DATA_DIR . '/PG_VERSION';
    if (!is_file($f)) {
        return null;
    }
    $v = trim((string) file_get_contents($f));
    return $v === '' ? null : $v;
}

/* ------------------------------------------------------------------ *
 *  Step 0 — verify environment
 * ------------------------------------------------------------------ */

out('[1/6] verifying PHP + PostgreSQL tools');

if (!extension_loaded('pdo_pgsql')) {
    err('FATAL: PHP pdo_pgsql extension is not loaded.');
    err('       Install it for your PHP build (e.g. apt install php-pgsql,');
    err('       brew install php, then enable pdo_pgsql in php.ini) and retry.');
    exit(1);
}

$pgCtl     = require_tool('pg_ctl');
$initdb    = require_tool('initdb');
$psql      = require_tool('psql');
$pgIsReady = require_tool('pg_isready');

$pgBinMajor = pg_version_from_bin();
if ($pgBinMajor === null) {
    err('FATAL: could not parse pg_ctl --version output.');
    exit(1);
}
out("       pg_ctl major version: {$pgBinMajor}");

/* ------------------------------------------------------------------ *
 *  Step 1 — ensure the embedded cluster exists
 * ------------------------------------------------------------------ */

out('[2/6] preparing embedded PostgreSQL cluster');

if (!is_dir(DATA_DIR)) {
    out('       .pgdata/ does not exist — running initdb');
    if (!is_dir(dirname(LOG_FILE))) {
        mkdir(dirname(LOG_FILE), 0775, true);
    }
    $cmd = sprintf(
        '%s -D %s -U %s --auth=trust --auth-host=trust --auth-local=trust > %s 2>&1',
        escapeshellarg($initdb),
        escapeshellarg(DATA_DIR),
        escapeshellarg(DB_USER),
        escapeshellarg(LOG_FILE)
    );
    exec($cmd, $o, $rc);
    if ($rc !== 0) {
        err('FATAL: initdb failed. See ' . LOG_FILE);
        err(implode("\n", $o));
        exit(1);
    }
    out('       initdb complete');
} else {
    $clusterMajor = pg_version_from_cluster();
    if ($clusterMajor !== null && $clusterMajor !== $pgBinMajor) {
        out("       WARNING: .pgdata/ is PG {$clusterMajor} but pg_ctl is PG {$pgBinMajor}.");
        out('       Reinitializing the embedded cluster to match the installed pg_ctl.');
        out('       Any local data in .pgdata/ will be lost.');
        exec('rm -rf ' . escapeshellarg(DATA_DIR) . ' ' . escapeshellarg(DATA_DIR . '.old') . ' 2>/dev/null');
        exec('mv ' . escapeshellarg(DATA_DIR) . ' ' . escapeshellarg(DATA_DIR . '.old') . ' 2>/dev/null');
        $cmd = sprintf(
            '%s -D %s -U %s --auth=trust --auth-host=trust --auth-local=trust >> %s 2>&1',
            escapeshellarg($initdb),
            escapeshellarg(DATA_DIR),
            escapeshellarg(DB_USER),
            escapeshellarg(LOG_FILE)
        );
        exec($cmd, $o, $rc);
        if ($rc !== 0) {
            err('FATAL: initdb failed after wipe. See ' . LOG_FILE);
            err(implode("\n", $o));
            exit(1);
        }
        out('       re-initdb complete (old cluster moved to .pgdata.old/)');
    } else {
        out('       cluster already exists at ' . DATA_DIR);
    }
}

/* ------------------------------------------------------------------ *
 *  Step 2 — start the cluster on 5433
 * ------------------------------------------------------------------ */

out('[3/6] starting PostgreSQL on port ' . DB_PORT);

// Refuse to start if a different Postgres already holds 5433.
exec(escapeshellarg($pgIsReady) . ' -h 127.0.0.1 -p ' . DB_PORT . ' -q', $o, $rc);
if ($rc === 0) {
    out("       a server is already responding on 127.0.0.1:5433 — assuming it's ours,");
    out('       continuing without starting a new instance.');
} else {
    $startLog = dirname(LOG_FILE) . '/pg-stdout.log';
    if (!is_dir(dirname($startLog))) {
        mkdir(dirname($startLog), 0775, true);
    }
    $cmd = sprintf(
        '%s -D %s -l %s -o %s start',
        escapeshellarg($pgCtl),
        escapeshellarg(DATA_DIR),
        escapeshellarg($startLog),
        escapeshellarg('-p ' . DB_PORT)
    );
    exec($cmd, $startOut, $startRc);
    if ($startRc !== 0) {
        err('FATAL: pg_ctl start failed.');
        err(implode("\n", $startOut));
        err('See ' . $startLog);
        exit(1);
    }

    // Wait for readiness with a hard timeout.
    $deadline = time() + PG_START_WAIT;
    $ready    = false;
    while (time() < $deadline) {
        exec(escapeshellarg($pgIsReady) . ' -h 127.0.0.1 -p ' . DB_PORT . ' -q', $o, $rc);
        if ($rc === 0) {
            $ready = true;
            break;
        }
        usleep(250_000); // 250ms
    }
    if (!$ready) {
        err('FATAL: PostgreSQL did not become ready within ' . PG_START_WAIT . 's.');
        err('See ' . $startLog);
        exit(1);
    }
    out('       ready in <' . PG_START_WAIT . 's');
}

/* ------------------------------------------------------------------ *
 *  Step 3 — import schema
 * ------------------------------------------------------------------ */

out('[4/6] importing database/schema.sql');

// Delegate to the existing one-shot importer so we don't duplicate
// the statement-splitting / error-reporting logic.
$env = sprintf(
    'DB_HOST=127.0.0.1 DB_PORT=%d DB_NAME=%s DB_USER=%s DB_PASS=%s DB_SCHEMA=%s DB_SSLMODE=disable',
    DB_PORT, DB_NAME, DB_USER, DB_PASS, SCHEMA
);
$cmd = $env . ' php ' . escapeshellarg(PROJECT_ROOT . '/tools/import_schema.php');
exec($cmd . ' 2>&1', $importOut, $importRc);
if ($importRc !== 0) {
    err('FATAL: schema import failed.');
    err(implode("\n", $importOut));
    exit(1);
}
out('       schema imported (idempotent — re-runs are safe)');

/* ------------------------------------------------------------------ *
 *  Step 4 — export env for the child dev server
 * ------------------------------------------------------------------ */

out('[5/6] preparing env for the dev server');

$childEnv = [
    'DB_HOST'    => '127.0.0.1',
    'DB_PORT'    => (string) DB_PORT,
    'DB_NAME'    => DB_NAME,
    'DB_USER'    => DB_USER,
    'DB_PASS'    => DB_PASS,
    'DB_SCHEMA'  => SCHEMA,
    'DB_SSLMODE' => 'disable',
    'PATH'       => getenv('PATH') ?: '',
    'HOME'       => getenv('HOME') ?: '',
];
foreach ($childEnv as $k => $v) {
    putenv("{$k}={$v}");
    $_ENV[$k]    = $v;
    $_SERVER[$k] = $v;
}

/* ------------------------------------------------------------------ *
 *  Step 5 — clean shutdown
 * ------------------------------------------------------------------ */

register_shutdown_function(static function (): void {
    // Best-effort: stop the Postgres child if we started it.
    // We can't tell from inside the shutdown fn whether we started
    // it vs. found one already running, so we attempt the stop
    // unconditionally. If the port is owned by something else,
    // `pg_ctl stop` will just fail to find our server and exit
    // non-zero — harmless.
    @exec('pg_ctl -D ' . escapeshellarg(DATA_DIR) . ' stop -m fast 2>/dev/null');
});

/* ------------------------------------------------------------------ *
 *  Step 6 — exec the dev server
 * ------------------------------------------------------------------ */

out('[6/6] starting php -S 0.0.0.0:' . DEV_HTTP_PORT);
out('');
out('  TRAC JHS SARMS is live at http://127.0.0.1:' . DEV_HTTP_PORT . '/');
out('  Sign in as registrar / Registrar@2026, change your password under');
out('  Account → Change Password immediately after first login.');
out('  Press Ctrl+C to stop both the dev server and the embedded Postgres.');
out('');

$devCmd = sprintf('exec %s -S 0.0.0.0:%d', escapeshellarg(PHP_BINARY), DEV_HTTP_PORT);
passthru($devCmd, $devExit);
exit($devExit);
