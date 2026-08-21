<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$activeYear = active_school_year();
$yearId = (int) ($activeYear['id'] ?? 0);

$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

$enrollments = [];
$total = 0;

if ($yearId > 0) {
    // Count total
    $countStmt = db()->prepare('SELECT COUNT(*) AS c FROM enrollments WHERE school_year_id = :year_id AND status = \'enrolled\'');
    $countStmt->execute(['year_id' => $yearId]);
    $total = (int) $countStmt->fetch()['c'];

    $paginated = paginate($total, $perPage, $currentPage);

    $stmt = db()->prepare(
        'SELECT e.id, e.section_id, e.enrollment_type, e.enrolled_at,
                s.id AS student_pk, s.student_id_no, s.lrn,
                CONCAT(s.last_name, \', \', s.first_name) AS student_name,
                g.id AS grade_level_id, g.name AS grade_name,
                sec.name AS section_name
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         JOIN grade_levels g ON g.id = e.grade_level_id
         LEFT JOIN sections sec ON sec.id = e.section_id
         WHERE e.school_year_id = :year_id AND e.status = \'enrolled\'
         ORDER BY g.id, sec.name, s.last_name
         LIMIT :limit OFFSET :offset'
    );
    $stmt->execute(['year_id' => $yearId, 'limit' => $paginated['per_page'], 'offset' => $paginated['offset']]);
    $enrollments = $stmt->fetchAll();
}

$unassigned = count(array_filter($enrollments, static fn ($row) => empty($row['section_id'])));

render_header('Enrollment Management', 'enrollment');
?>
<div class="stat-grid">
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">School Year</p>
        <div class="stat-value" style="font-size:1.4rem;"><?= e($activeYear['label'] ?? 'Not set') ?></div>
    </div>
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">Total Enrolled</p>
        <div class="stat-value"><?= count($enrollments) ?></div>
    </div>
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">Without Section</p>
        <div class="stat-value"><?= $unassigned ?></div>
    </div>
</div>

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
                    <th>Type</th>
                    <th>Enrolled</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$enrollments): ?>
                    <tr><td colspan="8" class="text-muted">No enrollments for the active school year.</td></tr>
                <?php endif; ?>
                <?php foreach ($enrollments as $row): ?>
                    <tr>
                        <td><?= e($row['student_id_no']) ?></td>
                        <td><?= e($row['student_name']) ?></td>
                        <td><?= e($row['lrn'] ?: '—') ?></td>
                        <td><?= e($row['grade_name']) ?></td>
                        <td>
                            <?php if ($row['section_name']): ?>
                                <?= e($row['section_name']) ?>
                            <?php else: ?>
                                <span class="badge badge-status-pending">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(ucfirst($row['enrollment_type'])) ?></td>
                        <td><?= e($row['enrolled_at']) ?></td>
                        <td><a class="btn btn-sm btn-outline-light" href="/modules/enrollment/assign.php?id=<?= (int) $row['id'] ?>">Assign</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($yearId > 0 && $paginated['last_page'] > 1): ?>
        <div class="p-3">
            <?= render_pager($paginated['current_page'], $paginated['last_page'], url('/modules/enrollment/index.php')) ?>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer();