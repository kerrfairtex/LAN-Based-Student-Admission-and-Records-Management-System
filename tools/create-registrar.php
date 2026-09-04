<?php
/**
 * Interactive CLI: create a new TRAC JHS SARMS user (registrar or encoder).
 *
 * Prompts for username, full name, password (twice), and role.
 * Hashes the password with bcrypt and INSERTs into
 * trac_jhs_sarms.users. Uses the same DB_* env vars as the rest of
 * the app, so run this after `bash tools/dev-up.sh` (or with the
 * same env vars set if you're pointing at a different DB).
 *
 * Usage:
 *   php tools/create-registrar.php
 *
 * The script refuses to insert a user with a username that already
 * exists. The two default seed users (registrar, encoder) come from
 * database/schema.sql; this tool is for ADDITIONAL accounts, not
 * for replacing the seeds.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "FATAL: this tool must be run from the command line.\n");
    exit(1);
}

/* ------------------------------------------------------------------ *
 *  Prompt helpers
 * ------------------------------------------------------------------ */

function prompt(string $label, bool $hidden = false): string
{
    if ($hidden && stripos(PHP_OS, 'WIN') === false) {
        // Read without echo on Unix.
        fwrite(STDOUT, $label);
        fwrite(STDOUT, ' ');
        stty_set();
        $line = rtrim((string) fgets(STDIN), "\r\n");
        fwrite(STDOUT, "\n");
        stty_restore();
        return $line;
    }
    fwrite(STDOUT, $label);
    fwrite(STDOUT, ' ');
    return rtrim((string) fgets(STDIN), "\r\n");
}

function stty_set(): void
{
    @system('stty -echo');
}
function stty_restore(): void
{
    @system('stty echo');
}

$username  = prompt('Username:');
$fullName  = prompt('Full name:');
$pass1     = prompt('Password (will be hidden):', true);
$pass2     = prompt('Confirm password:',              true);
fwrite(STDOUT, "Role (registrar / encoder): ");
$role      = strtolower(trim((string) fgets(STDIN)));

/* ------------------------------------------------------------------ *
 *  Validate
 * ------------------------------------------------------------------ */

$errors = [];
if (!preg_match('/^[a-z0-9_]{3,40}$/', $username)) {
    $errors[] = 'username must be 3-40 chars, lowercase letters / digits / underscore';
}
if ($fullName === '') {
    $errors[] = 'full name is required';
}
if (strlen($pass1) < 8) {
    $errors[] = 'password must be at least 8 characters';
}
if ($pass1 !== $pass2) {
    $errors[] = 'passwords do not match';
}
if (!in_array($role, [ROLE_REGISTRAR, ROLE_ENCODER], true)) {
    $errors[] = 'role must be "registrar" or "encoder"';
}

if ($errors) {
    fwrite(STDERR, "FATAL:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, '  - ' . $e . "\n");
    }
    exit(1);
}

/* ------------------------------------------------------------------ *
 *  Insert
 * ------------------------------------------------------------------ */

$hash = password_hash($pass1, PASSWORD_BCRYPT);

try {
    $stmt = db()->prepare(
        'INSERT INTO ' . DB_SCHEMA . '.users (username, password_hash, full_name, role)
         VALUES (:username, :hash, :full_name, :role)
         RETURNING id'
    );
    $stmt->execute([
        ':username'  => $username,
        ':hash'      => $hash,
        ':full_name' => $fullName,
        ':role'      => $role,
    ]);
    $id = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'users_username_key')
        || str_contains($e->getMessage(), 'duplicate key')) {
        fwrite(STDERR, "FATAL: username '{$username}' already exists.\n");
        exit(1);
    }
    throw $e;
}

fwrite(STDOUT, "OK: user #{$id} '{$username}' ({$role}) created.\n");
fwrite(STDOUT, "    Sign in at /auth/login.php to start using it.\n");
exit(0);
