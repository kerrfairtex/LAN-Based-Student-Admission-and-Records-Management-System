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

if ($filterQuery !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filterQuery) . '%';

    $where = [
        '(s.student_id_no ILIKE :q ESCAPE \'\\\' OR s.lrn ILIKE :q ESCAPE \'\\\' OR CONCAT(s.first_name, \' \', s.middle_name, \' \', s.last_name) ILIKE :q ESCAPE \'\\\' OR CONCAT(s.last_name, \', \', s.first_name) ILIKE :q ESCAPE \'\\\')'
    ];
    $params = ['q' => $like];
    if ($statusValid !== '') {
        $where[] = 's.status = :status';
        $params['status'] = $statusValid;
    }

    $sql =
        'SELECT s.*, g.name AS grade_name, sy.label AS school_year
         FROM students s
         LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = \'enrolled\'
         LEFT JOIN school_years sy ON sy.id = e.school_year_id AND sy.is_active = 1
         LEFT JOIN grade_levels g ON g.id = e.grade_level_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY s.last_name, s.first_name
         LIMIT 50';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    audit_log('search', 'students', null, "Search query: {$filterQuery}" . ($statusValid !== '' ? " (status={$statusValid})" : ''));
}

$hasFilters = $filterQuery !== '' || $statusValid !== '';

render_header('Search & Inquiry', 'search');
?>
<div class="panel-card glass-panel mb-3">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-7">
      <label class="form-label small mb-1" for="q">Search</label>
      <input type="text" id="q" name="q" class="form-control form-control-sm" value="<?= e($filterQuery) ?>" placeholder="Student ID, LRN, or name" autofocus>
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
  <h3>Results for "<?= e($filterQuery) ?>"<?= $statusValid !== '' ? ' (status: ' . e(ucfirst($statusValid)) . ')' : '' ?></h3>
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
            <td><?= e($student['grade_name'] ?: '—') ?></td>
            <td><?= e(ucfirst($student['status'])) ?></td>
            <td><a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/records/view.php?id=' . (int) $student['id'])) ?>">Open Record</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php
render_footer();