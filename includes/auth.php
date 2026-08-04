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
    $stmt = db()->prepare(
        'SELECT id, username, password_hash, full_name, role, is_active
         FROM users WHERE username = :username LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !(bool) $user['is_active']) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
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
