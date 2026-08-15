<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_registrar();

$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// Count total
$totalStmt = db()->query('SELECT COUNT(*) AS c FROM audit_logs');
$total = (int) $totalStmt->fetch()['c'];

$paginated = paginate($total, $perPage, $currentPage);

$stmt = db()->prepare(
    'SELECT a.*, u.full_name, u.username
     FROM audit_logs a
     JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->execute(['limit' => $paginated['per_page'], 'offset' => $paginated['offset']]);
$logs = $stmt->fetchAll();

render_header('Audit Log', 'audit');
?>
<p class="text-muted">Tracks sensitive actions for institutional accountability (Data Privacy Act compliance).</p>

<div class="table-card glass-panel">
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="6" class="text-muted">No audit log entries yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['created_at']) ?></td>
                        <td><?= e($log['full_name']) ?></td>
                        <td><?= e($log['action']) ?></td>
                        <td><?= e($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></td>
                        <td><?= e($log['details'] ?? '') ?></td>
                        <td><?= e($log['ip_address'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginated['last_page'] > 1): ?>
        <div class="p-3">
            <?= render_pager($paginated['current_page'], $paginated['last_page'], url('/modules/admin/audit.php')) ?>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer();