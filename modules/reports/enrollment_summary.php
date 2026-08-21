<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$activeYear = active_school_year();
$yearLabel = $activeYear['label'] ?? 'N/A';
$yearId = (int) ($activeYear['id'] ?? 0);

$rows = [];
if ($yearId > 0) {
    $stmt = db()->prepare(
        'SELECT g.name AS grade_name, COUNT(e.id) AS total
         FROM grade_levels g
         LEFT JOIN enrollments e ON e.grade_level_id = g.id
             AND e.school_year_id = :year_id AND e.status = \'enrolled\'
         GROUP BY g.id, g.name
         ORDER BY g.id'
    );
    $stmt->execute(['year_id' => $yearId]);
    $rows = $stmt->fetchAll();
}

$total = array_sum(array_column($rows, 'total'));

render_header('Enrollment Summary Report', 'reports');
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
        <p style="margin-top: 1rem;"><strong>ENROLLMENT SUMMARY</strong><br>School Year <?= e($yearLabel) ?></p>
        <p>Date Generated: <?= e(date('F j, Y')) ?></p>
    </div>

    <table class="table table-bordered" style="color: #000;">
        <thead>
            <tr>
                <th>Grade Level</th>
                <th class="text-end">Number of Students</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['grade_name']) ?></td>
                    <td class="text-end"><?= (int) $row['total'] ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td><strong>Total</strong></td>
                <td class="text-end"><strong><?= (int) $total ?></strong></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 3rem;">
        <p>Prepared by: ___________________________</p>
        <p>Verified by: ___________________________</p>
        <p>School Registrar</p>
    </div>
</div>
<?php
render_footer();
