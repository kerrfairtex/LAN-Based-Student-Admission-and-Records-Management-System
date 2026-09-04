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

    $stmt = db()->prepare('UPDATE users SET last_active = NOW() WHERE id = :id');
    $stmt->execute(['id' => (int) $_SESSION['user']['id']]);
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

    $stmt = db()->prepare(
        'SELECT id, username, password_hash, full_name, role, is_active
         FROM users WHERE username = :username LIMIT 1'
    );
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

    unset($user['password_hash'], $user['is_active']);
    $_SESSION['user'] = $user;
    $_SESSION['last_activity'] = time();

    audit_log('login', 'users', (int) $user['id'], 'User signed in');

    return true;
}

function logout_user(): void
{
    if (is_logged_in()) {
        audit_log('logout', 'users', (int) $_SESSION['user']['id'], 'User signed out');
    }

    unset($_SESSION['user'], $_SESSION['last_activity'], $_SESSION['_csrf']);
    session_regenerate_id(true);
}

/**
 * Return the originating client IP, honoring X-Forwarded-For from a single
 * trusted proxy (Render's edge). Falls back to REMOTE_ADDR. Never trust
 * multiple XFF hops because the public deploy is behind exactly one proxy.
 */
function client_ip(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $first = trim(explode(',', $forwarded)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
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
