<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$stats = dashboard_stats();

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
            <h3>Quick Actions</h3>
            <div class="d-grid gap-2">
                <a class="btn btn-primary" href="<?= e(url('/modules/admission/create.php')) ?>"><i class="bi bi-person-plus"></i> New Admission</a>
                <a class="btn btn-outline-light" href="<?= e(url('/modules/search/index.php')) ?>"><i class="bi bi-search"></i> Search Student</a>
                <a class="btn btn-outline-light" href="<?= e(url('/modules/reports/index.php')) ?>"><i class="bi bi-file-earmark-text"></i> Generate Report</a>
            </div>
        </div>
    </div>
</div>
<?php
render_footer();
