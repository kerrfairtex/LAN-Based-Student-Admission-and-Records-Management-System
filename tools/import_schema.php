<?php

/**
 * One-shot SQL importer for Supabase / local PostgreSQL.
 *
 * Reads the same DB_* env vars as config/database.php, then executes
 * the given .sql file against the target Postgres via `psql -f`.
 *
 * Usage:
 *   php tools/import_schema.php                          # imports database/schema.sql (default)
 *   php tools/import_schema.php database/migrations/foo.sql  # imports that file
 *
 * The default target is database/schema.sql. Pass any other .sql path
 * (relative to the project root, or absolute) to apply a migration
 * during bootstrap or recovery.
 */

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '6543';
$name = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASS') ?: '';

$argPath = $argv[1] ?? null;
if ($argPath === null) {
    $schemaFile = __DIR__ . '/../database/schema.sql';
} elseif (str_starts_with($argPath, '/')) {
    $schemaFile = $argPath;
} else {
    // Relative paths resolve against the project root so the operator
    // can write `php tools/import_schema.php database/migrations/005_inquiries.sql`
    // from anywhere.
    $schemaFile = __DIR__ . '/../' . ltrim($argPath, '/');
}
if (!is_file($schemaFile)) {
    fwrite(STDERR, "FATAL: SQL file not found at {$schemaFile}\n");
    exit(1);
}

// Pre-flight: verify Postgres is reachable BEFORE we ask psql to read the file.
// We surface a PHP-readable DSN error message; psql's own message is less friendly
// and doesn't include the libpq detail string in all cases.
try {
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "FATAL: cannot connect to Postgres: " . $e->getMessage() . "\n");
    exit(1);
}

$sql = file_get_contents($schemaFile);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "FATAL: empty or unreadable schema.sql\n");
    exit(1);
}

/**
 * Delegate statement parsing + execution to psql.
 *
 * The previous PHP-side preg_split on /;\s*\n/ chopped inside PL/pgSQL
 * function bodies (CREATE OR REPLACE FUNCTION ... AS $$ BEGIN ... END;
 * $$ LANGUAGE plpgsql), because every line inside the body ends with
 * `;\n`. Postgres already understands dollar-quoted strings, PL/pgSQL
 * bodies, and the CREATE SCHEMA / SET directives — passing the whole
 * file to `psql -f` is both simpler and correct.
 *
 * ON_ERROR_STOP=1 makes psql exit non-zero on the first error, so we
 * can detect failure and propagate it.
 *
 * Note on search_path: schema.sql fully qualifies every object with
 * `trac_jhs_sarms.<name>` (69 such references, including CREATE TABLE),
 * and `CREATE SCHEMA IF NOT EXISTS trac_jhs_sarms;` runs as the first
 * executable statement (line 10). We therefore do NOT need PGOPTIONS
 * here — the file is self-bootstrapping into its own schema. The PHP
 * runtime's `db()` sets search_path on every connection so the rest of
 * the app can use unqualified names.
 */
$psql = trim((string) shell_exec('command -v psql 2>/dev/null'));
if ($psql === '') {
    fwrite(STDERR, "FATAL: 'psql' not found on PATH. Install PostgreSQL client tools.\n");
    exit(1);
}

$cmd = sprintf(
    '%s -h %s -p %s -U %s -d %s -v ON_ERROR_STOP=1 -f %s 2>&1',
    escapeshellarg($psql),
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($user),
    escapeshellarg($name),
    escapeshellarg($schemaFile)
);

// Empty DB_PASS implies local --auth=trust (set up by dev-up.php /
// initdb), so psql will connect without a password. If a password IS
// set, push it via PGPASSWORD so psql doesn't prompt.
if ($pass !== '') {
    putenv('PGPASSWORD=' . $pass);
}

exec($cmd, $out, $rc);
if ($rc !== 0) {
    fwrite(STDERR, "FATAL: schema import failed (psql exit={$rc}).\n");
    fwrite(STDERR, implode("\n", $out) . "\n");
    exit(1);
}

echo "IMPORT OK via psql -f.\n";

// Verify the import actually created tables (not just that psql exited 0
// against a no-op file). schema.sql always lands objects in
// `trac_jhs_sarms.*`, so query that schema by name rather than via
// current_schema() (which on a bare PDO connection without search_path
// resolves to public).
$schemaName = getenv('DB_SCHEMA') ?: 'trac_jhs_sarms';
$tables = $pdo->query(
    "SELECT tablename FROM pg_tables WHERE schemaname = " .
    $pdo->quote($schemaName) . " ORDER BY tablename"
)->fetchAll(PDO::FETCH_COLUMN);
echo 'Tables: ' . count($tables) . ' → ' . implode(', ', $tables) . "\n";

if (count($tables) === 0) {
    fwrite(STDERR, "FATAL: schema import reported success but no tables were created.\n");
    fwrite(STDERR, "       Check that database/schema.sql contains CREATE TABLE statements.\n");
    exit(1);
}

exit(0);