<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/transfers.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT t.*, s.student_id_no, s.lrn,
            CONCAT(s.last_name, ", ", s.first_name) AS student_name,
            u.full_name AS created_by_name
     FROM transfer_requests t
     JOIN students s ON s.id = t.student_id
     JOIN users u ON u.id = t.created_by
     WHERE t.id = :id'
);
$stmt->execute(['id' => $id]);
$transfer = $stmt->fetch();

if (!$transfer) {
    flash('danger', 'Transfer request not found.');
    redirect('/modules/transfers/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $statusMap = [
        'mark_sent' => 'documents_sent',
        'mark_received' => 'documents_received',
        'complete' => 'completed',
        'escalate' => 'escalated',
    ];

    if (isset($statusMap[$action])) {
        $newStatus = $statusMap[$action];
        $completedAt = $newStatus === 'completed' ? date('Y-m-d H:i:s') : null;
        $escalatedAt = $newStatus === 'escalated' ? date('Y-m-d H:i:s') : null;

        $update = db()->prepare(
            'UPDATE transfer_requests
             SET status = :status, notes = :notes, updated_by = :updated_by,
                 completed_at = COALESCE(:completed_at, completed_at),
                 escalated_at = COALESCE(:escalated_at, escalated_at)
             WHERE id = :id'
        );
        $update->execute([
            'status' => $newStatus,
            'notes' => $notes ?: $transfer['notes'],
            'updated_by' => (int) $_SESSION['user']['id'],
            'completed_at' => $completedAt,
            'escalated_at' => $escalatedAt,
            'id' => $id,
        ]);

        if ($newStatus === 'completed' && $transfer['direction'] === 'outgoing') {
            db()->prepare("UPDATE students SET status = 'transferred' WHERE id = :id")
                ->execute(['id' => (int) $transfer['student_id']]);
        }

        audit_log('update', 'transfer_requests', $id, "Status changed to {$newStatus}");
        flash('success', 'Transfer request updated.');
        redirect('/modules/transfers/view.php?id=' . $id);
    }
}

$overdue = transfer_is_overdue($transfer);
$daysLeft = transfer_days_remaining($transfer);

render_header('Transfer Request', 'transfers');
?>
<div class="panel-card glass-panel mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><?= e(ucfirst($transfer['direction'])) ?> Transfer</h3>
            <p class="text-muted mb-0"><?= e($transfer['student_name']) ?> (<?= e($transfer['student_id_no']) ?>)</p>
        </div>
        <?php if ($overdue && $transfer['status'] !== 'completed'): ?>
            <span class="badge badge-status-rejected">SLA Overdue</span>
        <?php elseif ($transfer['status'] !== 'completed'): ?>
            <span class="badge badge-status-pending"><?= $daysLeft ?> days remaining</span>
        <?php else: ?>
            <span class="badge badge-status-approved">Completed</span>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="panel-card glass-panel">
            <h3>Request Details</h3>
            <p><strong>Counterpart School:</strong> <?= e($transfer['counterpart_school']) ?></p>
            <p><strong>Request Date:</strong> <?= e($transfer['request_date']) ?></p>
            <p><strong>First Attendance:</strong> <?= e($transfer['first_attendance_date'] ?: '—') ?></p>
            <p><strong>Due Date (30-day SLA):</strong> <?= e($transfer['due_date']) ?></p>
            <p><strong>Status:</strong> <?= e(transfer_status_label($transfer['status'])) ?></p>
            <p><strong>Created by:</strong> <?= e($transfer['created_by_name']) ?></p>
            <?php if ($transfer['notes']): ?>
                <p class="mb-0"><strong>Notes:</strong> <?= e($transfer['notes']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <?php if ($transfer['status'] !== 'completed'): ?>
            <div class="panel-card glass-panel">
                <h3>Update Status</h3>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= e($transfer['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <?php if ($transfer['direction'] === 'outgoing' && $transfer['status'] === 'pending'): ?>
                            <button type="submit" name="action" value="mark_sent" class="btn btn-outline-light">Mark Documents Sent</button>
                        <?php endif; ?>
                        <?php if ($transfer['direction'] === 'incoming' && in_array($transfer['status'], ['pending', 'documents_sent'], true)): ?>
                            <button type="submit" name="action" value="mark_received" class="btn btn-outline-light">Mark SF10 Received</button>
                        <?php endif; ?>
                        <button type="submit" name="action" value="complete" class="btn btn-primary">Mark Completed</button>
                        <?php if (is_registrar() && $overdue): ?>
                            <button type="submit" name="action" value="escalate" class="btn btn-outline-danger">Escalate to SGOD</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        <a href="/modules/records/view.php?id=<?= (int) $transfer['student_id'] ?>" class="btn btn-outline-light w-100 mt-3">View Student Record</a>
    </div>
</div>
<?php
render_footer();
