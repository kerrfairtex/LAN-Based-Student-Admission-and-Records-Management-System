<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_registrar();

$perPage = 20;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));

// ---- Read filter inputs (defensive against tampered URLs) ----
$filterUserId     = (int) ($_GET['user_id'] ?? 0);
$filterEntityType = trim((string) ($_GET['entity_type'] ?? ''));
$filterFrom       = trim((string) ($_GET['from'] ?? ''));
$filterTo         = trim((string) ($_GET['to'] ?? ''));

// Validate date inputs against YYYY-MM-DD. Empty stays empty; anything else
// that doesn't parse is dropped silently (treated as no filter on that side).
$dateRe = '/^\d{4}-\d{2}-\d{2}$/';
$fromValid = ($filterFrom !== '' && preg_match($dateRe, $filterFrom)) ? $filterFrom : '';
$toValid   = ($filterTo   !== '' && preg_match($dateRe, $filterTo))   ? $filterTo   : '';

// Whitelist entity_type against known values from the codebase (audit_log
// calls). Unknown values are ignored to avoid building a useless query.
$allowedEntityTypes = [
    'admissions', 'enrollments', 'students', 'student_records', 'sf10_records',
    'transfer_requests', 'users', 'audit_logs', 'lis_imports', 'app_settings',
];
$entityTypeValid = in_array($filterEntityType, $allowedEntityTypes, true) ? $filterEntityType : '';

// ---- Build dynamic WHERE ----
$where = [];
$params = [];
if ($filterUserId > 0) {
    $where[] = 'a.user_id = :user_id';
    $params['user_id'] = $filterUserId;
}
if ($entityTypeValid !== '') {
    $where[] = 'a.entity_type = :entity_type';
    $params['entity_type'] = $entityTypeValid;
}
if ($fromValid !== '') {
    $where[] = 'a.created_at >= :from_ts';
    $params['from_ts'] = $fromValid . ' 00:00:00';
}
if ($toValid !== '') {
    $where[] = 'a.created_at <= :to_ts';
    $params['to_ts'] = $toValid . ' 23:59:59';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// ---- Count + paginate ----
$countStmt = db()->prepare('SELECT COUNT(*) AS c FROM audit_logs a' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetch()['c'];

$paginated = paginate($total, $perPage, $currentPage);

// ---- Main query ----
$listSql =
    'SELECT a.*, u.full_name, u.username
     FROM audit_logs a
     JOIN users u ON u.id = a.user_id'
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
$logs = $stmt->fetchAll();

// ---- Filter-form dropdown sources ----
$users = db()->query('SELECT id, full_name, username FROM users ORDER BY full_name')->fetchAll();

// Distinct entity_types actually present in audit_logs (so the dropdown
// reflects real data rather than only the whitelist above).
$entityTypesPresent = db()->query(
    'SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type'
)->fetchAll(PDO::FETCH_COLUMN);

$hasFilters = $filterUserId > 0 || $entityTypeValid !== '' || $fromValid !== '' || $toValid !== '';

// Current retention window (for display + button copy).
$retentionDays = 1825;
try {
    $stmt = db()->prepare(
        "SELECT setting_value FROM trac_jhs_sarms.app_settings WHERE setting_key = 'audit_retention_days'"
    );
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val !== false && ctype_digit($val) && (int) $val > 0) {
        $retentionDays = (int) $val;
    }
} catch (PDOException $e) {
    // app_settings table may not have the row yet (migration not applied) —
    // fall back to the function's built-in default.
}

// Manual retention trigger (registrar only — already gated by require_registrar above).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_retention') {
    require_csrf();
    try {
        $deleted = (int) db()->query(
            'SELECT trac_jhs_sarms.purge_old_audit_logs()'
        )->fetchColumn();

        audit_log(
            'maintenance',
            'audit_logs',
            null,
            "Manual retention purge: deleted {$deleted} rows (window={$retentionDays}d)"
        );

        flash(
            'success',
            "Retention purge complete. {$deleted} audit-log row(s) older than {$retentionDays} days removed."
        );
    } catch (PDOException $e) {
        flash(
            'danger',
            'Retention purge failed. Is database/migrations/004_audit_retention.sql applied? Error: ' . $e->getMessage()
        );
    }
    redirect('/modules/admin/audit.php');
}

render_header('Audit Log', 'audit');
?>
<p class="text-muted">Tracks sensitive actions for institutional accountability (Data Privacy Act compliance).</p>

<div class="panel-card glass-panel mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= e($fromValid) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= e($toValid) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">User</label>
            <select name="user_id" class="form-select form-select-sm">
                <option value="">All users</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= $filterUserId === (int) $u['id'] ? 'selected' : '' ?>>
                        <?= e($u['full_name']) ?> (<?= e($u['username']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Entity</label>
            <select name="entity_type" class="form-select form-select-sm">
                <option value="">All entities</option>
                <?php foreach ($entityTypesPresent as $et): ?>
                    <option value="<?= e($et) ?>" <?= $entityTypeValid === $et ? 'selected' : '' ?>>
                        <?= e($et) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
        <?php if ($hasFilters): ?>
            <div class="col-12">
                <a href="<?= e(url('/modules/admin/audit.php')) ?>" class="btn btn-outline-light btn-sm">Clear filters</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<div class="panel-card glass-panel mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-muted">
            Retention policy: <strong><?= (int) $retentionDays ?></strong> days
            (<?= (int) ($retentionDays / 365) ?> year<?= $retentionDays >= 730 ? 's' : '' ?>).
            Rows older than this are purged from <code>trac_jhs_sarms.audit_logs</code>.
        </div>
        <form method="post" class="m-0"
              onsubmit="return confirm('Permanently delete all audit-log rows older than <?= (int) $retentionDays ?> days? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_retention">
            <button type="submit" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-archive"></i> Run Retention Now
            </button>
        </form>
    </div>
</div>

<div class="table-card glass-panel">
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="6" class="text-muted">No audit log entries<?= $hasFilters ? ' match the current filters' : ' yet' ?>.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['created_at']) ?></td>
                        <td><?= e($log['full_name']) ?></td>
                        <td><?= e($log['action']) ?></td>
                        <td><?= e($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></td>
                        <td><?= e($log['details'] ?? '') ?></td>
                        <td><?= e($log['ip_address'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginated['last_page'] > 1): ?>
        <div class="p-3">
            <?= render_pager($paginated['current_page'], $paginated['last_page'], url('/modules/admin/audit.php')) ?>
        </div>
    <?php endif; ?>
</div>
<?php
render_footer();