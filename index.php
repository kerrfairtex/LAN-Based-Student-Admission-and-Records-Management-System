<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (attempt_login($username, $password)) {
        redirect('/dashboard.php');
    } else {
        $error = 'Invalid credentials or inactive account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TRAC JHS Student Admission and Records Management System — Bongao, Tawi-Tawi, BARMM.">
    <title>TRAC JHS | Student Admission &amp; Records</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,650&family=Great+Vibes&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('/assets/css/landing.css')) ?>" rel="stylesheet">
    <!-- LAN-hosted — no external analytics -->
</head>
<body class="landing-body">
    <div class="landing">
        <div class="landing__media" aria-hidden="true">
            <img
                src="<?= e(url('/assets/img/hero-trac-jhs.jpg')) ?>"
                alt=""
                width="1920"
                height="1080"
                fetchpriority="high"
            >
            <div class="landing__scrim"></div>
        </div>
        <div class="landing__corner" aria-hidden="true"></div>

        <main class="landing__content">
            <section class="landing__intro">
                <div class="brand-mark">
                    <div class="brand-mark__seal" aria-hidden="true">TRAC<br>JHS</div>
                    <div class="brand-mark__text">
                        <span class="brand-mark__name">TRAC JHS</span>
                        <span class="brand-mark__place">Bongao, Tawi-Tawi</span>
                    </div>
                </div>

                <p class="brand-mark__affiliation">Bangsamoro · Basic Education · Laboratory High School</p>

                <h1 class="landing__headline">Student Admission &amp; Records</h1>
                <p class="landing__support">
                    Junior High enrollment records for Tawi-Tawi Regional Agricultural College.
                </p>
            </section>

            <section class="login-panel" aria-labelledby="signin-title">
                <h2 class="login-panel__title" id="signin-title">Staff Sign In</h2>
                <p class="login-panel__hint">Authorized Registrar and Data Encoder accounts only.</p>

                <?php
                $flash = get_flash();
                if ($flash): ?>
                    <div class="login-alert" role="alert"><?= e($flash['message']) ?></div>
                <?php elseif ($error !== ''): ?>
                    <div class="login-alert" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/index.php')) ?>" novalidate>
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
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-signin">Sign In</button>
                </form>
            </section>
        </main>

        <section class="landing__banner" aria-label="Institutional message">
            <div class="landing__banner-inner">
                <div class="landing__seals" aria-hidden="true">
                    <div class="seal" title="Bangsamoro">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3.2l1.2 3.7h3.9l-3.1 2.3 1.2 3.7L12 10.6 8.8 12.9l1.2-3.7-3.1-2.3h3.9L12 3.2z"/>
                            <path d="M7.2 16.2c2.1-1.8 7.5-1.8 9.6 0-.8 2.4-2.9 3.8-4.8 3.8s-4-1.4-4.8-3.8z" opacity=".85"/>
                        </svg>
                    </div>
                    <div class="seal" title="Education">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 4l9 4.5-9 4.5L3 8.5 12 4zm-7 8.2v3.3c0 .9 3.1 2.7 7 2.7s7-1.8 7-2.7v-3.3l-7 3.5-7-3.5z"/>
                        </svg>
                    </div>
                </div>
                <p class="landing__slogan">Empowering the Bangsamoro through quality junior high education</p>
            </div>
        </section>

        <footer class="landing__footer">
            <div class="landing__footer-inner">
                <span>
                    <strong>Laboratory High School</strong>
                    · Tawi-Tawi Regional Agricultural College
                </span>
                <div class="landing__contacts">
                    <span><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Bongao, Tawi-Tawi · BARMM</span>
                    <span><i class="bi bi-globe" aria-hidden="true"></i> Internet Access Only</span>
                </div>
            </div>
        </footer>
    </div>

    <script src="<?= e(url('/assets/js/landing.js')) ?>" defer></script>
</body>
</html>
