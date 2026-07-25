<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    <meta name="description" content="TRAC JHS Student Admission and Records Management System — Bongao, Tawi-Tawi.">
    <title>TRAC JHS | Student Admission &amp; Records</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,650&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/landing.css" rel="stylesheet">
</head>
<body class="landing-body">
    <div class="landing">
        <div class="landing__media" aria-hidden="true">
            <img
                src="/assets/img/hero-trac-jhs.jpg"
                alt=""
                width="1920"
                height="1080"
                fetchpriority="high"
            >
            <div class="landing__scrim"></div>
            <div class="landing__glow"></div>
        </div>

        <main class="landing__content">
            <section class="landing__intro">
                <div class="brand-mark">
                    <div class="brand-mark__seal" aria-hidden="true">TRAC<br>JHS</div>
                    <div class="brand-mark__text">
                        <span class="brand-mark__name">TRAC JHS</span>
                        <span class="brand-mark__place">Bongao, Tawi-Tawi</span>
                    </div>
                </div>

                <h1 class="landing__headline">Student Admission &amp; Records</h1>
                <p class="landing__support">
                    Centralized Junior High enrollment records for Tawi-Tawi Regional Agricultural College — secure on the school LAN.
                </p>
            </section>

            <section class="login-panel" aria-labelledby="signin-title">
                <h2 class="login-panel__title" id="signin-title">Staff Sign In</h2>
                <p class="login-panel__hint">Authorized Registrar and Data Encoder accounts only.</p>

                <?php if ($error !== ''): ?>
                    <div class="login-alert" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="/index.php" novalidate>
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

        <footer class="landing__footer">
            <span>
                <strong>Laboratory High School</strong>
                · Tawi-Tawi Regional Agricultural College
            </span>
            <span>Intranet access only · Authorized personnel</span>
        </footer>
    </div>

    <script src="/assets/js/landing.js" defer></script>
</body>
</html>
