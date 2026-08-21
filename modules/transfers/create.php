<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/transfers.php';

require_login();

$errors = [];
$input = [
    'student_id' => '',
    'direction' => 'incoming',
    'counterpart_school' => '',
    'request_date' => date('Y-m-d'),
    'first_attendance_date' => date('Y-m-d'),
    'notes' => '',
];

$students = db()->query(
    "SELECT id, student_id_no, CONCAT(last_name, ', ', first_name) AS name
     FROM students WHERE status = 'active' ORDER BY last_name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (array_keys($input) as $key) {
        $input[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $errors = validate_required([
        'student_id' => 'Student',
        'counterpart_school' => 'Counterpart school',
        'request_date' => 'Request date',
        'first_attendance_date' => 'First attendance date',
    ], $input);

    if (!in_array($input['direction'], ['incoming', 'outgoing'], true)) {
        $errors['direction'] = 'Invalid direction.';
    }

    if (!$errors) {
        $dueDate = transfer_sla_due_date($input['first_attendance_date']);

        $stmt = db()->prepare(
            'INSERT INTO transfer_requests (
                student_id, direction, counterpart_school, request_date,
                first_attendance_date, due_date, notes, created_by
            ) VALUES (
                :student_id, :direction, :counterpart_school, :request_date,
                :first_attendance_date, :due_date, :notes, :created_by
            )'
        );
        $stmt->execute([
            'student_id' => (int) $input['student_id'],
            'direction' => $input['direction'],
            'counterpart_school' => $input['counterpart_school'],
            'request_date' => $input['request_date'],
            'first_attendance_date' => $input['first_attendance_date'],
            'due_date' => $dueDate,
            'notes' => $input['notes'] ?: null,
            'created_by' => (int) $_SESSION['user']['id'],
        ]);

        $transferId = (int) db()->lastInsertId();
        audit_log('create', 'transfer_requests', $transferId, "Created {$input['direction']} transfer request");
        flash('success', 'Transfer request created. Due date: ' . $dueDate);
        redirect('/modules/transfers/view.php?id=' . $transferId);
    }
}

render_header('New Transfer Request', 'transfers');
?>
<div class="panel-card glass-panel">
    <form method="post" novalidate>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Student</label>
                <select name="student_id" class="form-select" required>
                    <option value="">Select student</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= (int) $student['id'] ?>" <?= $input['student_id'] === (string) $student['id'] ? 'selected' : '' ?>>
                            <?= e($student['name']) ?> (<?= e($student['student_id_no']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Direction</label>
                <select name="direction" class="form-select" required>
                    <option value="incoming" <?= $input['direction'] === 'incoming' ? 'selected' : '' ?>>Incoming (request SF10 from previous school)</option>
                    <option value="outgoing" <?= $input['direction'] === 'outgoing' ? 'selected' : '' ?>>Outgoing (release SF10 to receiving school)</option>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label">Counterpart School</label>
                <input type="text" name="counterpart_school" class="form-control" value="<?= e($input['counterpart_school']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Request Date</label>
                <input type="date" name="request_date" class="form-control" value="<?= e($input['request_date']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">First Attendance Date</label>
                <input type="date" name="first_attendance_date" class="form-control" value="<?= e($input['first_attendance_date']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">SLA Due Date (auto)</label>
                <input type="text" class="form-control" value="<?= e(transfer_sla_due_date($input['first_attendance_date'])) ?>" disabled>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= e($input['notes']) ?></textarea>
            </div>
        </div>
        <?php if ($errors): ?>
            <div class="alert alert-danger mt-3">Please complete all required fields.</div>
        <?php endif; ?>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Create Request</button>
            <a href="/modules/transfers/index.php" class="btn btn-outline-light">Cancel</a>
        </div>
    </form>
</div>
<?php
render_footer();
