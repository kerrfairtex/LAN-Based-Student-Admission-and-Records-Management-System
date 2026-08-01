<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/transfers.php';

require_login();

$direction = $_GET['direction'] ?? '';
$transfers = fetch_transfer_requests($direction ?: null);
$overdue = overdue_transfer_count();

render_header('Transfer Requests', 'transfers');
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <p class="text-muted mb-0">
        DepEd DO 54-2016: school records must be secured within <strong>30 days</strong> of first attendance.
        <?php if ($overdue > 0): ?>
            <span class="badge badge-status-rejected ms-1"><?= $overdue ?> overdue</span>
        <?php endif; ?>
    </p>
    <div class="d-flex gap-2">
        <a href="/modules/transfers/create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Request</a>
    </div>
</div>

<div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="/modules/transfers/index.php" class="btn btn-sm <?= $direction === '' ? 'btn-primary' : 'btn-outline-light' ?>">All</a>
    <a href="/modules/transfers/index.php?direction=incoming" class="btn btn-sm <?= $direction === 'incoming' ? 'btn-primary' : 'btn-outline-light' ?>">Incoming</a>
    <a href="/modules/transfers/index.php?direction=outgoing" class="btn btn-sm <?= $direction === 'outgoing' ? 'btn-primary' : 'btn-outline-light' ?>">Outgoing</a>
</div>

<div class="table-card glass-panel">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Direction</th>
                    <th>Counterpart School</th>
                    <th>Request Date</th>
                    <th>Due Date</th>
                    <th>SLA</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$transfers): ?>
                    <tr><td colspan="8" class="text-muted">No transfer requests recorded.</td></tr>
                <?php endif; ?>
                <?php foreach ($transfers as $row): ?>
                    <?php
                    $overdueRow = transfer_is_overdue($row);
                    $daysLeft = transfer_days_remaining($row);
                    ?>
                    <tr>
                        <td>
                            <?= e($row['student_name']) ?><br>
                            <small class="text-muted"><?= e($row['student_id_no']) ?></small>
                        </td>
                        <td><?= e(ucfirst($row['direction'])) ?></td>
                        <td><?= e($row['counterpart_school']) ?></td>
                        <td><?= e($row['request_date']) ?></td>
                        <td><?= e($row['due_date']) ?></td>
                        <td>
                            <?php if ($row['status'] === 'completed'): ?>
                                <span class="badge badge-status-approved">Done</span>
                            <?php elseif ($overdueRow): ?>
                                <span class="badge badge-status-rejected">Overdue</span>
                            <?php else: ?>
                                <span class="badge badge-status-pending"><?= $daysLeft ?>d left</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(transfer_status_label($row['status'])) ?></td>
                        <td><a class="btn btn-sm btn-outline-light" href="/modules/transfers/view.php?id=<?= (int) $row['id'] ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
render_footer();
