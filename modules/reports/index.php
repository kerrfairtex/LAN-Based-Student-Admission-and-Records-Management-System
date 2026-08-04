<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

render_header('Reporting', 'reports');
?>
<p class="text-muted">Generate printable institutional reports formatted in Times New Roman, Size 12.</p>

<div class="row g-3">
    <div class="col-md-6">
        <div class="panel-card glass-panel h-100">
            <h3>Enrollment Summary</h3>
            <p class="text-muted">Summary of enrolled students by grade level for the active school year.</p>
            <a href="<?= e(url('/modules/reports/enrollment_summary.php')) ?>" class="btn btn-primary">Generate Report</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel-card glass-panel h-100">
            <h3>Admission Status Report</h3>
            <p class="text-muted">List of pending, approved, and rejected admission applications.</p>
            <a href="<?= e(url('/modules/reports/admission_status.php')) ?>" class="btn btn-primary">Generate Report</a>
        </div>
    </div>
        <div class="col-md-6">
        <div class="panel-card glass-panel h-100">
            <h3>Student Master List</h3>
            <p class="text-muted">Complete list of active students with ID numbers and LRN.</p>
            <a href="<?= e(url('/modules/reports/student_masterlist.php')) ?>" class="btn btn-primary">Generate Report</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel-card glass-panel h-100">
            <h3>SF10-JHS Permanent Record</h3>
            <p class="text-muted">DepEd School Form 10 (formerly Form 137) for Junior High School learners.</p>
            <form action="/modules/reports/sf10.php" method="get" class="row g-2">
                <div class="col-12">
                    <select name="student_id" class="form-select" required>
                        <option value="">Select student</option>
                        <?php
                        $reportStudents = db()->query(
                            "SELECT id, student_id_no, CONCAT(last_name, ', ', first_name) AS name FROM students WHERE status = 'active' ORDER BY last_name"
                        )->fetchAll();
                        foreach ($reportStudents as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['student_id_no']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <select name="school_year_id" class="form-select" required>
                        <?php foreach (fetch_school_years() as $year): ?>
                            <option value="<?= (int) $year['id'] ?>" <?= !empty($year['is_active']) ? 'selected' : '' ?>><?= e($year['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <select name="grade_level_id" class="form-select" required>
                        <?php foreach (fetch_grade_levels() as $grade): ?>
                            <option value="<?= (int) $grade['id'] ?>"><?= e($grade['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Generate SF10</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
render_footer();
