<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$stats = dashboard_stats();
$activeYear = active_school_year();

// Recent activity feed (audit_logs, latest 10 across the system).
$recentActivity = db()->query(
    'SELECT a.action, a.entity_type, a.entity_id, a.details, a.created_at,
            u.full_name AS user_name, u.username
     FROM audit_logs a
     JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 10'
)->fetchAll();

render_header('Dashboard', 'dashboard');
?>
<div class="stat-grid">
    <div class="stat-card glass-panel">
        <div class="stat-icon stat-icon-gold"><i class="bi bi-person-check-fill"></i></div>
        <p class="mb-1 text-muted">Active Students</p>
        <div class="stat-value" data-count="<?= (int) $stats['total_students'] ?>">0</div>
    </div>
    <div class="stat-card glass-panel">
        <div class="stat-icon stat-icon-bronze"><i class="bi bi-hourglass-split"></i></div>
        <p class="mb-1 text-muted">Pending Admissions</p>
        <div class="stat-value" data-count="<?= (int) $stats['pending_admissions'] ?>">0</div>
    </div>
    <div class="stat-card glass-panel">
        <div class="stat-icon stat-icon-green"><i class="bi bi-mortarboard-fill"></i></div>
        <p class="mb-1 text-muted">Enrolled (<?= e($stats['active_school_year']) ?>)</p>
        <div class="stat-value" data-count="<?= (int) $stats['enrolled_this_year'] ?>">0</div>
    </div>
    <div class="stat-card glass-panel">
        <div class="stat-icon stat-icon-muted"><i class="bi bi-diagram-3-fill"></i></div>
        <p class="mb-1 text-muted">Unassigned Sections</p>
        <div class="stat-value" data-count="<?= (int) $stats['unassigned_sections'] ?>">0</div>
    </div>
    <div class="stat-card glass-panel">
        <div class="stat-icon <?= $stats['overdue_transfers'] > 0 ? 'stat-icon-danger' : 'stat-icon-muted' ?>"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <p class="mb-1 text-muted">Overdue Transfers</p>
        <div class="stat-value <?= $stats['overdue_transfers'] > 0 ? 'text-danger' : '' ?>" data-count="<?= (int) $stats['overdue_transfers'] ?>">0</div>
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
                This Internet-hosted system centralizes student admission and academic records for TRAC JHS.
                All data lives in a managed PostgreSQL instance secured for institutional access only,
                ensuring physical sovereignty and uninterrupted availability.
            </p>
            <ul class="text-muted mb-0">
                <li>Three-tier architecture: Presentation, PHP Logic, and PostgreSQL data layers</li>
                <li>Role-based access for School Registrar and Data Encoders</li>
                <li>Real-time validation during admission encoding</li>
                <li>Registrar-controlled database backup exports (logical dump + pg_dump-compatible)</li>
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

    <?php if (is_registrar()): ?>
        <div class="col-12">
            <div class="panel-card glass-panel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 class="mb-0">Recent Activity</h3>
                    <a href="<?= e(url('/modules/admin/audit.php')) ?>" class="btn btn-sm btn-outline-light">View full audit log</a>
                </div>
                <?php if (!$recentActivity): ?>
                    <p class="text-muted mb-0">No activity yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Entity</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $a): ?>
                                    <tr>
                                        <td><small><?= e($a['created_at']) ?></small></td>
                                        <td><?= e($a['user_name']) ?></td>
                                        <td><?= e($a['action']) ?></td>
                                        <td><?= e($a['entity_type']) ?><?= $a['entity_id'] !== null ? ' #' . (int) $a['entity_id'] : '' ?></td>
                                        <td><small><?= e($a['details'] ?? '') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer();
?>
<script src="<?= e(url('/assets/js/dashboard.js')) ?>"></script>
