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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $newStatus = $_POST['status'] ?? '';
    $validStatuses = ['active', 'transferred', 'graduated', 'dropped'];

    if (in_array($newStatus, $validStatuses, true)) {
        db()->prepare('UPDATE students SET status = :status, updated_at = NOW() WHERE id = :id')
            ->execute(['status' => $newStatus, 'id' => $studentId]);
        audit_log('status_change', 'students', $studentId, "Status changed to {$newStatus}");
        flash('success', "Student status updated to {$newStatus}.");
        redirect('/modules/records/view.php?id=' . $studentId);
    } else {
        flash('danger', 'Invalid status selected.');
    }
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
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">New Status</label>
                    <select name="status" class="form-select" required>
                        <option value="">— Select —</option>
                        <option value="active" <?= $student['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="transferred" <?= $student['status'] === 'transferred' ? 'selected' : '' ?>>Transferred</option>
                        <option value="graduated" <?= $student['status'] === 'graduated' ? 'selected' : '' ?>>Graduated</option>
                        <option value="dropped" <?= $student['status'] === 'dropped' ? 'selected' : '' ?>>Dropped / Withdrawn</option>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <a href="<?= e(url('/modules/records/view.php?id=' . $studentId)) ?>" class="btn btn-outline-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
render_footer();