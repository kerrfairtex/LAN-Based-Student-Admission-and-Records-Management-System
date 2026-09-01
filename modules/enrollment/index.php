<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

// ---- Read + sanitize filters ----
$filterQuery   = trim((string) ($_GET['q'] ?? ''));
$filterGradeId = (int) ($_GET['grade_id'] ?? 0);
$filterYearId  = (int) ($_GET['year_id'] ?? 0);
// 0 = unassigned, 1 = assigned; '' = any
$filterAssigned = $_GET['assigned'] ?? '';
$filterUnassigned = ($filterAssigned === '0');
$filterAssignedOnly = ($filterAssigned === '1');

$activeYear = active_school_year();
$defaultYearId = (int) ($activeYear['id'] ?? 0);
// The page is scoped to the active school year by default (matches prior
// behavior), but the filter lets the registrar look at other years too.
$yearId = $filterYearId > 0 ? $filterYearId : $defaultYearId;

$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

// ---- Build dynamic WHERE ----
// Base constraint: status = 'enrolled' AND school_year_id = :year_id.
// The year filter is non-optional (always applied) because enrollments
// without a school year are nonsensical.
$where = ['e.school_year_id = :year_id', "e.status = 'enrolled'"];
$params = ['year_id' => $yearId];

if ($filterGradeId > 0) {
    $where[] = 'e.grade_level_id = :grade_id';
    $params['grade_id'] = $filterGradeId;
}
if ($filterUnassigned) {
    $where[] = 'e.section_id IS NULL';
} elseif ($filterAssignedOnly) {
    $where[] = 'e.section_id IS NOT NULL';
}
if ($filterQuery !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filterQuery) . '%';
    $where[] = '(s.student_id_no ILIKE :q ESCAPE \'\\\' OR s.lrn ILIKE :q ESCAPE \'\\\' OR CONCAT(s.last_name, \' \', s.first_name, \' \', COALESCE(s.middle_name, \'\')) ILIKE :q ESCAPE \'\\\')';
    $params['q'] = $like;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);

$countSql = 'SELECT COUNT(*) AS c FROM enrollments e JOIN students s ON s.id = e.student_id' . $whereSql;
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

$paginated = paginate($total, $perPage, $currentPage);

$listSql =
    'SELECT e.id, e.section_id, e.enrollment_type, e.enrolled_at,
            s.id AS student_pk, s.student_id_no, s.lrn,
            CONCAT(s.last_name, \', \', s.first_name) AS student_name,
            g.id AS grade_level_id, g.name AS grade_name,
            sec.name AS section_name
     FROM enrollments e
     JOIN students s ON s.id = e.student_id
     JOIN grade_levels g ON g.id = e.grade_level_id
     LEFT JOIN sections sec ON sec.id = e.section_id'
    . $whereSql
    . ' ORDER BY g.id, sec.name, s.last_name
        LIMIT :limit OFFSET :offset';

$stmt = db()->prepare($listSql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit',  $paginated['per_page']);
$stmt->bindValue('offset', $paginated['offset']);
$stmt->execute();
$enrollments = $stmt->fetchAll();

$unassigned = count(array_filter($enrollments, static fn ($row) => empty($row['section_id'])));

$gradeLevels = fetch_grade_levels();
$schoolYears = fetch_school_years();

$hasFilters = $filterQuery !== '' || $filterGradeId > 0 || $filterUnassigned || $filterAssignedOnly || $filterYearId > 0;

render_header('Enrollment Management', 'enrollment');
?>
<div class="stat-grid">
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">School Year</p>
        <div class="stat-value" style="font-size:1.4rem;"><?= e($activeYear['label'] ?? 'Not set') ?></div>
    </div>
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">Total Enrolled</p>
        <div class="stat-value"><?= (int) $total ?></div>
    </div>
    <div class="stat-card glass-panel">
        <p class="mb-1 text-muted">Without Section (this page)</p>
        <div class="stat-value"><?= (int) $unassigned ?></div>
    </div>
</div>

<div class="panel-card glass-panel my-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">Search</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filterQuery) ?>" placeholder="ID, LRN, or name">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Grade</label>
            <select name="grade_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($gradeLevels as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= $filterGradeId === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">School Year</label>
            <select name="year_id" class="form-select form-select-sm">
                <?php foreach ($schoolYears as $y): ?>
                    <option value="<?= (int) $y['id'] ?>" <?= $yearId === (int) $y['id'] ? 'selected' : '' ?>><?= e($y['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Section</label>
            <select name="assigned" class="form-select form-select-sm">
                <option value="" <?= !$filterAssignedOnly && !$filterUnassigned ? 'selected' : '' ?>>All</option>
                <option value="1" <?= $filterAssignedOnly ? 'selected' : '' ?>>Assigned</option>
                <option value="0" <?= $filterUnassigned ? 'selected' : '' ?>>Unassigned</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
        <?php if ($hasFilters): ?>
            <div class="col-12">
                <a href="<?= e(url('/modules/enrollment/index.php')) ?>" class="btn btn-outline-light btn-sm">Clear filters</a>
            </div>
        <?php endif; ?>
    </form>
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
                    <tr><td colspan="8" class="text-muted">No enrollments<?= $hasFilters ? ' match the current filters' : ' for the active school year' ?>.</td></tr>
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
                        <td><a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/enrollment/assign.php?id=' . (int) $row['id'])) ?>">Assign</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginated['last_page'] > 1): ?>
        <div class="p-3">
            <?= render_pager($paginated['current_page'], $paginated['last_page'], url('/modules/enrollment/index.php')) ?>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer();