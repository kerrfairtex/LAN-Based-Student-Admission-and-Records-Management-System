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

// SF10 grades
$sf10 = db()->prepare(
    'SELECT sg.*, sy.label AS school_year, g.name AS grade_name
     FROM sf10_grade_entries sg
     JOIN school_years sy ON sy.id = sg.school_year_id
     JOIN grade_levels g ON g.id = sg.grade_level_id
     WHERE sg.student_id = :student_id
     ORDER BY sy.label DESC, g.name, sg.learning_area'
);
$sf10->execute(['student_id' => $id]);
$sf10Rows = $sf10->fetchAll();

// Print-specific header (no sidebar, no topbar)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Record - <?= e($student['student_id_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .panel-card { border: 1px solid #ddd; break-inside: avoid; }
        }
        body { background: #fff; color: #000; }
        .print-header { border-bottom: 3px solid #000; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .print-title { font-size: 1.5rem; font-weight: 700; }
        .print-subtitle { color: #555; }
        .info-table td { padding: 0.25rem 0.5rem; vertical-align: top; }
        .info-table td:first-child { font-weight: 600; width: 35%; color: #333; }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <!-- Print Header -->
        <div class="print-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="print-title">TRAC JHS — Student Record</div>
                    <div class="print-subtitle">LAN-Based Student Admission and Records Management System</div>
                </div>
                <div class="text-end">
                    <div><strong>Student ID:</strong> <?= e($student['student_id_no']) ?></div>
                    <div><strong>Date Printed:</strong> <?= date('F j, Y') ?></div>
                </div>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="panel-card border border-secondary p-3 mb-3" style="border-radius:0;">
            <h5 class="border-bottom pb-2 mb-3">Personal Information</h5>
            <table class="table info-table borderless">
                <tbody>
                    <tr><td>Full Name</td><td><?= e(trim("{$student['last_name']}, {$student['first_name']} {$student['middle_name']} {$student['suffix']}")) ?></td></tr>
                    <tr><td>LRN</td><td><?= e($student['lrn'] ?: '—') ?></td></tr>
                    <tr><td>Birthdate</td><td><?= e($student['birthdate']) ?></td></tr>
                    <tr><td>Sex</td><td><?= e($student['sex']) ?></td></tr>
                    <tr><td>Address</td><td><?= e($student['address']) ?></td></tr>
                    <tr><td>Contact</td><td><?= e($student['contact_number'] ?: '—') ?></td></tr>
                    <tr><td>Previous School</td><td><?= e($student['previous_school'] ?: '—') ?></td></tr>
                    <tr><td>Status</td><td><span class="badge bg-secondary"><?= e(ucfirst($student['status'])) ?></span></td></tr>
                </tbody>
            </table>
        </div>

        <!-- Guardian -->
        <div class="panel-card border border-secondary p-3 mb-3" style="border-radius:0;">
            <h5 class="border-bottom pb-2 mb-3">Guardian Information</h5>
            <table class="table info-table borderless">
                <tbody>
                    <tr><td>Guardian Name</td><td><?= e($student['guardian_name']) ?></td></tr>
                    <tr><td>Relationship</td><td><?= e($student['guardian_relationship']) ?></td></tr>
                    <tr><td>Guardian Contact</td><td><?= e($student['guardian_contact']) ?></td></tr>
                </tbody>
            </table>
        </div>

        <!-- Enrollment History -->
        <div class="panel-card border border-secondary p-3 mb-3" style="border-radius:0;">
            <h5 class="border-bottom pb-2 mb-3">Enrollment History</h5>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>School Year</th><th>Grade</th><th>Section</th><th>Type</th><th>Status</th><th>Enrolled</th></tr>
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

        <!-- Academic Records -->
        <div class="panel-card border border-secondary p-3 mb-3" style="border-radius:0;">
            <h5 class="border-bottom pb-2 mb-3">Academic Records</h5>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>School Year</th><th>Grade</th><th>General Average</th><th>Promotional Status</th><th>Awards</th></tr>
                </thead>
                <tbody>
                    <?php if (!$academicRecords): ?>
                        <tr><td colspan="5" class="text-muted">No academic records.</td></tr>
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

        <!-- SF10 Grades -->
        <?php if ($sf10Rows): ?>
        <div class="panel-card border border-secondary p-3 mb-3" style="border-radius:0;">
            <h5 class="border-bottom pb-2 mb-3">SF10 Per-Learning-Area Grades</h5>
            <?php foreach ($sf10Rows as $group): ?>
                <h6 class="mt-2 mb-1"><?= e($group['school_year']) ?> — <?= e($group['grade_name']) ?></h6>
                <table class="table table-sm table-bordered mb-2">
                    <thead class="table-light">
                        <tr><th>Learning Area</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Final</th><th>Remarks</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= e($group['learning_area']) ?></td>
                            <td><?= e($group['q1_rating'] ?? '—') ?></td>
                            <td><?= e($group['q2_rating'] ?? '—') ?></td>
                            <td><?= e($group['q3_rating'] ?? '—') ?></td>
                            <td><?= e($group['q4_rating'] ?? '—') ?></td>
                            <td><strong><?= e($group['final_rating'] ?? '—') ?></strong></td>
                            <td><?= e($group['remarks'] ?: '—') ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-4 pt-3 border-top text-muted small no-print">
            <div class="d-flex justify-content-between align-items-center">
                <span>TRAC JHS SARMS — Official Record</span>
                <span>Generated on <?= date('F j, Y g:i A') ?></span>
            </div>
            <div class="text-center mt-3">
                <button type="button" onclick="window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Print this page
                </button>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// No render_footer() — this is a standalone print view