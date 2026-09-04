<?php

declare(strict_types=1);

/**
 * TRAC JHS staff sign-in.
 *
 * The only place in the app that renders a login form.
 * Receives CSRF-protected POST with username + password,
 * calls attempt_login(), redirects to /dashboard.php on success.
 *
 * After successful sign-in the user is redirected to /dashboard.php.
 * The "Staff Sign In" link in the public header points here.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error    = '';
$username = '';
$reason   = isset($_GET['reason']) ? (string) $_GET['reason'] : '';

/* Detect "session expired" / "auth required" redirect targets. */
$notice = '';
if ($reason === 'expired') {
    $notice = 'Your session has expired. Please sign in again to continue.';
} elseif ($reason === 'required') {
    $notice = 'Please sign in to access that page.';
} elseif ($reason === 'logout') {
    $notice = 'You have been signed out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (attempt_login($username, $password)) {
        redirect('/dashboard.php');
    } else {
        // attempt_login() returns false for both bad-credentials and
        // throttled. Distinguish them by re-running the gate: if the gate
        // trips, the caller was rate-limited; otherwise it was a normal
        // bad-credentials attempt. Avoids leaking 'account locked' detail.
        if (function_exists('login_rate_check') && login_rate_check($username) !== 'ok') {
            http_response_code(429);
            $error = 'Too many sign-in attempts. Please wait 15 minutes and try again, or contact the School Registrar.';
        } else {
            $error = 'Invalid credentials or inactive account.';
        }
    }
}

$page_title        = 'Staff Sign In';
$page_description  = 'TRAC JHS staff sign-in, registrar and data encoder portal.';
$body_class        = 'login-body login-terminal';
$hide_nav_links    = true;
$hide_header       = true;          /* the brand moves into the card itself */
$minimal_footer    = true;
$card_legal        = true;          /* render copyright + legal + dev credit inside the card */
$active_nav        = 'login';

require __DIR__ . '/../includes/site_header.php';

$flash = get_flash();
?>

<!-- Background atmosphere: grid + glow orbs, behind the card.
     The atmosphere is fixed-positioned so it always covers the viewport
     regardless of body/main height. The .login-terminal class is on
     <body> (set via $body_class), not on a nested <main> — the
     header partial already opens <main id="main">, and footer.php closes
     it, so this page must NOT add another <main> wrapper. -->
<div class="login-terminal__atmosphere" aria-hidden="true">
    <span class="orb orb--gold"></span>
    <span class="orb orb--green"></span>
    <span class="grid-overlay"></span>
</div>

<section class="login-card" aria-labelledby="login-title">
    <!-- Circuit-trace animated border (SVG mask technique) -->
    <div class="login-card__trace" aria-hidden="true">
        <svg viewBox="0 0 100 100" preserveAspectRatio="none">
            <rect x="0.5" y="0.5" width="99" height="99" rx="2.4" ry="2.4"
                  fill="none" stroke="url(#traceGrad)" stroke-width="1"
                  pathLength="100" class="trace-rect"/>
            <defs>
                <linearGradient id="traceGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%"   stop-color="#D4A72C" stop-opacity="0.10"/>
                    <stop offset="40%"  stop-color="#E3BE52" stop-opacity="0.95"/>
                    <stop offset="60%"  stop-color="#D4A72C" stop-opacity="0.95"/>
                    <stop offset="100%" stop-color="#D4A72C" stop-opacity="0.10"/>
                </linearGradient>
            </defs>
        </svg>
    </div>

    <!-- Top: authentication node emblem -->
    <div class="login-card__node" data-stagger="1" aria-hidden="true">
        <div class="node-glow"></div>
        <div class="node-rings">
            <span class="node-ring node-ring--outer"></span>
            <span class="node-ring node-ring--mid"></span>
            <span class="node-ring node-ring--inner"></span>
        </div>
        <div class="node-seal">
            <img class="node-seal__img"
                 src="/assets/img/trac-jhs-seal.jpeg" alt=""
                 width="84" height="84" loading="eager" decoding="async"
                 onerror="this.onerror=null;this.style.display='none';this.parentNode.classList.add('node-seal--fallback');">
            <span class="node-seal__fallback" aria-hidden="true">TRAC</span>
        </div>
        <span class="node-status" aria-hidden="true">
            <span class="node-status__dot"></span>
            <span class="node-status__label">SECURE NODE</span>
        </span>
    </div>

    <!-- Heading block -->
    <div class="login-card__heading" data-stagger="2">
        <h1 class="login-card__title" id="login-title">Staff Sign In</h1>
        <p class="login-card__hint">Authorized Registrar and Data Encoder accounts only.</p>
    </div>

    <!-- Alerts -->
    <?php if ($flash): ?>
        <div class="login-alert" data-stagger="2" role="alert"><?= e($flash['message']) ?></div>
    <?php elseif ($notice !== ''): ?>
        <div class="login-alert login-alert--notice" data-stagger="2" role="status"><?= e($notice) ?></div>
    <?php elseif ($error !== ''): ?>
        <div class="login-alert login-alert--error" data-stagger="2" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Form -->
    <form class="login-form" method="post" action="<?= e(url('/auth/login.php')) ?>" data-login-form data-stagger="3" autocomplete="on">
        <?= csrf_field() ?>

        <!-- Username -->
        <div class="field field--floating">
            <span class="field__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>
                </svg>
            </span>
            <input
                type="text"
                id="username"
                name="username"
                class="field__input"
                autocomplete="username"
                required
                autofocus
                value="<?= e($username) ?>"
                placeholder=" "
            >
            <label for="username" class="field__label">Username</label>
        </div>

        <!-- Password -->
        <div class="field field--floating">
            <span class="field__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="11" width="16" height="10" rx="2"/>
                    <path d="M8 11V7a4 4 0 018 0v4"/>
                </svg>
            </span>
            <input
                type="password"
                id="password"
                name="password"
                class="field__input"
                autocomplete="current-password"
                required
                placeholder=" "
            >
            <label for="password" class="field__label">Password</label>

            <!-- Eye / Eye-slash morph -->
            <button
                type="button"
                class="field__toggle"
                data-password-toggle
                aria-label="Show password"
                aria-pressed="false"
            >
                <svg class="eye eye--closed" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg class="eye eye--open" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                    <circle cx="12" cy="12" r="3"/>
                    <line x1="3" y1="3" x2="21" y2="21"/>
                </svg>
            </button>
        </div>

        <!-- Sign In -->
        <button type="submit" class="btn-signin" data-stagger="4">
            <span class="btn-signin__label">Sign In</span>
            <span class="btn-signin__sending" aria-hidden="true">
                <span class="btn-signin__spinner" aria-hidden="true"></span>
                <span>Signing in&hellip;</span>
            </span>
            <span class="btn-signin__shine" aria-hidden="true"></span>
            <span class="btn-signin__ripple" aria-hidden="true"></span>
        </button>
    </form>

    <!-- Secondary links -->
    <div class="login-card__links" data-stagger="5">
        <a class="link-underline" href="mailto:registrar@tracjhs.edu.ph?subject=Password%20reset%20request">Forgot password?</a>
        <span class="link-sep" aria-hidden="true">·</span>
        <a class="link-underline" href="<?= e(url('/')) ?>">&larr; Back to landing page</a>
    </div>

    <!-- In-card legal strip: copyright, legal links, system name, dev credits -->
    <div class="login-card__legal" aria-label="Site information">
        <p class="login-card__legal-copy">&copy; <?= date('Y') ?> TRAC Junior High School. Laboratory school of Tawi-Tawi Regional Agricultural College.</p>
        <nav class="login-card__legal-links" aria-label="Legal">
            <a class="link-underline" href="<?= e(url('/privacy.php')) ?>">Privacy</a>
            <span class="link-sep" aria-hidden="true">|</span>
            <a class="link-underline" href="<?= e(url('/terms.php')) ?>">Terms</a>
            <span class="link-sep" aria-hidden="true">|</span>
            <a class="link-underline" href="<?= e(url('/about.php')) ?>">About</a>
        </nav>
        <p class="login-card__legal-system">TRAC JHS Student Admission and Records Management System</p>
        <p class="login-card__legal-devs">
            <span class="login-card__legal-devs-label">System Development Team</span>
            <span class="login-card__legal-devs-names">
                <span>Michael S. Giagales</span>
                <span class="login-card__legal-devs-sep" aria-hidden="true">|</span>
                <span>Omarkhan G. Sahisa</span>
                <span class="login-card__legal-devs-sep" aria-hidden="true">|</span>
                <span>Jeriko A. Binong</span>
                <span class="login-card__legal-devs-sep" aria-hidden="true">|</span>
                <span>Abumharwan Sabbaha</span>
            </span>
        </p>
    </div>
</section>

<?php require __DIR__ . '/../includes/site_footer.php'; ?>
