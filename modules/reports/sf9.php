<?php

declare(strict_types=1);

/**
 * SF9-JHS — Learner's Progress Report Card (formerly Form 138).
 *
 * Built from the same per-learning-area quarterly ratings that feed SF10
 * (sf10_grade_entries) plus the year's enrollment (grade/section) and
 * academic_records (attendance, promotional status). No extra tables.
 *
 * Descriptors and the 75 passing mark follow DepEd Order No. 8, s. 2015.
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/sf10.php';
require_once __DIR__ . '/../../includes/lis.php';

require_login();

$studentId = (int) ($_GET['student_id'] ?? 0);
$schoolYearId = (int) ($_GET['school_year_id'] ?? (active_school_year()['id'] ?? 0));

$stmt = db()->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();
if (!$student) {
    flash('danger', 'Student not found.');
    redirect('/modules/reports/index.php');
}

$sy = db()->prepare('SELECT label FROM school_years WHERE id = :id');
$sy->execute(['id' => $schoolYearId]);
$schoolYear = $sy->fetchColumn();
if ($schoolYear === false) {
    flash('danger', 'School year not found.');
    redirect('/modules/reports/index.php');
}

// Grade level + section come from the enrollment for that year; fall back
// to the academic record (e.g. learners imported with grades only).
$enr = db()->prepare(
    'SELECT e.grade_level_id, g.name AS grade_name, sec.name AS section_name
     FROM enrollments e
     JOIN grade_levels g ON g.id = e.grade_level_id
     LEFT JOIN sections sec ON sec.id = e.section_id
     WHERE e.student_id = :sid AND e.school_year_id = :sy'
);
$enr->execute(['sid' => $studentId, 'sy' => $schoolYearId]);
$enrollment = $enr->fetch();

$ar = db()->prepare(
    'SELECT ar.*, g.name AS grade_name FROM academic_records ar
     JOIN grade_levels g ON g.id = ar.grade_level_id
     WHERE ar.student_id = :sid AND ar.school_year_id = :sy'
);
$ar->execute(['sid' => $studentId, 'sy' => $schoolYearId]);
$academic = $ar->fetch() ?: [];

$gradeLevelId = (int) ($enrollment['grade_level_id'] ?? ($academic['grade_level_id'] ?? 0));
$gradeName = $enrollment['grade_name'] ?? ($academic['grade_name'] ?? '');
$sectionName = $enrollment['section_name'] ?? '';

if ($gradeLevelId <= 0) {
    flash('danger', "This learner has no enrollment or academic record for SY {$schoolYear}.");
    redirect('/modules/reports/index.php');
}

/** DepEd Order 8, s. 2015 descriptor for a final rating. */
function sf9_descriptor(?float $rating): string
{
    if ($rating === null) {
        return '';
    }
    return match (true) {
        $rating >= 90 => 'Outstanding',
        $rating >= 85 => 'Very Satisfactory',
        $rating >= 80 => 'Satisfactory',
        $rating >= 75 => 'Fairly Satisfactory',
        default       => 'Did Not Meet Expectations',
    };
}

$entries = fetch_sf10_entries($studentId, $schoolYearId, $gradeLevelId);

// Final rating: use the stored value; otherwise derive it from all four
// quarters (rounded, as on the official card).
$rows = [];
$finals = [];
foreach (SF10_JHS_LEARNING_AREAS as $area) {
    $entry = $entries[$area] ?? [];
    $q = [];
    foreach (['q1_rating', 'q2_rating', 'q3_rating', 'q4_rating'] as $k) {
        $q[] = isset($entry[$k]) && $entry[$k] !== null ? (float) $entry[$k] : null;
    }
    $final = isset($entry['final_rating']) && $entry['final_rating'] !== null
        ? (float) $entry['final_rating']
        : (in_array(null, $q, true) ? null : round(array_sum($q) / 4));
    if ($final !== null) {
        $finals[] = $final;
    }
    $remarks = $entry['remarks'] ?? '';
    if ($remarks === '' && $final !== null) {
        $remarks = $final >= 75 ? 'Passed' : 'Failed';
    }
    $rows[] = ['area' => $area, 'q' => $q, 'final' => $final, 'remarks' => $remarks];
}
$generalAverage = count($finals) === count(SF10_JHS_LEARNING_AREAS)
    ? round(array_sum($finals) / count($finals))
    : null;
if ($generalAverage === null && isset($academic['general_average']) && $academic['general_average'] !== null) {
    $generalAverage = (float) $academic['general_average'];
}

// Rate-limited audit entry (DPA: report generation containing PII/grades).
$recent = db()->prepare(
    "SELECT 1 FROM audit_logs
      WHERE user_id = :uid AND entity_type = 'students' AND entity_id = :sid
        AND action = 'report_sf9' AND created_at > NOW() - INTERVAL '5 minutes'
      LIMIT 1"
);
$recent->execute(['uid' => (int) $_SESSION['user']['id'], 'sid' => $studentId]);
if (!$recent->fetch()) {
    audit_log('report_sf9', 'students', $studentId, "Generated SF9 report card (sy={$schoolYearId}, grade={$gradeLevelId})");
}

$fmt = static fn (?float $v): string => $v === null ? '' : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
$age = '';
if (!empty($student['birthdate'])) {
    try {
        $age = (string) (new DateTimeImmutable($student['birthdate']))->diff(new DateTimeImmutable('today'))->y;
    } catch (Exception) {
        $age = '';
    }
}

render_header('SF9 Report Card', 'reports');
?>
<div class="no-print mb-3 d-flex flex-wrap gap-2">
    <button type="button" onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print SF9</button>
    <a href="<?= e(url('/modules/records/sf10_edit.php?student_id=' . $studentId . '&school_year_id=' . $schoolYearId . '&grade_level_id=' . $gradeLevelId)) ?>"
       class="btn btn-outline-light">Edit Grades</a>
    <a href="<?= e(url('/modules/reports/index.php')) ?>" class="btn btn-outline-light">Back</a>
</div>

<div class="print-report">
    <div class="text-center mb-3">
        <p style="margin:0;">Republic of the Philippines</p>
        <p style="margin:0;">Department of Education</p>
        <p style="margin:0;"><?= e(APP_REGION) ?> · <?= e(lis_division()) ?></p>
        <p style="margin:0.4rem 0 0;"><strong><?= e(APP_SCHOOL) ?></strong></p>
        <p style="margin:0;"><?= e(APP_LOCATION) ?></p>
        <p style="margin:0.6rem 0 0; font-size:14pt;"><strong>LEARNER'S PROGRESS REPORT CARD</strong></p>
        <p style="margin:0;">SF9-JHS · School Year <?= e((string) $schoolYear) ?></p>
    </div>

    <table class="table table-bordered table-sm">
        <tr>
            <td width="18%">Name</td>
            <td colspan="3"><strong><?= e(strtoupper(trim($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['suffix'] ?? '')))) ?></strong></td>
        </tr>
        <tr>
            <td>LRN</td><td><?= e($student['lrn'] ?: '—') ?></td>
            <td width="18%">Student ID</td><td><?= e($student['student_id_no']) ?></td>
        </tr>
        <tr>
            <td>Age</td><td><?= e($age ?: '—') ?></td>
            <td>Sex</td><td><?= e($student['sex']) ?></td>
        </tr>
        <tr>
            <td>Grade</td><td><?= e($gradeName) ?></td>
            <td>Section</td><td><?= e($sectionName ?: '—') ?></td>
        </tr>
    </table>

    <p class="mt-3 mb-1"><strong>REPORT ON LEARNING PROGRESS AND ACHIEVEMENT</strong></p>
    <table class="table table-bordered table-sm text-center">
        <thead>
            <tr>
                <th rowspan="2" class="text-start align-middle">Learning Areas</th>
                <th colspan="4">Quarter</th>
                <th rowspan="2" class="align-middle">Final Rating</th>
                <th rowspan="2" class="align-middle">Remarks</th>
            </tr>
            <tr><th>1</th><th>2</th><th>3</th><th>4</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="text-start"><?= e($row['area']) ?></td>
                    <?php foreach ($row['q'] as $qv): ?>
                        <td><?= e($fmt($qv)) ?></td>
                    <?php endforeach; ?>
                    <td><strong><?= e($fmt($row['final'])) ?></strong></td>
                    <td><?= e($row['remarks']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="5" class="text-end"><strong>General Average</strong></td>
                <td><strong><?= e($fmt($generalAverage)) ?></strong></td>
                <td><?= e($generalAverage === null ? '' : ($generalAverage >= 75 ? 'Passed' : 'Failed')) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="table table-bordered table-sm" style="max-width:520px;">
        <thead><tr><th>Descriptor</th><th>Grading Scale</th><th>Remarks</th></tr></thead>
        <tbody>
            <tr><td>Outstanding</td><td>90–100</td><td>Passed</td></tr>
            <tr><td>Very Satisfactory</td><td>85–89</td><td>Passed</td></tr>
            <tr><td>Satisfactory</td><td>80–84</td><td>Passed</td></tr>
            <tr><td>Fairly Satisfactory</td><td>75–79</td><td>Passed</td></tr>
            <tr><td>Did Not Meet Expectations</td><td>Below 75</td><td>Failed</td></tr>
        </tbody>
    </table>
    <?php if ($generalAverage !== null): ?>
        <p>Descriptor: <strong><?= e(sf9_descriptor($generalAverage)) ?></strong></p>
    <?php endif; ?>

    <p class="mt-3 mb-1"><strong>ATTENDANCE AND OTHER INFORMATION</strong></p>
    <table class="table table-bordered table-sm">
        <tr><td width="40%">Days present</td><td><?= e(isset($academic['attendance_days']) && $academic['attendance_days'] !== null ? (string) $academic['attendance_days'] : '—') ?></td></tr>
        <tr><td>Awards / Honors</td><td><?= e(($academic['awards'] ?? '') ?: '—') ?></td></tr>
        <tr><td>Promotional status</td><td><strong><?= e(($academic['promotional_status'] ?? '') ?: '—') ?></strong></td></tr>
    </table>

    <p class="mt-3 mb-1"><strong>PARENT / GUARDIAN'S SIGNATURE</strong></p>
    <table class="table table-bordered table-sm">
        <tr><td width="25%">1st Quarter</td><td></td></tr>
        <tr><td>2nd Quarter</td><td></td></tr>
        <tr><td>3rd Quarter</td><td></td></tr>
        <tr><td>4th Quarter</td><td></td></tr>
    </table>

    <div class="d-flex justify-content-between mt-4" style="gap:2rem;">
        <div class="text-center" style="flex:1;">
            <p style="margin:2rem 0 0; border-top:1px solid #000;">Adviser</p>
        </div>
        <div class="text-center" style="flex:1;">
            <p style="margin:2rem 0 0; border-top:1px solid #000;">School Head / Principal</p>
        </div>
    </div>
</div>
<?php
render_footer();
