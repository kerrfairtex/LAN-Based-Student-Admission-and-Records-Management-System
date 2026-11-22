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
        'SELECT g.name AS grade_name,
                COUNT(st.id) FILTER (WHERE st.sex = \'Male\') AS male,
                COUNT(st.id) FILTER (WHERE st.sex = \'Female\') AS female,
                COUNT(e.id) AS total
         FROM trac_jhs_sarms.grade_levels g
         LEFT JOIN trac_jhs_sarms.enrollments e ON e.grade_level_id = g.id
             AND e.school_year_id = :year_id AND e.status = \'enrolled\'
         LEFT JOIN trac_jhs_sarms.students st ON st.id = e.student_id
         GROUP BY g.id, g.name
         ORDER BY g.id'
    );
    $stmt->execute(['year_id' => $yearId]);
    $rows = $stmt->fetchAll();
}

$total = array_sum(array_column($rows, 'total'));
$totalMale = array_sum(array_column($rows, 'male'));
$totalFemale = array_sum(array_column($rows, 'female'));

if (wants_csv()) {
    $out = array_map(static fn ($r) => [$r['grade_name'], (int) $r['male'], (int) $r['female'], (int) $r['total']], $rows);
    $out[] = ['Total', $totalMale, $totalFemale, $total];
    send_csv(
        'enrollment_summary_' . $yearLabel . '.csv',
        ['Grade Level', 'Male', 'Female', 'Total'],
        $out,
        'Enrollment summary CSV, SY ' . $yearLabel
    );
}

render_header('Enrollment Summary Report', 'reports');
?>
<div class="no-print mb-3 d-flex gap-2">
    <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print Report</button>
    <a href="<?= e(csv_url('/modules/reports/enrollment_summary.php')) ?>" class="btn btn-outline-light"><i class="bi bi-filetype-csv"></i> Export CSV</a>
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
                <th class="text-end">Male</th>
                <th class="text-end">Female</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['grade_name']) ?></td>
                    <td class="text-end"><?= (int) $row['male'] ?></td>
                    <td class="text-end"><?= (int) $row['female'] ?></td>
                    <td class="text-end"><?= (int) $row['total'] ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td><strong>Total</strong></td>
                <td class="text-end"><strong><?= (int) $totalMale ?></strong></td>
                <td class="text-end"><strong><?= (int) $totalFemale ?></strong></td>
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
