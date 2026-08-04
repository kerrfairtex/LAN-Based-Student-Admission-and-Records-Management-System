<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/layout.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM students WHERE id = :id');
$stmt->execute(['id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    flash('danger', 'Student record not found.');
    redirect('/modules/records/index.php');
}

$errors = [];
$input = [
    'lrn' => $student['lrn'] ?? '',
    'first_name' => $student['first_name'],
    'middle_name' => $student['middle_name'] ?? '',
    'last_name' => $student['last_name'],
    'suffix' => $student['suffix'] ?? '',
    'birthdate' => $student['birthdate'],
    'sex' => $student['sex'],
    'address' => $student['address'],
    'contact_number' => $student['contact_number'] ?? '',
    'guardian_name' => $student['guardian_name'],
    'guardian_relationship' => $student['guardian_relationship'],
    'guardian_contact' => $student['guardian_contact'],
    'previous_school' => $student['previous_school'] ?? '',
    'remarks' => $student['remarks'] ?? '',
    'status' => $student['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (array_keys($input) as $key) {
        $input[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    $errors = validate_required([
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

    if (!$errors) {
        $update = db()->prepare(
            'UPDATE students SET
                lrn = :lrn, first_name = :first_name, middle_name = :middle_name,
                last_name = :last_name, suffix = :suffix, birthdate = :birthdate,
                sex = :sex, address = :address, contact_number = :contact_number,
                guardian_name = :guardian_name, guardian_relationship = :guardian_relationship,
                guardian_contact = :guardian_contact, previous_school = :previous_school,
                remarks = :remarks, status = :status
             WHERE id = :id'
        );

        $update->execute([
            'lrn' => $input['lrn'] ?: null,
            'first_name' => $input['first_name'],
            'middle_name' => $input['middle_name'] ?: null,
            'last_name' => $input['last_name'],
            'suffix' => $input['suffix'] ?: null,
            'birthdate' => $input['birthdate'],
            'sex' => $input['sex'],
            'address' => $input['address'],
            'contact_number' => $input['contact_number'] ?: null,
            'guardian_name' => $input['guardian_name'],
            'guardian_relationship' => $input['guardian_relationship'],
            'guardian_contact' => $input['guardian_contact'],
            'previous_school' => $input['previous_school'] ?: null,
            'remarks' => $input['remarks'] ?: null,
            'status' => $input['status'],
            'id' => $id,
        ]);

        audit_log('update', 'students', $id, 'Updated student profile');
        flash('success', 'Student record updated successfully.');
        redirect('/modules/records/view.php?id=' . $id);
    }
}

render_header('Edit Student Record', 'records');
?>
<div class="panel-card glass-panel">
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Student ID</label>
                <input type="text" class="form-control" value="<?= e($student['student_id_no']) ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">LRN</label>
                <input type="text" name="lrn" maxlength="12" class="form-control <?= isset($errors['lrn']) ? 'is-invalid' : '' ?>" value="<?= e($input['lrn']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?= e($input['first_name']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middle_name" class="form-control" value="<?= e($input['middle_name']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?= e($input['last_name']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Suffix</label>
                <input type="text" name="suffix" class="form-control" value="<?= e($input['suffix']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Birthdate</label>
                <input type="date" name="birthdate" class="form-control" value="<?= e($input['birthdate']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sex</label>
                <select name="sex" class="form-select" required>
                    <option value="Male" <?= $input['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $input['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2" required><?= e($input['address']) ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-control" value="<?= e($input['contact_number']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Name</label>
                <input type="text" name="guardian_name" class="form-control" value="<?= e($input['guardian_name']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Relationship</label>
                <input type="text" name="guardian_relationship" class="form-control" value="<?= e($input['guardian_relationship']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Guardian Contact</label>
                <input type="text" name="guardian_contact" class="form-control" value="<?= e($input['guardian_contact']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Previous School</label>
                <input type="text" name="previous_school" class="form-control" value="<?= e($input['previous_school']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <?php foreach (['active', 'transferred', 'graduated', 'dropped'] as $status): ?>
                        <option value="<?= $status ?>" <?= $input['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" rows="3"><?= e($input['remarks']) ?></textarea>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="<?= e(url('/modules/records/view.php?id=<?= $id ?>')) ?>" class="btn btn-outline-light">Cancel</a>
        </div>
    </form>
</div>
<?php
render_footer();
