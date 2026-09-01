<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT a.*, sy.label AS school_year, g.name AS grade_name,
            u.full_name AS encoder_name, r.full_name AS reviewer_name
     FROM admissions a
     JOIN school_years sy ON sy.id = a.school_year_id
     JOIN grade_levels g ON g.id = a.grade_level_id
     JOIN users u ON u.id = a.created_by
     LEFT JOIN users r ON r.id = a.reviewed_by
     WHERE a.id = :id'
);
$stmt->execute(['id' => $id]);
$admission = $stmt->fetch();

if (!$admission) {
    flash('danger', 'Admission application not found.');
    redirect('/modules/admission/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_registrar()) {
    require_csrf();

    $action = $_POST['action'] ?? '';
    $notes = trim($_POST['review_notes'] ?? '');

    if ($action === 'approve' && $admission['status'] === 'pending') {
        $sectionId = (int) ($_POST['section_id'] ?? 0) ?: null;

        // Guardrail: if section_id was submitted, it must belong to the
        // admission's grade level. The dropdown is pre-filtered, but a
        // tampered POST could submit any section_id.
        if ($sectionId !== null) {
            $sectionCheck = db()->prepare(
                'SELECT id FROM sections WHERE id = :id AND grade_level_id = :grade_level_id'
            );
            $sectionCheck->execute([
                'id' => $sectionId,
                'grade_level_id' => (int) $admission['grade_level_id'],
            ]);
            if (!$sectionCheck->fetch()) {
                flash('danger', 'Selected section does not belong to the application\'s grade level.');
                redirect('/modules/admission/view.php?id=' . $id);
            }
        }

        // LRN collision guard: if this LRN already belongs to a student, refuse
        // rather than mid-transaction INSERT-failing on uq_students_lrn.
        if (!empty($admission['lrn'])) {
            $existingStudent = db()->prepare('SELECT 1 FROM students WHERE lrn = :lrn LIMIT 1');
            $existingStudent->execute(['lrn' => $admission['lrn']]);
            if ($existingStudent->fetch()) {
                flash('danger', 'A student with this LRN already exists. Resolve the duplicate before approving.');
                redirect('/modules/admission/view.php?id=' . $id);
            }
        }

        // Duplicate-transfer guard: don't create a second open transfer for
        // the same student + direction. (Belt-and-suspenders; the schema
        // doesn't enforce this and we already guard in transfers/create.php.)
        if ($admission['enrollment_type'] === 'transferee' && $admission['previous_school']) {
            // Defer until after we know the student_id; check below.
        }

        $studentIdNo = generate_student_id();
        $studentId = 0;
        $committed = false;

        // Retry the whole transaction on unique-violation. generate_student_id
        // uses COUNT(*)+1 and can race under concurrent approvals.
        $attempts = 0;
        while ($attempts < 5 && !$committed) {
            try {
                db()->beginTransaction();

                $insertStudent = db()->prepare(
                    'INSERT INTO students (
                        student_id_no, lrn, first_name, middle_name, last_name, suffix,
                        birthdate, sex, address, contact_number, guardian_name,
                        guardian_relationship, guardian_contact, previous_school, created_by
                    ) VALUES (
                        :student_id_no, :lrn, :first_name, :middle_name, :last_name, :suffix,
                        :birthdate, :sex, :address, :contact_number, :guardian_name,
                        :guardian_relationship, :guardian_contact, :previous_school, :created_by
                    )'
                );

                $insertStudent->execute([
                    'student_id_no' => $studentIdNo,
                    'lrn' => $admission['lrn'],
                    'first_name' => $admission['first_name'],
                    'middle_name' => $admission['middle_name'],
                    'last_name' => $admission['last_name'],
                    'suffix' => $admission['suffix'],
                    'birthdate' => $admission['birthdate'],
                    'sex' => $admission['sex'],
                    'address' => $admission['address'],
                    'contact_number' => $admission['contact_number'],
                    'guardian_name' => $admission['guardian_name'],
                    'guardian_relationship' => $admission['guardian_relationship'],
                    'guardian_contact' => $admission['guardian_contact'],
                    'previous_school' => $admission['previous_school'],
                    'created_by' => (int) $_SESSION['user']['id'],
                ]);

                $studentId = (int) db()->lastInsertId();

                $insertEnrollment = db()->prepare(
                    'INSERT INTO enrollments (
                        student_id, school_year_id, grade_level_id, section_id, enrollment_type,
                        enrolled_at, created_by
                    ) VALUES (
                        :student_id, :school_year_id, :grade_level_id, :section_id, :enrollment_type,
                        :enrolled_at, :created_by
                    )'
                );

                $insertEnrollment->execute([
                    'student_id' => $studentId,
                    'school_year_id' => (int) $admission['school_year_id'],
                    'grade_level_id' => (int) $admission['grade_level_id'],
                    'section_id' => $sectionId,
                    'enrollment_type' => $admission['enrollment_type'],
                    'enrolled_at' => date('Y-m-d'),
                    'created_by' => (int) $_SESSION['user']['id'],
                ]);

                if ($admission['enrollment_type'] === 'transferee' && $admission['previous_school']) {
                    // Duplicate-transfer guard for this student.
                    $existingTransfer = db()->prepare(
                        "SELECT 1 FROM transfer_requests
                          WHERE student_id = :student_id
                            AND direction = 'incoming'
                            AND status NOT IN ('completed', 'escalated')
                          LIMIT 1"
                    );
                    $existingTransfer->execute(['student_id' => $studentId]);
                    if ($existingTransfer->fetch()) {
                        db()->rollBack();
                        flash('danger', 'This student already has an open incoming transfer request.');
                        redirect('/modules/admission/view.php?id=' . $id);
                    }

                    $dueDate = date('Y-m-d', strtotime('+30 days'));
                    $transfer = db()->prepare(
                        'INSERT INTO transfer_requests (
                            student_id, direction, counterpart_school, request_date,
                            first_attendance_date, due_date, status, created_by
                        ) VALUES (
                            :student_id, \'incoming\', :counterpart_school, :request_date,
                            :first_attendance_date, :due_date, \'pending\', :created_by
                        )'
                    );
                    $transfer->execute([
                        'student_id' => $studentId,
                        'counterpart_school' => $admission['previous_school'],
                        'request_date' => date('Y-m-d'),
                        'first_attendance_date' => date('Y-m-d'),
                        'due_date' => $dueDate,
                        'created_by' => (int) $_SESSION['user']['id'],
                    ]);
                }

                $updateAdmission = db()->prepare(
                    'UPDATE admissions
                     SET status = \'approved\', student_id = :student_id, reviewed_by = :reviewed_by,
                         reviewed_at = NOW(), review_notes = :review_notes
                     WHERE id = :id'
                );

                $updateAdmission->execute([
                    'student_id' => $studentId,
                    'reviewed_by' => (int) $_SESSION['user']['id'],
                    'review_notes' => $notes ?: null,
                    'id' => $id,
                ]);

                db()->commit();
                $committed = true;
            } catch (PDOException $e) {
                db()->rollBack();
                $isUniqueViolation =
                    $e->getCode() === '23505' ||
                    (strpos($e->getMessage(), '23505') !== false) ||
                    $e->getCode() === '1062' ||
                    (strpos($e->getMessage(), '1062') !== false);

                if (!$isUniqueViolation || ++$attempts >= 5) {
                    flash('danger', 'Unable to approve application: ' . $e->getMessage());
                    redirect('/modules/admission/view.php?id=' . $id);
                }

                // Regenerate student_id_no and retry.
                $studentIdNo = generate_student_id();
            }
        }

        if (!$committed) {
            flash('danger', 'Unable to approve application after multiple attempts. Please try again.');
            redirect('/modules/admission/view.php?id=' . $id);
        }

        audit_log('approve', 'admissions', $id, "Approved and enrolled as {$studentIdNo}");
        flash('success', "Application approved. Student ID: {$studentIdNo}");
        redirect('/modules/records/view.php?id=' . $studentId);
    }

    if ($action === 'reject' && $admission['status'] === 'pending') {
        $stmt = db()->prepare(
            'UPDATE admissions
             SET status = \'rejected\', reviewed_by = :reviewed_by,
                 reviewed_at = NOW(), review_notes = :review_notes
             WHERE id = :id'
        );
        $stmt->execute([
            'reviewed_by' => (int) $_SESSION['user']['id'],
            'review_notes' => $notes ?: null,
            'id' => $id,
        ]);

        audit_log('reject', 'admissions', $id, 'Application rejected');
        flash('warning', 'Application has been rejected.');
        redirect('/modules/admission/view.php?id=' . $id);
    }
}

// Rate-limited audit-log of view access (5-minute window per user/admission).
$recent = db()->prepare(
    'SELECT 1 FROM audit_logs
      WHERE user_id = :user_id
        AND entity_type = \'admissions\'
        AND entity_id = :entity_id
        AND action = \'view\'
        AND created_at > NOW() - INTERVAL \'5 minutes\'
      LIMIT 1'
);
$recent->execute([
    'user_id'    => (int) $_SESSION['user']['id'],
    'entity_id'  => $id,
]);
if (!$recent->fetch()) {
    audit_log('view', 'admissions', $id, 'Viewed admission application');
}

$documents = json_decode($admission['documents_submitted'] ?? '{}', true) ?: [];
$sections = fetch_sections((int) $admission['grade_level_id']);

render_header('Admission Details', 'admission');
?>
<div class="panel-card glass-panel mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><?= e($admission['application_no']) ?></h3>
            <p class="text-muted mb-0">
                <?= e(trim("{$admission['last_name']}, {$admission['first_name']} {$admission['middle_name']}")) ?>
            </p>
        </div>
        <span class="badge badge-status-<?= e($admission['status']) ?>"><?= e(ucfirst($admission['status'])) ?></span>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="panel-card glass-panel">
            <h3>Applicant Information</h3>
            <div class="row g-2">
                <div class="col-md-6"><strong>LRN:</strong> <?= e($admission['lrn'] ?: '—') ?></div>
                <div class="col-md-6"><strong>Birthdate:</strong> <?= e($admission['birthdate']) ?></div>
                <div class="col-md-6"><strong>Sex:</strong> <?= e($admission['sex']) ?></div>
                <div class="col-md-6"><strong>Grade:</strong> <?= e($admission['grade_name']) ?></div>
                <div class="col-md-6"><strong>School Year:</strong> <?= e($admission['school_year']) ?></div>
                <div class="col-md-6"><strong>Type:</strong> <?= e(ucfirst($admission['enrollment_type'])) ?></div>
                <div class="col-12"><strong>Address:</strong> <?= e($admission['address']) ?></div>
                <div class="col-md-6"><strong>Contact:</strong> <?= e($admission['contact_number'] ?: '—') ?></div>
                <div class="col-md-6"><strong>Previous School:</strong> <?= e($admission['previous_school'] ?: '—') ?></div>
            </div>

            <hr>
            <h3>Guardian</h3>
            <div class="row g-2">
                <div class="col-md-6"><strong>Name:</strong> <?= e($admission['guardian_name']) ?></div>
                <div class="col-md-6"><strong>Relationship:</strong> <?= e($admission['guardian_relationship']) ?></div>
                <div class="col-md-6"><strong>Contact:</strong> <?= e($admission['guardian_contact']) ?></div>
            </div>

            <hr>
            <h3>Documents</h3>
            <ul class="mb-0">
                <li>PSA Birth Certificate: <?= !empty($documents['psa_birth_certificate']) ? 'Submitted' : 'Not submitted' ?></li>
                <li>Report Card / SF9: <?= !empty($documents['report_card']) ? 'Submitted' : 'Not submitted' ?></li>
                <li>Good Moral Character: <?= !empty($documents['good_moral']) ? 'Submitted' : 'Not submitted' ?></li>
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="panel-card glass-panel mb-3">
            <h3>Processing</h3>
            <p class="text-muted mb-1"><strong>Encoded by:</strong> <?= e($admission['encoder_name']) ?></p>
            <p class="text-muted mb-1"><strong>Created:</strong> <?= e($admission['created_at']) ?></p>
            <?php if ($admission['reviewer_name']): ?>
                <p class="text-muted mb-1"><strong>Reviewed by:</strong> <?= e($admission['reviewer_name']) ?></p>
                <p class="text-muted mb-0"><strong>Reviewed at:</strong> <?= e($admission['reviewed_at']) ?></p>
            <?php endif; ?>
            <?php if ($admission['review_notes']): ?>
                <hr>
                <p class="mb-0"><strong>Notes:</strong> <?= e($admission['review_notes']) ?></p>
            <?php endif; ?>
        </div>

        <?php if (is_registrar() && $admission['status'] === 'pending'): ?>
            <div class="panel-card glass-panel">
                <h3>Registrar Action</h3>
                <form method="post">
        <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Section Assignment</label>
                        <select name="section_id" class="form-select">
                            <option value="">Unassigned (assign later)</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= (int) $section['id'] ?>"><?= e($section['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Review Notes</label>
                        <textarea name="review_notes" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="action" value="approve" class="btn btn-primary">Approve & Enroll</button>
                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger">Reject Application</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($admission['student_id']): ?>
            <a href="<?= e(url('/modules/records/view.php?id=' . (int) $admission['student_id'])) ?>" class="btn btn-outline-light w-100 mt-3">View Student Record</a>
        <?php endif; ?>

        <?php if ($admission['status'] === 'pending'): ?>
            <a href="<?= e(url('/modules/admission/edit.php?id=' . (int) $admission['id'])) ?>" class="btn btn-outline-light w-100 mt-2">
                <i class="bi bi-pencil"></i> Edit Application
            </a>
        <?php endif; ?>
    </div>
</div>
<?php
render_footer();
