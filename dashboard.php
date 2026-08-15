<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$stats = dashboard_stats();
$activeYear = active_school_year();

render_header('Dashboard', 'dashboard');
?>
<div class="stat-grid">
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">Active Students</p>
        <div class="stat-value"><?= (int) $stats['total_students'] ?></div>
    </div>
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">Pending Admissions</p>
        <div class="stat-value"><?= (int) $stats['pending_admissions'] ?></div>
    </div>
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">Enrolled (<?= e($stats['active_school_year']) ?>)</p>
        <div class="stat-value"><?= (int) $stats['enrolled_this_year'] ?></div>
    </div>
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">Unassigned Sections</p>
        <div class="stat-value"><?= (int) $stats['unassigned_sections'] ?></div>
    </div>
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">Overdue Transfers</p>
        <div class="stat-value <?= $stats['overdue_transfers'] > 0 ? 'text-danger' : '' ?>"><?= (int) $stats['overdue_transfers'] ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <a class="quick-action-card" href="<?= e(url('/modules/admission/create.php')) ?>">
        <div class="qa-icon qa-primary"><i class="bi bi-person-plus"></i></div>
        <span class="qa-label">New Admission</span>
    </a>
    <a class="quick-action-card" href="<?= e(url('/modules/transfers/create.php')) ?>">
        <div class="qa-icon qa-success"><i class="bi bi-arrow-left-right"></i></div>
        <span class="qa-label">Transfer</span>
    </a>
    <a class="quick-action-card" href="<?= e(url('/modules/search/index.php')) ?>">
        <div class="qa-icon qa-warning"><i class="bi bi-search"></i></div>
        <span class="qa-label">Search</span>
    </a>
    <a class="quick-action-card" href="<?= e(url('/modules/reports/index.php')) ?>">
        <div class="qa-icon qa-danger"><i class="bi bi-file-earmark-text"></i></div>
        <span class="qa-label">Reports</span>
    </a>
    <?php if (is_registrar()): ?>
        <a class="quick-action-card" href="<?= e(url('/modules/admin/backup.php')) ?>">
            <div class="qa-icon qa-success"><i class="bi bi-hdd-network"></i></div>
            <span class="qa-label">Backup</span>
        </a>
        <a class="quick-action-card" href="<?= e(url('/modules/admin/lis.php')) ?>">
            <div class="qa-icon qa-warning"><i class="bi bi-file-earmark-spreadsheet"></i></div>
            <span class="qa-label">LIS CSV</span>
        </a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="panel-card glass-panel">
            <h3>System Overview</h3>
            <p class="text-muted">
                This LAN-hosted system centralizes student admission and academic records for TRAC JHS.
                All data remains within the institutional intranet, ensuring physical sovereignty and
                uninterrupted access during internet outages.
            </p>
            <ul class="text-muted mb-0">
                <li>Three-tier architecture: Presentation, PHP Logic, and MySQL Data layers</li>
                <li>Role-based access for School Registrar and Data Encoders</li>
                <li>Real-time validation during admission encoding</li>
                <li>Registrar-controlled database backup exports</li>
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel-card glass-panel">
            <h3>Active School Year</h3>
            <p class="text-muted mb-1">
                <strong><?= e($activeYear['label'] ?? 'Not set') ?></strong>
            </p>
            <p class="text-muted small mb-3">
                Changed from the top bar. Takes effect across all modules.
            </p>
            <div class="d-grid">
                <a href="<?= e(url('/modules/admin/settings.php')) ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-gear"></i> Manage in Settings
                </a>
            </div>
        </div>
    </div>
</div>
<?php
render_footer();