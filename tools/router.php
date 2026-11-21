<?php

declare(strict_types=1);

/**
 * Router for the PHP built-in web server (`php -S ... tools/router.php`).
 *
 * The built-in server ignores .htaccess, so on the LAN deployment
 * (tools/dev-up.php) every file under the project root — backups/*.sql,
 * uploads/*.csv (learner PII), .env, config/ — would otherwise be
 * downloadable. This router mirrors the deny rules from the root
 * .htaccess. Keep the two lists in sync.
 *
 * Return false  → let the built-in server serve the file/script itself.
 */

$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$path = '/' . ltrim(str_replace('\\', '/', $path), '/');

$deny = static function (int $code) use ($root): bool {
    $error_code = $code;
    $_GET['code'] = (string) $code;
    require $root . '/error.php';
    return true;
};

// Path traversal / NUL guard.
if (str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path)) {
    return $deny(403);
}

// Sensitive directories (mirror of .htaccess).
if (preg_match('#^/(?:config|includes|database|backups|docs|tools|uploads|storage|templates|tests|api|node_modules|\.sessions|\.pgdata)(?:/|$)#', $path)) {
    return $deny(403);
}

// Dotfiles (.env, .git, .user.ini ...) except /.well-known/.
if (preg_match('#(?:^|/)\.(?!well-known/)#', $path)) {
    return $deny(403);
}

// Repo/build metadata at the web root.
if ($path !== '/robots.txt'
    && preg_match('#^/(?:[^/]+\.(?:md|sql|yaml|yml|lock|sh|cmd|txt)|composer\.json|package(?:-lock)?\.json|vercel\.json|Dockerfile(?:\.[a-z]+)?)$#', $path)) {
    return $deny(403);
}

// Clean URL (mirror of .htaccess RewriteRule).
if ($path === '/user-guidelines') {
    $_SERVER['SCRIPT_NAME'] = '/user-guidelines.php';
    require $root . '/user-guidelines.php';
    return true;
}

$file = $root . $path;
if ($path === '/' || is_dir($file)) {
    $index = rtrim($file, '/') . '/index.php';
    if (is_file($index)) {
        return false;
    }
    return $deny(404);
}

if (!is_file($file)) {
    return $deny(404);
}

return false;
