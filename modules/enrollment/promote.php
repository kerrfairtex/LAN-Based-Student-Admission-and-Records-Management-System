<?php

declare(strict_types=1);

/**
 * Year-end promotion (registrar only).
 *
 * For one grade level in a source school year, moves each selected learner
 * according to the promotional status already recorded in academic_records:
 *   Promoted (Grade 7–9) → new enrollment in the next grade, target year
 *   Promoted (Grade 10)  → student status 'graduated'
 *   Retained             → new enrollment in the same grade, target year
 *   Incomplete / none    → skipped (fix the academic record first)
 * The source enrollment is marked 'completed'. New enrollments have no
 * section yet; assign sections in Enrollments afterwards.
 *
 * All changes run in one transaction; learners already enrolled in the
 * target year are skipped (uniq_student_school_year).
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_registrar();

$schoolYears = fetch_school_years();          // label DESC
$gradeLevels = fetch_grade_levels();

// Grade progression is defined by GRADE_LEVEL_NAMES order, not by id.
$gradeIdByName = [];
foreach ($gradeLevels as $g) {
    $gradeIdByName[$g['name']] = (int) $g['id'];
}
$nextGradeId = [];
foreach (GRADE_LEVEL_NAMES as $i => $name) {
    if (isset($gradeIdByName[$name])) {
        $next = GRADE_LEVEL_NAMES[$i + 1] ?? null;
        $nextGradeId[$gradeIdByName[$name]] = $next !== null ? ($gradeIdByName[$next] ?? null) : null;
    }
}
$finalGradeId = $gradeIdByName[GRADE_LEVEL_NAMES[count(GRADE_LEVEL_NAMES) - 1]] ?? 0;

$yearLabel = [];
foreach ($schoolYears as $y) {
    $yearLabel[(int) $y['id']] = $y['label'];
}

// Default source = active year; default target = the year labelled one later.
$active = active_school_year();
$defaultSource = (int) ($active['id'] ?? 0);
$defaultTarget = 0;
if ($active && validate_school_year_label($active['label'])) {
    [$a, $b] = array_map('intval', explode('-', $active['label']));
    $want = ($a + 1) . '-' . ($b + 1);
    foreach ($schoolYears as $y) {
        if ($y['label'] === $want) {
            $defaultTarget = (int) $y['id'];
        }
    }
}

$src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$sourceYearId = (int) ($src['source_year_id'] ?? $defaultSource);
$targetYearId = (int) ($src['target_year_id'] ?? $defaultTarget);
$gradeId = (int) ($src['grade_level_id'] ?? ($gradeLevels[0]['id'] ?? 0));

/**
 * Decide what happens to one learner.
 * @return array{0:string,1:?int} [outcome, destination grade id]
 */
$decide = static function (?string $promo, int $grade) use ($nextGradeId, $finalGradeId): array {
    if ($promo === 'Promoted') {
        return $grade === $finalGradeId ? ['graduate', null] : ['promote', $nextGradeId[$grade] ?? null];
    }
    if ($promo === 'Retained') {
        return ['retain', $grade];
    }
    return ['skip', null];
};

$queryCandidates = static function (int $sourceYearId, int $gradeId, int $targetYearId): array {
    $stmt = db()->prepare(
        "SELECT e.id AS enrollment_id, e.student_id, e.grade_level_id,
                s.student_id_no, s.lrn, s.last_name, s.first_name, s.middle_name,
                ar.promotional_status, ar.general_average,
                (SELECT 1 FROM enrollments t
                  WHERE t.student_id = e.student_id AND t.school_year_id = :target) AS already_in_target
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         LEFT JOIN academic_records ar
                ON ar.student_id = e.student_id AND ar.school_year_id = e.school_year_id
         WHERE e.school_year_id = :source
           AND e.grade_level_id = :grade
           AND e.status = 'enrolled'
           AND s.status = 'active'
         ORDER BY s.last_name, s.first_name"
    );
    $stmt->execute(['source' => $sourceYearId, 'grade' => $gradeId, 'target' => $targetYearId]);
    return $stmt->fetchAll();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $back = '/modules/enrollment/promote.php?' . http_build_query([
        'source_year_id' => $sourceYearId, 'target_year_id' => $targetYearId, 'grade_level_id' => $gradeId,
    ]);

    $selected = array_map('intval', (array) ($_POST['enrollment_ids'] ?? []));
    $selected = array_flip(array_filter($selected, static fn (int $v): bool => $v > 0));

    $needsTarget = $gradeId !== $finalGradeId;
    if (!isset($yearLabel[$sourceYearId]) || ($needsTarget && !isset($yearLabel[$targetYearId]))) {
        flash('danger', 'Choose valid source and target school years.');
        redirect($back);
    }
    if ($needsTarget && $sourceYearId === $targetYearId) {
        flash('danger', 'Target school year must be different from the source year.');
        redirect($back);
    }
    if ($selected === []) {
        flash('warning', 'No learners selected.');
        redirect($back);
    }

    $userId = (int) $_SESSION['user']['id'];
    $counts = ['promote' => 0, 'retain' => 0, 'graduate' => 0, 'skip' => 0];

    try {
        db()->beginTransaction();

        $insert = db()->prepare(
            "INSERT INTO enrollments (student_id, school_year_id, grade_level_id, section_id,
                                      enrollment_type, status, enrolled_at, created_by)
             VALUES (:sid, :sy, :grade, NULL, 'returning', 'enrolled', CURRENT_DATE, :uid)
             ON CONFLICT (student_id, school_year_id) DO NOTHING"
        );
        $complete = db()->prepare("UPDATE enrollments SET status = 'completed' WHERE id = :id AND status = 'enrolled'");
        $graduate = db()->prepare("UPDATE students SET status = 'graduated' WHERE id = :id AND status = 'active'");

        // Re-read candidates inside the transaction: only ids that still
        // match source year + grade + 'enrolled' are acted on.
        foreach ($queryCandidates($sourceYearId, $gradeId, $targetYearId) as $row) {
            if (!isset($selected[(int) $row['enrollment_id']])) {
                continue;
            }
            [$outcome, $destGrade] = $decide($row['promotional_status'], (int) $row['grade_level_id']);
            $sid = (int) $row['student_id'];

            if ($outcome === 'skip' || ($outcome !== 'graduate' && ($destGrade === null || $row['already_in_target']))) {
                $counts['skip']++;
                continue;
            }

            if ($outcome === 'graduate') {
                $graduate->execute(['id' => $sid]);
                $detail = "Graduated (completed {$yearLabel[$sourceYearId]})";
            } else {
                $insert->execute(['sid' => $sid, 'sy' => $targetYearId, 'grade' => $destGrade, 'uid' => $userId]);
                if ($insert->rowCount() === 0) {
                    $counts['skip']++;
                    continue;
                }
                $detail = ($outcome === 'promote' ? 'Promoted' : 'Retained')
                    . " {$yearLabel[$sourceYearId]} → {$yearLabel[$targetYearId]} (grade_level_id={$destGrade})";
            }
            $complete->execute(['id' => (int) $row['enrollment_id']]);
            audit_log('year_end_' . $outcome, 'students', $sid, $detail);
            $counts[$outcome]++;
        }

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('[promote] ' . $e->getMessage());
        flash('danger', 'Year-end processing failed; no changes were saved.');
        redirect($back);
    }

    audit_log(
        'year_end_batch',
        'enrollments',
        null,
        sprintf(
            'Year-end batch sy=%s grade_level_id=%d target=%s: promoted=%d retained=%d graduated=%d skipped=%d',
            $yearLabel[$sourceYearId], $gradeId, $yearLabel[$targetYearId] ?? '-',
            $counts['promote'], $counts['retain'], $counts['graduate'], $counts['skip']
        )
    );
    flash('success', sprintf(
        'Done: %d promoted, %d retained, %d graduated, %d skipped. Assign sections for the new enrollments in Enrollments.',
        $counts['promote'], $counts['retain'], $counts['graduate'], $counts['skip']
    ));
    redirect($back);
}

$candidates = ($sourceYearId > 0 && $gradeId > 0) ? $queryCandidates($sourceYearId, $gradeId, $targetYearId) : [];
$isFinalGrade = $gradeId === $finalGradeId;

render_header('Year-End Promotion', 'promote');
?>
<div class="panel-card glass-panel mb-3">
    <p class="text-muted mb-3">
        Moves learners into the next school year using the <strong>promotional status</strong> recorded on each
        learner's academic record. Promoted learners go to the next grade (Grade 10 → graduated), retained learners
        repeat the grade, and learners with no or <em>Incomplete</em> status are skipped.
    </p>
    <form method="get" class="row g-2 align-items-end">
        <div class="col-sm-4">
            <label class="form-label" for="source_year_id">From school year</label>
            <select id="source_year_id" name="source_year_id" class="form-select">
                <?php foreach ($schoolYears as $y): ?>
                    <option value="<?= (int) $y['id'] ?>" <?= (int) $y['id'] === $sourceYearId ? 'selected' : '' ?>><?= e($y['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label" for="grade_level_id">Grade level</label>
            <select id="grade_level_id" name="grade_level_id" class="form-select">
                <?php foreach ($gradeLevels as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= (int) $g['id'] === $gradeId ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label" for="target_year_id">To school year</label>
            <select id="target_year_id" name="target_year_id" class="form-select">
                <option value="0">—</option>
                <?php foreach ($schoolYears as $y): ?>
                    <option value="<?= (int) $y['id'] ?>" <?= (int) $y['id'] === $targetYearId ? 'selected' : '' ?>><?= e($y['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-2">
            <button type="submit" class="btn btn-outline-light w-100">Load</button>
        </div>
    </form>
    <?php if (!$isFinalGrade && $targetYearId === 0): ?>
        <div class="alert alert-warning mt-3 mb-0">
            No target school year selected. Create the next school year in
            <a href="<?= e(url('/modules/admin/settings.php')) ?>">Settings</a> first.
        </div>
    <?php endif; ?>
</div>

<div class="table-card glass-panel">
    <form method="post"
          onsubmit="return confirm('Process the selected learners? This creates enrollments / marks graduates in one batch.');">
        <?= csrf_field() ?>
        <input type="hidden" name="source_year_id" value="<?= $sourceYearId ?>">
        <input type="hidden" name="target_year_id" value="<?= $targetYearId ?>">
        <input type="hidden" name="grade_level_id" value="<?= $gradeId ?>">

        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h3 class="m-0"><?= count($candidates) ?> learner(s) currently enrolled</h3>
            <button type="submit" class="btn btn-primary" <?= $candidates === [] ? 'disabled' : '' ?>>Process Selected</button>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="form-check-input" id="checkAll" aria-label="Select all"></th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Gen. Avg.</th>
                        <th>Promotional Status</th>
                        <th>Outcome</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($candidates === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">No currently-enrolled learners for this grade and year.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($candidates as $c): ?>
                        <?php
                        [$outcome, $dest] = $decide($c['promotional_status'], (int) $c['grade_level_id']);
                        $blocked = $outcome === 'skip'
                            || ($outcome !== 'graduate' && ($dest === null || $targetYearId === 0 || $targetYearId === $sourceYearId || $c['already_in_target']));
                        $destName = '';
                        foreach ($gradeLevels as $g) {
                            if ((int) $g['id'] === $dest) {
                                $destName = $g['name'];
                            }
                        }
                        $label = match (true) {
                            $outcome === 'skip' => 'Skip: set promotional status first',
                            $outcome !== 'graduate' && $c['already_in_target'] => 'Skip: already enrolled in target year',
                            $outcome === 'graduate' => 'Graduate',
                            $outcome === 'promote' => 'Promote to ' . $destName,
                            default => 'Retain in ' . $destName,
                        };
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input row-check" name="enrollment_ids[]"
                                       value="<?= (int) $c['enrollment_id'] ?>" <?= $blocked ? 'disabled' : 'checked' ?>
                                       aria-label="Select <?= e($c['last_name']) ?>">
                            </td>
                            <td><?= e($c['student_id_no']) ?></td>
                            <td>
                                <a href="<?= e(url('/modules/records/view.php?id=' . (int) $c['student_id'])) ?>">
                                    <?= e(trim($c['last_name'] . ', ' . $c['first_name'] . ' ' . ($c['middle_name'] ?? ''))) ?>
                                </a>
                            </td>
                            <td><?= e($c['general_average'] !== null ? (string) $c['general_average'] : '—') ?></td>
                            <td><?= e($c['promotional_status'] ?? '—') ?></td>
                            <td class="<?= $blocked ? 'text-muted' : '' ?>"><?= e($label) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
<script>
document.getElementById('checkAll')?.addEventListener('change', function () {
    document.querySelectorAll('.row-check:not(:disabled)').forEach(function (cb) { cb.checked = this.checked; }, this);
});
</script>
<?php
render_footer();
