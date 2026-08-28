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
    <meta name="description" content="TRAC JHS Student Admission and Records Management System — an internal LAN portal for authorized staff in Bongao, Tawi-Tawi.">
    <title>TRAC JHS | Student Admission &amp; Records</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,650&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('/assets/css/landing.css')) ?>" rel="stylesheet">
</head>
<body class="landing-body">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <a class="wordmark" href="#top" aria-label="TRAC JHS home"><span>TRAC</span><b>JHS</b></a>
    <nav class="site-nav" id="primary-nav" data-mobile-nav aria-label="Primary navigation">
        <a href="#capabilities">Capabilities</a><a href="#architecture">How it works</a><a href="#workflow">Workflow</a>
        <a class="nav-login" href="#sign-in">Staff sign in <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
    </nav>
    <button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="primary-nav"><span class="sr-only">Toggle navigation</span><i class="bi bi-list" aria-hidden="true"></i></button>
</header>
<main id="main-content">
<section class="hero" id="top">
    <div class="hero__wash" aria-hidden="true"></div><div class="hero__grid" aria-hidden="true"></div>
    <div class="hero__content reveal">
        <p class="eyebrow"><span class="status-dot"></span> Internal LAN portal · Bongao, Tawi-Tawi</p>
        <h1>One clear record<br><em>for every learner.</em></h1>
        <p class="hero__lede">A focused admission and records workspace for Tawi-Tawi Regional Agricultural College Laboratory High School — built for authorized staff, close to the school workflow.</p>
        <div class="hero__actions"><a class="button button--gold" href="#sign-in">Enter staff portal <i class="bi bi-arrow-right" aria-hidden="true"></i></a><a class="text-link" href="#capabilities">Explore the system <i class="bi bi-arrow-down" aria-hidden="true"></i></a></div>
        <div class="hero__proof"><span>Grounded in the repository</span><span class="proof-line"></span><span>Admission · Records · Reports</span></div>
    </div>
    <div class="hero__card reveal reveal--delay" id="sign-in">
        <div class="card-kicker">Authorized access</div><h2>Staff sign in</h2><p>For Registrar and Data Encoder accounts only.</p>
        <?php $flash = get_flash(); if ($flash): ?><div class="login-alert" role="alert"><?= e($flash['message']) ?></div><?php elseif ($error !== ''): ?><div class="login-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('/index.php')) ?>" novalidate>
            <?= csrf_field() ?>
            <label for="username">Username</label><input type="text" id="username" name="username" autocomplete="username" required autofocus value="<?= e($username) ?>" placeholder="Enter your username">
            <label for="password">Password</label><div class="password-field"><input type="password" id="password" name="password" autocomplete="current-password" required placeholder="Enter your password"><button type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button></div>
            <button type="submit" class="button button--gold button--full btn-signin">Sign in <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        </form><p class="card-note"><i class="bi bi-shield-lock" aria-hidden="true"></i> Access is managed by your school administrator.</p>
    </div>
</section>
<section class="intro-section section-shell reveal"><p class="eyebrow eyebrow--dark">A practical foundation</p><h2>Designed around the work<br><em>that matters every day.</em></h2><p class="intro-copy">TRAC JHS SARMS brings admission encoding, learner records, search, reporting, transfer tracking, and registrar-controlled backups into one dependable internal workspace.</p></section>
<section class="capabilities section-shell" id="capabilities"><div class="section-heading reveal"><div><p class="eyebrow eyebrow--dark">System capabilities</p><h2>From first entry<br>to trusted record.</h2></div><p>Each capability reflects the modules and workflows documented in this application.</p></div><div class="capability-grid">
    <article class="capability-card reveal"><span class="card-index">01</span><i class="bi bi-person-plus" aria-hidden="true"></i><h3>Admission encoding</h3><p>Capture applicant and enrollment information in a structured intake flow.</p><a href="#workflow">See the flow <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></article>
    <article class="capability-card reveal"><span class="card-index">02</span><i class="bi bi-folder2-open" aria-hidden="true"></i><h3>Centralized records</h3><p>Keep learner profiles and academic records available to authorized school roles.</p><a href="#architecture">How access works <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></article>
    <article class="capability-card reveal"><span class="card-index">03</span><i class="bi bi-search" aria-hidden="true"></i><h3>Search &amp; reporting</h3><p>Find records efficiently and produce the operational reports staff need.</p><a href="#workflow">Follow a record <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></article>
    <article class="capability-card reveal"><span class="card-index">04</span><i class="bi bi-arrow-left-right" aria-hidden="true"></i><h3>Transfer &amp; LIS workflows</h3><p>Track transfers and support SF10-JHS and LIS CSV-related processes.</p><a href="#source">View evidence <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></article>
</div></section>
<section class="architecture section-shell" id="architecture"><div class="architecture__copy reveal"><p class="eyebrow">Source of truth</p><h2>Purposefully<br><em>close to the school.</em></h2><p>The system is designed as an internal LAN application, not a public student portal. That boundary keeps sensitive learner information within the school’s managed network.</p><a class="button button--outline" href="#source">Read the operating model <i class="bi bi-arrow-down" aria-hidden="true"></i></a></div><div class="architecture__panel reveal" data-tabs><div class="tab-list" role="tablist" aria-label="Operating model"><button role="tab" aria-selected="true" aria-controls="tab-lan" id="tab-button-lan" data-tab="lan">LAN-first</button><button role="tab" aria-selected="false" aria-controls="tab-roles" id="tab-button-roles" data-tab="roles">Role-based</button><button role="tab" aria-selected="false" aria-controls="tab-audit" id="tab-button-audit" data-tab="audit">Traceable</button></div><div class="tab-panel" id="tab-lan" role="tabpanel" aria-labelledby="tab-button-lan"><span class="panel-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span><h3>Three tiers, one managed network</h3><p>Presentation, application logic, and database services work together through the school’s LAN and star-topology infrastructure.</p></div><div class="tab-panel" id="tab-roles" role="tabpanel" aria-labelledby="tab-button-roles" hidden><span class="panel-icon"><i class="bi bi-people" aria-hidden="true"></i></span><h3>Access follows responsibility</h3><p>Role-based access distinguishes Registrar and Data Encoder work so staff see the tools appropriate to their responsibilities.</p></div><div class="tab-panel" id="tab-audit" role="tabpanel" aria-labelledby="tab-button-audit" hidden><span class="panel-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span><h3>Changes leave a trail</h3><p>Audit logging and registrar-controlled backups support accountability, continuity, and recovery of school records.</p></div><div class="panel-footer"><span class="pulse-ring"></span> No public data is exposed</div></div></section>
<section class="workflow section-shell" id="workflow"><div class="section-heading reveal"><div><p class="eyebrow eyebrow--dark">The record journey</p><h2>Order in every<br><em>important handoff.</em></h2></div><p>A simple view of the documented operational path, from intake to continuity.</p></div><div class="workflow-line"><article class="workflow-step reveal"><span>01</span><h3>Admit</h3><p>Encode applicant and enrollment details.</p></article><article class="workflow-step reveal"><span>02</span><h3>Organize</h3><p>Maintain learner profiles and academic records.</p></article><article class="workflow-step reveal"><span>03</span><h3>Retrieve</h3><p>Search, review, and generate reports.</p></article><article class="workflow-step reveal"><span>04</span><h3>Protect</h3><p>Track transfers, audit changes, and back up.</p></article></div></section>
<section class="source-note section-shell reveal" id="source"><div><p class="eyebrow eyebrow--dark">Evidence, not embellishment</p><h2>Built from what the<br>system actually does.</h2></div><p>This landing page uses the project README, existing module names, authentication behavior, and documented architecture as its source of truth. It makes no claims about public enrollment, live statistics, rankings, or internet access.</p><a class="button button--dark" href="#sign-in">Continue to staff sign in <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></section>
</main>
<footer class="site-footer"><div class="wordmark"><span>TRAC</span><b>JHS</b></div><p>Tawi-Tawi Regional Agricultural College · Laboratory High School<br><span>Bongao, Tawi-Tawi · BARMM · Internal LAN portal</span></p><a href="#top" aria-label="Back to top"><i class="bi bi-arrow-up" aria-hidden="true"></i> Back to top</a></footer>
<script src="<?= e(url('/assets/js/landing.js')) ?>" defer></script>
</body>
</html>
