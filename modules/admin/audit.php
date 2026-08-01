<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_registrar();

$logs = db()->query(
    'SELECT a.*, u.full_name, u.username
     FROM audit_logs a
     JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 200'
)->fetchAll();

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
</div>
<?php
render_footer();
