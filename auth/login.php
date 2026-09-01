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

/* Detect "session expired" / "auth required" redirect targets.
   Other pages redirect here with ?reason=expired or ?reason=required
   when the user must re-authenticate. We surface a friendly notice
   instead of leaving the user wondering why they're here. */
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
        $error = 'Invalid credentials or inactive account.';
    }
}

$page_title        = 'Staff Sign In';
$page_description  = 'TRAC JHS staff sign-in — registrar and data encoder portal.';
$body_class        = 'login-body';
$hide_nav_links    = true;
$minimal_footer    = true;
$active_nav        = 'login';

require __DIR__ . '/../includes/site_header.php';

$flash = get_flash();
?>

<section class="login-shell">
    <div class="wrap" style="max-width:460px;">
        <div class="login-card">
            <div class="login-card__emblem" aria-hidden="true">
                <div class="login-card__emblem-rings">
                    <span class="ring ring--outer"></span>
                    <span class="ring ring--mid"></span>
                    <span class="ring ring--inner"></span>
                </div>
                <img class="login-card__emblem-img" src="/assets/img/lanbaselogo.jpeg" alt="" width="96" height="96" loading="eager" decoding="async" onerror="this.onerror=null;this.style.display='none';this.parentNode.classList.add('login-card__emblem--fallback');">
                <span class="login-card__emblem-fallback" aria-hidden="true">TRAC</span>
            </div>

            <h1 class="login-card__title">Staff Sign In</h1>
            <p class="login-card__hint">Authorized Registrar and Data Encoder accounts only.</p>

            <?php if ($flash): ?>
                <div class="login-alert" role="alert"><?= e($flash['message']) ?></div>
            <?php elseif ($notice !== ''): ?>
                <div class="login-alert login-alert--notice" role="status"><?= e($notice) ?></div>
            <?php elseif ($error !== ''): ?>
                <div class="login-alert login-alert--error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/auth/login.php')) ?>" data-login-form>
                <?= csrf_field() ?>

                <div class="field">
                    <label for="username">Username</label>
                    <div class="input-shell">
                        <input
                            type="text"
                            id="username"
                            name="username"
                            autocomplete="username"
                            required
                            autofocus
                            value="<?= e($username) ?>"
                            placeholder="Enter your username"
                        >
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-shell">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            placeholder="Enter your password"
                        >
                        <button
                            type="button"
                            class="input-shell__toggle"
                            data-password-toggle
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <span aria-hidden="true">&#128065;</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-signin">
                    <span class="btn-signin__label">Sign In</span>
                    <span class="btn-signin__sending" aria-hidden="true">Signing in&hellip;</span>
                </button>

                <p class="login-card__row">
                    <a class="login-card__help" href="mailto:registrar@tracjhs.edu.ph?subject=Password%20reset%20request">Forgot password?</a>
                </p>
            </form>

            <p class="login-card__footnote">
                <a href="<?= e(url('/')) ?>">&larr; Back to landing page</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/site_footer.php'; ?>