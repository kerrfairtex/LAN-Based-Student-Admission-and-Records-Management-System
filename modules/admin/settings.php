<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/lis.php';

require_registrar();

$schoolYears = fetch_school_years();
$sections = fetch_sections();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_year') {
        $label = trim($_POST['label'] ?? '');
        $start = $_POST['start_date'] ?? null;
        $end = $_POST['end_date'] ?? null;
        $setActive = isset($_POST['is_active']);

        if ($label) {
            try {
                db()->beginTransaction();
                if ($setActive) {
                    db()->exec('UPDATE school_years SET is_active = 0');
                }
                db()->prepare(
                    'INSERT INTO school_years (label, is_active, start_date, end_date) VALUES (:label, :active, :start, :end)'
                )->execute([
                    'label' => $label,
                    'active' => $setActive ? 1 : 0,
                    'start' => $start ?: null,
                    'end' => $end ?: null,
                ]);
                db()->commit();
                                $newYearId = (int) db()->lastInsertId();
                                audit_log('create', 'school_years', $newYearId, "Created school year {$label}" . ($setActive ? ' (set active)' : ''));
                                flash('success', "School year {$label} added.");
                            } catch (PDOException $e) {
                                db()->rollBack();
                                flash('danger', 'Failed to add school year: ' . $e->getMessage());
                            }
                        }
                        redirect('/modules/admin/settings.php');
                    }

                    if ($action === 'activate_year' && isset($_POST['year_id'])) {
                        try {
                            db()->beginTransaction();
                            db()->exec('UPDATE school_years SET is_active = 0');
                            $yearId = (int) $_POST['year_id'];
                            db()->prepare('UPDATE school_years SET is_active = 1 WHERE id = :id')
                                ->execute(['id' => $yearId]);
                            db()->commit();
                            audit_log('update', 'school_years', $yearId, "Set school year #{$yearId} as active");
                            flash('success', 'Active school year updated.');
                        } catch (PDOException $e) {
                            db()->rollBack();
                            flash('danger', 'Failed to activate school year: ' . $e->getMessage());
                        }
                        redirect('/modules/admin/settings.php');
                    }

                    if ($action === 'add_section') {
                        $gradeId = (int) ($_POST['grade_level_id'] ?? 0);
                        $name = trim($_POST['section_name'] ?? '');
                        if ($gradeId && $name) {
                            try {
                                db()->prepare('INSERT INTO sections (grade_level_id, name) VALUES (:grade, :name)')
                                    ->execute(['grade' => $gradeId, 'name' => $name]);
                                $newSectionId = (int) db()->lastInsertId();
                                audit_log('create', 'sections', $newSectionId, "Created section {$name} (grade_level_id={$gradeId})");
                                flash('success', "Section {$name} added.");
                            } catch (PDOException $e) {
                                flash('danger', 'Failed to add section: ' . $e->getMessage());
                            }
                        }
                        redirect('/modules/admin/settings.php');
                    }

    if ($action === 'lis_settings') {
        $schoolId = trim($_POST['lis_school_id'] ?? '');
        $division = trim($_POST['lis_division'] ?? '');

        $changes = [];
        if ($schoolId !== '' && preg_match('/^\d{6}$/', $schoolId)) {
            set_app_setting('lis_school_id', $schoolId);
            $changes[] = "lis_school_id={$schoolId}";
        }
        if ($division !== '') {
            set_app_setting('lis_division', $division);
            $changes[] = "lis_division={$division}";
        }
        if ($changes !== []) {
            audit_log('update', 'app_settings', null, 'LIS export settings: ' . implode(', ', $changes));
        }
        flash('success', 'LIS export settings updated.');
        redirect('/modules/admin/settings.php');
    }
}

$gradeLevels = fetch_grade_levels();

render_header('System Settings', 'settings');
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3>School Years</h3>
            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead><tr><th>Label</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($schoolYears as $year): ?>
                            <tr>
                                <td><?= e($year['label']) ?></td>
                                <td><?= $year['is_active'] ? 'Yes' : 'No' ?></td>
                                <td>
                                    <?php if (!$year['is_active']): ?>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="activate_year">
                                            <input type="hidden" name="year_id" value="<?= (int) $year['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-light">Set Active</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_year">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="label" class="form-control" placeholder="2026-2027" required>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="end_date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="activeYear">
                            <label class="form-check-label" for="activeYear">Active</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Add School Year</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3>Sections</h3>
            <div class="table-responsive mb-3" style="max-height:240px;overflow-y:auto;">
                <table class="table table-sm">
                    <thead><tr><th>Grade</th><th>Section</th></tr></thead>
                    <tbody>
                        <?php foreach ($sections as $section): ?>
                            <tr>
                                <td><?= e($section['grade_name']) ?></td>
                                <td><?= e($section['name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="add_section">
                <div class="row g-2">
                    <div class="col-md-5">
                        <select name="grade_level_id" class="form-select" required>
                            <?php foreach ($gradeLevels as $grade): ?>
                                <option value="<?= (int) $grade['id'] ?>"><?= e($grade['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="section_name" class="form-control" placeholder="Section name" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3>LIS Export Settings</h3>
            <p class="text-muted small">Six-digit EBEIS School ID used in SF1 CSV exports for DepEd LIS.</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="lis_settings">
                <div class="mb-3">
                    <label class="form-label">School ID (6 digits)</label>
                    <input type="text" name="lis_school_id" class="form-control" pattern="\d{6}" maxlength="6"
                           value="<?= e(lis_school_id()) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Schools Division Office</label>
                    <input type="text" name="lis_division" class="form-control"
                           value="<?= e(lis_division()) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save LIS Settings</button>
            </form>
        </div>
    </div>
</div>
<?php
render_footer();
