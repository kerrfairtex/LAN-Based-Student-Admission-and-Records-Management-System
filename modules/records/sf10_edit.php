<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/sf10.php';

require_login();

$studentId = (int) ($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    flash('danger', 'Student not found.');
    redirect('/modules/records/index.php');
}

$schoolYears = fetch_school_years();
$gradeLevels = fetch_grade_levels();

$input = [
    'school_year_id' => (string) (active_school_year()['id'] ?? ''),
    'grade_level_id' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $input['school_year_id'] = trim($_POST['school_year_id'] ?? '');
    $input['grade_level_id'] = trim($_POST['grade_level_id'] ?? '');

    $grades = $_POST['grades'] ?? [];
    save_sf10_entries(
        $studentId,
        (int) $input['school_year_id'],
        (int) $input['grade_level_id'],
        $grades,
        (int) $_SESSION['user']['id']
    );

    $entries = fetch_sf10_entries($studentId, (int) $input['school_year_id'], (int) $input['grade_level_id']);
    $generalAverage = compute_general_average($entries);

    $academic = db()->prepare(
        'SELECT id FROM academic_records
         WHERE student_id = :student_id AND school_year_id = :school_year_id'
    );
    $academic->execute([
        'student_id' => $studentId,
        'school_year_id' => (int) $input['school_year_id'],
    ]);
    $academicRow = $academic->fetch();

    if ($academicRow) {
        db()->prepare(
            'UPDATE academic_records SET grade_level_id = :grade_level_id, general_average = :general_average, updated_by = :updated_by WHERE id = :id'
        )->execute([
            'grade_level_id' => (int) $input['grade_level_id'],
            'general_average' => $generalAverage,
            'updated_by' => (int) $_SESSION['user']['id'],
            'id' => $academicRow['id'],
        ]);
    } else {
        db()->prepare(
            'INSERT INTO academic_records (student_id, school_year_id, grade_level_id, general_average, updated_by)
             VALUES (:student_id, :school_year_id, :grade_level_id, :general_average, :updated_by)'
        )->execute([
            'student_id' => $studentId,
            'school_year_id' => (int) $input['school_year_id'],
            'grade_level_id' => (int) $input['grade_level_id'],
            'general_average' => $generalAverage,
            'updated_by' => (int) $_SESSION['user']['id'],
        ]);
    }

    audit_log('update', 'sf10_grade_entries', $studentId, 'Updated SF10 grade entries');
    flash('success', 'SF10 grades saved. General average: ' . ($generalAverage ?? 'N/A'));
    redirect('/modules/records/sf10_edit.php?student_id=' . $studentId . '&school_year_id=' . $input['school_year_id'] . '&grade_level_id=' . $input['grade_level_id']);
}

if (isset($_GET['school_year_id'], $_GET['grade_level_id'])) {
    $input['school_year_id'] = (string) $_GET['school_year_id'];
    $input['grade_level_id'] = (string) $_GET['grade_level_id'];
}

$entries = [];
if ($input['school_year_id'] && $input['grade_level_id']) {
    $entries = fetch_sf10_entries($studentId, (int) $input['school_year_id'], (int) $input['grade_level_id']);
}

render_header('SF10 Grade Entry', 'records');
?>
<div class="panel-card glass-panel mb-3">
    <p class="mb-0">
        <strong><?= e(trim("{$student['last_name']}, {$student['first_name']}")) ?></strong>
        — LRN: <?= e($student['lrn'] ?: 'N/A') ?> | ID: <?= e($student['student_id_no']) ?>
    </p>
</div>

<div class="panel-card glass-panel mb-3">
    <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="student_id" value="<?= (int) $studentId ?>">
        <div class="col-md-4">
            <label class="form-label small mb-1">School Year</label>
            <select name="school_year_id" class="form-select form-select-sm">
                <?php foreach ($schoolYears as $year): ?>
                    <option value="<?= (int) $year['id'] ?>" <?= $input['school_year_id'] === (string) $year['id'] ? 'selected' : '' ?>><?= e($year['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-1">Grade Level</label>
            <select name="grade_level_id" class="form-select form-select-sm">
                <option value="">Select grade</option>
                <?php foreach ($gradeLevels as $grade): ?>
                    <option value="<?= (int) $grade['id'] ?>" <?= $input['grade_level_id'] === (string) $grade['id'] ? 'selected' : '' ?>><?= e($grade['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">Load Grades</button>
        </div>
    </form>
</div>

<?php if ($input['grade_level_id']): ?>
<div class="panel-card glass-panel">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="student_id" value="<?= (int) $studentId ?>">
        <input type="hidden" name="school_year_id" value="<?= (int) $input['school_year_id'] ?>">
        <input type="hidden" name="grade_level_id" value="<?= (int) $input['grade_level_id'] ?>">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Learning Area</th>
                        <th>Q1</th>
                        <th>Q2</th>
                        <th>Q3</th>
                        <th>Q4</th>
                        <th>Final</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (SF10_JHS_LEARNING_AREAS as $area): ?>
                        <?php $entry = $entries[$area] ?? []; ?>
                        <tr>
                            <td><?= e($area) ?></td>
                            <td><input type="number" step="0.01" min="0" max="100" name="grades[<?= e($area) ?>][q1]" class="form-control form-control-sm" value="<?= e((string) ($entry['q1_rating'] ?? '')) ?>"></td>
                            <td><input type="number" step="0.01" min="0" max="100" name="grades[<?= e($area) ?>][q2]" class="form-control form-control-sm" value="<?= e((string) ($entry['q2_rating'] ?? '')) ?>"></td>
                            <td><input type="number" step="0.01" min="0" max="100" name="grades[<?= e($area) ?>][q3]" class="form-control form-control-sm" value="<?= e((string) ($entry['q3_rating'] ?? '')) ?>"></td>
                            <td><input type="number" step="0.01" min="0" max="100" name="grades[<?= e($area) ?>][q4]" class="form-control form-control-sm" value="<?= e((string) ($entry['q4_rating'] ?? '')) ?>"></td>
                            <td><input type="number" step="0.01" min="0" max="100" name="grades[<?= e($area) ?>][final]" class="form-control form-control-sm" value="<?= e((string) ($entry['final_rating'] ?? '')) ?>"></td>
                            <td><input type="text" name="grades[<?= e($area) ?>][remarks]" class="form-control form-control-sm" value="<?= e($entry['remarks'] ?? '') ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save SF10 Grades</button>
            <a href="<?= e(url('/modules/reports/sf10.php?student_id=' . (int) $studentId . '&school_year_id=' . (int) $input['school_year_id'] . '&grade_level_id=' . (int) $input['grade_level_id'])) ?>" class="btn btn-outline-light" target="_blank">Preview SF10</a>
            <a href="<?= e(url('/modules/records/view.php?id=' . (int) $studentId)) ?>" class="btn btn-outline-light">Back</a>
        </div>
    </form>
</div>
<?php endif; ?>
<?php
render_footer();
