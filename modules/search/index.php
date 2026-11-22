<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

// ---- Read + sanitize filters ----
$filterQuery  = trim((string) ($_GET['q'] ?? ''));
$filterStatus = trim((string) ($_GET['status'] ?? ''));

$allowedStatuses = ['active', 'transferred', 'graduated', 'dropped'];
$statusValid = in_array($filterStatus, $allowedStatuses, true) ? $filterStatus : '';

$results = [];
$total = 0;
$perPage = 25;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$paginated = paginate(0, $perPage, $currentPage);

if ($filterQuery !== '' || $statusValid !== '') {
    $where = [];
    $params = [];
    if ($filterQuery !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filterQuery) . '%';
        $where[] = '(s.student_id_no ILIKE :q OR s.lrn ILIKE :q OR CONCAT_WS(\' \', s.first_name, s.middle_name, s.last_name) ILIKE :q OR CONCAT(s.last_name, \', \', s.first_name) ILIKE :q)';
        $params['q'] = $like;
    }
    if ($statusValid !== '') {
        $where[] = 's.status = :status';
        $params['status'] = $statusValid;
    }
    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $count = db()->prepare('SELECT COUNT(*) FROM trac_jhs_sarms.students s' . $whereSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $paginated = paginate($total, $perPage, $currentPage);

    // Grade/section from the enrollment in the selected school year only.
    $selectSql =
        'SELECT s.*, g.name AS grade_name, sec.name AS section_name
         FROM trac_jhs_sarms.students s
         LEFT JOIN trac_jhs_sarms.enrollments e
                ON e.student_id = s.id AND e.school_year_id = :year_id AND e.status = \'enrolled\'
         LEFT JOIN trac_jhs_sarms.grade_levels g ON g.id = e.grade_level_id
         LEFT JOIN trac_jhs_sarms.sections sec ON sec.id = e.section_id'
        . $whereSql . '
         ORDER BY s.last_name, s.first_name, s.id';
    $params['year_id'] = (int) (active_school_year()['id'] ?? 0);

    if (wants_csv()) {
        $stmt = db()->prepare($selectSql);
        $stmt->execute($params);
        send_csv(
            'student_search.csv',
            ['Student ID', 'LRN', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Birthdate', 'Grade', 'Section', 'Status'],
            array_map(static fn ($r) => [
                $r['student_id_no'], $r['lrn'], $r['last_name'], $r['first_name'], $r['middle_name'],
                $r['sex'], $r['birthdate'], $r['grade_name'], $r['section_name'], ucfirst($r['status']),
            ], $stmt->fetchAll()),
            "Search CSV: q={$filterQuery}" . ($statusValid !== '' ? ", status={$statusValid}" : '') . " ({$total} rows)"
        );
    }

    $stmt = db()->prepare($selectSql . ' LIMIT ' . (int) $paginated['per_page'] . ' OFFSET ' . (int) $paginated['offset']);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    if ($currentPage === 1) {
        audit_log('search', 'students', null, "Search query: {$filterQuery}" . ($statusValid !== '' ? " (status={$statusValid})" : ''));
    }
}

$hasFilters = $filterQuery !== '' || $statusValid !== '';

render_header('Search & Inquiry', 'search');
?>
<div class="panel-card glass-panel mb-3">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-7">
      <label class="form-label small mb-1" for="q">Search</label>
      <input type="text" id="q" name="q" class="form-control form-control-sm" value="<?= e($filterQuery) ?>" placeholder="Student ID, LRN, or name (or pick a status only)" autofocus>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">Status</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Any</option>
        <?php foreach ($allowedStatuses as $s): ?>
          <option value="<?= e($s) ?>" <?= $statusValid === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-1">
      <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Search</button>
    </div>
    <?php if ($hasFilters): ?>
      <div class="col-12">
        <a href="<?= e(url('/modules/search/index.php')) ?>" class="btn btn-outline-light btn-sm">Clear filters</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php if ($hasFilters): ?>
<div class="table-card glass-panel">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <h3 class="mb-0"><?= $filterQuery !== '' ? 'Results for "' . e($filterQuery) . '"' : 'All students' ?><?= $statusValid !== '' ? ' (status: ' . e(ucfirst($statusValid)) . ')' : '' ?>
      <small class="text-muted">&middot; <?= $total ?> found</small></h3>
    <?php if ($total > 0): ?>
      <a href="<?= e(csv_url('/modules/search/index.php')) ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-filetype-csv" aria-hidden="true"></i> Export CSV</a>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Name</th>
          <th>LRN</th>
          <th>Grade</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$results): ?>
          <tr><td colspan="6" class="text-muted">No matching records found.</td></tr>
        <?php endif; ?>
        <?php foreach ($results as $student): ?>
          <tr>
            <td><?= e($student['student_id_no']) ?></td>
            <td><?= e(trim("{$student['last_name']}, {$student['first_name']} {$student['middle_name']}")) ?></td>
            <td><?= e($student['lrn'] ?: '—') ?></td>
            <td><?= e($student['grade_name'] ? $student['grade_name'] . ($student['section_name'] ? ' - ' . $student['section_name'] : '') : '—') ?></td>
            <td><?= e(ucfirst($student['status'])) ?></td>
            <td><a class="btn btn-view-red btn-sm" href="<?= e(url('/modules/records/view.php?id=' . (int) $student['id'])) ?>">Open Record</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($paginated['last_page'] > 1): ?>
    <div class="p-3"><?= render_pager($paginated['current_page'], $paginated['last_page'], url('/modules/search/index.php')) ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
render_footer();