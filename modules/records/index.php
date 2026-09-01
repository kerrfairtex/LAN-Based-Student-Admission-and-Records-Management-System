<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

// ---- Read + sanitize filters ----
$filterQuery    = trim((string) ($_GET['q'] ?? ''));
$filterStatus   = trim((string) ($_GET['status'] ?? ''));
$filterGradeId  = (int) ($_GET['grade_id'] ?? 0);
$filterYearId   = (int) ($_GET['year_id'] ?? 0);

// Status whitelist matches schema CHECK ('active', 'transferred', 'graduated', 'dropped').
$allowedStatuses = ['active', 'transferred', 'graduated', 'dropped'];
$statusValid = in_array($filterStatus, $allowedStatuses, true) ? $filterStatus : '';

$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

// ---- Build dynamic WHERE ----
$where = ['1=1'];
$params = [];
if ($filterStatus !== '') {
    $where[] = 's.status = :status';
    $params['status'] = $filterStatus;
}
if ($filterGradeId > 0) {
    $where[] = 'e.grade_level_id = :grade_id';
    $params['grade_id'] = $filterGradeId;
}
if ($filterYearId > 0) {
    $where[] = 'e.school_year_id = :year_id';
    $params['year_id'] = $filterYearId;
}
if ($filterQuery !== '') {
    // ILIKE for Postgres case-insensitive search. Pattern escaped so '%' and '_'
    // in the user query aren't treated as wildcards.
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filterQuery) . '%';
    $where[] = '(s.student_id_no ILIKE :q ESCAPE \'\\\' OR s.lrn ILIKE :q ESCAPE \'\\\' OR CONCAT(s.last_name, \' \', s.first_name, \' \', COALESCE(s.middle_name, \'\')) ILIKE :q ESCAPE \'\\\')';
    $params['q'] = $like;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);

// ---- Count + paginate ----
$countSql =
    'SELECT COUNT(DISTINCT s.id) AS c
     FROM students s
     LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = \'enrolled\''
    . $whereSql;

$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

$paginated = paginate($total, $perPage, $currentPage);

// ---- Main query (DISTINCT because a student could in principle have two
// enrollments — though the uniq_student_school_year index prevents that
// in practice; the DISTINCT is a safety net). ----
$listSql =
    'SELECT DISTINCT ON (s.id) s.*,
            e.grade_level_id, g.name AS grade_name,
            sy.label AS school_year, sy.id AS school_year_id,
            sec.name AS section_name
     FROM students s
     LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = \'enrolled\'
     LEFT JOIN school_years sy ON sy.id = e.school_year_id
     LEFT JOIN grade_levels g ON g.id = e.grade_level_id
     LEFT JOIN sections sec ON sec.id = e.section_id'
    . $whereSql
    . ' ORDER BY s.id, s.last_name, s.first_name
      LIMIT :limit OFFSET :offset';

$stmt = db()->prepare($listSql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit',  $paginated['per_page']);
$stmt->bindValue('offset', $paginated['offset']);
$stmt->execute();
$students = $stmt->fetchAll();

$gradeLevels = fetch_grade_levels();
$schoolYears = fetch_school_years();

$hasFilters = $filterQuery !== '' || $statusValid !== '' || $filterGradeId > 0 || $filterYearId > 0;

render_header('Records Management', 'records');
?>
<p class="text-muted">Retrieve, update, and archive student academic histories.</p>

<div class="panel-card glass-panel mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1">Search</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filterQuery) ?>" placeholder="ID, LRN, or name">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($allowedStatuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $statusValid === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
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
                <option value="">All</option>
                <?php foreach ($schoolYears as $y): ?>
                    <option value="<?= (int) $y['id'] ?>" <?= $filterYearId === (int) $y['id'] ? 'selected' : '' ?>><?= e($y['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
        <?php if ($hasFilters): ?>
            <div class="col-12">
                <a href="<?= e(url('/modules/records/index.php')) ?>" class="btn btn-outline-light btn-sm">Clear filters</a>
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
                    <th>School Year</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$students): ?>
                    <tr><td colspan="7" class="text-muted">No student records<?= $hasFilters ? ' match the current filters' : '' ?>.</td></tr>
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