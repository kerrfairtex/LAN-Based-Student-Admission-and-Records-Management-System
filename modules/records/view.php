<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute(['id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    flash('danger', 'Student record not found.');
    redirect('/modules/records/index.php');
}

$enrollments = db()->prepare(
    'SELECT e.*, sy.label AS school_year, g.name AS grade_name, sec.name AS section_name
     FROM enrollments e
     JOIN school_years sy ON sy.id = e.school_year_id
     JOIN grade_levels g ON g.id = e.grade_level_id
     LEFT JOIN sections sec ON sec.id = e.section_id
     WHERE e.student_id = :student_id
     ORDER BY e.enrolled_at DESC'
);
$enrollments->execute(['student_id' => $id]);
$enrollmentRows = $enrollments->fetchAll();

$records = db()->prepare(
    'SELECT ar.*, sy.label AS school_year, g.name AS grade_name
     FROM academic_records ar
     JOIN school_years sy ON sy.id = ar.school_year_id
     JOIN grade_levels g ON g.id = ar.grade_level_id
     WHERE ar.student_id = :student_id
     ORDER BY ar.school_year_id DESC'
);
$records->execute(['student_id' => $id]);
$academicRecords = $records->fetchAll();

// Audit-log the view only if the same user has not viewed this student
// in the last 5 minutes. Otherwise every page refresh floods audit_logs
// and works against the retention policy added in migration 004.
$recent = db()->prepare(
    'SELECT 1 FROM audit_logs
      WHERE user_id = :user_id
        AND entity_type = \'students\'
        AND entity_id = :entity_id
        AND action = \'view\'
        AND created_at > NOW() - INTERVAL \'5 minutes\'
      LIMIT 1'
);
$recent->execute([
    'user_id'    => (int) $_SESSION['user']['id'],
    'entity_id'  => $id,
]);
if (!$recent->fetch()) {
    audit_log('view', 'students', $id, 'Viewed student record');
}

render_header('Student Record', 'records');
?>
<div class="panel-card glass-panel mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><?= e($student['student_id_no']) ?></h3>
            <p class="text-muted mb-0">
                <?= e(trim("{$student['last_name']}, {$student['first_name']} {$student['middle_name']}")) ?>
            </p>
        </div>
        <span class="badge badge-status-active"><?= e(ucfirst($student['status'])) ?></span>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3>Personal Information</h3>
            <p><strong>LRN:</strong> <?= e($student['lrn'] ?: '—') ?></p>
            <p><strong>Birthdate:</strong> <?= e($student['birthdate']) ?></p>
            <p><strong>Sex:</strong> <?= e($student['sex']) ?></p>
            <p><strong>Address:</strong> <?= e($student['address']) ?></p>
            <p><strong>Contact:</strong> <?= e($student['contact_number'] ?: '—') ?></p>
            <p class="mb-0"><strong>Previous School:</strong> <?= e($student['previous_school'] ?: '—') ?></p>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3>Guardian Information</h3>
            <p><strong>Name:</strong> <?= e($student['guardian_name']) ?></p>
            <p><strong>Relationship:</strong> <?= e($student['guardian_relationship']) ?></p>
            <p class="mb-0"><strong>Contact:</strong> <?= e($student['guardian_contact']) ?></p>
        </div>
    </div>
</div>

<div class="table-card glass-panel mt-3">
    <h3>Enrollment History</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>School Year</th>
                    <th>Grade</th>
                    <th>Section</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$enrollmentRows): ?>
                    <tr><td colspan="6" class="text-muted">No enrollment history.</td></tr>
                <?php endif; ?>
                <?php foreach ($enrollmentRows as $row): ?>
                    <tr>
                        <td><?= e($row['school_year']) ?></td>
                        <td><?= e($row['grade_name']) ?></td>
                        <td><?= e($row['section_name'] ?: '—') ?></td>
                        <td><?= e(ucfirst($row['enrollment_type'])) ?></td>
                        <td><?= e(ucfirst($row['status'])) ?></td>
                        <td><?= e($row['enrolled_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="table-card glass-panel mt-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 class="mb-0">Academic Records</h3>
        <a href="<?= e(url('/modules/records/academic.php?student_id=' . (int) $student['id'])) ?>" class="btn btn-sm btn-primary">Add / Update Record</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>School Year</th>
                    <th>Grade</th>
                    <th>General Average</th>
                    <th>Status</th>
                    <th>Awards</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$academicRecords): ?>
                    <tr><td colspan="5" class="text-muted">No academic records archived yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($academicRecords as $record): ?>
                    <tr>
                        <td><?= e($record['school_year']) ?></td>
                        <td><?= e($record['grade_name']) ?></td>
                        <td><?= e($record['general_average'] ?? '—') ?></td>
                        <td><?= e($record['promotional_status'] ?? '—') ?></td>
                        <td><?= e($record['awards'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex gap-2 mt-3 no-print">
    <a href="<?= e(url('/modules/records/sf10_edit.php?student_id=' . (int) $student['id'])) ?>" class="btn btn-primary">SF10 Grades</a>
    <a href="<?= e(url('/modules/records/edit.php?id=' . (int) $student['id'])) ?>" class="btn btn-outline-light">Edit Profile</a>
    <a href="<?= e(url('/modules/records/status.php?id=' . (int) $student['id'])) ?>" class="btn btn-outline-light">Change Status</a>
    <a href="<?= e(url('/modules/records/print.php?id=' . (int) $student['id'])) ?>" class="btn btn-outline-light" target="_blank"><i class="bi bi-printer"></i> Print</a>
    <a href="<?= e(url('/modules/records/index.php')) ?>" class="btn btn-outline-light">Back to Records</a>
</div>
<?php
render_footer();
