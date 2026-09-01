<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$studentId = (int) ($_GET['id'] ?? 0);
if ($studentId <= 0) {
    redirect('/modules/records/index.php');
}

$stmt = db()->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    flash('danger', 'Student not found.');
    redirect('/modules/records/index.php');
}

// Allowed transitions:
//   active → transferred | graduated | dropped
//   transferred | dropped | graduated → active (re-admit / revert — registrar only)
$allowedTransitions = [
    'active'      => ['transferred', 'graduated', 'dropped'],
    'transferred' => ['active'],
    'graduated'   => ['active'],
    'dropped'     => ['active'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $newStatus = $_POST['status'] ?? '';
    $currentStatus = $student['status'];

    // Resolve whether this transition is allowed at all.
    $isRevert = in_array($currentStatus, ['transferred', 'graduated', 'dropped'], true)
                && $newStatus === 'active';

    if ($isRevert && !is_registrar()) {
        flash('danger', 'Only the School Registrar can re-admit or revert a student status.');
        redirect('/modules/records/status.php?id=' . $studentId);
    }

    if (!isset($allowedTransitions[$currentStatus])
        || !in_array($newStatus, $allowedTransitions[$currentStatus], true)) {
        flash('danger', 'Invalid status transition for the current record state.');
        redirect('/modules/records/status.php?id=' . $studentId);
    }

    // Defense-in-depth: still validate against the hard schema enum.
    $validStatuses = ['active', 'transferred', 'graduated', 'dropped'];
    if (!in_array($newStatus, $validStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('/modules/records/status.php?id=' . $studentId);
    }

    db()->prepare('UPDATE students SET status = :status, updated_at = NOW() WHERE id = :id')
        ->execute(['status' => $newStatus, 'id' => $studentId]);
    audit_log('status_change', 'students', $studentId, "Status changed from {$currentStatus} to {$newStatus}");
    flash('success', "Student status updated to {$newStatus}.");
    redirect('/modules/records/view.php?id=' . $studentId);
}

render_header('Change Student Status', 'records');
?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3>Student Information</h3>
            <table class="table table-sm">
                <tbody>
                    <tr><th>Student ID</th><td><?= e($student['student_id_no']) ?></td></tr>
                    <tr><th>Name</th><td><?= e(trim("{$student['last_name']}, {$student['first_name']} {$student['middle_name']}")) ?></td></tr>
                    <tr><th>Current Status</th><td><span class="badge badge-status-<?= e($student['status']) ?>"><?= e(ucfirst($student['status'])) ?></span></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3>Update Status</h3>
            <?php
                $currentStatus = $student['status'];
                $options = $allowedTransitions[$currentStatus] ?? [];
                $registrarOnly = is_registrar();
            ?>
            <?php if (!$options): ?>
                <p class="text-muted mb-0">No further status transitions are available for this student.</p>
            <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="status" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($options as $opt): ?>
                                <?php $requiresRegistrar = $currentStatus !== 'active'; ?>
                                <option value="<?= e($opt) ?>">
                                    <?= e(ucfirst($opt)) ?><?= $requiresRegistrar && !$registrarOnly ? ' (registrar only)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$registrarOnly && $currentStatus !== 'active'): ?>
                            <small class="text-muted">Re-admitting or reverting a student's status requires the School Registrar.</small>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Status</button>
                        <a href="<?= e(url('/modules/records/view.php?id=' . $studentId)) ?>" class="btn btn-outline-light">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
render_footer();