<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_registrar();

// ---- Read inquiry ID ----

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid inquiry ID.');
    redirect('/modules/admin/inquiries.php');
}

// ---- Load inquiry ----

$stmt = db()->prepare('SELECT id, full_name, grade, contact_number, status, created_at, updated_at FROM inquiries WHERE id = :id');
$stmt->execute(['id' => $id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    flash('danger', 'Inquiry not found.');
    redirect('/modules/admin/inquiries.php');
}

// ---- Handle status update (POST) ----

$allowedStatuses = ['new', 'processed', 'spam', 'duplicate'];
$statusLabels = [
    'new'       => 'New',
    'processed' => 'Processed',
    'spam'      => 'Spam',
    'duplicate' => 'Duplicate',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $newStatus = trim((string) ($_POST['status'] ?? ''));

    if ($newStatus === '' || !in_array($newStatus, $allowedStatuses, true)) {
        flash('danger', 'Invalid status selected.');
        redirect('/modules/admin/inquiry.php?id=' . $id);
    }

    $oldStatus = $inquiry['status'];

    if ($oldStatus === $newStatus) {
        flash('info', 'Status is already "' . $statusLabels[$newStatus] . '".');
        redirect('/modules/admin/inquiry.php?id=' . $id);
    }

    try {
        $stmt = db()->prepare('UPDATE inquiries SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $newStatus, 'id' => $id]);

        // Reload to get updated updated_at
        $stmt = db()->prepare('SELECT id, full_name, grade, contact_number, status, created_at, updated_at FROM inquiries WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $inquiry = $stmt->fetch();

        // Audit log
        audit_log(
            'status_change',
            'inquiries',
            (int) $inquiry['id'],
            "Changed status from \"{$statusLabels[$oldStatus]}\" to \"{$statusLabels[$newStatus]}\" for inquiry #{$inquiry['id']} ({$inquiry['full_name']})"
        );

        flash('success', 'Status updated to "' . $statusLabels[$newStatus] . '".');
        redirect('/modules/admin/inquiry.php?id=' . $id);
    } catch (Throwable $e) {
        error_log('inquiry status update failed: ' . $e->getMessage());
        flash('danger', 'Failed to update status. Please try again.');
        redirect('/modules/admin/inquiry.php?id=' . $id);
    }
}

// ---- Build status form URL preserving GET filters ----

$referrer = $_SERVER['HTTP_REFERER'] ?? '/modules/admin/inquiries.php';
// If coming from the list page, preserve filter params
parse_str(parse_url($referrer, PHP_URL_QUERY), $refParams);
$validFilterKeys = ['q', 'status', 'grade', 'page'];
$filterParams = [];
foreach ($validFilterKeys as $key) {
    if (isset($refParams[$key])) {
        $filterParams[$key] = $refParams[$key];
    }
}
$filterQuery = $filterParams ? '?' . http_build_query($filterParams) : '';

// ---- Render ----

render_header('Inquiry #' . $inquiry['id'], 'inquiries');
?>
<div class="page-section">
    <a href="<?= e(url('/modules/admin/inquiries.php' . $filterQuery)) ?>" class="btn btn-sm btn-outline-light mb-3">
        <i class="bi bi-arrow-left"></i> Back to Inquiries
    </a>

    <div class="card glass-panel">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                <div>
                    <h3 class="mb-1">Inquiry #<?= (int) $inquiry['id'] ?></h3>
                    <p class="text-muted mb-0">Submitted <?= date('M j, Y g:i A', strtotime($inquiry['created_at'])) ?></p>
                </div>
                <span class="badge-status badge-status-<?= $inquiry['status'] ?>"><?= strtoupper($inquiry['status']) ?></span>
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Full Name</label>
                        <p class="mb-0 fs-5"><?= e($inquiry['full_name']) ?></p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Grade Level</label>
                        <p class="mb-0 fs-5"><?= e($inquiry['grade']) ?></p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Contact Number</label>
                        <p class="mb-0 fs-5"><?= e($inquiry['contact_number']) ?></p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Status</label>
                        <p class="mb-0 fs-5">
                            <span class="badge-status badge-status-<?= $inquiry['status'] ?>"><?= strtoupper($inquiry['status']) ?></span>
                        </p>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <h5 class="mb-3">Change Status</h5>
            <p class="text-muted small mb-3">Update the classification of this inquiry. The record is preserved regardless of classification.</p>

            <form method="post" action="<?= e(url('/modules/admin/inquiry.php?id=' . (int) $inquiry['id'])) ?>" class="row g-3 align-items-end">
                <?= csrf_field() ?>
                <div class="col-12 col-sm-6">
                    <label for="status-select" class="form-label">New Status</label>
                    <select name="status" id="status-select" class="form-select form-select-sm" required>
                        <option value="">-- Select status --</option>
                        <?php foreach ($allowedStatuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= $inquiry['status'] === $s ? 'selected' : '' ?>>
                                <?= $statusLabels[$s] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-check-lg"></i> Save Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card glass-panel mt-3">
        <div class="card-body">
            <h6 class="mb-2">Timeline</h6>
            <ul class="list-unstyled small text-muted mb-0">
                <li><strong>Submitted:</strong> <?= date('M j, Y g:i A', strtotime($inquiry['created_at'])) ?></li>
                <li><strong>Last Updated:</strong> <?= date('M j, Y g:i A', strtotime($inquiry['updated_at'])) ?></li>
            </ul>
        </div>
    </div>
</div>

<?php render_footer(); ?>
