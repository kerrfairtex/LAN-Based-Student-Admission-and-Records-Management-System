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
            <a href="/modules/reports/enrollment_summary.php" class="btn btn-primary">Generate Report</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel-card glass-panel h-100">
            <h3>Admission Status Report</h3>
            <p class="text-muted">List of pending, approved, and rejected admission applications.</p>
            <a href="/modules/reports/admission_status.php" class="btn btn-primary">Generate Report</a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel-card glass-panel h-100">
            <h3>Student Master List</h3>
            <p class="text-muted">Complete list of active students with ID numbers and LRN.</p>
            <a href="/modules/reports/student_masterlist.php" class="btn btn-primary">Generate Report</a>
        </div>
    </div>
</div>
<?php
render_footer();
