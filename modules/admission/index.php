<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

// ---- Read + sanitize filters ----
$filterQuery    = trim((string) ($_GET['q'] ?? ''));
$filterStatus   = trim((string) ($_GET['status'] ?? ''));
$filterYearId   = (int) ($_GET['year_id'] ?? 0);
$filterGradeId  = (int) ($_GET['grade_id'] ?? 0);
$filterType     = trim((string) ($_GET['type'] ?? ''));

$allowedStatuses = ['pending', 'approved', 'rejected'];
$statusValid = in_array($filterStatus, $allowedStatuses, true) ? $filterStatus : '';

$allowedTypes = ['new', 'returning', 'transferee'];
$typeValid = in_array($filterType, $allowedTypes, true) ? $filterType : '';

$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

// ---- Build dynamic WHERE ----
$where = ['1=1'];
$params = [];
if ($statusValid !== '') {
    $where[] = 'a.status = :status';
    $params['status'] = $statusValid;
}
if ($filterYearId > 0) {
    $where[] = 'a.school_year_id = :year_id';
    $params['year_id'] = $filterYearId;
}
if ($filterGradeId > 0) {
    $where[] = 'a.grade_level_id = :grade_id';
    $params['grade_id'] = $filterGradeId;
}
if ($typeValid !== '') {
    $where[] = 'a.enrollment_type = :type';
    $params['type'] = $typeValid;
}
if ($filterQuery !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filterQuery) . '%';
    $where[] = '(a.application_no ILIKE :q ESCAPE \'\\\' OR a.lrn ILIKE :q ESCAPE \'\\\' OR CONCAT(a.last_name, \' \', a.first_name, \' \', COALESCE(a.middle_name, \'\')) ILIKE :q ESCAPE \'\\\')';
    $params['q'] = $like;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);

// ---- Count + paginate ----
$countStmt = db()->prepare('SELECT COUNT(*) AS c FROM admissions a' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

$paginated = paginate($total, $perPage, $currentPage);

// ---- Main query ----
$listSql =
    'SELECT a.*, sy.label AS school_year, g.name AS grade_name, u.full_name AS encoder_name
     FROM admissions a
     JOIN school_years sy ON sy.id = a.school_year_id
     JOIN grade_levels g ON g.id = a.grade_level_id
     JOIN users u ON u.id = a.created_by'
    . $whereSql
    . ' ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset';

$stmt = db()->prepare($listSql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit',  $paginated['per_page']);
$stmt->bindValue('offset', $paginated['offset']);
$stmt->execute();
$admissions = $stmt->fetchAll();

$schoolYears = fetch_school_years();
$gradeLevels = fetch_grade_levels();

$hasFilters = $filterQuery !== '' || $statusValid !== '' || $typeValid !== '' || $filterYearId > 0 || $filterGradeId > 0;

render_header('Admission', 'admission');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Digital encoding of new student applications with real-time validation.</p>
    <a href="<?= e(url('/modules/admission/create.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-jg"></i> New Application</a>
</div>

<div class="panel-card glass-panel mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">Search</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filterQuery) ?>" placeholder="App no, LRN, or name">
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
            <label class="form-label small mb-1">Type</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($allowedTypes as $t): ?>
                    <option value="<?= e($t) ?>" <?= $typeValid === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
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
        <div class="col-md-1">
            <label class="form-label small mb-1">Grade</label>
            <select name="grade_id" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($gradeLevels as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= $filterGradeId === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
        <?php if ($hasFilters): ?>
            <div class="col-12">
                <a href="<?= e(url('/modules/admission/index.php')) ?>" class="btn btn-outline-light btn-sm">Clear filters</a>
            </div>
        <?php endif; ?>
    </form>
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
                    <tr><td colspan="9" class="text-muted">No admission applications<?= $hasFilters ? ' match the current filters' : ' yet' ?>.</td></tr>
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