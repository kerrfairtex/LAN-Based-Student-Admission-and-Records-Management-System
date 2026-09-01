<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$studentId = (int) ($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    flash('danger', 'Student not found.');
    redirect('/modules/records/index.php');
}

$errors = [];
$input = [
    'school_year_id' => (string) (active_school_year()['id'] ?? ''),
    'grade_level_id' => '',
    'general_average' => '',
    'promotional_status' => '',
    'attendance_days' => '',
    'awards' => '',
    'record_notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (array_keys($input) as $key) {
        $input[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $errors = validate_required([
        'school_year_id' => 'School year',
        'grade_level_id' => 'Grade level',
    ], $input);

    if ($input['general_average'] !== '' && !is_numeric($input['general_average'])) {
        $errors['general_average'] = 'General average must be a number.';
    } elseif ($input['general_average'] !== '' && ((float) $input['general_average'] < 0 || (float) $input['general_average'] > 100)) {
        $errors['general_average'] = 'General average must be between 0 and 100.';
    }

    if ($input['attendance_days'] !== '' && (!ctype_digit($input['attendance_days']) || (int) $input['attendance_days'] < 0)) {
        $errors['attendance_days'] = 'Attendance days must be a non-negative integer.';
    }

    if (!$errors) {
        $existing = db()->prepare(
            'SELECT id FROM academic_records WHERE student_id = :student_id AND school_year_id = :school_year_id'
        );
        $existing->execute([
            'student_id' => $studentId,
            'school_year_id' => (int) $input['school_year_id'],
        ]);
        $record = $existing->fetch();

        if ($record) {
            $update = db()->prepare(
                'UPDATE academic_records SET
                    grade_level_id = :grade_level_id,
                    general_average = :general_average,
                    promotional_status = :promotional_status,
                    attendance_days = :attendance_days,
                    awards = :awards,
                    record_notes = :record_notes,
                    updated_by = :updated_by
                 WHERE id = :id'
            );
            $update->execute([
                'grade_level_id' => (int) $input['grade_level_id'],
                'general_average' => $input['general_average'] !== '' ? $input['general_average'] : null,
                'promotional_status' => $input['promotional_status'] ?: null,
                'attendance_days' => $input['attendance_days'] !== '' ? (int) $input['attendance_days'] : null,
                'awards' => $input['awards'] ?: null,
                'record_notes' => $input['record_notes'] ?: null,
                'updated_by' => (int) $_SESSION['user']['id'],
                'id' => (int) $record['id'],
            ]);
            audit_log('update', 'academic_records', (int) $record['id'], 'Updated academic record');
        } else {
            $insert = db()->prepare(
                'INSERT INTO academic_records (
                    student_id, school_year_id, grade_level_id, general_average,
                    promotional_status, attendance_days, awards, record_notes, updated_by
                ) VALUES (
                    :student_id, :school_year_id, :grade_level_id, :general_average,
                    :promotional_status, :attendance_days, :awards, :record_notes, :updated_by
                )'
            );
            $insert->execute([
                'student_id' => $studentId,
                'school_year_id' => (int) $input['school_year_id'],
                'grade_level_id' => (int) $input['grade_level_id'],
                'general_average' => $input['general_average'] !== '' ? $input['general_average'] : null,
                'promotional_status' => $input['promotional_status'] ?: null,
                'attendance_days' => $input['attendance_days'] !== '' ? (int) $input['attendance_days'] : null,
                'awards' => $input['awards'] ?: null,
                'record_notes' => $input['record_notes'] ?: null,
                'updated_by' => (int) $_SESSION['user']['id'],
            ]);
            audit_log('create', 'academic_records', (int) db()->lastInsertId(), 'Created academic record');
        }

        flash('success', 'Academic record saved.');
        redirect('/modules/records/view.php?id=' . $studentId);
    }
}

$schoolYears = fetch_school_years();
$gradeLevels = fetch_grade_levels();

render_header('Academic Record', 'records');
?>
<div class="panel-card glass-panel">
    <p class="text-muted">Student: <strong><?= e(trim("{$student['last_name']}, {$student['first_name']}")) ?></strong> (<?= e($student['student_id_no']) ?>)</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="student_id" value="<?= $studentId ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">School Year</label>
                <select name="school_year_id" class="form-select" required>
                    <?php foreach ($schoolYears as $year): ?>
                        <option value="<?= (int) $year['id'] ?>"><?= e($year['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Grade Level</label>
                <select name="grade_level_id" class="form-select" required>
                    <option value="">Select grade</option>
                    <?php foreach ($gradeLevels as $grade): ?>
                        <option value="<?= (int) $grade['id'] ?>"><?= e($grade['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">General Average</label>
                <input type="number" step="0.01" min="0" max="100" name="general_average" class="form-control <?= isset($errors['general_average']) ? 'is-invalid' : '' ?>" value="<?= e($input['general_average']) ?>">
                <?php if (isset($errors['general_average'])): ?>
                    <div class="invalid-feedback"><?= e($errors['general_average']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Promotional Status</label>
                <select name="promotional_status" class="form-select">
                    <option value="">Select</option>
                    <option value="Promoted">Promoted</option>
                    <option value="Retained">Retained</option>
                    <option value="Incomplete">Incomplete</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Attendance Days</label>
                <input type="number" min="0" name="attendance_days" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Awards</label>
                <input type="text" name="awards" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Record Notes</label>
                <textarea name="record_notes" class="form-control" rows="3"></textarea>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Save Record</button>
            <a href="<?= e(url('/modules/records/view.php?id=' . (int) $studentId)) ?>" class="btn btn-outline-light">Cancel</a>
        </div>
    </form>
</div>
<?php
render_footer();
