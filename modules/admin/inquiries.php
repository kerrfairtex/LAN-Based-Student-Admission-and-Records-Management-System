<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_registrar();

// ---- Read filter inputs (defensive: cast/validate defensively) ----

$search     = trim((string) ($_GET['q'] ?? ''));
$status     = trim((string) ($_GET['status'] ?? ''));
$grade      = trim((string) ($_GET['grade'] ?? ''));
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

// ---- Whitelist status filter ----

$allowedStatuses = ['new', 'processed', 'spam', 'duplicate'];
$statusMap = [
    'new'       => 'New',
    'processed' => 'Processed',
    'spam'      => 'Spam',
    'duplicate' => 'Duplicate',
];

$statusFilter = ($status !== '' && in_array($status, $allowedStatuses, true))
    ? $status
    : '';

// ---- Whitelist grade filter ----

$allowedGrades = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'];
$gradeFilter = ($grade !== '' && in_array($grade, $allowedGrades, true))
    ? $grade
    : '';

// ---- Build WHERE + params ----

$where = [];
$params = [];

if ($search !== '') {
    // PostgreSQL ILIKE with safe escaping of user input.
    $escaped = str_replace(
        ['%', '_', '\\'],
        ['\\\\\\\\%', '\\\\\\\\%', '\\\\\\\\\\\\\\\\'],
        $search
    );
    $like = '%' . $escaped . '%';
    $where[] = '(full_name ILIKE :q OR contact_number ILIKE :q)';
    $params['q'] = $like;
}

if ($statusFilter !== '') {
    $where[] = 'status = :status';
    $params['status'] = $statusFilter;
}

if ($gradeFilter !== '') {
    $where[] = 'grade = :grade';
    $params['grade'] = $gradeFilter;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// ---- Count total matching rows ----

$countSql = 'SELECT COUNT(*) AS c FROM inquiries' . $whereSql;
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

// ---- Pagination ----

$perPage = 20;
$paginated = paginate($total, $perPage, $currentPage);

// ---- Main query ----

$listSql =
    'SELECT id, full_name, grade, contact_number, status, created_at, updated_at
     FROM inquiries'
    . $whereSql
    . ' ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset';

$stmt = db()->prepare($listSql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit',  $paginated['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $paginated['offset'],   PDO::PARAM_INT);
$stmt->execute();
$inquiries = $stmt->fetchAll();

// ---- Status distribution counts (for summary cards) ----

$counts = [];
foreach ($allowedStatuses as $s) {
    $cstmt = db()->prepare('SELECT COUNT(*) AS c FROM inquiries WHERE status = :s');
    $cstmt->execute(['s' => $s]);
    $counts[$s] = (int) $cstmt->fetch()['c'];
}

// ---- Base URL for pagination (preserves all current GET params except page) ----

$baseParams = $_GET;
unset($baseParams['page']);
$baseUrl = '/modules/admin/inquiries.php' . ($baseParams ? '?' . http_build_query($baseParams) : '');

// ---- Render ----

render_header('Inquiries', 'inquiries');
?>
<div class="page-section">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="mb-1">Inquiries</h2>
            <p class="text-muted mb-0">Public admission inquiries submitted through the website.</p>
        </div>
    </div>

    <!-- Summary count cards -->
    <div class="stat-grid">
        <div class="stat-card glass-panel">
            <div class="stat-icon stat-icon-gold"><i class="bi bi-inbox"></i></div>
            <p class="mb-1 text-muted">Total Inquiries</p>
            <div class="stat-value" data-count="<?= $total ?>">0</div>
        </div>
        <div class="stat-card glass-panel">
            <div class="stat-icon <?= $counts['new'] > 0 ? 'stat-icon-gold' : 'stat-icon-muted' ?>"><i class="bi bi-hourglass-split"></i></div>
            <p class="mb-1 text-muted">New</p>
            <div class="stat-value <?= $counts['new'] > 0 ? 'text-warning' : '' ?>" data-count="<?= $counts['new'] ?>">0</div>
        </div>
        <div class="stat-card glass-panel">
            <div class="stat-icon <?= $counts['processed'] > 0 ? 'stat-icon-green' : 'stat-icon-muted' ?>"><i class="bi bi-check-circle-fill"></i></div>
            <p class="mb-1 text-muted">Processed</p>
            <div class="stat-value <?= $counts['processed'] > 0 ? '' : 'text-muted' ?>" data-count="<?= $counts['processed'] ?>">0</div>
        </div>
        <div class="stat-card glass-panel">
            <div class="stat-icon <?= $counts['spam'] > 0 ? 'stat-icon-danger' : 'stat-icon-muted' ?>"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <p class="mb-1 text-muted">Spam</p>
            <div class="stat-value <?= $counts['spam'] > 0 ? 'text-danger' : '' ?>" data-count="<?= $counts['spam'] ?>">0</div>
        </div>
        <div class="stat-card glass-panel">
            <div class="stat-icon <?= $counts['duplicate'] > 0 ? 'stat-icon-muted' : 'stat-icon-muted' ?>"><i class="bi bi-file-earmark"></i></div>
            <p class="mb-1 text-muted">Duplicate</p>
            <div class="stat-value" data-count="<?= $counts['duplicate'] ?>">0</div>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" action="<?= e(url('/modules/admin/inquiries.php')) ?>" class="row g-3 align-items-end mb-4 filter-bar">
        <div class="col-12 col-sm-6 col-md-3">
            <label for="filter-q" class="form-label small text-muted">Search</label>
            <input type="search" name="q" id="filter-q"
                   class="form-control form-control-sm"
                   placeholder="Name or contact number"
                   value="<?= e($search) ?>">
        </div>
        <div class="col-6 col-sm-3 col-md-2">
            <label for="filter-status" class="form-label small text-muted">Status</label>
            <select name="status" id="filter-status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="new"       <?= $statusFilter === 'new'       ? 'selected' : '' ?>>New</option>
                <option value="processed" <?= $statusFilter === 'processed' ? 'selected' : '' ?>>Processed</option>
                <option value="spam"      <?= $statusFilter === 'spam'      ? 'selected' : '' ?>>Spam</option>
                <option value="duplicate" <?= $statusFilter === 'duplicate' ? 'selected' : '' ?>>Duplicate</option>
            </select>
        </div>
        <div class="col-6 col-sm-3 col-md-2">
            <label for="filter-grade" class="form-label small text-muted">Grade</label>
            <select name="grade" id="filter-grade" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="Grade 7" <?= $gradeFilter === 'Grade 7' ? 'selected' : '' ?>>Grade 7</option>
                <option value="Grade 8" <?= $gradeFilter === 'Grade 8' ? 'selected' : '' ?>>Grade 8</option>
                <option value="Grade 9" <?= $gradeFilter === 'Grade 9' ? 'selected' : '' ?>>Grade 9</option>
                <option value="Grade 10" <?= $gradeFilter === 'Grade 10' ? 'selected' : '' ?>>Grade 10</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-5 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-outline-light w-100">Apply Filters</button>
            <?php if ($search !== '' || $statusFilter !== '' || $gradeFilter !== ''): ?>
                <a href="<?= e(url('/modules/admin/inquiries.php')) ?>" class="btn btn-sm btn-outline-light w-100">Clear Filters</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">ID</th>
                    <th>Applicant</th>
                    <th style="width:110px">Grade</th>
                    <th style="width:140px">Contact</th>
                    <th style="width:120px">Status</th>
                    <th style="width:160px">Submitted</th>
                    <th style="width:100px" class="text-nowrap">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$inquiries): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <?php if ($search === '' && $statusFilter === '' && $gradeFilter === ''): ?>
                                <i class="bi bi-inbox" style="font-size:2rem;opacity:0.4;display:block;margin-bottom:0.5rem"></i>
                                No inquiries yet.
                            <?php else: ?>
                                <i class="bi bi-search" style="font-size:2rem;opacity:0.4;display:block;margin-bottom:0.5rem"></i>
                                No inquiries match the current filters.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($inquiries as $inq): ?>
                    <tr>
                        <td class="text-muted text-nowrap">#<?= (int) $inq['id'] ?></td>
                        <td>
                            <strong><?= e($inq['full_name']) ?></strong>
                        </td>
                        <td><?= e($inq['grade']) ?></td>
                        <td class="text-muted"><?= e($inq['contact_number']) ?></td>
                        <td>
                            <?php
                            $badgeClass = 'badge-status-' . $inq['status'];
                            ?>
                            <span class="badge-status <?= $badgeClass ?>"><?= strtoupper($inq['status']) ?></span>
                        </td>
                        <td class="text-muted text-nowrap small">
                            <?= $inq['created_at'] !== null ? e(date('M j, Y H:i', strtotime($inq['created_at']))) : '—' ?>
                        </td>
                        <td>
                            <a href="<?= e(url('/modules/admin/inquiry.php?id=' . (int) $inq['id'])) ?>"
                               class="btn btn-sm btn-view-red">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($paginated['last_page'] > 1): ?>
        <div class="mt-3">
            <?= render_pager($paginated['current_page'], $paginated['last_page'], $baseUrl) ?>
        </div>
    <?php endif; ?>

    <p class="text-muted small mt-3">
        Showing <?= count($inquiries) ?> of <?= $total ?> inquiry<?= $total === 1 ? '' : 'es' ?>.
    </p>
</div>

<?php render_footer(); ?>
