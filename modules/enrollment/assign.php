<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT e.*, s.student_id_no, s.lrn,
            CONCAT(s.last_name, ", ", s.first_name) AS student_name,
            g.name AS grade_name, g.id AS grade_level_id,
            sec.name AS section_name, sy.label AS school_year
     FROM enrollments e
     JOIN students s ON s.id = e.student_id
     JOIN grade_levels g ON g.id = e.grade_level_id
     JOIN school_years sy ON sy.id = e.school_year_id
     LEFT JOIN sections sec ON sec.id = e.section_id
     WHERE e.id = :id'
);
$stmt->execute(['id' => $id]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    flash('danger', 'Enrollment record not found.');
    redirect('/modules/enrollment/index.php');
}

$sections = fetch_sections((int) $enrollment['grade_level_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sectionId = (int) ($_POST['section_id'] ?? 0);

    if ($sectionId <= 0) {
        flash('danger', 'Please select a section.');
        redirect('/modules/enrollment/assign.php?id=' . $id);
    }

    $update = db()->prepare('UPDATE enrollments SET section_id = :section_id WHERE id = :id');
    $update->execute(['section_id' => $sectionId, 'id' => $id]);

    audit_log('update', 'enrollments', $id, 'Assigned section to enrollment');
    flash('success', 'Section assigned successfully.');
    redirect('/modules/enrollment/index.php');
}

render_header('Assign Section', 'enrollment');
?>
<div class="panel-card glass-panel">
    <p class="text-muted mb-3">
        <strong><?= e($enrollment['student_name']) ?></strong>
        (<?= e($enrollment['student_id_no']) ?>) — <?= e($enrollment['grade_name']) ?>, SY <?= e($enrollment['school_year']) ?>
    </p>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Current Section</label>
            <input type="text" class="form-control" value="<?= e($enrollment['section_name'] ?: 'Unassigned') ?>" disabled>
        </div>
        <div class="mb-3">
            <label class="form-label">Assign Section</label>
            <select name="section_id" class="form-select" required>
                <option value="">Select section</option>
                <?php foreach ($sections as $section): ?>
                    <option value="<?= (int) $section['id'] ?>" <?= (int) $enrollment['section_id'] === (int) $section['id'] ? 'selected' : '' ?>>
                        <?= e($section['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Assignment</button>
            <a href="/modules/enrollment/index.php" class="btn btn-outline-light">Cancel</a>
        </div>
    </form>
</div>
<?php
render_footer();
