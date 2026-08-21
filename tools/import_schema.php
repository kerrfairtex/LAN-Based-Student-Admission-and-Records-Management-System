<?php

/**
 * One-shot schema importer for Supabase PostgreSQL.
 *
 * Reads the same DB_* env vars as config/database.php, then executes
 * database/schema.sql statement-by-statement against the target Postgres.
 *
 * Usage: php tools/import_schema.php
 */

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '6543';
$name = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASS') ?: '';

$schemaFile = __DIR__ . '/../database/schema.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "FATAL: schema.sql not found at {$schemaFile}\n");
    exit(1);
}

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

// Strip full-line comments so statements classify cleanly
$sql = preg_replace('/^\s*--.*$/m', '', $sql);

// Split into statements
$statements = array_filter(
    array_map('trim', preg_split('/;\s*\n/', $sql)),
    static fn (string $s): bool => $s !== ''
);

$executed = 0;
$skipped = 0;
foreach ($statements as $stmt) {
    $upper = strtoupper(substr($stmt, 0, 60));
    // Skip SET search_path and SET NAMES — handled by PGOPTIONS
    if (str_starts_with($upper, 'CREATE SCHEMA') || preg_match('/^SET\s+(search_path|NAMES)/i', $upper)) {
        $skipped++;
        continue;
    }

    try {
        $pdo->exec($stmt);
        $executed++;
    } catch (PDOException $e) {
        fwrite(STDERR, "ERROR executing:\n{$stmt}\n→ " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "IMPORT OK: {$executed} statements executed, {$skipped} skipped (SCHEMA / SET).\n";

// Verify table count
$tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
echo 'Tables: ' . count($tables) . ' → ' . implode(', ', $tables) . "\n";
exit(0);