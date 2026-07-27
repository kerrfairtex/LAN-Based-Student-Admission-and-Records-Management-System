<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$errors = [];
$input = [
    'school_year_id' => (string) (active_school_year()['id'] ?? ''),
    'grade_level_id' => '',
    'enrollment_type' => 'new',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'suffix' => '',
    'lrn' => '',
    'birthdate' => '',
    'sex' => '',
    'address' => '',
    'contact_number' => '',
    'guardian_name' => '',
    'guardian_relationship' => '',
    'guardian_contact' => '',
    'previous_school' => '',
    'psa_birth_cert' => '',
    'report_card' => '',
    'good_moral' => '',
];

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
        $documents = [
            'psa_birth_certificate' => $input['psa_birth_cert'] === '1',
            'report_card' => $input['report_card'] === '1',
            'good_moral' => $input['good_moral'] === '1',
        ];

        $stmt = db()->prepare(
            'INSERT INTO admissions (
                application_no, school_year_id, grade_level_id, enrollment_type,
                first_name, middle_name, last_name, suffix, lrn, birthdate, sex,
                address, contact_number, guardian_name, guardian_relationship,
                guardian_contact, previous_school, documents_submitted, created_by
            ) VALUES (
                :application_no, :school_year_id, :grade_level_id, :enrollment_type,
                :first_name, :middle_name, :last_name, :suffix, :lrn, :birthdate, :sex,
                :address, :contact_number, :guardian_name, :guardian_relationship,
                :guardian_contact, :previous_school, :documents_submitted, :created_by
            )'
        );

        $applicationNo = generate_application_no();

        $stmt->execute([
            'application_no' => $applicationNo,
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
            'documents_submitted' => json_encode($documents),
            'created_by' => (int) $_SESSION['user']['id'],
        ]);

        $admissionId = (int) db()->lastInsertId();
        audit_log('create', 'admissions', $admissionId, "Created application {$applicationNo}");
        flash('success', "Application {$applicationNo} encoded successfully.");
        redirect('/modules/admission/view.php?id=' . $admissionId);
    }
}

$schoolYears = fetch_school_years();
$gradeLevels = fetch_grade_levels();

render_header('New Admission', 'admission');
?>
<div class="panel-card glass-panel">
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
            <button type="submit" class="btn btn-primary">Submit Application</button>
            <a href="<?= e(url('/modules/admission/index.php')) ?>" class="btn btn-outline-light">Cancel</a>
        </div>
    </form>
</div>
<?php
render_footer();
