<?php

declare(strict_types=1);

/**
 * Centralized CSRF + session helpers.
 *
 * Required before use:
 *   - config/app.php (initializes session + session_save_path)
 *
 * Public API:
 *   csrf_token()       — string, lazily generates a 64-hex token if absent
 *   csrf_field()       — string, prints <input type="hidden" name="_csrf" value="…">
 *   verify_csrf($tok)  — bool, constant-time compare
 *   require_csrf()     — void, sets 419 + flash + redirect on mismatch
 */

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool
    {
        $sessionToken = $_SESSION['_csrf'] ?? '';

        return is_string($token)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf(): void
    {
        $token = $_POST['_csrf'] ?? null;

        if (!verify_csrf(is_string($token) ? $token : null)) {
            http_response_code(419);
            $_SESSION['flash'] = [
                'type'    => 'danger',
                'message' => 'Your session token expired. Please try again.',
            ];
            header('Location: ' . (defined('APP_BASE_PATH') ? APP_BASE_PATH : '') . '/');
            exit;
        }
    }
}