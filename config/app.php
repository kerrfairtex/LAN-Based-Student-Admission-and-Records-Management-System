<?php

declare(strict_types=1);

define('APP_NAME', 'TRAC JHS SARMS');
define('APP_SCHOOL', 'Tawi-Tawi Regional Agricultural College Junior High School');
define('APP_LOCATION', 'Bongao, Tawi-Tawi');
define('APP_REGION', 'BARMM');
define('APP_TIMEZONE', 'Asia/Manila');
define('SESSION_TIMEOUT', 1800);
define('APP_ROOT', dirname(__DIR__));

/**
 * Detect URL base path for XAMPP subdirectory installs
 * (e.g. /trac-jhs-sarms when document root is htdocs).
 */
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$appRootReal = realpath(APP_ROOT) ?: APP_ROOT;
$basePath = '';

if ($documentRoot !== '' && str_starts_with($appRootReal, $documentRoot)) {
    $basePath = str_replace('\\', '/', substr($appRootReal, strlen($documentRoot)));
}

define('APP_BASE_PATH', rtrim($basePath, '/'));

date_default_timezone_set(APP_TIMEZONE);

/**
 * Force display_errors off, defensively, BEFORE anything else runs.
 *
 * Why we override .user.ini here:
 *   `.user.ini` is honored by CGI/FastCGI/FPM SAPIs but IGNORED by Apache's
 *   mod_php. Render ships `php:8.3-apache` which uses mod_php, so any
 *   `display_errors = 0` set in `.user.ini` is silently dropped there. In
 *   production on Render, `display_errors` defaults to ON, so PHP warnings
 *   and notices (e.g. `scandir(): Permission denied` from a missing
 *   persistent-disk mount) leak into HTML responses alongside the actual
 *   page content. That leaks absolute filesystem paths and table names to
 *   anyone who hits a broken endpoint.
 *
 *   `ini_set()` from PHP code is also unreliable on some SAPIs and is
 *   blocked once output has started; setting it as the very first thing in
 *   config/app.php (which every page requires) guarantees it runs before
 *   any output and on every SAPI we ship to.
 *
 * What we still keep in .user.ini:
 *   - The portable baseline (CGI/FPM users still pick it up).
 *   - `log_errors = 1` and `error_log = php://stderr` for the Render log
 *     stream — those are honored by mod_php and are the diagnostic channel
 *     operators use to investigate warnings we now suppress on the wire.
 *
 * We also bump error_reporting here so E_DEPRECATED/E_STRICT are NOT
 * silenced at the reporting layer — they still go to the error log, only
 * the on-screen HTML is suppressed. That keeps server-side diagnostics
 * complete while shutting the info-disclosure hole.
 */
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

/**
 * Emit baseline security headers from PHP for every page. Apache's mod_headers
 * is not always available (Render's stock php image doesn't enable it), so we
 * set these in PHP before any output is sent. The .htaccess also sets them via
 * "Header always set" for environments that DO have mod_headers — duplicates are
 * harmless; the latest value wins.
 */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Content-Security-Policy. Mirrored in .htaccess mod_headers for the
    // Apache path; this PHP fallback covers the PHP built-in dev server,
    // any SAPI without mod_headers, and edge intermediaries that strip
    // .htaccess-set headers.
    //
    // What's allowed:
    //   default-src 'self'         — no external scripts/styles by default
    //   script-src  +cdn.jsdelivr  — Bootstrap JS (no inline currently used
    //                                  beyond Bootstrap's own bundle init,
    //                                  but 'unsafe-inline' keeps the
    //                                  existing inline mobile-sidebar
    //                                  init block working without a
    //                                  per-request nonce)
    //   style-src   +fonts.googleapis +cdn.jsdelivr — Google Fonts CSS,
    //                                  Bootstrap CSS, Bootstrap Icons
    //                                  font CSS, plus inline style=""
    //                                  attrs that the templates rely on
    //   font-src    +cdn.jsdelivr +fonts.gstatic +data: — Bootstrap Icons
    //                                  webfont + Google Fonts files +
    //                                  inline data: URIs (favicons)
    //   img-src     'self' data:    — local images + inline data: URIs only
    //   connect-src 'self'         — no XHR/fetch to other origins
    //   frame-ancestors 'self'     — no third-party iframe embedding
    //   form-action 'self'         — forms can only POST to us
    //   base-uri    'self'         — no <base href> hijack
    //   object-src  'none'         — no <object>/<embed>/Flash legacy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");

    header_remove('X-Powered-By');
}

/**
 * Ensure a writable directory exists at $path. Handles three cases:
 *   1. Path is a real directory — do nothing.
 *   2. Path is a symlink (Render persistent-disk pattern) — mkdir the target if missing.
 *   3. Path is missing — mkdir recursively.
 * Suppresses the "File exists" warning when the path is a dangling symlink.
 *
 * Defined here (not in functions.php) because config/app.php is the first file
 * loaded by every page, including before session storage needs to be set up.
 */
function ensure_dir(string $path, int $mode = 0750): void
{
    if (is_dir($path)) {
        return;
    }
    if (is_link($path)) {
        $target = readlink($path);
        if ($target !== false && !is_dir($target)) {
            @mkdir($target, $mode, true);
        }
        return;
    }
    @mkdir($path, $mode, true);
}

require_once __DIR__ . '/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    // Keep session storage inside the project dir (no leaks to /tmp).
    // On Render this is a symlink into the persistent disk; on local dev it's
    // a real directory. ensure_dir() handles both (including the dangling-symlink
    // case that the persistent disk hits on first deploy).
    ensure_dir(APP_ROOT . '/.sessions');

    session_save_path(APP_ROOT . '/.sessions');

    session_name('TRAC_JHS_SARMS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_BASE_PATH === '' ? '/' : APP_BASE_PATH . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // Treat the cookie as Secure when the request was HTTPS, either by direct
        // TLS termination (HTTPS server var) or by a trusted proxy that forwards the
        // original scheme via X-Forwarded-Proto (Render behind Cloudflare).
        'secure' => (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ),
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';
require_once APP_ROOT . '/includes/functions.php';

/**
 * Global PDOException → graceful-error conversion.
 *
 * The previous code path was: any uncaught PDOException bubbled up through
 * PHP's default handler, which dumps the message + stack trace into the HTTP
 * response (Dsn/host fragments, file paths, table names — information
 * disclosure). The audit caught this on modules/records/view.php:13, but
 * every other db()->prepare()/query() call site was equally capable of
 * leaking — fixing only one would just move the next critical finding to a
 * different endpoint on the next audit.
 *
 * Strategy:
 *   - set_exception_handler here catches anything that escapes per-page
 *     try/catch blocks, logs the full exception server-side (PHP error log
 *     → Render log stream), and renders a generic, branded 500 page. No
 *     PDOException text, file paths, stack frames, or query strings are
 *     echoed to the browser.
 *   - Per-page handlers (e.g. records/view.php) should still validate input
 *     BEFORE the DB call and wrap the call in try/catch so the user gets a
 *     helpful in-context redirect instead of a generic 500. The global
 *     handler is a safety net, not the primary UX.
 *   - We do NOT touch display_errors here — that lives in .user.ini so the
 *     directive is honored by the SAPI before any script runs.
 */
set_exception_handler(function (Throwable $e): void {
    // Log the FULL exception to PHP error log (→ Render log stream).
    error_log(sprintf(
        '[unhandled] %s: %s in %s:%d%sStack:%s%s',
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        PHP_EOL,
        $e->getTraceAsString()
    ));

    if (headers_sent()) {
        // Headers already flushed by an earlier echo — best we can do is
        // emit a plain-text fallback. Don't try to send a 500 status.
        echo "A server error occurred. Please contact the registrar's office.";
        return;
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');

    // Minimal branded page. Kept inline (no template dependency) so it
    // works even if the rendering layer is what threw.
    echo <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Server error — TRAC JHS SARMS</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<style>
  body{font:16px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#e9efe9;background:#0a1612;margin:0;padding:48px 20px;}
  .wrap{max-width:560px;margin:0 auto;background:#11221c;border:1px solid #1c3a2e;border-radius:10px;padding:28px;}
  h1{margin:0 0 8px;font-size:20px;color:#d4a72c;}
  p{margin:0 0 12px;color:#c4d4cb;}
  a.btn{display:inline-block;margin-top:8px;padding:8px 14px;border:1px solid #2c5a48;border-radius:6px;color:#e9efe9;text-decoration:none;}
  a.btn:hover{background:#163126;}
</style>
</head>
<body>
<div class="wrap">
  <h1>We hit a snag on our side.</h1>
  <p>Your request could not be completed because of a temporary server problem.
     Our team has been notified. Please try again in a moment.</p>
  <p>If the problem continues, contact the registrar's office.</p>
  <a class="btn" href="/">Return to home</a>
</div>
</body>
</html>
HTML;
    exit;
});
