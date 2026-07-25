<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$stmt = db()->query(
    'SELECT s.*, e.grade_level_id, g.name AS grade_name, sy.label AS school_year, sec.name AS section_name
     FROM students s
     LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = "enrolled"
     LEFT JOIN school_years sy ON sy.id = e.school_year_id AND sy.is_active = 1
     LEFT JOIN grade_levels g ON g.id = e.grade_level_id
     LEFT JOIN sections sec ON sec.id = e.section_id
     WHERE s.status = "active"
     ORDER BY s.last_name, s.first_name'
);
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
                            <a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/records/view.php?id=<?= (int) $student[\'id\'] ?>')) ?>">View</a>
                            <a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/records/edit.php?id=<?= (int) $student[\'id\'] ?>')) ?>">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
render_footer();
