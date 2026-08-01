<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/sf10.php';

require_login();

$studentId = (int) ($_GET['student_id'] ?? 0);
$schoolYearId = (int) ($_GET['school_year_id'] ?? active_school_year()['id'] ?? 0);
$gradeLevelId = (int) ($_GET['grade_level_id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    flash('danger', 'Student not found.');
    redirect('/modules/reports/index.php');
}

$sy = db()->prepare('SELECT label FROM school_years WHERE id = :id');
$sy->execute(['id' => $schoolYearId]);
$schoolYear = $sy->fetch()['label'] ?? '';

$gl = db()->prepare('SELECT name FROM grade_levels WHERE id = :id');
$gl->execute(['id' => $gradeLevelId]);
$gradeName = $gl->fetch()['name'] ?? '';

$entries = $gradeLevelId ? fetch_sf10_entries($studentId, $schoolYearId, $gradeLevelId) : [];
$generalAverage = compute_general_average($entries);

$academic = db()->prepare(
    'SELECT promotional_status, attendance_days, awards FROM academic_records
     WHERE student_id = :student_id AND school_year_id = :school_year_id'
);
$academic->execute(['student_id' => $studentId, 'school_year_id' => $schoolYearId]);
$academicRecord = $academic->fetch() ?: [];

render_header('SF10-JHS Permanent Record', 'reports');
?>
<div class="no-print mb-3 d-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print SF10</button>
    <a href="/modules/records/sf10_edit.php?student_id=<?= $studentId ?>&school_year_id=<?= $schoolYearId ?>&grade_level_id=<?= $gradeLevelId ?>" class="btn btn-outline-light">Edit Grades</a>
    <a href="/modules/reports/index.php" class="btn btn-outline-light">Back</a>
</div>

<div class="print-report">
    <div class="text-center mb-3">
        <p style="margin:0;">Republic of the Philippines</p>
        <p style="margin:0;">Department of Education</p>
        <p style="margin:0.5rem 0;"><strong>Learner Permanent Academic Record (SF10-JHS)</strong></p>
        <p style="margin:0;"><?= e(APP_SCHOOL) ?></p>
    </div>

    <p><strong>LEARNER'S PERSONAL INFORMATION</strong></p>
    <table class="table table-bordered" style="color:#000;">
        <tr>
            <td width="25%">LRN</td>
            <td><?= e($student['lrn'] ?: '—') ?></td>
            <td width="25%">Student ID</td>
            <td><?= e($student['student_id_no']) ?></td>
        </tr>
        <tr>
            <td>Last Name</td>
            <td><?= e($student['last_name']) ?></td>
            <td>First Name</td>
            <td><?= e($student['first_name']) ?></td>
        </tr>
        <tr>
            <td>Middle Name</td>
            <td><?= e($student['middle_name'] ?: '—') ?></td>
            <td>Extension</td>
            <td><?= e($student['suffix'] ?: '—') ?></td>
        </tr>
        <tr>
            <td>Birthdate</td>
            <td><?= e($student['birthdate']) ?></td>
            <td>Sex</td>
            <td><?= e($student['sex']) ?></td>
        </tr>
        <tr>
            <td>Address</td>
            <td colspan="3"><?= e($student['address']) ?></td>
        </tr>
        <tr>
            <td>Parent/Guardian</td>
            <td><?= e($student['guardian_name']) ?></td>
            <td>Contact</td>
            <td><?= e($student['guardian_contact']) ?></td>
        </tr>
    </table>

    <p class="mt-3"><strong>LEARNER'S ACADEMIC PROGRESS — <?= e($gradeName) ?> (SY <?= e($schoolYear) ?>)</strong></p>
    <table class="table table-bordered" style="color:#000;">
        <thead>
            <tr>
                <th>Learning Area</th>
                <th>Q1</th>
                <th>Q2</th>
                <th>Q3</th>
                <th>Q4</th>
                <th>Final Rating</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (SF10_JHS_LEARNING_AREAS as $area): ?>
                <?php $entry = $entries[$area] ?? []; ?>
                <tr>
                    <td><?= e($area) ?></td>
                    <td><?= e((string) ($entry['q1_rating'] ?? '')) ?></td>
                    <td><?= e((string) ($entry['q2_rating'] ?? '')) ?></td>
                    <td><?= e((string) ($entry['q3_rating'] ?? '')) ?></td>
                    <td><?= e((string) ($entry['q4_rating'] ?? '')) ?></td>
                    <td><?= e((string) ($entry['final_rating'] ?? '')) ?></td>
                    <td><?= e($entry['remarks'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="5" class="text-end"><strong>General Average</strong></td>
                <td><strong><?= e((string) ($generalAverage ?? '')) ?></strong></td>
                <td><?= e($academicRecord['promotional_status'] ?? '') ?></td>
            </tr>
        </tbody>
    </table>

    <p><strong>Other Information:</strong></p>
    <p>Days of Attendance: <?= e((string) ($academicRecord['attendance_days'] ?? '—')) ?></p>
    <p>Awards/Honors: <?= e($academicRecord['awards'] ?? '—') ?></p>

    <div style="margin-top:3rem; border:1px solid #000; padding:1rem;">
        <p><strong>CERTIFICATION</strong></p>
        <p style="text-indent:2rem;">
            I certify that this is a true record of <strong><?= e(trim("{$student['first_name']} {$student['middle_name']} {$student['last_name']}")) ?></strong>
            who is eligible for admission to <?= e($gradeName) ?> and has completed the requirements as indicated above.
        </p>
        <div style="margin-top:2rem;">
            <p>___________________________</p>
            <p>School Head / Principal</p>
            <p>Date: ______________________</p>
        </div>
    </div>
</div>
<?php
render_footer();
