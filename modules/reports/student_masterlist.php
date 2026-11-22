<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

// Grade/section come from the enrollment in the selected school year only
// (the old LEFT JOIN on school_years did not filter, so a learner could
// show a grade from a past year).
$activeYear = active_school_year();
$yearId = (int) ($activeYear['id'] ?? 0);
$yearLabel = $activeYear['label'] ?? 'N/A';

$stmt = db()->prepare(
    'SELECT s.student_id_no, s.lrn,
            CONCAT(s.last_name, \', \', s.first_name, \' \', COALESCE(s.middle_name, \'\')) AS full_name,
            s.sex, s.birthdate, g.name AS grade_name, sec.name AS section_name, s.status
     FROM trac_jhs_sarms.students s
     LEFT JOIN trac_jhs_sarms.enrollments e
            ON e.student_id = s.id AND e.school_year_id = :year_id AND e.status = \'enrolled\'
     LEFT JOIN trac_jhs_sarms.grade_levels g ON g.id = e.grade_level_id
     LEFT JOIN trac_jhs_sarms.sections sec ON sec.id = e.section_id
     WHERE s.status = \'active\'
     ORDER BY s.last_name, s.first_name'
);
$stmt->execute(['year_id' => $yearId]);
$rows = $stmt->fetchAll();

if (wants_csv()) {
    send_csv(
        'student_masterlist_' . $yearLabel . '.csv',
        ['Student ID', 'LRN', 'Name', 'Sex', 'Birthdate', 'Grade', 'Section', 'Status'],
        array_map(static fn ($r) => [
            $r['student_id_no'], $r['lrn'], trim($r['full_name']), $r['sex'], $r['birthdate'],
            $r['grade_name'], $r['section_name'], ucfirst($r['status']),
        ], $rows),
        'Student master list CSV, SY ' . $yearLabel . ' (' . count($rows) . ' rows)'
    );
}

render_header('Student Master List', 'reports');
?>
<div class="no-print mb-3 d-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print Report</button>
    <a href="<?= e(csv_url('/modules/reports/student_masterlist.php')) ?>" class="btn btn-outline-light"><i class="bi bi-filetype-csv"></i> Export CSV</a>
    <a href="<?= e(url('/modules/reports/index.php')) ?>" class="btn btn-outline-light">Back</a>
</div>

<div class="print-report">
    <div class="text-center mb-4">
        <h1 style="font-size: 14pt; margin-bottom: 0;">Republic of the Philippines</h1>
        <h2 style="font-size: 13pt; margin-top: 0.25rem;">Department of Education</h2>
        <h3 style="font-size: 12pt; margin-top: 0.5rem;"><?= e(APP_SCHOOL) ?></h3>
        <p style="margin-top: 1rem;"><strong>STUDENT MASTER LIST</strong></p>
        <p>School Year <?= e($yearLabel) ?> &middot; Date Generated: <?= e(date('F j, Y')) ?></p>
    </div>

    <table class="table table-bordered" style="color: #000;">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>LRN</th>
                <th>Name</th>
                <th>Sex</th>
                <th>Grade</th>
                <th>Section</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['student_id_no']) ?></td>
                    <td><?= e($row['lrn'] ?: '—') ?></td>
                    <td><?= e(trim($row['full_name'])) ?></td>
                    <td><?= e($row['sex']) ?></td>
                    <td><?= e($row['grade_name'] ?: '—') ?></td>
                    <td><?= e($row['section_name'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><strong>Total active learners:</strong> <?= count($rows) ?></p>
</div>
<?php
render_footer();
