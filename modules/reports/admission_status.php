<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$rows = db()->query(
    'SELECT a.application_no, a.status, a.enrollment_type,
            CONCAT(a.last_name, \', \', a.first_name) AS applicant_name,
            g.name AS grade_name, sy.label AS school_year, a.created_at
     FROM admissions a
     JOIN grade_levels g ON g.id = a.grade_level_id
     JOIN school_years sy ON sy.id = a.school_year_id
     ORDER BY a.created_at DESC'
)->fetchAll();

render_header('Admission Status Report', 'reports');
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
        <p style="margin-top: 1rem;"><strong>ADMISSION STATUS REPORT</strong></p>
        <p>Date Generated: <?= e(date('F j, Y')) ?></p>
    </div>

    <table class="table table-bordered" style="color: #000;">
        <thead>
            <tr>
                <th>Application No.</th>
                <th>Applicant</th>
                <th>Grade</th>
                <th>School Year</th>
                <th>Type</th>
                <th>Status</th>
                <th>Date Encoded</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['application_no']) ?></td>
                    <td><?= e($row['applicant_name']) ?></td>
                    <td><?= e($row['grade_name']) ?></td>
                    <td><?= e($row['school_year']) ?></td>
                    <td><?= e(ucfirst($row['enrollment_type'])) ?></td>
                    <td><?= e(ucfirst($row['status'])) ?></td>
                    <td><?= e(date('Y-m-d', strtotime($row['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
render_footer();
