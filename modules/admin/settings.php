<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/lis.php';

require_registrar();

/** Validate an optional Y-m-d date string; returns normalized value or null. */
$normDate = static function (?string $value): ?string {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
};

/** Validate school-year fields; returns list of error strings. */
$yearErrors = static function (string $label, ?string $start, ?string $end, string $rawStart, string $rawEnd): array {
    $errors = [];
    if (!validate_school_year_label($label)) {
        $errors[] = 'label must look like 2026-2027';
    } else {
        [$a, $b] = array_map('intval', explode('-', $label));
        if ($b !== $a + 1) {
            $errors[] = 'label years must be consecutive (e.g. 2026-2027)';
        }
    }
    if ($rawStart !== '' && $start === null) {
        $errors[] = 'start date is invalid';
    }
    if ($rawEnd !== '' && $end === null) {
        $errors[] = 'end date is invalid';
    }
    if ($start && $end && $end <= $start) {
        $errors[] = 'end date must be after start date';
    }
    return $errors;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_year' || $action === 'update_year') {
        $yearId = (int) ($_POST['year_id'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $rawStart = trim((string) ($_POST['start_date'] ?? ''));
        $rawEnd = trim((string) ($_POST['end_date'] ?? ''));
        $start = $normDate($rawStart);
        $end = $normDate($rawEnd);
        $setActive = isset($_POST['is_active']);
        $errors = $yearErrors($label, $start, $end, $rawStart, $rawEnd);

        if ($errors !== []) {
            flash('danger', 'School year not saved: ' . implode('; ', $errors) . '.');
            redirect('/modules/admin/settings.php' . ($action === 'update_year' ? '?edit_year=' . $yearId : ''));
        }

        try {
            db()->beginTransaction();
            if ($action === 'add_year') {
                if ($setActive) {
                    db()->exec('UPDATE school_years SET is_active = 0');
                }
                $stmt = db()->prepare(
                    'INSERT INTO school_years (label, is_active, start_date, end_date)
                     VALUES (:label, :active, :start, :end) RETURNING id'
                );
                $stmt->execute(['label' => $label, 'active' => $setActive ? 1 : 0, 'start' => $start, 'end' => $end]);
                $yearId = (int) $stmt->fetchColumn();
                audit_log('create', 'school_years', $yearId, "Created school year {$label}" . ($setActive ? ' (set active)' : ''));
                $msg = "School year {$label} added.";
            } else {
                $stmt = db()->prepare(
                    'UPDATE school_years SET label = :label, start_date = :start, end_date = :end WHERE id = :id'
                );
                $stmt->execute(['label' => $label, 'start' => $start, 'end' => $end, 'id' => $yearId]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('not found');
                }
                audit_log('update', 'school_years', $yearId, "Updated school year #{$yearId}: label={$label}, start=" . ($start ?? '-') . ', end=' . ($end ?? '-'));
                $msg = "School year {$label} updated.";
            }
            db()->commit();
            flash('success', $msg);
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $dup = $e instanceof PDOException && $e->getCode() === '23505';
            flash('danger', $dup ? "School year {$label} already exists." : 'Failed to save school year.');
        }
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'activate_year') {
        $yearId = (int) ($_POST['year_id'] ?? 0);
        try {
            db()->beginTransaction();
            $check = db()->prepare('SELECT label FROM school_years WHERE id = :id');
            $check->execute(['id' => $yearId]);
            $label = $check->fetchColumn();
            if ($label === false) {
                throw new RuntimeException('not found');
            }
            db()->exec('UPDATE school_years SET is_active = 0');
            db()->prepare('UPDATE school_years SET is_active = 1 WHERE id = :id')->execute(['id' => $yearId]);
            db()->commit();
            audit_log('update', 'school_years', $yearId, "Set school year {$label} as active");
            flash('success', "Active school year set to {$label}.");
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('danger', 'Failed to activate school year.');
        }
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'delete_year') {
        $yearId = (int) ($_POST['year_id'] ?? 0);
        $stmt = db()->prepare('SELECT label, is_active FROM school_years WHERE id = :id');
        $stmt->execute(['id' => $yearId]);
        $year = $stmt->fetch();
        if (!$year) {
            flash('danger', 'School year not found.');
        } elseif ((int) $year['is_active'] === 1) {
            flash('danger', 'You cannot delete the active school year. Activate another year first.');
        } else {
            try {
                // FK constraints (admissions, enrollments, academic_records,
                // sf10_grade_entries, lis_import_logs) block deletion of a
                // year that has any learner data attached.
                db()->prepare('DELETE FROM school_years WHERE id = :id')->execute(['id' => $yearId]);
                if ((int) ($_SESSION['selected_school_year_id'] ?? 0) === $yearId) {
                    unset($_SESSION['selected_school_year_id']);
                }
                audit_log('delete', 'school_years', $yearId, "Deleted school year {$year['label']}");
                flash('success', "School year {$year['label']} deleted.");
            } catch (PDOException $e) {
                flash('danger', $e->getCode() === '23503'
                    ? "School year {$year['label']} has admissions, enrollments or records attached and cannot be deleted."
                    : 'Failed to delete school year.');
            }
        }
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'add_section' || $action === 'update_section') {
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $gradeId = (int) ($_POST['grade_level_id'] ?? 0);
        $name = trim((string) ($_POST['section_name'] ?? ''));

        $gradeOk = db()->prepare('SELECT 1 FROM grade_levels WHERE id = :id');
        $gradeOk->execute(['id' => $gradeId]);

        if ($name === '' || mb_strlen($name) > 50 || !$gradeOk->fetchColumn()) {
            flash('danger', 'Section needs a valid grade level and a name (max 50 characters).');
        } else {
            try {
                if ($action === 'add_section') {
                    $stmt = db()->prepare('INSERT INTO sections (grade_level_id, name) VALUES (:grade, :name) RETURNING id');
                    $stmt->execute(['grade' => $gradeId, 'name' => $name]);
                    $sectionId = (int) $stmt->fetchColumn();
                    audit_log('create', 'sections', $sectionId, "Created section {$name} (grade_level_id={$gradeId})");
                    flash('success', "Section {$name} added.");
                } else {
                    // Moving a section to another grade would silently
                    // mislabel existing enrollments, so only allow it while
                    // the section is unused.
                    $cur = db()->prepare(
                        'SELECT s.grade_level_id, (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = s.id) AS used
                         FROM sections s WHERE s.id = :id'
                    );
                    $cur->execute(['id' => $sectionId]);
                    $row = $cur->fetch();
                    if (!$row) {
                        flash('danger', 'Section not found.');
                    } elseif ((int) $row['grade_level_id'] !== $gradeId && (int) $row['used'] > 0) {
                        flash('danger', 'This section already has enrollments; its grade level cannot be changed.');
                    } else {
                        db()->prepare('UPDATE sections SET grade_level_id = :grade, name = :name WHERE id = :id')
                            ->execute(['grade' => $gradeId, 'name' => $name, 'id' => $sectionId]);
                        audit_log('update', 'sections', $sectionId, "Updated section #{$sectionId}: name={$name}, grade_level_id={$gradeId}");
                        flash('success', "Section {$name} updated.");
                    }
                }
            } catch (PDOException $e) {
                flash('danger', $e->getCode() === '23505'
                    ? "A section named {$name} already exists in that grade level."
                    : 'Failed to save section.');
            }
        }
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'delete_section') {
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $stmt = db()->prepare('SELECT name FROM sections WHERE id = :id');
        $stmt->execute(['id' => $sectionId]);
        $name = $stmt->fetchColumn();
        if ($name === false) {
            flash('danger', 'Section not found.');
        } else {
            try {
                db()->prepare('DELETE FROM sections WHERE id = :id')->execute(['id' => $sectionId]);
                audit_log('delete', 'sections', $sectionId, "Deleted section {$name}");
                flash('success', "Section {$name} deleted.");
            } catch (PDOException $e) {
                flash('danger', $e->getCode() === '23503'
                    ? "Section {$name} has enrollments and cannot be deleted. Rename it instead."
                    : 'Failed to delete section.');
            }
        }
        redirect('/modules/admin/settings.php');
    }

    if ($action === 'lis_settings') {
        $schoolId = trim((string) ($_POST['lis_school_id'] ?? ''));
        $division = trim((string) ($_POST['lis_division'] ?? ''));

        $changes = [];
        if ($schoolId !== '' && preg_match('/^\d{6}$/', $schoolId)) {
            set_app_setting('lis_school_id', $schoolId);
            $changes[] = "lis_school_id={$schoolId}";
        }
        if ($division !== '' && mb_strlen($division) <= 255) {
            set_app_setting('lis_division', $division);
            $changes[] = "lis_division={$division}";
        }
        if ($changes !== []) {
            audit_log('update', 'app_settings', null, 'LIS export settings: ' . implode(', ', $changes));
        }
        flash('success', 'LIS export settings updated.');
        redirect('/modules/admin/settings.php');
    }

    flash('danger', 'Unknown action.');
    redirect('/modules/admin/settings.php');
}

$schoolYears = fetch_school_years();
$sections = db()->query(
    'SELECT s.*, g.name AS grade_name,
            (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = s.id) AS enrollment_count
     FROM sections s
     JOIN grade_levels g ON g.id = s.grade_level_id
     ORDER BY g.id, s.name'
)->fetchAll();
$gradeLevels = fetch_grade_levels();

$editYearId = (int) ($_GET['edit_year'] ?? 0);
$editYear = null;
foreach ($schoolYears as $y) {
    if ((int) $y['id'] === $editYearId) {
        $editYear = $y;
    }
}
$editSectionId = (int) ($_GET['edit_section'] ?? 0);
$editSection = null;
foreach ($sections as $s) {
    if ((int) $s['id'] === $editSectionId) {
        $editSection = $s;
    }
}

render_header('System Settings', 'settings');
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3>School Years</h3>
            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead><tr><th>Label</th><th>Dates</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($schoolYears as $year): ?>
                            <tr>
                                <td><?= e($year['label']) ?></td>
                                <td class="small"><?= e(($year['start_date'] ?? '') ?: '—') ?> → <?= e(($year['end_date'] ?? '') ?: '—') ?></td>
                                <td><?= $year['is_active'] ? 'Yes' : 'No' ?></td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-light"
                                       href="<?= e(url('/modules/admin/settings.php?edit_year=' . (int) $year['id'])) ?>">Edit</a>
                                    <?php if (!$year['is_active']): ?>
                                        <form method="post" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="activate_year">
                                            <input type="hidden" name="year_id" value="<?= (int) $year['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-light">Set Active</button>
                                        </form>
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm(<?= e(json_encode('Delete school year ' . $year['label'] . '? This cannot be undone.', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>);">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_year">
                                            <input type="hidden" name="year_id" value="<?= (int) $year['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <h4 class="h6"><?= $editYear ? 'Edit ' . e($editYear['label']) : 'Add School Year' ?></h4>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editYear ? 'update_year' : 'add_year' ?>">
                <?php if ($editYear): ?>
                    <input type="hidden" name="year_id" value="<?= (int) $editYear['id'] ?>">
                <?php endif; ?>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small" for="sy_label">Label</label>
                        <input type="text" id="sy_label" name="label" class="form-control" placeholder="2026-2027"
                               pattern="\d{4}-\d{4}" value="<?= e($editYear['label'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="sy_start">Start</label>
                        <input type="date" id="sy_start" name="start_date" class="form-control" value="<?= e($editYear['start_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="sy_end">End</label>
                        <input type="date" id="sy_end" name="end_date" class="form-control" value="<?= e($editYear['end_date'] ?? '') ?>">
                    </div>
                    <?php if (!$editYear): ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="activeYear">
                                <label class="form-check-label" for="activeYear">Set as active year</label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm"><?= $editYear ? 'Save Changes' : 'Add School Year' ?></button>
                        <?php if ($editYear): ?>
                            <a class="btn btn-outline-light btn-sm" href="<?= e(url('/modules/admin/settings.php')) ?>">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card glass-panel">
            <h3>Sections</h3>
            <div class="table-responsive mb-3" style="max-height:300px;overflow-y:auto;">
                <table class="table table-sm">
                    <thead><tr><th>Grade</th><th>Section</th><th>Enrollments</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($sections as $section): ?>
                            <tr>
                                <td><?= e($section['grade_name']) ?></td>
                                <td><?= e($section['name']) ?></td>
                                <td><?= (int) $section['enrollment_count'] ?></td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-light"
                                       href="<?= e(url('/modules/admin/settings.php?edit_section=' . (int) $section['id'])) ?>">Edit</a>
                                    <?php if ((int) $section['enrollment_count'] === 0): ?>
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm(<?= e(json_encode('Delete section ' . $section['name'] . '?', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>);">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_section">
                                            <input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <h4 class="h6"><?= $editSection ? 'Edit section ' . e($editSection['name']) : 'Add Section' ?></h4>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editSection ? 'update_section' : 'add_section' ?>">
                <?php if ($editSection): ?>
                    <input type="hidden" name="section_id" value="<?= (int) $editSection['id'] ?>">
                <?php endif; ?>
                <div class="row g-2">
                    <div class="col-md-5">
                        <select name="grade_level_id" class="form-select" aria-label="Grade level" required>
                            <?php foreach ($gradeLevels as $grade): ?>
                                <option value="<?= (int) $grade['id'] ?>"
                                    <?= $editSection && (int) $editSection['grade_level_id'] === (int) $grade['id'] ? 'selected' : '' ?>>
                                    <?= e($grade['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="section_name" class="form-control" placeholder="Section name" maxlength="50"
                               aria-label="Section name" value="<?= e($editSection['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><?= $editSection ? 'Save' : 'Add' ?></button>
                    </div>
                    <?php if ($editSection): ?>
                        <div class="col-12">
                            <a class="btn btn-outline-light btn-sm" href="<?= e(url('/modules/admin/settings.php')) ?>">Cancel</a>
                        </div>
                    <?php endif; ?>
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
                    <label class="form-label" for="lis_school_id">School ID (6 digits)</label>
                    <input type="text" id="lis_school_id" name="lis_school_id" class="form-control" pattern="\d{6}" maxlength="6"
                           value="<?= e(lis_school_id()) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="lis_division">Schools Division Office</label>
                    <input type="text" id="lis_division" name="lis_division" class="form-control" maxlength="255"
                           value="<?= e(lis_division()) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save LIS Settings</button>
            </form>
        </div>
    </div>
</div>
<?php
render_footer();
