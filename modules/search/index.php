<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$query = trim($_GET['q'] ?? '');
$results = [];

if ($query !== '') {
    $like = '%' . $query . '%';
    $stmt = db()->prepare(
        'SELECT s.*, g.name AS grade_name, sy.label AS school_year
         FROM students s
         LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = "enrolled"
         LEFT JOIN school_years sy ON sy.id = e.school_year_id AND sy.is_active = 1
         LEFT JOIN grade_levels g ON g.id = e.grade_level_id
         WHERE s.student_id_no LIKE :q
            OR s.lrn LIKE :q
            OR CONCAT(s.first_name, " ", s.middle_name, " ", s.last_name) LIKE :q
            OR CONCAT(s.last_name, ", ", s.first_name) LIKE :q
         ORDER BY s.last_name, s.first_name
         LIMIT 50'
    );
    $stmt->execute(['q' => $like]);
    $results = $stmt->fetchAll();

    audit_log('search', 'students', null, "Search query: {$query}");
}

render_header('Search & Inquiry', 'search');
?>
<div class="panel-card glass-panel mb-3">
  <form method="get" class="row g-2 align-items-end">
    <div class="col-md-9">
      <label class="form-label" for="q">Search by Student ID, LRN, or Name</label>
      <input type="text" id="q" name="q" class="form-control" value="<?= e($query) ?>" placeholder="e.g. TRAC-2026-0001 or Juan Dela Cruz" autofocus>
    </div>
    <div class="col-md-3">
      <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Search</button>
    </div>
  </form>
</div>

<?php if ($query !== ''): ?>
<div class="table-card glass-panel">
  <h3>Results for "<?= e($query) ?>"</h3>
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
            <td><a class="btn btn-sm btn-outline-light" href="<?= e(url('/modules/records/view.php?id=<?= (int) $student[\'id\'] ?>')) ?>">Open Record</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php
render_footer();
