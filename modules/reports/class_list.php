<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

// Class list per section (male learners first, then female, alphabetical),
// the list advisers ask for at the start of every school year. Print or CSV.

$activeYear = active_school_year();
$yearId = (int) ($_GET['school_year_id'] ?? ($activeYear['id'] ?? 0));
$sectionParam = (string) ($_GET['section_id'] ?? '');
$unassigned = $sectionParam === 'none';
$sectionId = $unassigned ? 0 : (int) $sectionParam;

$yearStmt = db()->prepare('SELECT * FROM trac_jhs_sarms.school_years WHERE id = :id');
$yearStmt->execute(['id' => $yearId]);
$year = $yearStmt->fetch() ?: null;

$section = null;
if ($sectionId > 0) {
    $stmt = db()->prepare(
        'SELECT s.id, s.name, s.grade_level_id, g.name AS grade_name
         FROM trac_jhs_sarms.sections s
         JOIN trac_jhs_sarms.grade_levels g ON g.id = s.grade_level_id
         WHERE s.id = :id'
    );
    $stmt->execute(['id' => $sectionId]);
    $section = $stmt->fetch() ?: null;
}

$rows = [];
if ($year && ($section || $unassigned)) {
    $sql = 'SELECT st.lrn, st.student_id_no, st.last_name, st.first_name, st.middle_name, st.suffix,
                   st.sex, st.birthdate, st.guardian_name, st.guardian_contact,
                   e.enrollment_type, g.name AS grade_name
            FROM trac_jhs_sarms.enrollments e
            JOIN trac_jhs_sarms.students st ON st.id = e.student_id
            JOIN trac_jhs_sarms.grade_levels g ON g.id = e.grade_level_id
            WHERE e.school_year_id = :year_id
              AND e.status IN (\'enrolled\', \'completed\')
              AND ' . ($unassigned ? 'e.section_id IS NULL' : 'e.section_id = :section_id') . '
            ORDER BY CASE st.sex WHEN \'Male\' THEN 0 ELSE 1 END, g.id, st.last_name, st.first_name';
    $params = ['year_id' => $yearId];
    if (!$unassigned) {
        $params['section_id'] = $sectionId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

$title = $unassigned ? 'Unassigned learners' : ($section ? "{$section['grade_name']} - {$section['name']}" : '');

/** Age on 31 October of the school year's first calendar year (DepEd cut-off). */
$ageAsOf = static function (string $birthdate) use ($year): string {
    if (!$year || !preg_match('/^(\d{4})-/', (string) $year['label'], $m)) {
        return '';
    }
    try {
        return (string) (new DateTimeImmutable($birthdate))->diff(new DateTimeImmutable($m[1] . '-10-31'))->y;
    } catch (Throwable) {
        return '';
    }
};

$fullName = static fn (array $r): string => trim(
    $r['last_name'] . ', ' . $r['first_name']
    . ($r['suffix'] ? ' ' . $r['suffix'] : '')
    . ($r['middle_name'] ? ' ' . $r['middle_name'] : '')
);

if (wants_csv() && $title !== '' && $year) {
    $i = 0;
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            ++$i, $r['lrn'], $r['student_id_no'], $fullName($r), $r['sex'], $r['birthdate'],
            $ageAsOf($r['birthdate']), $r['grade_name'], ucfirst($r['enrollment_type']),
            $r['guardian_name'], $r['guardian_contact'],
        ];
    }
    send_csv(
        'class_list_' . $year['label'] . '_' . $title . '.csv',
        ['No.', 'LRN', 'Student ID', 'Name', 'Sex', 'Birthdate', 'Age (Oct 31)', 'Grade', 'Type', 'Guardian', 'Guardian Contact'],
        $out,
        "Class list CSV: {$title}, SY {$year['label']} (" . count($out) . ' learners)'
    );
}

$sections = fetch_sections();
$males = array_values(array_filter($rows, static fn ($r) => $r['sex'] === 'Male'));
$females = array_values(array_filter($rows, static fn ($r) => $r['sex'] !== 'Male'));

render_header('Class List', 'reports');
?>
<div class="no-print panel-card glass-panel mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1" for="cl_year">School Year</label>
            <select id="cl_year" name="school_year_id" class="form-select form-select-sm">
                <?php foreach (fetch_school_years() as $y): ?>
                    <option value="<?= (int) $y['id'] ?>" <?= (int) $y['id'] === $yearId ? 'selected' : '' ?>><?= e($y['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label small mb-1" for="cl_section">Section</label>
            <select id="cl_section" name="section_id" class="form-select form-select-sm" required>
                <option value="">Select section</option>
                <?php foreach ($sections as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $sectionId ? 'selected' : '' ?>><?= e($s['grade_name'] . ' - ' . $s['name']) ?></option>
                <?php endforeach; ?>
                <option value="none" <?= $unassigned ? 'selected' : '' ?>>No section yet (unassigned)</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary btn-sm">Show</button>
            <?php if ($title !== '' && $year): ?>
                <button type="button" onclick="window.print()" class="btn btn-outline-light btn-sm"><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
                <a href="<?= e(csv_url('/modules/reports/class_list.php')) ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-filetype-csv" aria-hidden="true"></i> Export CSV</a>
            <?php endif; ?>
            <a href="<?= e(url('/modules/reports/index.php')) ?>" class="btn btn-outline-light btn-sm">Back</a>
        </div>
    </form>
</div>

<?php if ($title === '' || !$year): ?>
    <p class="text-muted">Choose a school year and section to generate the class list.</p>
<?php else: ?>
<div class="print-report">
    <div class="text-center mb-4">
        <h1 style="font-size: 14pt; margin-bottom: 0;">Republic of the Philippines</h1>
        <h2 style="font-size: 13pt; margin-top: 0.25rem;">Department of Education</h2>
        <h3 style="font-size: 12pt; margin-top: 0.5rem;"><?= e(APP_SCHOOL) ?></h3>
        <p style="margin-top: 1rem;"><strong>CLASS LIST</strong><br>
            <?= e($title) ?> &middot; School Year <?= e($year['label']) ?></p>
        <p>Adviser: ______________________________ &nbsp; Date Generated: <?= e(date('F j, Y')) ?></p>
    </div>

    <?php
    $i = 0;
    foreach (['MALE' => $males, 'FEMALE' => $females] as $groupLabel => $group):
    ?>
        <table class="table table-bordered" style="color: #000;">
            <thead>
                <tr><th colspan="7"><?= $groupLabel ?> (<?= count($group) ?>)</th></tr>
                <tr>
                    <th style="width:3rem;">No.</th>
                    <th>LRN</th>
                    <th>Name (Last, First Middle)</th>
                    <th>Birthdate</th>
                    <th>Age</th>
                    <?php if ($unassigned): ?><th>Grade</th><?php else: ?><th>Type</th><?php endif; ?>
                    <th>Guardian Contact</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$group): ?>
                    <tr><td colspan="7" class="text-muted">None</td></tr>
                <?php endif; ?>
                <?php foreach ($group as $r): ?>
                    <tr>
                        <td><?= ++$i ?></td>
                        <td><?= e($r['lrn'] ?: '—') ?></td>
                        <td><?= e($fullName($r)) ?></td>
                        <td><?= e($r['birthdate']) ?></td>
                        <td><?= e($ageAsOf($r['birthdate'])) ?></td>
                        <td><?= e($unassigned ? $r['grade_name'] : ucfirst($r['enrollment_type'])) ?></td>
                        <td><?= e($r['guardian_contact']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <p><strong>Total:</strong> <?= count($rows) ?> &nbsp; (Male <?= count($males) ?>, Female <?= count($females) ?>)</p>

    <div style="margin-top: 3rem; display:flex; justify-content:space-between; gap:2rem;">
        <div>
            <p>Prepared by:</p>
            <p>___________________________<br>Class Adviser</p>
        </div>
        <div>
            <p>Noted by:</p>
            <p>___________________________<br>School Registrar</p>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
render_footer();
