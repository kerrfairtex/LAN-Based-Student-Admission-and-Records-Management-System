<?php

declare(strict_types=1);

/**
 * Branded error page for 403 / 404 / 500.
 *
 * Wired from .htaccess (ErrorDocument) on Apache and from tools/router.php
 * on the PHP built-in (LAN/dev) server. Intentionally standalone: it does
 * NOT load config/app.php, sessions or the database, so it still renders
 * when Postgres or the session store is the thing that failed.
 */

$code = (int) ($_GET['code'] ?? ($error_code ?? 404));
if (!in_array($code, [403, 404, 500, 503], true)) {
    $code = 404;
}

$messages = [
    403 => ['Access denied', 'You do not have permission to view this resource.'],
    404 => ['Page not found', 'The page you were looking for does not exist or has moved.'],
    500 => ['Something went wrong', 'An unexpected error occurred. The problem has been logged. Please try again in a moment.'],
    503 => ['Service unavailable', 'The system is temporarily unavailable. Please try again in a few minutes.'],
];
[$title, $body] = $messages[$code];

http_response_code($code);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header_remove('X-Powered-By');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= $code ?> · <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · TRAC JHS</title>
    <link href="/assets/css/tokens.css" rel="stylesheet">
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:16px;
               font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
               background: var(--cream-100, #f6f3ea); color: var(--ink-900, #1b2340); }
        .err { max-width:460px; text-align:center; }
        .err img { width:72px; height:72px; border-radius:50%; }
        .err .code { font-size:56px; font-weight:700; margin:16px 0 0; letter-spacing:-1px; }
        .err h1 { font-size:22px; margin:4px 0 12px; }
        .err p { color: var(--ink-900, #1b2340); opacity:.75; line-height:1.5; margin:0 0 24px; }
        .err a { display:inline-block; padding:10px 18px; border-radius:8px; text-decoration:none;
                 background: var(--brand-green, #1f4d2b); color:#fff; font-weight:600; margin:4px; }
        .err a.secondary { background:transparent; color: var(--brand-green, #1f4d2b);
                           border:1px solid currentColor; }
    </style>
</head>
<body>
<main class="err" role="main">
    <img src="/assets/img/trac-jhs-seal.jpeg" alt="TRAC JHS seal" width="72" height="72">
    <p class="code"><?= $code ?></p>
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></p>
    <a href="/">Home</a>
    <a class="secondary" href="/dashboard.php">Dashboard</a>
</main>
</body>
</html>
