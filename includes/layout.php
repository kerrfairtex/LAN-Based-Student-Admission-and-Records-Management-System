<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

function render_header(string $title, string $active = ''): void
{
    $user = current_user();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar glass-panel">
            <div class="brand-block">
                <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
                <div>
                    <h1><?= e(APP_NAME) ?></h1>
                    <p><?= e(APP_SCHOOL) ?></p>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a class="nav-link <?= $active === 'admission' ? 'active' : '' ?>" href="/modules/admission/index.php"><i class="bi bi-person-plus"></i> Admission</a>
                <a class="nav-link <?= $active === 'enrollment' ? 'active' : '' ?>" href="/modules/enrollment/index.php"><i class="bi bi-people"></i> Enrollment</a>
                <a class="nav-link <?= $active === 'records' ? 'active' : '' ?>" href="/modules/records/index.php"><i class="bi bi-folder2-open"></i> Records</a>
                <a class="nav-link <?= $active === 'transfers' ? 'active' : '' ?>" href="/modules/transfers/index.php"><i class="bi bi-arrow-left-right"></i> Transfers</a>
                <a class="nav-link <?= $active === 'search' ? 'active' : '' ?>" href="/modules/search/index.php"><i class="bi bi-search"></i> Search & Inquiry</a>
                <a class="nav-link <?= $active === 'reports' ? 'active' : '' ?>" href="/modules/reports/index.php"><i class="bi bi-file-earmark-text"></i> Reporting</a>
                <?php if (is_registrar()): ?>
                    <a class="nav-link <?= $active === 'users' ? 'active' : '' ?>" href="/modules/admin/users.php"><i class="bi bi-person-gear"></i> Users</a>
                    <a class="nav-link <?= $active === 'settings' ? 'active' : '' ?>" href="/modules/admin/settings.php"><i class="bi bi-gear"></i> Settings</a>
                    <a class="nav-link <?= $active === 'audit' ? 'active' : '' ?>" href="/modules/admin/audit.php"><i class="bi bi-journal-text"></i> Audit Log</a>
                    <a class="nav-link <?= $active === 'backup' ? 'active' : '' ?>" href="/modules/admin/backup.php"><i class="bi bi-hdd-network"></i> Database Backup</a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <div class="user-chip">
                    <i class="bi bi-person-circle"></i>
                    <div>
                        <strong><?= e($user['full_name'] ?? '') ?></strong>
                        <span><?= e(ucfirst($user['role'] ?? '')) ?></span>
                    </div>
                </div>
                <a href="/auth/logout.php" class="btn btn-outline-light btn-sm w-100 mt-2"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
                <a href="/modules/account/password.php" class="btn btn-outline-light btn-sm w-100 mt-2"><i class="bi bi-key"></i> Change Password</a>
            </div>
        </aside>
        <main class="main-content">
            <header class="topbar glass-panel">
                <div>
                    <h2><?= e($title) ?></h2>
                    <p class="mb-0 text-muted">LAN-Based Student Admission and Records Management</p>
                </div>
                <div class="lan-badge"><i class="bi bi-shield-lock"></i> Intranet Only</div>
            </header>
            <section class="content-area">
    <?php
    $flash = get_flash();
    if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
    <?php endif;
}

function render_footer(): void
{
    ?>
            </section>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}
