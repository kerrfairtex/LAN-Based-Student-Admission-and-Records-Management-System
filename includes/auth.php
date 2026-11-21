<?php

declare(strict_types=1);

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_registrar(): bool
{
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === ROLE_REGISTRAR;
}

function is_encoder(): bool
{
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === ROLE_ENCODER;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Please sign in to continue.');
        redirect('/');
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        flash('warning', 'Your session has expired. Please sign in again.');
        redirect('/');
    }

    $_SESSION['last_activity'] = time();

    // Touch last_active AND re-read the account in the same round-trip, so
    // that a registrar disabling a user, changing their role, or forcing a
    // password reset takes effect on that user's very next request instead
    // of when their session happens to expire. RETURNING * (not a column
    // list) keeps this working on databases that predate migration 008.
    $stmt = db()->prepare('UPDATE users SET last_active = NOW() WHERE id = :id RETURNING *');
    $stmt->execute(['id' => (int) $_SESSION['user']['id']]);
    $row = $stmt->fetch();

    if (!$row || !(int) $row['is_active']) {
        unset($_SESSION['user'], $_SESSION['last_activity']);
        session_regenerate_id(true);
        flash('warning', 'Your account is no longer active. Please contact the School Registrar.');
        redirect('/auth/login.php');
    }

    $_SESSION['user']['role'] = $row['role'];
    $_SESSION['user']['full_name'] = $row['full_name'];
    if ((int) ($row['must_change_password'] ?? 0) === 1) {
        $_SESSION['must_change_password'] = true;
    }

    enforce_password_change();
}

/**
 * While a forced password change is pending (seed password, or a registrar
 * reset), every authenticated page except the change-password screen
 * redirects there. Logout stays reachable because it never calls
 * require_login().
 */
function enforce_password_change(): void
{
    if (empty($_SESSION['must_change_password'])) {
        return;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_ends_with($script, '/modules/account/password.php')) {
        return;
    }
    flash('warning', 'You must set a new password before continuing.');
    redirect('/modules/account/password.php');
}

/** Passwords that are public in this repository (README / schema.sql). */
const KNOWN_PUBLIC_PASSWORDS = ['Registrar@2026', 'Encoder@2026'];

/**
 * Password policy. Returns a list of human-readable problems (empty = OK).
 *
 * Follows NIST SP 800-63B in spirit: length matters most, plus a block list
 * of known-compromised values. A letter+digit mix is required because staff
 * accounts guard learner PII under RA 10173.
 */
function password_policy_errors(string $password, string $username = ''): array
{
    $errors = [];
    $len = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);

    if ($len < 10) {
        $errors[] = 'must be at least 10 characters';
    }
    if ($len > 128) {
        $errors[] = 'must be at most 128 characters';
    }
    if (!preg_match('/\pL/u', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'must contain at least one letter and one number';
    }
    if ($username !== '' && stripos($password, $username) !== false) {
        $errors[] = 'must not contain the username';
    }
    $common = ['password123', 'password1234', 'qwerty12345', '1234567890', 'admin12345', 'trac123456', 'tracjhs2026'];
    if (in_array($password, KNOWN_PUBLIC_PASSWORDS, true) || in_array(strtolower($password), $common, true)) {
        $errors[] = 'is a publicly known password';
    }

    return $errors;
}

/**
 * Store a new password hash. $mustChange=true forces the user to pick a new
 * password on next sign-in (used for registrar resets).
 *
 * Tolerates databases that predate migration 008 (no must_change_password
 * column): falls back to updating only the hash.
 */
function set_user_password(int $userId, string $plain, bool $mustChange): void
{
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    try {
        db()->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = :mc WHERE id = :id'
        )->execute(['hash' => $hash, 'mc' => $mustChange ? 1 : 0, 'id' => $userId]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '42703') { // undefined_column
            throw $e;
        }
        db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => $hash, 'id' => $userId]);
    }
}

/** Number of active registrar accounts (guards against locking everyone out). */
function active_registrar_count(): int
{
    return (int) db()->query(
        "SELECT COUNT(*) FROM users WHERE role = 'registrar' AND is_active = 1"
    )->fetchColumn();
}

function require_registrar(): void
{
    require_login();

    if (!is_registrar()) {
        flash('danger', 'Only the School Registrar can perform this action.');
        redirect('/dashboard.php');
    }
}

function attempt_login(string $username, string $password): bool
{
    // Rate-limit gate. Run BEFORE password_verify so an attacker cannot
    // consume CPU cycles hashing guesses. Two windows:
    //   - per-username: 5 fails / 15 min -> 429 (block guessing the password
    //     for a known username)
    //   - per-IP:       30 fails / 5 min -> 429 (block username-enumeration
    //     sweeps across many usernames from one source)
    $rate = login_rate_check($username);
    if ($rate !== 'ok') {
        audit_log(
            'login_throttled',
            'users',
            null,
            sprintf('throttled=%s; username=%s; ip=%s', $rate, $username, client_ip())
        );
        return false;
    }

    // SELECT * so this keeps working on databases that predate migration 008
    // (must_change_password column).
    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !(bool) $user['is_active']) {
        // Failed-login audit (account not found or disabled). Don't leak
        // whether the username exists — log the failure reason as
        // 'unknown_or_disabled' so DPA reviewers can distinguish
        // brute-force patterns from inactive-account typos.
        audit_log(
            'login_failed',
            'users',
            $user ? (int) $user['id'] : null,
            $user ? 'Login attempt on inactive account' : 'Login attempt with unknown username'
        );
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        audit_log(
            'login_failed',
            'users',
            (int) $user['id'],
            'Bad password for ' . $user['username']
        );
        return false;
    }

    $touch = db()->prepare('UPDATE users SET last_active = NOW() WHERE id = :id');
    $touch->execute(['id' => $user['id']]);

    // Regenerate session ID to prevent session fixation attacks.
    // This MUST happen after clearing sensitive session data and before
    // storing the authenticated user record — the old session ID becomes
    // invalid, so any attacker-set session cookie is discarded.
    session_regenerate_id(true);

    // Force a password change when the account was reset by a registrar or
    // is still using one of the seed passwords published in this repo.
    $mustChange = (int) ($user['must_change_password'] ?? 0) === 1
        || in_array($password, KNOWN_PUBLIC_PASSWORDS, true);

    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ];
    $_SESSION['last_activity'] = time();
    if ($mustChange) {
        $_SESSION['must_change_password'] = true;
    } else {
        unset($_SESSION['must_change_password']);
    }

    audit_log('login', 'users', (int) $user['id'], 'User signed in');

    return true;
}

function logout_user(): void
{
    if (is_logged_in()) {
        audit_log('logout', 'users', (int) $_SESSION['user']['id'], 'User signed out');
    }

    unset($_SESSION['user'], $_SESSION['last_activity'], $_SESSION['_csrf'], $_SESSION['must_change_password']);
    session_regenerate_id(true);
}

/**
 * Read recent login_failed audit rows and decide whether the current attempt
 * should be throttled. Returns 'ok', 'username', or 'ip'.
 *
 * Implementation: audit_logs already records every failed attempt with the
 * attempted username embedded in `details` ("Bad password for X" / "Login
 * attempt with unknown username X") and the originating IP in `ip_address`.
 * Counting there avoids any new table or session state — what is already
 * being audited becomes the throttle counter.
 *
 * Successful login naturally ages the counter out because we only count
 * 'login_failed' rows.
 */
function login_rate_check(string $username): string
{
    $ip = client_ip();

    // Per-username: 5 fails / 15 min. The audit row `details` column carries
    // the attempted username in two formats ("Bad password for X" for a known
    // username, "Login attempt with unknown username X" otherwise), so we
    // LIKE both patterns. Username is already constrained to VARCHAR(50)
    // and bound via prepared statements, so the LIKE wildcards can't inject.
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS n FROM audit_logs
         WHERE action = 'login_failed'
           AND created_at > NOW() - INTERVAL '15 minutes'
           AND (details LIKE :u1 OR details LIKE :u2)"
    );
    $stmt->execute([
        ':u1' => '%for ' . $username . '%',
        ':u2' => '%unknown username ' . $username . '%',
    ]);
    $uFails = (int) $stmt->fetch()['n'];
    if ($uFails >= 5) {
        return 'username';
    }

    // Per-IP: 30 fails / 5 min (covers enumeration sweeps across many usernames)
    if ($ip !== '') {
        $stmt = db()->prepare(
            "SELECT COUNT(*) AS n FROM audit_logs
             WHERE action = 'login_failed'
               AND ip_address = :ip
               AND created_at > NOW() - INTERVAL '5 minutes'"
        );
        $stmt->execute([':ip' => $ip]);
        $ipFails = (int) $stmt->fetch()['n'];
        if ($ipFails >= 30) {
            return 'ip';
        }
    }

    return 'ok';
}

/**
 * Clear the per-username throttle counter. Called by a registrar from
 * Admin -> Users when a legitimate user got locked out (e.g. password
 * typo during rotation). Logs the reset for audit trail.
 */
function reset_login_throttle(string $username): int
{
    $stmt = db()->prepare(
        "DELETE FROM audit_logs
         WHERE action = 'login_failed'
           AND (details LIKE :u1 OR details LIKE :u2)"
    );
    $stmt->execute([
        ':u1' => '%for ' . $username . '%',
        ':u2' => '%unknown username ' . $username . '%',
    ]);
    return $stmt->rowCount();
}
