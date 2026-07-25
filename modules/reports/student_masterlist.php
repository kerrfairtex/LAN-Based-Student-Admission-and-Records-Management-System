<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$rows = db()->query(
    'SELECT s.student_id_no, s.lrn,
            CONCAT(s.last_name, ", ", s.first_name, " ", IFNULL(s.middle_name, "")) AS full_name,
            s.sex, g.name AS grade_name, s.status
     FROM students s
     LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = "enrolled"
     LEFT JOIN school_years sy ON sy.id = e.school_year_id AND sy.is_active = 1
     LEFT JOIN grade_levels g ON g.id = e.grade_level_id
     WHERE s.status = "active"
     ORDER BY s.last_name, s.first_name'
)->fetchAll();

render_header('Student Master List', 'reports');
?>
<div class="no-print mb-3 d-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print Report</button>
    <a href="<?= e(url('/modules/reports/index.php')) ?>" class="btn btn-outline-light">Back</a>
</div>

<div class="print-report">
    <div class="text-center mb-4">
        <h1 style="font-size: 14pt; margin-bottom: 0;">Republic of the Philippines</h1>
        <h2 style="font-size: 13pt; margin-top: 0.25rem;">Department of Education</h2>
        <h3 style="font-size: 12pt; margin-top: 0.5rem;"><?= e(APP_SCHOOL) ?></h3>
        <p style="margin-top: 1rem;"><strong>STUDENT MASTER LIST</strong></p>
        <p>Date Generated: <?= e(date('F j, Y')) ?></p>
    </div>

    <table class="table table-bordered" style="color: #000;">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>LRN</th>
                <th>Name</th>
                <th>Sex</th>
                <th>Grade</th>
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
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
render_footer();
