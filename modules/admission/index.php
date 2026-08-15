<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

// Count total
$totalStmt = db()->query('SELECT COUNT(*) AS c FROM admissions');
$total = (int) $totalStmt->fetch()['c'];

$paginated = paginate($total, $perPage, $currentPage);

$stmt = db()->prepare(
    'SELECT a.*, sy.label AS school_year, g.name AS grade_name, u.full_name AS encoder_name
     FROM admissions a
     JOIN school_years sy ON sy.id = a.school_year_id
     JOIN grade_levels g ON g.id = a.grade_level_id
     JOIN users u ON u.id = a.created_by
     ORDER BY a.created_at DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->execute(['limit' => $paginated['per_page'], 'offset' => $paginated['offset']]);
$admissions = $stmt->fetchAll();

render_header('Admission', 'admission');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Digital encoding of new student applications with real-time validation.</p>
    <a href="<?= e(url('/modules/admission/create.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Application</a>
</div>

<div class="table-card glass-panel">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Application No.</th>
                    <th>Applicant</th>
                    <th>LRN</th>
                    <th>Grade</th>
                    <th>School Year</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Encoded By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$admissions): ?>
                    <tr><td colspan="9" class="text-muted">No admission applications yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($admissions as $row): ?>
                    <tr>
                        <td><?= e($row['application_no']) ?></td>
                        <td><?= e(trim("{$row['last_name']}, {$row['first_name']} {$row['middle_name']}")) ?></td>
                        <td><?= e($row['lrn'] ?: '—') ?></td>
                        <td><?= e($row['grade_name']) ?></td>
                        <td><?= e($row['school_year']) ?></td>
                        <td><?= e(ucfirst($row['enrollment_type'])) ?></td>
                        <td><span class="badge badge-status-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                        <td><?= e($row['encoder_name']) ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/admission/view.php?id=' . (int) $row['id'])) ?>">View</a>
                            <?php if ($row['status'] === 'pending'): ?>
                                <a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/admission/edit.php?id=' . (int) $row['id'])) ?>">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginated['last_page'] > 1): ?>
        <div class="p-3">
            <?= render_pager($paginated['current_page'], $paginated['last_page'], url('/modules/admission/index.php')) ?>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer();