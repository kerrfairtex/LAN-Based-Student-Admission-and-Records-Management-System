<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

function render_header(string $title, string $active = ''): void
{
    $user = current_user();
    $activeYear = active_school_year();
    $schoolYears = fetch_all_school_years();
    $notifications = fetch_notifications();
    $overdueCount = count($notifications['overdue']);

    // App-shell HTML contains session-dependent PII and audit-relevant
    // data — never let a downstream cache serve a stale copy. The
    // .htaccess mod_headers block also emits this for environments that
    // do not load mod_headers; harmless if duplicated.
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $sidebarGroups = [
        [
            'label' => '',
            'items' => [
                ['label' => 'Dashboard', 'href' => '/dashboard.php', 'active' => $active === 'dashboard', 'icon' => 'bi-speedometer2'],
            ],
        ],
        [
            'label' => 'Admissions',
            'items' => [
                ['label' => 'Admissions',    'href' => '/modules/admission/index.php',   'active' => $active === 'admission',    'icon' => 'bi-person-plus'],
                ['label' => 'Students',      'href' => '/modules/records/index.php',     'active' => $active === 'records',      'icon' => 'bi-people'],
                ['label' => 'Enrollments',   'href' => '/modules/enrollment/index.php',  'active' => $active === 'enrollment',   'icon' => 'bi-person-check'],
                ['label' => 'Records',       'href' => '/modules/records/academic.php',  'active' => $active === 'academic',     'icon' => 'bi-journal'],
            ],
        ],
        [
            'label' => 'Reports',
            'items' => [
                ['label' => 'Reports',   'href' => '/modules/reports/index.php',  'active' => $active === 'reports',  'icon' => 'bi-file-earmark-text'],
                ['label' => 'Transfers', 'href' => '/modules/transfers/index.php', 'active' => $active === 'transfers', 'icon' => 'bi-arrow-left-right'],
                ['label' => 'Search',    'href' => '/modules/search/index.php',   'active' => $active === 'search',   'icon' => 'bi-search'],
            ],
        ],
    ];

    if (is_registrar()) {
        $sidebarGroups[] = [
            'label' => 'Administration',
            'items' => [
                ['label' => 'Users',  'href' => '/modules/admin/users.php',     'active' => $active === 'users',     'icon' => 'bi-person-gear'],
                ['label' => 'Settings','href' => '/modules/admin/settings.php',  'active' => $active === 'settings',  'icon' => 'bi-gear'],
                ['label' => 'Audit',   'href' => '/modules/admin/audit.php',     'active' => $active === 'audit',     'icon' => 'bi-journal-text'],
                ['label' => 'LIS',     'href' => '/modules/admin/lis.php',       'active' => $active === 'lis',       'icon' => 'bi-file-earmark-spreadsheet'],
                ['label' => 'Backup',  'href' => '/modules/admin/backup.php',    'active' => $active === 'backup',    'icon' => 'bi-hdd-network'],
                ['label' => 'Restore', 'href' => '/modules/admin/restore.php',   'active' => $active === 'restore',   'icon' => 'bi-arrow-counterclockwise'],
            ],
        ];
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="<?= e(url('/assets/css/tokens.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('/assets/css/app-shell.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('/assets/css/dashboard.css')) ?>" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- tokens.css is the shared design-system source of truth (dark-green
         palette, gold accents, Fraunces serif, spacing/typography tokens).
         It was already loaded by includes/partials/header.php for the public
         landing/login/legal pages, but the authenticated layout below never
         referenced it. Result: dashboard/records/admissions etc. rendered
         with only Bootstrap and zero project styling — Bootstrap grid
         worked, but cards, panels, and the app shell had no design applied.

         Loading tokens.css here is a wiring fix, not a design decision:
         the tokens already exist and are the canonical source for this
         app's visual language. Page-specific selectors (.stat-card,
         .glass-panel, .app-shell, .avatar-sm, etc.) live in the page
         markup's inline classes — they need a dedicated layout stylesheet
         to actually paint, which is a separate, deliberate design task.
    -->
    <!-- LAN-hosted — no external analytics -->
</head>
<body>
    <div class="app-shell">

        <!-- Mobile hamburger overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="brand-block">
                    <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
                    <div>
                        <h1><?= e(APP_NAME) ?></h1>
                        <p><?= e(APP_SCHOOL) ?><br><?= e(APP_LOCATION) ?> · <?= e(APP_REGION) ?></p>
                    </div>
                </div>
                <button class="sidebar-close" id="sidebarClose" aria-label="Close menu">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <?php foreach ($sidebarGroups as $group): ?>
                    <?php if ($group['label']): ?>
                        <div class="sidebar-group-label"><?= e($group['label']) ?></div>
                    <?php endif; ?>
                    <?php foreach ($group['items'] as $item): ?>
                        <a class="nav-link <?= $item['active'] ? 'active' : '' ?>"
                           href="<?= e(url($item['href'])) ?>">
                            <i class="bi bi-<?= e($item['icon']) ?>"></i>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="user-chip">
                    <i class="bi bi-person-circle"></i>
                    <div>
                        <strong><?= e($user['full_name'] ?? '') ?></strong>
                        <span><?= e(ucfirst($user['role'] ?? '')) ?></span>
                    </div>
                </div>
                <a href="<?= e(url('/modules/account/password.php')) ?>" class="btn btn-outline-light btn-sm w-100 mt-2">
                    <i class="bi bi-key"></i> Change Password
                </a>
                <a href="<?= e(url('/auth/logout.php')) ?>" class="btn btn-outline-light btn-sm w-100 mt-2">
                    <i class="bi bi-box-arrow-right"></i> Sign Out
                </a>
            </div>
        </aside>

        <!-- Main content -->
        <main class="main-content">
            <!-- Top bar -->
            <header class="topbar glass-panel">
                <div class="topbar-left">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <h2><?= e($title) ?></h2>
                </div>

                <div class="topbar-right">
                    <!-- Academic Year Selector -->
                    <div class="dropdown">
                        <a href="#" class="topbar-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-calendar-range"></i>
                            <span class="d-none d-sm-inline">
                                <?= e($activeYear['label'] ?? 'No year set') ?>
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <?php foreach ($schoolYears as $year): ?>
                                <form method="post" action="<?= e(url('/modules/admin/set_year.php')) ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="year_id" value="<?= (int) $year['id'] ?>">
                                    <button type="submit"
                                            class="dropdown-item w-100 text-start <?= ($activeYear['id'] ?? 0) === (int) $year['id'] ? 'active' : '' ?>">
                                        <?= e($year['label']) ?>
                                        <?php if ($year['is_active']): ?>
                                            <span class="badge bg-primary ms-1">Active</span>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="dropdown">
                        <a href="#" class="topbar-link dropdown-toggle position-relative" data-bs-toggle="dropdown" aria-expanded="false"
                           id="notificationToggle">
                            <i class="bi bi-bell"></i>
                            <?php if ($overdueCount > 0): ?>
                                <span class="notification-dot"><?= $overdueCount ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <div class="notification-header">
                                <h6>Notifications</h6>
                                <span class="text-muted small"><?= $overdueCount ?> overdue</span>
                            </div>

                            <?php if ($overdueCount > 0): ?>
                                <div class="notification-section">
                                    <div class="notification-section-title">
                                        <i class="bi bi-exclamation-triangle text-danger"></i>
                                        Overdue Transfers
                                    </div>
                                    <?php foreach ($notifications['overdue'] as $t): ?>
                                        <a href="<?= e(url('/modules/transfers/view.php?id=' . (int) $t['id'])) ?>"
                                           class="notification-item">
                                            <div class="notification-icon bg-danger-transparent">
                                                <i class="bi bi-arrow-left-right text-danger"></i>
                                            </div>
                                            <div class="notification-content">
                                                <strong><?= e($t['student_id_no']) ?></strong>
                                                <span><?= e($t['first_name'] . ' ' . $t['last_name']) ?></span>
                                                <small>Due: <?= e($t['due_date']) ?> · <?= e($t['counterpart_school']) ?></small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($notifications['recent'])): ?>
                                <div class="notification-section">
                                    <div class="notification-section-title">
                                        <i class="bi bi-check-circle text-success"></i>
                                        Recent Approvals
                                    </div>
                                    <?php foreach ($notifications['recent'] as $a): ?>
                                        <a href="<?= e(url('/modules/admission/view.php?id=' . (int) $a['id'])) ?>"
                                           class="notification-item">
                                            <div class="notification-icon bg-success-transparent">
                                                <i class="bi bi-check text-success"></i>
                                            </div>
                                            <div class="notification-content">
                                                <strong><?= e($a['application_no']) ?></strong>
                                                <span><?= e($a['first_name'] . ' ' . $a['last_name']) ?></span>
                                                <small>Approved by <?= e($a['reviewer'] ?? 'system') ?></small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($overdueCount === 0 && empty($notifications['recent'])): ?>
                                <div class="notification-empty">
                                    <i class="bi bi-bell-slash"></i>
                                    <p>No notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- User avatar -->
                    <div class="dropdown">
                        <a href="#" class="topbar-avatar dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-sm">
                                <?php
                                $name = $user['full_name'] ?? 'U';
                                $initials = strtoupper(substr($name, 0, 1));
                                if (strpos($name, ' ') !== false) {
                                    $parts = explode(' ', $name);
                                    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
                                }
                                ?>
                                <?= e($initials) ?>
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="dropdown-item-text">
                                <strong><?= e($user['full_name'] ?? '') ?></strong>
                                <span class="text-muted"><?= e(ucfirst($user['role'] ?? '')) ?></span>
                            </div>
                            <a href="<?= e(url('/modules/account/password.php')) ?>" class="dropdown-item">
                                <i class="bi bi-key"></i> Change Password
                            </a>
                            <?php if (is_registrar()): ?>
                                <a href="<?= e(url('/modules/admin/settings.php')) ?>" class="dropdown-item">
                                    <i class="bi bi-gear"></i> Settings
                                </a>
                            <?php endif; ?>
                            <a href="<?= e(url('/auth/logout.php')) ?>" class="dropdown-item">
                                <i class="bi bi-box-arrow-right"></i> Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- LAN badge -->
            <div class="lan-badge-row">
                <span class="lan-badge"><i class="bi bi-shield-lock"></i> Internet Access Only</span>
            </div>

            <!-- Flash messages -->
            <?php
            $flash = get_flash();
            if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif;
            ?>
            <section class="content-area">
    <?php
}

function render_footer(): void
{
    ?>
            </section>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>
    <script>
        // Mobile sidebar toggle
        (function () {
            const overlay = document.getElementById('mobileOverlay');
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('mobileMenuBtn');
            const closeBtn = document.getElementById('sidebarClose');

            function openSidebar() {
                sidebar.classList.add('open');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            if (menuBtn) menuBtn.addEventListener('click', openSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
            if (overlay) overlay.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeSidebar();
            });
        })();
    </script>
</body>
</html>
    <?php
}