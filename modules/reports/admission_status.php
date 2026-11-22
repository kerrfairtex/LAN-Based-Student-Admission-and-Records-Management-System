<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

// Filters: school year (default: active), status, and "missing requirements
// only" so the registrar can follow up on PSA/SF9/etc. still outstanding.
$activeYear = active_school_year();
$yearParam = (string) ($_GET['school_year_id'] ?? ($activeYear['id'] ?? ''));
$yearId = $yearParam === 'all' ? 0 : (int) $yearParam;
$status = (string) ($_GET['status'] ?? '');
$status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : '';
$missingOnly = ($_GET['missing'] ?? '') === '1';

$where = [];
$params = [];
if ($yearId > 0) {
    $where[] = 'a.school_year_id = :year_id';
    $params['year_id'] = $yearId;
}
if ($status !== '') {
    $where[] = 'a.status = :status';
    $params['status'] = $status;
}

$stmt = db()->prepare(
    'SELECT a.id, a.application_no, a.status, a.enrollment_type, a.lrn,
            CONCAT(a.last_name, \', \', a.first_name) AS applicant_name,
            a.guardian_name, a.guardian_contact, a.documents_submitted,
            g.name AS grade_name, sy.label AS school_year, a.created_at
     FROM trac_jhs_sarms.admissions a
     JOIN trac_jhs_sarms.grade_levels g ON g.id = a.grade_level_id
     JOIN trac_jhs_sarms.school_years sy ON sy.id = a.school_year_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
     ORDER BY a.created_at DESC'
);
$stmt->execute($params);

$rows = [];
foreach ($stmt->fetchAll() as $row) {
    $row['missing'] = admission_missing_documents(admission_documents_decode($row['documents_submitted']));
    if ($missingOnly && !$row['missing']) {
        continue;
    }
    $rows[] = $row;
}

$yearLabel = 'All years';
foreach (fetch_school_years() as $y) {
    if ((int) $y['id'] === $yearId) {
        $yearLabel = 'SY ' . $y['label'];
    }
}

if (wants_csv()) {
    send_csv(
        'admission_status_' . $yearLabel . ($missingOnly ? '_missing_requirements' : '') . '.csv',
        ['Application No.', 'Applicant', 'LRN', 'Grade', 'School Year', 'Type', 'Status', 'Missing Requirements', 'Guardian', 'Guardian Contact', 'Date Encoded'],
        array_map(static fn ($r) => [
            $r['application_no'], $r['applicant_name'], $r['lrn'], $r['grade_name'], $r['school_year'],
            ucfirst($r['enrollment_type']), ucfirst($r['status']),
            $r['missing'] ? implode('; ', $r['missing']) : 'Complete',
            $r['guardian_name'], $r['guardian_contact'], date('Y-m-d', strtotime($r['created_at'])),
        ], $rows),
        "Admission status CSV, {$yearLabel}" . ($status ? ", status={$status}" : '') . ($missingOnly ? ', missing requirements only' : '') . ' (' . count($rows) . ' rows)'
    );
}

render_header('Admission Status Report', 'reports');
?>
<div class="no-print panel-card glass-panel mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1" for="as_year">School Year</label>
            <select id="as_year" name="school_year_id" class="form-select form-select-sm">
                <option value="all" <?= $yearId === 0 ? 'selected' : '' ?>>All years</option>
                <?php foreach (fetch_school_years() as $y): ?>
                    <option value="<?= (int) $y['id'] ?>" <?= (int) $y['id'] === $yearId ? 'selected' : '' ?>><?= e($y['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1" for="as_status">Status</label>
            <select id="as_status" name="status" class="form-select form-select-sm">
                <option value="">Any</option>
                <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" name="missing" value="1" id="as_missing" <?= $missingOnly ? 'checked' : '' ?>>
                <label class="form-check-label small" for="as_missing">Missing requirements only</label>
            </div>
        </div>
        <div class="col-md-4 d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <button type="button" onclick="window.print()" class="btn btn-outline-light btn-sm"><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
            <a href="<?= e(csv_url('/modules/reports/admission_status.php')) ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-filetype-csv" aria-hidden="true"></i> Export CSV</a>
            <a href="<?= e(url('/modules/reports/index.php')) ?>" class="btn btn-outline-light btn-sm">Back</a>
        </div>
    </form>
</div>

<div class="print-report">
    <div class="text-center mb-4">
        <h1 style="font-size: 14pt; margin-bottom: 0;">Republic of the Philippines</h1>
        <h2 style="font-size: 13pt; margin-top: 0.25rem;">Department of Education</h2>
        <h3 style="font-size: 12pt; margin-top: 0.5rem;"><?= e(APP_SCHOOL) ?></h3>
        <p style="margin-top: 1rem;"><strong><?= $missingOnly ? 'ADMISSIONS WITH MISSING REQUIREMENTS' : 'ADMISSION STATUS REPORT' ?></strong><br>
            <?= e($yearLabel) ?><?= $status ? ' &middot; ' . e(ucfirst($status)) : '' ?></p>
        <p>Date Generated: <?= e(date('F j, Y')) ?></p>
    </div>

    <table class="table table-bordered" style="color: #000;">
        <thead>
            <tr>
                <th>Application No.</th>
                <th>Applicant</th>
                <th>Grade</th>
                <th>School Year</th>
                <th>Type</th>
                <th>Status</th>
                <th>Missing Requirements</th>
                <th>Date Encoded</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-muted">No applications match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="<?= e(url('/modules/admission/view.php?id=' . (int) $row['id'])) ?>"><?= e($row['application_no']) ?></a></td>
                    <td><?= e($row['applicant_name']) ?></td>
                    <td><?= e($row['grade_name']) ?></td>
                    <td><?= e($row['school_year']) ?></td>
                    <td><?= e(ucfirst($row['enrollment_type'])) ?></td>
                    <td><?= e(ucfirst($row['status'])) ?></td>
                    <td><?= $row['missing'] ? e(implode('; ', $row['missing'])) : 'Complete' ?></td>
                    <td><?= e(date('Y-m-d', strtotime($row['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><strong>Total:</strong> <?= count($rows) ?></p>
</div>
<?php
render_footer();
