<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$admissionId = (int) ($_GET['id'] ?? 0);
if ($admissionId <= 0) {
    redirect('/modules/admission/index.php');
}

// Load existing admission
$stmt = db()->prepare(
    'SELECT a.*, u.full_name AS encoder_name
     FROM admissions a
     JOIN users u ON u.id = a.created_by
     WHERE a.id = :id'
);
$stmt->execute(['id' => $admissionId]);
$admission = $stmt->fetch();

if (!$admission) {
    flash('danger', 'Admission application not found.');
    redirect('/modules/admission/index.php');
}

// Only allow editing of pending admissions
if ($admission['status'] !== 'pending') {
    flash('danger', 'Only pending applications can be edited. This application is ' . $admission['status'] . '.');
    redirect('/modules/admission/view.php?id=' . $admissionId);
}

// Decode documents_submitted JSON
$documents = json_decode($admission['documents_submitted'] ?? '{}', true) ?: [];

$input = [
    'school_year_id' => (string) $admission['school_year_id'],
    'grade_level_id' => (string) $admission['grade_level_id'],
    'enrollment_type' => $admission['enrollment_type'],
    'first_name' => $admission['first_name'],
    'middle_name' => $admission['middle_name'] ?? '',
    'last_name' => $admission['last_name'],
    'suffix' => $admission['suffix'] ?? '',
    'lrn' => $admission['lrn'] ?? '',
    'birthdate' => $admission['birthdate'],
    'sex' => $admission['sex'],
    'address' => $admission['address'],
    'contact_number' => $admission['contact_number'] ?? '',
    'guardian_name' => $admission['guardian_name'],
    'guardian_relationship' => $admission['guardian_relationship'],
    'guardian_contact' => $admission['guardian_contact'],
    'previous_school' => $admission['previous_school'] ?? '',
    'psa_birth_cert' => ($documents['psa_birth_certificate'] ?? false) ? '1' : '',
    'report_card' => ($documents['report_card'] ?? false) ? '1' : '',
    'good_moral' => ($documents['good_moral'] ?? false) ? '1' : '',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (array_keys($input) as $key) {
        $input[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $errors = validate_required([
        'school_year_id' => 'School year',
        'grade_level_id' => 'Grade level',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'birthdate' => 'Birthdate',
        'sex' => 'Sex',
        'address' => 'Address',
        'guardian_name' => 'Guardian name',
        'guardian_relationship' => 'Guardian relationship',
        'guardian_contact' => 'Guardian contact',
    ], $input);

    if ($input['lrn'] !== '' && !validate_lrn($input['lrn'])) {
        $errors['lrn'] = 'LRN must be exactly 12 digits.';
    }

    if (!in_array($input['enrollment_type'], ['new', 'returning', 'transferee'], true)) {
        $errors['enrollment_type'] = 'Invalid enrollment type.';
    }

    if (!$errors) {
        $docs = [
            'psa_birth_certificate' => $input['psa_birth_cert'] === '1',
            'report_card' => $input['report_card'] === '1',
            'good_moral' => $input['good_moral'] === '1',
        ];

        db()->prepare(
            'UPDATE admissions SET
                school_year_id = :school_year_id, grade_level_id = :grade_level_id,
                enrollment_type = :enrollment_type, first_name = :first_name,
                middle_name = :middle_name, last_name = :last_name, suffix = :suffix,
                lrn = :lrn, birthdate = :birthdate, sex = :sex, address = :address,
                contact_number = :contact_number, guardian_name = :guardian_name,
                guardian_relationship = :guardian_relationship, guardian_contact = :guardian_contact,
                previous_school = :previous_school, documents_submitted = :documents,
                updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'school_year_id' => (int) $input['school_year_id'],
            'grade_level_id' => (int) $input['grade_level_id'],
            'enrollment_type' => $input['enrollment_type'],
            'first_name' => $input['first_name'],
            'middle_name' => $input['middle_name'] ?: null,
            'last_name' => $input['last_name'],
            'suffix' => $input['suffix'] ?: null,
            'lrn' => $input['lrn'] ?: null,
            'birthdate' => $input['birthdate'],
            'sex' => $input['sex'],
            'address' => $input['address'],
            'contact_number' => $input['contact_number'] ?: null,
            'guardian_name' => $input['guardian_name'],
            'guardian_relationship' => $input['guardian_relationship'],
            'guardian_contact' => $input['guardian_contact'],
            'previous_school' => $input['previous_school'] ?: null,
            'documents_submitted' => json_encode($docs),
            'id' => $admissionId,
        ]);

        audit_log('update', 'admissions', $admissionId, "Updated application {$admission['application_no']}");
        flash('success', "Application {$admission['application_no']} updated successfully.");
        redirect('/modules/admission/view.php?id=' . $admissionId);
    }
}

$schoolYears = fetch_school_years();
$gradeLevels = fetch_grade_levels();

render_header('Edit Admission', 'admission');
?>
<div class="panel-card glass-panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Edit Application <?= e($admission['application_no']) ?></h3>
        <span class="badge badge-status-<?= e($admission['status']) ?>"><?= e(ucfirst($admission['status'])) ?></span>
    </div>
    <p class="text-muted small mb-3">Encoded by <?= e($admission['encoder_name']) ?> on <?= e($admission['created_at']) ?></p>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">School Year</label>
                <select name="school_year_id" class="form-select <?= isset($errors['school_year_id']) ? 'is-invalid' : '' ?>" required>
                    <option value="">Select school year</option>
                    <?php foreach ($schoolYears as $year): ?>
                        <option value="<?= (int) $year['id'] ?>" <?= $input['school_year_id'] === (string) $year['id'] ? 'selected' : '' ?>><?= e($year['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Grade Level</label>
                <select name="grade_level_id" class="form-select <?= isset($errors['grade_level_id']) ? 'is-invalid' : '' ?>" required>
                    <option value="">Select grade</option>
                    <?php foreach ($gradeLevels as $grade): ?>
                        <option value="<?= (int) $grade['id'] ?>" <?= $input['grade_level_id'] === (string) $grade['id'] ? 'selected' : '' ?>><?= e($grade['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Enrollment Type</label>
                <select name="enrollment_type" class="form-select" required>
                    <option value="new" <?= $input['enrollment_type'] === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="returning" <?= $input['enrollment_type'] === 'returning' ? 'selected' : '' ?>>Returning</option>
                    <option value="transferee" <?= $input['enrollment_type'] === 'transferee' ? 'selected' : '' ?>>Transferee</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" value="<?= e($input['first_name']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middle_name" class="form-control" value="<?= e($input['middle_name']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" value="<?= e($input['last_name']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Suffix</label>
                <input type="text" name="suffix" class="form-control" value="<?= e($input['suffix']) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">LRN (12 digits)</label>
                <input type="text" name="lrn" maxlength="12" pattern="\d{12}" class="form-control <?= isset($errors['lrn']) ? 'is-invalid' : '' ?>" value="<?= e($input['lrn']) ?>">
                <?php if (isset($errors['lrn'])): ?><div class="invalid-feedback"><?= e($errors['lrn']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Birthdate</label>
                <input type="date" name="birthdate" class="form-control <?= isset($errors['birthdate']) ? 'is-invalid' : '' ?>" value="<?= e($input['birthdate']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Sex</label>
                <select name="sex" class="form-select <?= isset($errors['sex']) ? 'is-invalid' : '' ?>" required>
                    <option value="">Select</option>
                    <option value="Male" <?= $input['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $input['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Address</label>
                <textarea name="address" rows="2" class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" required><?= e($input['address']) ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-control" value="<?= e($input['contact_number']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Name</label>
                <input type="text" name="guardian_name" class="form-control <?= isset($errors['guardian_name']) ? 'is-invalid' : '' ?>" value="<?= e($input['guardian_name']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Relationship</label>
                <input type="text" name="guardian_relationship" class="form-control <?= isset($errors['guardian_relationship']) ? 'is-invalid' : '' ?>" value="<?= e($input['guardian_relationship']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Contact</label>
                <input type="text" name="guardian_contact" class="form-control <?= isset($errors['guardian_contact']) ? 'is-invalid' : '' ?>" value="<?= e($input['guardian_contact']) ?>" required>
            </div>
            <div class="col-md-8">
                <label class="form-label">Previous School (if transferee)</label>
                <input type="text" name="previous_school" class="form-control" value="<?= e($input['previous_school']) ?>">
            </div>

            <div class="col-12">
                <label class="form-label d-block">Documents Submitted</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="psa_birth_cert" value="1" id="psa" <?= $input['psa_birth_cert'] === '1' ? 'checked' : '' ?>>

                    <label class="form-check-label" for="psa">PSA Birth Certificate</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="report_card" value="1" id="rc" <?= $input['report_card'] === '1' ? 'checked' : '' ?>>

                    <label class="form-check-label" for="rc">Report Card / SF9</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="good_moral" value="1" id="gm" <?= $input['good_moral'] === '1' ? 'checked' : '' ?>>

                    <label class="form-check-label" for="gm">Good Moral Character</label>
                </div>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger mt-3">Please correct the highlighted fields.</div>
        <?php endif; ?>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= e(url('/modules/admission/view.php?id=' . $admissionId)) ?>" class="btn btn-outline-light">Cancel</a>
        </div>
    </form>
</div>
<?php
render_footer();