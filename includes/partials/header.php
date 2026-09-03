<?php

declare(strict_types=1);

/**
 * Shared public-site header.
 * Loaded by every page that renders the TRAC JHS public shell
 * (index.php, auth/login.php, about.php, privacy.php, terms.php, contact.php).
 *
 * Self-sufficient: this partial pulls in everything it needs
 * (config + functions + auth helpers) so any page that requires
 * site_header.php is automatically safe to call is_logged_in(),
 * redirect(), and csrf_field().
 *
 * Caller may set:
 *   $page_title        — string, default 'TRAC JHS'
 *   $page_description  — string, default TRAC JHS landing description
 *   $active_nav        — one of '', 'about', 'programs', 'admissions',
 *                        'campus', 'contact', 'login'
 *   $body_class        — string added to <body class="…">
 *   $hide_nav_links    — bool (default false): hide the in-page anchor nav
 *                        (used by auth/login.php where the menu doesn't apply)
 *   $hide_header       — bool (default false): suppress the entire <header>
 *                        (used by auth/login.php where the brand lives inside
 *                        the card itself)
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../auth.php';

// Security headers — emitted from PHP because Apache's mod_headers isn't
// always loaded (e.g., Render's stock PHP image). The .htaccess also sets
// these for environments that DO have mod_headers — they are harmless to
// duplicate and Apache will override any duplicates with the latest value.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header_remove('X-Powered-By');

// Cache-Control for the public landing HTML. Static assets (.css/.js/images
// /fonts) get long-cache headers via .htaccess mod_expires / mod_headers in
// production; the PHP-built-in dev server ignores .htaccess so static-asset
// caching is a no-op locally (acceptable). Here we set a moderate cache for
// the public page itself — short enough that content updates show up quickly,
// long enough to absorb traffic spikes.
header('Cache-Control: public, max-age=3600, must-revalidate');

// Authentication routes must NEVER be cached, regardless of platform.
// The .htaccess rule covers Apache (mod_headers); this PHP fallback
// covers the PHP built-in dev server, Cloudflare's edge cache, and any
// other intermediary that doesn't honor per-file htaccess directives.
// no-store is stricter than no-cache — prevents storage entirely
// rather than just revalidation — and is the right call for pages
// that carry CSRF tokens or post-logout state.
$authPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
if (in_array($authPath, ['/auth/login.php', '/auth/logout.php'], true)) {
    header_remove('Cache-Control');
    header_remove('Pragma');
    header_remove('Expires');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$page_title        = $page_title        ?? 'TRAC JHS';
$page_description  = $page_description  ?? 'TRAC JHS, Junior High School of Tawi-Tawi Regional Agricultural College, Bongao, Tawi-Tawi.';
$active_nav        = $active_nav        ?? '';
$body_class        = $body_class        ?? '';
$hide_nav_links    = $hide_nav_links    ?? false;
$hide_header       = $hide_header       ?? false;
$is_authed         = is_logged_in();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($page_description) ?>">
    <title><?= e($page_title) ?> &middot; TRAC JHS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= e(url('/assets/css/tokens.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('/assets/css/landing.css')) ?>" rel="stylesheet">
    <?php if (strpos($body_class, 'login-body') !== false): ?><link href="<?= e(url('/assets/css/login.css')) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="<?= e($body_class) ?>">

<?php if (!$hide_header): ?>
<header class="site-header" role="banner">
    <div class="wrap site-header__row">
        <a class="brand" href="<?= e(url('/')) ?>">
            <img class="brand-mark" src="/assets/img/lanbaselogo.jpeg" alt="TRAC seal" width="38" height="38">
            <span class="brand-text">
                <strong>TRAC JHS</strong>
                <span>Bongao, Tawi-Tawi</span>
            </span>
        </a>

        <?php if (!$hide_nav_links): ?>
        <nav class="site-nav" aria-label="Public sections">
            <a class="site-nav__link<?= $active_nav==='about'      ?' is-active':'' ?>" href="<?= e(url('/')) ?>#about">About</a>
            <a class="site-nav__link<?= $active_nav==='programs'   ?' is-active':'' ?>" href="<?= e(url('/')) ?>#programs">Academics</a>
            <a class="site-nav__link<?= $active_nav==='admissions' ?' is-active':'' ?>" href="<?= e(url('/')) ?>#admissions">Admissions</a>
            <a class="site-nav__link<?= $active_nav==='campus'     ?' is-active':'' ?>" href="<?= e(url('/')) ?>#campus">Campus</a>
            <a class="site-nav__link<?= $active_nav==='contact'    ?' is-active':'' ?>" href="<?= e(url('/')) ?>#contact">Contact</a>
        </nav>
        <?php endif; ?>

        <div class="site-header__actions">
            <?php if ($is_authed): ?>
                <a class="btn-portal" href="<?= e(url('/dashboard.php')) ?>">Dashboard</a>
            <?php elseif (!$hide_nav_links): ?>
                <a class="btn-portal" href="<?= e(url('/auth/login.php')) ?>">Staff Sign In</a>
            <?php endif; ?>
            <?php if (!$hide_nav_links): ?>
            <button class="menu-btn" type="button" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-nav">
                <svg class="menu-btn-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="3" y1="6"  x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$hide_nav_links): ?>
    <nav class="mobile-nav" id="mobile-nav" aria-label="Public sections (mobile)" hidden>
        <a href="<?= e(url('/')) ?>#about">About</a>
        <a href="<?= e(url('/')) ?>#programs">Academics</a>
        <a href="<?= e(url('/')) ?>#admissions">Admissions</a>
        <a href="<?= e(url('/')) ?>#campus">Campus</a>
        <a href="<?= e(url('/')) ?>#contact">Contact</a>
        <?php if ($is_authed): ?>
            <a href="<?= e(url('/dashboard.php')) ?>">Dashboard</a>
        <?php else: ?>
            <a href="<?= e(url('/auth/login.php')) ?>">Staff Sign In</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</header>
<?php endif; ?>

<main id="main">