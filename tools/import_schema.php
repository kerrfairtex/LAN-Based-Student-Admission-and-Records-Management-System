<?php

/**
 * One-shot schema importer for Railway MySQL.
 *
 * Reads the same DB_* env vars as config/database.php, then executes
 * database/schema.sql statement-by-statement against the target database.
 *
 * Usage: php tools/import_schema.php
 */

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$name = getenv('DB_NAME') ?: 'trac_jhs_sarms';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$schemaFile = __DIR__ . '/../database/schema.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "FATAL: schema.sql not found at {$schemaFile}\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "FATAL: cannot connect to MySQL: " . $e->getMessage() . "\n");
    exit(1);
}

$sql = file_get_contents($schemaFile);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "FATAL: empty or unreadable schema.sql\n");
    exit(1);
}

// Split into statements. Split on ";\n" which is safe here — no semicolons
// appear inside string literals or data values in schema.sql.
$statements = array_filter(
    array_map('trim', preg_split('/;\s*\n/', $sql)),
    static fn (string $s): bool => $s !== ''
);

// Drop CREATE DATABASE / USE statements — the database already exists
// (created by the MYSQL_DATABASE env var on the container).
$executed = 0;
$skipped = 0;
foreach ($statements as $stmt) {
    $upper = strtoupper(substr($stmt, 0, 60));
    if (str_starts_with($upper, 'CREATE DATABASE') || str_starts_with($upper, 'USE ')) {
        $skipped++;
        continue;
    }

    try {
        $pdo->exec($stmt);
        $executed++;
    } catch (PDOException $e) {
        fwrite(STDERR, "ERROR executing:\n{$stmt}\n→ " . $e->getMessage() . "\n");
        // Re-throw: a failed DDL statement should abort the import so we don't
        // ship a half-initialized schema.
        exit(1);
    }
}

echo "IMPORT OK: {$executed} statements executed, {$skipped} skipped (DB/USE).\n";

// Verify table count
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo 'Tables: ' . count($tables) . ' → ' . implode(', ', $tables) . "\n";
exit(0);
