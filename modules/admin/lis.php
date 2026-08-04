<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/lis.php';

require_registrar();

$schoolYears = fetch_school_years();
$gradeLevels = fetch_grade_levels();
$importLogs = lis_recent_import_logs();
$activeYear = active_school_year();
$importErrors = $_SESSION['lis_import_errors'] ?? [];
unset($_SESSION['lis_import_errors']);

render_header('LIS CSV Export / Import', 'lis');
?>
<?php if ($importErrors): ?>
<div class="alert alert-warning">
    <strong>Import errors (first <?= count($importErrors) ?>):</strong>
    <ul class="mb-0 mt-2 small">
        <?php foreach ($importErrors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<p class="text-muted mb-3">
    Export enrolled learners to an SF1-aligned CSV for DepEd LIS encoding, or import learner data
    from LIS exports / Enhanced BEEF spreadsheets. Reference: SF1 School Register, DO 35 s.2022 (BEEF).
</p>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3><i class="bi bi-download"></i> Export to LIS CSV</h3>
            <p class="text-muted small">Generates SF1-aligned register for the active or selected school year.</p>
            <form method="get" action="<?= e(url('/modules/admin/lis_export.php')) ?>">
                <div class="mb-3">
                    <label class="form-label">School Year</label>
                    <select name="school_year_id" class="form-select" required>
                        <?php foreach ($schoolYears as $year): ?>
                            <option value="<?= (int) $year['id'] ?>" <?= (int) $year['is_active'] ? 'selected' : '' ?>>
                                <?= e($year['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Grade Level (optional)</label>
                    <select name="grade_level_id" class="form-select">
                        <option value="">All grades</option>
                        <?php foreach ($gradeLevels as $grade): ?>
                            <option value="<?= (int) $grade['id'] ?>"><?= e($grade['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Section (optional)</label>
                    <select name="section_id" class="form-select">
                        <option value="">All sections</option>
                        <?php foreach (fetch_sections() as $section): ?>
                            <option value="<?= (int) $section['id'] ?>">
                                <?= e($section['grade_name'] . ' — ' . $section['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-file-earmark-spreadsheet"></i> Download CSV</button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3><i class="bi bi-upload"></i> Import from CSV</h3>
            <p class="text-muted small">
                Matches existing learners by LRN or Student ID; creates new records when not found.
                Updates enrollment when Grade Level is present in the file.
            </p>
            <form method="post" action="<?= e(url('/modules/admin/lis_import.php')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Default School Year (if not in CSV)</label>
                    <select name="default_school_year_id" class="form-select" required>
                        <?php foreach ($schoolYears as $year): ?>
                            <option value="<?= (int) $year['id'] ?>" <?= (int) $year['is_active'] ? 'selected' : '' ?>>
                                <?= e($year['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="lis_csv" class="form-control" accept=".csv,text/csv" required>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Import CSV</button>
                    <a href="<?= e(url('/modules/admin/lis_template.php')) ?>" class="btn btn-outline-light">Download Template</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12">
        <div class="panel-card glass-panel">
            <h3>LIS Configuration</h3>
            <p class="text-muted small mb-3">
                School ID: <strong><?= e(lis_school_id()) ?></strong> ·
                Division: <strong><?= e(lis_division()) ?></strong> ·
                Region: <strong><?= e(APP_REGION) ?></strong>
            </p>
            <p class="text-muted small mb-0">
                Update School ID and Division in <a href="<?= e(url('/modules/admin/settings.php')) ?>" class="link-light">System Settings</a>.
            </p>
        </div>
    </div>

    <?php if ($importLogs): ?>
    <div class="col-12">
        <div class="table-card glass-panel">
            <h3>Recent Imports</h3>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>File</th>
                            <th>School Year</th>
                            <th>Total</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Skipped</th>
                            <th>Errors</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($importLogs as $log): ?>
                            <tr>
                                <td><?= e($log['created_at']) ?></td>
                                <td><?= e($log['filename']) ?></td>
                                <td><?= e($log['school_year'] ?? '—') ?></td>
                                <td><?= (int) $log['rows_total'] ?></td>
                                <td><?= (int) $log['rows_created'] ?></td>
                                <td><?= (int) $log['rows_updated'] ?></td>
                                <td><?= (int) $log['rows_skipped'] ?></td>
                                <td><?= (int) $log['rows_errors'] ?></td>
                                <td><?= e($log['imported_by_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="panel-card glass-panel mt-3">
    <h3>CSV Column Reference (SF1 / BEEF aligned)</h3>
    <p class="text-muted small mb-2">Required for import: Last_Name, First_Name, Birthdate, Sex, Address, Guardian_Name, Guardian_Relationship, Guardian_Contact</p>
    <code class="small text-muted"><?= e(implode(',', LIS_CSV_COLUMNS)) ?></code>
</div>
<?php
render_footer();
