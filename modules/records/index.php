<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// Count total active students
$totalStmt = db()->query('SELECT COUNT(*) AS c FROM students WHERE status = \'active\'');
$total = (int) $totalStmt->fetch()['c'];

$paginated = paginate($total, $perPage, $currentPage);

$stmt = db()->prepare(
    'SELECT s.*, e.grade_level_id, g.name AS grade_name, sy.label AS school_year, sec.name AS section_name
     FROM students s
     LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = \'enrolled\'
     LEFT JOIN school_years sy ON sy.id = e.school_year_id
     LEFT JOIN grade_levels g ON g.id = e.grade_level_id
     LEFT JOIN sections sec ON sec.id = e.section_id
     WHERE s.status = \'active\'
     ORDER BY s.last_name, s.first_name
     LIMIT :limit OFFSET :offset'
);
$stmt->execute(['limit' => $paginated['per_page'], 'offset' => $paginated['offset']]);
$students = $stmt->fetchAll();

render_header('Records Management', 'records');
?>
<p class="text-muted">Retrieve, update, and archive student academic histories.</p>

<div class="table-card glass-panel">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>LRN</th>
                    <th>Grade</th>
                    <th>Section</th>
                    <th>School Year</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$students): ?>
                    <tr><td colspan="7" class="text-muted">No student records found.</td></tr>
                <?php endif; ?>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= e($student['student_id_no']) ?></td>
                        <td><?= e(trim("{$student['last_name']}, {$student['first_name']} {$student['middle_name']}")) ?></td>
                        <td><?= e($student['lrn'] ?: '—') ?></td>
                        <td><?= e($student['grade_name'] ?: '—') ?></td>
                        <td><?= e($student['section_name'] ?: '—') ?></td>
                        <td><?= e($student['school_year'] ?: '—') ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/records/view.php?id=' . (int) $student['id'])) ?>">View</a>
                            <a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/records/edit.php?id=' . (int) $student['id'])) ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/records/status.php?id=' . (int) $student['id'])) ?>">Status</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginated['last_page'] > 1): ?>
        <div class="p-3">
            <?= render_pager($paginated['current_page'], $paginated['last_page'], url('/modules/records/index.php')) ?>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer();